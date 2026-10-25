<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Administrative geography node: governorate → city → district → village/sector.
 */
class Region extends Model
{
    use HasPublicId;

    protected $fillable = [
        'parent_id', 'level', 'code', 'name_ar', 'name_en',
        'center_lat', 'center_lng', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'center_lat' => 'decimal:7',
            'center_lng' => 'decimal:7',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}