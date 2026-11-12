<?php

namespace App\Interfaces\Activities;

use Illuminate\Http\Request;

interface ActivitiesServiceInterface
{
    public function createActivity(array $data);

    public function getActivities(?string $limit, Request $request);

    public function getActivityById(string $id);

    public function updateActivityById(string $id, array $data);

    public function deleteActivityById(string $id);
}
