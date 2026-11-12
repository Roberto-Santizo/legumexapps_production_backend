<?php

namespace App\Http\Resources\WeeklyPlanTaskAssistant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AssistantAudioSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'audio_id' => $this->id,
            'audio_url' => Storage::disk('s3')->url($this->path),
            'mime_type' => $this->mime_type,
            'characters' => $this->characters,
        ];
    }
}
