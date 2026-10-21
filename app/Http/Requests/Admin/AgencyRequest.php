<?php

namespace App\Http\Requests\Admin;

use App\Models\Agency;
use App\Models\WaterBody;
use App\Support\MunicipalityRouting;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class AgencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    protected function prepareForValidation(): void
    {
        $fields = [];
        foreach (['name', 'municipality', 'barangay', 'email'] as $key) {
            if (is_string($this->input($key))) {
                $fields[$key] = preg_replace('/\s+/u', ' ', trim($this->input($key)));
            }
        }
        $this->merge($fields);
        if (is_string($this->input('municipality'))) {
            $canonical = MunicipalityRouting::municipalities()->first(fn (string $value): bool => mb_strtolower($value) === mb_strtolower($this->input('municipality')));
            if ($canonical !== null) {
                $this->merge(['municipality' => $canonical]);
            }
            if ($this->input('name') === null || $this->input('name') === '') {
                $this->merge(['name' => MunicipalityRouting::label($this->input('municipality'))]);
            }
        }
        if (is_string($this->input('name')) && is_string($this->input('municipality')) && ($this->input('barangay') === null || is_string($this->input('barangay')))) {
            $this->merge(['identity_key' => Agency::identityKey($this->input('name'), $this->input('municipality'), $this->input('barangay'))]);
        }
    }

    public function rules(): array
    {
        $unique = Rule::unique('agencies', 'identity_key');
        if ($this->route('agency') instanceof Agency) {
            $unique->ignore($this->route('agency')->id);
        }

        $municipalityUnique = Rule::unique('agencies', 'municipality');
        if ($this->route('agency') instanceof Agency) {
            $municipalityUnique->ignore($this->route('agency')->id);
        }

        return [
            'name' => ['required', 'string', 'max:160'], 'municipality' => ['required', 'string', Rule::in(MunicipalityRouting::municipalities()->all()), $municipalityUnique], 'barangay' => ['prohibited'],
            'email' => ['required', 'email:rfc', 'max:254'], 'active' => ['required', 'boolean'], 'verification_status' => ['required', Rule::in(['provisional', 'verified'])],
            'notes' => ['nullable', 'string', 'max:2000'], 'identity_key' => ['required', $unique], 'water_body_ids' => ['nullable', 'array', 'max:100'],
            'water_body_ids.*' => ['required', 'integer', 'distinct', Rule::exists(WaterBody::class, 'id')->whereIn('category', array_keys(WaterBody::CATEGORIES))->where('site_type', 'water_body')->whereNull('spring_type')],
        ];
    }

    public function messages(): array
    {
        return ['identity_key.unique' => 'That municipality already has a recipient. Edit or enable the existing entry instead.',
            'municipality.unique' => 'That municipality already has a recipient. Edit or enable the existing entry instead.'];
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson()) {
            parent::failedValidation($validator);
        }
        $input = array_filter($this->only(['name', 'municipality', 'barangay', 'email', 'active', 'verification_status', 'notes']),
            fn (mixed $value): bool => is_scalar($value) || $value === null);
        $ids = $this->input('water_body_ids');
        $input['water_body_ids'] = is_array($ids) ? array_slice(array_values(array_filter($ids, is_scalar(...))), 0, 100) : [];
        throw new HttpResponseException(redirect()->back()->withErrors($validator)->withInput($input));
    }
}
