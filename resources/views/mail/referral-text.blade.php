AquaMap Laguna
{{ $details['test'] ? 'Delivery test' : 'Environmental referral' }}
Reference: {{ $reference }}

@if($details['test'])
This is an explicit administrator-triggered email delivery test to aquamaplaguna@gmail.com.
No private report or supporting photo is included.
Please locate this reference in the inbox before confirming receipt in AquaMap.
@else
Report reference: {{ $details['report_reference'] }}
Water body: {{ $details['water_body'] }}
@if(array_key_exists('incident_municipality', $details))
Water-body recorded municipality: {{ $details['municipality'] }}
Incident municipality (admin selected): {{ $details['incident_municipality'] ?: 'Not selected / not verified' }}
@else
Municipality / locality: {{ $details['municipality'] }}
@endif
Concern: {{ $details['concern'] }}
Observation date: {{ $details['observed_on'] ?: 'Not provided' }}
Submitted: {{ $details['submitted_at'] }}

Community-submitted description:
{{ $details['description'] }}

@if($details['latitude'] !== null && $details['longitude'] !== null)
Reported latitude: {{ $details['latitude'] }}
Reported longitude: {{ $details['longitude'] }}
Map: https://www.google.com/maps/search/?api=1&query={{ rawurlencode($details['latitude'].','.$details['longitude']) }}
@if($details['location_accuracy_m'] !== null)
Device-reported accuracy: {{ $details['location_accuracy_m'] }} metres (not surveyed accuracy).
@endif
@else
No problem-location coordinates were provided. No water-body reference point has been substituted.
@endif
@if($details['landmark'])
Nearby landmark / barangay as supplied by the reporter: {{ $details['landmark'] }}
@endif

This observation is community-submitted and is not an independently verified environmental violation. Coordinates and locality details are unverified reporter selections. Supporting photographs, when approved, are re-encoded and attached privately.
@endif

Transport acceptance or inbox receipt does not establish that an agency read, investigated or acted on a report.
