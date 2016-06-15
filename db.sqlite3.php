<?php
// sqlite3 support for stitches

class db extends Database
{

    public static function connect()
    {
        $options = Database::$configuration;
        $opt_database       = get_option($options, 'db.database', 'media/site.db');
        $sqlite = new SQLite3($opt_database);

        return $sqlite;
    }

    public static function get_last_error()
    {
        if (Database::$connection) {
            $c = Database::get_connection_id();
            return $c->lastErrorMsg();
        }
    }

    public static function run_query($query)
    {
        $c = Database::get_connection_id();
        return $c->query($query);
    }

    public static function fetch($query_id)
    {
        return $query_id->fetchArray();
    }


    public static function fetch_num($query_id)
    {
        return $query_id->fetchArray(SQLITE3_NUM);
    }


    public static function fetch_assoc($query_id)
    {
        return $query_id->fetchArray(SQLITE3_ASSOC);
    }

    public static function escape($str)
    {
        $c = Database::get_connection_id();
        return $c->escapeString($str);
    }


    public static function get_last_id()
    {
        list($curr_v) = db::get("select last_insert_rowid()");
        return intval($curr_v);
    }


    public static function num_rows($query_id)
    {
        if ($query_id->numColumns() && $query_id->columnType(0) != SQLITE3_NULL) {
            return 999;
        } else {
            return 0;
        }
    }

    public static function table_exists($table_name)
    {
        return db::one('select 1 from sqlite_master where name=%s', $table_name);
    }


    public static function field_exists($table, $field)
    {
        $old_fatality = Database::$is_query_error_fatal;
        Database::$is_query_error_fatal = false;
        $check = @db::query('select %S from %S limit 0',
            $field, $table);
        Database::$is_query_error_fatal = $old_fatality;
        if ($check) {
            return true;
        }
        return false;
    }
}
