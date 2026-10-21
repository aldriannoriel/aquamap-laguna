<?php

namespace App\Http\Requests\Admin;

use App\Models\PollutionReport;
use App\Models\WaterBody;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportFilterRequest extends FormRequest
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
            'queue' => ['nullable', Rule::in(['all', 'action', 'processed'])],
            'q' => ['nullable', 'string', 'max:200'],
            'lake' => ['nullable', 'integer', Rule::exists(WaterBody::class, 'id')],
            'type' => ['nullable', 'string', Rule::in(array_keys(PollutionReport::TYPES))],
            'status' => ['nullable', 'string', Rule::in(array_keys(PollutionReport::STATUSES))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('from'), ['after_or_equal:from'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    protected $redirectRoute = 'admin.reports.index';
}
