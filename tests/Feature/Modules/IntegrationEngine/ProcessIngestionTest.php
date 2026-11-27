<?php

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Modules\IntegrationEngine\Actions\ProcessIngestion;
use App\Modules\IntegrationEngine\Enums\IngestionStatus;
use App\Modules\IntegrationEngine\Enums\IntegrationDataset;
use App\Modules\IntegrationEngine\Models\Ingestion;
use App\Modules\IntegrationEngine\Support\IntegrationPaths;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->integrationRoot = storage_path('framework/testing/integration-'.Str::uuid());
    config()->set('integration.root', $this->integrationRoot);
    app(IntegrationPaths::class)->ensureDirectories();
});

afterEach(function (): void {
    File::deleteDirectory($this->integrationRoot);
});

function claimedIngestion(IntegrationDataset $dataset, string $contents): Ingestion
{
    $ingestion = Ingestion::query()->create([
        'dataset' => $dataset,
        'original_filename' => config("integration.datasets.{$dataset->value}.prefix").'example.ready.csv',
        'checksum' => hash('sha256', $contents),
        'status' => IngestionStatus::CLAIMED,
        'file_size' => strlen($contents),
        'discovered_at' => now(),
        'claimed_at' => now(),
    ]);
    $filename = config("integration.datasets.{$dataset->value}.prefix").$ingestion->id.'-'.Str::uuid().'.csv';
    $ingestion->update(['processing_filename' => $filename]);
    $path = config('integration.root').'/processing/'.$filename;
    File::put($path, $contents);

    return $ingestion->fresh();
}

it('keeps valid account rows and records precise row errors for a partial file', function (): void {
    $ingestion = claimedIngestion(
        IntegrationDataset::ACCOUNTS,
        "account_external_id,account_type,name,status\nA-1,supplier,Acme,active\nA-2,invalid,Northwind,active\n",
    );

    app(ProcessIngestion::class)->handle($ingestion);

    $ingestion->refresh();
    expect($ingestion->status)->toBe(IngestionStatus::PARTIALLY_FAILED)
        ->and($ingestion->total_rows)->toBe(2)
        ->and($ingestion->valid_rows)->toBe(1)
        ->and($ingestion->failed_rows)->toBe(1)
        ->and($ingestion->errors()->first()->row_number)->toBe(3)
        ->and($ingestion->errors()->first()->field)->toBe('account_type');
    $this->assertDatabaseHas('accounts', [
        'external_id' => 'A-1',
        'account_number' => 'A-1',
        'account_type' => AccountType::SUPPLIER->value,
        'name' => 'Acme',
    ]);
    expect(File::glob($this->integrationRoot.'/failed/*/*.csv'))->toHaveCount(1)
        ->and(File::glob($this->integrationRoot.'/failed/*/*.errors.json'))->toHaveCount(1);
});

it('upserts changed products without creating duplicate business records', function (): void {
    $first = claimedIngestion(
        IntegrationDataset::PRODUCTS,
        "product_external_id,sku,name,description,price,currency\nP-1,SKU-1,Widget,First,12.00,GBP\n",
    );
    app(ProcessIngestion::class)->handle($first);
    $second = claimedIngestion(
        IntegrationDataset::PRODUCTS,
        "product_external_id,sku,name,description,price,currency\nP-1,SKU-1,Widget Plus,Changed,15.50,GBP\n",
    );

    app(ProcessIngestion::class)->handle($second);

    expect(Product::query()->count())->toBe(1)
        ->and(Product::query()->first()->name)->toBe('Widget Plus')
        ->and($second->fresh()->updated_records)->toBe(1);
});

it('commits valid non-adjacent orders atomically while rejecting an invalid order', function (): void {
    Account::query()->create([
        'name' => 'Acme',
        'account_number' => 'A-1',
        'account_type' => AccountType::VENDOR,
        'account_status' => AccountStatus::ACTIVE,
        'external_id' => 'A-1',
    ]);
    Product::query()->create([
        'name' => 'Widget',
        'description' => null,
        'price' => '10.00',
        'sku' => 'SKU-1',
        'currency' => 'GBP',
        'external_id' => 'P-1',
    ]);
    $header = 'order_external_id,order_number,order_date,account_external_id,status,currency,total_amount,line_external_id,line_number,product_external_id,quantity,unit_price,line_total,description,sku,unit_of_measure';
    $rows = [
        'O-1,SO-1,2026-10-20,A-1,pending,GBP,20.00,L-1,1,P-1,1.0000,10.00,10.00,First,SKU-1,EA',
        'O-2,SO-2,2026-10-20,A-1,pending,GBP,5.00,L-3,1,P-MISSING,1.0000,5.00,5.00,Bad,,EA',
        'O-1,SO-1,2026-10-20,A-1,pending,GBP,20.00,L-2,2,P-1,1.0000,10.00,10.00,Second,SKU-1,EA',
    ];
    $ingestion = claimedIngestion(IntegrationDataset::SALES_ORDERS, $header."\n".implode("\n", $rows)."\n");

    app(ProcessIngestion::class)->handle($ingestion);

    $ingestion->refresh();
    $salesOrder = SalesOrder::query()->where('external_id', 'O-1')->firstOrFail();
    expect($ingestion->status)->toBe(IngestionStatus::PARTIALLY_FAILED)
        ->and($ingestion->valid_rows)->toBe(2)
        ->and($ingestion->failed_rows)->toBe(1)
        ->and($salesOrder->lines()->count())->toBe(2)
        ->and(SalesOrder::query()->where('external_id', 'O-2')->exists())->toBeFalse()
        ->and($ingestion->errors()->where('code', 'unknown_product')->value('row_number'))->toBe(3);
});

it('rejects sales orders for supplier accounts', function (): void {
    Account::factory()->supplier()->create(['external_id' => 'A-1']);
    Product::factory()->create(['external_id' => 'P-1', 'sku' => 'SKU-1']);
    $header = 'order_external_id,order_number,order_date,account_external_id,status,currency,total_amount,line_external_id,line_number,product_external_id,quantity,unit_price,line_total,description,sku,unit_of_measure';
    $ingestion = claimedIngestion(
        IntegrationDataset::SALES_ORDERS,
        $header."\n".'O-1,SO-1,2026-10-20,A-1,pending,GBP,10.00,L-1,1,P-1,1.0000,10.00,10.00,Widget,SKU-1,EA'."\n",
    );

    app(ProcessIngestion::class)->handle($ingestion);

    expect(SalesOrder::query()->count())->toBe(0)
        ->and($ingestion->fresh()->status)->toBe(IngestionStatus::FAILED)
        ->and($ingestion->errors()->where('code', 'invalid_relation')->value('message'))
        ->toBe('Account [A-1] must be a vendor to place sales orders.');
});

it('rejects an entire order when repeated header fields are inconsistent', function (): void {
    Account::factory()->create(['external_id' => 'A-1']);
    Product::factory()->create(['external_id' => 'P-1', 'sku' => 'SKU-1']);
    $header = 'order_external_id,order_number,order_date,account_external_id,status,currency,total_amount,line_external_id,line_number,product_external_id,quantity,unit_price,line_total,description,sku,unit_of_measure';
    $contents = $header."\n"
        .'O-1,SO-1,2026-10-20,A-1,pending,GBP,10.00,L-1,1,P-1,1.0000,5.00,5.00,First,SKU-1,EA'."\n"
        .'O-1,SO-DIFFERENT,2026-10-20,A-1,pending,GBP,10.00,L-2,2,P-1,1.0000,5.00,5.00,Second,SKU-1,EA'."\n";
    $ingestion = claimedIngestion(IntegrationDataset::SALES_ORDERS, $contents);

    app(ProcessIngestion::class)->handle($ingestion);

    expect(SalesOrder::query()->count())->toBe(0)
        ->and($ingestion->fresh()->status)->toBe(IngestionStatus::FAILED)
        ->and($ingestion->errors()->where('code', 'inconsistent_order_header')->exists())->toBeTrue();
});

it('updates an existing order and supplied line on a changed re-delivery', function (): void {
    Account::factory()->create(['external_id' => 'A-1']);
    Product::factory()->create(['external_id' => 'P-1', 'sku' => 'SKU-1']);
    $header = 'order_external_id,order_number,order_date,account_external_id,status,currency,total_amount,line_external_id,line_number,product_external_id,quantity,unit_price,line_total,description,sku,unit_of_measure';
    $first = claimedIngestion(
        IntegrationDataset::SALES_ORDERS,
        $header."\n".'O-1,SO-1,2026-10-20,A-1,pending,GBP,5.00,L-1,1,P-1,1.0000,5.00,5.00,Original,SKU-1,EA'."\n",
    );
    app(ProcessIngestion::class)->handle($first);
    $second = claimedIngestion(
        IntegrationDataset::SALES_ORDERS,
        $header."\n".'O-1,SO-1,2026-10-20,A-1,processing,GBP,7.50,L-1,1,P-1,1.0000,7.50,7.50,Changed,SKU-1,EA'."\n",
    );

    app(ProcessIngestion::class)->handle($second);

    $salesOrder = SalesOrder::query()->firstOrFail();
    expect(SalesOrder::query()->count())->toBe(1)
        ->and($salesOrder->lines()->count())->toBe(1)
        ->and($salesOrder->total_amount)->toBe('7.50')
        ->and($salesOrder->lines()->first()->description)->toBe('Changed')
        ->and($second->fresh()->updated_records)->toBe(2);
});

it('rejects invalid headers before changing business data', function (): void {
    $ingestion = claimedIngestion(IntegrationDataset::ACCOUNTS, "wrong,account_type,name,status\nA-1,vendor,Acme,active\n");

    app(ProcessIngestion::class)->handle($ingestion);

    expect(Account::query()->count())->toBe(0)
        ->and($ingestion->fresh()->status)->toBe(IngestionStatus::FAILED)
        ->and($ingestion->errors()->where('code', 'invalid_file')->exists())->toBeTrue();
});

it('quarantines files that exceed the configured byte limit', function (): void {
    config()->set('integration.max_file_bytes', 10);
    $ingestion = claimedIngestion(
        IntegrationDataset::ACCOUNTS,
        "account_number,account_external_id,account_type,name,status\nA-1,vendor,Acme,active\n",
    );

    app(ProcessIngestion::class)->handle($ingestion);

    expect(Account::query()->count())->toBe(0)
        ->and($ingestion->fresh()->status)->toBe(IngestionStatus::FAILED)
        ->and($ingestion->errors()->where('code', 'invalid_file')->exists())->toBeTrue();
});

it('stops streaming after the configured row limit', function (): void {
    config()->set('integration.max_rows', 1);
    $ingestion = claimedIngestion(
        IntegrationDataset::ACCOUNTS,
        "account_number,account_external_id,account_type,name,status\nA-1,vendor,Acme,active\nA-2,supplier,Northwind,active\n",
    );

    app(ProcessIngestion::class)->handle($ingestion);

    expect(Account::query()->count())->toBe(1)
        ->and($ingestion->fresh()->status)->toBe(IngestionStatus::PARTIALLY_FAILED)
        ->and($ingestion->valid_rows)->toBe(1)
        ->and($ingestion->errors()->where('code', 'invalid_file')->exists())->toBeTrue();
});

it('rejects a non UTF-8 row before writing it', function (): void {
    $ingestion = claimedIngestion(
        IntegrationDataset::ACCOUNTS,
        "account_external_id,account_type,name,status\nA-1,vendor,\xB1,active\n",
    );

    app(ProcessIngestion::class)->handle($ingestion);

    expect(Account::query()->count())->toBe(0)
        ->and($ingestion->fresh()->status)->toBe(IngestionStatus::FAILED)
        ->and($ingestion->errors()->where('code', 'invalid_file')->exists())->toBeTrue();
});
