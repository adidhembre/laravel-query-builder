<?php

namespace Aditya\QueryBuilder\Sort;

class SortCache
{
    /**
     * Relations to be joined
     */
    public static $joins = [];

    /**
     * join method 'left', 'right', 'inner'
     */
    public static $join_method = 'left';
    
    /**
     * check if match found or not
     */
    public static $match_found = false;

    /**
     * column to be sorted
     */
    public static $sort_column = null;

    /**
     * To check if already grouped or not
     */

    public static $is_grouped = false;
}