<?php

namespace App\Filament\Resources\SalesOrders\Pages;

use App\Filament\Resources\SalesOrders\SalesOrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Table;

class ManageSalesOrderItems extends ManageRelatedRecords
{
    protected static string $resource = SalesOrderResource::class;

    protected static string $relationship = 'sales_orders';

    protected static ?string $relatedResource = SalesOrderResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
