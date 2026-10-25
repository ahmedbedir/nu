<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasPublicId;

    protected $fillable = [
        'invoice_id', 'amount', 'method', 'state', 'reason',
        'requested_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'approved_at' => 'datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}