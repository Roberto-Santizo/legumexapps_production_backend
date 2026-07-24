<?php

namespace App\Services\WeeklyPlanTasks;

use App\Errors\NotFoundError;
use App\Interfaces\WeeklyPlanTasks\WeeklyPlanTasksServiceInterface;
use App\Models\LineSku;
use App\Models\WeeklyPlanTask;
use Illuminate\Http\Request;
use Override;

class WeeklyPlanTasksService implements WeeklyPlanTasksServiceInterface
{
    #[Override]
    public function createWeeklyPlanTask(array $data)
    {
        $performance = LineSku::find($data['line_sku_id'], ['*'])->load(['sku']);
        $sku = $performance->sku;
        $total_lbs = $data['boxes'] * $sku->presentation;
        
        $payload = [
            'boxes' =>          $data['boxes'],
            'pallets'=>         $data['boxes'] / $sku->boxes_per_pallet,
            'hours'=>           $total_lbs / $performance->lbs_performance,
            'destination'=>     $data['destination'],
            'operation_date'=>  $data['operation_date'],
            'weekly_plan_id'=>  $data['weekly_plan_id'],
            'line_sku_id'=>     $data['line_sku_id'],
        ];

        $weeklyPlanTask = WeeklyPlanTask::create($payload);

        return $weeklyPlanTask;
    }

    #[Override]
    public function getWeeklyPlanTasks(?string $limit, Request $request)
    {
        $query = WeeklyPlanTask::query();
        $query->with(['performance', 'performance.sku', 'performance.line']);
        
        if($request->query('weeklyPlanId')) $query->where('weekly_plan_id', $request->query('weeklyPlanId'));
        
        if ($limit) return $query->paginate($limit);
        
        return $query->get();
    }

    #[Override]
    public function getWeeklyPlanTaskById(string $id)
    {
        $weeklyPlanTask = WeeklyPlanTask::find($id);
        if (! $weeklyPlanTask) {
            throw new NotFoundError('La tarea del plan semanal no existe');
        }

        return $weeklyPlanTask;
    }

    #[Override]
    public function updateWeeklyPlanTaskById(array $data, string $id)
    {
        $weeklyPlanTask = $this->getWeeklyPlanTaskById($id);
        $weeklyPlanTask->update($data);

        return true;
    }

    #[Override]
    public function deleteWeeklyPlanTaskById(string $id)
    {
        $weeklyPlanTask = $this->getWeeklyPlanTaskById($id);
        $weeklyPlanTask->delete();

        return true;
    }
}
