<?php

namespace App\Filament\Resources\Ingestions\RelationManagers;

use App\Modules\IntegrationEngine\Models\Ingestion;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ErrorsRelationManager extends RelationManager
{
    protected static string $relationship = 'errors';

    protected static ?string $title = 'Validation and processing errors';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Ingestion
            && (auth()->user()?->can('view', $ownerRecord) ?? false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('message')
            ->columns([
                TextColumn::make('row_number')->label('Row')->placeholder('File')->sortable(),
                TextColumn::make('field')->placeholder('—')->searchable(),
                TextColumn::make('code')->badge()->searchable(),
                TextColumn::make('message')->searchable()->wrap(),
                TextColumn::make('created_at')->label('Logged')->dateTime()->sortable(),
            ])
            ->defaultSort('row_number');
    }
}
