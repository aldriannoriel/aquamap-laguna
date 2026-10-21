<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Agency extends Model
{
    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public static function identityKey(string $name, string $municipality, ?string $barangay): string
    {
        return hash('sha256', mb_strtolower(preg_replace('/\s+/u', ' ', trim($municipality))));
    }

    protected static function booted(): void
    {
        static::saving(function (Agency $agency): void {
            $agency->identity_key = self::identityKey($agency->name, $agency->municipality, null);
        });
    }

    public function waterBodies(): BelongsToMany
    {
        return $this->belongsToMany(WaterBody::class);
    }
}
