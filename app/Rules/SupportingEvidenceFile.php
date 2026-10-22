<?php

namespace App\Rules;

use App\Support\ReportEvidenceStorage;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;

class SupportingEvidenceFile implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            if (! $value instanceof UploadedFile) {
                throw new \RuntimeException('A file is required.');
            }
            ReportEvidenceStorage::inspect($value);
        } catch (\Throwable) {
            $fail('Upload a valid JPG, JPEG, PNG, WebP, PDF, DOC or DOCX file. Active, malformed or mismatched files are not accepted.');
        }
    }
}
