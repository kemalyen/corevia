<?php

namespace App\Filament\Resources\SalesOrders\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SalesOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('order_number'),
                        TextEntry::make('order_date')
                            ->dateTime(),
                        TextEntry::make('account.name')
                            ->numeric(),
                        TextEntry::make('total_amount')
                            ->numeric(),
                        TextEntry::make('status'),
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->placeholder('-'),
                    ])->columnSpanFull(),
            ]);
    }
}
