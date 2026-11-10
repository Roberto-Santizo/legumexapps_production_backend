<?php

namespace App\Http\Resources\WeeklyPlanTaskAssistant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationMessageResource extends JsonResource
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
            'role' => $this->role,
            'content' => $this->content,
            'created_at' => $this->created_at?->format('d-m-Y H:i'),
        ];
    }
}
