<?php

namespace App\Filament\Resources\Accounts\Schemas;

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
                        TextInput::make('name')
                            ->required()->columnSpanFull(),

                        Select::make('account_status')
                            ->options(AccountStatus::class),

                        Select::make('account_type')
                            ->options(AccountType::class),

                    ])->columnSpanFull(),
            ]);
    }
}
