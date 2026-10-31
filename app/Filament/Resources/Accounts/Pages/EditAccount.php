<?php

namespace App\Filament\Resources\Accounts\Pages;

use App\Filament\Resources\Accounts\AccountResource;
use App\Models\Account;
use App\Portal\Support\CatalogueAuditLogger;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use LogicException;

class EditAccount extends EditRecord
{
    protected static string $resource = AccountResource::class;

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
        if (! $this->record instanceof Account) {
            throw new LogicException('The saved record must be an account.');
        }

        app(CatalogueAuditLogger::class)->accountConfigurationChanged($this->record);
    }
}
