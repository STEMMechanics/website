<?php
namespace App\Conductors;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class Conductor {
    protected $class = null;
    protected $sort = "id";
    protected $limit = 50;
    protected $maxLimit = 100;
    protected $includes = [];

    private $collection = null;
    private $query = null;

    final public static function request(Request $request) {
        $conductor_class = get_called_class();
        $conductor = new $conductor_class;

        $total = 0;

        try {
            $conductor->query = $conductor->class::query();
        } catch (\Throwable $e) {
            throw new \Exception('Failed to create query builder instance for ' . $conductor->class . '.', 0, $e);
        }
        
        // Scope query
        $conductor->scope($conductor->query);

        // Filter request
        $fields = $conductor->fields(new $conductor->class);
        if(is_array($fields) == false) {
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
        if($limitFields == null) {
            $limitFields = $fields;
        } else {
            $limitFields = array_intersect($limitFields, $fields);
        }
        $conductor->limitFields($limitFields);

        $conductor->collection = $conductor->query->get();

        // Transform and Includes
        $includes = $conductor->includes;
        if($request->has('includes')) {
            $includes = explode(',', $request->input('includes'));
        }
        
        $conductor->collection = $conductor->collection->map(function ($model) use($conductor, $includes) {
            $conductor->includes($model, $includes);
            $model = $conductor->transform($model);

            return $model;
        });

        return [$conductor->collection, $total];
    }

    final public static function model(Request $request, Model $model) {
        $conductor_class = get_called_class();
        $conductor = new $conductor_class;
        
        $fields = $conductor->fields(new $conductor->class);

        // Limit fields
        $limitFields = $fields;
        if($request != null && $request->has('fields')) {
            $requestFields = $request->input('fields');
            if($requestFields != null) {
                $limitFields = array_intersect(explode(',', $requestFields), $fields);
            }
        }

        if(empty($limitFields) === false) {
            $modelSubset = new $conductor->class;
            foreach($limitFields as $field) {
                $modelSubset->setAttribute($field, $model->$field);
            }
            $model = $modelSubset;
        }
        
        // Includes
        $includes = $conductor->includes;
        if($request != null && $request->has('includes')) {
            $includes = explode(',', $request->input('includes', ''));
        }
        $conductor->includes($model, $includes);

        // Transform
        $model = $conductor->transform($model);
        
        return $model;
    }

    final public function filterField(Builder $builder, string $field, mixed $value) {
        // Split by comma, but respect quotation marks
        if (is_string($value)) {
            $values = preg_split('/(?<!\\\\),/', $value);
            $values = array_map(function ($val) {
                return str_replace('\,', ',', $val);
            }, $values);
        } else if (is_array($value)) {
            $values = $value;
        } else {
            throw new \InvalidArgumentException('Expected string or array, got ' . gettype($value));
        }

        // Add each AND check to the query
        $this->query->where(function ($query) use ($field, $values) {
            foreach ($values as $value) {
                $value = trim($value);
                
                // Check if value has a prefix and remove it if it's a number
                if (preg_match('/^([<>!=]=?)(\d+\.?\d*)$/', $value, $matches)) {
                    $prefix = $matches[1];
                    $value = $matches[2];
                } else {
                    $prefix = '';
                }
                
                // If the value starts with '=', exact match
                if (strpos($value, '=') === 0) {
                    $query->where($field, '=', substr($value, 1));
                } else if (strpos($value, '!=') === 0) {
                    $query->where($field, '<>', substr($value, 2));
                } else if (strpos($value, '!') === 0) {
                    $query->where($field, 'NOT LIKE', '%'.substr($value, 1).'%');
                } else {
                    $query->where($field, 'LIKE', "%$value%");
                }
                
                // Apply the prefix to the query if the value is a number
                if (is_numeric($value)) {
                    switch ($prefix) {
                        case '>':
                            $query->where($field, '>', $value);
                            break;
                        case '<':
                            $query->where($field, '<', $value);
                            break;
                        case '>=':
                            $query->where($field, '>=', $value);
                            break;
                        case '<=':
                            $query->where($field, '<=', $value);
                            break;
                        case '!=':
                        case '<>':
                            $query->where($field, '<>', $value);
                            break;
                    }
                }
            }
        });
    }

    final public function collection(Collection $collection = null) {
        if($collection != null) {
            $this->collection = $collection;
        }

        return $this->collection;
    }

    final public function count() {
        if($this->query != null) {
            return $this->query->count();
        }

        return 0;
    }
    
    final public function sort(mixed $fields = null) {
        if(is_string($fields)) {
            $fields = explode(',', $fields);
        } else if($fields == null) {
            $fields = $this->sort;
        }

        if(is_array($fields)) {
            foreach ($fields as $orderByField) {
                $direction = 'asc';
                $directionChar = substr($orderByField, 0, 1);
                
                if(in_array($directionChar, ['-', '+'])) {
                    $orderByField = substr($orderByField, 1);
                    if($directionChar == '-') {
                        $direction = 'desc';
                    }
                }

                $this->query->orderBy(trim($orderByField), $direction);
            }
        } else {
            throw new \InvalidArgumentException('Expected string or array, got ' . gettype($fields));
        }
    }
    
    final public function filter(array $filters) {
        foreach ($filters as $param => $value) {
            $this->filterField($this->query, $param, $value);
        }
    }

    final public function paginate(int $page = 1, int $limit = -1) {
        // Limit
        if($limit < 1) {
            $limit = $this->limit;
        } else {
            $limit = min($limit, $this->maxLimit);
        }
        $this->query->limit($limit);

        // Page
        if($page < 1) {
            $page = 1;
        }
        $this->query->offset(($page - 1) * $limit);
    }

    final public function includes(Model $model, array $includes) {
        foreach($includes as $include) {
            $includeMethodName = 'include' . Str::studly($include);
            if (method_exists($this, $includeMethodName)) {
                $attributeName = Str::snake($include);
                $attributeValue = $this->{$includeMethodName}($model);
                if($attributeValue !== null) {
                    $model->$attributeName = $this->{$includeMethodName}($model);
                }
            }
        }
    }

    final public function limitFields(array $fields) {
        if(empty($fields) !== true) {
            $this->query->select($fields);
        }
    }

    /** overrides */
    public function scope(Builder $builder) {

    }

    public function fields(Model $model) {
        $visibleFields = $model->getVisible();
        if (empty($visibleFields)) {
            $tableColumns = $model->getConnection()
                ->getSchemaBuilder()
                ->getColumnListing($model->getTable());
            return $tableColumns;
        }

        return $visibleFields;
    }

    public function transform(Model $model) {
        return $model->toArray();
    }

    public static function viewable(Model $model) {
        return true;
    }

    public static function creatable() {
        return true;
    }    

    public static function updatable(Model $model) {
        return true;
    }

    public static function destroyable(Model $model) {
        return true;
    }
}