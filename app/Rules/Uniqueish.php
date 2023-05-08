<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class Uniqueish implements Rule
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
     * @var string|null
     */
    protected $column;

    /**
     * The ID of the record to be ignored.
     *
     * @var integer|null
     */
    protected $ignoreId;


    /**
     * Create a new rule instance.
     *
     * @param string $table  The table name to compare.
     * @param string $column The column name to compare.
     * @return void
     */
    public function __construct(string $table, string $column = null)
    {
        $this->table = $table;
        $this->column = $column;
    }

    /**
     * Set the ID of the record to be ignored.
     *
     * @param  integer $id
     * @return $this
     */
    public function ignore($id)
    {
        $this->ignoreId = $id;
        return $this;
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
        $columnName = ($this->column ?? $attribute);
        $similarValue = preg_replace('/[^A-Za-z]/', '', strtolower($value));

        $query = DB::table($this->table)
        ->whereRaw('LOWER(REGEXP_REPLACE(' . $columnName . ', \'[^A-Za-z]\', \'\')) = ?', [$similarValue]);

        if ($this->ignoreId !== null) {
            $query->where('id', '<>', $this->ignoreId);
        }

        $result = $query->first();

        return $result === null;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The :attribute is similar to an existing value in the database.';
    }
}
