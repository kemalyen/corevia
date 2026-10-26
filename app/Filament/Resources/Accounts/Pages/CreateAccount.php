<?php

namespace App\Filament\Resources\Accounts\Pages;

use App\Filament\Resources\Accounts\AccountResource;
use App\Portal\Support\CatalogueAuditLogger;
use Filament\Resources\Pages\CreateRecord;

class CreateAccount extends CreateRecord
{
    protected static string $resource = AccountResource::class;

    protected function afterCreate(): void
    {
        app(CatalogueAuditLogger::class)->accountConfigurationChanged($this->record);
    }
}
