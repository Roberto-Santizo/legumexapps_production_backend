<?php

namespace App\Services\WeeklyPlanTaskEmployees;

use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Interfaces\WeeklyPlanTaskEmployees\WeeklyPlanTaskEmployeesServiceInterface;
use App\Interfaces\WeeklyPlanTasks\WeeklyPlanTasksServiceInterface;
use App\Models\Position;
use App\Models\WeeklyPlanEmployee;
use App\Models\WeeklyPlanTask;
use App\Models\WeeklyPlanTaskEmployee;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Override;

class WeeklyPlanTaskEmployeesService implements WeeklyPlanTaskEmployeesServiceInterface
{
    public function __construct(private WeeklyPlanTasksServiceInterface $weeklyPlanTasksService) {}

    #[Override]
    public function getAvailableEmployees(string $taskId)
    {
        $task = $this->weeklyPlanTasksService->getWeeklyPlanTaskById($taskId);

        return $this->getCandidates($task);
    }

    #[Override]
    public function getTaskEmployees(string $taskId)
    {
        $task = $this->weeklyPlanTasksService->getWeeklyPlanTaskById($taskId);

        return $task->employees()
            ->with(['weeklyPlanEmployee.employee', 'position', 'replacedWeeklyPlanEmployee.employee'])
            ->get();
    }

    #[Override]
    public function confirmEmployees(string $taskId, array $data)
    {
        $task = $this->weeklyPlanTasksService->getWeeklyPlanTaskById($taskId);
        $this->ensureTaskAwaitsEmployeeConfirmation($task);

        $replacements = collect($data['replacements'] ?? [])->map(fn (array $replacement) => [
            'replaced' => (int) $replacement['weekly_plan_employee_id'],
            'replacement' => (int) $replacement['replacement_weekly_plan_employee_id'],
        ]);
        $additions = collect($data['additions'] ?? [])->map(fn ($id) => (int) $id);
        $removals = collect($data['removals'] ?? [])->map(fn ($id) => (int) $id);

        $candidates = $this->getCandidates($task)->keyBy('id');
        $replacedIds = $replacements->pluck('replaced');
        $incomingIds = $replacements->pluck('replacement')->merge($additions);

        $weeklyPlanEmployees = WeeklyPlanEmployee::with('employee')
            ->findMany($replacedIds->merge($incomingIds)->merge($removals)->unique()->values())
            ->keyBy('id');
        $codeOf = fn (int $id) => $weeklyPlanEmployees->get($id)->employee->code;

        $errors = [];

        foreach ($replacedIds->merge($removals)->unique() as $id) {
            if (! $candidates->has($id)) {
                $errors[] = "El empleado '{$codeOf($id)}' no es candidato de la tarea";
            }
        }

        foreach ($removals->intersect($replacedIds) as $id) {
            if ($candidates->has($id)) {
                $errors[] = "El empleado '{$codeOf($id)}' no puede quitarse y reemplazarse a la vez";
            }
        }

        foreach ($incomingIds->unique() as $id) {
            if ($weeklyPlanEmployees->get($id)->weekly_plan_id != $task->weekly_plan_id) {
                $errors[] = "El empleado '{$codeOf($id)}' no pertenece al plan semanal de la tarea";
            }
        }

        $assignments = $candidates
            ->reject(fn (WeeklyPlanEmployee $candidate) => $removals->contains($candidate->id) || $replacedIds->contains($candidate->id))
            ->map(fn (WeeklyPlanEmployee $candidate) => [
                'weekly_plan_employee_id' => $candidate->id,
                'position_id' => $candidate->position_id,
                'replaced_weekly_plan_employee_id' => null,
            ])
            ->values()
            ->merge($replacements->map(fn (array $replacement) => [
                'weekly_plan_employee_id' => $replacement['replacement'],
                'position_id' => $candidates->get($replacement['replaced'])?->position_id,
                'replaced_weekly_plan_employee_id' => $replacement['replaced'],
            ]))
            ->merge($additions->map(fn (int $id) => [
                'weekly_plan_employee_id' => $id,
                'position_id' => $weeklyPlanEmployees->get($id)->position_id,
                'replaced_weekly_plan_employee_id' => null,
            ]));

        $assignments->countBy('weekly_plan_employee_id')
            ->filter(fn (int $count) => $count > 1)
            ->each(function (int $count, int $id) use (&$errors, $codeOf) {
                $errors[] = "El empleado '{$codeOf($id)}' está asignado más de una vez";
            });

        if ($assignments->isEmpty()) {
            $errors[] = 'La tarea debe tener al menos un empleado asignado';
        }

        if (! empty($errors)) {
            throw new BadRequestError(implode(PHP_EOL, $errors));
        }

        $now = now();
        $assignmentsToCreate = $assignments
            ->map(fn (array $assignment) => [
                'weekly_plan_task_id' => $task->id,
                ...$assignment,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        DB::transaction(function () use ($task, $assignmentsToCreate) {
            $lockedTask = WeeklyPlanTask::lockForUpdate()->find($task->id);
            $this->ensureTaskAwaitsEmployeeConfirmation($lockedTask);

            WeeklyPlanTaskEmployee::insert($assignmentsToCreate);

            $lockedTask->status = 3;
            $lockedTask->save();
        });

        return null;
    }

    #[Override]
    public function addEmployee(string $taskId, int $weeklyPlanEmployeeId)
    {
        $task = $this->weeklyPlanTasksService->getWeeklyPlanTaskById($taskId);
        $this->ensureTaskAcceptsEmployeeChanges($task);

        $weeklyPlanEmployee = WeeklyPlanEmployee::with('employee')->find($weeklyPlanEmployeeId);
        $this->ensureEmployeeCanJoinTask($task, $weeklyPlanEmployee);

        WeeklyPlanTaskEmployee::create([
            'weekly_plan_task_id' => $task->id,
            'weekly_plan_employee_id' => $weeklyPlanEmployee->id,
            'position_id' => $weeklyPlanEmployee->position_id,
            'replaced_weekly_plan_employee_id' => null,
        ]);

        return null;
    }

    #[Override]
    public function replaceEmployee(string $assignmentId, int $weeklyPlanEmployeeId)
    {
        $assignment = $this->getAssignmentById($assignmentId);
        $task = $assignment->task;
        $this->ensureTaskAcceptsEmployeeChanges($task);

        $weeklyPlanEmployee = WeeklyPlanEmployee::with('employee')->find($weeklyPlanEmployeeId);
        $this->ensureEmployeeCanJoinTask($task, $weeklyPlanEmployee);

        DB::transaction(function () use ($assignment, $weeklyPlanEmployee) {
            $assignment->delete();

            WeeklyPlanTaskEmployee::create([
                'weekly_plan_task_id' => $assignment->weekly_plan_task_id,
                'weekly_plan_employee_id' => $weeklyPlanEmployee->id,
                'position_id' => $assignment->position_id,
                'replaced_weekly_plan_employee_id' => $assignment->weekly_plan_employee_id,
            ]);
        });

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
     * Individual changes are only allowed while the task is ready for execution or in progress.
     */
    private function ensureTaskAcceptsEmployeeChanges(WeeklyPlanTask $task): void
    {
        if (! in_array($task->status, [3, 4])) {
            throw new BadRequestError('Solo se puede modificar el personal de una tarea lista para ejecución o en progreso');
        }
    }

    /**
     * The incoming employee must belong to the task's weekly plan and not be actively assigned to it.
     */
    private function ensureEmployeeCanJoinTask(WeeklyPlanTask $task, WeeklyPlanEmployee $weeklyPlanEmployee): void
    {
        $code = $weeklyPlanEmployee->employee->code;

        if ($weeklyPlanEmployee->weekly_plan_id != $task->weekly_plan_id) {
            throw new BadRequestError("El empleado '{$code}' no pertenece al plan semanal de la tarea");
        }

        if ($task->employees()->where('weekly_plan_employee_id', $weeklyPlanEmployee->id)->exists()) {
            throw new BadRequestError("El empleado '{$code}' ya está asignado a la tarea");
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

    /**
     * Employees of the task's weekly plan whose active position belongs to the task's line.
     *
     * @return Collection<int, WeeklyPlanEmployee>
     */
    private function getCandidates(WeeklyPlanTask $task): Collection
    {
        $lineId = $task->performance->line_id;

        return WeeklyPlanEmployee::with(['employee', 'position'])
            ->where('weekly_plan_id', $task->weekly_plan_id)
            ->whereHas('position', function ($query) use ($lineId) {
                $query->where('line_id', $lineId)->where('status', 1);
            })
            ->orderBy(
                Position::select('code')->whereColumn('positions.id', 'weekly_plan_employees.position_id')
            )
            ->get();
    }
}
