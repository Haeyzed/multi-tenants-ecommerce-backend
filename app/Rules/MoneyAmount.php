<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a money amount as a non-scientific decimal with at most two places.
 */
class MoneyAmount implements ValidationRule
{
    public function __construct(
        private readonly bool $allowZero = false,
        private readonly bool $allowNull = false,
    ) {}

    /**
     * @param  Closure(string, ?string=): void  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            if (! $this->allowNull) {
                $fail('The :attribute field is required.');
            }

            return;
        }

        if (is_bool($value) || is_array($value) || is_object($value)) {
            $fail('The :attribute must be a valid money amount.');

            return;
        }

        $string = is_string($value) ? trim($value) : (string) $value;

        if ($string === '' || ! preg_match('/^\d+(\.\d{1,2})?$/', $string)) {
            $fail('The :attribute must be a valid money amount with up to 2 decimal places.');

            return;
        }

        if (! $this->allowZero && bccomp($string, '0', 2) <= 0) {
            $fail('The :attribute must be greater than zero.');
        }
    }
}
