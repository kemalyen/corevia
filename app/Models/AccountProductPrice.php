<?php

namespace App\Models;

use App\Portal\Support\CatalogueAuditLogger;
use Database\Factories\AccountProductPriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $account_id
 * @property int $product_id
 * @property string $amount
 * @property string $currency
 * @property string $unit_of_measure
 */
class AccountProductPrice extends Model
{
    /** @use HasFactory<AccountProductPriceFactory> */
    use HasFactory;

    protected $fillable = ['account_id', 'product_id', 'amount', 'currency', 'unit_of_measure'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:4'];
    }

    protected static function booted(): void
    {
        static::created(fn (AccountProductPrice $price) => app(CatalogueAuditLogger::class)->accountPriceChanged('created', $price));
        static::updated(fn (AccountProductPrice $price) => app(CatalogueAuditLogger::class)->accountPriceChanged('updated', $price));
        static::deleted(fn (AccountProductPrice $price) => app(CatalogueAuditLogger::class)->accountPriceChanged('deleted', $price));
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
