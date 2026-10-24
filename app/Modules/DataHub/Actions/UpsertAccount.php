<?php

namespace App\Modules\DataHub\Actions;

use App\Models\Account;

class UpsertAccount
{
    /**
     * @param  array{external_id: string, account_type: string, name: string, status: string}  $data
     */
    public function handle(array $data): bool
    {
        $account = Account::query()->firstOrNew([
            'external_id' => $data['external_id'],
            'account_number' => $data['account_number'],
        ]);
        $wasRecentlyCreated = ! $account->exists;

        $account->fill([
            'name' => $data['name'],
            'account_type' => $data['account_type'],
            'account_status' => $data['status'],
        ])->save();

        return $wasRecentlyCreated;
    }
}
