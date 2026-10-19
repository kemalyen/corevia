<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required(),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required(),

                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->dehydrateStateUsing(fn(string $state): string => Hash::make($state))
                            ->dehydrated(fn(?string $state): bool => filled($state))
                            ->required(fn(string $operation): bool => $operation === 'create'),

                        TextInput::make('passwordConfirmation')
                            ->label('Confirm Password')
                            ->password()
                            ->revealable()
                            ->required(fn(Get $get): bool => filled($get('password')))
                            ->same('password') // Ensures it matches the 'password' field
                            ->dehydrated(false), // Exclude from saved model data

                        Select::make('account_id')
                            ->relationship('account', 'name')
                            ->required(),


                        Select::make('roles')->relationship(
                            'roles',
                            'name'
                        )
                            ->getOptionLabelFromRecordUsing(fn(Model $record) => Str::of($record->name)->replace(['-', '_'], ' ')->title())
                            ->required()
                            ->preload(),


                    ])->columnSpanFull()
            ]);
    }
}
