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
        return hash('sha256', implode('|', array_map(fn (?string $v): string => mb_strtolower(preg_replace('/\s+/u', ' ', trim($v ?? ''))), [$name, $municipality, $barangay])));
    }

    public function waterBodies(): BelongsToMany
    {
        return $this->belongsToMany(WaterBody::class);
    }
}
