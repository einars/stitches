<?php
// mysql support for stitches (via mysqli)

class db extends Database
{

    public static function connect()
    {
        $options = Database::$configuration;
        $opt_user_name = get_option($options, 'db.user', null);
        $opt_host      = get_option($options, 'db.host', null);
        $opt_database  = get_option($options, 'db.database', null);
        $opt_password  = get_option($options, 'db.password', null);
        $opt_port      = get_option($options, 'db.port', null);
        $opt_socket    = get_option($options, 'db.socket', null);
        $opt_sql_mode  = get_option($options, 'db.sql-mode', 'ansi,traditional');

        $opt_charset   = get_option($options, 'db.charset', 'utf8');

        $opt_debug     = R::is_debug_mode();

        $connection_id = @mysqli_connect($opt_host, $opt_user_name, $opt_password, $opt_database, $opt_port, $opt_socket);

        if ($connection_id) {
            mysqli_set_charset($connection_id, $opt_charset);
            mysqli_query($connection_id, "set sql_mode='$opt_sql_mode'"); // mysql defaults are insane
        }
        return $connection_id;
    }

    public static function get_last_error()
    {
        if (Database::$connection) {
            return mysqli_error(Database::$connection);
        }
    }

    public static function run_query($query)
    {
        return mysqli_query(db::get_connection_id(), $query);
    }

    public static function fetch($query_id)
    {
        return @mysqli_fetch_array($query_id, MYSQLI_BOTH);
    }


    public static function fetch_num($query_id)
    {
        return @mysqli_fetch_row($query_id);
    }


    public static function fetch_assoc($query_id)
    {
        return @mysqli_fetch_assoc($query_id);
    }

    public static function escape($str)
    {
        return mysqli_real_escape_string(db::get_connection_id(), $str);
    }


    public static function get_last_id()
    {
        list($curr_v) = db::get("select last_insert_id()");
        return intval($curr_v);
    }


    public static function num_rows($query_id)
    {
        return mysqli_num_rows($query_id);
    }

    public static function table_exists($table_name)
    {
        return db::one('show tables like %s', $table_name);
    }

    public static function field_exists($table_name, $field_name)
    {
        $field_name_lc = strtolower_utf($field_name);
        $fields = db::get_assoc_array('show columns from %S', $table_name);
        foreach($fields as $field=>$foo) {
            if (strtolower_utf($field) == $field_name_lc) {
                return true;
            }
        }
        return false;
    }

    public static function index_exists($table_name, $index_name)
    {
        $index_name_lc = strtolower_utf($index_name);
        $res = db::query('show index from %S', $table_name);
        while ($r = db::fetch_assoc($res)) {
            if (strtolower_utf($r['Key_name']) == $index_name_lc) {
                return true;
            }
        }
        return false;
    }

}
