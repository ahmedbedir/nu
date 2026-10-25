<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitTimeEvent extends Model
{
    protected $fillable = ['visit_id', 'kind', 'occurred_at', 'actor_user_id', 'reason'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}