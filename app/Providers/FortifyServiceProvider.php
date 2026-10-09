<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::query()->where('email', Str::lower($request->string('email')->toString()))->first();

            if ($user && $user->is_active && Hash::check($request->string('password')->toString(), $user->password)) {
                $user->forceFill(['last_login_at' => now()])->save();

                return $user;
            }

            return null;
        });

        Fortify::loginView(fn () => Inertia::render('auth/login', [
            'canResetPassword' => true,
            'status' => session('status'),
        ]));
        Fortify::requestPasswordResetLinkView(fn () => Inertia::render('auth/forgot-password', [
            'status' => session('status'),
        ]));
        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->string('email')->toString(),
            'token' => $request->route('token'),
        ]));
        Fortify::verifyEmailView(fn () => Inertia::render('auth/verify-email', [
            'status' => session('status'),
        ]));
        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip()
            );
        });
    }
}
