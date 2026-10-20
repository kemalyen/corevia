<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('View User Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('email')
                            ->label('Email address'),
                        TextEntry::make('email_verified_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->placeholder('-'),

                        TextEntry::make('account.name')
                            ->label('Account Name')
                            ->hidden(fn ($record) => $record->account === null || $record->hasRole('vendor-user')),

                        TextEntry::make('managedAccounts.name')
                            ->label('Account Manager Accounts')
                            ->hidden(fn ($record) => $record->hasRole('vendor-user')),

                        TextEntry::make('roles.name')
                            ->label('Role')->getStateUsing(fn ($record) => $record->roles()->pluck('name')->map(fn ($role) => str($role)->replace(['-', '_'], ' ')->title())->join(', ')),

                    ])->columnSpanFull(),
            ]);
    }
}
