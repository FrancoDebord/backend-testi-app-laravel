<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name', 'slug', 'icon', 'color', 'display_order', 'testimony_count', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'       => 'boolean',
            'display_order'   => 'integer',
            'testimony_count' => 'integer',
        ];
    }

    public function testimonies(): HasMany
    {
        return $this->hasMany(Testimony::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('display_order');
    }
}
