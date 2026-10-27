<?php

namespace App\Filament\Resources\Accounts\RelationManagers;

use App\Models\AccountProductPrice;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccountPricesRelationManager extends RelationManager
{
    protected static string $relationship = 'accountPrices';

    public function form(Schema $schema): Schema
    {
        $accountId = $this->getOwnerRecord()->getKey();

        return $schema
            ->components([
                Select::make('product_id')
                    ->relationship('product', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->sku} — {$record->name}")
                    ->searchable(['sku', 'name'])
                    ->required()
                    ->rule(fn (Get $get, ?AccountProductPrice $record) => Rule::unique('account_product_prices', 'product_id')
                        ->where('account_id', $accountId)
                        ->where('currency', Str::upper(trim((string) $get('currency'))))
                        ->where('unit_of_measure', Str::upper(trim((string) $get('unit_of_measure'))))
                        ->ignore($record))
                    ->columnSpanFull(),
                TextInput::make('amount')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.0001)
                    ->required(),
                TextInput::make('currency')
                    ->required()
                    ->length(3)
                    ->regex('/^[A-Z]{3}$/')
                    ->dehydrateStateUsing(fn (string $state): string => Str::upper(trim($state)))
                    ->helperText('No currency conversion is performed.'),
                TextInput::make('unit_of_measure')
                    ->maxLength(32)
                    ->default('')
                    ->dehydrateStateUsing(fn (?string $state): string => Str::upper(trim((string) $state)))
                    ->helperText('Leave blank only when the product feed unit is also blank.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product.name')
            ->columns([
                TextColumn::make('product.sku')->label('Product code')->searchable(),
                TextColumn::make('product.name')->searchable(),
                TextColumn::make('amount')->numeric(decimalPlaces: 4),
                TextColumn::make('currency'),
                TextColumn::make('unit_of_measure')->placeholder('Not supplied'),
                TextColumn::make('fallback')
                    ->state(fn (): string => 'Falls back to the matching product-feed price when no exact override exists.')
                    ->wrap(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! auth()->check()) {
            return false;
        }

        return auth()->user()->can('update', $ownerRecord)
            && auth()->user()->can('manage-account-catalogue');
    }
}
