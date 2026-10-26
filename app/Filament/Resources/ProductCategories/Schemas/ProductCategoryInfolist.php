<?php

namespace App\Filament\Resources\ProductCategories\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductCategoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Category hierarchy')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('parent.name')->placeholder('Root category'),
                        TextEntry::make('products.name')->badge(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
