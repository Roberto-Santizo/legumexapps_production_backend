<?php

namespace App\Services\WeeklyPlanTaskEmployees;

use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Interfaces\BiometricQueries\BiometricQueriesServiceInterface;
use App\Interfaces\LineActivities\LineActivitiesServiceInterface;
use App\Interfaces\WeeklyPlanTaskEmployees\WeeklyPlanTaskEmployeesServiceInterface;
use App\Interfaces\WeeklyPlanTasks\WeeklyPlanTasksServiceInterface;
use App\Models\Line;
use App\Models\LineActivity;
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
        private LineActivitiesServiceInterface $lineActivitiesService,
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
        $employees = $task->employees()->with(['position.activity', 'lineActivity.activity'])->get();

        return $employees;
    }

    #[Override]
    public function confirmEmployees(string $taskId, array $data)
    {
        $task = $this->weeklyPlanTasksService->getWeeklyPlanTaskById($taskId);
        $line = $this->getTaskLine($task);

        $codes = array_column($data['employees'], 'position');
        $positions = Position::whereIn('code', $codes)->get()->keyBy('code');

        $lineActivityIds = array_map('intval', array_column($data['employees'], 'line_activity_id'));
        $lineActivities = LineActivity::with('activity')->whereIn('id', array_unique($lineActivityIds))->get()->keyBy('id');
        $assignedCounts = $this->getAssignedCounts($task, $lineActivities->keys());
        $firtPosition = $line->positions()->first();


        if ($line->is_wildcard && !$firtPosition) {
            throw new BadRequestError("La línea debería de tener por lo menos una posición");
        }

        $errors = [];
        $bulkData = [];
        $now = now();

        foreach ($data['employees'] as $employee) {
            $position = $positions->get($employee['position']);
            $lineActivityId = (int) $employee['line_activity_id'];
            $lineActivity = $lineActivities->get($lineActivityId);
            $hasErrors = false;

            if (!$line->is_wildcard && ! $position) {
                $errors[] = "La posición '{$employee['position']}' no existe";
                $hasErrors = true;
            }

            if (! $lineActivity) {
                $errors[] = "La actividad de la línea '{$lineActivityId}' no existe";
                $hasErrors = true;
            }

            if ($hasErrors) {
                continue;
            }

            $bulkData[] = [
                'weekly_plan_task_id' => $task->id,
                'code' => $employee['code'],
                'name' => $employee['name'],
                'position_id' => $line->is_wildcard ? $firtPosition->id : $position->id,
                'line_activity_id' => $lineActivity->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $lineActivitiesOfTaskLine = $lineActivities->filter(fn(LineActivity $lineActivity) => $lineActivity->line_id === $line->id);
        array_push($errors, ...$this->collectQuotaErrors($lineActivitiesOfTaskLine, array_count_values($lineActivityIds), $assignedCounts));

        if (! empty($errors)) {
            throw new BadRequestError(implode(PHP_EOL, $errors));
        }

        $this->storeAssignments($task, $bulkData);

        return null;
    }

    /**
     * Same as confirmEmployees, but each employee's line activity is resolved from
     * the task line and the activity of the position found by its code.
     *
     * @param  array{employees: array<int, array{code: string, name: string, position: string}>}  $data
     */
    #[Override]
    public function confirmEmployeesByPosition(string $taskId, array $data)
    {
        $task = $this->weeklyPlanTasksService->getWeeklyPlanTaskById($taskId);
        $line = $this->getTaskLine($task);

         if ($line->is_wildcard) {
            throw new BadRequestError("La línea es de comodines, no se puede confirmar por posición");
        }

        $codes = array_column($data['employees'], 'position');
        $positions = Position::with('activity')->whereIn('code', $codes)->get()->keyBy('code');

        $activityIds = $positions->pluck('activity_id')->filter()->unique()->values();
        $lineActivities = LineActivity::with('activity')
            ->where('line_id', $line->id)
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

            $lineActivity = $lineActivities->get($position->activity_id);

            if (! $lineActivity) {
                $errors[] = "La actividad '{$position->activity?->name}' de la posición '{$position->code}' no está asignada a la línea de la tarea";

                continue;
            }

            $sentCounts[$lineActivity->id] = ($sentCounts[$lineActivity->id] ?? 0) + 1;

            $bulkData[] = [
                'weekly_plan_task_id' => $task->id,
                'code' => $employee['code'],
                'name' => $employee['name'],
                'position_id' => $position->id,
                'line_activity_id' => $lineActivity->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $lineActivitiesById = $lineActivities->keyBy('id');
        $assignedCounts = $this->getAssignedCounts($task, $lineActivitiesById->keys());
        array_push($errors, ...$this->collectQuotaErrors($lineActivitiesById, $sentCounts, $assignedCounts));

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
     * @param  array{line_activity_id: int}  $data
     */
    #[Override]
    public function updateEmployeeActivity(string $assignmentId, array $data)
    {
        $assignment = $this->getAssignmentById($assignmentId);
        $task = $assignment->task;
        $this->ensureTaskAcceptsEmployeeChanges($task);
        $line = $this->getTaskLine($task);

        $lineActivity = $this->lineActivitiesService->getLineActivityById((string) $data['line_activity_id']);
        $activityName = $lineActivity->activity->name;

        if ($lineActivity->line_id !== $line->id) {
            throw new BadRequestError("La actividad '{$activityName}' no pertenece a la línea de la tarea");
        }

        $assignedCount = $task->employees()
            ->where('line_activity_id', $lineActivity->id)
            ->whereKeyNot($assignment->id)
            ->count();

        if ($assignedCount + 1 > $lineActivity->quantity) {
            throw new BadRequestError("La actividad '{$activityName}' admite {$lineActivity->quantity} personas y ya tiene {$assignedCount} asignadas");
        }

        $assignment->update(['line_activity_id' => $lineActivity->id]);

        return null;
    }

    /**
     * Active assignments of the task per line activity.
     *
     * @param  Collection<int, int>  $lineActivityIds
     * @return Collection<int, int>
     */
    private function getAssignedCounts(WeeklyPlanTask $task, Collection $lineActivityIds): Collection
    {
        return $task->employees()
            ->whereIn('line_activity_id', $lineActivityIds)
            ->selectRaw('line_activity_id, count(*) as total')
            ->groupBy('line_activity_id')
            ->pluck('total', 'line_activity_id');
    }

    /**
     * Quota errors for line activities whose assigned plus sent employees exceed their quantity.
     *
     * @param  Collection<int, LineActivity>  $lineActivities  keyed by id
     * @param  array<int, int>  $sentCounts  line_activity_id => employees sent
     * @param  Collection<int, int>  $assignedCounts  line_activity_id => employees already assigned
     * @return list<string>
     */
    private function collectQuotaErrors(Collection $lineActivities, array $sentCounts, Collection $assignedCounts): array
    {
        $errors = [];

        foreach ($sentCounts as $lineActivityId => $sentCount) {
            $lineActivity = $lineActivities->get($lineActivityId);

            if (! $lineActivity) {
                continue;
            }

            $assignedCount = (int) $assignedCounts->get($lineActivityId, 0);

            if ($assignedCount + $sentCount > $lineActivity->quantity) {
                $errors[] = "La actividad '{$lineActivity->activity->name}' admite {$lineActivity->quantity} personas; ya hay {$assignedCount} asignadas y se enviaron {$sentCount}";
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
     * Line whose activity quotas apply to the task's employees.
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
