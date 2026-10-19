<?php

namespace App\Filament\Resources\SalesOrders\Schemas;

use App\Enums\OrderStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;


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
                            ->relationship('account', 'name')
                            ->required(),
                        TextInput::make('total_amount')
                            ->required()
                            ->numeric(),
                        Select::make('status')
                            ->options(OrderStatus::class)
                    ])->columnSpanFull()
            ]);
    }
}
