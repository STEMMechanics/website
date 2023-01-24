<?php

namespace App\Filters;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\SchemaException;
use ReflectionClass;
use RuntimeException;
use InvalidArgumentException;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Schema;

abstract class FilterAbstract
{
    /**
     * The model class to filter
     *
     * @var mixed
     */
    protected $class;

    /**
     * The filter request
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * The models table
     *
     * @var string
     */
    protected $table = '';

    /**
     * Array of columns that can be filtered by the api
     *
     * @var array
     */
    protected $filterable = null;

    /**
     * Default column sorting (prefix with - for descending)
     *
     * @var string|array
     */
    protected $defaultSort = 'id';

    /**
     * Default collection result limit
     *
     * @var integer
     */
    protected $defaultLimit = 50;

    /**
     * Found records from query
     * @var integer
     */
    protected $foundTotal = 0;

    /**
     * Maximum collection result limit
     *
     * @var integer
     */
    protected $maxLimit = 100;

    /**
     * Only return these attributes in the results
     * (minus any excludes)
     *
     * @var array
     */
    protected $only = [];

    /**
     * Exclude these attributes from the results
     *
     * @var array
     */
    protected $exclude = [];

    /**
     * Filter columns for q param
     *
     * @var string|array
     */
    protected $q = [];


    /**
     * Filter constructor.
     *
     * @param \Illuminate\Http\Request $request Request object.
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Only include the specified attributes in the results.
     *
     * @param string|array $only Only return these attributes.
     * @return void
     */
    public function only(mixed $only)
    {
        if (is_array($only) === true) {
            $this->only = $only;
        } else {
            $this->only = [$only];
        }
    }

    /**
     * Exclude the specified attributes in the results.
     *
     * @param string|array $exclude Attributes to exclude.
     * @return void
     */
    public function exclude(mixed $exclude)
    {
        if (is_array($exclude) === true) {
            $this->exclude = $exclude;
        } else {
            $this->exclude = [$exclude];
        }
    }

    /**
     * Check if the model is viewable by the user
     *
     * @param mixed $model Model instance.
     * @param mixed $user  Current user.
     * @return boolean
     */
    // protected function viewable(mixed $model, mixed $user)
    // {
    //     return true;
    // }

    /**
     * Prepend action to the builder to limit the results
     *
     * @param Builder $builder Builder instance.
     * @param mixed   $user    Current user.
     * @return Builder|null
     */
    // protected function prebuild(Builder $builder, mixed $user)
    // {
    //     return $builder;
    // }


    /**
     * Return an array of attributes visible in the results
     *
     * @param array     $attributes Attributes currently visible.
     * @param User|null $user       Current logged in user or null.
     * @return mixed
     */
    protected function seeAttributes(array $attributes, mixed $user)
    {
        return $attributes;
    }

    /**
     * Apply all the requested filters if available.
     *
     * @param Model $model Model object to filter. If null create query.
     * @return Builder|Model
     */
    public function filter(Model $model = null)
    {
        $this->foundTotal = 0;

        $builder = $this->class::query();

        /* Get the related model */
        $classModel = $model;
        if ($model === null) {
            $classModel = $builder->getModel();
        }

        /* Get table name */
        if ($this->table === '') {
            if ($model === null) {
                $this->table = $classModel->getTable();
            } else {
                $this->table = $model->getTable();
            }
        }

        /* Run query prebuilder or viewable */
        if ($model === null) {
            if (method_exists($this, 'prebuild') === true) {
                $prebuilder = $this->prebuild($builder, $this->request->user());
                if ($prebuilder instanceof Builder) {
                    $builder = $prebuilder;
                }
            }
        } else {
            if (method_exists($this, 'viewable') === true) {
                if ($this->viewable($model, $this->request->user()) === false) {
                    return null;
                }
            }
        }

        /* Get attributes from table or use 'only' */
        $attributes = [];
        if (is_array($this->only) === true && count($this->only) > 0) {
            $attributes = $this->only;
        } else {
            $attributes = Schema::getColumnListing($this->table);
        }

        /* Run attribute modifiers*/
        $modifiedAttribs = $this->seeAttributes($attributes, $this->request->user());
        if (is_array($modifiedAttribs) === true) {
            $attributes = $modifiedAttribs;
        }

        foreach ($attributes as $key => $column) {
            $method = 'see' . Str::studly($column) . 'Attribute';
            if (
                method_exists($this, $method) === true &&
                $this->$method($this->request->user()) === false
            ) {
                unset($attributes[$key]);
            }
        }

        if (is_array($this->exclude) === true && count($this->exclude) > 0) {
            $attributes = array_diff($attributes, $this->exclude);
        }

        /* Setup attributes and appends */
        // $attributesAppends = array_merge($attributes, $classModel->getAppends());

        /* Apply ?fields= request to attributes */
        if ($this->request->has('fields') === true) {
            $attributes = array_intersect($attributes, explode(',', $this->request->fields));
        }

        /* Hide remaining attributes in model (if present) and return */
        if ($model !== null) {
            // TODO: Also show $this->request->fields that are appends

            $model->makeHidden(array_diff(Schema::getColumnListing($this->table), $attributes));
            return $model;
        }

        /* Are there attributes left? */
        if (count($attributes) === 0) {
            $this->foundTotal = 0;
            return new Collection();
        }

        /* apply select! */
        $builder->select($attributes);

        /* Setup filterables if not present */
        if ($this->filterable === null) {
            $this->filterable = $attributes;
        }

        /* Filter values */
        $filterRequest = array_filter($this->request->only(array_intersect($attributes, $this->filterable)));
        $this->builderArrayFilter($builder, $filterRequest);

        if (is_array($this->q) === true && count($this->q) > 0) {
            $qQueries = [];
            foreach ($this->q as $key => $value) {
                if (is_array($value) === true) {
                    $qKey = $key === '_' ? '' : $key;
                    foreach ($value as $subvalue) {
                        $qQueries[$key][$subvalue] = $this->request->get("q" . $qKey);
                    }
                } elseif ($this->request->has("q") === true) {
                    $qQueries['_'][$value] = $this->request->get("q");
                }
            }

            foreach ($qQueries as $key => $value) {
                $builder->where(function ($query) use ($value) {
                    $this->builderArrayFilter($query, $value, 'or');
                });
            }
        }//end if

        /* Apply sorting */
        $sortList = $this->defaultSort;
        if ($this->request->has('sort') === true) {
            $sortList = explode(',', $this->request->sort);
        }

        /* Transform sort list to array */
        if (is_array($sortList) === false) {
            if (strlen($sortList) > 0) {
                $sortList = [$sortList];
            } else {
                $sortList = [];
            }
        }

        /* Remove non-viewable attributes from sort list */
        if (count($sortList) > 0) {
            $sortList = array_intersect($attributes, $sortList);
        }

        /* Do we have any sort element left? */
        if (count($sortList) > 0) {
            foreach ($sortList as $sortAttribute) {
                $prefix = substr($sortAttribute, 0, 1);
                $direction = 'asc';

                if (in_array($prefix, ['-', '+']) === true) {
                    $sortAttribute = substr($sortAttribute, 1);
                    if ($prefix === '-') {
                        $direction = 'desc';
                    }
                }

                $builder->orderBy($sortAttribute, $direction);
            }//end foreach
        }//end if

        /* save found count */
        $this->foundTotal = $builder->count();

        /* Apply result limit */
        $limit = $this->defaultLimit;
        if ($this->request->has('limit') === true) {
            $limit = intval($this->request->limit);
        }
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > $this->maxLimit && $this->maxLimit !== 0) {
            $limit = $this->maxLimit;
        }

        $builder->limit($limit);

        /* Apply page offset */
        if ($this->request->has('page') === true) {
            $page = intval($this->request->page);
            if ($page < 1) {
                $page = 1;
            }

            $builder->offset((intval($this->request->page) - 1) * $limit);
        }

        /* run spot run */
        $collection = $builder->get();

        return $collection;
    }

    /**
     * Filter content based on the filterRequest
     * @param mixed  $builder        Builder object
     * @param array  $filterRequest  Filter key/value
     * @param string $defaultBoolean Default where boolean
     * @return void
     */
    protected function builderArrayFilter(mixed $builder, array $filterRequest, string $defaultBoolean = 'and')
    {
        foreach ($filterRequest as $filterAttribute => $filterValue) {
            $tags = [];
            $boolean = $defaultBoolean;

            $matches = preg_split('/(?<!\\\\)"/', $filterValue, -1, PREG_SPLIT_OFFSET_CAPTURE);
            foreach ($matches as $idx => $match_info) {
                if (($idx % 2) === true) {
                    if (substr($filterValue, ($match_info[1] - 2), 1) === ',') {
                        $tags[] = ['operator' => '', 'tag' => stripslashes(trim($match_info[0]))];
                    } else {
                        $tags[(count($tags) - 1)]['tag'] .= stripslashes(trim($match_info[0]));
                    }
                } else {
                    $innerTags = [$match_info[0]];
                    if (strpos($match_info[0], ',') !== false) {
                        $innerTags = preg_split('/(?<!\\\\),/', $match_info[0]);
                    }

                    foreach ($innerTags as $tag) {
                        $tag = stripslashes(trim($tag));
                        if (strlen($tag) > 0) {
                            $operator = '=';

                            $single = substr($tag, 0, 1);
                            $double = substr($tag . ' ', 0, 2); // add empty space incase len $tag < 2

                            // check for operators at start
                            if (in_array($double, ['!=', '<>', '><', '>=', '<=', '=>', '=<']) === true) {
                                if ($double === '<>' || $double === '><') {
                                    $double = '!=';
                                } elseif ($double === '=>') {
                                    $double = '>=';
                                } elseif ($double === '=<') {
                                    $double == '>=';
                                }

                                $operator = $double;
                                $tag = substr($tag, 2);
                            } else {
                                if (in_array($single, ['=', '!', '>', '<', '~', '%']) === true) {
                                    if ($single === '=') {
                                        $single = '=='; // a single '=' is actually a double '=='
                                    }

                                    $operator = $single;
                                    $tag = substr($tag, 1);
                                }
                            }//end if

                            $tags[] = ['operator' => $operator, 'tag' => $tag];
                        }//end if
                    }//end foreach
                }//end if
            }//end foreach

            if (count($tags) > 1) {
                $boolean = 'or';
            }

            foreach ($tags as $tag_data) {
                $operator = $tag_data['operator'];
                $value = $tag_data['tag'];
                $table = $this->table;
                $column = $filterAttribute;

                if (($dotPos = strpos($filterAttribute, '.')) !== false) {
                    $table = substr($filterAttribute, 0, $dotPos);
                    $column = substr($filterAttribute, ($dotPos + 1));
                }

                $columnType = DB::getSchemaBuilder()->getColumnType($table, $column);

                if (
                    in_array($columnType, ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint',
                        'decimal', 'float', 'double', 'real', 'double precision'
                    ]) === true
                ) {
                    if (in_array($operator, ['=', '>', '<', '>=', '<=', '%', '!']) === false) {
                        continue;
                    }

                    $columnType = 'numeric';
                } elseif (in_array($columnType, ['date', 'time', 'datetime', 'timestamp', 'year']) === true) {
                    if (in_array($operator, ['=', '>', '<', '>=', '<=', '!']) === false) {
                        continue;
                    }

                    $columnType = 'datetime';
                } elseif (
                    in_array($columnType, ['string', 'char', 'varchar', 'timeblob', 'blob', 'mediumblob',
                        'longblob', 'tinytext', 'text', 'mediumtext', 'longtext', 'enum'
                    ]) === true
                ) {
                    if (in_array($operator, ['=', '==', '!', '!=', '~']) === false) {
                        continue;
                    }

                    $columnType = 'text';

                    if ($value === "''" || $value === '""') {
                        $value = '';
                    } elseif (strcasecmp($value, 'null') !== 0) {
                        if ($operator === '!') {
                            $operator = 'NOT LIKE';
                            $value = '%' . $value . '%';
                        } elseif ($operator === '=') {
                            $operator = 'LIKE';
                            $value = '%' . $value . '%';
                        } elseif ($operator === '~') {
                            $operator = 'SOUNDS LIKE';
                        } elseif ($operator === '==') {
                            $operator = '=';
                        }
                    }
                } elseif ($columnType === 'boolean') {
                    if (in_array($operator, ['=', '!']) === false) {
                        continue;
                    }

                    if (strtolower($value) === 'true') {
                        $value = 1;
                    } elseif (strtolower($value) === 'false') {
                        $value = 0;
                    }
                }//end if

                $betweenSeperator = strpos($value, '<>');
                if (
                    $operator === '=' && $betweenSeperator !== false && in_array($columnType, ['numeric',
                        'datetime'
                    ]) === true
                ) {
                    $value = explode('<>', $value);
                    $operator = '<>';
                }

                if ($operator !== '') {
                    $this->builderWhere($builder, $table, $column, $operator, $value, $boolean);
                }
            }//end foreach
        }//end foreach
    }

    /**
     * Insert a where statement into the builder, taking the filter map into consideration
     *
     * @param Builder $builder  Builder instance.
     * @param string  $table    Table name.
     * @param string  $column   Column name.
     * @param string  $operator Where operator.
     * @param mixed   $value    Value to test.
     * @param string  $boolean  Use Or comparison.
     * @return void
     * @throws RuntimeException Error applying statement.
     * @throws InvalidArgumentException Error applying statement.
     */
    protected function builderWhere(
        Builder &$builder,
        string $table,
        string $column,
        string $operator,
        mixed $value,
        string $boolean
    ) {
        if (
            (is_string($value) === true && $operator !== '<>') || (is_array($value) === true && count($value) === 2 &&
            $operator === '<>')
        ) {
            if ($table !== '' && $table !== $this->table) {
                $builder->whereHas($table, function ($query) use ($column, $operator, $value, $boolean) {
                    if ($operator !== '<>') {
                        if (strcasecmp($value, 'null') === 0) {
                            if ($operator === '!') {
                                $query->whereNotNull($column, $boolean);
                            } else {
                                $query->whereNull($column, $boolean);
                            }
                        } else {
                            $query->where($column, $operator, $value, $boolean);
                        }
                    } else {
                        $query->whereBetween($column, $value, $boolean);
                    }
                });
            } else {
                if ($operator !== '<>') {
                    if (strcasecmp($value, 'null') === 0) {
                        if ($operator === '!') {
                            $builder->whereNotNull($column, $boolean);
                        } else {
                            $builder->whereNull($column, $boolean);
                        }
                    } else {
                        $builder->where($column, $operator, $value, $boolean);
                    }
                } else {
                    $builder->whereBetween($column, $value, $boolean);
                }
            }//end if
        }//end if
    }

    /**
     * Return the found total of items
     * @return integer
     */
    public function foundTotal()
    {
        return $this->foundTotal;
    }
}
