<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'municipality', 'water_type', 'latitude', 'longitude', 'description', 'source_url'])]
class WaterBody extends Model
{
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function fishSpecies(): BelongsToMany
    {
        return $this->belongsToMany(FishSpecies::class, 'water_body_fish', 'water_body_id', 'fish_species_id')
            ->withPivot(['id', 'compatibility_status', 'explanation', 'source_url'])
            ->withTimestamps();
    }

    public function waterBodyFish(): HasMany
    {
        return $this->hasMany(WaterBodyFish::class);
    }

    public function pollutionReports(): HasMany
    {
        return $this->hasMany(PollutionReport::class);
    }
}
