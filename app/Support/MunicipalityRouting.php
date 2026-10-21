<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\PollutionReport;
use App\Models\ReportReview;
use App\Models\User;
use App\Models\WaterBody;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MunicipalityRouting
{
    /** Reviewed Laguna locality labels; catalogue qualifiers do not establish municipality identity. */
    private const LOCALITIES = ['Alaminos', 'Bay', 'Biñan City', 'Calamba City', 'Calauan', 'Cavinti', 'Famy', 'Los Baños', 'Luisiana', 'Magdalena', 'Majayjay', 'Nagcarlan', 'Pagsanjan', 'Pakil', 'Pangil', 'Pila', 'San Pablo City', 'San Pedro City', 'Santa Cruz', 'Santa Rosa City', 'Siniloan', 'Victoria'];

    public static function municipalities(): Collection
    {
        return WaterBody::supported()->whereIn('municipality', self::LOCALITIES)->distinct()->pluck('municipality')
            ->push('Magdalena')->unique()->sort()->values();
    }

    public static function exact(?string $locality): ?string
    {
        return in_array($locality, self::LOCALITIES, true) ? $locality : null;
    }

    public static function label(string $municipality): string
    {
        return str_ends_with($municipality, ' City') ? 'City of '.substr($municipality, 0, -5) : 'Municipality of '.$municipality;
    }

    public static function pointKey(PollutionReport $report): string
    {
        return hash('sha256', json_encode([$report->id, $report->latitude, $report->longitude, $report->location_accuracy_m], JSON_THROW_ON_ERROR));
    }

    /** No municipality boundary dataset with verified provenance is installed. */
    public static function incident(PollutionReport $report): array
    {
        return ['municipality' => null,
            'status' => $report->latitude !== null && $report->longitude !== null ? 'boundaries_unavailable' : 'missing_incident_point',
            'point_key' => self::pointKey($report)];
    }

    public static function selection(PollutionReport $report): ?array
    {
        $reviews = $report->relationLoaded('reviews') ? $report->reviews->sortByDesc('id') : $report->reviews()->reorder('id', 'desc')->get();
        foreach ($reviews as $review) {
            $selection = $review->incidentRouting();
            if ($selection !== null) {
                return hash_equals(self::pointKey($report), $selection['point_key']) ? [...$selection, 'review_id' => $review->id,
                    'selected_by' => $review->administrator_id, 'selected_at' => $review->created_at->toIso8601String()] : null;
            }
        }

        return null;
    }

    public static function select(PollutionReport $report, Agency $agency, User $admin, string $reason, string $pointKey, string $expectedStatus): ReportReview
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['routing_reason' => 'Explain the incident municipality selection or override.']);
        }

        return DB::transaction(function () use ($report, $agency, $admin, $reason, $pointKey, $expectedStatus): ReportReview {
            $report = PollutionReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_unless($report->status === $expectedStatus && in_array($report->status, ['pending', 'verified'], true), 409, 'Reload the report before selecting its incident municipality.');
            abort_unless(hash_equals(self::pointKey($report), $pointKey), 409, 'The incident location changed. Review the saved coordinates again.');
            $agency = Agency::whereKey($agency->id)->lockForUpdate()->firstOrFail();
            abort_unless($agency->active && self::exact($agency->municipality) !== null, 422, 'Select an active Laguna municipality recipient.');
            $referral = $report->referral()->first();
            if ($referral !== null) {
                abort_unless($referral->status === 'queued' && ! $referral->deliveryApproved() && $referral->attempts === 0
                    && $referral->agency_id === $agency->id && ($referral->recipient_snapshot['municipality'] ?? null) === $agency->municipality,
                    409, 'The existing referral snapshot cannot be rerouted. Review its delivery history.');
            }
            $previous = self::selection($report);
            if ($previous && $previous['agency_id'] === $agency->id && $previous['reason'] === trim($reason)) {
                return ReportReview::findOrFail($previous['review_id']);
            }
            $incident = self::incident($report);
            $review = new ReportReview;
            $review->forceFill(['administrator_id' => $admin->id, 'previous_status' => $report->status, 'new_status' => $report->status,
                'review_note' => json_encode(['kind' => 'incident_municipality_v1', 'agency_id' => $agency->id, 'municipality' => $agency->municipality,
                    'reason' => trim($reason), 'point_key' => $pointKey, 'boundary_status' => $incident['status'],
                    'suggested_municipality' => $incident['municipality']], JSON_THROW_ON_ERROR)]);
            $report->reviews()->save($review);

            return $review;
        });
    }
}
