<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Services\AdminFeeEstimator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use BelongsToOrganization;

    // organization_id otomatis diisi trait (tidak fillable).
    protected $fillable = ['name', 'fee_shopee', 'fee_tokotiktok'];

    protected function casts(): array
    {
        return [
            'fee_shopee' => 'decimal:2',
            'fee_tokotiktok' => 'decimal:2',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** Tarif % biaya admin untuk channel pesanan (SHOPEE vs TIKTOKTOKO; Tokopedia+TikTok digabung jadi TIKTOKTOKO). */
    public function feeForMarketplace(string $marketplace): float
    {
        $fee = $marketplace === 'SHOPEE'
            ? (float) $this->fee_shopee
            : (float) $this->fee_tokotiktok;

        // Bila tarif kategori belum diisi (0), pakai default agar estimasi tidak jadi 0.
        return $fee > 0 ? $fee : AdminFeeEstimator::DEFAULT_COMMISSION_PCT;
    }
}
