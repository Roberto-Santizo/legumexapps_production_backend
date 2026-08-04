<?php

namespace App\Interfaces\WeeklyPlanTaskObservations;

use Illuminate\Http\Request;

interface WeeklyPlanTaskObservationsServiceInterface
{
    public function getWeeklyPlanTaskObservations(Request $request);

    public function createWeeklyPlanTaskObservation(array $data);

    public function getWeeklyPlanTaskObservationById(string $id);

    public function updateWeeklyPlanTaskObservationById(array $data, string $id);

    public function deleteWeeklyPlanTaskObservationById(string $id);
}
