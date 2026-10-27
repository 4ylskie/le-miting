<?php

namespace App\Providers;

use App\Services\Ai\AiRecommender;
use App\Services\Ai\MockAiRecommender;
use App\Services\Ai\OpenAiService;
use App\Services\DemoEnvironmentGuard;
use App\Services\DemoMigrationGuard;
use App\Services\DevelopmentDatabaseGuard;
use App\Services\Payments\FiuuPaymentService;
use App\Services\Payments\MockPaymentService;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentProcessor::class, function () {
            return config('payment.driver') === 'fiuu'
                ? new FiuuPaymentService
                : new MockPaymentService;
        });

        $this->app->singleton(AiRecommender::class, function () {
            return config('ai.driver') === 'openai'
                ? new OpenAiService
                : new MockAiRecommender;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        if ($this->app->runningInConsole()) {
            $this->app->make(Kernel::class)->rerouteSymfonyCommandEvents();
        }
        $this->app['events']->listen(CommandStarting::class, function (CommandStarting $event): void {
            if ($this->app->bound('demo.requested')) {
                if ($this->app->bound(DemoMigrationGuard::class)) {
                    $this->app->make(DemoMigrationGuard::class)->protectCommand($event,
                        $this->app->storagePath('app/private/demo-forward-migration.started'));
                } else {
                    $this->app->make(DemoEnvironmentGuard::class)->protectCommand($event);
                }
            } else {
                $this->app->make(DevelopmentDatabaseGuard::class)->protectCommand($event);
            }
        });

        RateLimiter::for('rasa-ai', function (Request $request): Limit {
            return Limit::perMinute(10)
                ->by('rasa-ai:'.$request->ip())
                ->response(function (Request $request, array $headers): RedirectResponse {
                    $response = redirect()->back()
                        ->withInput()
                        ->with('error', 'Rasa AI is receiving too many requests. Please wait a moment and try again.');

                    $response->headers->add($headers);

                    return $response;
                });
        });
    }
}
