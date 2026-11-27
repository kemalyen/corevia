<?php

namespace App\Filament\Widgets;

use App\Modules\IntegrationEngine\Models\Ingestion;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentIngestionsTable extends TableWidget
{
    protected static ?string $heading = 'Recent Integrations';

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', Ingestion::class) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Ingestion::query()
                    ->latest('created_at')
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('dataset')
                    ->badge(),
                TextColumn::make('original_filename')
                    ->label('File')
                    ->limit(30),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('valid_rows')
                    ->label('Valid')
                    ->numeric(),
                TextColumn::make('failed_rows')
                    ->label('Failed')
                    ->numeric(),
                TextColumn::make('created_at')
                    ->label('Received')
                    ->since(),
            ])
            ->paginated(false);
    }
}
