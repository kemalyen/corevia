<?php

namespace App\Modules\IntegrationEngine\Jobs;

use App\Modules\IntegrationEngine\Actions\ProcessIngestion;
use App\Modules\IntegrationEngine\Enums\IngestionStatus;
use App\Modules\IntegrationEngine\Models\Ingestion;
use App\Modules\IntegrationEngine\Support\IntegrationPaths;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessIngestionJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public int $uniqueFor = 1800;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 180];

    public function __construct(public int $ingestionId) {}

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('ingestion:'.$this->ingestionId))->expireAfter(660)];
    }

    public function uniqueId(): string
    {
        return (string) $this->ingestionId;
    }

    public function handle(ProcessIngestion $processor): void
    {
        $processor->handle(Ingestion::query()->findOrFail($this->ingestionId));
    }

    public function failed(?Throwable $exception): void
    {
        $ingestion = Ingestion::query()->find($this->ingestionId);

        if ($ingestion === null) {
            return;
        }

        if (! $ingestion->status->isTerminal()) {
            $message = $exception?->getMessage() ?? 'The ingestion job exhausted its retry attempts.';
            $ingestion->errors()->create(['code' => 'processing_failure', 'message' => $message]);
            $ingestion->update([
                'status' => IngestionStatus::FAILED,
                'last_error' => $message,
                'failed_at' => now(),
                'completed_at' => now(),
            ]);
        }

        try {
            $paths = app(IntegrationPaths::class);

            if ($paths->processingFileExists($ingestion)) {
                $paths->finish($ingestion, $ingestion->status === IngestionStatus::COMPLETED);
            }
        } catch (Throwable $moveException) {
            Log::error('Terminal ingestion file could not be moved.', [
                'ingestion_id' => $ingestion->id,
                'exception' => $moveException,
            ]);
        }
    }
}
