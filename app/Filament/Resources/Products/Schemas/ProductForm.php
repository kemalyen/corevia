<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\Currency;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
                            ->numeric()
                            ->minValue(0)
                            ->step(0.0001)
                            ->helperText('Normal feed price. Account-specific prices are managed on the account.'),
                        Select::make('currency')
                            ->options(Currency::class)
                            ->enum(Currency::class)
                            ->default(Currency::GBP->value)
                            ->required()
                            ->searchable(),
                        TextInput::make('unit_of_measure')
                            ->maxLength(32),
                        TextInput::make('sku')
                            ->label('SKU')
                            ->required(),
                        Select::make('categories')
                            ->relationship('categories', 'name')
                            ->searchable()
                            ->multiple()
                            ->columnSpanFull(),
                    ])->columnSpanFull(),
            ]);
    }
}
