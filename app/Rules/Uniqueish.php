<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;
use PDO;

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
     * @param  integer $id The ID to ignore.
     * @return $this
     */
    public function ignore(int $id)
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

        try {
            $query = DB::table($this->table)
            ->where($columnName, 'like', '%' . $similarValue . '%');

            if ($this->ignoreId !== null) {
                $query->where('id', '<>', $this->ignoreId);
            }

            $query->whereRaw('LOWER(REGEXP_REPLACE(' . $columnName . ', \'[^A-Za-z0-9]\', \'\')) = ?', [$similarValue]);
            $result = $query->first();
        } catch (\Illuminate\Database\QueryException $e) {
            // Fall back to performing the regex replace in PHP
            // $results = $query->get();
            $query = DB::table($this->table);
            $results = $query->get();

            foreach ($results as $result) {
                $resultValue = preg_replace('/[^A-Za-z0-9]/', '', strtolower($result->{$columnName}));
                if ($resultValue === $similarValue) {
                    return false; // Value already exists in the table
                }
            }
            return true; // Value does not exist in the table
        }//end try

        return $result === null;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The :attribute is similar to one that already exists. Please choose another.';
    }
}
