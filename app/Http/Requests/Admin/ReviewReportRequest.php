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
            'status' => ['required', 'string', Rule::in(['verified', 'rejected', 'resolved'])],
            'expected_status' => ['required', 'string', Rule::in(array_keys(PollutionReport::STATUSES))],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.reports.show', $this->route('pollutionReport'));
    }
}
