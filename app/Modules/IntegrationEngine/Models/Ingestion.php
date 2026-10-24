<?php

namespace App\Modules\IntegrationEngine\Models;

use App\Modules\IntegrationEngine\Enums\IngestionStatus;
use App\Modules\IntegrationEngine\Enums\IntegrationDataset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingestion extends Model
{
    protected $fillable = [
        'dataset', 'original_filename', 'processing_filename', 'checksum', 'status',
        'file_size', 'attempts', 'total_rows', 'valid_rows', 'failed_rows', 'created_records',
        'updated_records', 'last_error', 'discovered_at', 'claimed_at', 'queued_at',
        'processing_started_at', 'completed_at', 'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'dataset' => IntegrationDataset::class,
            'status' => IngestionStatus::class,
            'discovered_at' => 'immutable_datetime',
            'claimed_at' => 'immutable_datetime',
            'queued_at' => 'immutable_datetime',
            'processing_started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }

    public function errors(): HasMany
    {
        return $this->hasMany(IngestionError::class);
    }

    public function stagedSalesOrderRows(): HasMany
    {
        return $this->hasMany(StagedSalesOrderRow::class);
    }
}
