<?php

namespace App\Filament\Resources\Accounts\RelationManagers;

use App\Filament\Resources\SalesOrders\SalesOrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class SalesOrdersRelationManager extends RelationManager
{
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
