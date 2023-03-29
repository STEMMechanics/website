<?php

namespace App\Conductors;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class Conductor
{
    /**
     * The Conductors Model class.
     *
     * @var string|null
     */
    protected $class = null;

    /**
     * The default sorting fields of a collection. Can be an array. Supports - and + prefixes.
     *
     * @var string|array
     */
    protected $sort = "id";

    /**
     * The default collection size limit per request.
     *
     * @var integer
     */
    protected $limit = 50;

    /**
     * The maximum collection size limit per request.
     *
     * @var integer
     */
    protected $maxLimit = 100;

    /**
     * The default includes to include in a request.
     *
     * @var array
     */
    protected $includes = [];

    /**
     * The conductor collection.
     *
     * @var Collection
     */
    private $collection = null;

    /**
     * The conductor query.
     *
     * @var Builder
     */
    private $query = null;


    /**
     * Split a string on commas, keeping quotes intact.
     *
     * @param string $string The string to split.
     * @return array The split string.
     */
    private function splitString(string $string)
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

    /**
     * Filter a field with a specific Builder object
     *
     * @param Builder $builder The builder object to append.
     * @param string  $field   The field name.
     * @param mixed   $value   The value or array of values to filter.
     * @param string  $boolean The comparision boolean (AND or OR).
     * @return void
     */
    private function filterFieldWithBuilder(Builder $builder, string $field, mixed $value, string $boolean = 'AND')
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
        $builder->where(function ($query) use ($field, $values) {
            foreach ($values as $value) {
                $value = trim($value);
                $prefix = '';

                // Check if value has a prefix and remove it if it's a number
                if (preg_match('/^(!?=|[<>]=?|<>|!)([^=!<>].*)$/', $value, $matches) > 0) {
                    $prefix = $matches[1];
                    $value = $matches[2];
                }

                // Apply the prefix to the query if the value is a number
                switch ($prefix) {
                    case '=':
                        $query->orWhere($field, '=', $value);
                        break;
                    case '!':
                        $query->orWhere($field, 'NOT LIKE', "%$value%");
                        break;
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
                        $query->orWhere($field, '!=', $value);
                        break;
                    case '<>':
                        $seperatorPos = strpos($value, '|');
                        if ($seperatorPos !== false) {
                            $query->orWhereBetween($field, [substr($value, 0, $seperatorPos), substr($value, ($seperatorPos + 1))]);
                        } else {
                            $query->orWhere($field, '!=', $value);
                        }
                        break;
                    default:
                        $query->orWhere($field, 'LIKE', "%$value%");
                        break;
                }//end switch
            }//end foreach
        }, null, null, $boolean);
    }

    /**
     * Run the conductor on a Request to generate a collection and total.
     *
     * @param Request $request The request data.
     * @return array The processed and transformed collection | the total rows found.
     */
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

        // Filter request
        $fields = $conductor->fields(new $conductor->class());
        if (is_array($fields) === false) {
            $fields = [];
        }

        $params = $request->all();
        $filterFields = array_intersect_key($params, array_flip($fields));
        $conductor->filter($filterFields);
        if ($request->has('filter') === true) {
            $conductor->filterRaw($request->input('filter', ''), $fields);
        }

        // After Scope query
        $conductor->query->where(function ($query) use ($conductor) {
            $conductor->scope($query);
        });

        // Sort request
        $conductor->sort($request->input('sort', $conductor->sort));

        // Get total
        $total = $conductor->count();

        // Paginate
        $conductor->paginate($request->input('page', 1), $request->input('limit', -1));

        // Limit fields
        $limitFields = explode(',', $request->input('fields'));
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

    /**
     * Run the conductor on a collection with the data stored in a Request.
     *
     * @param Request    $request    The request data.
     * @param Collection $collection The collection.
     * @return array The processed and transformed model data.
     */
    final public static function collection(Request $request, Collection $collection)
    {
        $conductor_class = get_called_class();
        $conductor = new $conductor_class();

        $transformedCollection = collect();

        foreach ($collection as $item) {
            if ($conductor->viewable($item)) {
                $transformedCollection->push($conductor->transform($item));
            }
        }

        return $transformedCollection;
    }

    /**
     * Run the conductor on a Model with the data stored in a Request.
     *
     * @param Request $request The request data.
     * @param Model   $model   The model.
     * @return array The processed and transformed model data.
     */
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

    /**
     * Filter a single field in the conductor collection.
     *
     * @param string $field   The field name.
     * @param mixed  $value   The value or array of values to filter.
     * @param string $boolean The comparision boolean (AND or OR).
     * @return void
     */
    final public function filterField(string $field, mixed $value, string $boolean = 'AND')
    {
        $this->filterFieldWithBuilder($this->query, $field, $value, $boolean);
    }

    /**
     * Get or Set the conductor collection.
     *
     * @param Collection $collection If not null, use the passed collection.
     * @return Collection The current conductor collection.
     */
    // final public function collection(Collection $collection = null)
    // {
    //     if ($collection !== null) {
    //         $this->collection = $collection;
    //     }

    //     return $this->collection;
    // }

    /**
     * Return the current conductor collection count.
     *
     * @return integer The current collection count.
     */
    final public function count()
    {
        if ($this->query !== null) {
            return $this->query->count();
        }

        return 0;
    }

    /**
     * Sort the conductor collection.
     *
     * @param mixed $fields A field name or array of field names to sort. Supports a prefix of + or - to change direction.
     * @return void
     */
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

    /**
     * Filter the conductor collection based on an array of field => value.
     *
     * @param array $filters An array of field => value to filter.
     * @return void
     */
    final public function filter(array $filters)
    {
        foreach ($filters as $param => $value) {
            $this->filterField($param, $value);
        }
    }

    /**
     * Paginate the conductor collection.
     *
     * @param integer $page  The current page to return.
     * @param integer $limit The limit of items to include or use default.
     * @return void
     */
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

    /**
     * Append a list of includes to the model.
     *
     * @param Model $model    The model to append.
     * @param array $includes The list of includes to include.
     * @return void
     */
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

    /**
     * Limit the returned fields in the conductor collection.
     *
     * @param array $fields An array of field names.
     * @return void
     */
    final public function limitFields(array $fields)
    {
        if (empty($fields) !== true) {
            $this->query->select($fields);
        }
    }

    /**
     * Filter the conductor collection using raw data.
     *
     * @param string     $filterString The raw filter string to parse.
     * @param array|null $limitFields  The fields to ignore in the filter string.
     * @return void
     */
    final public function filterRaw(string $filterString, array|null $limitFields = null)
    {
        if (is_array($limitFields) === false || empty($limitFields) === true) {
            $limitFields = null;
        } else {
            $limitFields = array_map('strtolower', $limitFields);
        }

        $tokens = preg_split('/([()]|,OR,|,AND,|,)/', $filterString, -1, (PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE));
        $glued = [];
        $glueToken = '';
        foreach ($tokens as $item) {
            if ($glueToken === '') {
                if (preg_match('/(?<!\\\\)[\'"]/', $item, $matches, PREG_OFFSET_CAPTURE) === 1) {
                    $glueToken = $matches[0][0];
                    $item = substr($item, 0, $matches[0][1]) . substr($item, ($matches[0][1] + 1));
                    $item = str_replace("\\$glueToken", $glueToken, $item);
                }

                $glued[] = $item;
            } else {
                // search for ending glue token
                if (preg_match('/(?<!\\\\)' . $glueToken . '/', $item, $matches, PREG_OFFSET_CAPTURE) === 1) {
                    $item = substr($item, 0, $matches[0][1]) . substr($item, ($matches[0][1] + 1));
                    $glueToken = '';
                }

                $item = str_replace("\\$glueToken", $glueToken, $item);

                $glued[(count($glued) - 1)] .= $item;
            }
        }//end foreach
        $tokens = $glued;

        $parseTokens = function ($tokenList, $level, $index, $groupBoolean = null) use ($limitFields, &$parseTokens) {
            $tokenGroup = [];
            $firstToken = false;
            $tokenGroupBoolean = 'AND';

            if ($groupBoolean !== null) {
                $firstToken = true;
                $tokenGroupBoolean = $groupBoolean;
            }

            while ($index < count($tokenList)) {
                $token = $tokenList[$index];

                ++$index;
                if ($token === '(') {
                    // next group
                    $nextGroupBoolean = null;
                    if (count($tokenGroup) > 0 && strlen($tokenGroup[(count($tokenGroup) - 1)]['field']) === 0) {
                        $nextGroupBoolean = $tokenGroup[(count($tokenGroup) - 1)]['boolean'];
                        unset($tokenGroup[(count($tokenGroup) - 1)]);
                    }

                    $index = $parseTokens($tokenList, $level + 1, $index, $nextGroupBoolean);
                } elseif ($token === ')') {
                    // end group
                    break;
                } elseif (in_array(strtoupper($token), [',AND,', ',OR,']) === true) {
                    // update boolean
                    $boolean = trim(strtoupper($token), ',');

                    if ($firstToken === false && $level > 0) {
                        $tokenGroupBoolean = $boolean;
                    } else {
                        $firstToken = true;
                        $tokenGroup[] = [
                            'field' => '',
                            'value' => '',
                            'boolean' => $boolean
                        ];
                    }
                } elseif (strpos($token, ':') !== false) {
                    // set tokenGroup
                    $firstToken = true;
                    $field = substr($token, 0, strpos($token, ':'));
                    $value = substr($token, (strpos($token, ':') + 1));
                    $boolean = 'AND';

                    if (count($tokenGroup) > 0 && strlen($tokenGroup[(count($tokenGroup) - 1)]['field']) === 0) {
                        $tokenGroup[(count($tokenGroup) - 1)]['field'] = $field;
                        $tokenGroup[(count($tokenGroup) - 1)]['value'] = $value;
                        $boolean = $tokenGroup[(count($tokenGroup) - 1)]['boolean'];
                    } else {
                        $tokenGroup[] = [
                            'field' => $field,
                            'value' => $value,
                            'boolean' => 'AND'
                        ];
                    }

                    if ($limitFields === null || in_array(strtolower($field), $limitFields) !== true) {
                        unset($tokenGroup[(count($tokenGroup) - 1)]);
                    }

                    if ($level === 0) {
                        $this->filterFieldWithBuilder($this->query, $field, $value, $boolean);
                    }
                }//end if
            }//end while

            if ($level > 0) {
                if ($tokenGroupBoolean === 'OR') {
                    $this->query->orWhere(function ($query) use ($tokenGroup) {
                        foreach ($tokenGroup as $tokenItem) {
                            if (strlen($tokenItem['field']) > 0) {
                                $this->filterFieldWithBuilder($query, $tokenItem['field'], $tokenItem['value'], $tokenItem['boolean']);
                            }
                        }
                    });
                } else {
                    $this->query->where(function ($query) use ($tokenGroup) {
                        foreach ($tokenGroup as $tokenItem) {
                            if (strlen($tokenItem['field']) > 0) {
                                $this->filterFieldWithBuilder($query, $tokenItem['field'], $tokenItem['value'], $tokenItem['boolean']);
                            }
                        }
                    });
                }
            }//end if

            return $index;
        };

        $parseTokens($tokens, 0, 0);
    }

    /**
     * Run a scope query on the collection before anything else.
     *
     * @param Builder $builder The builder in use.
     * @return void
     */
    public function scope(Builder $builder)
    {
    }

    /**
     * Return an array of model fields visible to the current user.
     *
     * @param Model $model The model in question.
     * @return array The array of field names.
     */
    public function fields(Model $model)
    {
        $visibleFields = $model->getVisible();
        if (empty($visibleFields) === true) {
            $visibleFields = $model->getConnection()
                ->getSchemaBuilder()
                ->getColumnListing($model->getTable());
        }

        $appends = $model->getAppends();
        if(is_array($appends) === true) {
            $visibleFields = array_merge($visibleFields, $appends);
        }

        return $visibleFields;
    }

    /**
     * Transform the passed Model to an array
     *
     * @param Model $model The model to transform.
     * @return array The transformed model.
     */
    public function transform(Model $model)
    {
        $result = $model->toArray();

        $fields = $this->fields($model);
        if(is_array($fields) === true) {
            $result = array_intersect_key($result, array_flip($fields));
        }

        return $result;
    }

    /**
     * Is the passed model viewable by the current user?
     *
     * @param Model $model The model in question.
     * @return boolean Is the model viewable.
     */
    public static function viewable(Model $model)
    {
        return true;
    }

    /**
     * Is the model creatable by the current user?
     *
     * @return boolean Is the model creatable.
     */
    public static function creatable()
    {
        return true;
    }

    /**
     * Is the passed model updateable by the current user?
     *
     * @param Model $model The model in question.
     * @return boolean Is the model updateable.
     */
    public static function updatable(Model $model)
    {
        return true;
    }

    /**
     * Is the passed model destroyable by the current user?
     *
     * @param Model $model The model in question.
     * @return boolean Is the model destroyable.
     */
    public static function destroyable(Model $model)
    {
        return true;
    }
}
