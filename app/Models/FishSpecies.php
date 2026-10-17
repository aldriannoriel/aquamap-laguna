<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FishSpecies extends Model
{
    public function waterBodies(): BelongsToMany
    {
        return $this->belongsToMany(WaterBody::class, 'water_body_fish', 'fish_species_id', 'water_body_id')
            ->withPivot(['id', 'compatibility_status', 'explanation', 'source_url', 'evidence_type', 'sampling_period', 'publication_year'])
            ->withTimestamps();
    }

    public function waterBodyFish(): HasMany
    {
        return $this->hasMany(WaterBodyFish::class, 'fish_species_id');
    }
}
