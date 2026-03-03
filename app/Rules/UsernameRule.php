<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UsernameRule implements ValidationRule
{
    public function __construct(
        private readonly bool $allowRestrictedTerms = false
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $username = User::normalizeUsername((string) $value);
        if ($username === '') {
            $fail('Username may only contain letters, numbers, dots, underscores, and hyphens.');

            return;
        }

        if (mb_strlen($username) < 3 || mb_strlen($username) > 32) {
            $fail('Username must be between 3 and 32 characters.');

            return;
        }

        if (! $this->allowRestrictedTerms && User::containsRestrictedUsernameTerm($username)) {
            $fail('That username is not allowed.');
        }
    }
}
