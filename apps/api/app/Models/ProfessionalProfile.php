<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A professional credential profile — separate from ·account active state·.
 *
 * A user may hold several; each carries its own credentials, and eligibility to
 * execute a specific procedure is decided per credential scope (AC-02, T-011, T-012).
 */
class ProfessionalProfile extends Model
{
    use HasFactory;
    use HasPublicId;

    protected $fillable = [
        'user_id', 'profession_id', 'display_name', 'bio',
        'years_experience_self_declared', 'declares_external_employment', 'noc_document_ref',
        'status', 'gender', 'home_lat', 'home_lng', 'suspended_until', 'suspension_reason',
    ];

    protected function casts(): array
    {
        return [
            'declares_external_employment' => 'boolean',
            'suspended_until' => 'datetime',
            'home_lat' => 'decimal:7',
            'home_lng' => 'decimal:7',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profession(): BelongsTo
    {
        return $this->belongsTo(Profession::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(ProfessionalCredential::class);
    }

    public function serviceApprovals(): HasMany
    {
        return $this->hasMany(ProviderServiceApproval::class);
    }

    public function areas(): HasMany
    {
        return $this->hasMany(ProviderArea::class);
    }

    /** Credentials that are verified and not past their expiry date. */
    public function validCredentials(): HasMany
    {
        return $this->credentials()
            ->where('status', 'verified')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()->toDateString());
            });
    }
}