<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportReview extends Model
{
    public const UPDATED_AT = null;

    public function pollutionReport(): BelongsTo
    {
        return $this->belongsTo(PollutionReport::class);
    }

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administrator_id');
    }
}
