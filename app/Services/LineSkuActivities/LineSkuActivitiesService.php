<?php

namespace App\Services\LineSkuActivities;

use App\Errors\BadRequestError;
use App\Errors\NotFoundError;
use App\Interfaces\Activities\ActivitiesServiceInterface;
use App\Interfaces\LineSkuActivities\LineSkuActivitiesServiceInterface;
use App\Interfaces\LineSkus\LineSkusServiceInterface;
use App\Models\LineSkuActivity;
use App\Models\Position;
use Override;

class LineSkuActivitiesService implements LineSkuActivitiesServiceInterface
{
    public function __construct(
        private LineSkusServiceInterface $lineSkusService,
        private ActivitiesServiceInterface $activitiesService,
    ) {}

    #[Override]
    public function getLineSkuActivities(string $lineSkuId)
    {
        $lineSku = $this->lineSkusService->getLineSkuById($lineSkuId);

        return $lineSku->lineSkuActivities()
            ->select('line_sku_activities.*')
            ->join('activities', 'activities.id', '=', 'line_sku_activities.activity_id')
            ->with('activity')
            ->orderBy('activities.name')
            ->get();
    }

    /**
     * @param  array{activity_id: int, quantity: int}  $data
     */
    #[Override]
    public function createLineSkuActivity(string $lineSkuId, array $data)
    {
        $lineSku = $this->lineSkusService->getLineSkuById($lineSkuId);
        $activity = $this->activitiesService->getActivityById($data['activity_id']);

        if ($lineSku->lineSkuActivities()->where('activity_id', $activity->id)->exists()) {
            throw new BadRequestError('La actividad ya está asignada al SKU de línea');
        }

        return LineSkuActivity::create([
            'line_sku_id' => $lineSku->id,
            'activity_id' => $activity->id,
            'quantity' => $data['quantity'],
        ]);
    }

    #[Override]
    public function getLineSkuActivityById(string $id)
    {
        $lineSkuActivity = LineSkuActivity::with('activity')->find($id);

        if (! $lineSkuActivity) {
            throw new NotFoundError('La actividad del SKU de línea no existe');
        }

        return $lineSkuActivity;
    }

    /**
     * @param  array{quantity: int}  $data
     */
    #[Override]
    public function updateLineSkuActivityById(string $id, array $data)
    {
        $lineSkuActivity = $this->getLineSkuActivityById($id);

        $lineSkuActivity->update(['quantity' => $data['quantity']]);

        return true;
    }

    #[Override]
    public function deleteLineSkuActivityById(string $id)
    {
        $lineSkuActivity = $this->getLineSkuActivityById($id);

        if ($lineSkuActivity->taskEmployees()->withTrashed()->exists()) {
            throw new BadRequestError('No se puede quitar una actividad con personal asignado');
        }

        $hasPositions = Position::where('line_sku_id', $lineSkuActivity->line_sku_id)
            ->where('activity_id', $lineSkuActivity->activity_id)
            ->exists();

        if ($hasPositions) {
            throw new BadRequestError('No se puede quitar una actividad con posiciones asignadas');
        }

        $lineSkuActivity->delete();

        return true;
    }
}
