<?php

namespace App\Filament\Resources\ProductCategories\Pages;

use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use App\Models\ProductCategory;
use App\Portal\Support\CatalogueAuditLogger;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use LogicException;

class EditProductCategory extends EditRecord
{
    protected static string $resource = ProductCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        if (! $this->record instanceof ProductCategory) {
            throw new LogicException('The saved record must be a product category.');
        }

        app(CatalogueAuditLogger::class)->categoryChanged('saved_with_assignments', $this->record);
    }
}
