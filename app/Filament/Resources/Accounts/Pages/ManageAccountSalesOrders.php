<?php

namespace App\Filament\Resources\Accounts\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Models\Account;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;

class ManageAccountSalesOrders extends ManageRelatedRecords
{
    protected static string $resource = AccountResource::class;

    protected static string $relationship = 'sales_orders';

    protected static ?string $relatedResource = SalesOrderResource::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $navigationLabel = 'Sales Orders';

    public static function canAccess(array $parameters = []): bool
    {
        $account = $parameters['record'] ?? null;

        return $account instanceof Account
            && $account->isVendor()
            && AccountResource::canView($account)
            && parent::canAccess($parameters);
    }

    public function getTitle(): string|Htmlable
    {
        return "Sales Orders — {$this->getRecord()->name}";
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('order_number')
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order reference')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer_reference')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('order_date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('total_amount')
                    ->money(fn ($record): string => $record->currency ?? 'GBP')
                    ->sortable(),
            ])
            ->filters([
                DateRangeFilter::make('order_date'),
                SelectFilter::make('status')
                    ->options(OrderStatus::class),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort('order_date', 'desc');
    }
}
