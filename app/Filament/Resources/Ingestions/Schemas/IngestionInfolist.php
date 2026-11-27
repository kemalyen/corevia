<?php

namespace App\Filament\Resources\Ingestions\Schemas;

use App\Modules\IntegrationEngine\Enums\IngestionStatus;
use App\Modules\IntegrationEngine\Enums\IntegrationDataset;
use App\Modules\IntegrationEngine\Models\Ingestion;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class IngestionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Run summary')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('id')->label('Run')->formatStateUsing(fn (int $state): string => "#{$state}"),
                        TextEntry::make('dataset')->badge()->formatStateUsing(
                            fn (IntegrationDataset $state): string => str($state->value)->headline()->toString(),
                        ),
                        TextEntry::make('status')->badge()->formatStateUsing(
                            fn (IngestionStatus $state): string => str($state->value)->headline()->toString(),
                        ),
                        TextEntry::make('attempts'),
                        TextEntry::make('original_filename')->label('File')->columnSpan(2),
                        TextEntry::make('checksum')->copyable()->columnSpan(2),
                        TextEntry::make('file_size')->label('File size')->formatStateUsing(fn (int $state): string => Number::fileSize($state)),
                        TextEntry::make('total_rows')->label('Total rows'),
                        TextEntry::make('valid_rows')->label('Valid rows'),
                        TextEntry::make('failed_rows')->label('Failed rows'),
                        TextEntry::make('created_records')->label('Created records'),
                        TextEntry::make('updated_records')->label('Updated records'),
                    ]),
                Section::make('Timeline')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('discovered_at')->dateTime(),
                        TextEntry::make('queued_at')->dateTime()->placeholder('—'),
                        TextEntry::make('processing_started_at')->dateTime()->placeholder('—'),
                        TextEntry::make('completed_at')->dateTime()->placeholder('—'),
                        TextEntry::make('failed_at')->dateTime()->placeholder('—'),
                    ]),
                Section::make('Latest system error')
                    ->schema([
                        TextEntry::make('last_error')->placeholder('No system error recorded.'),
                    ])
                    ->visible(fn (Ingestion $record): bool => filled($record->last_error)),
            ]);
    }
}
