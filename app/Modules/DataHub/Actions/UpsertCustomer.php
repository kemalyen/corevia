<?php

namespace App\Modules\DataHub\Actions;

use App\Enums\AccountType;
use App\Models\Account;

class UpsertCustomer
{
    /**
     * @param  array{external_id: string, name: string, status: string}  $data
     */
    public function handle(string $source, array $data): bool
    {
        $account = Account::query()->firstOrNew([
            'source' => $source,
            'external_id' => $data['external_id'],
        ]);
        $wasRecentlyCreated = ! $account->exists;

        $account->fill([
            'name' => $data['name'],
            'account_type' => AccountType::VENDOR,
            'account_status' => $data['status'],
        ])->save();

        return $wasRecentlyCreated;
    }
}
