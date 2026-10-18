<?php

namespace App\Filament\Resources\Accounts\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('account_type')
                    ->required()
                    ->default('vendor'),
                TextInput::make('account_status')
                    ->required()
                    ->default('active'),
            ]);
    }
}
