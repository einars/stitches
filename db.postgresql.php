<?php
// postgres support for stitches

class db extends Database
{

    public static function connect()
    {
        $options       = Database::$configuration;
        $opt_user_name = get_option($options, 'db.user', null);
        $opt_host      = get_option($options, 'db.host', null);
        $opt_database  = get_option($options, 'db.database', null);
        $opt_password  = get_option($options, 'db.password', null);

        $opt_port      = get_option($options, 'db.port', null);

        $params = array();
        if ($opt_user_name) {
            $params[] = "user=$opt_user_name";
        }
        if ($opt_host) {
            $params[] = "host=$opt_host";
        }
        if ($opt_database) {
            $params[] = "dbname=$opt_database";
        }
        if ($opt_password) {
            $params[] = "password=$opt_password";
        }
        if ($opt_port) {
            $params[] = "port=$opt_port";
        }
        $connection_id = pg_pconnect(implode(' ', $params));

        return $connection_id;
    }

    public static function get_last_error()
    {
        if (Database::$connection) {
            return @pg_last_error(Database::$connection);
        }
    }

    public static function run_query($query)
    {
        return pg_query(db::get_connection_id(), $query);
    }

    public static function fetch($query_id)
    {
        return @pg_fetch_array($query_id);
    }


    public static function fetch_num($query_id)
    {
        return @pg_fetch_row($query_id);
    }


    public static function fetch_assoc($query_id)
    {
        return @pg_fetch_assoc($query_id);
    }

    public static function escape($str)
    {
        return pg_escape_string(db::get_connection_id(), $str);
    }


    public static function get_last_id()
    {
        return intval(db::one("select lastval()"));
    }


    public static function num_rows($query_id)
    {
        return pg_num_rows($query_id);
    }

    public static function table_exists($table_name)
    {

        $table_schema = null;
        if (strpos($table_name, '.') !== false) {
            list($table_schema, $table_name) = explode('.', $table_name);
        }
        $exists = db::one('select 1 from information_schema.columns where table_name=%s and table_catalog=current_database() and table_schema=%s',
            $table_name, any($table_schema, 'public'));
        return $exists == 1;
    }

    public static function field_exists($table_name, $field_name)
    {
        $table_schema = null;
        if (strpos($table_name, '.') !== false) {
            list($table_schema, $table_name) = explode('.', $table_name);
        }
        $exists = db::one('select 1 from information_schema.columns where column_name=%s and table_name=%s and table_catalog=current_database() and table_schema=%s',
            $field_name, $table_name, any($table_schema, 'public'));
        return $exists == 1;
    }

    public static function index_exists($__unused__table_name, $index_name)
    {
        list($check) = db::get('select relname from pg_class where relname=%s and relam > 0', $index_name);
        return !! $check;
    }
}
