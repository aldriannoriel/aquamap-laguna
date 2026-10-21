<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Referral extends Model
{
    public const TEST_RECIPIENT = 'aquamaplaguna@gmail.com';

    protected $hidden = ['payload', 'recipient_snapshot'];

    protected function casts(): array
    {
        return ['recipient_snapshot' => 'array', 'payload' => 'encrypted:array', 'include_photo' => 'boolean', 'approved_at' => 'datetime', 'queued_at' => 'datetime',
            'sending_at' => 'datetime', 'sent_at' => 'datetime', 'failed_at' => 'datetime', 'inbox_confirmed_at' => 'datetime'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(PollutionReport::class, 'pollution_report_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ReferralEvent::class)->orderBy('id');
    }
}
