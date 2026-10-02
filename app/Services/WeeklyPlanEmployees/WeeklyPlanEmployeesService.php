<?php

namespace App\Services\WeeklyPlanEmployees;

use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Imports\WeeklyPlanEmployeesImport;
use App\Interfaces\WeeklyPlanEmployees\WeeklyPlanEmployeesServiceInterface;
use App\Models\Employee;
use App\Models\Position;
use App\Models\WeeklyPlan;
use App\Models\WeeklyPlanEmployee;
use App\Models\WeeklyPlanTaskEmployee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Override;

class WeeklyPlanEmployeesService implements WeeklyPlanEmployeesServiceInterface
{
    #[Override]
    public function createWeeklyPlanEmployee(array $data)
    {
        $weeklyPlanEmployee = WeeklyPlanEmployee::create($data);

        return $weeklyPlanEmployee;
    }

    #[Override]
    public function getWeeklyPlanEmployees(?string $limit, Request $request)
    {
        $query = WeeklyPlanEmployee::query()->with(['employee', 'position']);

        if ($request->query('name')) {
            $query->whereHas('employee', function ($p0) use ($request) {
                $p0->where('name', 'LIKE', '%'.$request->query('name').'%');
            });
        }

        if ($request->query('code')) {
            $query->whereHas('employee', function ($p0) use ($request) {
                $p0->where('code', 'LIKE', '%'.$request->query('code').'%');
            });
        }

        if ($request->query('position')) {
            $query->whereHas('position', function ($p0) use ($request) {
                $p0->where('code', 'LIKE', '%'.$request->query('position').'%');
            });
        }

        if ($request->query('week')) {
            $query->whereHas('weeklyPlan', function ($p0) use ($request) {
                $p0->where('week', '=', $request->query('week'));
            });
        }

        if ($request->query('year')) {
            $query->whereHas('weeklyPlan', function ($p0) use ($request) {
                $p0->where('year', '=', $request->query('year'));
            });
        }

        if ($limit) {
            return $query->paginate($limit);
        }

        return $query->get();
    }

    #[Override]
    public function getWeeklyPlanEmployeeById(string $id)
    {
        $weeklyPlanEmployee = WeeklyPlanEmployee::find($id);
        if (! $weeklyPlanEmployee) {
            throw new NotFoundError('El empleado del plan semanal no existe');
        }

        return $weeklyPlanEmployee;
    }

    #[Override]
    public function updateWeeklyPlanEmployeeById(array $data, string $id)
    {
        $weeklyPlanEmployee = $this->getWeeklyPlanEmployeeById($id);
        $weeklyPlanEmployee->update($data);

        return true;
    }

    #[Override]
    public function deleteWeeklyPlanEmployeeById(string $id)
    {
        $weeklyPlanEmployee = $this->getWeeklyPlanEmployeeById($id);

        $hasTaskAssignments = WeeklyPlanTaskEmployee::withTrashed()
            ->where('weekly_plan_employee_id', $weeklyPlanEmployee->id)
            ->orWhere('replaced_weekly_plan_employee_id', $weeklyPlanEmployee->id)
            ->exists();

        if ($hasTaskAssignments) {
            throw new BadRequestError('No se puede eliminar un empleado del plan con personal asignado en tareas');
        }

        $weeklyPlanEmployee->delete();

        return true;
    }

    #[Override]
    public function uploadFile(mixed $file)
    {
        $rows = Excel::toCollection(new WeeklyPlanEmployeesImport, $file)->first() ?? collect();

        if ($rows->isEmpty()) {
            throw new BadRequestError('El archivo no contiene filas');
        }

        $employeeIdsByCode = Employee::pluck('id', 'code');
        $employeeCodesById = $employeeIdsByCode->flip();
        $positionIdsByCode = Position::pluck('id', 'code');
        $positionCodesById = $positionIdsByCode->flip();
        $weeklyPlansByWeekAndYear = WeeklyPlan::get()->keyBy(fn (WeeklyPlan $weeklyPlan) => "{$weeklyPlan->week}-{$weeklyPlan->year}");

        $weeklyPlanIdsInFile = $rows
            ->map(fn ($row) => $weeklyPlansByWeekAndYear->get(trim((string) ($row['semana'] ?? '')).'-'.trim((string) ($row['year'] ?? '')))?->id)
            ->filter()
            ->unique();

        $positionIdsByPlanEmployee = [];
        $employeeIdsByPlanPosition = [];

        WeeklyPlanEmployee::whereIn('weekly_plan_id', $weeklyPlanIdsInFile)
            ->get(['weekly_plan_id', 'employee_id', 'position_id'])
            ->each(function (WeeklyPlanEmployee $weeklyPlanEmployee) use (&$positionIdsByPlanEmployee, &$employeeIdsByPlanPosition) {
                $positionIdsByPlanEmployee["{$weeklyPlanEmployee->weekly_plan_id}-{$weeklyPlanEmployee->employee_id}"] = (int) $weeklyPlanEmployee->position_id;
                $employeeIdsByPlanPosition["{$weeklyPlanEmployee->weekly_plan_id}-{$weeklyPlanEmployee->position_id}"] = (int) $weeklyPlanEmployee->employee_id;
            });

        $now = now();
        $errors = [];
        $weeklyPlanEmployeesToCreate = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;
            $rowErrors = [];

            $employeeCode = trim((string) ($row['codigo'] ?? ''));
            $positionCode = trim((string) ($row['posicion'] ?? ''));
            $week = trim((string) ($row['semana'] ?? ''));
            $year = trim((string) ($row['year'] ?? ''));

            $employeeId = (int) $employeeIdsByCode->get($employeeCode);
            $positionId = (int) $positionIdsByCode->get($positionCode);
            $weeklyPlanId = $weeklyPlansByWeekAndYear->get("{$week}-{$year}")?->id;

            if ($employeeCode === '') {
                $rowErrors[] = "Línea {$lineNumber}: el campo 'codigo' es obligatorio";
            } elseif (! $employeeId) {
                $rowErrors[] = "Línea {$lineNumber}: el empleado con código '{$employeeCode}' no existe";
            }

            if ($positionCode === '') {
                $rowErrors[] = "Línea {$lineNumber}: el campo 'posicion' es obligatorio";
            } elseif (! $positionId) {
                $rowErrors[] = "Línea {$lineNumber}: la posición con código '{$positionCode}' no existe";
            }

            if ($week === '' || $year === '') {
                $rowErrors[] = "Línea {$lineNumber}: los campos 'semana' y 'year' son obligatorios";
            } elseif (! $weeklyPlanId) {
                $rowErrors[] = "Línea {$lineNumber}: el plan semanal de la semana {$week} del año {$year} no existe";
            }

            if ($employeeId && $positionId && $weeklyPlanId) {
                $planLabel = "el plan de la semana {$week} del año {$year}";
                $assignedPositionId = $positionIdsByPlanEmployee["{$weeklyPlanId}-{$employeeId}"] ?? null;
                $assignedEmployeeId = $employeeIdsByPlanPosition["{$weeklyPlanId}-{$positionId}"] ?? null;

                if ($assignedPositionId === $positionId) {
                    $rowErrors[] = "Línea {$lineNumber}: el empleado '{$employeeCode}' ya está registrado en {$planLabel}";
                } elseif ($assignedPositionId) {
                    $rowErrors[] = "Línea {$lineNumber}: el empleado '{$employeeCode}' ya está asignado a la posición '{$positionCodesById->get($assignedPositionId)}' en {$planLabel}";
                }

                if ($assignedEmployeeId && $assignedEmployeeId !== $employeeId) {
                    $rowErrors[] = "Línea {$lineNumber}: la posición '{$positionCode}' ya está ocupada por el empleado '{$employeeCodesById->get($assignedEmployeeId)}' en {$planLabel}";
                }
            }

            if (! empty($rowErrors)) {
                array_push($errors, ...$rowErrors);

                continue;
            }

            $positionIdsByPlanEmployee["{$weeklyPlanId}-{$employeeId}"] = $positionId;
            $employeeIdsByPlanPosition["{$weeklyPlanId}-{$positionId}"] = $employeeId;

            $weeklyPlanEmployeesToCreate[] = [
                'employee_id' => $employeeId,
                'position_id' => $positionId,
                'weekly_plan_id' => $weeklyPlanId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($errors)) {
            throw new BadRequestError(implode(PHP_EOL, $errors));
        }

        DB::transaction(function () use ($weeklyPlanEmployeesToCreate) {
            WeeklyPlanEmployee::insert($weeklyPlanEmployeesToCreate);
        });

        return ['created' => count($weeklyPlanEmployeesToCreate)];
    }
}
