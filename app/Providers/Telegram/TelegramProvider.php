<?php

namespace App\Providers\Telegram;

use App\Interfaces\Telegram\TelegramServiceInterface;
use App\Services\Telegram\TelegramService;
use Illuminate\Support\ServiceProvider;

class TelegramProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(TelegramServiceInterface::class, TelegramService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
