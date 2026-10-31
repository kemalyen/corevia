<?php

namespace App\Filament\Resources\Ingestions;

use App\Filament\Resources\Ingestions\Pages\ListIngestions;
use App\Filament\Resources\Ingestions\Pages\ViewIngestion;
use App\Filament\Resources\Ingestions\RelationManagers\ErrorsRelationManager;
use App\Filament\Resources\Ingestions\Schemas\IngestionInfolist;
use App\Filament\Resources\Ingestions\Tables\IngestionsTable;
use App\Modules\IntegrationEngine\Models\Ingestion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class IngestionResource extends Resource
{
    protected static ?string $model = Ingestion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Integration Runs';

    protected static ?string $modelLabel = 'integration run';

    protected static ?string $pluralModelLabel = 'integration runs';

    protected static ?int $navigationSort = 100;

    public static function infolist(Schema $schema): Schema
    {
        return IngestionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IngestionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [ErrorsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIngestions::route('/'),
            'view' => ViewIngestion::route('/{record}'),
        ];
    }
}
