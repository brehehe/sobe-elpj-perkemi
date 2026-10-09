<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->web(append: [HandleInertiaRequests::class]);
        $middleware->alias([
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->dontReportDuplicates();

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            $requestId = AssignRequestId::resolve($request);
            $usesApiContract = $request->is('api/*') || $request->expectsJson();

            if ($usesApiContract) {
                return AssignRequestId::decorate($response, $requestId, true);
            }

            if ($response->getStatusCode() === 419 && $request->hasSession()) {
                return AssignRequestId::decorate(
                    back()->with('error', 'Sesi halaman berakhir. Muat ulang lalu ulangi tindakan Anda.'),
                    $requestId,
                    false,
                );
            }

            $status = $response->getStatusCode();
            $rendersOperationalError = in_array($status, [403, 404, 429], true)
                || (! app()->environment('local') && in_array($status, [500, 503], true));

            if ($rendersOperationalError) {
                $errorResponse = Inertia::render('errors/status', [
                    'status' => $status,
                    'requestId' => $requestId,
                    'authenticated' => $request->user() !== null,
                    'supportMessage' => (string) config('lpj.system_policy.support_message', 'Hubungi administrator sistem.'),
                ])->toResponse($request)->setStatusCode($status);

                return AssignRequestId::decorate($errorResponse, $requestId, false);
            }

            return AssignRequestId::decorate($response, $requestId, false);
        });
    })->create();
