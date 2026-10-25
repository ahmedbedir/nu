<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceProcedure extends Model
{
    protected $fillable = ['service_id', 'code', 'name_ar', 'name_en', 'price', 'billing_mode', 'duration_minutes'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}