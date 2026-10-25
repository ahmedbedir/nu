<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Independent cash attestation by each party. A disagreement is a dispute —
 * never a settlement.
 */
class CashCollectionConfirmation extends Model
{
    protected $fillable = ['invoice_id', 'party', 'claim', 'amount', 'confirmed_by', 'confirmed_at'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'confirmed_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}