<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['name', 'municipality', 'water_type', 'latitude', 'longitude', 'description', 'source_url'])]
class WaterBody extends Model
{
    public const CATEGORIES = [
        'lake' => 'Lakes',
        'river' => 'Rivers',
        'waterfall' => 'Waterfalls',
    ];

    public const PUBLIC_CATEGORIES = self::CATEGORIES;

    public const REPORT_CATEGORIES = self::CATEGORIES;

    protected static function booted(): void
    {
        static::creating(function (WaterBody $water): void {
            if (! array_key_exists($water->category ?? 'lake', self::CATEGORIES)
                || ($water->site_type ?? 'water_body') !== 'water_body' || $water->spring_type !== null) {
                throw ValidationException::withMessages(['category' => 'Only lakes, rivers and waterfalls are supported.']);
            }
        });
    }

    #[Scope]
    protected function supported(Builder $query): void
    {
        $query->whereIn('category', array_keys(self::CATEGORIES))
            ->where('site_type', 'water_body')->whereNull('spring_type');
    }

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'aliases' => 'array',
            'geography' => 'array',
            'research_references' => 'array',
            'image' => 'array',
        ];
    }

    public function fishSpecies(): BelongsToMany
    {
        return $this->belongsToMany(FishSpecies::class, 'water_body_fish', 'water_body_id', 'fish_species_id')
            ->withPivot(['id', 'compatibility_status', 'explanation', 'source_url', 'evidence_type', 'sampling_period', 'publication_year'])
            ->withTimestamps();
    }

    public function waterBodyFish(): HasMany
    {
        return $this->hasMany(WaterBodyFish::class);
    }

    public function pollutionReports(): HasMany
    {
        return $this->hasMany(PollutionReport::class);
    }
}
