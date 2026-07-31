<?php

namespace App\Http\Resources\PackingMaterialTransactions;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackingMaterialTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'responsable' => $this->responsable,
            'observations' => $this->observations,
            'responsable_signature' => $this->responsable_signature,
            'user_signature' => $this->user_signature,
            'type' => $this->type,
            'user_id' => $this->user_id,
            'user_name' => $this->user->name,
            'weekly_plan_task_id' => $this->weekly_plan_task_id,
        ];
    }
}
