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
    $this->integrationRoot = storage_path('framework/testing/integration-'.Str::uuid());
    config()->set('integration.root', $this->integrationRoot);
});

afterEach(function (): void {
    File::deleteDirectory($this->integrationRoot);
});

it('claims ready files and queues datasets in dependency order', function (): void {
    Queue::fake([ProcessIngestionJob::class]);
    $paths = app(IntegrationPaths::class);
    $paths->ensureDirectories();
    $customerDirectory = $paths->incomingDirectory('erp', IntegrationDataset::CUSTOMERS);
    $productDirectory = $paths->incomingDirectory('erp', IntegrationDataset::PRODUCTS);
    File::put($customerDirectory.'/customers.tmp', "customer_external_id,name,status\nC-1,Acme,active\n");
    rename($customerDirectory.'/customers.tmp', $customerDirectory.'/customers.ready.csv');
    File::put($productDirectory.'/products.ready.csv', "product_external_id,sku,name,description,price,currency\nP-1,SKU-1,Widget,,12.00,GBP\n");

    $this->artisan('integration:discover')->assertSuccessful();

    expect(Ingestion::query()->count())->toBe(1)
        ->and(Ingestion::query()->first()->dataset)->toBe(IntegrationDataset::CUSTOMERS)
        ->and(Ingestion::query()->first()->status)->toBe(IngestionStatus::QUEUED)
        ->and(File::exists($productDirectory.'/products.ready.csv'))->toBeTrue();
    Queue::assertPushed(ProcessIngestionJob::class, 1);
});

it('ignores temporary files and archives duplicate deliveries without another job', function (): void {
    Queue::fake([ProcessIngestionJob::class]);
    $paths = app(IntegrationPaths::class);
    $paths->ensureDirectories();
    $directory = $paths->incomingDirectory('erp', IntegrationDataset::CUSTOMERS);
    $contents = "customer_external_id,name,status\nC-1,Acme,active\n";
    File::put($directory.'/customers.tmp.csv', $contents);
    File::put($directory.'/customers.ready.csv', $contents);

    $this->artisan('integration:discover')->assertSuccessful();
    $ingestion = Ingestion::query()->firstOrFail();
    $ingestion->update(['status' => IngestionStatus::COMPLETED]);
    File::put($directory.'/customers-copy.ready.csv', $contents);

    $this->artisan('integration:discover')->assertSuccessful();

    expect(Ingestion::query()->count())->toBe(1)
        ->and(File::exists($directory.'/customers.tmp.csv'))->toBeTrue()
        ->and(File::allFiles($this->integrationRoot.'/archive/erp/customers/duplicates'))->toHaveCount(1);
    Queue::assertPushed(ProcessIngestionJob::class, 1);
});

it('ignores symbolic links even when their names use the ready suffix', function (): void {
    Queue::fake([ProcessIngestionJob::class]);
    $paths = app(IntegrationPaths::class);
    $paths->ensureDirectories();
    $directory = $paths->incomingDirectory('erp', IntegrationDataset::CUSTOMERS);
    $target = $this->integrationRoot.'/outside.csv';
    File::put($target, "customer_external_id,name,status\nC-1,Acme,active\n");
    symlink($target, $directory.'/linked.ready.csv');

    $this->artisan('integration:discover')->assertSuccessful();

    expect(Ingestion::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});
