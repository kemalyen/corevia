<?php

namespace App\Filament\Resources\Accounts\Pages;

use App\Filament\Resources\Accounts\AccountResource;
use App\Models\Account;
use App\Portal\Support\CatalogueAuditLogger;
use Filament\Resources\Pages\CreateRecord;
use LogicException;

class CreateAccount extends CreateRecord
{
    protected static string $resource = AccountResource::class;

    protected function afterCreate(): void
    {
        if (! $this->record instanceof Account) {
            throw new LogicException('The created record must be an account.');
        }

        app(CatalogueAuditLogger::class)->accountConfigurationChanged($this->record);
    }
}
