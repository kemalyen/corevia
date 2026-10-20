<?php

namespace App\Filament\Resources\SalesOrders\Schemas;

use App\Enums\OrderStatus;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class SalesOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sales Order')
                    ->columns(2)
                    ->schema([
                        TextInput::make('order_number')
                            ->required(),
                        DateTimePicker::make('order_date')
                            ->required(),
                        Select::make('account_id')
                            ->relationship(
                                name: 'account',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder => self::scopeAccountOptions($query),
                            )
                            ->required(),
                        TextInput::make('total_amount')
                            ->required()
                            ->numeric(),
                        Select::make('status')
                            ->options(OrderStatus::class),
                    ])->columnSpanFull(),
            ]);
    }

    private static function scopeAccountOptions(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->can('manage-all-orders') || $user->can('manage-all-accounts')) {
            return $query;
        }

        if ($user->account_id !== null) {
            return $query->whereKey($user->account_id);
        }

        return $query->whereIn(
            'accounts.id',
            $user->managedAccounts()->select('accounts.id'),
        );
    }
}
