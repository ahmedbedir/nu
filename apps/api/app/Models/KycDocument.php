<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Private KYC document. Files live on a private disk; only short-lived signed URLs
 * are ever issued, and the raw path is never serialised to clients.
 */
class KycDocument extends Model
{
    protected $fillable = [
        'user_id', 'identity_verification_id', 'doc_type', 'disk', 'path',
        'mime', 'size_bytes', 'checksum', 'scan_status', 'status',
    ];

    protected $hidden = ['path', 'checksum'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verification(): BelongsTo
    {
        return $this->belongsTo(IdentityVerification::class, 'identity_verification_id');
    }
}