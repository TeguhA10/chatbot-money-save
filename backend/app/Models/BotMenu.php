<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BotMenu extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'category_icon',
        'command',
        'description',
        'is_quick_command',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_quick_command' => 'boolean',
        'is_active'        => 'boolean',
        'sort_order'       => 'integer',
    ];

    /**
     * Scope active items.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order', 'asc');
    }
}
