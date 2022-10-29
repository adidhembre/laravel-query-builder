<?php

namespace Aditya\QueryBuilder\Filter;

class FilterCache
{
    /**
     * To Meagre multiple filters with same key
     * Available options are 'intersection', 'union', 'overlap'
     */
    public static $mearge_option = 'intersection';

    /**
     * To remember available keys with index
     */
    public static $available_keys = [];

    /**
     * To remember model and keys relation
     */
    public static $model_keys = [];

    /**
     * To remember if the through is already override or not
     */
    public static $lock_through = false;

    /**
     * To remember through in array format
     */
    public static $through = [];

    /**
     * Processed Filters
     */
    public static $filters = [];
}