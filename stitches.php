<?php

/*
 * Stitches
 *
 * PHP 5+, E_everything-safe
 *
 */

require_once(dirname(__FILE__) . '/common.php');
require_once(dirname(__FILE__) . '/failures.php');
s::_startup();

class S {

    # all settings set by s::set('something') go here
    static $settings = array();

    # the classes are search
    static $class_search_paths = array();

    # Default file patterns to be searched in class_search_paths.
    # Requiring class 'BaseFoo' will search for c.basefoo.php, basefoo.class.php and cla_basefoo.php
    static $class_search_patterns = array( 'c.%s.php', '%s.class.php', 'cla_%s.php' );

    # config passed in by s::configure()
    static $config = null;


    # s::startup
    # ----------
    # Happens immediately after including stitches.php
    #
    static function _startup()
    {
        error_reporting(E_ALL);

        s::set('start-time', microtime(true));

        if (get_magic_quotes_gpc()) {

            function __stripslashes_array(&$what)
            {
                if (is_array($what)) {
                    array_walk($what, '__stripslashes_array');
                } else {
                    $what = stripslashes($what);
                }
            }

            if ($_GET) {
                array_walk($_GET,    '__stripslashes_array');
            }
            if ($_POST) {
                array_walk($_POST,   '__stripslashes_array');
            }
            if ($_REQUEST) {
                array_walk($_REQUEST, '__stripslashes_array');
            }
        }

        if ( ! defined('STITCHES_KEEP_REQUEST_ARRAYS')) {
            # Sanitize GET/POST/REQUEST by killing arrays.
            #
            # It's often easy to break php applications in hilarious ways, as
            # typically nobody expects array in input params.
            #
            # convention: if you need array, its name should start with array_.
            #
            foreach($_GET as $k=>$v) {
                if (is_array($v) && ! starts_with('array_', $k)) {
                    $_GET[$k] = implode(', ', $v);
                }
            }
            foreach($_POST as $k=>$v) {
                if (is_array($v) && ! starts_with('array_', $k)) {
                    $_POST[$k] = implode(', ', $v);
                }
            }
            foreach($_REQUEST as $k=>$v) {
                if (is_array($v) && ! starts_with('array_', $k)) {
                    $_REQUEST[$k] = implode(', ', $v);
                }
            }
        }



        if ( ! isset($_SERVER['REQUEST_URI'])) { // probably started from command-line
            s::set('cli', true);
            $_SERVER['REQUEST_URI'] = null;
        }

        if ( ! isset($_SERVER['HTTP_HOST'])) {
            $_SERVER['HTTP_HOST'] = 'localhost';
        }
        if ( ! isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $_SERVER['HTTP_X_FORWARDED_FOR'] = null;
        }

        if ( ! isset($_SERVER['HTTP_USER_AGENT'])) {
            $_SERVER['HTTP_USER_AGENT'] = null;
        }

        if ( ! isset($_SERVER['HTTP_ACCEPT'])) {
            $_SERVER['HTTP_ACCEPT'] = null;
        }

        if ( ! ini_get('date.timezone')) {
            date_default_timezone_set('Europe/Helsinki');
        }

        // additional autoload classes from these paths
        // stitches-path is checked last
        if (file_exists('./site')) {
            S::add_class_path('./site');
        }
        S::add_class_path(dirname(__FILE__));

        if (function_exists('spl_autoload_register')) {
            spl_autoload_register('stitches_autoload');
        }

        if ( ! function_exists('spl_autoload_register') and ! function_exists('__autoload')) {
            // old-school autload fallback
            function __autoload($class_name) {
                return stitches_autoload($class_name);
            }

        }

        Errors::load();

        if ( ! s::cli()) {
            ob_start();
        }

    }

    static function configure($config = null)
    {
        if ( ! $config)                       $config = array();
        if ( ! isset($config['db.engine']))   $config['db.engine'] = null;

        s::$config = $config;

        $engine_name = Database::load($config);
        s::set('database', $engine_name);

        # only database connection information is loaded.
        # the actual connection will happen only when it's first used,
        # typically — when sessions initialize

        // action to be executed is determined before s::run()
        //
        // if you need to, you may modify s::get('action') between calls
        // to s::configure() and s::run()
        //
        if (s::cli()) {
            global $argv;
            $action = get(1, $argv);
        } else {

            $get_action = get_string('action', $_GET);
            $post_action = get_string('action', $_POST);

            $action = trim(any($post_action, $get_action), '/');
        }

        if ($action === 'install') {
            define('STITCHES_INSTALLING', true);
        }

        s::set('action', $action);

        Config::load();
        s::on('configure', 'stitches_configuration');

        # verify debug mode and set 'debug' accordingly
        $debug_enabled = s::get('cfg:debug.enabled');
        if ($debug_enabled) {
            // check IP restriction
            if (($debug_ip = s::get('cfg:debug.ip'))) {
                # split on spaces and/or commas
                $debug_ips = preg_split('/[ ,]+/', $debug_ip);
                $my_ip = get('REMOTE_ADDR', $_SERVER);
                $debug_enabled = in_array($my_ip, $debug_ips);
            }
        }
        if ($debug_enabled) {
            s::enable_debug_mode();
        }

    }

    static /* exit */ function run()
    {
        if (sizeof(s::$settings) === 1) {
            # you want to run with empty settings? ok, why not
            s::configure(array());
        }


        Session::initialize();

        if ( ! s::cli()) {
            Page::set_canonical_url(get('REQUEST_URI', $_SERVER));
        }

        try {
            s::execute(s::get('action'));
        } catch (Exception $e) {
            s::error('Uncaught exception: ' . $e->getMessage());
        }
    }


    # s::execute ($action)
    # --------------------
    # Parses the route based on $action and executes the requested action
    #
    # Called by s::run() when action is determined
    #
    static function execute($action)
    {

        $action = trim($action, '/');

        s::set('action', $action);

        if ($action == 'install') {  // shortcut without invoking get_routes
            $routes = array('install' => 'on_stitches_install');
        } else {
            $routes = s::get_routes();
        }

        $had_something_to_do = false;

        $catch_all_fn = null;
        if (isset($routes['*'])) {
            $catch_all_fn = $routes['*'];
            unset($routes['*']);
        }

        if (isset($routes[$action])) {
            // common case
            s::set('executed-action', $routes[$action]);
            s::call_array($routes[$action], array(null));
            $had_something_to_do = true;
        } else {
            // check regexps
            // don't really like the regexp business here, would prefer wildcards (%d, %s) in $routes
            foreach($routes as $k=>$func) { // search and verify for regexps
                if ($k and $k[0] == '^') {
                    $regexp = $k;
                    if (substr($regexp, -1) != '$') {
                        $regexp .= '$';
                    }
                    if (preg_match("/$regexp/D", $action, $matches)) {
                        s::set('executed-action', $func);
                        array_shift($matches);
                        s::call_array($func, $matches);
                        $had_something_to_do = true;
                        break;
                    }
                }
            }
        }
        if ( ! $had_something_to_do) {
            if ($catch_all_fn) {
                $had_something_to_do = s::call($catch_all_fn, $action ? $action : '');
            }
            if ( ! $had_something_to_do) {
                Errors::page_not_found(hs("Action %s not found.", $action));
            }
        }

        $c = ob_get_clean();

        Page::present($c); // wraps all to a nice html

        exit;
    }

    # s::cli ()
    # ---------
    # Returns true, if called from console/command line
    # Returns false, if running normally, under webserver
    #
    static function cli()
    {
        return s::get('cli');
    }



    # get some configuration setting
    # settings starting with "cfg:" and "config:" are passed to Config
    # settings starting with "cache:" are passed to Cache
    # settings starting with "sess:", "session:" are passed to Session
    #
    # Anything else is page-specific and stored in s::$settings.
    #
    static function get($setting, $opts = null)
    {
        if (starts_with('s:', $setting)) s::warn('Setting starts with "s:"');

        if (strpos($setting, ':') === false) {
            return get($setting, s::$settings);
        }

        list($prefix, $key) = explode(':', $setting, 2);
        switch($prefix) {
        case 'sess':
        case 'session':
            if ( ! isset($_SESSION)) {
                # init on first access
                Session::initialize();
            }
            return isset($_SESSION) ? get($key, $_SESSION) : null;
        case 'cfg':
        case 'config':
            return Config::get($key);
        case 'cache':
            return Cache::get($key, $opts);
        default:
            return get($setting, s::$settings);
        }
    }

    static function set($setting, $value = true)
    {
        if (starts_with('s:', $setting)) s::warn('Setting starts with "s:"');

        if (strpos($setting, ':') === false) {
            s::$settings[$setting] = $value;
            return;
        }
        list($prefix, $key) = explode(':', $setting, 2);
        switch($prefix) {
        case 'sess':
        case 'session':
            if ( ! isset($_SESSION)) {
                # init on first access
                Session::initialize();
            }
            $_SESSION[$key] = $value;
            break;
        case 'cfg':
        case 'config':
            Config::set($key, $value);
            break;
        case 'cache':
            return Cache::set($key, $value);
        default:
            s::$settings[$setting] = $value;
        }
    }

    static function error($message /* , ... */)
    {
        if (func_num_args() > 1) {
            $args = func_get_args();
            $message = call_user_func_array('sprintf', $args);
        }
        trigger_error($message, E_USER_ERROR);
    }


    static function warning($message /* , ... */)
    {
        if (func_num_args() > 1) {
            $args = func_get_args();
            $message = call_user_func_array('sprintf', $args);
        }
        trigger_error($message, E_USER_WARNING);
    }

    // alias for s::warning
    static function warn($message /* , ... */)
    {
        if (func_num_args() > 1) {
            $args = func_get_args();
            $message = call_user_func_array('sprintf', $args);
        }
        trigger_error($message, E_USER_WARNING);
    }

    # s::debug (printf-like syntax)
    # --------
    # Send a debug message.
    # By default, stitches_debug_message listener is attached to the message
    # if debug mode is enabled, that prints html debug text.
    #
    static function debug($message /* , ... */)
    {
        if (func_num_args() > 1) {
            $args = func_get_args();
            $message = call_user_func_array('sprintf', $args);
        }
        s::event('debug', $message);
    }

    static function add_routes($routes)
    {
        $stored_routes = any(s::get('stored-routes'), array());
        $stored_routes = array_merge($stored_routes, $routes);
        s::set('stored-routes', $stored_routes);
        s::on('routes', 's::add_stored_routes');
    }

    static function add_stored_routes($routes)
    {
        $stored_routes = any(s::get('stored-routes'), array());
        s::set('stored_routes', array());
        return array_merge($routes, $stored_routes);
    }


    static function get_routes()
    {
        if ( ! ($routes = s::get('routes'))) {
            $route_defaults = array(
                'run-tests' => 'on_stitches_unittests',
                'install' => 'on_stitches_install',
                'admin/tests' => 'on_stitches_unittests',
                'admin/install' => 'on_stitches_install',
                'robots.txt' => 'on_stitches_robots_txt',
            );

            $routes = s::emit('routes', $route_defaults);
            $routes = s::process_route_macros($routes);

            if ( ! isset($routes['']) && ! isset($routes['*'])) {
                $routes[''] = 'on_stitches_default_index';
            }
            s::set('routes', $routes);
        }
        return $routes;
    }

    protected static function process_route_macros($routes)
    {
        $processed = array();
        foreach($routes as $k=>$v) {
            if (strpos($k, '%') !== false) {
                $k = str_replace('/', '\\/', $k);
                $k = str_replace('%ext', '(\.([a-zA-Z]+))?$', $k); // extension
                $k = str_replace('%d', '(\d+?)', $k);
                $k = str_replace('%*', '(.*?)', $k);
                $k = str_replace('%s', '([a-z\d\-\_.]+?)', $k);
                $k = str_replace('%S', '([a-zA-Z\d\-\_.]+?)', $k);
                $hex = '[a-f\d]';
                $k = str_replace('%u', "($hex{8}-$hex{4}-$hex{4}-$hex{4}-$hex{12})", $k);
                $k = '^' . $k . '$';
            }
            $processed[$k] = $v;
        }
        return $processed;
    }

    # s::call
    # -------
    #
    # call _something_, that might be:
    #
    # 1. 'function_name'
    # 2. 'sitefile:function_name'
    #     require file 'site/sitefile.php' and call function function_name
    # 3. 'classname::static_function'
    #     calls a static function in the class
    # 5. some actual closure
    #     s::call(function () { return 42; });
    #
    # All the parameters are passed, as you'd expect.
    #
    #     s::call(
    #       function ($n) { return $n; },
    #       42
    #     );
    #
    static function call($signature /* , params */)
    {
        $args = func_get_args();
        array_shift($args); // shift off $signature
        return s::call_array($signature, $args);
    }




    # s::call_array
    # -------------
    #
    # Similar to s::call, but all the parameters are given in $params list.
    #
    # See s::call documentation for supported $signature syntaxes.
    #
    static function call_array($signature, $params)
    {
        if (is_object($signature) and $signature instanceof Closure) {
            return call_user_func_array($signature, $params);
        }
        # (REQUIRED_PERMISSIONS) page: function
        if ($signature && $signature[0] == '(') { // permission specifier
            list($permissions, $signature) = explode(')', $signature . ')');
            $permissions = trim($permissions, '()');
            if ($permissions) {

                if ( ! isset(s::$ev_listeners['permission-check'])) {
                    s::error('Permissions "%s" requested, but no filter for permission-check event installed.', $permissions);
                }

                // probably the filter itself will error or redirect already
                $success = s::emit('permission-check', $permissions);
                if (failed($success)) {
                    return false;
                }

            }
        }

        if (strpos($signature, '::')) {
            list($classname, $function) = explode('::', $signature);
            $call = array(trim($classname), trim($function));
        } else {
            list($file, $func) = explode(':', trim($signature) . ':');
            if ($func) {
                $files = explode(',', $file);
                foreach($files as $file) {
                    require_once('./site/' . trim($file) . '.php');
                }
            } else {
                $func = $file;
            }
            $call = trim($func);
            if ( ! function_exists($call)) {
                s::error('Function %s not found.', $call);
            }
        }

        return call_user_func_array($call, $params);
    }


    static function add_class_path($path)
    {
        $path = rtrim($path, '/');
        s::$class_search_paths[$path] = $path;
    }

    static function is_production()
    {
        return s::get('cfg:site.is-production');
    }

    # s::db()
    # -------
    # Returns true, if actual database is used in the application.
    #
    # You can get underlying database engine name via s::get('database').
    #
    static function db()
    {
        $engine_name = s::get('database');
        return !! $engine_name;
    }

    # s::enable_debug_mode
    # --------------------
    # Set a global flag that may be queried with s::is_debug_mode or s::get('debug'),
    # to determine if debug information needs to be displayed.
    static function enable_debug_mode()
    {
        s::set('debug', true);
        s::on('debug', 'stitches_debug_message');
    }

    # s::is_debug_mode
    # ----------------
    # Returns true, if debug mode is active
    static function is_debug_mode()
    {
        return s::get('debug');
    }


    # Internal stitch events
    #========================
    #
    # set up an event listener with:
    #
    #   s::on('my-event', function () { echo "MY-EVENT!"; });
    #
    # call event somewhere else with:
    #
    #   s::event('my-event');
    #
    # events support a single parameter:
    #
    #   s::event('my-event', $params);
    #
    # The parameter is filtered through all of the event listeners,
    # null return values are ignored;
    # This is used in wrapping the content with base page decorations —
    # rendered content is filtered through the "content" event listeners
    # that each return decorated html, like this:
    #
    #   function sample_content_listener($html) {
    #     return '<div class="wrap">' . $html . '</div>';
    #   }
    #   s::on('content', 'sample_content_listener');
    #
    # Return failure(..) from a listener to break this event.

    static $ev_listeners = array();

    # s::on
    # -----
    # Start listening for the event.
    #
    #   function on_body_end() {
    #      echo 'Body end';
    #   }
    #   s::on('page:body-end', 'on_body_end');
    #
    # $callback follows s::call syntax and may be:
    # 1. 'function_name'
    # 2. 'sitefile:function_name', 'handlers:my_handler' will require site/handlers.php and call my_handler
    # 3. 'classname::static_function'
    # 4. '$classname::function_name'
    #     class will be instantiated, i.e (new classname())->function_name();
    # 5. actual closure
    #
    # see s::call syntax for details.
    #
    static function on($ev_name, $callback)
    {
        if (is_string($callback)) {
            # easily find/remove listener and avoid duplicates,
            #
            # s::on('event', 'some_function');
            # may be removed with
            # unset(s::$ev_listners['event']['some_function'])
            #
            # but, in your code, use s::off('event', 'some_function')
            s::$ev_listeners[$ev_name][$callback] = $callback;
        } else {
            s::$ev_listeners[$ev_name][] = $callback;
        }
    }

    static function emit($ev_name, $param = null)
    {
        foreach(get_array($ev_name, s::$ev_listeners, array()) as $listener) {
            $ret = s::call($listener, $param);
            $param = ($ret === null) ? $param : $ret;
            if (failed($param)) break;
        }
        return $param;
    }


    # s::event
    # --------
    # Alias of s::emit
    #
    static function event($ev_name, $param = null)
    {
        return s::emit($ev_name, $param);
    }


    # s::off
    # ------
    # Remove one or all listeners from the event.
    #
    function off($ev_name, $callback = null)
    {
        if ($callback === null) {
            unset(s::$ev_listeners[$ev_name]);
        } else {
            if (is_string($callback)) {
                unset(s::$ev_listeners[$ev_name][$callback]);
            } else {
                s::$ev_listeners[$ev_name] = array_diff(s::$ev_listeners[$ev_name], array($callback));
            }
        }
    }


}

function stitches_autoload($class_name)
{
    $class_name = strtolower_utf($class_name);
    foreach(s::$class_search_paths as $path) {
        foreach(s::$class_search_patterns as $pattern) {
            if (file_exists($file = $path . '/' . sprintf($pattern, $class_name))) {
                require_once($file);
                return;
            }
        }
    }
    if ( ! class_exists($class_name)) {
        # don't fail if there are more autoloaders
        if (sizeof(spl_autoload_functions()) == 1) {
            s::error('Class %s not found', $class_name);
        }
    }
}


// predefined dispatchers
# on_statiches_install
# --------------------
# Default handler for http://localhost/install and 'php index.php install' actions.
#
# Your own installation steps is expected to be found in site/installation.php.
#
function on_stitches_install()
{
    installer::load();

    if (file_exists('site/installation.php')) {
        require_once('site/installation.php');
    }

    installer::run();
}


# on_stitches_robots_txt
# -----------------------
# Default robots.txt handler
#
# Tags are gathered via 'robots' event, the data filtered is
# associative array,
#
#   href => bool_allow
#   or
#   tag_name => tag_value
#
# e.g
#
#   [
#     'crawl-delay' => 50,
#     '/secret' => false,
#   ]
#
function on_stitches_robots_txt()
{
    Page::set_plain_output();

    $robot_tags = array(
        '/' => (bool)s::get('cfg:site.is-production')
    );

    $robot_tags = s::emit('robots', $robot_tags);

    echo "User-agent: *\n\n";
    foreach($robot_tags as $tag=>$value) {
        if ($value === true || $value === false) {
            h("%s: %s\n",  $value ? 'Allow' : 'Disallow', $tag);
        } else {
            // allow 'crawl-delay' => 10, etc
            h("%s: %s\n",  $tag, $value);
        }
    }
}


# stitches_configuration
# ----------------------
# Defines site-specific  configuration settings supported by stitches itself.
#
# If database is used, the settings will be persisted in 'configuration' table.
# If no database is used, there is no persistence.
#
# If any settings were passed in the array for the s::configure, then  will
# be treated as immutable and the s::configure version will always be used.
#
function stitches_configuration()
{
    Config::define('debug.ip',           null,  'IP addresses for the debug mode');
    Config::define('debug.enabled',      false, 'Global debug mode');
    Config::define('debug.sql',          true,  'Show SQL dumps in the debug mode?');
    Config::define('site.is-production', false, 'Production flag');
}


# on_stitches_default_index
# -------------------------
# Useless default handler for the root of site.
#
function on_stitches_default_index()
{
    echo 'It works';
}

# stitches_debug_message
# ----------------------
# Default s::debug(...) handler.
#
function stitches_debug_message($message)
{
    if ( ! s::is_debug_mode()) return;
    if (Page::is_plain()) {
        printf("%s %s\n", date('Y-m-d H:i'), $message);
    } else {
        h('<p class="stitches-debug">%s</p>', $message);
    }
}

function redirect($url)
{
    return page::redirect($url);
}
