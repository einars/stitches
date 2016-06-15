<?php
// "no-database" placeholder for stitches

class db extends Database
{

    public static function connect()
    {
        return 'shim';
    }

    public static function get_last_error()
    {
    }

    public static function run_query($query)
    {
        return true;
    }

    public static function fetch($query_id)
    {
    }


    public static function fetch_num($query_id)
    {
    }


    public static function fetch_assoc($query_id)
    {
    }

    public static function escape($str)
    {
        return $str;
    }


    public static function get_last_id()
    {
    }


    public static function num_rows($query_id)
    {
    }

    public static function table_exists($table_name)
    {
        return false;
    }

    public static function field_exists($table_name, $field_name)
    {
        return false;
    }

    public static function index_exists($table_name, $index_name)
    {
        return false;
    }

}

