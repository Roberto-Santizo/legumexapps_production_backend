<?php

namespace App\Interfaces\WeeklyPlanTaskPerformanceRecords;

use Illuminate\Http\Request;

interface WeeklyPlanTaskPerformanceRecordsServiceInterface
{
    public function getWeeklyPlanTaskPerformanceRecords(Request $request);

    public function createWeeklyPlanTaskPerformanceRecord(array $data);

    public function getWeeklyPlanTaskPerformanceRecordById(string $id);

    public function updateWeeklyPlanTaskPerformanceRecordById(array $data, string $id);

    public function deleteWeeklyPlanTaskPerformanceRecordById(string $id);
}
