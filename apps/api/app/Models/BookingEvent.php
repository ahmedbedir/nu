<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['booking_id', 'event', 'from_state', 'to_state', 'actor_user_id', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}