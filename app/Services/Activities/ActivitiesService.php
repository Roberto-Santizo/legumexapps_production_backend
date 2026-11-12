<?php

namespace App\Services\Activities;

use App\Errors\NotFoundError;
use App\Interfaces\Activities\ActivitiesServiceInterface;
use App\Models\Activity;
use Illuminate\Http\Request;
use Override;

class ActivitiesService implements ActivitiesServiceInterface
{
    #[Override]
    public function createActivity(array $data)
    {
        $newActivity = Activity::create([
            'name' => $data['name'],
            'unit' => $data['unit'],
        ]);

        return $newActivity;
    }

    #[Override]
    public function getActivities(?string $limit, Request $request)
    {
        $query = Activity::query();

        if ($request->filled('name')) {
            $query->where('name', 'LIKE', '%'.mb_strtoupper($request->query('name')).'%');
        }

        $query->orderBy('name');

        if ($limit) {
            return $query->paginate($limit);
        }

        return $query->get();
    }

    #[Override]
    public function getActivityById(string $id)
    {
        $activity = Activity::find($id, ['*']);
        if (! $activity) {
            throw new NotFoundError('La actividad no existe');
        }

        return $activity;
    }

    #[Override]
    public function updateActivityById(string $id, array $data)
    {
        $activity = $this->getActivityById($id);

        $activity->update($data);

        return true;
    }

    #[Override]
    public function deleteActivityById(string $id)
    {
        $activity = $this->getActivityById($id);

        $activity->delete();

        return true;
    }
}
