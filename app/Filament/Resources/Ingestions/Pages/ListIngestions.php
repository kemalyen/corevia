<?php

namespace App\Filament\Resources\Ingestions\Pages;

use App\Filament\Resources\Ingestions\IngestionResource;
use Filament\Resources\Pages\ListRecords;

class ListIngestions extends ListRecords
{
    protected static string $resource = IngestionResource::class;
}
