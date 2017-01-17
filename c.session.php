<?php

# Session
#
# Database-backed session management.
#

class Session {

    static $enabled = false;

    static function initialize()
    {
        s::on('install', 'session_install');

        if (s::cli()) {
            return; // no session on command-line
        }

        if (get('sessions.enabled', s::$config) === false) {
            return;
        }

        if (defined('STITCHES_INSTALLING')) {
            return;
        }


        if (session::use_db()) {

            Session::$enabled = true;
            session_set_save_handler(
                'sx_open',
                'sx_close',
                'sx_read',
                'sx_write',
                'sx_destroy',
                'sx_clean'
            );
        }

        if ( ! session_id()) {
            if ($session_lifetime = get('session.lifetime', s::$config)) {
                session_set_cookie_params($session_lifetime);
            }
            session_start();
        }
    }

    static function get($setting)
    {
        return get($setting, $_SESSION);
    }

    static function set($setting, $value = true)
    {
        $_SESSION[$setting] = $value;
    }

    static function peek($session_id)
    {
        if (Session::$enabled && ($data = sx_read($session_id))) {
            return sx_decode($data);
        }
    }

    static function ip_of($session_id)
    {
        return db::one('select ip_address from sessions where session_id=%s', $session_id);
    }

    static function user_agent_of($session_id)
    {
        return db::one('select user_agent from sessions where session_id=%s', $session_id);
    }

    static function destroy($session_id)
    {
        db::query('delete from sessions where session_id=%s', $session_id);
    }

    static function cleanup()
    {
        if ($session_lifetime = get('session.lifetime', s::$config)) {
            $max_activity = time() - (int)($session_lifetime);
        } else {
            $max_activity = time() - 240 * 60; // 4h timeout
        }
        db::query('delete from sessions where last_access_time < %s', date('Y-m-d H:i', $max_activity));
    }
    static function use_db()
    {
        if ( ! s::db()) return false;
        if (defined('STITCHES_INSTALLING')) return false;
        if (get('db.alien', s::$config)) return false;
        return true;
    }
}


function sx_open()
{
    return true;
}

function sx_close()
{
    return true;
}

function sx_read($session_id)
{
    $session = db::one('select data from sessions where session_id=%s', $session_id);
    return $session;
}

function sx_write($session_id, $data)
{
    if ( ! $data) {
        db::query('delete from sessions where session_id=%s', $session_id);
    } else {
        $check = db::one('select session_id from sessions where session_id=%s', $session_id);
        if ($check) {
            db::query('update sessions set
                last_access_time=now(), ip_address=%s, data=%s, user_agent=%s where session_id=%s',
                    get('REMOTE_ADDR', $_SERVER), $data, get('HTTP_USER_AGENT', $_SERVER), $session_id);
        } else {
            db::insert('sessions', array(
                'session_id' => $session_id,
                'ip_address' => get('REMOTE_ADDR', $_SERVER),
                '~last_access_time' => 'now()',
                'data' => $data,
                'user_agent' => substr(get('HTTP_USER_AGENT', $_SERVER), 0, 250),
            ));
        }
    }
    return true;
}

function sx_destroy($session_id)
{
    Session::destroy($session_id);
    return true;
}

function sx_clean()
{
    global $config;
    if ( ! get('session.manual-cleanup', $config)) {
        Session::cleanup();
    }
    return true;
}



# sx_decode
# ---------
# Unserialize session data. Used in Session::peek(...).
# Based on session_real_decode by bmorel@ssi.fr
#
# Will not work if php is hardened via suhosin and similar.
#
function sx_decode($str)
{
    $str = (string)$str;

    $endptr = strlen($str);
    $p = 0;

    $serialized = '';
    $items = 0;
    $level = 0;

    while ($p < $endptr) {
        $q = $p;
        while ($str[$q] != '|')
            if (++$q >= $endptr) break 2;

        if ($str[$p] == '!') {
            $p++;
            $has_value = false;
        } else {
            $has_value = true;
        }

        $name = substr($str, $p, $q - $p);
        $q++;

        $serialized .= 's:' . strlen($name) . ':"' . $name . '";';

        if ($has_value) {
            for (;;) {
                $p = $q;
                switch ($str[$q]) {
                    case 'N': /* null */
                    case 'b': /* boolean */
                    case 'i': /* integer */
                    case 'd': /* decimal */
                        do $q++;
                        while ( ($q < $endptr) && ($str[$q] != ';') );
                        $q++;
                        $serialized .= substr($str, $p, $q - $p);
                        if ($level == 0) break 2;
                        break;
                    case 'R': /* reference  */
                        $q+= 2;
                        for ($id = ''; ($q < $endptr) && ($str[$q] != ';'); $q++) $id .= $str[$q];
                        $q++;
                        $serialized .= 'R:' . ($id + 1) . ';'; /* increment pointer because of outer array */
                        if ($level == 0) break 2;
                        break;
                    case 's': /* string */
                        $q+=2;
                        for ($length=''; ($q < $endptr) && ($str[$q] != ':'); $q++) $length .= $str[$q];
                        $q+=2;
                        $q+= (int)$length + 2;
                        $serialized .= substr($str, $p, $q - $p);
                        if ($level == 0) break 2;
                        break;
                    case 'a': /* array */
                    case 'O': /* object */
                        do $q++;
                        while ( ($q < $endptr) && ($str[$q] != '{') );
                        $q++;
                        $level++;
                        $serialized .= substr($str, $p, $q - $p);
                        break;
                    case '}': /* end of array|object */
                        $q++;
                        $serialized .= substr($str, $p, $q - $p);
                        if (--$level == 0) break 2;
                        break;
                    default:
                        return false;
                }
            }
        } else {
            $serialized .= 'N;';
            $q+= 2;
        }
        $items++;
        $p = $q;
    }
    $out = unserialize( 'a:' . $items . ':{' . $serialized . '}' );
    return $out;
}


function session_install()
{
    class inst_sessions extends InstallerTableTask {
        function get_sql() {
            $timestamp = s::get('database') == 'postgresql' ? 'timestamp with time zone' : 'datetime';
            return "
create table sessions
(
session_id char(32) primary key,
last_access_time $timestamp,
ip_address varchar(128),
user_agent varchar(250),
data text
);";
        }
        function get_table_name() {
            return 'sessions';
        }
    }
}
