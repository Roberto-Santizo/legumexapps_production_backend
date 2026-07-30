<?php

namespace App\Http\Resources\DraftWeeklyPlans;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class RawMaterialNecessityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tasks = $this->tasks()->with('sku.rawMaterialItems.item')->get();

        return $tasks->flatMap(fn ($task) => $task->sku->rawMaterialItems->map(fn ($recipeItem) => [
            'code' => $recipeItem->item->code,
            'name' => $recipeItem->item->product_name,
            'quantity' => ($task->boxes * $task->sku->presentation) * $recipeItem->percentage,
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
