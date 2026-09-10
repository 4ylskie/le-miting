<?php

namespace App\Providers;

use App\Services\Ai\AiRecommender;
use App\Services\Ai\MockAiRecommender;
use App\Services\Ai\OpenAiService;
use App\Services\Payments\FiuuPaymentService;
use App\Services\Payments\MockPaymentService;
use App\Services\Payments\PaymentProcessor;
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
        //
    }
}
