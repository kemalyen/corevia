<?php

namespace App\Filament\Resources\ProductCategories\Pages;

use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use App\Models\ProductCategory;
use App\Portal\Support\CatalogueAuditLogger;
use Filament\Resources\Pages\CreateRecord;
use LogicException;

class CreateProductCategory extends CreateRecord
{
    protected static string $resource = ProductCategoryResource::class;

    protected function afterCreate(): void
    {
        if (! $this->record instanceof ProductCategory) {
            throw new LogicException('The created record must be a product category.');
        }

        app(CatalogueAuditLogger::class)->categoryChanged('created_with_assignments', $this->record);
    }
}
