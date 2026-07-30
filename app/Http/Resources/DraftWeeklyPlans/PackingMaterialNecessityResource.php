<?php

namespace App\Http\Resources\DraftWeeklyPlans;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class PackingMaterialNecessityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        $tasks = $this->tasks()->with('sku.packingMaterialItems.item')->get();

        return $tasks->flatMap(fn ($task) => $task->sku->packingMaterialItems->map(fn ($recipeItem) => [
                'code' => $recipeItem->item->code,
                'name' => $recipeItem->item->name,
                'quantity' => ($task->boxes * $task->sku->presentation) / $recipeItem->lbs_per_item,
            ]))
            ->groupBy('code')
            ->map(fn (Collection $items) => [
                'label' => "{$items->first()['name']} - {$items->first()['code']}",
                'value' => $items->sum('quantity'),
            ])
            ->values()
            ->all();
    }
}
