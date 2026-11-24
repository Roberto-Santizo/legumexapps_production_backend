<?php

namespace App\Services\Telegram;

use App\Interfaces\Telegram\TelegramServiceInterface;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Override;
use RuntimeException;
use Throwable;

class TelegramService implements TelegramServiceInterface
{
    private const MAX_TEXT_LENGTH = 4096;

    private const MAX_CAPTION_LENGTH = 1024;

    private const TIMEOUT_SECONDS = 60;

    private const MAX_ATTEMPTS = 3;

    #[Override]
    public function isConfigured(): bool
    {
        return filled(config('services.telegram.bot_token')) && filled(config('services.telegram.chat_id'));
    }

    #[Override]
    public function sendMessage(string $text): void
    {
        $this->call('sendMessage', fn (PendingRequest $request): PendingRequest => $request->asJson(), [
            'text' => mb_substr($text, 0, self::MAX_TEXT_LENGTH),
        ]);
    }

    #[Override]
    public function sendDocument(string $content, string $filename, string $mimeType, string $caption): void
    {
        $this->call(
            'sendDocument',
            fn (PendingRequest $request): PendingRequest => $request->attach('document', $content, $filename, ['Content-Type' => $mimeType]),
            ['caption' => mb_substr($caption, 0, self::MAX_CAPTION_LENGTH)],
        );
    }

    /**
     * Call a Bot API method. The token travels in the URL, so neither the URL nor the original HTTP exception may leave this method.
     *
     * @param  Closure(PendingRequest): PendingRequest  $configure
     * @param  array<string, string>  $payload
     */
    private function call(string $method, Closure $configure, array $payload): void
    {
        $failure = null;

        try {
            $response = $configure($this->request())->post(
                'https://api.telegram.org/bot'.config('services.telegram.bot_token')."/{$method}",
                ['chat_id' => config('services.telegram.chat_id'), ...$payload],
            );

            if (! $response->successful() || $response->json('ok') !== true) {
                $failure = "status {$response->status()}: {$this->description($response)}";
            }
        } catch (Throwable) {
            $failure = 'error de conexión';
        }

        if ($failure !== null) {
            throw new RuntimeException("Telegram {$method} falló ({$failure})");
        }
    }

    /**
     * Request that only retries connection errors, 429 and 5xx.
     */
    private function request(): PendingRequest
    {
        return Http::timeout(self::TIMEOUT_SECONDS)->retry(
            self::MAX_ATTEMPTS,
            fn (int $attempt): int => $attempt * 1000,
            fn (Throwable $exception): bool => $exception instanceof ConnectionException
                || ($exception instanceof RequestException
                    && ($exception->response->status() === 429 || $exception->response->serverError())),
            throw: false,
        );
    }

    /**
     * Error description returned by Telegram, if any.
     */
    private function description(Response $response): string
    {
        $description = $response->json('description');

        return is_string($description) ? $description : 'sin descripción';
    }
}
