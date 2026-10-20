<?php

namespace App\Modules\IntegrationEngine\Actions;

use App\Modules\IntegrationEngine\Enums\IngestionStatus;
use App\Modules\IntegrationEngine\Enums\IntegrationDataset;
use App\Modules\IntegrationEngine\Jobs\ProcessIngestionJob;
use App\Modules\IntegrationEngine\Models\Ingestion;
use App\Modules\IntegrationEngine\Support\IntegrationPaths;
use DirectoryIterator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DiscoverReadyFiles
{
    public function __construct(private IntegrationPaths $paths) {}

    public function handle(): int
    {
        $this->paths->ensureDirectories();
        $dispatched = 0;

        foreach (array_keys(config('integration.sources', [])) as $source) {
            foreach (IntegrationDataset::cases() as $dataset) {
                $hadReadyFiles = false;

                foreach (new DirectoryIterator($this->paths->incomingDirectory($source, $dataset)) as $file) {
                    if ($file->isDot() || ! str_ends_with($file->getFilename(), (string) config('integration.ready_suffix'))) {
                        continue;
                    }

                    $hadReadyFiles = true;

                    if ($file->isLink() || ! $file->isFile()) {
                        Log::warning('Integration discovery ignored an unsafe directory entry.', [
                            'source' => $source,
                            'dataset' => $dataset->value,
                            'filename' => $file->getFilename(),
                        ]);

                        continue;
                    }

                    $dispatched += $this->claim($source, $dataset, $file->getPathname());
                }

                $activeExists = Ingestion::query()
                    ->where('source', $source)
                    ->where('dataset', $dataset)
                    ->whereIn('status', [
                        IngestionStatus::DISCOVERED,
                        IngestionStatus::CLAIMED,
                        IngestionStatus::QUEUED,
                        IngestionStatus::PROCESSING,
                    ])
                    ->exists();

                if ($hadReadyFiles || $activeExists) {
                    break;
                }
            }
        }

        return $dispatched;
    }

    private function claim(string $source, IntegrationDataset $dataset, string $incomingPath): int
    {
        $checksum = hash_file('sha256', $incomingPath);

        if ($checksum === false) {
            throw new RuntimeException('A checksum could not be calculated for a ready integration file.');
        }

        $lockKey = 'integration:claim:'.hash('sha256', $source.'|'.$dataset->value.'|'.$checksum);

        return (int) (Cache::lock($lockKey, 30)->get(
            fn (): int => $this->claimWithChecksum($source, $dataset, $incomingPath, $checksum),
        ) ?? 0);
    }

    private function claimWithChecksum(
        string $source,
        IntegrationDataset $dataset,
        string $incomingPath,
        string $checksum,
    ): int {
        try {
            $ingestion = Ingestion::query()->firstOrCreate(
                ['source' => $source, 'dataset' => $dataset, 'checksum' => $checksum],
                [
                    'original_filename' => basename($incomingPath),
                    'status' => IngestionStatus::DISCOVERED,
                    'file_size' => filesize($incomingPath),
                    'discovered_at' => now(),
                ],
            );
        } catch (UniqueConstraintViolationException) {
            $ingestion = Ingestion::query()
                ->where('source', $source)
                ->where('dataset', $dataset)
                ->where('checksum', $checksum)
                ->firstOrFail();
        }

        if (! $ingestion->wasRecentlyCreated && $ingestion->status !== IngestionStatus::DISCOVERED) {
            $this->paths->archiveDuplicate($source, $dataset, $incomingPath);
            Log::info('Duplicate integration delivery archived without reprocessing.', [
                'ingestion_id' => $ingestion->id,
            ]);

            return 0;
        }

        try {
            if ($ingestion->processing_filename === null) {
                $ingestion->update([
                    'processing_filename' => $this->paths->newProcessingFilename($ingestion),
                ]);
            }

            $processingFilename = $this->paths->claim($ingestion, $incomingPath);
            $ingestion->update([
                'processing_filename' => $processingFilename,
                'status' => IngestionStatus::CLAIMED,
                'claimed_at' => now(),
                'last_error' => null,
            ]);
        } catch (RuntimeException $exception) {
            $ingestion->update(['last_error' => $exception->getMessage()]);
            Log::error('Integration file claim failed.', [
                'ingestion_id' => $ingestion->id,
                'exception' => $exception,
            ]);

            return 0;
        }

        try {
            $ingestion->update(['status' => IngestionStatus::QUEUED, 'queued_at' => now()]);
            ProcessIngestionJob::dispatch($ingestion->id)->onQueue((string) config('integration.queue'));
        } catch (\Throwable $exception) {
            $ingestion->update([
                'status' => IngestionStatus::CLAIMED,
                'last_error' => 'Queue dispatch failed: '.$exception->getMessage(),
            ]);
            report($exception);

            return 0;
        }

        return 1;
    }
}
