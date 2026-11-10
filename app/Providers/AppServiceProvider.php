<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
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
        RateLimiter::for('weekly-plan-task-assistant', function (Request $request) {
            return Limit::perMinute((int) config('services.weekly_plan_task_assistant.rate_limit'))
                ->by(auth()->id() ?? $request->ip())
                ->response(fn () => response()->json([
                    'statusCode' => 429,
                    'message' => 'Demasiadas preguntas, intenta de nuevo en un momento',
                    'data' => null,
                ], 429));
        });
    }
}
