<?php

namespace App\Console\Commands;

use App\Interfaces\DailyLineReports\DailyLineReportsServiceInterface;
use App\Interfaces\Telegram\TelegramServiceInterface;
use App\Jobs\SendDailyLineAudioReport;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

#[Signature('reports:daily-line-audio {--date= : Fecha a reportar (Y-m-d); por defecto, ayer}')]
#[Description('Envía a Telegram un audio por línea con el resumen de las tareas ejecutadas en la fecha')]
class SendDailyLineAudioReports extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DailyLineReportsServiceInterface $dailyLineReportsService, TelegramServiceInterface $telegramService): int
    {
        $date = $this->reportDate();

        if (! $date) {
            return self::FAILURE;
        }

        if (! $telegramService->isConfigured()) {
            $message = 'Reporte diario por línea: falta configurar TELEGRAM_BOT_TOKEN o TELEGRAM_CHAT_ID';
            $this->error($message);
            Log::error($message);

            return self::FAILURE;
        }

        try {
            $lines = $dailyLineReportsService->getLinesToReport($date);

            foreach ($lines as $line) {
                SendDailyLineAudioReport::dispatch($line, $date->format('Y-m-d'));
            }
        } catch (Throwable $th) {
            report($th);
            $this->error('Hubo un error al generar el reporte diario');
            $this->notifyError($telegramService);

            return self::FAILURE;
        }

        $this->info("Reportes despachados para el {$date->format('Y-m-d')}: {$lines->count()}");

        return self::SUCCESS;
    }

    /**
     * Date to report in the plant's timezone, or null when --date is not valid.
     */
    private function reportDate(): ?CarbonImmutable
    {
        $timezone = config('services.daily_line_reports.timezone');
        $date = $this->option('date');

        if ($date === null) {
            return CarbonImmutable::now($timezone)->subDay()->startOfDay();
        }

        if (Validator::make(['date' => $date], ['date' => ['required', 'date_format:Y-m-d']])->fails()) {
            $this->error('La fecha debe ser válida y tener el formato Y-m-d');

            return null;
        }

        $reportDate = CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone);

        if ($reportDate->greaterThan(CarbonImmutable::now($timezone)->startOfDay())) {
            $this->error('La fecha no puede ser futura');

            return null;
        }

        return $reportDate;
    }

    /**
     * Try to notify the chat that the report could not be generated.
     */
    private function notifyError(TelegramServiceInterface $telegramService): void
    {
        try {
            $telegramService->sendMessage('Hubo un error al generar el reporte diario');
        } catch (Throwable $th) {
            report($th);
        }
    }
}
