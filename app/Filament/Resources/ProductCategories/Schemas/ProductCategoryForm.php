<?php

namespace App\Filament\Resources\ProductCategories\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Category hierarchy')
                    ->description('Product access follows the current hierarchy. Moving this category immediately changes descendant-based account access.')
                    ->schema([
                        TextInput::make('name')->required()->maxLength(255),
                        Select::make('parent_id')
                            ->relationship('parent', 'name')
                            ->searchable()
                            ->nullable(),
                        Select::make('products')
                            ->relationship('products', 'name')
                            ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->sku} — {$record->name}")
                            ->searchable(['sku', 'name'])
                            ->multiple()
                            ->helperText('Products can belong to more than one category.'),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
