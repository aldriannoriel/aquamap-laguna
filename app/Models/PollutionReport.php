<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PollutionReport extends Model
{
    public const TYPES = [
        'solid_waste' => 'Litter or solid waste',
        'wastewater' => 'Suspected wastewater discharge',
        'oil_chemical' => 'Oil or suspected chemical spill',
        'fish_death' => 'Dead or distressed fish',
        'water_change' => 'Unusual water color or odor',
        'other' => 'Other observation',
    ];

    protected $hidden = ['reporter_name', 'reporter_email', 'photo_path'];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    }

    public function waterBody(): BelongsTo
    {
        return $this->belongsTo(WaterBody::class);
    }
}
