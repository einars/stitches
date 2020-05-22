<?php

# Universal error handling and some error pages
#
#

class Errors {

    static function load()
    {

        if (defined('STITCHES_DONT_HANDLE_ERRORS')) return;

        set_error_handler('errors_default_handler');
        s::on('install', 'errors_install');
        s::on('configure', 'errors_configure');
        s::on('error', 'Errors::maybe_log_to_database');
        s::on('error', 'Errors::display');
    }

    # Errors::display
    # ---------------
    # Default pretty php error output
    #
    static function display($e)
    {
        $message = $e['message'];
        $trace = $e['trace'];
        $is_fatal = $e['is_fatal'];

        if (StPage::is_plain()) {
            h("%s\n", $message);
            foreach($trace as $t) {
                h("%s:%s %s\n"
                    , $t['file']
                    , $t['line']
                    , $t['source']
                );
            }
        } else {
            # wrapped in a table so it won't break if error happened while
            # drawing inside / alongside other tags.
            echo "<!--\n";
            h("%s\n", $message);
            foreach($trace as $t) {
                h("%s:%s %s\n"
                    , $t['file']
                    , $t['line']
                    , $t['source']
                );
            }
            echo "\n-->\n";
            h('<table class="stitches-failure %s">', $is_fatal ? 'stitches-error' : 'stitches-warning');

            h('<tr><th class="stitches-failure-text" colspan="2">%s</th></tr>', $message);
            if ($trace) {
                foreach($trace as $t) {
                    echo '<tr>';
                    h('<td>%s:%s</td>', $t['file'], $t['line']);
                    h('<td>%s</td>', $t['source']);
                    echo '</tr>';
                }
            }
            echo '</table>';
            Errors::style();

        }
    }

    static function maybe_log_to_database($e)
    {
        if ( ! s::db()) return;
        if ( ! s::get('cfg:errors.log-to-database')) return;

        $message = $e['message'];
        $error_trace = $e['trace'];
        $is_fatal = $e['is_fatal'];

        $hash_source = $message;
        $trace = '';
        if (is_array($error_trace)) {
            $trace = array();
            $hash_source = '';
            foreach($error_trace as $t) {
                $trace[] = $t['file'] . ':' . $t['line'] . "\n" . $t['source'] . "\n";
                $hash_source .= $t['file'] . ':' . $t['line'] . "\n";
            }
            $trace = substr(implode("\n", $trace), 0, 10240);
        }

        $error_id = md5($hash_source);
        $check = db::one('select 1 from error_log where error_id=%s', $error_id);
        if ($check) {
            db::query('update error_log set last_seen=now(), times_seen = times_seen + 1
                where error_id=%s', $error_id);
        } else {

            $error = array(
                'error_id' => $error_id,
                'url' => reduce_string(get('SERVER_NAME', $_SERVER, 'cli:') . any(get('REQUEST_URI', $_SERVER), s::get('action')), 250),
                '~first_seen' => 'now()',
                '~last_seen' => 'now()',
                'times_seen' => 1,
                'is_fatal' => $is_fatal,
                'is_seen' => false,
                'message' => $message,
                'trace' => $trace,
                'request' => repr($_REQUEST),
                'server' => repr($_SERVER),
            );
            db::insert('error_log', $error);
            s::emit('error.db.new', $error);
        }

        if ( ! s::cli() && s::get('cfg:errors.log-to-database.hide')) {
            // Stop propagation
            // next handler would display the error, this prevents that
            return failure('stop');
        }

    }

    static function error_page_helper($original_args, $header)
    {
        if (sizeof($original_args) > 1) {
            $message = call_user_func_array('sprintf', $original_args);
        } else {
            $message = any($original_args[0], get('REQUEST_URI', $_SERVER));
        }

        @ob_end_clean();

        if (s::is_debug_mode()) {
            s::error('Request failed: %s', $message);
        } else {
            StPage::pretty_error_page($header, $message);
            exit;
        }

    }

    static /* exit */ function page_bad_request($message = null /* , ... */)
    {
        $args = func_get_args();
        errors::error_page_helper($args, '400 Bad Request');
    }

    static /* exit */ function page_internal_error($message = null /* , ... */)
    {
        $args = func_get_args();
        errors::error_page_helper($args, '500 Internal Server Error');
    }


    static /* exit */ function page_access_denied($message = null /* , ... */)
    {
        $args = func_get_args();
        errors::error_page_helper($args, '403 Access denied');
    }

    static /* exit */ function page_not_found($message = null /* , ... */)
    {
        $args = func_get_args();
        errors::error_page_helper($args, '404 Not Found');
    }

    static function clear()
    {
        s::set('was_error', false);
        s::set('last_error', null);
    }
    static function style()
    {

        if (StPage::is_plain()) return;

        static $run_once = 0;
        if ($run_once++) return;

        // echo instead of StPage::add_style because error might've been fatal,
        // so the wrapper handlers will never get called, and there might never
        // be a chance to display stylesheet otherwise.
        //
        // it's possible for error message to find itself in any place in the
        // page code, so font sizes and colors should be defensive as well..

        echo '<style>';
        echo <<<CSS
.stitches-failure {
  font-family: monospace;
  font-size: 12px;
  background-color: #fff7f7;
  border: 1px solid #caa; 
  padding: 0.5em;
  margin-top: 1em;
  margin-bottom: 1em;
}
.stitches-failure-text {
  padding-bottom: 0.5em;
  margin-bottom: 0.5em;
  font-weight: bold;
  border-bottom: 1px solid #caa;
}
.stitches-failure td,
.stitches-failure th
 {
  padding: 0 8px;
}
CSS;
        echo '</style>';



    }
}


# errors_default_handler
# ------------------------------
#
# Handles all errors and warnings, optionally logging them into the database.
#
function errors_default_handler($errno, $message)
{
    s::set('was_error', true);
    s::set('last_error', $message);

    $max_reported_errors = 10;

    $deadly_errors = array(E_ERROR, E_PARSE, E_USER_ERROR, E_CORE_ERROR);
    $is_fatal = in_array($errno, $deadly_errors);

    $show_params = true;
    $error_text   = $message;
    $error_vars   = array();

    $error_trace  = array();
    $traces = debug_backtrace();

    # process debug_backtrace to something usable
    foreach($traces as $n=>$tt) {

        $n_file = get('file', $tt);
        $line   = get('line', $tt);
        $args   = get_array('args', $tt);
        $func   = get('function', $tt);
        $class  = get('class', $tt);
        $type   = get('type', $tt);
        $object = get('object', $tt);

        if ($type == '::') {
            $func = $class . '::' . $func;
        } elseif ($type == '->') {
            $func = "[$class]" . '->' . $func;
        }

        $file = substr($n_file, strrpos($n_file, '/') + 1);

        if ($file and $line) {

            $trace_entry = array('file' => $file, 'line' => $line, 'source' => null);

            if ($func == 'trigger_error' and $file == 'stitches.php') {
                // eat trigger_error line after s::warning / s::error
                continue;
            }

            if ($n > 0) {
                $repr_args = '';
                if ($args) {
                    $repr_args = array();
                    foreach($args as $arg) {
                        $repr_args[] = repr($arg);
                    }
                    $repr_args = implode($repr_args, ', ');
                }
                $trace_entry['source'] = "$func($repr_args)";
            }

            # Ignore errors/warnings in database implementation.
            # Makes backtraces quite cleaner.
            if (starts_with('c.database', $file)) continue;
            if (starts_with('db.', $file)) continue;

            $error_trace[] = $trace_entry;
        }
    }

    static $reported_error_count = 0;

    if (error_reporting()) {

        $reported_error_count++;
        if ($reported_error_count == $max_reported_errors) {
            $is_fatal = true;
        }

        if ($is_fatal && ! headers_sent()) {
            // in case the content type hasn't been specified yet, try forcing it
            header('Content-type: text/html; charset=utf-8');
        }
        s::emit('error', array(
            'message' => $message, 
            'trace' => $error_trace, 
            'is_fatal' => $is_fatal
        ));
    }


    if ($reported_error_count == $max_reported_errors) {
        if (StPage::is_plain()) {
            echo 'Too many errors.';
        } else {
            h('<p>%s</p>', 'Too many errors.');
        }
    }

    if ($is_fatal) {
        exit(-1);
    }


}


function errors_install()
{
    class inst_error_log extends InstallerTableTask {

        var $sql = '
create table error_log (
    error_id char(32) primary key,
    url varchar(250),
mysql:        first_seen datetime,
mysql:        last_seen datetime,
postgresql:   first_seen timestamp with time zone,
postgresql:   last_seen timestamp with time zone,
    times_seen int,
    is_fatal bool default false,
    is_seen bool default false,
    message text,
    trace text,
    request text,
    server text
);
';

    }
}

function errors_configure()
{
    StConfig::define('errors.log-to-database', false, 'Log warnings and errors in the database?');
    StConfig::define('errors.log-to-database.hide', false, 'Should the error messages be hidden after logging?');
}

