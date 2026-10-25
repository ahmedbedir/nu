<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceVersion extends Model
{
    protected $fillable = ['service_id', 'version', 'snapshot', 'change_note', 'editor_user_id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}