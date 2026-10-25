<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scoped, revocable, time-limited delegation of patient rights (#22–#24).
 *
 * Scopes are explicit strings; a grant for "book" never implies "view_medical".
 */
class PatientDelegation extends Model
{
    use HasPublicId;

    public const SCOPE_BOOK = 'book';
    public const SCOPE_PAY = 'pay';
    public const SCOPE_VIEW_VISIT_STATUS = 'view_visit_status';
    public const SCOPE_VIEW_MEDICAL = 'view_medical';

    public const SCOPES = [
        self::SCOPE_BOOK,
        self::SCOPE_PAY,
        self::SCOPE_VIEW_VISIT_STATUS,
        self::SCOPE_VIEW_MEDICAL,
    ];

    protected $fillable = [
        'patient_id', 'grantee_user_id', 'granted_by_user_id', 'scopes',
        'status', 'valid_from', 'valid_until', 'consent_evidence',
        'revoked_at', 'revoked_by',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function grantee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'grantee_user_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->revoked_at === null
            && ($this->valid_from === null || $this->valid_from->isPast())
            && ($this->valid_until === null || $this->valid_until->isFuture());
    }

    public function hasScope(string $scope): bool
    {
        return $this->isActive() && in_array($scope, $this->scopes ?? [], true);
    }
}