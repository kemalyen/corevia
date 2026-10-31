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
    $incomingDirectory = $paths->incomingDirectory();
    File::put($incomingDirectory.'/accounts-example.tmp', "account_number,account_external_id,account_type,name,status\nA-1,vendor,Acme,active\n");
    rename($incomingDirectory.'/accounts-example.tmp', $incomingDirectory.'/accounts-example.ready.csv');
    File::put($incomingDirectory.'/products-example.ready.csv', "product_external_id,sku,name,description,price,currency\nP-1,SKU-1,Widget,,12.00,GBP\n");

    $this->artisan('integration:discover')->assertSuccessful();

    expect(Ingestion::query()->count())->toBe(1)
        ->and(Ingestion::query()->first()->dataset)->toBe(IntegrationDataset::ACCOUNTS)
        ->and(Ingestion::query()->first()->status)->toBe(IngestionStatus::QUEUED)
        ->and(Ingestion::query()->first()->processing_filename)->toStartWith('accounts-')
        ->and(File::exists($incomingDirectory.'/products-example.ready.csv'))->toBeTrue();
    Queue::assertPushed(ProcessIngestionJob::class, 1);
});

it('ignores temporary files and archives duplicate deliveries without another job', function (): void {
    Queue::fake([ProcessIngestionJob::class]);
    $paths = app(IntegrationPaths::class);
    $paths->ensureDirectories();
    $directory = $paths->incomingDirectory();
    $contents = "account_external_id,account_type,name,status\nA-1,vendor,Acme,active\n";
    File::put($directory.'/accounts-example.tmp.csv', $contents);
    File::put($directory.'/accounts-example.ready.csv', $contents);

    $this->artisan('integration:discover')->assertSuccessful();
    $ingestion = Ingestion::query()->firstOrFail();
    $ingestion->update(['status' => IngestionStatus::COMPLETED]);
    File::put($directory.'/accounts-copy.ready.csv', $contents);

    $this->artisan('integration:discover')->assertSuccessful();

    expect(Ingestion::query()->count())->toBe(1)
        ->and(File::exists($directory.'/accounts-example.tmp.csv'))->toBeTrue()
        ->and(File::allFiles($this->integrationRoot.'/archive/duplicates'))->toHaveCount(1);
    Queue::assertPushed(ProcessIngestionJob::class, 1);
});

it('ignores symbolic links and unknown dataset prefixes', function (): void {
    Queue::fake([ProcessIngestionJob::class]);
    $paths = app(IntegrationPaths::class);
    $paths->ensureDirectories();
    $directory = $paths->incomingDirectory();
    $target = $this->integrationRoot.'/outside.csv';
    File::put($target, "account_external_id,account_type,name,status\nA-1,vendor,Acme,active\n");
    symlink($target, $directory.'/accounts-linked.ready.csv');
    File::put($directory.'/unknown-example.ready.csv', "unknown\nvalue\n");

    $this->artisan('integration:discover')->assertSuccessful();

    expect(Ingestion::query()->count())->toBe(0)
        ->and(File::exists($directory.'/unknown-example.ready.csv'))->toBeTrue();
    Queue::assertNothingPushed();
});

it('queues catalogue relationship datasets in dependency order', function (): void {
    Queue::fake([ProcessIngestionJob::class]);
    $paths = app(IntegrationPaths::class);
    $paths->ensureDirectories();
    $directory = $paths->incomingDirectory();
    File::put(
        $directory.'/account-product-prices-example.ready.csv',
        "account_external_id,product_external_id,amount,currency,unit_of_measure\nA-1,P-1,8.5000,GBP,EA\n",
    );
    File::put(
        $directory.'/product-category-mappings-example.ready.csv',
        "product_external_id,category_name\nP-1,Widgets\n",
    );
    File::put(
        $directory.'/sales-orders-example.ready.csv',
        "order_external_id\nO-1\n",
    );

    $this->artisan('integration:discover')->assertSuccessful();

    expect(Ingestion::query()->sole()->dataset)->toBe(IntegrationDataset::ACCOUNT_PRODUCT_PRICES)
        ->and(File::exists($directory.'/product-category-mappings-example.ready.csv'))->toBeTrue()
        ->and(File::exists($directory.'/sales-orders-example.ready.csv'))->toBeTrue();
    Queue::assertPushed(ProcessIngestionJob::class, 1);
});
