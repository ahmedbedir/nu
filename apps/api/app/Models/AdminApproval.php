<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminApproval extends Model
{
    use HasPublicId;

    protected $fillable = [
        'kind', 'action', 'payload', 'status',
        'requested_by', 'approved_by', 'approved_at', 'reason',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array', 'approved_at' => 'datetime'];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}