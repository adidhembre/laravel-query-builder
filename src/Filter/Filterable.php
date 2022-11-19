<?php

namespace Aditya\QueryBuilder\Filter;

use Aditya\QueryBuilder\Common\General;

trait Filterable
{
    use General;

    public function scopeFilter($query,Array $filters=null){
        $model = $query->getModel();
        $this->initiateFilters($model,$filters);
        $this->runFilters($query,$model);
        $this->filterableDeepFilters($model,$query,$filters);
        return $query;
    }

    public function scopeSetThrough($query,$through=null){
        $this->registerThrough($query->getModel(),$through);
        return $query;
    }

    public function scopeAddFilters($query,$filters){
        if(is_array($filters)){
            $this->registerFilters($query->getModel(),$filters);
        }
        return $query;
    }

    public function scopeSetMeargeOption($query,$option='intersection'){
        FilterCache::$mearge_option = $option;
        return $query;
    }

    private function initiateFilters($model,$filters){
        $this->clearCache();
        $this->registerThrough($model);
        $this->registerAvailableKeys($model);
        $this->registerFilters($model,$filters);
    }

    private function runFilters($query,$model){
        $this->optimizeFilters();
        $this->handleFilters($query,$model);
    }

    private function handleFilters($query,$parent_model){
        $this->applyFilters($query);
        foreach(FilterCache::$through as $thr){
            if(is_array($thr)){
                $model = $parent_model->{$thr[0]}()->getRelated()->getModel();
                $class = get_class($model);
                $model_join_required = false;
                $childs_required = [];
                if(count(FilterCache::$model_keys[$class]) > 0){
                    $model_join_required = true;
                }
                foreach($thr[1] as $key){
                    $child = $model->{$key}()->getRelated()->getModel();
                    $class = get_class($child);
                    if(count(FilterCache::$model_keys[$class]) > 0){
                        array_push($childs_required,$key);
                        $model_join_required = true;
                    }
                }
                if($model_join_required){
                    $query->whereHas($thr[0],function($q) use($childs_required){
                        $this->applyFilters($q);
                        foreach($childs_required as $c){
                            $q->whereHas($c, function($q){
                                $this->applyFilters($q);
                            });
                        }
                    });
                }
            }
            else{
                if(count(FilterCache::$model_keys) > 0){
                    $query->whereHas($thr,function($q){
                        $this->applyFilters($q);
                    });
                }
            }
        }
    }

    private function applyFilters($query){
        $model = $query->getModel();
        $class = get_class($model);
        $filters = $this->fromModelGetFilters($model);
        foreach(FilterCache::$model_keys[$class] as $key){
            //from cache get index
            $filter = $filters[FilterCache::$available_keys[$key]];
            $value = FilterCache::$filters[$key];
            if(is_callable($filter)){
                $filter($query,$value);
            }
            else if(is_array($filter)){
                $child = $model->{$filter[0]}()->getRelated()->getModel();
                $query->whereHas($filter[0],function($q)use($filter,$value,$model,$child){
                    $model->queryBuilderForFilters($q,$child,$filter[1],$value);
                });
            }
            else if(method_exists($model,$filter)){
                $model->{$filter}($query,$value);
            }
            else{
                $this->queryBuilderForFilters($query,$model,$filter,$value);
            }
        }
    }

    private function queryBuilderForFilters($query,$model,$column,$value){
        $table = $model->getTable();
        $column = str_replace("@","",$column);
        $func = is_array($value) && count($value) > 1 ? 'whereIn' : 'where';
        $query->{$func}("$table.$column",$value);
    }

    private function optimizeFilters(){
        $keys = array_keys(FilterCache::$filters);
        foreach(array_keys(FilterCache::$model_keys) as $key){
            FilterCache::$model_keys[$key] = array_intersect(FilterCache::$model_keys[$key],$keys);
        }
    }

    private function registerFilters($model,$filters){
        $filters = $filters != null ? $filters : request()->only(array_keys(FilterCache::$available_keys));
        $processor_available = method_exists($model,'processFilterValues');
        foreach($filters as $key => $value){
            $value = $processor_available ? $model->processFilterValues($key,$value) : $value;
            $value = is_object($value) ? $value->toArray() : $value;
            $this->meargeFilter($key,$value);
        }
    }

    private function meargeFilter($key,$value){
        if(isset(FilterCache::$filters[$key])){
            if($this->validateForMearge($value)){
                if(FilterCache::$mearge_option = 'intersection'){
                    FilterCache::$filters[$key] = array_intersect(FilterCache::$filters[$key],$value);
                    return true;
                }
                if(FilterCache::$mearge_option = 'union'){
                    FilterCache::$filters[$key] = array_merge(FilterCache::$filters[$key],$value);
                    return true;
                }
            }
        }
        FilterCache::$filters[$key] = $value;
        return true;
    }

    private function validateForMearge($value){
        if(is_array($value)){
            foreach($value as $k => $v){
                if(gettype($k) == 'string'){
                    return false;
                }
            }
        }
        else{
            return false;
        }
        return true;
    }

    private function registerAvailableKeys($model){
        $this->extractAvailableFilterKeysFromModel($model);
        foreach(FilterCache::$through as $thr){
            if(is_array($thr)){
                $model = $model->{$thr[0]}()->getRelated()->getModel();
                $this->extractAvailableFilterKeysFromModel($model);
                foreach($thr[1] as $key){
                    $child = $model->{$key}()->getRelated()->getModel();
                    $this->extractAvailableFilterKeysFromModel($child);
                }
            }
            else{
                $model = $model->{$thr}()->getRelated()->getModel();
                $this->extractAvailableFilterKeysFromModel($model);
            }
        }
    }

    private function cleanAvailableKeys(){
        FilterCache::$model_keys = [];
        FilterCache::$available_keys = [];
    }

    private function cleanThrough(){
        FilterCache::$lock_through = false;
        FilterCache::$through = [];
    }

    private function registerThrough($model,$through=null){
        if(!FilterCache::$lock_through){
            $through = $through==null ? $this->fromModelGetThrough($model) : $through;
            if($through != null){
                foreach($through as $t){
                    $thr = explode(':',$t);
                    $thr = count($thr) == 2 ? [$thr[0],explode(',',$thr[1])] : $thr[0]; 
                    array_push(FilterCache::$through,$thr);
                }
            }
        }
    }

    private function extractAvailableFilterKeysFromModel($model){
        $class = get_class($model);
        FilterCache::$model_keys[$class] = [];
        foreach($this->fromModelGetFilters($model) as $key => $filter){
            $is_alias = gettype($key) == 'string';
            $filter = $is_alias ? $key : $filter;
            FilterCache::$available_keys[$filter] = $key;
            array_push(FilterCache::$model_keys[$class],$filter);
        }
    }

    private function fromModelGetFilters($model){
        return $this->fromModelGetKeys($model,'filters');
    }

    private function fromModelGetThrough($model){
        return $this->fromModelGetKeys($model,'filter_through');
    }

    private function fromModelGetDeepThrough($model){
        return $this->fromModelGetKeys($model,'deep_through');
    }

    private function filterableDeepFilters($model,$query,$filters){
        $deep_through = $this->fromModelGetDeepThrough($model);
        foreach($deep_through as $thr){
            if(method_exists($model,$thr)){
                $this->cleanAvailableKeys();
                $this->cleanThrough();
                $child = $model->{$thr}()->getRelated()->getModel();
                $this->initiateFilters($child,$filters);
                $this->optimizeFilters();
                if($this->validateDeepFilter()){
                    $query->whereHas($thr,function($q) use($child){
                        $this->handleFilters($q,$child);
                    });
                }
            }
        }
    }

    private function validateDeepFilter(){
        foreach(FilterCache::$model_keys as $val){
            if(count($val) != 0) return true;
        }
        return false;
    }

    private function clearCache(){
        $this->cleanAvailableKeys();
        $this->cleanThrough();
        FilterCache::$filters = [];
    }
}
