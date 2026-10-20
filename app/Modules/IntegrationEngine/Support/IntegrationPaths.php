<?php

namespace App\Modules\IntegrationEngine\Support;

use App\Modules\IntegrationEngine\Enums\IntegrationDataset;
use App\Modules\IntegrationEngine\Models\Ingestion;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;

class IntegrationPaths
{
    public function __construct(private Filesystem $files) {}

    public function ensureDirectories(): void
    {
        foreach (array_keys(config('integration.sources', [])) as $source) {
            foreach (IntegrationDataset::cases() as $dataset) {
                foreach (['in', 'processing', 'archive', 'failed'] as $area) {
                    $this->files->ensureDirectoryExists(
                        $this->directory($area, $source, $dataset),
                        (int) config('integration.directory_mode'),
                        true,
                    );
                }
            }
        }
    }

    public function incomingDirectory(string $source, IntegrationDataset $dataset): string
    {
        return $this->directory('in', $source, $dataset);
    }

    public function incomingPath(string $source, IntegrationDataset $dataset, string $filename): string
    {
        return $this->incomingDirectory($source, $dataset).DIRECTORY_SEPARATOR.basename($filename);
    }

    public function processingPath(Ingestion $ingestion): string
    {
        if ($ingestion->processing_filename === null) {
            throw new RuntimeException('The ingestion has not been assigned a processing filename.');
        }

        return $this->directory('processing', $ingestion->source, $ingestion->dataset)
            .DIRECTORY_SEPARATOR.$ingestion->processing_filename;
    }

    public function processingFileExists(Ingestion $ingestion): bool
    {
        return $ingestion->processing_filename !== null && is_file($this->processingPath($ingestion));
    }

    public function claim(Ingestion $ingestion, string $incomingPath): string
    {
        $processingFilename = $ingestion->processing_filename ?? $this->newProcessingFilename($ingestion);
        $processingPath = $this->directory('processing', $ingestion->source, $ingestion->dataset)
            .DIRECTORY_SEPARATOR.$processingFilename;

        if (! rename($incomingPath, $processingPath)) {
            throw new RuntimeException('The ready file could not be atomically moved to processing.');
        }

        chmod($processingPath, (int) config('integration.file_mode'));

        return $processingFilename;
    }

    public function newProcessingFilename(Ingestion $ingestion): string
    {
        return $ingestion->id.'-'.Str::uuid().'.csv';
    }

    public function finish(Ingestion $ingestion, bool $successful): string
    {
        $sourcePath = $this->processingPath($ingestion);
        $area = $successful ? 'archive' : 'failed';
        $destination = $this->directory($area, $ingestion->source, $ingestion->dataset)
            .DIRECTORY_SEPARATOR.now()->format('Y-m-d')
            .DIRECTORY_SEPARATOR.$ingestion->processing_filename;

        $this->files->ensureDirectoryExists(
            dirname($destination),
            (int) config('integration.directory_mode'),
            true,
        );

        if (! $successful) {
            $this->writeErrorManifest($ingestion, $destination.'.errors.json');
        }

        if (! rename($sourcePath, $destination)) {
            throw new RuntimeException("The processed file could not be moved to {$area}.");
        }

        return $destination;
    }

    public function archiveDuplicate(string $source, IntegrationDataset $dataset, string $path): void
    {
        $directory = $this->directory('archive', $source, $dataset)
            .DIRECTORY_SEPARATOR.'duplicates'.DIRECTORY_SEPARATOR.now()->format('Y-m-d');
        $this->files->ensureDirectoryExists(
            $directory,
            (int) config('integration.directory_mode'),
            true,
        );
        $destination = $directory.DIRECTORY_SEPARATOR.Str::uuid().'.csv';

        if (! rename($path, $destination)) {
            throw new RuntimeException('The duplicate file could not be archived.');
        }

        chmod($destination, (int) config('integration.file_mode'));
    }

    public function restoreFailed(Ingestion $ingestion): bool
    {
        if ($ingestion->processing_filename === null) {
            return false;
        }

        $pattern = $this->directory('failed', $ingestion->source, $ingestion->dataset)
            .DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.$ingestion->processing_filename;
        $matches = glob($pattern, GLOB_NOSORT);

        if ($matches === false || $matches === []) {
            return false;
        }

        $processingPath = $this->processingPath($ingestion);

        if (! rename($matches[0], $processingPath)) {
            throw new RuntimeException('The failed file could not be restored to processing.');
        }

        return true;
    }

    private function directory(string $area, string $source, IntegrationDataset $dataset): string
    {
        return rtrim((string) config('integration.root'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.$area
            .DIRECTORY_SEPARATOR.$source
            .DIRECTORY_SEPARATOR.$dataset->value;
    }

    private function writeErrorManifest(Ingestion $ingestion, string $destination): void
    {
        $temporaryPath = $destination.'.tmp-'.Str::uuid();
        $contents = json_encode([
            'ingestion_id' => $ingestion->id,
            'source' => $ingestion->source,
            'dataset' => $ingestion->dataset->value,
            'original_filename' => $ingestion->original_filename,
            'checksum' => $ingestion->checksum,
            'errors' => $ingestion->errors()
                ->orderBy('row_number')
                ->orderBy('id')
                ->get(['row_number', 'field', 'code', 'message'])
                ->toArray(),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        if (file_put_contents($temporaryPath, $contents) === false) {
            throw new RuntimeException('The quarantine error manifest could not be written.');
        }

        chmod($temporaryPath, (int) config('integration.file_mode'));

        if (! rename($temporaryPath, $destination)) {
            throw new RuntimeException('The quarantine error manifest could not be finalized.');
        }
    }
}
