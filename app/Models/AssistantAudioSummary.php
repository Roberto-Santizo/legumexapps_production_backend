<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'conversation_id', 'path', 'mime_type', 'characters', 'provider', 'model', 'voice'])]
class AssistantAudioSummary extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
