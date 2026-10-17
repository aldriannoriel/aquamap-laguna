<?php

namespace App\Http\Requests;

use App\Models\PollutionReport;
use App\Models\WaterBody;
use App\Rules\ReportFormToken;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StorePollutionReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'website' => ['nullable', 'string', 'max:2048', 'prohibited'],
            'report_form_token' => ['bail', 'required', 'string', 'max:2048', new ReportFormToken($this)],
            'water_body_id' => ['bail', 'required', 'integer', Rule::exists(WaterBody::class, 'id')],
            'pollution_type' => ['required', 'string', Rule::in(array_keys(PollutionReport::TYPES))],
            'description' => ['required', 'string', 'max:5000'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'photo' => ['bail', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:5120'],
            'reporter_name' => ['nullable', 'string', 'max:255'],
            'reporter_email' => ['nullable', 'string', 'email', 'max:254'],
        ];
    }

    public function messages(): array
    {
        return [
            'website.prohibited' => 'This submission could not be verified. Please review your entries below and try again.',
            'report_form_token.required' => 'Please open the reporting form before submitting. Your entries have been kept.',
            'water_body_id.required' => 'Please choose a lake.',
            'water_body_id.exists' => 'Please choose an existing lake.',
            'pollution_type.required' => 'Please choose an observation type.',
            'pollution_type.in' => 'Please choose one of the listed observation types.',
            'description.required' => 'Please describe what you observed.',
            'photo.max' => 'The photo must be no larger than 5 MB.',
            'photo.uploaded' => 'The photo could not be uploaded. Please choose a JPEG, PNG or WebP up to 5 MB.',
        ];
    }

    public function safeOldInput(): array
    {
        $input = array_filter($this->only([
            'water_body_id', 'pollution_type', 'description', 'latitude', 'longitude', 'reporter_name', 'reporter_email',
        ]), fn (mixed $value): bool => is_scalar($value) || $value === null);
        $token = $this->input('report_form_token');
        if (is_string($token) && strlen($token) <= 2048) {
            $input['report_form_token'] = $token;
        }

        return $input;
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson()) {
            parent::failedValidation($validator);
        }

        throw new HttpResponseException(redirect()->route('reports.create')
            ->withErrors($validator)->withInput($this->safeOldInput())
            ->header('Cache-Control', 'no-store, private'));
    }
}
