<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Portal\Support\CatalogueAuditLogger;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use LogicException;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return '';
    }

    protected function afterSave(): void
    {
        if (! $this->record instanceof Product) {
            throw new LogicException('The saved record must be a product.');
        }

        app(CatalogueAuditLogger::class)->productCategoriesChanged($this->record);
    }
}
