<?php

use App\Filament\Widgets\DailyOrdersChart;
use App\Filament\Widgets\RecentIngestionsTable;
use App\Filament\Widgets\RecentlyUpdatedProductsTable;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

it('renders dashboard widgets for authorized management users', function () {
    foreach (['view-any-sales-order', 'view-any-ingestion', 'view-any-product'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo(['view-any-sales-order', 'view-any-ingestion', 'view-any-product']);

    $this->actingAs($user);

    Livewire::test(DailyOrdersChart::class)->assertOk();
    Livewire::test(RecentIngestionsTable::class)->assertOk();
    Livewire::test(RecentlyUpdatedProductsTable::class)->assertOk();
});
