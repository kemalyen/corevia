<?php

use App\Modules\IntegrationEngine\Enums\IngestionStatus;
use App\Modules\IntegrationEngine\Enums\IntegrationDataset;
use App\Modules\IntegrationEngine\Jobs\ProcessIngestionJob;
use App\Modules\IntegrationEngine\Models\Ingestion;
use App\Modules\IntegrationEngine\Support\IntegrationPaths;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->integrationRoot = storage_path('framework/testing/integration-' . Str::uuid());
    config()->set('integration.root', $this->integrationRoot);
    app(IntegrationPaths::class)->ensureDirectories();
});

afterEach(function (): void {
    File::deleteDirectory($this->integrationRoot);
});

it('requeues a stranded claimed ingestion when explicitly selected', function (): void {
    Queue::fake([ProcessIngestionJob::class]);
    $filename = 'accounts-' . Str::uuid() . '.csv';
    $contents = "account_number,account_external_id,account_type,name,status\nA-1,vendor,Acme,active\n";
    $ingestion = Ingestion::query()->create([
        'dataset' => IntegrationDataset::ACCOUNTS,
        'original_filename' => 'accounts-example.ready.csv',
        'processing_filename' => $filename,
        'checksum' => hash('sha256', $contents),
        'status' => IngestionStatus::CLAIMED,
        'file_size' => strlen($contents),
        'discovered_at' => now(),
        'claimed_at' => now(),
    ]);
    File::put($this->integrationRoot . '/processing/' . $filename, $contents);

    $this->artisan("integration:recover --id={$ingestion->id}")->assertSuccessful();

    expect($ingestion->fresh()->status)->toBe(IngestionStatus::QUEUED);
    Queue::assertPushed(ProcessIngestionJob::class, fn(ProcessIngestionJob $job): bool => $job->ingestionId === $ingestion->id);
});

it('requires an explicit flag before retrying a quarantined ingestion', function (): void {
    Queue::fake([ProcessIngestionJob::class]);
    $filename = 'accounts-' . Str::uuid() . '.csv';
    $contents = "account_number,account_external_id,account_type,name,status\nA-1,vendor,Acme,active\n";
    $ingestion = Ingestion::query()->create([
        'dataset' => IntegrationDataset::ACCOUNTS,
        'original_filename' => 'accounts-example.ready.csv',
        'processing_filename' => $filename,
        'checksum' => hash('sha256', $contents),
        'status' => IngestionStatus::FAILED,
        'file_size' => strlen($contents),
        'discovered_at' => now(),
        'failed_at' => now(),
    ]);
    $failedDirectory = $this->integrationRoot . '/failed/2026-10-20';
    File::ensureDirectoryExists($failedDirectory);
    File::put($failedDirectory . '/' . $filename, $contents);

    $this->artisan("integration:recover --id={$ingestion->id}")->assertSuccessful();
    Queue::assertNothingPushed();

    $this->artisan("integration:recover --id={$ingestion->id} --retry-failed")->assertSuccessful();

    expect($ingestion->fresh()->status)->toBe(IngestionStatus::QUEUED)
        ->and(File::exists($this->integrationRoot . '/processing/' . $filename))->toBeTrue();
    Queue::assertPushed(ProcessIngestionJob::class, 1);
});

it('recovers a crash after the atomic move but before the claim status update', function (): void {
    Queue::fake([ProcessIngestionJob::class]);
    $filename = 'accounts-' . Str::uuid() . '.csv';
    $contents = "account_number,account_external_id,account_type,name,status\nA-1,vendor,Acme,active\n";
    $ingestion = Ingestion::query()->create([
        'dataset' => IntegrationDataset::ACCOUNTS,
        'original_filename' => 'accounts-example.ready.csv',
        'processing_filename' => $filename,
        'checksum' => hash('sha256', $contents),
        'status' => IngestionStatus::DISCOVERED,
        'file_size' => strlen($contents),
        'discovered_at' => now(),
    ]);
    File::put($this->integrationRoot . '/processing/' . $filename, $contents);

    $this->artisan("integration:recover --id={$ingestion->id}")->assertSuccessful();

    expect($ingestion->fresh()->status)->toBe(IngestionStatus::QUEUED);
    Queue::assertPushed(ProcessIngestionJob::class, 1);
});
