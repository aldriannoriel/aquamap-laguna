<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportReview extends Model
{
    public const UPDATED_AT = null;

    public function incidentRouting(): ?array
    {
        if ($this->previous_status !== $this->new_status || ! is_string($this->review_note)) {
            return null;
        }
        $selection = json_decode($this->review_note, true);

        return is_array($selection) && ($selection['kind'] ?? null) === 'incident_municipality_v1'
            && is_int($selection['agency_id'] ?? null) && is_string($selection['municipality'] ?? null)
            && is_string($selection['point_key'] ?? null) && is_string($selection['reason'] ?? null) ? $selection : null;
    }

    public function pollutionReport(): BelongsTo
    {
        return $this->belongsTo(PollutionReport::class);
    }

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administrator_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(ReportEvidence::class)->chaperone('review');
    }
}
