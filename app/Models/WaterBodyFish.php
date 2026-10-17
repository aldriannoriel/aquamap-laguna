<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaterBodyFish extends Model
{
    protected $table = 'water_body_fish';

    public function waterBody(): BelongsTo
    {
        return $this->belongsTo(WaterBody::class);
    }

    public function fishSpecies(): BelongsTo
    {
        return $this->belongsTo(FishSpecies::class, 'fish_species_id');
    }
}
