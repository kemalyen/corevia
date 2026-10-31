<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Portal\Support\CatalogueAuditLogger;
use Filament\Resources\Pages\CreateRecord;
use LogicException;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function afterCreate(): void
    {
        if (! $this->record instanceof Product) {
            throw new LogicException('The created record must be a product.');
        }

        app(CatalogueAuditLogger::class)->productCategoriesChanged($this->record);
    }
}
