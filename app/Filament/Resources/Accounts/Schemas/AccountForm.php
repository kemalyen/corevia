<?php

namespace App\Filament\Resources\Accounts\Schemas;

use App\Enums\AccountType;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;

class AccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Edit Account Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()->columnSpanFull(),

                        Select::make('account_status')
                            ->options(AccountType::class),

                        Select::make('account_type')
                            ->options(AccountType::class)

                    ])->columnSpanFull()
            ]);
    }
}
