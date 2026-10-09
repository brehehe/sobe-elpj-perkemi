<?php

namespace App\Providers;

use App\Actions\RecordAuditEventAction;
use App\Contracts\AiReviewer;
use App\Contracts\EvidenceScanner;
use App\Enums\GovernancePolicyType;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\ClamAvEvidenceScanner;
use App\Services\GovernancePolicyRegistry;
use App\Services\OpenAiReviewer;
use App\Services\SignatureEvidenceScanner;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AiReviewer::class, OpenAiReviewer::class);
        $this->app->bind(EvidenceScanner::class, function ($app): EvidenceScanner {
            return match (config('evidence.scanner')) {
                'signature' => $app->make(SignatureEvidenceScanner::class),
                'clamav' => $app->make(ClamAvEvidenceScanner::class),
                default => throw new InvalidArgumentException('Evidence scanner driver is not supported.'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DevCommands::artisan('queue:listen --queue=ai,broadcasts,default --tries=4 --timeout=300', 'queue');
        DevCommands::artisan('reverb:start', 'reverb');

        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Gate::before(fn (User $user): ?bool => $user->isGlobalAdministrator() ? true : null);
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(90)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('ai', function (Request $request): array {
            $policy = app(GovernancePolicyRegistry::class)->get(GovernancePolicyType::Ai);
            $userKey = $request->user()?->id ?: $request->ip();
            $unitKey = $request->user()?->unit_id ?: 'unassigned';

            return [
                Limit::perMinute(max(1, (int) ($policy['user_rate_per_minute'] ?? 10)))->by('ai:user:'.$userKey),
                Limit::perMinute(max(1, (int) ($policy['unit_rate_per_minute'] ?? 30)))->by('ai:unit:'.$unitKey),
                Limit::perMinute(max(1, (int) ($policy['organization_rate_per_minute'] ?? 120)))->by('ai:organization'),
            ];
        });

        Event::listen(Login::class, function (Login $event): void {
            if (! $event->user instanceof User) {
                return;
            }
            app(RecordAuditEventAction::class)->handle(
                $event->user,
                'auth.login_succeeded',
                $event->user,
                payload: ['guard' => $event->guard, 'remember' => $event->remember],
                authority: 'auth.login',
            );
        });

        Event::listen(Failed::class, function (Failed $event): void {
            if ($event->user instanceof User) {
                app(RecordAuditEventAction::class)->handle(
                    $event->user,
                    'auth.login_failed',
                    $event->user,
                    payload: ['guard' => $event->guard, 'identifier_hash' => hash('sha256', (string) ($event->credentials['email'] ?? ''))],
                    result: 'FAILED',
                    authority: 'auth.login',
                );

                return;
            }

            $correlationId = request()->header('X-Correlation-ID') ?: (string) Str::uuid7();
            AuditEvent::create([
                'event_id' => (string) Str::uuid7(),
                'correlation_id' => $correlationId,
                'request_id' => Str::limit(request()->header('X-Request-ID') ?: (string) Str::uuid7(), 100, ''),
                'event_type' => 'auth.login_failed',
                'source_channel' => 'WEB',
                'authority' => 'GUEST',
                'payload_version' => 1,
                'result' => 'FAILED',
                'payload' => ['guard' => $event->guard, 'identifier_hash' => hash('sha256', (string) ($event->credentials['email'] ?? ''))],
                'evidence_references' => [],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });
    }
}
