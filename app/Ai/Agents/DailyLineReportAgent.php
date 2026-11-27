<?php

namespace App\Ai\Agents;

use App\Ai\Tools\GetPalletKpiReport;
use App\Ai\Tools\GetWeeklyPlanTaskObservations;
use App\Ai\Tools\SearchWeeklyPlanTasks;
use App\Ai\Tools\SummarizeLotRecords;
use App\Ai\Tools\SummarizeProductRecords;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxSteps(10)]
#[Timeout(60)]
class DailyLineReportAgent implements Agent, HasTools
{
    use Promptable;

    /**
     * Read-only query tools available to the agent.
     *
     * @var list<class-string<Tool>>
     */
    private const TOOLS = [
        SearchWeeklyPlanTasks::class,
        GetPalletKpiReport::class,
        SummarizeLotRecords::class,
        SummarizeProductRecords::class,
        GetWeeklyPlanTaskObservations::class,
    ];

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return view('ai.daily-line-report', [
            'maxAudioChars' => (int) config('services.weekly_plan_task_assistant.audio.max_chars'),
        ])->render();
    }

    /**
     * Get the tools available to the agent.
     *
     * @return list<Tool>
     */
    public function tools(): iterable
    {
        return array_map(fn (string $tool) => app($tool), self::TOOLS);
    }
}
