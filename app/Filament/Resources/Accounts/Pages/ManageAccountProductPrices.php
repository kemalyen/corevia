<?php

namespace App\Filament\Resources\Accounts\Pages;

use App\Enums\Currency;
use App\Filament\Resources\Accounts\AccountResource;
use App\Models\Account;
use App\Models\AccountProductPrice;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ManageAccountProductPrices extends ManageRelatedRecords
{
    protected static string $resource = AccountResource::class;

    protected static string $relationship = 'accountPrices';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyPound;

    protected static ?string $navigationLabel = 'Product Prices';

    public static function canAccess(array $parameters = []): bool
    {
        $account = $parameters['record'] ?? null;

        return $account instanceof Account
            && $account->isVendor()
            && AccountResource::canEdit($account)
            && (auth()->user()?->can('manage-account-catalogue') ?? false);
    }

    public function getTitle(): string|Htmlable
    {
        return "Product Prices — {$this->getRecord()->name}";
    }

    public function form(Schema $schema): Schema
    {
        $accountId = $this->getRecord()->getKey();

        return $schema
            ->components([
                Select::make('product_id')
                    ->relationship('product', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->sku} — {$record->name}")
                    ->searchable(['sku', 'name'])
                    ->required()
                    ->rule(fn (Get $get, ?AccountProductPrice $record) => Rule::unique('account_product_prices', 'product_id')
                        ->where('account_id', $accountId)
                        ->where('currency', $get('currency'))
                        ->where('unit_of_measure', Str::upper(trim((string) $get('unit_of_measure'))))
                        ->ignore($record))
                    ->columnSpanFull(),
                TextInput::make('amount')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.0001)
                    ->required(),
                Select::make('currency')
                    ->options(Currency::class)
                    ->enum(Currency::class)
                    ->default(Currency::GBP->value)
                    ->required()
                    ->searchable()
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
                TextColumn::make('product.sku')
                    ->label('Product code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('product.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Account-specific price')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),
                TextColumn::make('currency'),
                TextColumn::make('unit_of_measure')
                    ->label('Unit')
                    ->placeholder('Not supplied'),
            ])
            ->emptyStateHeading('No account-specific price overrides')
            ->emptyStateDescription('Products without an override continue to use their matching normal product-feed price.')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }
}
