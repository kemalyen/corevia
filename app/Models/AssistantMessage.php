<?php

namespace App\Models;

use Database\Factories\AssistantMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantMessage extends Model
{
    /** @use HasFactory<AssistantMessageFactory> */
    use HasFactory;

    protected $fillable = ['assistant_conversation_id', 'role', 'content', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    /** @return BelongsTo<AssistantConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'assistant_conversation_id');
    }
}
