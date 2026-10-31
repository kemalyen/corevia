<?php

namespace App\Filament\Resources\Ingestions\Tables;

use App\Modules\IntegrationEngine\Enums\IngestionStatus;
use App\Modules\IntegrationEngine\Enums\IntegrationDataset;
use App\Modules\IntegrationEngine\Models\Ingestion;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class IngestionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('Run')->prefix('#')->sortable(),
                TextColumn::make('dataset')
                    ->badge()
                    ->formatStateUsing(fn (IntegrationDataset $state): string => str($state->value)->headline()->toString())
                    ->searchable(),
                TextColumn::make('original_filename')
                    ->label('File')
                    ->searchable()
                    ->limit(45)
                    ->tooltip(fn (Ingestion $record): string => $record->original_filename),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (IngestionStatus $state): string => str($state->value)->headline()->toString())
                    ->color(fn (IngestionStatus $state): string => match ($state) {
                        IngestionStatus::COMPLETED => 'success',
                        IngestionStatus::PARTIALLY_FAILED => 'warning',
                        IngestionStatus::FAILED => 'danger',
                        IngestionStatus::PROCESSING => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('total_rows')->label('Rows')->numeric()->sortable(),
                TextColumn::make('failed_rows')
                    ->label('Failed')
                    ->numeric()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->sortable(),
                TextColumn::make('errors_count')
                    ->label('Errors')
                    ->counts('errors')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('attempts')->numeric()->sortable(),
                TextColumn::make('discovered_at')->label('Started')->dateTime()->sortable(),
                TextColumn::make('completed_at')->label('Finished')->dateTime()->placeholder('—')->sortable(),
            ])
            ->filters([
                SelectFilter::make('dataset')
                    ->options(collect(IntegrationDataset::cases())->mapWithKeys(
                        fn (IntegrationDataset $dataset): array => [$dataset->value => str($dataset->value)->headline()->toString()],
                    )),
                SelectFilter::make('status')
                    ->options(collect(IngestionStatus::cases())->mapWithKeys(
                        fn (IngestionStatus $status): array => [$status->value => str($status->value)->headline()->toString()],
                    )),
            ])
            ->recordActions([ViewAction::make()])
            ->defaultSort('id', 'desc');
    }
}
