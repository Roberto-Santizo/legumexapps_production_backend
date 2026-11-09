<?php

namespace App\Services\WeeklyPlanTaskEmployees;

use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Interfaces\BiometricQueries\BiometricQueriesServiceInterface;
use App\Interfaces\WeeklyPlanTaskEmployees\WeeklyPlanTaskEmployeesServiceInterface;
use App\Interfaces\WeeklyPlanTasks\WeeklyPlanTasksServiceInterface;
use App\Models\WeeklyPlanEmployee;
use App\Models\WeeklyPlanTask;
use App\Models\WeeklyPlanTaskEmployee;
use Illuminate\Support\Facades\DB;
use Override;

class WeeklyPlanTaskEmployeesService implements WeeklyPlanTaskEmployeesServiceInterface
{
    public function __construct(
        private WeeklyPlanTasksServiceInterface $weeklyPlanTasksService,
        private BiometricQueriesServiceInterface $biometricQueriesService,
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
        $employees = $task->employees;

        return $employees;
    }

    #[Override]
    public function confirmEmployees(string $taskId, array $data)
    {
        $task = $this->weeklyPlanTasksService->getWeeklyPlanTaskById($taskId);
        $bulkData = [];

        foreach ($data['employees'] as $employee) {
            $bulkData[] = [
                'weekly_plan_task_id' => $task->id,
                'code' => $employee['code'],
                'name' => $employee['name'],
                'position' => $employee['position'],
            ];

        }

        DB::table('weekly_plan_task_employees')->insert($bulkData);
        $task->status = 3;
        $task->save();

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
}
