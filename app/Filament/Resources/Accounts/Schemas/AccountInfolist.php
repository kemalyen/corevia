<?php

namespace App\Filament\Resources\Accounts\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AccountInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('View Account Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('account_number'),
                        TextEntry::make('account_type'),
                        TextEntry::make('account_status'),
                        TextEntry::make('product_access_mode')->label('Product access'),
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
