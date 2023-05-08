<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class RequiredIfAny implements Rule
{
    /**
     * The table name to compare.
     *
     * @var string
     */
    protected $table;

    /**
     * The column name to compare.
     *
     * @var string[]
     */
    protected $columns;


    /**
     * Create a new rule instance.
     *
     * @param string $table      The table name to compare.
     * @param string ...$columns The column name(s) to compare.
     * @return void
     */
    public function __construct(string $table, string ...$columns)
    {
        $this->table = $table;
        $this->columns = $columns;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  mixed $attribute Not used.
     * @param  mixed $value     The value to compare.
     * @return boolean
     */
    public function passes(mixed $attribute, mixed $value)
    {
        foreach ($this->columns as $column) {
            $result = DB::table($this->table)
                ->where($column, '!=', '')
                ->where($column, '!=', null)
                ->where($attribute, '=', '')
                ->exists();

            if ($result !== null) {
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
        return 'The :attribute field is required if any of the following fields are not empty: ' . implode(', ', $this->columns);
    }
}
