<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * The account principal.
 *
 * NON-NEGOTIABLE: a user account is NOT a patient, NOT a professional and NOT a visit
 * address. Those are separate records linked with explicit, revocable consent.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPublicId, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'phone_country',
        'locale',
        'status',
        'identity_status',
        'gender',
        'birth_date',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'identity_verified_at' => 'datetime',
            'birth_date' => 'date',
            'password' => 'hashed',
        ];
    }

    // --- relationships ---------------------------------------------------

    public function roles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot(['granted_by', 'expires_at'])
            ->withTimestamps();
    }

    public function patientLinks(): HasMany
    {
        return $this->hasMany(PatientUserLink::class);
    }

    public function delegationsGranted(): HasMany
    {
        return $this->hasMany(PatientDelegation::class, 'grantee_user_id');
    }

    // --- authorisation helpers -------------------------------------------

    public function hasRole(string $code): bool
    {
        return $this->roles()
            ->where('code', $code)
            ->where(function ($q) {
                $q->whereNull('user_roles.expires_at')
                    ->orWhere('user_roles.expires_at', '>', now());
            })
            ->exists();
    }

    /** @param string[] $codes */
    public function hasAnyRole(array $codes): bool
    {
        return $this->roles()
            ->whereIn('code', $codes)
            ->where(function ($q) {
                $q->whereNull('user_roles.expires_at')
                    ->orWhere('user_roles.expires_at', '>', now());
            })
            ->exists();
    }

    public function hasPermission(string $code): bool
    {
        return Permission::query()
            ->where('permissions.code', $code)
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('role_permissions')
                    ->join('user_roles', 'user_roles.role_id', '=', 'role_permissions.role_id')
                    ->whereColumn('role_permissions.permission_id', 'permissions.id')
                    ->where('user_roles.user_id', $this->id);
            })
            ->exists();
    }

    public function isIdentityVerified(): bool
    {
        return $this->identity_status === 'verified';
    }
}