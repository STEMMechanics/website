<?php

namespace App\Conductors;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class Conductor
{
    protected $class = null;
    protected $sort = "id";
    protected $limit = 50;
    protected $maxLimit = 100;
    protected $includes = [];

    private $collection = null;
    private $query = null;


    private function splitString($string)
    {
        $parts = [];
        $start = 0;
        $len = strlen($string);

        while ($start < $len) {
            $commaPos = strpos($string, ',', $start);
            $singlePos = strpos($string, '\'', $start);
            $doublePos = strpos($string, '"', $start);

            // Find the smallest position that is not false
            $minPos = false;
            if ($commaPos !== false) {
                $minPos = $commaPos;
            }
            if ($singlePos !== false && ($minPos === false || $singlePos < $minPos)) {
                $minPos = $singlePos;
            }
            if ($doublePos !== false && ($minPos === false || $doublePos < $minPos)) {
                $minPos = $doublePos;
            }

            if ($minPos === false) {
                // No more commas, single quotes, or double quotes found
                $part = substr($string, $start);
                $parts[] = trim($part);
                break;
            } else {
                // Add the current part to the parts array
                $part = substr($string, $start, ($minPos - $start));
                $parts[] = trim($part);

                // Update the start position to the next character after the comma, single quote, or double quote
                if ($string[$minPos] === ',') {
                    $start = ($minPos + 1);
                } else {
                    $quoteChar = $string[$minPos];
                    $endPos = strpos($string, $quoteChar, ($minPos + 1));
                    if ($endPos === false) {
                        $part = substr($string, ($minPos + 1));
                        $parts[] = trim($part);
                        break;
                    } else {
                        $part = substr($string, ($minPos + 1), ($endPos - $minPos - 1));
                        $parts[] = trim($part);
                        $start = ($endPos + 1);
                    }
                }
            }//end if
        }//end while

        return array_filter($parts, function ($value) {
            return $value !== '';
        });
    }

    final public static function request(Request $request)
    {
        $conductor_class = get_called_class();
        $conductor = new $conductor_class();

        $total = 0;

        try {
            $conductor->query = $conductor->class::query();
        } catch (\Throwable $e) {
            throw new \Exception('Failed to create query builder instance for ' . $conductor->class . '.', 0, $e);
        }

        // Scope query
        $conductor->scope($conductor->query);

        // Filter request
        $fields = $conductor->fields(new $conductor->class());
        if (is_array($fields) === false) {
            $fields = [];
        }

        $params = $request->all();
        $filterFields = array_intersect_key($params, array_flip($fields));
        $conductor->filter($filterFields);

        // Sort request
        $conductor->sort($request->input('sort', $conductor->sort));

        // Get total
        $total = $conductor->count();

        // Paginate
        $conductor->paginate($request->input('page', 1), $request->input('limit', -1));

        // Limit fields
        $limitFields = $request->input('fields');
        if ($limitFields === null) {
            $limitFields = $fields;
        } else {
            $limitFields = array_intersect($limitFields, $fields);
        }
        $conductor->limitFields($limitFields);

        $conductor->collection = $conductor->query->get();

        // Transform and Includes
        $includes = $conductor->includes;
        if ($request->has('includes') === true) {
            $includes = explode(',', $request->input('includes'));
        }

        $conductor->collection = $conductor->collection->map(function ($model) use ($conductor, $includes) {
            $conductor->includes($model, $includes);
            $model = $conductor->transform($model);

            return $model;
        });

        return [$conductor->collection, $total];
    }

    final public static function model(Request $request, Model $model)
    {
        $conductor_class = get_called_class();
        $conductor = new $conductor_class();

        $fields = $conductor->fields(new $conductor->class());

        // Limit fields
        $limitFields = $fields;
        if ($request !== null && $request->has('fields') === true) {
            $requestFields = $request->input('fields');
            if ($requestFields !== null) {
                $limitFields = array_intersect(explode(',', $requestFields), $fields);
            }
        }

        if (empty($limitFields) === false) {
            $modelSubset = new $conductor->class();
            foreach ($limitFields as $field) {
                $modelSubset->setAttribute($field, $model->$field);
            }
            $model = $modelSubset;
        }

        // Includes
        $includes = $conductor->includes;
        if ($request !== null && $request->has('includes') === true) {
            $includes = explode(',', $request->input('includes', ''));
        }
        $conductor->includes($model, $includes);

        // Transform
        $model = $conductor->transform($model);

        return $model;
    }

    final public function filterField(Builder $builder, string $field, mixed $value)
    {
        $values = [];

        // Split by comma, but respect quotation marks
        if (is_string($value) === true) {
            $values = $this->splitString($value);
        } elseif (is_array($value) === true) {
            $values = $value;
        } else {
            throw new \InvalidArgumentException('Expected string or array, got ' . gettype($value));
        }

        // Add each AND check to the query
        $this->query->where(function ($query) use ($field, $values) {
            foreach ($values as $value) {
                $value = trim($value);

                // Check if value has a prefix and remove it if it's a number
                if (preg_match('/^([<>!=]=?)(\d+\.?\d*)$/', $value, $matches) > 0) {
                    $prefix = $matches[1];
                    $value = $matches[2];
                } else {
                    $prefix = '';
                }

                // If the value starts with '=', exact match

                if (strpos($value, '=') === 0) {
                    $query->orWhere($field, '=', substr($value, 1));
                } elseif (strpos($value, '!=') === 0) {
                    $query->orWhere($field, '<>', substr($value, 2));
                } elseif (strpos($value, '!') === 0) {
                    $query->orWhere($field, 'NOT LIKE', '%' . substr($value, 1) . '%');
                } else {
                    $query->orWhere($field, 'LIKE', "%$value%");
                }

                // Apply the prefix to the query if the value is a number
                if (is_numeric($value) === true) {
                    switch ($prefix) {
                        case '>':
                            $query->orWhere($field, '>', $value);
                            break;
                        case '<':
                            $query->orWhere($field, '<', $value);
                            break;
                        case '>=':
                            $query->orWhere($field, '>=', $value);
                            break;
                        case '<=':
                            $query->orWhere($field, '<=', $value);
                            break;
                        case '!=':
                        case '<>':
                            $query->orWhere($field, '<>', $value);
                            break;
                    }
                }
            }//end foreach
        });
    }

    final public function collection(Collection $collection = null)
    {
        if ($collection !== null) {
            $this->collection = $collection;
        }

        return $this->collection;
    }

    final public function count()
    {
        if ($this->query !== null) {
            return $this->query->count();
        }

        return 0;
    }

    final public function sort(mixed $fields = null)
    {
        if (is_string($fields) === true) {
            $fields = explode(',', $fields);
        } elseif ($fields === null) {
            $fields = $this->sort;
        }

        if (is_array($fields) === true) {
            foreach ($fields as $orderByField) {
                $direction = 'asc';
                $directionChar = substr($orderByField, 0, 1);

                if (in_array($directionChar, ['-', '+']) === true) {
                    $orderByField = substr($orderByField, 1);
                    if ($directionChar === '-') {
                        $direction = 'desc';
                    }
                }

                $this->query->orderBy(trim($orderByField), $direction);
            }
        } else {
            throw new \InvalidArgumentException('Expected string or array, got ' . gettype($fields));
        }
    }

    final public function filter(array $filters)
    {
        foreach ($filters as $param => $value) {
            $this->filterField($this->query, $param, $value);
        }
    }

    final public function paginate(int $page = 1, int $limit = -1)
    {
        // Limit
        if ($limit < 1) {
            $limit = $this->limit;
        } else {
            $limit = min($limit, $this->maxLimit);
        }
        $this->query->limit($limit);

        // Page
        if ($page < 1) {
            $page = 1;
        }
        $this->query->offset(($page - 1) * $limit);
    }

    final public function includes(Model $model, array $includes)
    {
        foreach ($includes as $include) {
            $includeMethodName = 'include' . Str::studly($include);
            if (method_exists($this, $includeMethodName) === true) {
                $attributeName = Str::snake($include);
                $attributeValue = $this->{$includeMethodName}($model);
                if ($attributeValue !== null) {
                    $model->$attributeName = $this->{$includeMethodName}($model);
                }
            }
        }
    }

    final public function limitFields(array $fields)
    {
        if (empty($fields) !== true) {
            $this->query->select($fields);
        }
    }

    /** overrides */
    public function scope(Builder $builder)
    {
    }

    public function fields(Model $model)
    {
        $visibleFields = $model->getVisible();
        if (empty($visibleFields) === true) {
            $tableColumns = $model->getConnection()
                ->getSchemaBuilder()
                ->getColumnListing($model->getTable());
            return $tableColumns;
        }

        return $visibleFields;
    }

    public function transform(Model $model)
    {
        return $model->toArray();
    }

    public static function viewable(Model $model)
    {
        return true;
    }

    public static function creatable()
    {
        return true;
    }

    public static function updatable(Model $model)
    {
        return true;
    }

    public static function destroyable(Model $model)
    {
        return true;
    }
}
