<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingQuote extends Model
{
    use HasPublicId;

    protected $fillable = [
        'booking_id', 'version', 'lines', 'subtotal', 'total',
        'currency', 'status', 'expires_at', 'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'lines' => 'array',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}