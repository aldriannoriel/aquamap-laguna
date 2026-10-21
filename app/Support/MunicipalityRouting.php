<?php

namespace App\Support;

use App\Models\WaterBody;
use Illuminate\Support\Collection;

class MunicipalityRouting
{
    /** Exact locality labels verified in the existing catalogue; qualifiers are deliberately excluded. */
    private const LOCALITIES = ['Alaminos', 'Bay', 'Biñan City', 'Calamba City', 'Calauan', 'Cavinti', 'Famy', 'Los Baños', 'Luisiana', 'Majayjay', 'Nagcarlan', 'Pagsanjan', 'Pakil', 'Pangil', 'Pila', 'San Pablo City', 'San Pedro City', 'Santa Cruz', 'Santa Rosa City', 'Siniloan', 'Victoria'];

    public static function municipalities(): Collection
    {
        return WaterBody::supported()->whereIn('municipality', self::LOCALITIES)->distinct()->orderBy('municipality')->pluck('municipality');
    }

    public static function exact(?string $locality): ?string
    {
        return in_array($locality, self::LOCALITIES, true) ? $locality : null;
    }

    public static function label(string $municipality): string
    {
        return str_ends_with($municipality, ' City') ? 'City of '.substr($municipality, 0, -5) : 'Municipality of '.$municipality;
    }
}
