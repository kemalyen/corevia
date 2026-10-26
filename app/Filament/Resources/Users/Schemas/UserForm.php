<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

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

                        Toggle::make('is_active')
                            ->label('Active user')
                            ->default(true),

                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create'),

                        TextInput::make('passwordConfirmation')
                            ->label('Confirm Password')
                            ->password()
                            ->revealable()
                            ->required(fn (Get $get): bool => filled($get('password')))
                            ->same('password') // Ensures it matches the 'password' field
                            ->dehydrated(false), // Exclude from saved model data

                        Select::make('account_id')
                            ->relationship('account', 'name')
                            ->required(fn (Get $get): bool => self::hasSelectedRole($get, 'vendor-user'))
                            ->visible(fn (Get $get): bool => self::hasSelectedRole($get, 'vendor-user')),

                        Select::make('managedAccounts')
                            ->relationship('managedAccounts', 'name')
                            ->multiple()
                            ->preload()
                            ->visible(fn (Get $get): bool => self::hasSelectedRole($get, 'account-manager'))
                            ->label('Managed Accounts')
                            ->required(fn (Get $get): bool => self::hasSelectedRole($get, 'account-manager')),

                        Select::make('roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->live()
                            ->getOptionLabelFromRecordUsing(fn (Role $record) => Str::of($record->name)->replace(['-', '_'], ' ')->title())
                            ->required()
                            ->preload(),
                    ])->columnSpanFull(),
            ]);
    }

    private static function hasSelectedRole(Get $get, string $roleName): bool
    {
        $selectedRoleIds = (array) $get('roles');

        return Role::query()
            ->where('name', $roleName)
            ->whereKey($selectedRoleIds)
            ->exists();
    }
}
