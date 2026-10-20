<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Edit Product Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->columnSpanFull()
                            ->required(),
                        Textarea::make('description')
                            ->columnSpanFull(),
                        TextInput::make('price')
                            ->required()
                            ->numeric()
                            ->prefix('$'),
                        TextInput::make('sku')
                            ->label('SKU')
                            ->required(),
                    ])->columnSpanFull()
            ]);
    }
}
