<?php

use App\Http\Middleware\EnsureAdminRole;
use App\Services\DemoEnvironmentGuard;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'ensure-admin' => EnsureAdminRole::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (InvalidSignatureException $e, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            return response()->view('errors.invalid-link', [], 403);
        });
    })->create();

$app->afterBootstrapping(LoadConfiguration::class, function (Application $app): void {
    $app['config']->set('database.connections', array_filter(
        $app['config']->get('database.connections', []),
        fn (array $connection): bool => ($connection['driver'] ?? null) === 'mysql',
    ));
});

DemoEnvironmentGuard::prepare($app);

return $app;
