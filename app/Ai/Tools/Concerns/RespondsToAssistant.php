<?php

namespace App\Ai\Tools\Concerns;

use App\Errors\ApiException;
use Closure;

trait RespondsToAssistant
{
    /**
     * Encode the tool output as JSON for the model.
     */
    protected function json(mixed $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Run the tool, returning business errors as text so the model can explain them; any other exception propagates.
     *
     * @param  Closure(): string  $callback
     */
    protected function attempt(Closure $callback): string
    {
        try {
            return $callback();
        } catch (ApiException $exception) {
            return "Error: {$exception->getMessage()}";
        }
    }
}
