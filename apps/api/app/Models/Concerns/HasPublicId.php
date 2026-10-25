<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Adds a non-enumerable public ULID identifier.
 *
 * Sequential primary keys are never exposed in URLs or API payloads, which removes
 * the simplest class of IDOR ("guess the next id"). Route binding uses `public_id`.
 */
trait HasPublicId
{
    protected static function bootHasPublicId(): void
    {
        static::creating(function ($model) {
            if (empty($model->public_id)) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function scopeWherePublicId(Builder $query, string $publicId): Builder
    {
        return $query->where($this->getTable().'.public_id', $publicId);
    }
}