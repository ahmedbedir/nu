<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One side of a double-entry journal. Entries are append-only; a correction is a
 * new compensating journal, never a mutation of a posted entry.
 */
class LedgerEntry extends Model
{
    protected $fillable = [
        'journal_id', 'account_id', 'side', 'amount', 'currency',
        'reference_type', 'reference_id', 'memo', 'posted_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'account_id');
    }
}