<?php

namespace App\Modules\IntegrationEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngestionError extends Model
{
    protected $fillable = ['row_number', 'field', 'code', 'message'];

    public function ingestion(): BelongsTo
    {
        return $this->belongsTo(Ingestion::class);
    }
}
