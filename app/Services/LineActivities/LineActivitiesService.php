<?php

namespace App\Services\LineActivities;

use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Interfaces\Activities\ActivitiesServiceInterface;
use App\Interfaces\LineActivities\LineActivitiesServiceInterface;
use App\Interfaces\Lines\LinesServiceInterface;
use App\Models\LineActivity;
use Override;

class LineActivitiesService implements LineActivitiesServiceInterface
{
    public function __construct(
        private LinesServiceInterface $linesService,
        private ActivitiesServiceInterface $activitiesService,
    ) {}

    #[Override]
    public function getLineActivities(string $lineCode)
    {
        $line = $this->linesService->getLineByCode($lineCode);

        return $line->lineActivities()
            ->select('line_activities.*')
            ->join('activities', 'activities.id', '=', 'line_activities.activity_id')
            ->with('activity')
            ->orderBy('activities.name')
            ->get();
    }

    /**
     * @param  array{activity_id: int, quantity: int}  $data
     */
    #[Override]
    public function createLineActivity(string $lineCode, array $data)
    {
        $line = $this->linesService->getLineByCode($lineCode);
        $activity = $this->activitiesService->getActivityById($data['activity_id']);

        if ($line->lineActivities()->where('activity_id', $activity->id)->exists()) {
            throw new BadRequestError('La actividad ya está asignada a la línea');
        }

        return LineActivity::create([
            'line_id' => $line->id,
            'activity_id' => $activity->id,
            'quantity' => $data['quantity'],
        ]);
    }

    #[Override]
    public function getLineActivityById(string $id)
    {
        $lineActivity = LineActivity::with('activity')->find($id);

        if (! $lineActivity) {
            throw new NotFoundError('La actividad de la línea no existe');
        }

        return $lineActivity;
    }

    /**
     * @param  array{quantity: int}  $data
     */
    #[Override]
    public function updateLineActivityById(string $id, array $data)
    {
        $lineActivity = $this->getLineActivityById($id);

        $lineActivity->update(['quantity' => $data['quantity']]);

        return true;
    }

    #[Override]
    public function deleteLineActivityById(string $id)
    {
        $lineActivity = $this->getLineActivityById($id);

        $lineActivity->delete();

        return true;
    }
}
