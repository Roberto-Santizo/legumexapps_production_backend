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
    public function getWeeklyPlanEmployees(?string $limit)
    {
        $query = WeeklyPlanEmployee::query();

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
        $weeklyPlanEmployee->delete();

        return true;
    }

    #[Override]
    public function uploadFile(mixed $file)
    {
        $rows = Excel::toCollection(new WeeklyPlanEmployeesImport, $file)->first();

        $employeeIdsByCode = Employee::pluck('id', 'code');
        $positionIdsByCode = Position::pluck('id', 'code');
        $weeklyPlansByWeekAndYear = WeeklyPlan::get()->keyBy(fn (WeeklyPlan $weeklyPlan) => "{$weeklyPlan->week}-{$weeklyPlan->year}");

        $now = now();
        $errors = [];
        $weeklyPlanEmployeesToCreate = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;

            $employeeCode = $row['codigo'] ?? null;
            $positionCode = $row['posicion'] ?? null;
            $week = $row['semana'] ?? null;
            $year = $row['year'] ?? null;

            $employeeId = $employeeIdsByCode->get($employeeCode);
            $positionId = $positionIdsByCode->get($positionCode);
            $weeklyPlanId = $weeklyPlansByWeekAndYear->get("{$week}-{$year}")?->id;

            if (! $employeeId) {
                $errors[] = "Línea {$lineNumber}: el empleado con código '{$employeeCode}' no existe";
            }

            if (! $positionId) {
                $errors[] = "Línea {$lineNumber}: la posición con código '{$positionCode}' no existe";
            }

            if (! $weeklyPlanId) {
                $errors[] = "Línea {$lineNumber}: el plan semanal de la semana {$week} del año {$year} no existe";
            }

            if ($employeeId && $positionId && $weeklyPlanId) {
                $weeklyPlanEmployeesToCreate[] = [
                    'employee_id' => $employeeId,
                    'position_id' => $positionId,
                    'weekly_plan_id' => $weeklyPlanId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
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
