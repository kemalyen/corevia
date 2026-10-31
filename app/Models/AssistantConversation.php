<?php

namespace App\Models;

use Database\Factories\AssistantConversationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssistantConversation extends Model
{
    /** @use HasFactory<AssistantConversationFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'account_id', 'title', 'last_message_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return HasMany<AssistantMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(AssistantMessage::class);
    }
}
