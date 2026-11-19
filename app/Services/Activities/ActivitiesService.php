<?php

namespace App\Services\Activities;

use App\Errors\BadRequestError;
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

        if ($request->filled('line_id')) {
            $query->whereHas('lineActivities', fn ($q) => $q->where('line_id', $request->query('line_id')));
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

        if ($activity->lineActivities()->exists()) {
            throw new BadRequestError('No se puede eliminar una actividad asignada a líneas');
        }

        if ($activity->positions()->exists()) {
            throw new BadRequestError('No se puede eliminar una actividad asignada a posiciones');
        }

        $activity->delete();

        return true;
    }
}
