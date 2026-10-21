<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PollutionReport extends Model
{
    public const STATUSES = ['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected', 'resolved' => 'Resolved'];

    public const TRANSITIONS = ['pending' => ['verified', 'rejected'], 'verified' => ['resolved'], 'rejected' => [], 'resolved' => []];

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
            $query->whereHas('referral', fn (Builder $q) => $q->where('status', '!=', 'accepted'))
                ->orWhere(fn (Builder $q) => $q->whereIn('status', ['pending', 'verified'])->whereDoesntHave('referral'));
        });
    }

    public function getWorkflowStatusAttribute(): string
    {
        if ($this->referral) {
            return $this->referral->label();
        }

        return match ($this->status) {
            'pending' => 'Pending review', 'verified' => 'Ready for referral approval',
            default => self::STATUSES[$this->status],
        };
    }
}
