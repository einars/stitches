<?php

# A junkyard of random, useful functions


require_once(dirname(__FILE__) . '/options.php');


{
    # get, get_*
    # ----------
    #
    # Used for safe array access.
    #
    # get('key', $array) or get('key, $array, 'default') is the preferred way
    # to access array members that might be absent.
    #
    # get(...) will always return string!
    #
    # Leaving $array empty, $_GET and $_POST will searched, so
    # get('q') will safely return GET/POST parameter 'q'.
    #
    # get_int, get_bool, get_double will safely cast the parameter (or array
    # value) to the desired type, returning null if cast fails.

    function get($name, $array = null, $default = null)
    {
        $value = null;

        if ($array === null) {
            $value = array_key_exists($name, $_GET) ? $_GET[$name] : null;
            if ($value === null) {
                $value = array_key_exists($name, $_POST) ? $_POST[$name] : null;
            }
        } else {
            $value = array_key_exists($name, $array) ? $array[$name] : null;
        }

        return $value === null ? $default : $value;
    }


    function get_string($name, $array = null, $default = null)
    {
        $n = get($name, $array, $default);
        if ($n === null || $n === '') {
            return null;
        }

        return is_string($n) ? $n : ('' . $n);
    }

    function get_int($name, $array = null, $default = null)
    {
        $n = get($name, $array, $default);
        if ($n === null) {
            return null;
        }
        if ((int)$n === 0 and $n != '0') {
            return null;
        }
        return (int)$n;
    }

    function get_decimal($name, $array = null, $default = null)
    {
        $s = get($name, $array, $default);
        if ( ! $s) {
            return $s;
        }
        if (preg_match('/^-?[\d]+(\.?|\.0+)$/', $s)) {
            return (int)$s;
        }
        $s = str_replace(',', '.', $s);
        if (preg_match('/^-?[\d]+\.\d*$/', $s)) {
            return (double)$s;
        }
        return null;
    }

    function get_bool($name, $array = null, $default = null)
    {
        return booleanize(get($name, $array, $default));
    }

    function get_double($name, $array = null, $default = null)
    {
        $n = get($name, $array, $default);
        if ($n === null) return null;
        $cvt = (double)str_replace(',', '.', $n);
        if ($cvt === 0.0 and $n != '0' and $n != '0.0') return null;
        return $cvt;
    }



    function get_array($name, $array = null)
    {
        # sic: no default supported.
        $n = get($name, $array);
        if ($n === null) return array(); // sic: not null, but an empty array
        if ( ! is_array($n)) {
            return array($n);
        }
        return $n;
    }
}


# booleanize
# ----------
# convert any value to boolean, taking into account the values usually returned
# from postgresql (literal 't') or mysql (1) or checkbox-post ('on')

function booleanize($v)
{
    if ($v === true) return $v;
    if ($v === false) return $v;
    if ( ! $v) return false;

    return ($v === 'on' or $v[0] === 't' or $v[0] === 'T' or $v === 1);
}

# absolute_url
# ------------
# append http/https and domain name
# does not process .. and stuff like that
#
# absolute_url('/') = http://localhost/
# absolute_url('?foo') = http://localhost/?foo
# etc
#
# pointless when run from CLI: beware when using in cron.
#

function absolute_url($url)
{

    $method = get('HTTPS', $_SERVER) == 'on' ? 'https://' : 'http://';

    if (strpos($url, '://')) {
        list($current_method, $rest) = explode('://', $url);
    } else {
        $current_method = null;
        $rest = null;
    }

    if ($url and $url[0] == '?') {
        if (strpos($_SERVER['REQUEST_URI'], '?') === false) {
            $url = $method . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . $url;
        } else {
            $url = $method . $_SERVER['HTTP_HOST'] . substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], '?')) . $url;
        }
    } else if ($url and $url[0] == '/') {
        $url = $method . $_SERVER['HTTP_HOST'] . $url;
    } else if ($current_method and $rest) {
        // no changes required
    } else {
        $url = $method . $_SERVER['HTTP_HOST'] . rtrim($_SERVER['REQUEST_URI'], '/') . '/' . $url;
    }

    return $url;

}

# any
# ---
# Return first non-null parameter.
# Empty string ('') is treated as null by convention.
#
#   $display_name = any($real_name, $email, "id=$id");
#
function any()
{
    $params = func_get_args();
    $param = null;
    foreach($params as $param) {
        if ($param !== null && $param !== '') {
            return $param;
        }
    }
    return $param;
}




function starts_with($needle, $haystack)
{
    if ($needle === null or $needle === '') {
        return $haystack !== null;
    }
    return substr($haystack, 0, strlen($needle)) === $needle;
}
function ends_with($needle, $haystack)
{
    if ($needle === null or $needle === '') {
        return $haystack !== null;
    }
    return substr($haystack, -strlen($needle)) === $needle;
}


# format_numeric
# --------------
#
# format number based on (latvian) singular/plural counting grammar
# TBD: some ideas about localizations
#
#    format_numeric(1, '%d piece', '%d pieces', 'none')
#    > 1 piece
#    format_numeric(30, '%d piece', '%d pieces', 'none')
#    > 30 pieces
#    format_numeric(0, '%d piece', '%d pieces', 'none')
#    > none
#
function format_numeric($num, $single, $multiple, $opt_zero = null)
{
    if ($num == 0 and $opt_zero !== null) {
        return $opt_zero;
    }
    return sprintf(($num % 10 == 1 && $num % 100 != 11) ? $single : $multiple, $num);
}


# array_from_comma_string
# -----------------------
#
# Explode comma-delimited array, trimming elements.
#
#    array_from_comma_string('Mo, Tu, We')
#    > ['Mo', 'Tu', 'We']
#
function array_from_comma_string($string)
{
    if ( ! $string) {
        return array();
    }

    $elems = explode(',', $string);
    $elems = array_map('trim', $elems);
    $elems = array_filter($elems);
    return $elems;
}

{
    # Date/time manipulation

    function timestamp_from_dmy($dmyhm)
    {

        if ($dmyhm) {
            if (preg_match('/^(\d?\d)\.(\d?\d)\.(\d\d\d\d)\.?$/', $dmyhm, $m)) {
                return mktime(0, 0, 0, $m[2], $m[1], $m[3]);
            }

            if (preg_match('/^(\d?\d)\.(\d?\d)\.(\d\d\d\d) (\d\d?):(\d\d)/', $dmyhm, $m)) {
                return mktime($m[4], $m[5], 0, $m[2], $m[1], $m[3]);
            }
        }
    }

    function timestamp_from_ymd($ymdhm)
    {
        if ($ymdhm) {
            if (preg_match('/^(\d\d\d\d)-(\d\d)-(\d\d)$/', $ymdhm, $m)) {
                return mktime(0, 0, 0, $m[2], $m[3], $m[1]);
            }

            if (preg_match('/^(\d\d\d\d)-(\d\d)-(\d\d) (\d\d?):(\d\d)/', $ymdhm, $m)) {
                return mktime($m[4], $m[5], 0, $m[2], $m[3], $m[1]);
            }
        }
    }


    function ymd_from_timestamp($ts)
    {
        $h = date(' H:i', $ts);
        if ($h == ' 00:00') {
            $h = '';
        }
        if ($ts) {
            return date('Y-m-d', $ts) . $h;
        } else {
            return null;
        }
    }

    function ymdhm_from_timestamp($ts)
    {
        if ($ts) {
            return date('Y-m-d H:i', $ts);
        } else {
            return null;
        }
    }

    function dmy_from_timestamp($ts, $skip_time = false)
    {
        $h = date(' H:i', $ts);
        if ($skip_time or $h == ' 00:00') {
            $h = '';
        }
        if ($ts) {
            return date('d.m.Y', $ts) . $h;
        } else {
            return null;
        }
    }

    function dmyhm_from_timestamp($ts)
    {
        if ($ts) {
            return date('d.m.Y H:i', $ts);
        } else {
            return null;
        }
    }


    function dmy_from_ymd($ymd)
    {
        return dmy_from_timestamp(timestamp_from_ymd($ymd));
    }

}


# first_key
# ---------
#
# Returns the first key of an array.
# Related: first_key, last_key, first_value, last_value.
#
#   first_key(['foo' => 1, 'bar' => 2])
#   > 'foo'
#
function first_key($array)
{
    if ($array === null) {
        return null;
    }
    $keys = array_keys($array);
    return $keys ? $keys[0] : null;
}

# last_key
# ---------
#
# Returns the last key of an array.
# Related: first_key, last_key, first_value, last_value.
#
#   last_key(['foo' => 1, 'bar' => 2])
#   > 'bar'
#
function last_key($array)
{
    if ($array === null) {
        return null;
    }
    $keys = array_keys($array);
    return $keys ? $keys[sizeof($keys) - 1] : null;
}


# first_value
# -----------
#
# Returns the first value of the array items, if any.
# Related: first_key, last_key, first_value, last_value.
#
#   first_value(['foo' => 1, 'bar' => 2])
#   > 1
#
function first_value($array)
{
    $key = first_key($array);
    return $key !== null ? $array[$key] : null;
}


# last_value
# -----------
#
# Returns the last value of the array items, if any.
# Related: first_key, last_key, first_value, last_value.
#
#   last_value(['foo' => 1, 'bar' => 2])
#   > 2
#
function last_value($array)
{
    $key = last_key($array);
    return $key !== null ? $array[$key] : null;
}


# uuid
# ----
#
# Generate a random uuid-ish uuid, sufficient for minor everyday use.
# 
#   uuid()
#   > 57595445-0001-48cb-a30f-7fff7fff7fff
#
# The last three words are 0x7fff by default, but may be overriden
# by defining function 'instance' that returns a word to use instead.
#
function uuid() {
    $instance = function_exists('instance') ? instance() : 0x7fff;
    static $seq = 0;
    $seq++;
    if ($seq === 0x10000) $seq = 1;
    return sprintf( '%08x-%04x-%04x-%04x-%04x%04x%04x',
        // 32 bits for "time_low"
        time(),

        // 16 bits for "time_mid"
        $seq,

        // 16 bits for "time_hi_and_version",
        // four most significant bits holds version number 4
        mt_rand( 0, 0x0fff ) | 0x4000,

        // 16 bits, 8 bits for "clk_seq_hi_res",
        // 8 bits for "clk_seq_low",
        // two most significant bits holds zero and one for variant DCE1.1
        mt_rand( 0, 0x3fff ) | 0x8000,

        // 48 bits for "node"
        $instance, $instance, $instance);
}


function get_uuid()
{
    $args = func_get_args();
    return call_user_func_array('get', $args);
}



{
    # UTF-8 helpers

    function strtoupper_utf($str) {
        return mb_convert_case($str, MB_CASE_UPPER, 'UTF-8');
    }

    function strtolower_utf($str) {
        return mb_convert_case($str, MB_CASE_LOWER, 'UTF-8');
    }

    function strlen_utf($str) {
        return mb_strlen($str, 'UTF-8');
    }

    function substr_utf($str, $from, $length = null) {
        return mb_substr($str, $from, $length, 'UTF-8');
    }

    function is_valid_utf($string)
    {
        return mb_detect_encoding($string, 'UTF-8', true) === 'UTF-8';
    }

    function is_valid_utf8($string)
    {
        return is_valid_utf($string);
    }

}

{
    # HTML output helpers

    function r_htmlspecialchars($s)
    {
        return nl2br(htmlspecialchars($s, ENT_QUOTES, 'UTF-8'));
    }

    // htmlspecialchars + printf: all strings get htmlspecialchars treatment
    function hsprintf($fmt /*, ... */)
    {
        $args = func_get_args();
        return vsprintf(array_shift($args), array_map('r_htmlspecialchars', $args));
    }

    function hprintf($fmt /*, ... */)
    {
        $args = func_get_args();
        vprintf(array_shift($args), array_map('r_htmlspecialchars', $args));
    }

    // hprintf alias -- used frequently enough
    function h($fmt /*, ... */)
    {
        $args = func_get_args();
        vprintf(array_shift($args), array_map('r_htmlspecialchars', $args));
    }

    // hsprintf alias -- used frequently enough
    function hs($fmt /*, ... */)
    {
        $args = func_get_args();
        return vsprintf(array_shift($args), array_map('r_htmlspecialchars', $args));
    }


}

# dump
# ----
# display contents of variable, using html-formatted output from repr.
#
# Prettier var_dump / print_r replacement
#
# Options:
#   limit: true/false (default true) — limit the number of array elements displayed
#   array-limit: int (default 50) - how many array elements to display
#
function dump($v, $opts = null)
{
    if (! Page::is_plain()) {
        echo '<pre class="debug debug-dump">';
        echo str_replace(' ', '&nbsp;', htmlspecialchars(repr($v, $opts)));
        echo '</pre>';
    } else {
        echo repr($v, $opts) . "\n";
    }
}


# repr
# ----
# return string representation of variable
#
# print_r(..., $return = true) replacement
#
# Options:
#   limit: true/false (default true) — limit the number of array elements displayed
#   array-limit: int (default 50) - how many array elements to display
#
function repr($param, $opts = null)
{
    normalize_options($opts);
    $opt_array_limit = get_option($opts, 'array-limit', '50');
    $opt_limit       = get_option($opts, 'limit', true); // no-limit to drop array limit
    $opt_array_marks = get_option($opts, 'array-marks', '[]');
    $opt_array_recursion_depth = get_option($opts, 'array-recursion-depth', 0);
    if ( ! $opt_limit) {
        $opt_array_limit = -1;
    }

    $opts_deeper = array_merge($opts, array(
        'array-recursion-depth' => $opt_array_recursion_depth + 1
    ));

    if (is_array($param)) {
        $recursion_key = 'rabbid-detect-recursion';
        if (isset($param[$recursion_key])) {
            unset($param[$recursion_key]);
            return '* recursion *';
        }
        $o                     = array();
        $expected_key          = 0;
        $associative           = false;
        $param[$recursion_key] = true;
        $spacer                = str_repeat('    ', $opt_array_recursion_depth);
        $indices_to_show       = $opt_array_limit;
        foreach($param as $k=>$v) {
            $indices_to_show--;
            if ($indices_to_show == 0) {
                $o[] = '...';
                break;
            }

            if ($k === $recursion_key) {
                continue;
            }
            if ( ! $associative and $k === $expected_key) {
                $expected_key++;
                $o[] = repr($v, $opts_deeper);
            } else {
                $associative = true;
                $o[] = repr($k, $opts_deeper) . ':' . repr($v, $opts_deeper);
            }
        }
        unset($param[$recursion_key]);

        if ($associative and sizeof($param) > 1) {
            return $opt_array_marks[0] . "\n    $spacer" . implode(",\n    " . $spacer, $o) . "\n$spacer" . $opt_array_marks[1];
        } else {
            return $opt_array_marks[0] . ' ' . implode(', ', $o) . ' ' . $opt_array_marks[1];
        }
    } else if ($param === null) {
        return 'null';
    } else if (is_string($param)) {
        if (strlen_utf($param) > 1000) {
            $param = reduce_string($param, 1000);
        }
        $param = str_replace("\n", '\n', $param);
        if ( ! is_valid_utf($param)) {
            // make non-utf string printable
            $converted = '';
            for ($i = 0 ; $i < strlen($param) ; $i++) {
                if ($param[$i] >= ' ' && $param[$i] < chr(128)) {
                    $converted .= $param[$i];
                } else {
                    $converted .= sprintf('\x%02x', ord($param[$i]));
                }
            }
            $param = $converted;
        }
        return '"' . $param . '"';
    } else if (is_bool($param)) {
        return $param ? 'true' : 'false';
    } else if (is_double($param)) {
        return (string)$param;
    } else if (is_object($param)) {
        $opts['array-marks'] = '{}';
        return get_class($param) . ' ' . repr(get_object_vars($param), $opts);
    } else if (is_resource($param)) {
        return '<resource>';
    } else if (is_integer($param)) {
        return (string)$param;
    } else {
        return gettype($param);
    }
}





# safe_name
# ---------
# Filter source string so that consists only of latin characters and numbers.
#
# Usable for file names, link parts, etc.
#
# European and cyrillic accented characters mostly get replaced by
# transliterated version.
#
# Resulting string never gets larger than original.
#
#   safe_name("вот же как")
#   > vot_ze_kak
#
#   safe_name("вот же как", '-')
#   > vot-ze-kak
#
#   safe_name("'<>haha hoho!!!")
#   > haha_hoho
#
function safe_name($text, $delimiter = '_')
{
    if ( ! $text) return '';

    $text = strtolower_utf($text);

    $translation_table = array(
        'ā' => 'a',
        'č' => 'c',
        'ē' => 'e',
        'ģ' => 'g',
        'ī' => 'i',
        'ķ' => 'k',
        'ļ' => 'l',
        'ņ' => 'n',
        'ō' => 'o',
        'š' => 's',
        'ū' => 'u',
        'ž' => 'z',
        'а' => 'a',
        'б' => 'b',
        'в' => 'v',
        'г' => 'g',
        'д' => 'd',
        'е' => 'e',
        'ё' => 'e',
        'ж' => 'z',
        'з' => 'z',
        'и' => 'i',
        'й' => 'j',
        'к' => 'k',
        'л' => 'l',
        'м' => 'm',
        'н' => 'n',
        'о' => 'o',
        'п' => 'p',
        'р' => 'r',
        'с' => 's',
        'т' => 't',
        'у' => 'u',
        'ф' => 'f',
        'х' => 'h',
        'ц' => 'c',
        'ч' => 'c',
        'ш' => 's',
        'щ' => 's',
        'ъ' => '',
        'ы' => 'y',
        'ь' => '',
        'э' => 'e',
        'ю' => 'u',
        'я' => 'j',

        'ґ' => 'g',
        'і' => 'i',
        'ї' => 'i',
        'є' => 'e',

        'à' => 'a',
        'á' => 'a',
        'â' => 'a',
        'ã' => 'a',
        'ä' => 'a',
        'ç' => 'c',
        'è' => 'e',
        'é' => 'e',
        'ê' => 'e',
        'ë' => 'e',
        'ì' => 'i',
        'í' => 'i',
        'î' => 'i',
        'ï' => 'i',
        'ò' => 'o',
        'ó' => 'o',
        'ô' => 'o',
        'õ' => 'o',
        'ö' => 'o',
        'š' => 's',
        'ù' => 'u',
        'ú' => 'u',
        'û' => 'u',
        'ü' => 'u',
        'ý' => 'y',
        'ÿ' => 'y',
        'ž' => 'z',

    );
    $text = strtr($text, $translation_table);
    $quoted_delimiter = preg_quote($delimiter);
    $allowed_chars = 'abcdefghijklmnopqrstuvwxyz01234567890' . $quoted_delimiter;
    $out = preg_replace("/[^$allowed_chars]+/", $delimiter, $text);
    $out = preg_replace("/$quoted_delimiter{2,}/", $delimiter, $out);

    $out = trim($out, $delimiter);
    if ( ! $out) $out = $delimiter;

    return $out;
}

# reduce_string
# -------------
# cut string to specified length, possibly ellipsizing it
function reduce_string($str, $len)
{
    if ( ! $str) {
        return $str;
    }

    if ($len < 3) {
        return '...';
    }

    if (strlen_utf($str) > $len) {
        $x = substr_utf($str, 0, $len - 3) .'...';
        for ($i = $len - 3; $i > 0; $i--) {
            if (substr_utf($str, $i, 1) == ' ') {
                return substr_utf($str, 0, $i) . '...';
            }
        }
        return $x;
    } else {
        return $str;
    }
}


