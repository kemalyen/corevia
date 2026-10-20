<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),

                TextColumn::make('roles.name')->label('Roles')
                    ->getStateUsing(fn ($record) => $record->roles->pluck('name')->map(fn ($role) => str($role)->replace(['-', '_'], ' ')->title())->join(', ')),

                TextColumn::make('account.name')
                    ->searchable()
                    ->label('Account Name')
                    ->getStateUsing(fn ($record) => $record->account?->name)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('managedAccounts.name')
                    ->label('Account Manager Accounts')
                    ->getStateUsing(fn ($record) => $record->managedAccounts->pluck('name')->join(', '))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('account_id')
                    ->relationship('account', 'name')
                    ->label('Account Name'),
                SelectFilter::make('roles.name')
                    ->relationship('roles', 'name')
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => Str::of($record->name)->replace(['-', '_'], ' ')->title()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }
}
