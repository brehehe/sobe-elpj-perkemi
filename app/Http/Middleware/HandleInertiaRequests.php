<?php

namespace App\Http\Middleware;

use App\Enums\GovernancePolicyType;
use App\Services\GovernancePolicyRegistry;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $systemPolicy = app(GovernancePolicyRegistry::class)->get(GovernancePolicyType::System);

        return [
            ...parent::share($request),
            'app' => ['name' => 'Smart e-LPJ SOBE'],
            'system' => [
                'policy_version' => $systemPolicy['version'],
                'data_classification' => $systemPolicy['data_classification'],
                'maintenance_message' => $systemPolicy['maintenance_message'] ?? null,
                'support_message' => $systemPolicy['support_message'],
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'unit' => $user->unit?->only(['id', 'code', 'name']),
                    'roles' => $user->getRoleNames()->values(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->values(),
                ] : null,
            ],
            'notifications' => fn () => $user?->unreadNotifications()->latest()->limit(8)->get()->map(fn ($notification): array => [
                'id' => $notification->id,
                'data' => $notification->data,
                'created_at' => $notification->created_at?->diffForHumans(),
            ])->values() ?? [],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
