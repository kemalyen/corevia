<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentlyUpdatedProductsTable extends TableWidget
{
    protected static ?string $heading = 'Recently Updated Products';

    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', Product::class) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->latest('updated_at')
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU'),
                TextColumn::make('name')
                    ->limit(35),
                TextColumn::make('price')
                    ->money(fn (Product $record): string => $record->currency ?? 'GBP'),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since(),
            ])
            ->paginated(false);
    }
}
