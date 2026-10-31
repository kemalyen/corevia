<?php

namespace App\Filament\Resources\Accounts\Schemas;

use App\Enums\AccountProductAccessMode;
use App\Enums\AccountStatus;
use App\Enums\AccountType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Edit Account Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('account_number'),
                        TextInput::make('name')
                            ->required()->columnSpanFull(),

                        Select::make('account_status')
                            ->options(AccountStatus::class),

                        Select::make('account_type')
                            ->options(AccountType::class),

                    ])->columnSpanFull(),
                Section::make('Vendor catalogue access')
                    ->description('Overrides and grants are admin-managed. Category access follows the current hierarchy, so moving categories or products changes access immediately.')
                    ->schema([
                        Select::make('product_access_mode')
                            ->options(AccountProductAccessMode::class)
                            ->default(AccountProductAccessMode::RESTRICTED->value)
                            ->required()
                            ->live(),
                        Select::make('permittedProducts')
                            ->relationship('permittedProducts', 'name')
                            ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->sku} — {$record->name}")
                            ->searchable(['sku', 'name'])
                            ->multiple()
                            ->helperText('Individual grants are combined with category grants.'),
                        Select::make('permittedProductCategories')
                            ->relationship('permittedProductCategories', 'name')
                            ->searchable()
                            ->multiple()
                            ->helperText('A category grant includes products assigned to that category and all current descendants.'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
