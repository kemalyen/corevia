<?php

namespace App\Modules\IntegrationEngine\Actions;

use App\Modules\IntegrationEngine\Enums\IngestionStatus;
use App\Modules\IntegrationEngine\Jobs\ProcessIngestionJob;
use App\Modules\IntegrationEngine\Models\Ingestion;
use App\Modules\IntegrationEngine\Support\IntegrationPaths;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class RecoverIngestion
{
    public function __construct(private IntegrationPaths $paths) {}

    public function handle(?int $ingestionId = null, bool $retryFailed = false): int
    {
        $cutoff = now()->subMinutes((int) config('integration.stale_after_minutes'));
        $query = Ingestion::query();

        if ($ingestionId !== null) {
            $query->whereKey($ingestionId)->where(function (Builder $query) use ($retryFailed): void {
                $query->whereIn('status', [
                    IngestionStatus::DISCOVERED,
                    IngestionStatus::CLAIMED,
                    IngestionStatus::QUEUED,
                    IngestionStatus::PROCESSING,
                ]);

                if ($retryFailed) {
                    $query->orWhereIn('status', [IngestionStatus::FAILED, IngestionStatus::PARTIALLY_FAILED]);
                }
            });
        } else {
            $query->where(function (Builder $query) use ($cutoff, $retryFailed): void {
                $query->where(function (Builder $query) use ($cutoff): void {
                    $query->whereIn('status', [
                        IngestionStatus::DISCOVERED,
                        IngestionStatus::CLAIMED,
                        IngestionStatus::QUEUED,
                        IngestionStatus::PROCESSING,
                    ])->where('updated_at', '<=', $cutoff);
                });

                if ($retryFailed) {
                    $query->orWhereIn('status', [IngestionStatus::FAILED, IngestionStatus::PARTIALLY_FAILED]);
                }
            });
        }

        $recovered = 0;

        foreach ($query->orderBy('id')->get() as $ingestion) {
            if (in_array($ingestion->status, [IngestionStatus::FAILED, IngestionStatus::PARTIALLY_FAILED], true)) {
                if (! $this->paths->restoreFailed($ingestion)) {
                    $ingestion->update(['last_error' => 'Recovery could not find the quarantined file.']);

                    continue;
                }
            } elseif ($ingestion->status === IngestionStatus::DISCOVERED) {
                if (! $this->paths->processingFileExists($ingestion)) {
                    $incomingPath = $this->paths->incomingPath($ingestion->original_filename);

                    if (! is_file($incomingPath) || is_link($incomingPath)) {
                        $ingestion->update(['last_error' => 'Recovery could not find a safe incoming or processing file.']);

                        continue;
                    }

                    if ($ingestion->processing_filename === null) {
                        $ingestion->update([
                            'processing_filename' => $this->paths->newProcessingFilename($ingestion),
                        ]);
                    }

                    $this->paths->claim($ingestion->fresh(), $incomingPath);
                }

                $ingestion->update(['claimed_at' => now()]);
            } elseif (! $this->paths->processingFileExists($ingestion)) {
                $ingestion->update(['last_error' => 'Recovery could not find the claimed processing file.']);

                continue;
            }

            $ingestion->update([
                'status' => IngestionStatus::QUEUED,
                'queued_at' => now(),
                'last_error' => null,
                'completed_at' => null,
                'failed_at' => null,
            ]);
            ProcessIngestionJob::dispatch($ingestion->id)->onQueue((string) config('integration.queue'));
            $recovered++;

            Log::info('Integration ingestion recovered and queued.', ['ingestion_id' => $ingestion->id]);
        }

        return $recovered;
    }
}
