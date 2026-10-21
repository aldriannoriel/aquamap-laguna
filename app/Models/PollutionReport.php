<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PollutionReport extends Model
{
    public const STATUSES = ['pending' => 'Pending', 'verified' => 'Verified', 'under_review' => 'Under Review',
        'in_progress' => 'In Progress', 'rejected' => 'Rejected', 'resolved' => 'Resolved', 'closed' => 'Closed'];

    public const TRANSITIONS = ['pending' => ['verified', 'rejected', 'under_review', 'in_progress', 'resolved', 'closed'],
        'verified' => ['rejected', 'under_review', 'in_progress', 'resolved', 'closed'],
        'under_review' => ['in_progress', 'resolved', 'closed'], 'in_progress' => ['under_review', 'resolved', 'closed'],
        'rejected' => [], 'resolved' => [], 'closed' => []];

    public const TYPES = [
        'plastic_waste' => 'Plastic waste',
        'floating_garbage' => 'Floating garbage',
        'illegal_dumping' => 'Suspected illegal dumping',
        'solid_waste' => 'Litter or solid waste',
        'wastewater' => 'Suspected wastewater discharge',
        'oil_chemical' => 'Oil or suspected chemical spill',
        'fish_death' => 'Dead or distressed fish',
        'water_change' => 'Unusual water color or odor',
        'other' => 'Other visible environmental concern',
    ];

    protected $hidden = ['reporter_name', 'reporter_email', 'photo_path', 'latitude', 'longitude', 'landmark', 'location_accuracy_m'];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'observed_on' => 'date', 'location_accuracy_m' => 'decimal:2'];
    }

    public function waterBody(): BelongsTo
    {
        return $this->belongsTo(WaterBody::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ReportReview::class)->orderBy('id');
    }

    public function referral(): HasOne
    {
        return $this->hasOne(Referral::class);
    }

    public function scopeActionRequired(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereHas('referral', fn (Builder $q) => $q->whereNotIn('status', ['accepted', 'cancelled'])->whereNull('sent_at'))
                ->orWhere(fn (Builder $q) => $q->whereIn('status', ['pending', 'verified'])->whereDoesntHave('referral'));
        });
    }

    public function scopeForwarded(Builder $query): Builder
    {
        return $query->whereHas('referral', fn (Builder $referral) => $referral->where(fn (Builder $delivery) => $delivery
            ->where('status', 'accepted')->orWhereNotNull('sent_at')));
    }

    public function scopeProcessed(Builder $query): Builder
    {
        return $query->where(fn (Builder $reports) => $reports->forwarded()->orWhere(fn (Builder $closed) => $closed
            ->whereIn('status', ['rejected', 'resolved', 'closed'])
            ->where(fn (Builder $delivery) => $delivery->whereDoesntHave('referral')
                ->orWhereHas('referral', fn (Builder $referral) => $referral->where('status', 'cancelled')))));
    }

    public function forwarded(): bool
    {
        return $this->referral && ($this->referral->status === 'accepted' || $this->referral->sent_at !== null);
    }

    public function canReject(): bool
    {
        return in_array($this->status, ['pending', 'verified'], true)
            && (! $this->referral || $this->referral->cancellable());
    }

    public function availableTransitions(): array
    {
        $allowed = self::TRANSITIONS[$this->status] ?? [];
        if ($this->forwarded()) {
            return array_values(array_intersect($allowed, ['under_review', 'in_progress', 'resolved', 'closed']));
        }

        return $this->canReject() ? array_values(array_intersect($allowed, ['verified', 'rejected'])) : [];
    }

    public function getWorkflowStatusAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pending review', 'verified' => $this->forwarded() ? 'Forwarded / awaiting follow-up' : 'Approved / awaiting forwarding',
            default => self::STATUSES[$this->status] ?? 'Unknown status',
        };
    }
}
