<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'tagline',
        'duration_days',
        'price',
        'original_price',
        'discount_percentage',
        'badge',
        'features',
        'is_popular',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'duration_days'       => 'integer',
        'price'               => 'integer',
        'original_price'      => 'integer',
        'discount_percentage' => 'integer',
        'features'            => 'array',
        'is_popular'          => 'boolean',
        'is_active'           => 'boolean',
        'sort_order'          => 'integer',
    ];

    /**
     * Scope active plans.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order', 'asc');
    }

    /**
     * Formatted price attribute (e.g. Rp 25.000).
     */
    public function getFormattedPriceAttribute(): string
    {
        return 'Rp ' . number_format($this->price, 0, ',', '.');
    }

    /**
     * Short price (e.g. 25rb, 199rb).
     */
    public function getShortPriceAttribute(): string
    {
        if ($this->price >= 1_000_000) {
            return ($this->price / 1_000_000) . 'jt';
        }

        return round($this->price / 1_000) . 'rb';
    }
}
