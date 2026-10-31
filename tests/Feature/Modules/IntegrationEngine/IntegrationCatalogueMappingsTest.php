<?php

use App\Models\Account;
use App\Models\AccountProductPrice;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Modules\IntegrationEngine\Actions\ProcessIngestion;
use App\Modules\IntegrationEngine\Enums\IngestionStatus;
use App\Modules\IntegrationEngine\Enums\IntegrationDataset;
use App\Modules\IntegrationEngine\Models\Ingestion;
use App\Modules\IntegrationEngine\Support\IntegrationPaths;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->integrationRoot = storage_path('framework/testing/catalogue-integration-'.Str::uuid());
    config()->set('integration.root', $this->integrationRoot);
    app(IntegrationPaths::class)->ensureDirectories();
});

afterEach(function (): void {
    File::deleteDirectory($this->integrationRoot);
});

function claimedCatalogueIngestion(IntegrationDataset $dataset, string $contents): Ingestion
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
    File::put(config('integration.root').'/processing/'.$filename, $contents);

    return $ingestion->fresh();
}

it('creates and updates account product price overrides idempotently', function (): void {
    $account = Account::factory()->create(['external_id' => 'A-1']);
    $product = Product::factory()->create(['external_id' => 'P-1']);
    $header = 'account_external_id,product_external_id,amount,currency,unit_of_measure';
    $first = claimedCatalogueIngestion(
        IntegrationDataset::ACCOUNT_PRODUCT_PRICES,
        $header."\nA-1,P-1,8.5000,GBP,EA\n",
    );

    app(ProcessIngestion::class)->handle($first);

    $second = claimedCatalogueIngestion(
        IntegrationDataset::ACCOUNT_PRODUCT_PRICES,
        $header."\nA-1,P-1,9.2500,GBP,EA\n",
    );
    app(ProcessIngestion::class)->handle($second);

    expect(AccountProductPrice::query()->count())->toBe(1)
        ->and(AccountProductPrice::query()->first()->account_id)->toBe($account->getKey())
        ->and(AccountProductPrice::query()->first()->product_id)->toBe($product->getKey())
        ->and(AccountProductPrice::query()->first()->amount)->toBe('9.2500')
        ->and($first->fresh()->created_records)->toBe(1)
        ->and($second->fresh()->updated_records)->toBe(1);
});

it('maps products to existing categories without importing categories', function (): void {
    $product = Product::factory()->create(['external_id' => 'P-1']);
    $category = ProductCategory::factory()->create(['name' => 'Widgets']);
    $contents = "product_external_id,category_name\nP-1,Widgets\n";
    $ingestion = claimedCatalogueIngestion(IntegrationDataset::PRODUCT_CATEGORY_MAPPINGS, $contents);

    app(ProcessIngestion::class)->handle($ingestion);

    expect($product->fresh()->categories()->whereKey($category->getKey())->exists())->toBeTrue()
        ->and(ProductCategory::query()->count())->toBe(1)
        ->and($ingestion->fresh()->created_records)->toBe(1);

    $repeat = claimedCatalogueIngestion(IntegrationDataset::PRODUCT_CATEGORY_MAPPINGS, $contents."\n");
    app(ProcessIngestion::class)->handle($repeat);

    expect($product->fresh()->categories()->count())->toBe(1)
        ->and($repeat->fresh()->updated_records)->toBe(1);
});

it('records unknown relationships without creating prices or categories', function (): void {
    Product::factory()->create(['external_id' => 'P-1']);
    $priceIngestion = claimedCatalogueIngestion(
        IntegrationDataset::ACCOUNT_PRODUCT_PRICES,
        "account_external_id,product_external_id,amount,currency,unit_of_measure\nMISSING,P-1,8.5000,GBP,EA\n",
    );
    $categoryIngestion = claimedCatalogueIngestion(
        IntegrationDataset::PRODUCT_CATEGORY_MAPPINGS,
        "product_external_id,category_name\nP-1,Missing category\n",
    );

    app(ProcessIngestion::class)->handle($priceIngestion);
    app(ProcessIngestion::class)->handle($categoryIngestion);

    expect(AccountProductPrice::query()->count())->toBe(0)
        ->and(ProductCategory::query()->count())->toBe(0)
        ->and($priceIngestion->fresh()->status)->toBe(IngestionStatus::FAILED)
        ->and($categoryIngestion->fresh()->status)->toBe(IngestionStatus::FAILED)
        ->and($priceIngestion->errors()->where('code', 'invalid_relation')->exists())->toBeTrue()
        ->and($categoryIngestion->errors()->where('code', 'invalid_relation')->exists())->toBeTrue();
});

it('rejects product prices for supplier accounts', function (): void {
    Account::factory()->supplier()->create(['external_id' => 'A-1']);
    Product::factory()->create(['external_id' => 'P-1']);
    $ingestion = claimedCatalogueIngestion(
        IntegrationDataset::ACCOUNT_PRODUCT_PRICES,
        "account_external_id,product_external_id,amount,currency,unit_of_measure\nA-1,P-1,8.5000,GBP,EA\n",
    );

    app(ProcessIngestion::class)->handle($ingestion);

    expect(AccountProductPrice::query()->count())->toBe(0)
        ->and($ingestion->fresh()->status)->toBe(IngestionStatus::FAILED)
        ->and($ingestion->errors()->where('code', 'invalid_relation')->value('message'))
        ->toBe('Account [A-1] must be a vendor to have product prices.');
});

it('rejects ambiguous category names instead of guessing a mapping', function (): void {
    $product = Product::factory()->create(['external_id' => 'P-1']);
    ProductCategory::factory()->count(2)->create(['name' => 'Widgets']);
    $ingestion = claimedCatalogueIngestion(
        IntegrationDataset::PRODUCT_CATEGORY_MAPPINGS,
        "product_external_id,category_name\nP-1,Widgets\n",
    );

    app(ProcessIngestion::class)->handle($ingestion);

    expect($product->fresh()->categories()->count())->toBe(0)
        ->and($ingestion->fresh()->status)->toBe(IngestionStatus::FAILED)
        ->and($ingestion->errors()->where('code', 'invalid_relation')->value('message'))
        ->toBe('Product category [Widgets] is ambiguous.');
});
