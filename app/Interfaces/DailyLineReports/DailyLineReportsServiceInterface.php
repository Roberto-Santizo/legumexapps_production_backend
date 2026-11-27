<?php

namespace App\Interfaces\DailyLineReports;

use App\Models\Line;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Laravel\Ai\Responses\AudioResponse;
use RuntimeException;

interface DailyLineReportsServiceInterface
{
    /**
     * Lines with at least one executed task with capture records on the given date, ordered by code.
     *
     * @return Collection<int, Line>
     */
    public function getLinesToReport(CarbonInterface $date): Collection;

    /**
     * Plain text script of the line's summary for the given date.
     *
     * @throws RuntimeException when the agent returns an empty script
     */
    public function generateScript(Line $line, CarbonInterface $date): string;

    /**
     * Speech audio of the given script, kept in memory.
     */
    public function generateAudio(string $script): AudioResponse;
}
