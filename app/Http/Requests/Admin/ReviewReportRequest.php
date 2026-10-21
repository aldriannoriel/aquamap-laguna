<?php

namespace App\Http\Requests\Admin;

use App\Models\PollutionReport;
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
            'status' => ['required', 'string', Rule::in(['verified', 'rejected', 'under_review', 'in_progress', 'resolved', 'closed'])],
            'expected_status' => ['required', 'string', Rule::in(array_keys(PollutionReport::STATUSES))],
            'review_note' => ['required_if:status,resolved', 'nullable', 'string', 'max:2000'],
            'rejection_reason' => ['exclude_unless:status,rejected', 'required', 'string', 'max:2000'],
            'resolution_basis' => ['exclude_unless:status,resolved', 'required', 'string', 'max:4000'],
            'closure_reason' => ['exclude_unless:status,closed', 'required', 'string', 'max:2000'],
            'reopen_reason' => ['exclude_unless:expected_status,closed', 'required', 'string', 'max:2000'],
            'follow_up_attempts' => ['exclude_unless:status,closed', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['rejection_reason.required' => 'Explain why this report is being rejected.',
            'review_note.required_if' => 'Resolution remarks are required.',
            'resolution_basis.required' => 'Provide a documented resolution basis or supporting evidence reference.',
            'closure_reason.required' => 'Explain why this forwarded report is being closed without resolution.',
            'reopen_reason.required' => 'Explain the new information supporting reopening this closed case.'];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.reports.show', $this->route('pollutionReport'));
    }
}
