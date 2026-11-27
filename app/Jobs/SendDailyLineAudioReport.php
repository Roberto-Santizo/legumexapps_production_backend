<?php

namespace App\Jobs;

use App\Interfaces\DailyLineReports\DailyLineReportsServiceInterface;
use App\Interfaces\Telegram\TelegramServiceInterface;
use App\Models\Line;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class SendDailyLineAudioReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public bool $failOnTimeout = true;

    /**
     * Create a new job instance.
     *
     * @param  string  $date  Reported date (Y-m-d)
     */
    public function __construct(public Line $line, public string $date) {}

    /**
     * Execute the job.
     */
    public function handle(DailyLineReportsServiceInterface $dailyLineReportsService, TelegramServiceInterface $telegramService): void
    {
        $script = $dailyLineReportsService->generateScript($this->line, $this->reportDate());

        try {
            $audio = $dailyLineReportsService->generateAudio($script);
        } catch (Throwable $th) {
            report($th);

            $telegramService->sendMessage("{$this->label()}\n\n{$script}");

            return;
        }

        $mimeType = $audio->mimeType() ?? 'audio/mpeg';
        $extension = in_array($mimeType, ['audio/wav', 'audio/x-wav'], true) ? 'wav' : 'mp3';

        $telegramService->sendDocument(
            $audio->content(),
            'resumen-'.Str::slug($this->line->code)."-{$this->date}.{$extension}",
            $mimeType,
            "Resumen {$this->label()}",
        );
    }

    /**
     * Notify the chat that the line's report could not be sent.
     */
    public function failed(?Throwable $exception): void
    {
        try {
            app(TelegramServiceInterface::class)->sendMessage("Hubo un error al generar el reporte diario: {$this->label()}");
        } catch (Throwable $th) {
            report($th);
        }
    }

    /**
     * Line name and reported date, as shown in the chat.
     */
    private function label(): string
    {
        return "{$this->line->name} — {$this->reportDate()->format('d-m-Y')}";
    }

    private function reportDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->date);
    }
}
