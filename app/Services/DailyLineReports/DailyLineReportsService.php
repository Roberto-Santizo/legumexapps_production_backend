<?php

namespace App\Services\DailyLineReports;

use App\Ai\Agents\DailyLineReportAgent;
use App\Enums\CaptureType;
use App\Interfaces\DailyLineReports\DailyLineReportsServiceInterface;
use App\Models\Line;
use App\Models\WeeklyPlanTask;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Laravel\Ai\Audio;
use Laravel\Ai\Responses\AudioResponse;
use Override;
use RuntimeException;

class DailyLineReportsService implements DailyLineReportsServiceInterface
{
    /**
     * Statuses of an executed task: En Progreso and Finalizada.
     */
    private const EXECUTED_STATUSES = [4, 5];

    #[Override]
    public function getLinesToReport(CarbonInterface $date): Collection
    {
        $lineIds = WeeklyPlanTask::query()
            ->with('performance')
            ->whereDate('operation_date', $date)
            ->whereIn('status', self::EXECUTED_STATUSES)
            ->where(function (Builder $query) {
                foreach (CaptureType::cases() as $captureType) {
                    $query->orWhere(function (Builder $family) use ($captureType) {
                        $family->whereHas('performance.line', function (Builder $line) use ($captureType) {
                            $line->where('capture_type', $captureType->value);
                        })->has($this->recordsRelation($captureType));
                    });
                }
            })
            ->get()
            ->pluck('performance.line_id')
            ->unique();

        return Line::query()->whereIn('id', $lineIds)->orderBy('code')->get();
    }

    #[Override]
    public function generateScript(Line $line, CarbonInterface $date): string
    {
        $response = DailyLineReportAgent::make()->prompt(
            "Haz el resumen en audio de la línea {$line->name} (código {$line->code}, familia {$line->capture_type?->value}) del {$date->format('Y-m-d')}.",
            provider: config('services.weekly_plan_task_assistant.provider'),
            model: config('services.weekly_plan_task_assistant.model') ?: null,
        );

        $script = trim($response->text);

        if ($script === '') {
            throw new RuntimeException("El guion del reporte diario de la línea {$line->code} está vacío");
        }

        return $this->truncate($script, (int) config('services.weekly_plan_task_assistant.audio.max_chars'));
    }

    #[Override]
    public function generateAudio(string $script): AudioResponse
    {
        $config = config('services.weekly_plan_task_assistant.audio');

        return Audio::of($script)
            ->voice($config['voice'])
            ->timeout($config['timeout'])
            ->generate($config['provider'], $config['model'] ?: null);
    }

    /**
     * Task relation with the capture records of the given family.
     */
    private function recordsRelation(CaptureType $captureType): string
    {
        return match ($captureType) {
            CaptureType::Pallet => 'performanceRecords',
            CaptureType::Lot => 'lotRecords',
            CaptureType::Product => 'productRecords',
        };
    }

    /**
     * Cut the text to the maximum length without splitting words.
     */
    private function truncate(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        $wholeWords = preg_replace('/\s+\S*$/u', '', mb_substr($text, 0, $maxLength + 1));

        return mb_substr($wholeWords, 0, $maxLength);
    }
}
