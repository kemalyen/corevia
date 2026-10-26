<?php

namespace App\Filament\Resources\ProductCategories\Pages;

use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use App\Portal\Support\CatalogueAuditLogger;
use Filament\Resources\Pages\CreateRecord;

class CreateProductCategory extends CreateRecord
{
    protected static string $resource = ProductCategoryResource::class;

    protected function afterCreate(): void
    {
        app(CatalogueAuditLogger::class)->categoryChanged('created_with_assignments', $this->record);
    }
}
