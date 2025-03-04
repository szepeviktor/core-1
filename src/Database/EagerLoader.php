<?php


namespace TypeRocket\Database;


use TypeRocket\Models\Model;

class EagerLoader
{

    protected $load = [];
    protected $with = null;


    /**
     * Eager Load
     *
     * @param array $load
     * @param $results
     * @param string|null $with
     * @return mixed
     */
    public function load($load, $results, $with)
    {
        $this->load = $load;
        $this->with = $with;

        return $this->withEager($results);
    }

    /**
     * With Eager
     *
     * @param $result
     * @return mixed
     */
    protected function withEager($result) {
        if(empty($this->load) || empty($result)) {
            return $result;
        }

        /** @var Model $relation */
        $relation = $this->load['relation'] ? clone $this->load['relation']: null;
        $name = $this->load['name'];

        if(is_null($relation)) {
            /** @var Model $result */
            if($result instanceof Results) {
                foreach($result as $key => $value) {
                    /** @var Model $value */
                    $value->setRelationship($name, null);
                }
            } else {
                $result->setRelationship($name, null);
            }

            return $result;
        }

        $type = $relation->getRelatedBy()['type'];
        $result = $this->{$type}($result);

        return $result;
    }

    /**
     * Compile Items
     *
     * @param array|Results $items results from query
     * @param string $on the field to group items by
     * @param bool $array return array or object
     * @param string $resultClass
     * @param bool $unset unset the on property from result item
     *
     * @return array
     */
    protected function compileItems($items, $on = null, $array = false, $resultClass = '\TypeRocket\Database\Results', $unset = false) {
        $set = [];

        if(!empty($items)) {
            foreach ($items as $item) {
                $index = $item->{$on};

                if(!$array) {
                    $set[$index] = $item;
                } else {
                    if(empty($set[$index]) && $resultClass) {
                        $set[$index] = new $resultClass;
                    }

                    if($unset) { unset($item->{$on}); }
                    $set[$index]->append($item);
                }
            }
        }

        return $set;
    }

    /**
     * Belongs To
     *
     * @param $result
     * @return mixed
     * @throws \Exception
     */
    public function belongsTo($result)
    {
        $ids = [];
        /** @var Model $relation */
        $relation = clone $this->load['relation'];
        $name = $this->load['name'];
        $by = $relation->getRelatedBy();
        $query = $by['query'];

        if($result instanceof Results) {
            foreach($result as $model) {
                /** @var Model $model */
                $ids[] = $model->{$query['local_id']};
            }
        } elseif($result instanceof Model) {
            $ids[] = $result->{$query['local_id']};
        }

        $on = $relation->getIdColumn();
        $items = $relation->removeTake()->removeWhere()->where($on, 'IN', $ids)->with($this->with)->get();

        $set = $this->compileItems($items, $on, false, $relation->getResultsClass());

        if($result instanceof Results) {
            foreach($result as $key => $value) {
                /** @var Model $value */
                $local_id = $value->{$query['local_id']};
                $value->setRelationship($name, $set[$local_id] ?? null);
            }
        } else {
            $lookup = $result->{$query['local_id']};
            $result->setRelationship($name, $set[$lookup] ?? null);
        }

        return $result;
    }

    /**
     * Has One
     *
     * @param $result
     * @return mixed
     * @throws \Exception
     */
    public function hasOne($result)
    {
        $ids = [];
        /** @var Model $relation */
        $relation = clone $this->load['relation'];
        $name = $this->load['name'];
        $query = $relation->getRelatedBy()['query'];

        if($result instanceof Results) {
            foreach($result as $model) {
                /** @var Model $model */
                $ids[] = $model->getId();
            }
        } elseif($result instanceof Model) {
            $ids[] = $result->getId();
        }

        $items = $relation->removeTake()->removeWhere()->where($query['id_foreign'], 'IN', $ids)->with($this->with)->get();

        $set = $this->compileItems($items, $query['id_foreign'], false, $relation->getResultsClass());

        if($result instanceof Results) {
            foreach($result as $key => $value) {
                /** @var Model $value */
                $local_id = $value->getId();
                $value->setRelationship($name, $set[$local_id] ?? null);
            }
        } else {
            $result->setRelationship($name, $set[$result->getId()] ?? null);
        }

        return $result;
    }

    /**
     * Has Many
     *
     * @param $result
     * @return mixed
     * @throws \Exception
     */
    public function hasMany($result)
    {
        $ids = [];
        /** @var Model $relation */
        $relation = clone $this->load['relation'];
        $name = $this->load['name'];
        $query = $relation->getRelatedBy()['query'];

        if($result instanceof Results) {
            foreach($result as $model) {
                /** @var Model $model */
                $ids[] = $model->getId();
            }
        } elseif($result instanceof Model) {
            $ids[] = $result->getId();
        }

        $items = $relation->removeTake()->removeWhere()->where($query['id_foreign'], 'IN', $ids)->with($this->with)->get();

        $set = $this->compileItems($items, $query['id_foreign'], true, $relation->getResultsClass() );

        if($result instanceof Results) {
            foreach($result as $key => $value) {
                /** @var Model $value */
                $local_id = $value->getId();
                $value->setRelationship($name, $set[$local_id] ?? null);
            }
        } else {
            $result->setRelationship($name, $set[$result->getId()] ?? null);
        }

        return $result;
    }


    /**
     * Belong To Many
     *
     * @param $result
     * @return mixed
     * @throws \Exception
     */
    public function belongsToMany($result)
    {
        $ids = [];
        /** @var Model $relation */
        $relation = clone $this->load['relation'];
        $name = $this->load['name'];
        $query = $relation->getRelatedBy()['query'];
        $set = [];

        if($result instanceof Results) {
            foreach($result as $model) {
                /** @var Model $model */
                $ids[] = $model->getId();
            }
        } elseif($result instanceof Model) {
            $ids[] = $result->getId();
        }

        $items = $relation
            ->select($query['where_column'] . ' as the_relationship_id')
            ->removeTake()
            ->removeWhere()
            ->where($query['where_column'], 'IN', $ids)
            ->with($this->with)
            ->get();

        $set = $this->compileItems($items, 'the_relationship_id', true, $relation->getResultsClass());

        if($result instanceof Results) {
            foreach($result as $key => $value) {
                /** @var Model $value */
                $local_id = $value->getId();
                $value->setRelationship($name, $set[$local_id] ?? null);
            }
        } else {
            $result->setRelationship($name, $set[$result->getId()] ?? null);
        }

        return $result;
    }
}