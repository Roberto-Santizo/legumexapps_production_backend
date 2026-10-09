<?php

namespace App\Services\WeeklyPlanTaskLotRecords;

use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Interfaces\WeeklyPlanTaskLotRecords\WeeklyPlanTaskLotRecordsServiceInterface;
use App\Models\WeeklyPlanTaskLotRecord;
use Illuminate\Http\Request;
use Override;

class WeeklyPlanTaskLotRecordsService implements WeeklyPlanTaskLotRecordsServiceInterface
{
    #[Override]
    public function getWeeklyPlanTaskLotRecords(Request $request)
    {
        $weeklyPlanTaskId = $request->query('weeklyPlanTaskId');

        if (! $weeklyPlanTaskId) {
            throw new BadRequestError('El id de la tarea del plan semanal es obligatorio');
        }

        return WeeklyPlanTaskLotRecord::with('user')
            ->where('weekly_plan_task_id', $weeklyPlanTaskId)
            ->orderBy('created_at')
            ->get();
    }

    #[Override]
    public function createWeeklyPlanTaskLotRecord(array $data)
    {
        throw new BadRequestError('La captura por lote aún no está disponible');
    }

    #[Override]
    public function getWeeklyPlanTaskLotRecordById(string $id)
    {
        $record = WeeklyPlanTaskLotRecord::with('user')->find($id);

        if (! $record) {
            throw new NotFoundError('El registro de lote no existe');
        }

        return $record;
    }

    #[Override]
    public function updateWeeklyPlanTaskLotRecordById(array $data, string $id)
    {
        throw new BadRequestError('La captura por lote aún no está disponible');
    }

    #[Override]
    public function deleteWeeklyPlanTaskLotRecordById(string $id)
    {
        throw new BadRequestError('La captura por lote aún no está disponible');
    }
}
