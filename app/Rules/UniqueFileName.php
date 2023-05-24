<?php

namespace App\Rules;

use App\Models\Media;
use Illuminate\Contracts\Validation\Rule;

class UniqueFileName implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  mixed  $value
     */
    public function passes(string $attribute, $value): bool
    {
        return (Media::fileExists($value) === false);
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'The file name already exists.';
    }
}
