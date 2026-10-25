<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CredentialReview extends Model
{
    protected $fillable = ['credential_id', 'reviewer_user_id', 'verdict', 'notes'];

    public function credential(): BelongsTo
    {
        return $this->belongsTo(ProfessionalCredential::class, 'credential_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }
}