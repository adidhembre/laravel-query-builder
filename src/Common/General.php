<?php

namespace Aditya\QueryBuilder\Common;

trait General
{
    private function fromModelGetKeys($model,$key){
        if(method_exists($model,$key)){
            return $model->{$key}();
        }
        else if($model->{$key} != null){
            return $model->{$key};
        }
        return [];
    }
}