<?php namespace October\Rain\Database;

use Illuminate\Database\Eloquent\Builder as BuilderModel;

/**
 * Query builder class.
 *
 * Extends Eloquent builder class.
 *
 * @package october\database
 * @author Alexey Bobkov, Samuel Georges
 */
class Builder extends BuilderModel
{

    /**
     * Add a basic where clause to the query, @ symbols in columns replaced with
     * the model table name.
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @param  string  $boolean
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (is_string($column) && strpos($column, '@') !== false) {
            $column = str_replace('@', $this->model->getTable().'.', $column);
        }

        return parent::where($column, $operator, $value, $boolean);
    }

    /**
     * Joins relationships to a query and optionally eager loads them
     */
    public function joinWith($relations, $eagerLoad = true)
    {
        if (is_string($relations)) $relations = func_get_args();

        foreach ($relations as $index => $relation) {
            if (!$this->model->hasRelation($relation))
                unset($relations[$index]);
        }

        $selectTables = [];

        if ($eagerLoad)
            $this->with($relations);

        foreach ($relations as $relation) {
            $relationObj = $this->model->$relation();
            $relationObj->joinWithQuery($this);
        }

        /*
         * Adds the final selection of primary model table and removes duplicates
         */
        $selectTables[] = $this->model->getTable().'.*';
        if (count($this->query->columns))
            $this->query->columns = array_diff($this->query->columns, $selectTables);

        $this->addSelect($selectTables);

        return $this;
    }

    /**
     * Perform a search on this query for term found in columns
     * @param  string $term  Search query
     * @param  array $columns Table columns to search
     * @return self
     */
    public function searchWhere($term, $columns = [])
    {
        if (!is_array($columns))
            $columns = [$columns];

        $words = explode(' ', $term);
        foreach ($columns as $field) {
            $this->orWhere(function($query) use ($field, $words) {
                foreach ($words as $word) {
                    if (!strlen($word)) continue;
                    $fieldSql = $this->query->raw(sprintf("lower(%s)", $field));
                    $wordSql = '%'.trim(mb_strtolower($word)).'%';
                    $query->where($fieldSql, 'LIKE', $wordSql);
                }
            });
        }

        return $this;
    }

    /**
     * Dynamically handle calls into the query instance.
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if ($this->model->methodExists($scope = 'scope'.ucfirst($method))) {
            return $this->callScope($scope, $parameters);
        }

        return parent::__call($method, $parameters);
    }

}