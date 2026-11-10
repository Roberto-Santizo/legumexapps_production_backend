<?php

namespace App\Ai\Agents;

use App\Ai\Tools\GetWeeklyPlanTaskCaptureFields;
use App\Ai\Tools\GetWeeklyPlanTaskDetail;
use App\Ai\Tools\GetWeeklyPlanTaskEmployees;
use App\Ai\Tools\GetWeeklyPlanTaskLogs;
use App\Ai\Tools\GetWeeklyPlanTaskObservations;
use App\Ai\Tools\GetWeeklyPlanTaskTimeouts;
use App\Ai\Tools\ListLotRecords;
use App\Ai\Tools\ListPalletRecords;
use App\Ai\Tools\ListProductRecords;
use App\Ai\Tools\SearchWeeklyPlanTasks;
use App\Ai\Tools\SummarizeLotRecords;
use App\Ai\Tools\SummarizePalletRecords;
use App\Ai\Tools\SummarizeProductRecords;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

class WeeklyPlanTaskAssistant implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    /**
     * Read-only tools available to the agent.
     *
     * @var list<class-string<Tool>>
     */
    private const TOOLS = [
        SearchWeeklyPlanTasks::class,
        GetWeeklyPlanTaskDetail::class,
        GetWeeklyPlanTaskCaptureFields::class,
        GetWeeklyPlanTaskEmployees::class,
        GetWeeklyPlanTaskTimeouts::class,
        GetWeeklyPlanTaskLogs::class,
        GetWeeklyPlanTaskObservations::class,
        ListPalletRecords::class,
        SummarizePalletRecords::class,
        ListLotRecords::class,
        SummarizeLotRecords::class,
        ListProductRecords::class,
        SummarizeProductRecords::class,
    ];

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return view('ai.weekly-plan-task-assistant', [
            'today' => now()->toDateString(),
            'maxRangeDays' => (int) config('services.weekly_plan_task_assistant.max_range_days'),
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
