<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only audit trail (#215). Rows are never updated or deleted by application code;
 * `hash` chains to `prev_hash` so casual tampering is detectable.
 */
class AuditEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'event', 'actor_user_id', 'actor_role', 'subject_type', 'subject_id',
        'purpose', 'metadata', 'correlation_id', 'ip', 'prev_hash', 'hash',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}