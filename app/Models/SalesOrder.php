<?php

namespace App\Models;

use App\Enums\ErpSyncState;
use App\Enums\OrderOrigin;
use App\Enums\OrderStatus;
use Database\Factories\SalesOrderFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $account_id
 * @property string $order_number
 * @property Carbon $order_date
 * @property string $total_amount
 * @property string|null $currency
 * @property OrderStatus $status
 * @property OrderOrigin $origin
 * @property ErpSyncState $erp_sync_state
 * @property string|null $external_id
 * @property string|null $customer_reference
 * @property-read Collection<int, SalesOrderLine> $lines
 */
class SalesOrder extends Model
{
    /** @use HasFactory<SalesOrderFactory> */
    use HasFactory;

    protected $attributes = ['currency' => 'GBP'];

    protected $fillable = [
        'order_number',
        'order_date',
        'account_id',
        'total_amount',
        'status',
        'currency',
        'external_id',
        'origin',
        'erp_sync_state',
        'portal_submission_token',
        'submitted_by_user_id',
        'customer_reference',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'total_amount' => 'decimal:2',
        'status' => OrderStatus::class,
        'origin' => OrderOrigin::class,
        'erp_sync_state' => ErpSyncState::class,
    ];

    /** @return HasMany<SalesOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }
}
