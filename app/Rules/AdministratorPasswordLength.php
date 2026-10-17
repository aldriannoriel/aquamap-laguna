<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class AdministratorPasswordLength implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && config('hashing.driver') === 'bcrypt' && strlen($value) > 72) {
            $fail('The password must be at most 72 bytes with the configured password hasher.');
        }
    }
}
