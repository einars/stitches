<?php
// sqlite support for stitches

class db extends Database
{

    public static function connect()
    {
        $options = Database::$configuration;
        $opt_database  = get_option($options, 'db.database', 'media/site.db');
        $connection_id = @sqlite_open($opt_database);

        return $connection_id;
    }

    public static function get_last_error()
    {
        if (Database::$connection) {
            $last_error = sqlite_last_error(Database::$connection);
            if ($last_error) {
                return sqlite_error_string($last_error);
            }
        }
    }

    public static function run_query($query)
    {
        return sqlite_query(db::get_connection_id(), $query);
    }

    public static function fetch($query_id)
    {
        return @sqlite_fetch_array($query_id, SQLITE_BOTH);
    }


    public static function fetch_num($query_id)
    {
        return @sqlite_fetch_array($query_id, SQLITE_NUM);
    }


    public static function fetch_assoc($query_id)
    {
        return @sqlite_fetch_array($query_id, SQLITE_ASSOC);
    }

    public static function escape($str)
    {
        return sqlite_escape_string($str);
    }


    public static function get_last_id()
    {
        list($curr_v) = db::get("select last_insert_rowid()");
        return intval($curr_v);
    }


    public static function num_rows($query_id)
    {
        return sqlite_num_rows($query_id);
    }

    public static function table_exists($table_name)
    {
        return db::one('select 1 from sqlite_master where name=%s', $table_name);
    }

}
