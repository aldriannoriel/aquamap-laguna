<?php

namespace App\Http\Requests\Admin;

use App\Models\PollutionReport;
use App\Rules\SupportingEvidenceFile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('access-admin') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(['verified', 'rejected', 'under_review', 'in_progress', 'resolved', 'closed', 'municipality_update'])],
            'expected_status' => ['required', 'string', Rule::in(array_keys(PollutionReport::STATUSES))],
            'review_note' => [Rule::requiredIf(fn (): bool => $this->input('status') === 'municipality_update' && ! $this->hasFile('supporting_evidence')), 'nullable', 'string', 'max:2000'],
            'rejection_reason' => ['exclude_unless:status,rejected', 'required', 'string', 'max:2000'],
            'supporting_evidence' => [Rule::prohibitedIf(fn (): bool => ! in_array($this->input('status'), ['resolved', 'municipality_update', 'under_review', 'in_progress'], true)), 'required_if:status,resolved', 'nullable', 'array', 'min:1', 'max:5'],
            'supporting_evidence.*' => ['bail', 'required', 'file', 'max:5120', new SupportingEvidenceFile],
            'resolution_confirmed' => ['exclude_unless:status,resolved', 'required', 'accepted'],
            'closure_reason' => ['exclude_unless:status,closed', 'required', 'string', 'max:2000'],
            'reopen_reason' => ['exclude_unless:expected_status,closed', 'required', 'string', 'max:2000'],
            'follow_up_attempts' => ['exclude_unless:status,closed', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['rejection_reason.required' => 'Explain why this report is being rejected.',
            'review_note.required' => 'Add remarks or supporting evidence for the municipality update.',
            'supporting_evidence.required_if' => 'Upload at least one supporting evidence file before confirming resolution.',
            'resolution_confirmed.accepted' => 'Confirm that the uploaded evidence supports resolving this concern.',
            'closure_reason.required' => 'Explain why this forwarded report is being closed without resolution.',
            'reopen_reason.required' => 'Explain the new information supporting reopening this closed case.'];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.reports.show', $this->route('pollutionReport'));
    }
}
