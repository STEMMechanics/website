<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

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
     * @param  string $attribute
     * @param  mixed  $value
     * @return boolean
     */
    public function passes($attribute, $value)
    {
        // Get the list of storage disks from the filesystems configuration file
        $disks = array_keys(Config::get('filesystems.disks'));

        foreach ($disks as $disk) {
            if (Storage::disk($disk)->exists($value) === true) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The file name already exists.';
    }
}
