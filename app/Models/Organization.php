<?php

namespace App\Models;

use App\Services\DefaultCategories;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant: satu Organization = satu seller. Data bisnis terikat ke organisasi ini.
 */
class Organization extends Model
{
    protected $fillable = ['name', 'slug', 'active'];

    protected static function booted(): void
    {
        // Setiap organisasi baru langsung dapat kategori default (+ tarif resmi),
        // agar fitur kategori & estimasi biaya admin siap pakai untuk SEMUA seller.
        static::created(function (Organization $org): void {
            app(DefaultCategories::class)->seedForOrg((int) $org->id);
        });
    }

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
