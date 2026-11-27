<?php

namespace App\Services\WeeklyPlanTaskEmployees;

use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Interfaces\BiometricQueries\BiometricQueriesServiceInterface;
use App\Interfaces\LineSkuActivities\LineSkuActivitiesServiceInterface;
use App\Interfaces\LineSkus\LineSkusServiceInterface;
use App\Interfaces\WeeklyPlanTaskEmployees\WeeklyPlanTaskEmployeesServiceInterface;
use App\Interfaces\WeeklyPlanTasks\WeeklyPlanTasksServiceInterface;
use App\Models\Line;
use App\Models\LineSkuActivity;
use App\Models\Position;
use App\Models\WeeklyPlanTask;
use App\Models\WeeklyPlanTaskEmployee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Override;

class WeeklyPlanTaskEmployeesService implements WeeklyPlanTaskEmployeesServiceInterface
{
    public function __construct(
        private WeeklyPlanTasksServiceInterface $weeklyPlanTasksService,
        private BiometricQueriesServiceInterface $biometricQueriesService,
        private LineSkuActivitiesServiceInterface $lineSkuActivitiesService,
        private LineSkusServiceInterface $lineSkusService,
    ) {}

    #[Override]
    public function getAvailableEmployees(string $taskId)
    {
        $task = $this->weeklyPlanTasksService->getWeeklyPlanTaskById($taskId);
        $lineCode = $task->performance?->line?->code;

        if (! $lineCode) {
            return [];
        }

        return $this->biometricQueriesService->getEmployeesByLineCode($lineCode);
    }

    #[Override]
    public function getTaskEmployees(string $taskId)
    {
        $task = $this->weeklyPlanTasksService->getWeeklyPlanTaskById($taskId);
        $employees = $task->employees()->with(['catalogPosition.activity', 'lineSkuActivity.activity'])->get();

        return $employees;
    }

    #[Override]
    public function confirmEmployees(string $taskId, array $data)
    {
        $task = $this->weeklyPlanTasksService->getWeeklyPlanTaskById($taskId);
        $line = $this->getTaskLine($task);

        $codes = array_column($data['employees'], 'position');
        $positions = Position::where('line_sku_id', $task->line_sku_id)->whereIn('code', $codes)->get()->keyBy('code');

        $lineSkuActivityIds = array_map('intval', array_column($data['employees'], 'line_sku_activity_id'));
        $lineSkuActivities = LineSkuActivity::with('activity')->whereIn('id', array_unique($lineSkuActivityIds))->get()->keyBy('id');
        $assignedCounts = $this->getAssignedCounts($task, $lineSkuActivities->keys());
        $firtPosition = $task->performance->positions()->first();

        if ($line->is_wildcard && ! $firtPosition) {
            throw new BadRequestError('El SKU de línea debería de tener por lo menos una posición');
        }

        $errors = [];
        $bulkData = [];
        $now = now();

        foreach ($data['employees'] as $employee) {
            $position = $positions->get($employee['position']);
            $lineSkuActivityId = (int) $employee['line_sku_activity_id'];
            $lineSkuActivity = $lineSkuActivities->get($lineSkuActivityId);
            $hasErrors = false;

            if (! $line->is_wildcard && ! $position) {
                $errors[] = "La posición '{$employee['position']}' no existe";
                $hasErrors = true;
            }

            if (! $lineSkuActivity) {
                $errors[] = "La actividad de la línea '{$lineSkuActivityId}' no existe";
                $hasErrors = true;
            }

            if ($hasErrors) {
                continue;
            }

            $bulkData[] = [
                'weekly_plan_task_id' => $task->id,
                'code' => $employee['code'],
                'name' => $employee['name'],
                'position' => $employee['position'],
                'position_id' => $line->is_wildcard ? $firtPosition->id : $position->id,
                'line_sku_activity_id' => $lineSkuActivity->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $lineSkuActivitiesOfTask = $lineSkuActivities->filter(fn (LineSkuActivity $lineSkuActivity) => $lineSkuActivity->line_sku_id === $task->line_sku_id);
        array_push($errors, ...$this->collectQuotaErrors($lineSkuActivitiesOfTask, array_count_values($lineSkuActivityIds), $assignedCounts));

        if (! empty($errors)) {
            throw new BadRequestError(implode(PHP_EOL, $errors));
        }

        $this->storeAssignments($task, $bulkData);

        return null;
    }

    /**
     * Active assignments of the line SKU's last finished task, to be reused as a confirmEmployees request.
     *
     * @return Collection<int, WeeklyPlanTaskEmployee>
     */
    #[Override]
    public function getLastEmployeesConfiguration(string $lineSkuId)
    {
        $lineSku = $this->lineSkusService->getLineSkuById($lineSkuId);

        $task = WeeklyPlanTask::whereNotNull('end_date')
            ->where('line_sku_id', $lineSku->id)
            ->latest('end_date')
            ->latest('id')
            ->first();

        if (! $task) {
            throw new NotFoundError('El SKU de línea no tiene tareas finalizadas');
        }

        return $task->employees()->with('catalogPosition')->orderBy('id')->get();
    }

    /**
     * Same as confirmEmployees, but each employee's line SKU activity is resolved from
     * the task line SKU and the activity of the position found by its code.
     *
     * @param  array{employees: array<int, array{code: string, name: string, position: string}>}  $data
     */
    #[Override]
    public function confirmEmployeesByPosition(string $taskId, array $data)
    {
        $task = $this->weeklyPlanTasksService->getWeeklyPlanTaskById($taskId);
        $line = $this->getTaskLine($task);

        if ($line->is_wildcard) {
            throw new BadRequestError('La línea es de comodines, no se puede confirmar por posición');
        }

        $codes = array_column($data['employees'], 'position');
        $positions = Position::with('activity')->where('line_sku_id', $task->line_sku_id)->whereIn('code', $codes)->get()->keyBy('code');

        $activityIds = $positions->pluck('activity_id')->filter()->unique()->values();
        $lineSkuActivities = LineSkuActivity::with('activity')
            ->where('line_sku_id', $task->line_sku_id)
            ->whereIn('activity_id', $activityIds)
            ->get()
            ->keyBy('activity_id');

        $errors = [];
        $bulkData = [];
        $sentCounts = [];
        $now = now();

        foreach ($data['employees'] as $employee) {
            $position = $positions->get($employee['position']);

            if (! $position) {
                $errors[] = "La posición '{$employee['position']}' no existe";

                continue;
            }

            if (! $position->activity_id) {
                $errors[] = "La posición '{$position->code}' no tiene una actividad asignada";

                continue;
            }

            $lineSkuActivity = $lineSkuActivities->get($position->activity_id);

            if (! $lineSkuActivity) {
                $errors[] = "La actividad '{$position->activity?->name}' de la posición '{$position->code}' no está asignada al SKU de línea de la tarea";

                continue;
            }

            $sentCounts[$lineSkuActivity->id] = ($sentCounts[$lineSkuActivity->id] ?? 0) + 1;

            $bulkData[] = [
                'weekly_plan_task_id' => $task->id,
                'code' => $employee['code'],
                'name' => $employee['name'],
                'position' => $employee['position'],
                'position_id' => $position->id,
                'line_sku_activity_id' => $lineSkuActivity->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $lineSkuActivitiesById = $lineSkuActivities->keyBy('id');
        $assignedCounts = $this->getAssignedCounts($task, $lineSkuActivitiesById->keys());
        array_push($errors, ...$this->collectQuotaErrors($lineSkuActivitiesById, $sentCounts, $assignedCounts));

        if (! empty($errors)) {
            throw new BadRequestError(implode(PHP_EOL, $errors));
        }

        $this->storeAssignments($task, $bulkData);

        return null;
    }

    #[Override]
    public function removeEmployee(string $assignmentId)
    {
        $assignment = $this->getAssignmentById($assignmentId);
        $task = $assignment->task;
        $this->ensureTaskAcceptsEmployeeChanges($task);

        if ($task->employees()->count() <= 1) {
            throw new BadRequestError('La tarea debe tener al menos un empleado asignado');
        }

        $assignment->delete();

        return null;
    }

    /**
     * @param  array{line_sku_activity_id: int}  $data
     */
    #[Override]
    public function updateEmployeeActivity(string $assignmentId, array $data)
    {
        $assignment = $this->getAssignmentById($assignmentId);
        $task = $assignment->task;
        $this->ensureTaskAcceptsEmployeeChanges($task);

        $lineSkuActivity = $this->lineSkuActivitiesService->getLineSkuActivityById((string) $data['line_sku_activity_id']);
        $activityName = $lineSkuActivity->activity->name;

        if ($lineSkuActivity->line_sku_id !== $task->line_sku_id) {
            throw new BadRequestError("La actividad '{$activityName}' no pertenece al SKU de línea de la tarea");
        }

        $assignedCount = $task->employees()
            ->where('line_sku_activity_id', $lineSkuActivity->id)
            ->whereKeyNot($assignment->id)
            ->count();

        if ($assignedCount + 1 > $lineSkuActivity->quantity) {
            throw new BadRequestError("La actividad '{$activityName}' admite {$lineSkuActivity->quantity} personas y ya tiene {$assignedCount} asignadas");
        }

        $assignment->update(['line_sku_activity_id' => $lineSkuActivity->id]);

        return null;
    }

    /**
     * Active assignments of the task per line SKU activity.
     *
     * @param  Collection<int, int>  $lineSkuActivityIds
     * @return Collection<int, int>
     */
    private function getAssignedCounts(WeeklyPlanTask $task, Collection $lineSkuActivityIds): Collection
    {
        return $task->employees()
            ->whereIn('line_sku_activity_id', $lineSkuActivityIds)
            ->selectRaw('line_sku_activity_id, count(*) as total')
            ->groupBy('line_sku_activity_id')
            ->pluck('total', 'line_sku_activity_id');
    }

    /**
     * Quota errors for line SKU activities whose assigned plus sent employees exceed their quantity.
     *
     * @param  Collection<int, LineSkuActivity>  $lineSkuActivities  keyed by id
     * @param  array<int, int>  $sentCounts  line_sku_activity_id => employees sent
     * @param  Collection<int, int>  $assignedCounts  line_sku_activity_id => employees already assigned
     * @return list<string>
     */
    private function collectQuotaErrors(Collection $lineSkuActivities, array $sentCounts, Collection $assignedCounts): array
    {
        $errors = [];

        foreach ($sentCounts as $lineSkuActivityId => $sentCount) {
            $lineSkuActivity = $lineSkuActivities->get($lineSkuActivityId);

            if (! $lineSkuActivity) {
                continue;
            }

            $assignedCount = (int) $assignedCounts->get($lineSkuActivityId, 0);

            if ($assignedCount + $sentCount > $lineSkuActivity->quantity) {
                $errors[] = "La actividad '{$lineSkuActivity->activity->name}' admite {$lineSkuActivity->quantity} personas; ya hay {$assignedCount} asignadas y se enviaron {$sentCount}";
            }
        }

        return $errors;
    }

    /**
     * Inserts every assignment and moves the task to status 3 atomically: all rows or none.
     *
     * @param  list<array<string, mixed>>  $bulkData
     */
    private function storeAssignments(WeeklyPlanTask $task, array $bulkData): void
    {
        DB::transaction(function () use ($task, $bulkData) {
            WeeklyPlanTaskEmployee::insert($bulkData);
            $task->status = 3;
            $task->save();
        });
    }

    /**
     * Active assignment by id; soft deleted rows are excluded.
     */
    private function getAssignmentById(string $id): WeeklyPlanTaskEmployee
    {
        $assignment = WeeklyPlanTaskEmployee::find($id);
        if (! $assignment) {
            throw new NotFoundError('La asignación no existe');
        }

        return $assignment;
    }

    /**
     * Line of the task's line SKU, used for the wildcard flag and the biometric lookup.
     */
    private function getTaskLine(WeeklyPlanTask $task): Line
    {
        $line = $task->performance?->line;

        if (! $line) {
            throw new BadRequestError('La tarea no tiene una línea asignada');
        }

        return $line;
    }

    /**
     * Individual changes are only allowed while the task is ready for execution or in progress.
     */
    private function ensureTaskAcceptsEmployeeChanges(WeeklyPlanTask $task): void
    {
        if (! in_array($task->status, [3, 4])) {
            throw new BadRequestError('Solo se puede modificar el personal de una tarea lista para ejecución o en progreso');
        }
    }

    /**
     * Confirmation is only allowed once, while the task is in status 2.
     */
    private function ensureTaskAwaitsEmployeeConfirmation(WeeklyPlanTask $task): void
    {
        if ($task->status != 2) {
            throw new BadRequestError('La tarea no está lista para confirmar asignaciones');
        }

        if ($task->employees()->exists()) {
            throw new BadRequestError('La tarea ya tiene personal asignado');
        }
    }
}
