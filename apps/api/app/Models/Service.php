<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A publishable clinical service. Admin drafts it; a **medical reviewer** publishes
 * it (PRD-03, #65). A published version is snapshotted so historical bookings keep
 * their agreed terms.
 */
class Service extends Model
{
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'category_id', 'code', 'name_ar', 'name_en', 'description_ar', 'description_en',
        'profession_id', 'billing_mode', 'default_duration_minutes', 'status',
        'published_by', 'published_at', 'requires_medical_order', 'order_validation_method',
        'required_fields', 'red_flag_screen', 'allowed_age_range', 'allowed_genders',
        'base_price', 'currency',
    ];

    protected function casts(): array
    {
        return [
            'required_fields' => 'array',
            'red_flag_screen' => 'array',
            'allowed_age_range' => 'array',
            'allowed_genders' => 'array',
            'published_at' => 'datetime',
            'requires_medical_order' => 'boolean',
            'base_price' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function profession(): BelongsTo
    {
        return $this->belongsTo(Profession::class);
    }

    public function procedures(): HasMany
    {
        return $this->hasMany(ServiceProcedure::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ServiceVersion::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function ageAllowedFor(?int $age): bool
    {
        if ($age === null || ! $this->allowed_age_range) {
            return true;
        }

        [$min, $max] = $this->allowed_age_range + [null, null];

        if ($min !== null && $age < $min) {
            return false;
        }

        if ($max !== null && $age > $max) {
            return false;
        }

        return true;
    }
}