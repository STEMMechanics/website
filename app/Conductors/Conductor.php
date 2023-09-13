<?php

namespace App\Conductors;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
     * The default filters to use in a request.
     *
     * @var array
     */
    protected $defaultFilters = [];

    /**
     * The conductor collection.
     *
     * @var Collection
     */
    protected $collection = null;

    /**
     * The collection filter to apply.
     *
     * @var array
     */
    protected $filterArray = [];

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
    private function splitString(string $string): array
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
     * Filter Collection based on the Request.
     *
     * @param Request    $request     The user request.
     * @param array|null $limitFields A list of fields to limit the filter request to.
     * @return void
     */
    private function filter(Request $request, array|null $limitFields = null): void
    {
        if (is_array($limitFields) === true && count($limitFields) === 0) {
            $limitFields = null;
        }

        $filterFields = $request->all();
        if ($limitFields !== null) {
            $filterFields = array_intersect_key($filterFields, array_flip($limitFields));
        }
        $filterFields += $this->defaultFilters;

        foreach ($filterFields as $field => $value) {
            if (
                is_array($limitFields) === false ||
                in_array(strtolower($field), array_map('strtolower', $limitFields)) !== false
            ) {
                $value = trim($value);
                $operator = '';
                $join = 'AND';

                // Check if value has a operator and remove it if it's a number
                if (preg_match('/^(!?=|[<>]=?|<>|!|\|)([^=!<>].*)*$/', $value, $matches) > 0) {
                    $operator = $matches[1];
                    $value = ($matches[2] ?? '');
                }

                if (strlen($value) === 0 && ($operator !== '==' && $operator !== '!=')) {
                    continue;
                }

                switch ($operator) {
                    case '=':
                        $operator = '==';
                        break;
                    case '!':
                        $operator = 'NOT LIKE';
                        $value = "%{$value}%";
                        break;
                    case '>':
                    case '<':
                    case '|':
                        $separatorPos = strpos($value, '|');
                        if ($separatorPos !== false) {
                            $operator = '==';
                            $valueList = explode('|', $value);
                            foreach($valueList as $valueItem) {
                                $this->appendFilter($field, $operator, $valueItem, 'OR');
                            }
                            continue 2;
                        }
                        break;
                    case '>=':
                    case '<=':
                    case '!=':
                        break;
                    case '<>':
                        $operator = '!=';
                        break;
                    default:
                        $operator = 'LIKE';
                        $value = "%{$value}%";
                        break;
                }//end switch

                $this->appendFilter($field, $operator, $value, $join);
            }//end if
        }//end foreach
        if ($request->has('filter') === true) {
            $this->appendFilterString($request->input('filter', ''), $limitFields);
        }

        $this->applyFilters();
    }

    /**
     * Apple the filter array to the collection.
     *
     * @return void
     */
    final public function applyFilters(): void
    {
        $parseFunc = function ($filterArray, $query) use (&$parseFunc) {
            $item = null;
            $result = null;
            $join = 'AND';

            $relationFilter = [];

            $buildWhereFunc = function ($query, $field, $operator, $value, $join) {
                if ($join === 'OR') {
                    if ($operator === '<>') {
                        $separatorPos = strpos($value, '|');
                        if ($separatorPos !== false) {
                            $query->orWhereBetween(
                                $field,
                                [substr($value, 0, $separatorPos), substr($value, ($separatorPos + 1))]
                            );
                        } else {
                            $query->orWhere($field, '!=', $value);
                        }
                    } else {
                        $query->orWhere($field, $operator, $value);
                    }
                } else {
                    if ($operator === '<>') {
                        $separatorPos = strpos($value, '|');
                        if ($separatorPos !== false) {
                            $query->whereBetween(
                                $field,
                                [substr($value, 0, $separatorPos), substr($value, ($separatorPos + 1))]
                            );
                        } else {
                            $query->where($field, '!=', $value);
                        }
                    } else {
                        $query->where($field, $operator, $value);
                    }
                }//end if
            };

            if (gettype($query) === 'array') {
                $item = $query;
            }

            foreach ($filterArray as $condition) {
                $currentResult = false;

                if (is_array($condition) === true) {
                    if (isset($condition[0]) === true && is_array($condition[0]) === true) {
                        if ($item !== null) {
                            $currentResult = $parseFunc($condition, $item);
                        } else {
                            if ($join === 'OR') {
                                $query->orWhere(function ($subQuery) use ($parseFunc, $condition) {
                                    $parseFunc($condition, $subQuery);
                                });
                            } else {
                                $query->where(function ($subQuery) use ($parseFunc, $condition) {
                                    $parseFunc($condition, $subQuery);
                                });
                            }
                        }
                    } else {
                        list($field, $operator, $value) = $condition;

                        if ($item !== null) {
                            if (array_key_exists($field, $item) === true) {
                                switch ($operator) {
                                    case '==':
                                        $currentResult = ($item[$field] == $value);
                                        break;
                                    case 'NOT LIKE':
                                        $currentResult = (stripos($item[$field], substr($value, 1, -1)) === false);
                                        break;
                                    case '>':
                                        $currentResult = ($item[$field] > $value);
                                        break;
                                    case '<':
                                        $currentResult = ($item[$field] < $value);
                                        break;
                                    case '>=':
                                        $currentResult = ($item[$field] >= $value);
                                        break;
                                    case '<=':
                                        $currentResult = ($item[$field] <= $value);
                                        break;
                                    case '!=':
                                        $currentResult = ($item[$field] != $value);
                                        break;
                                    case '<>':
                                        $separatorPos = strpos($value, '|');
                                        if ($separatorPos !== false) {
                                            $fieldInt = intval($item[$field]);
                                            $currentResult = (
                                                $fieldInt > intVal(
                                                    substr($value, 0, $separatorPos)
                                                ) && $fieldInt < intVal(substr($value, ($separatorPos + 1))));
                                        } else {
                                            $currentResult = ($item[$field] != $value);
                                        }
                                        break;
                                    case 'LIKE':
                                        $currentResult = (stripos($item[$field], substr($value, 1, -1)) !== false);
                                        break;
                                }//end switch
                            }//end if
                        } else {
                            if ($operator === '==') {
                                $operator = '=';
                            }

                            $relationSplit = strpos($field, '.');
                            if ($relationSplit !== false) {
                                $relation = substr($field, 0, $relationSplit);
                                $field = substr($field, ($relationSplit + 1));

                                if (method_exists($this->class, $relation) === true) {
                                    $relationFilter[$relation][] = [$field, $operator, $value, $join];
                                }
                            } else {
                                $buildWhereFunc($query, $field, $operator, $value, $join);
                            }
                        }//end if
                    }//end if

                    if ($item !== null) {
                        if ($result === null) {
                            $result = $currentResult;
                        } else {
                            if ($join === 'OR') {
                                $result = $result || $currentResult;
                            } else {
                                $result = $result && $currentResult;
                            }
                        }
                    }

                    $join = 'OR';
                } else {
                    $join = $condition;
                }//end if
            }//end foreach

            foreach ($relationFilter as $relation => $conditions) {
                $query->whereHas($relation, function ($subQuery) use ($buildWhereFunc, $conditions) {
                    foreach ($conditions as $condition) {
                        $buildWhereFunc($subQuery, $condition[0], $condition[1], $condition[2], $condition[3]);
                    }
                });
            }

            return $result;
        };

        $filterArray = $this->filterArray;
        if (count($filterArray) === 0) {
            $filterArray = $this->defaultFilters;
        }
        if (count($filterArray) !== 0) {
            if ($this->collection !== null) {
                $this->collection = $this->collection->filter(function ($item) use ($parseFunc) {
                    return $parseFunc($this->filterArray, $item);
                });
            } else {
                $parseFunc($this->filterArray, $this->query);
            }
        }
    }

    /**
     * Run the conductor on a Request to generate a collection and total.
     *
     * @param Request $request The request data.
     * @return array The processed and transformed collection | the total rows found.
     */
    final public static function request(Request $request): array
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
        $limitFields = $conductor->fields(new $conductor->class());
        if (is_array($limitFields) === false) {
            $limitFields = [];
        }
        $conductor->filter($request, $limitFields);

        // After Scope query
        $conductor->query->where(function ($query) use ($conductor) {
            $conductor->scope($query);
        });

        // Sort request
        $sort = $request->input('sort', $conductor->sort);
        if (strlen($sort) === 0) {
            if (strlen($conductor->sort) > 0) {
                $conductor->sort($conductor->sort);
            }
        } else {
            $conductor->sort($sort);
        }

        // Get total
        $total = $conductor->count();

        // Paginate
        $conductor->paginate($request->input('page', 1), $request->input('limit', -1), $request->input('offset', 0));

        // Filter request
        $fields = $conductor->fields(new $conductor->class());
        if (is_array($fields) === false) {
            $fields = [];
        }

        // Limit fields
        $limitFields = array_map(function ($field) {
            if (strpos($field, '.') !== false) {
                return substr($field, 0, strpos($field, '.'));
            }
            return $field;
        }, explode(',', $request->input('fields', '')));
        if ($limitFields === null) {
            $limitFields = $fields;
        } else {
            $limitFields = array_intersect($limitFields, $fields);
        }
        $conductor->limitFields($limitFields);
        $conductor->collection = $conductor->query->get();

        // Transform and Includes
        $includes = $conductor->includes;
        if (count($limitFields) > 0) {
            $includes = array_intersect($limitFields, $conductor->includes);
        }

        $conductor->collection = $conductor->collection->map(
            function ($model) use ($conductor, $includes, $limitFields) {
                $conductor->applyIncludes($model, $includes);

                if (count($limitFields) > 0) {
                    $model->setAppends(array_intersect($model->getAppends(), $limitFields));
                }

                $model = $conductor->transformModel($model);

                return $model;
            }
        );

        return [$conductor->collection, $total];
    }

    /**
     * Run the conductor on a collection with the data stored in a Request.
     *
     * @param Request    $request    The request data.
     * @param Collection $collection The collection.
     * @return array The processed and transformed model data.
     */
    final public static function collection(Request $request, Collection $collection): array
    {
        $conductor_class = get_called_class();
        $conductor = new $conductor_class();

        $conductor->collection = collect();

        foreach ($collection as $item) {
            if ($conductor->viewable($item) === true) {
                $conductor->collection->push($conductor->transformModel($item));
            }
        }

        // Filter request
        $limitFields = $conductor->fields(new $conductor->class());
        if (is_array($limitFields) === false) {
            $limitFields = [];
        }
        $conductor->filter($request, $limitFields);

        // Get total
        $total = $conductor->collection->count();

        // Sort request
        $sort = $request->input('sort', $conductor->sort);
        if (strlen($sort) === 0) {
            if (strlen($conductor->sort) > 0) {
                $conductor->sort($sort);
            }
        } else {
            $conductor->sort($sort);
        }

        // Paginate
        $conductor->paginate($request->input('page', 1), $request->input('limit', -1), $request->input('offset', 0));


        return [$conductor->collection, $total];
    }

    /**
     * Filter a custom query on a user request.
     *
     * @param Builder    $query       The custom query.
     * @param Request    $request     The request.
     * @param array|null $limitFields Limit the request to these fields.
     * @return Builder
     */
    public static function filterQuery(Builder $query, Request $request, array|null $limitFields = null): Builder
    {
        $conductor_class = get_called_class();
        $conductor = new $conductor_class();

        $conductor->query = $query;
        $conductor->filter($request, $limitFields);

        return $conductor->query;
    }


    /**
     * Run the conductor on a Model with the data stored in a Request.
     *
     * @param Request    $request The request data.
     * @param string     $key     The key prefix to use.
     * @param Model|null $model   The model.
     * @return array|null The processed and transformed model data.
     */
    final public static function includeModel(Request $request, string $key, mixed $model): array|null
    {
        $fields = [];

        if ($request !== null && $request->has('fields') === true) {
            $requestFields = $request->input('fields');
            if ($requestFields !== null) {
                $requestFields = explode(',', $requestFields);
                if (in_array($key, $requestFields) === false) {
                    foreach ($requestFields as $field) {
                        if (strpos($field, $key . '.') === 0) {
                            $fields[] = substr($field, (strlen($key) + 1));
                        }
                    }
                }
            }
        }

        return static::model($fields, $model);
    }

    /**
     * Run the conductor on a Model with the data stored in a Request.
     *
     * @param mixed      $fields The fields to show.
     * @param Model|null $model  The model.
     * @return array|null The processed and transformed model data.
     */
    final public static function model(mixed $fields, mixed $model): array|null
    {
        if ($model === null) {
            return null;
        }

        $conductor_class = get_called_class();
        $conductor = new $conductor_class();

        $requestIncludes = [];
        $modelFields = $conductor->fields(new $conductor->class());
        
        // Limit fields
        $limitFields = $modelFields;
        if ($fields instanceof Request) {
            if ($fields !== null && $fields->has('fields') === true) {
                $requestFields = $fields->input('fields');
                if ($requestFields !== null) {
                    $limitFields = array_intersect(explode(',', $requestFields), $modelFields);
                }
            }
        } elseif (is_array($fields) === true && count($fields) > 0) {
            $limitFields = array_intersect($fields, $modelFields);
        }

        if (empty($limitFields) === false) {
            $modelAppends = $model->getAppends();

            foreach (array_diff($modelFields, $limitFields) as $attribute) {
                $key = array_search($attribute, $modelAppends);
                if ($key !== false) {
                    unset($modelAppends[$key]);
                } else {
                    unset($model[$attribute]);
                }
            }
            $model->setAppends($modelAppends);
        }

        // Includes
        $includes = array_intersect($limitFields, $conductor->includes);
        $conductor->applyIncludes($model, $includes);

        // Transform
        $model = $conductor->transformModel($model);

        return $model;
    }

    /**
     * Return the current conductor collection count.
     *
     * @return integer The current collection count.
     */
    final public function count(): int
    {
        if ($this->query !== null) {
            return $this->query->count();
        }

        return 0;
    }

    /**
     * Sort the conductor collection.
     *
     * @param mixed $fields A field name or array of field names to sort. Supports prefix of +/- to change direction.
     * @return void
     */
    final public function sort(mixed $fields = null): void
    {
        $collectionSort = [];

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

                if ($this->collection !== null) {
                    $collectionSort[] = [trim($orderByField), $direction];
                } else {
                    $this->query->orderBy(trim($orderByField), $direction);
                }
            }
        } else {
            throw new \InvalidArgumentException('Expected string or array, got ' . gettype($fields));
        }//end if

        if ($this->collection !== null) {
            $this->collection = $this->collection->sortBy($collectionSort)->values();
        }
    }

    /**
     * Paginate the conductor collection.
     *
     * @param integer $page   The current page to return.
     * @param integer $limit  The limit of items to include or use default.
     * @param integer $offset Offset the page count after this count of rows.
     * @return mixed
     */
    final public function paginate(int $page = 1, int $limit = -1, int $offset = 0)
    {
        // Limit
        if ($limit < 1) {
            $limit = $this->limit;
        } else {
            $limit = min($limit, $this->maxLimit);
        }

        // Page
        if ($page < 1) {
            $page = 1;
        }

        // After
        if ($offset < 0) {
            $offset = 0;
        }

        if ($this->collection !== null) {
            $this->collection = $this->collection->splice(((($page - 1) * $limit) + $offset), $limit);
        } else {
            $this->query->limit($limit);
            $this->query->offset((($page - 1) * $limit) + $offset);
        }
    }

    /**
     * Apply a list of includes to the model.
     *
     * @param Model $model    The model to append.
     * @param array $includes The list of includes to include.
     * @return void
     */
    final public function applyIncludes(Model $model, array $includes): void
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
    final public function limitFields(array $fields): void
    {
        if (empty($fields) !== true) {
            $this->query->select(array_diff($fields, $this->includes));
        }
    }

    /**
     * Filter the conductor collection using raw data.
     *
     * @param string     $rawFilter   The raw filter string to parse.
     * @param array|null $limitFields The fields to allow in the filter string.
     * @param string     $outerJoin   The join for this filter group.
     * @return void
     */
    final public function appendFilterString(string $rawFilter, array|null $limitFields = null, string $outerJoin = 'AND'): void
    {
        if ($rawFilter === '') {
            return;
        }

        if (substr($rawFilter, -1) !== ',') {
            $rawFilter .= ',';
        }

        $parseFunc = function ($string, &$i = 0) use (&$parseFunc, $limitFields) {
            $tokens = [];
            $ignoreUntil = '';
            $skipUntil = '';
            $field = '';
            $value = null;
            $set = &$field;

            for (; $i < strlen($string); $i++) {
                $char = $string[$i];

                if ($skipUntil !== '' && $char !== $skipUntil) {
                    continue;
                }

                if ($ignoreUntil === '') {
                    if ($char === '\'' || $char === '"') {
                        $ignoreUntil = $char;
                    } elseif ($char === ':') {
                        if ($field === '') {
                            $skipUntil = ',';
                            continue;
                        }

                        if ($field[0] === '\'' || $field[0] === '"') {
                            $field = substr($field, 1, -1);
                        }

                        if ($set !== $value) {
                            $set = &$value;
                            continue;
                        }
                    } elseif (($char === ')' && $string[($i + 1)] === ',') || $char === ',') {
                        if ($value === null) {
                            $tokens[] = $field;
                        } else {
                            $value = trim($value);
                            $operator = 'LIKE';

                            // Check if value has a operator and remove it if it's a number
                            if (preg_match('/^(!?=|[<>]=?|<>|!)([^=!<>].*)*$/', $value, $matches) > 0) {
                                $operator = $matches[1];
                                $value = ($matches[2] ?? '');
                            }

                            if ($value[0] === '\'' || $value[0] === '"') {
                                $value = substr($value, 1, -1);
                            }

                            if ($operator === 'LIKE') {
                                $value = "%{$value}%";
                            }

                            if (
                                is_array($limitFields) === false ||
                                in_array(strtolower($field), array_map('strtolower', $limitFields)) !== false
                            ) {
                                $tokens[] = [$field, $operator, $value];
                            }
                        }//end if

                        $field = '';
                        $value = null;
                        $set = &$field;

                        if ($char === ')') {
                            $i++;
                            return $tokens;
                        }

                        continue;
                    } elseif ($char === '(') {
                        if ($field === '') {
                            $i++;
                            $tokens[] = $parseFunc($string, $i);
                            continue;
                        }
                    }//end if
                } elseif ($char === $ignoreUntil) {
                    $ignoreUntil = '';
                }//end if

                $set .= $char;
            }//end for

            return $tokens;
        };

        $i = 0;
        $filterArray = $parseFunc($rawFilter, $i);

        if (count($this->filterArray) !== 0) {
            $this->filterArray[] = $outerJoin;
        }
        $this->filterArray[] = $filterArray;
    }

    /**
     * Append a field to the filter array.
     *
     * @param string $field    The field name to append.
     * @param string $operator The operator to append.
     * @param string $value    The value to append.
     * @param string $join     The join to append.
     * @return void
     */
    final public function appendFilter(string $field, string $operator, string $value, string $join = 'OR'): void
    {
        if (count($this->filterArray) !== 0) {
            $this->filterArray[] = $join;
        }
        $this->filterArray[] = [$field, $operator, $value];
    }

    /**
     * Run a scope query on the collection before anything else.
     *
     * @param Builder $builder The builder in use.
     * @return void
     */
    public function scope(Builder $builder): void
    {
        // empty
    }

    /**
     * Return an array of model fields visible to the current user.
     *
     * @param Model $model The model in question.
     * @return array The array of field names.
     */
    public function fields(Model $model): array
    {
        $visibleFields = Cache::remember("model:{$model->getTable()}:visible", now()->addDays(28), function () use ($model) {
            $fields = $model->getVisible();
            if (empty($fields) === true) {
                $fields = Cache::remember("schema:{$model->getTable()}:columns", now()->addDays(28), function () use ($model) {
                    return $model->getConnection()
                    ->getSchemaBuilder()
                    ->getColumnListing($model->getTable());
                });
            }

            return $fields;
        });

        $appends = $model->getAppends();
        if (is_array($appends) === true) {
            $visibleFields = array_merge($visibleFields, $appends);
        }

        if (is_array($this->includes) === true) {
            $visibleFields = array_merge($visibleFields, $this->includes);
        }

        return $visibleFields;
    }

    /**
     * Transform the passed Model to an array
     *
     * @param Model $model The model to transform.
     * @return array The transformed model.
     */
    protected function transformModel(Model $model): array
    {
        $result = $this->transform($model);
        foreach ($result as $key => $value) {
            $transformFunction = 'transform' . Str::studly($key);
            if (method_exists($this, $transformFunction) === true) {
                $result[$key] = $this->$transformFunction($value);
            }
        }

        $result = $this->transformFinal($result);
        return $result;
    }

    /**
     * Transform the passed Model to an array
     *
     * @param Model $model The model to transform.
     * @return array The transformed model.
     */
    public function transform(Model $model): array
    {
        $result = $model->toArray();

        $fields = $this->fields($model);

        if (is_array($fields) === true) {
            $result = array_intersect_key($result, array_flip($fields));
        }

        return $result;
    }

    /**
     * Final Transform of the model array
     *
     * @param array $data The model array to transform.
     * @return array The transformed model.
     */
    public function transformFinal(array $data): array
    {
        return $data;
    }

    /**
     * Is the passed model viewable by the current user?
     *
     * @param Model $model The model in question.
     * @return boolean Is the model viewable.
     */
    public static function viewable(Model $model): bool
    {
        return true;
    }

    /**
     * Is the model creatable by the current user?
     *
     * @return boolean Is the model creatable.
     */
    public static function creatable(): bool
    {
        return true;
    }

    /**
     * Is the passed model updatable by the current user?
     *
     * @param Model $model The model in question.
     * @return boolean Is the model updatable.
     */
    public static function updatable(Model $model): bool
    {
        return true;
    }

    /**
     * Is the passed model destroyable by the current user?
     *
     * @param Model $model The model in question.
     * @return boolean Is the model destroyable.
     */
    public static function destroyable(Model $model): bool
    {
        return true;
    }
}
