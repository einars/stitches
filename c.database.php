<?php

# Database
# ========
#
# Stitches database abstraction. 
#
# The actual implementations for various engines are found in db.*.php.
#
# Only one actual connection can be active at the same time.
#
# Configuration is expected to be passsed in the s::configure call.
# Settings:
#
#  db.engine:   mysql, postgresql, sqlite, sqlite3
#  db.user:     username
#  db.password: password
#  db.database: database (database file for sqlite)
#  db.host:     host to connect to
#  db.port:     tcp port for connecting
#
# Mysql-specific settings:
#  db.socket:   socket to use instead of tcp port
#  db.charset:  default utf8
#  db.sql-mode: default "ansi,traditional"
#
# No further initialization (after s::configure) necessary, just take and use.
#
#   $users = db::get_assoc_list('select * from users');
#
# Error handling:
# (..)



class Database {

    static $configuration = null;
    static $connection = null;
    static $is_query_error_fatal = true;


    # db::set_connection_information
    # ------------------------------
    # Receives the database settings, typically from global $config,
    # and stores for itself.
    #
    static function load($config)
    {
        $engine = get('db.engine', $config, null);
        if ( ! $engine || $engine === 'none') {
            $engine = null;
            require_once(dirname(__FILE__) . '/db.shim.php');
        } else {
            require_once(dirname(__FILE__) . '/db.' . $engine . '.php');
        }

        Database::$configuration = $config;
        return $engine;
    }


    # db::query
    # ---------
    # Execute sql query and return the handle to results.
    # On error, if Database::$is_query_error_fatal, will terminate,
    # otherwise only a system warning will be issued.
    #
    # Example:
    #   $res = db::query('select user_name from table where user_id=%d', $user_id);
    #   while(list($user_name) = db::fetch($res)) {
    #     echo $user_name;
    #   }
    #
    static function query($query /*, ... */)
    {
        if ( ! $query) {
            s::error('Empty query passed.');
        }

        $start_time = microtime(true);
        db::acquire_connection();
        if (func_num_args() > 1) {
            $args = func_get_args();
            $query = db::sprintf_array($args);
        }
        if ( ! ($query_result = db::run_query($query))) {
            if (Database::$is_query_error_fatal) {
                s::error("Cannot execute %s:\n%s"
                    , $query
                    , db::get_last_error()
                );
            } else {
                s::warn("Cannot execute %s:\n%s"
                    , $query
                    , db::get_last_error()
                );
            }
        }

        // you may listen on 'query' events
        s::emit('query', [
            'time'   => microtime(true) - $start_time,
            'query'  => $query,
            'result' => $query_result
        ]);
        return $query_result;
    }

    # db::get_list
    # ------------
    #
    # Example:
    #
    #   db::get_list('select user_id from users')
    #   > ['john', 'scott', 'barbara']
    #
    static function get_list($query /* ... */)
    {
        if (func_num_args() > 1) {
            $args = func_get_args();
            $query = db::sprintf_array($args);
        }
        $out = [];
        $res = db::query($query);
        while (list($n) = db::fetch_num($res)) {
            $out[] = $n;
        }
        return $out;
    }


    # db::one
    # -------
    # Fetch first value of first row and return it directly
    #
    # Example:
    #
    #   db::one('select count(*) from users')
    #   > 3
    #
    static function one($query /* ... */)
    {
        if (func_num_args() > 1) {
            $args = func_get_args();
            $query = db::sprintf_array($args);
        }
        list($n) = db::get_num($query);
        return $n;
    }

    # db::get_all
    # -----------
    # Fetch all rows (as numeric + associative) and return as list
    #
    # Example:
    #
    #   db::get_all('select id, name from users')
    #   > [ {1, 'john', user_id: 1, user_name: 'john'}, ... ]
    #
    static function get_all($query /* ... */)
    {
        if (func_num_args() > 1) {
            $args = func_get_args();
            $query = db::sprintf_array($args);
        }
        $out = array();
        $res = db::query($query);
        while ($r = db::fetch($res)) {
            $out[] = $r;
        }
        return $out;
    }

    # db::get_kw_array
    # ----------------
    # Return key-value map, key being the first column, value - the second.
    #
    # Example:
    #
    #   db::get_kw_array('select id, name from users')
    #   > {1: 'john', 2: 'scott', 3: 'barbara'}
    #
    static function get_kw_array($query /*, ... */)
    {
        if (func_num_args() > 1) {
            $args = func_get_args();
            $query = db::sprintf_array($args);
        }
        $res = db::query($query);
        $out = array();
        while (list($k, $w) = db::fetch_num($res)) {
            $out[$k] = $w;
        }
        return $out;
    }



    # db::get
    # -------
    # Return first row of the results, both numerically and associatively
    # indexed.
    #
    # Related: db::get, db::get_assoc, db::get_num, db::get_object
    # Related: db::fget, db::fget_assoc
    #
    static function get($query /*, ... */)
    {
        if (func_num_args() > 1) {
            $args = func_get_args();
            $query = db::sprintf_array($args);
        }
        return db::fetch(db::query($query));
    }

    # db::get_assoc
    # -------------
    # Return first row of the results, associatively indexed.
    #
    # Related: db::get, db::get_assoc, db::get_num, db::get_object
    # Related: db::fget, db::fget_assoc
    #
    static function get_assoc($query /*, ... */)
    {
        if (func_num_args() > 1) {
            $args = func_get_args();
            $query = db::sprintf_array($args);
        }
        return db::fetch_assoc(db::query($query));
    }


    # db::get_object
    # -------------
    # Return first row of the results as an stdobj object.
    #
    # Related: db::get, db::get_assoc, db::get_num, db::get_object
    # Related: db::fget, db::fget_assoc
    #
    static function get_object($query /*, ... */)
    {
        if (func_num_args() > 1) {
            $args = func_get_args();
            $query = db::sprintf_array($args);
        }
        return db::fetch_object(db::query($query));
    }


    # db::get_num
    # -----------
    # Return first row of the results, numerically indexed.
    #
    # Related: db::get, db::get_assoc, db::get_num, db::get_object
    # Related: db::fget, db::fget_assoc
    #
    static function get_num($query /*, ... */)
    {
        if (func_num_args() > 1) {
            $args = func_get_args();
            $query = db::sprintf_array($args);
        }
        return db::fetch_num(db::query($query));
    }


    # db::fget
    # --------
    # Failsafe "db::get".
    # Return first row of the results, show page not found if no data
    # available.
    #
    # Useful for ensuring that data actually exists.
    #
    # Example:
    #
    #   $user_id = get('user_id'); // GET or POST
    #   $user = db::fget('select * from users where id=%d', $user_id);
    #   dump($user); // user will always exist
    #
    static function fget($query /*, ... */)
    {
        if (func_num_args() > 1) {
            $args = func_get_args();
            $query = db::sprintf_array($args);
        }
        $ret = db::fetch(db::query($query));
        if ( ! $ret) {
            Errors::page_bad_request();
        }
        return $ret;
    }

    # db::fget_assoc
    # --------------
    # failsafe "db::get_assoc. See db::fget.
    #
    static function fget_assoc($query /*, ... */)
    {
        if (func_num_args() > 1) {
            $args = func_get_args();
            $query = db::sprintf_array($args);
        }
        $ret = db::fetch_assoc(db::query($query));
        if ( ! $ret) {
            Errors::page_bad_request();
        }
        return $ret;
    }


    # db::get_assoc_list
    # ------------------
    # Return all rows in a list, every list element is associative array.
    #
    #   db::get_assoc_list('select * from users');
    #   > [ { user_id: 1, user_name: john}, ... ]
    #
    static function get_assoc_list($query /*, ... */)
    {
        if (func_num_args() > 1) {
            $args = func_get_args();
            $query = db::sprintf_array($args);
        }
        $res = db::query($query);
        $out = array();
        while ($r = db::fetch_assoc($res)) {
            $out[] = $r;
        }
        return $out;
    }

    # db::get_assoc_mao
    # ------------------
    # Return all rows in an associative array, first column is used as a key.
    #
    #   db::get_assoc_map('select user_id, user_name from users'); // keyed by user_id
    #   > [ 1: { user_id: 1, user_name: john}, 2: ... ]
    #
    static function get_assoc_map($query /*, ... */)
    {
        if (func_num_args() > 1) {
            $args = func_get_args();
            $query = db::sprintf_array($args);
        }
        $res = db::query($query);
        $out = array();
        while ($r = db::fetch_assoc($res)) {
            list($field_name, $first_value) = each($r);
            if (isset($out[$first_value])) {
                s::warning("Duplicate key «%s».\n%s", $first_value, $query);
            }
            $out[$first_value] = $r;
        }
        return $out;
    }

    static function get_connection_id()
    {
        if ( ! Database::$connection) {
            db::acquire_connection();
        }
        return Database::$connection;
    }

    static function quote($what)
    {
        return db::escape($what);
    }

    static function acquire_connection()
    {

        if ( ! Database::$connection) {
            Database::$connection = db::connect();
            Database::$configuration = null; // cleanup
            if ( ! Database::$connection) {
                $error = s::get('last_error');
                @ob_end_clean();
                s::error("Connection to database failed.\n%s", $error);
            }
        }
    }

    static function sprintf($format /* ... */) 
    {
        $args = func_get_args();
        return db::sprintf_array($args);
    }

    static function sprintf_array($args)
    {
        if (sizeof($args) == 1) {
            return $args[0];
        }

        $chunks = array();
        $format = $args[0];
        $offset = 0;
        $param = 1;
        while (($pos = strpos($format, '%')) !== FALSE) {
            $chunks[] = substr($format, 0, $pos);
            $specifier = $format[$pos + 1];
            switch($specifier) {
            case 's':
                if ( ! array_key_exists($param, $args)) {
                    s::error('sprintf_array: not enough parameters.');
                }
                $chunks[] = db::as_string($args[$param]);
                $param += 1;
                break;
            case 'S':
                // do not enquote the string with %S
                if ( !array_key_exists($param, $args)) {
                    s::error('sprintf_array: not enough parameters.');
                }
                $chunks[] = db::as_string($args[$param], false);
                $param += 1;
                break;

            case 'd':
                if ( ! array_key_exists($param, $args)) {
                    s::error('sprintf_array: not enough parameters.');
                }
                $chunks[] = db::as_int($args[$param]);
                $param += 1;
                break;
            case 'f':
                if ( ! array_key_exists($param, $args)) {
                    s::error('sprintf_array: not enough parameters.');
                }
                $chunks[] = db::as_float($args[$param]);
                $param += 1;
                break;
            case '%':
                $chunks[] = '%';
                break;
            default:
                $chunks[] = '%';
                $chunks[] = $specifier;
            }
            $pos += 2;
            $format = substr($format, $pos);
        }
        $chunks[] = $format;
        return implode('', $chunks);


    }


    # db::insert
    # ----------
    # Insert ar record (specified by assoc array) in the database.
    # Fields starting with '.' are skipped.
    # Fields starting with '~' are inserted as-is, without escaping (typically for your sql).
    #
    # Example
    #   db::insert('users', [
    #     '.system-data' => 'abc123',
    #     'user_name' => 'john',
    #     '~created' => 'now()', // sic!
    #   ]);
    #
    # You may get the inserted id by db::get_last_id().
    #
    static function begin()
    {
        return db::query('begin');
    }
    static function commit()
    {
        return db::query('commit');
    }
    static function rollback()
    {
        return db::query('rollback');
    }
    static function insert($table, $values)
    {
        $fields = array();
        $vals = array();
        foreach($values as $k=>$v) {
            if ($k[0] == '.') {
                // skip
            } else if ($k[0] == '~') {
                // ~field_name skips escaping
                $fields[] = trim($k, '~');
                $vals[] = $v;
            } else {
                $fields[] = $k;
                $vals[] = db::as_string($v);
            }
        }
        db::query(sprintf('insert into %s (%s) values (%s)'
            , $table
            , implode(', ', $fields)
            , implode(', ', $vals)
        ));
    }

    static function as_string($param, $quote = true)
    {
        // $quote=     true       false
        // ---------------------------------
        // string      'string'   string
        // st'ring     'st\'ring' st'ring
        // null        null       null
        // emptystr    null       emptystr   sic!

        if ($param === null) {
            return 'null';
        }
        if (is_string($param)) {
            $param = trim($param);
            if ($param === '') {
                // %S returns empty string for empty string
                return $quote ? 'null' : '';
            }
            if ($quote) {
                return "'" . db::escape($param) . "'";
            } else {
                return $param; // not escaped
            }
        }
        if (is_int($param)) {
            return sprintf('%d', $param);
        }
        if (is_bool($param)) {
            return $param ? 'true' : 'false';
        }
        if (is_array($param)) {
            if ($quote) {
                $process = array();
                foreach($param as $k) {
                    $process[] = db::as_string($k);
                }
            } else {
                $process = $param;
            }
            if (sizeof($process)) {
                return implode(', ', $process);
            } else {
                return 'null';
            }
        }
        // let system decide
        return $quote ? "'$param'" : "$param";
    }


    static function as_int($param)
    {
        if ($param === null) return 'null';
        if (is_array($param)) {
            if (!sizeof($param)) {
                return 'null';
            }
            $process = array();
            foreach($param as $k) {
                $process[] = db::as_int($k);
            }
            return implode(', ', $process);
        }
        if (is_int($param) or is_double($param)) {
            return (string) ((int)$param);
        }
        return sprintf('%d', $param);
    }


    static function as_float($param)
    {
        if ($param === null) return 'null';
        return (string)(sprintf('%0.8f', $param) + 0);
    }


    static function booleanize(&$kw_array, $boolean_keys)
    {
        if ( ! $kw_array) {
            return;
        }
        if (is_string($boolean_keys)) {
            $boolean_keys = array_from_comma_string($boolean_keys);
        }
        foreach($boolean_keys as $key) {
            $key = trim($key);
            $kw_array[$key] = booleanize($kw_array[$key]);
        }
        return $kw_array;
    }



    // override me
    static function connect()
    {
        R::error('Not implemented');
    }

    // override me
    static function get_last_error()
    {
        R::error('Not implemented');
    }

    // override me
    static function get_last_id()
    {
        R::error('Not implemented');
    }

    // override me
    static function fetch($query_id)
    {
        R::error('Not implemented');
    }

    // override me
    static function fetch_num($query_id)
    {
        R::error('Not implemented');
    }

    // override me
    static function fetch_assoc($query_id)
    {
        R::error('Not implemented');
    }

    // override me
    // do not use directly
    static function run_query($query_id)
    {
        R::error('Not implemented');
    }

    // override me
    static function escape($something)
    {
        r::error('not implemented');
    }

    // override me
    static function num_rows($something)
    {
        r::error('not implemented');
    }

    // override me
    public static function table_exists($table_name)
    {
        R::error('Not implemented');
    }

    // override me
    public static function field_exists($table_name, $field_name)
    {
        R::error('Not implemented');
    }

    // override me
    public static function index_exists($table_name, $index_name)
    {
        R::error('Not implemented');
    }

    // no need to override this
    static function fetch_object($query_id, $boolean_keys = null)
    {
        $x = db::fetch_assoc($query_id);
        if ($x) {
            if ($boolean_keys) {
                db::fixup_booleans($x, $boolean_keys);
            }
            return (object)$x;
        } else {
            return null;
        }
    }





}
