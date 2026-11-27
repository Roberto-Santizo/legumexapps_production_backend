<?php

namespace App\Interfaces\Telegram;

use RuntimeException;

interface TelegramServiceInterface
{
    /**
     * Whether the bot token and the destination chat are set.
     */
    public function isConfigured(): bool;

    /**
     * Send a plain text message to the configured chat, truncated to the Telegram limit.
     *
     * @throws RuntimeException when Telegram does not accept the message
     */
    public function sendMessage(string $text): void;

    /**
     * Send a file to the configured chat, with its caption truncated to the Telegram limit.
     *
     * @throws RuntimeException when Telegram does not accept the document
     */
    public function sendDocument(string $content, string $filename, string $mimeType, string $caption): void;
}
