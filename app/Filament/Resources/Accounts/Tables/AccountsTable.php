<?php

namespace App\Filament\Resources\Accounts\Tables;

use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Resources\Accounts\Pages\ManageAccountProductPrices;
use App\Filament\Resources\Accounts\Pages\ManageAccountSalesOrders;
use App\Filament\Resources\Accounts\Pages\ManageAccountUsers;
use App\Models\Account;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('account_number')
                    ->searchable(),
                TextColumn::make('account_status')->badge(),
                TextColumn::make('account_type')->badge(),
                TextColumn::make('product_access_mode')->label('Product access')->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ActionGroup::make([
                    Action::make('users')
                        ->icon(Heroicon::OutlinedUsers)
                        ->visible(fn (Account $record): bool => ManageAccountUsers::canAccess(['record' => $record]))
                        ->url(fn (Account $record): string => AccountResource::getUrl('users', ['record' => $record])),
                    Action::make('salesOrders')
                        ->label('Sales Orders')
                        ->icon(Heroicon::OutlinedShoppingCart)
                        ->visible(fn (Account $record): bool => ManageAccountSalesOrders::canAccess(['record' => $record]))
                        ->url(fn (Account $record): string => AccountResource::getUrl('sales-orders', ['record' => $record])),
                    Action::make('productPrices')
                        ->label('Product Prices')
                        ->icon(Heroicon::OutlinedCurrencyPound)
                        ->visible(fn (Account $record): bool => ManageAccountProductPrices::canAccess(['record' => $record]))
                        ->url(fn (Account $record): string => AccountResource::getUrl('product-prices', ['record' => $record])),
                ])
                    ->label('Related')
                    ->icon(Heroicon::OutlinedEllipsisVertical),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
