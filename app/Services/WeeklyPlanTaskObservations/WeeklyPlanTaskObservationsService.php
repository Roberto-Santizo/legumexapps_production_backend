<?php

namespace App\Services\WeeklyPlanTaskObservations;

use App\Errors\BadRequestError;
use App\Errors\ForbiddenError;
use App\Errors\NotFoundError;
use App\Interfaces\WeeklyPlanTaskObservations\WeeklyPlanTaskObservationsServiceInterface;
use App\Models\WeeklyPlanTaskObservation;
use Illuminate\Http\Request;
use Override;

class WeeklyPlanTaskObservationsService implements WeeklyPlanTaskObservationsServiceInterface
{
    #[Override]
    public function getWeeklyPlanTaskObservations(Request $request)
    {
        $weeklyPlanTaskId = $request->query('weeklyPlanTaskId');

        if (! $weeklyPlanTaskId) {
            throw new BadRequestError('El id de la tarea del plan semanal es obligatorio');
        }

        return WeeklyPlanTaskObservation::with('user')
            ->where('weekly_plan_task_id', $weeklyPlanTaskId)
            ->orderBy('created_at')
            ->get();
    }

    #[Override]
    public function createWeeklyPlanTaskObservation(array $data)
    {
        return WeeklyPlanTaskObservation::create([
            'weekly_plan_task_id' => $data['weekly_plan_task_id'],
            'user_id' => auth()->user()->id,
            'observation' => $data['observation'],
        ]);
    }

    #[Override]
    public function getWeeklyPlanTaskObservationById(string $id)
    {
        $observation = WeeklyPlanTaskObservation::with('user')->find($id);

        if (! $observation) {
            throw new NotFoundError('La observación no existe');
        }

        return $observation;
    }

    #[Override]
    public function updateWeeklyPlanTaskObservationById(array $data, string $id)
    {
        $observation = $this->getWeeklyPlanTaskObservationById($id);

        $this->ensureIsOwner($observation);

        $observation->update(['observation' => $data['observation']]);

        return true;
    }

    #[Override]
    public function deleteWeeklyPlanTaskObservationById(string $id)
    {
        $observation = $this->getWeeklyPlanTaskObservationById($id);

        $this->ensureIsOwner($observation);

        $observation->delete();

        return true;
    }

    /**
     * Only the author of an observation can modify or delete it.
     */
    private function ensureIsOwner(WeeklyPlanTaskObservation $observation): void
    {
        if ($observation->user_id !== auth()->user()->id) {
            throw new ForbiddenError('No puedes modificar una observación de otro usuario');
        }
    }
}
