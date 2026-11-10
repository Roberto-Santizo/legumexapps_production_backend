<?php

namespace App\Interfaces\WeeklyPlanTaskProductRecords;

use Illuminate\Http\Request;

interface WeeklyPlanTaskProductRecordsServiceInterface
{
    public function getWeeklyPlanTaskProductRecords(Request $request);

    public function createWeeklyPlanTaskProductRecord(array $data);

    public function getWeeklyPlanTaskProductRecordById(string $id);

    public function updateWeeklyPlanTaskProductRecordById(array $data, string $id);

    public function deleteWeeklyPlanTaskProductRecordById(string $id);
}
