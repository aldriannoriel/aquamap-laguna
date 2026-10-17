<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PollutionReport extends Model
{
    public function waterBody(): BelongsTo
    {
        return $this->belongsTo(WaterBody::class);
    }
}
