<?php

use App\Filament\Resources\Ingestions\IngestionResource;
use App\Filament\Resources\Ingestions\Pages\ListIngestions;
use App\Filament\Resources\Ingestions\Pages\ViewIngestion;
use App\Filament\Resources\Ingestions\RelationManagers\ErrorsRelationManager;
use App\Models\User;
use App\Modules\IntegrationEngine\Enums\IngestionStatus;
use App\Modules\IntegrationEngine\Enums\IntegrationDataset;
use App\Modules\IntegrationEngine\Jobs\ProcessIngestionJob;
use App\Modules\IntegrationEngine\Models\Ingestion;
use App\Modules\IntegrationEngine\Support\IntegrationPaths;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->integrationRoot = storage_path('framework/testing/integration-runs-'.Str::uuid());
    config()->set('integration.root', $this->integrationRoot);
    app(IntegrationPaths::class)->ensureDirectories();
});

afterEach(function (): void {
    File::deleteDirectory($this->integrationRoot);
});

function integrationRun(IngestionStatus $status = IngestionStatus::FAILED): Ingestion
{
    return Ingestion::query()->create([
        'dataset' => IntegrationDataset::PRODUCTS,
        'original_filename' => 'products-example.ready.csv',
        'processing_filename' => 'products-1-example.csv',
        'checksum' => str_repeat('a', 64),
        'status' => $status,
        'file_size' => 128,
        'attempts' => 1,
        'total_rows' => 2,
        'valid_rows' => 1,
        'failed_rows' => 1,
        'created_records' => 1,
        'updated_records' => 0,
        'last_error' => 'One row failed validation.',
        'discovered_at' => now()->subMinute(),
        'failed_at' => now(),
    ]);
}

it('shows integration runs and their row-level errors to authorised administrators', function (): void {
    $administrator = User::factory()->create();
    $administrator->assignRole('master-data');
    $ingestion = integrationRun();
    $error = $ingestion->errors()->create([
        'row_number' => 2,
        'field' => 'currency',
        'code' => 'validation',
        'message' => 'The selected currency is invalid.',
    ]);
    $this->actingAs($administrator);

    Livewire::test(ListIngestions::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$ingestion]);

    $this->get(IngestionResource::getUrl('view', ['record' => $ingestion]))
        ->assertOk()
        ->assertSee('One row failed validation.');

    Livewire::test(ErrorsRelationManager::class, [
        'ownerRecord' => $ingestion,
        'pageClass' => ViewIngestion::class,
    ])->assertCanSeeTableRecords([$error]);
});

it('forbids users without integration operations permission', function (): void {
    $accountManager = User::factory()->create();
    $accountManager->assignRole('account-manager');
    $ingestion = integrationRun();

    $this->actingAs($accountManager)
        ->get(IngestionResource::getUrl('index'))
        ->assertForbidden();
    $this->get(IngestionResource::getUrl('view', ['record' => $ingestion]))
        ->assertForbidden();
});

it('restores and queues a failed integration from the retry action', function (): void {
    Bus::fake();
    $administrator = User::factory()->create();
    $administrator->assignRole('master-data');
    $ingestion = integrationRun();
    $failedDirectory = $this->integrationRoot.'/failed/'.now()->format('Y-m-d');
    File::ensureDirectoryExists($failedDirectory);
    File::put($failedDirectory.'/'.$ingestion->processing_filename, "product_external_id,sku,name,description,price,currency\n");
    $this->actingAs($administrator);

    Livewire::test(ViewIngestion::class, ['record' => $ingestion->getRouteKey()])
        ->callAction('retry')
        ->assertNotified('Integration queued for retry');

    expect($ingestion->fresh()->status)->toBe(IngestionStatus::QUEUED)
        ->and(File::exists($this->integrationRoot.'/processing/'.$ingestion->processing_filename))->toBeTrue();
    Bus::assertDispatched(ProcessIngestionJob::class);
});

it('does not offer retry for completed integrations', function (): void {
    $administrator = User::factory()->create();
    $administrator->assignRole('master-data');
    $ingestion = integrationRun(IngestionStatus::COMPLETED);
    $this->actingAs($administrator);

    Livewire::test(ViewIngestion::class, ['record' => $ingestion->getRouteKey()])
        ->assertActionHidden('retry');
});
