<?php

namespace App\Services\WeeklyPlanTaskAssistant;

use App\Calculators\CaptureSummaryCalculator;
use App\Enums\CaptureType;
use App\Errors\BadRequestError;
use App\Interfaces\WeeklyPlanTaskAssistant\WeeklyPlanTaskAssistantServiceInterface;
use App\Interfaces\WeeklyPlanTasks\WeeklyPlanTasksServiceInterface;
use App\Models\LineField;
use App\Models\User;
use App\Models\WeeklyPlanTask;
use App\Models\WeeklyPlanTaskLotRecord;
use App\Models\WeeklyPlanTaskPerformanceRecord;
use App\Models\WeeklyPlanTaskProductRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\ConversationStore;
use Override;

class WeeklyPlanTaskAssistantService implements WeeklyPlanTaskAssistantServiceInterface
{
    /**
     * Relations needed to resolve WeeklyPlanTaskAssistantTaskResource without N+1.
     */
    private const TASK_RELATIONS = ['performance.sku.client', 'performance.line.positions', 'openTimeout', 'weeklyPlan'];

    /**
     * Keys accepted as task filters by the search and the filtered summaries.
     */
    private const TASK_FILTER_KEYS = ['weekly_plan_id', 'week', 'year', 'operation_date_from', 'operation_date_to', 'line_code', 'sku_code'];

    public function __construct(
        private WeeklyPlanTasksServiceInterface $weeklyPlanTasksService,
        private CaptureSummaryCalculator $captureSummaryCalculator,
        private ConversationStore $conversationStore,
    ) {}

    #[Override]
    public function searchTasks(array $filters): Collection
    {
        return $this->filteredTasksQuery($filters)->get();
    }

    #[Override]
    public function getTaskDetail(string $taskId): WeeklyPlanTask
    {
        $task = $this->getTask($taskId);
        $task->loadCount(['employees', 'timeouts', 'observations', 'logs', 'performanceRecords', 'lotRecords', 'productRecords']);

        return $task;
    }

    #[Override]
    public function getTaskCaptureFields(string $taskId): Collection
    {
        $task = $this->weeklyPlanTasksService->getWeeklyPlanTaskById($taskId);
        $task->loadMissing('performance.line');
        $line = $task->performance?->line;

        if (! $line) {
            return collect();
        }

        return $line->lineFields()
            ->with('captureField')
            ->orderBy('order')
            ->get()
            ->each(fn (LineField $lineField) => $lineField->setRelation('line', $line));
    }

    #[Override]
    public function getTaskEmployees(string $taskId): Collection
    {
        $task = $this->weeklyPlanTasksService->getWeeklyPlanTaskById($taskId);

        return $task->employees()->orderBy('name')->get();
    }

    #[Override]
    public function getTaskTimeouts(string $taskId): Collection
    {
        $task = $this->weeklyPlanTasksService->getWeeklyPlanTaskById($taskId);

        return $task->timeouts()->with(['timeout', 'user'])->orderBy('start_date')->get();
    }

    #[Override]
    public function getTaskLogs(string $taskId): Collection
    {
        $task = $this->weeklyPlanTasksService->getWeeklyPlanTaskById($taskId);

        return $task->logs()->with('user')->orderBy('created_at')->get();
    }

    #[Override]
    public function getTaskObservations(string $taskId): Collection
    {
        $task = $this->weeklyPlanTasksService->getWeeklyPlanTaskById($taskId);

        return $task->observations()->with('user')->orderBy('created_at')->get();
    }

    #[Override]
    public function getCaptureRecords(string $taskId, CaptureType $captureType): Collection
    {
        $task = $this->getTask($taskId);
        $this->ensureTaskCapturesBy($task, $captureType);

        return $this->captureRecordsQuery($captureType, [$task->id])->get();
    }

    #[Override]
    public function summarizeCaptureRecords(CaptureType $captureType, array $scope): array
    {
        $hasTask = filled($scope['task_id'] ?? null);
        $hasFilters = collect(self::TASK_FILTER_KEYS)->contains(fn (string $key) => filled($scope[$key] ?? null));

        if ($hasTask === $hasFilters) {
            throw new BadRequestError('Indica una tarea o filtros, no ambos');
        }

        if ($hasTask) {
            $task = $this->getTask((string) $scope['task_id']);
            $this->ensureTaskCapturesBy($task, $captureType);
            $tasks = collect([$task]);
        } else {
            $tasks = $this->filteredTasksQuery($scope)
                ->whereHas('performance.line', function (Builder $line) use ($captureType) {
                    $line->where('capture_type', $captureType->value);
                })
                ->get();
        }

        $records = $tasks->isEmpty()
            ? collect()
            : $this->captureRecordsQuery($captureType, $tasks->pluck('id')->all())->get();

        $summary = match ($captureType) {
            CaptureType::Pallet => $this->captureSummaryCalculator->pallet($records, $tasks),
            CaptureType::Lot => $this->captureSummaryCalculator->lot($records, $tasks),
            CaptureType::Product => $this->captureSummaryCalculator->product($records, $tasks),
        };

        if ($hasTask) {
            unset($summary['by_task']);
        }

        return $summary;
    }

    #[Override]
    public function assertConversationBelongsTo(User $user, string $conversationId): void
    {
        //
    }

    #[Override]
    public function getConversations(User $user, ?string $limit)
    {
        return collect();
    }

    #[Override]
    public function getConversationMessages(User $user, string $conversationId)
    {
        return collect();
    }

    #[Override]
    public function deleteConversation(User $user, string $conversationId): bool
    {
        return false;
    }

    /**
     * Read-only query of the capture records of the given family for the given tasks, oldest first.
     *
     * @param  array<int, int>  $taskIds
     * @return Builder<WeeklyPlanTaskPerformanceRecord|WeeklyPlanTaskLotRecord|WeeklyPlanTaskProductRecord>
     */
    private function captureRecordsQuery(CaptureType $captureType, array $taskIds): Builder
    {
        $model = match ($captureType) {
            CaptureType::Pallet => WeeklyPlanTaskPerformanceRecord::class,
            CaptureType::Lot => WeeklyPlanTaskLotRecord::class,
            CaptureType::Product => WeeklyPlanTaskProductRecord::class,
        };

        return $model::query()
            ->with('user')
            ->whereIn('weekly_plan_task_id', $taskIds)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * The line of the task must capture by the given family.
     */
    private function ensureTaskCapturesBy(WeeklyPlanTask $task, CaptureType $captureType): void
    {
        $taskCaptureType = $task->performance?->line?->capture_type;

        if ($taskCaptureType !== $captureType) {
            $taskUnitLabel = $taskCaptureType?->unitLabel() ?? 'ninguna familia';

            throw new BadRequestError("La tarea {$task->id} captura por {$taskUnitLabel}, no por {$captureType->unitLabel()}");
        }
    }

    /**
     * Task by id with the relations and sums needed by WeeklyPlanTaskAssistantTaskResource.
     */
    private function getTask(string $taskId): WeeklyPlanTask
    {
        $task = $this->weeklyPlanTasksService->getWeeklyPlanTaskById($taskId);
        $task->load(self::TASK_RELATIONS);

        return $task;
    }

    /**
     * Read-only query of the tasks matching the assistant filters, with the same relations and sums as getWeeklyPlanTasks.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<WeeklyPlanTask>
     */
    private function filteredTasksQuery(array $filters): Builder
    {
        $this->ensureTaskFiltersAreScoped($filters);

        $query = WeeklyPlanTask::query()
            ->with(self::TASK_RELATIONS)
            ->withSum('performanceRecords', 'net_weight')
            ->withSum('lotRecords', 'trimmed_lbs')
            ->withSum('productRecords', 'trimmed_lbs')
            ->withSum('timeouts', 'duration_hours');

        if (filled($filters['weekly_plan_id'] ?? null)) {
            $query->where('weekly_plan_id', $filters['weekly_plan_id']);
        }

        if (filled($filters['week'] ?? null) && filled($filters['year'] ?? null)) {
            $query->whereHas('weeklyPlan', function (Builder $weeklyPlan) use ($filters) {
                $weeklyPlan->where('week', $filters['week'])->where('year', $filters['year']);
            });
        }

        if (filled($filters['operation_date_from'] ?? null) && filled($filters['operation_date_to'] ?? null)) {
            $query->whereDate('operation_date', '>=', $filters['operation_date_from'])
                ->whereDate('operation_date', '<=', $filters['operation_date_to']);
        }

        if (filled($filters['line_code'] ?? null)) {
            $query->whereHas('performance.line', function (Builder $line) use ($filters) {
                $line->where('code', $filters['line_code']);
            });
        }

        if (filled($filters['sku_code'] ?? null)) {
            $query->whereHas('performance.sku', function (Builder $sku) use ($filters) {
                $sku->where('code', 'LIKE', '%'.$filters['sku_code'].'%');
            });
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('operation_date')->orderBy('id');
    }

    /**
     * Filters must narrow the tasks with a weekly plan, a week or a date range no longer than the configured maximum.
     *
     * @param  array<string, mixed>  $filters
     */
    private function ensureTaskFiltersAreScoped(array $filters): void
    {
        $hasWeeklyPlan = filled($filters['weekly_plan_id'] ?? null);
        $hasWeek = filled($filters['week'] ?? null) && filled($filters['year'] ?? null);
        $hasDateRange = filled($filters['operation_date_from'] ?? null) && filled($filters['operation_date_to'] ?? null);

        if (! $hasWeeklyPlan && ! $hasWeek && ! $hasDateRange) {
            throw new BadRequestError('Acota la búsqueda con un plan semanal, una semana o un rango de fechas');
        }

        if ($hasDateRange) {
            $maxRangeDays = (int) config('services.weekly_plan_task_assistant.max_range_days');
            $from = Carbon::parse($filters['operation_date_from'])->startOfDay();
            $to = Carbon::parse($filters['operation_date_to'])->startOfDay();

            if ($from->diffInDays($to) > $maxRangeDays) {
                throw new BadRequestError("El rango máximo de fechas es de {$maxRangeDays} días");
            }
        }
    }
}
