<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Referral extends Model
{
    public const TEST_RECIPIENT = 'aquamaplaguna@gmail.com';

    public const DELIVERY_APPROVAL = 'municipality_delivery_confirmed';

    public function deliveryApproved(): bool
    {
        return $this->pollution_report_id !== null && $this->events()->where('event', self::DELIVERY_APPROVAL)->exists();
    }

    public function label(): string
    {
        if ($this->interrupted()) {
            return $this->status === 'sending' ? 'Delivery outcome unknown' : 'Preparation interrupted';
        }

        return match ($this->status) {
            'queued', 'cancelled' => 'Not Sent', 'preparing', 'sending' => 'Sending',
            'accepted' => 'Sent / accepted by SMTP', 'failed' => 'Failed',
            'uncertain' => 'Failed', default => ucfirst($this->status),
        };
    }

    public function interrupted(): bool
    {
        return in_array($this->status, ['preparing', 'sending'], true) && $this->sent_at === null
            && $this->updated_at?->lt(now()->subMinutes(5));
    }

    public function retryable(): bool
    {
        return $this->status === 'failed' && ($this->sending_at === null || in_array($this->error_code, ['smtp_auth_rejected', 'smtp_rejected', 'smtp_connection_failed'], true));
    }

    public function cancellable(): bool
    {
        return $this->sent_at === null && (in_array($this->status, ['queued', 'preparing', 'cancelled'], true) || $this->retryable());
    }

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
