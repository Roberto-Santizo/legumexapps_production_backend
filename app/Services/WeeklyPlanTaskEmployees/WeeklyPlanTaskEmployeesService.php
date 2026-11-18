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
        $assignedCounts = $task->employees()
            ->whereIn('line_activity_id', $lineActivities->keys())
            ->selectRaw('line_activity_id, count(*) as total')
            ->groupBy('line_activity_id')
            ->pluck('total', 'line_activity_id');

        $errors = [];
        $bulkData = [];
        $now = now();

        foreach ($data['employees'] as $employee) {
            $position = $positions->get($employee['position']);
            $lineActivityId = (int) $employee['line_activity_id'];
            $lineActivity = $lineActivities->get($lineActivityId);
            $hasErrors = false;

            if (! $position) {
                $errors[] = "La posición '{$employee['position']}' no existe";
                $hasErrors = true;
            }

            if (! $lineActivity) {
                $errors[] = "La actividad de la línea '{$lineActivityId}' no existe";
                $hasErrors = true;
            } elseif ($lineActivity->line_id !== $line->id) {
                $errors[] = "La actividad '{$lineActivity->activity->name}' no pertenece a la línea de la tarea";
                $hasErrors = true;
            }

            if ($hasErrors) {
                continue;
            }

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

        foreach (array_count_values($lineActivityIds) as $lineActivityId => $sentCount) {
            $lineActivity = $lineActivities->get($lineActivityId);

            if (! $lineActivity || $lineActivity->line_id !== $line->id) {
                continue;
            }

            $assignedCount = (int) $assignedCounts->get($lineActivityId, 0);

            if ($assignedCount + $sentCount > $lineActivity->quantity) {
                $errors[] = "La actividad '{$lineActivity->activity->name}' admite {$lineActivity->quantity} personas; ya hay {$assignedCount} asignadas y se enviaron {$sentCount}";
            }
        }

        if (! empty($errors)) {
            throw new BadRequestError(implode(PHP_EOL, $errors));
        }

        DB::transaction(function () use ($task, $bulkData) {
            WeeklyPlanTaskEmployee::insert($bulkData);
            $task->status = 3;
            $task->save();
        });

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
