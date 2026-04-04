<?php

namespace App\Rules;

use App\Support\SafeRichContent;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class RichHtmlNotEmpty implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('İçerik metin olmalıdır.');

            return;
        }

        if (SafeRichContent::isEffectivelyEmpty($value)) {
            $fail('İçerik boş olamaz.');
        }
    }
}
