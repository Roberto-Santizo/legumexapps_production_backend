<?php

namespace App\Interfaces\WeeklyPlanTaskLotRecords;

use Illuminate\Http\Request;

interface WeeklyPlanTaskLotRecordsServiceInterface
{
    public function getWeeklyPlanTaskLotRecords(Request $request);

    public function createWeeklyPlanTaskLotRecord(array $data);

    public function getWeeklyPlanTaskLotRecordById(string $id);

    public function updateWeeklyPlanTaskLotRecordById(array $data, string $id);

    public function deleteWeeklyPlanTaskLotRecordById(string $id);
}
