<?php

namespace Aditya\QueryBuilder\Sort;

use Aditya\QueryBuilder\Common\General;
use Kirschbaum\PowerJoins\PowerJoins;

trait Sortable
{
    use General, PowerJoins;

    public function scopeSort($query,Array $key=null,$order='asc'){
        if($key==null) return $query;
        $model = $query->getModel();
        $this->mapAvailableKeys($model,$key);
        $this->applySorting($query,$key,$order);
        return $query;
    }

    private function applySorting($query,$key,$order){
        if(SortCache::$sort_column == null) SortCache::$sort_column = $key;
        $query->when(count(SortCache::$joins) > 0,function($q){
            $q->joinRelationship(implode('.',SortCache::$joins));
        })
        ->groupBy($query->getModel()->getTable().".id")
        ->sortBy(SortCache::$sort_column,$order);
    }

    private function mapAvailableKeys($model,$key){
        $this->initiateSortMapping();
        SortCache::$match_found = $this->matchSortKeysInModel($model,$key);
        $this->matchSortKeysFromThrough($model,$key);
        $this->matchSortKeysFromDeepThrough($model,$key);
    }

    private function initiateSortMapping(){
        SortCache::$match_found = false;
        SortCache::$sort_column = null;
        SortCache::$joins = [];
    }

    private function matchSortKeysFromDeepThrough($model,$key){
        if(SortCache::$match_found) return true;
        foreach($this->fromModelGetKeys($model,'sort_deep') as $deep){
            $child = $model->{$deep}()->getRelated()->getModel();
            SortCache::$joins = [$deep];
            SortCache::$match_found = $this->matchSortKeysInModel($child,$key);
            $this->matchSortKeysFromDeepThrough($child,$key);
            if(SortCache::$match_found){
                array_push(SortCache::$joins,$deep);
                array_push(SortCache::$joins,$deep);
                return true;
            };
        }
        SortCache::$joins = [];
        return false;
    }

    private function matchSortKeysFromThrough($model,$key){
        if(SortCache::$match_found) return true;
        foreach($this->fromModelGetKeys($model,'sort_through') as $through){
            if(SortCache::$match_found) return true;
            $through = explode(':',$through);
            $thr_model = $model->{$through[0]}()->getRelated();
            SortCache::$match_found = $this->matchSortKeysInModel($thr_model,$key);
            if(SortCache::$match_found){
                array_push(SortCache::$joins,$through[0]);
                return true;
            }
            if(isset($through[1])){
                foreach(explode(',',$through[1]) as $rel){
                    $rel_model = $thr_model->{$rel[0]}()->getRelated();
                    SortCache::$match_found = $this->matchSortKeysInModel($rel_model,$key);
                    if(SortCache::$match_found){
                        array_push(SortCache::$joins,$through[0]);
                        array_push(SortCache::$joins,$through[1]);
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function matchSortKeysInModel($model,$match){
        foreach($this->fromModelGetKeys($model,'sort') as $key => $column){
            $is_alias = gettype($key) == 'string';
            $key = $is_alias ? $key : $column;
            if($match == $key){
                SortCache::$sort_column = $model->getTable().".$column";
                return true;
            }
        };
        return false;
    }
}
