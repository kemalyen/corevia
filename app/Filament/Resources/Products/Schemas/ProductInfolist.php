<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->columnSpanFull(),
                        TextEntry::make('description')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('price')
                            ->numeric(decimalPlaces: 4)
                            ->placeholder('Unavailable'),
                        TextEntry::make('currency')->placeholder('-'),
                        TextEntry::make('unit_of_measure')->placeholder('Not supplied'),
                        TextEntry::make('sku')
                            ->label('SKU'),
                        TextEntry::make('categories.name')->badge()->columnSpanFull(),
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->placeholder('-'),
                    ])->columnSpanFull(),
            ]);
    }
}
