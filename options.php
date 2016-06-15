<?php

#
# Optional function parameter convention
# ======================================
#
# All the parameters are expected to be received in a single assoc-array
# option variable:
#
#   function foo($opts = null)
#   {
#     $opt_something = get_option($opts, 'something', false);
#     $opt_nothing   = get_option($opts, 'nothing', true);
#     $opt_password  = get_option($opts, 'password', 'hunter2');
#     // ...
#   }
#
# Passing parameters to function with optional parameters:
#
#   foo()
#     use default parameters (something=false, nothing=true, password=hunter2)
#
# You may pass comma-separated strings:
#
#   foo('something')
#     something = true
#
#   foo('something, nothing')
#     something = true, nothing=true
#
#  You may negate the parameter by prefixing parameter name with 'no-', 'not-' or 'dont-':
#
#   foo('not-something')
#     something = false
#
#   foo('dont-something')
#     something = false
#
#  Yoy may pass an array:
#
#     foo(['password' => 'love', 'no-nothing']
#
#
# Parameters will get normalized/canonicalized on first get_option access — a
# clean associative array with fullly expanded options will be created.
#
# Try to keep list of 
#
#    $opt_something = get_option($opts, 'something'); 
#
# statements on top of the function for a clean supported option overview.
# Try to name options $opt_NAME, where NAME is option name.
#
# Prefer dashes in option names, e.g:
#
#   $opt_log_errors = get_option($opts, 'log-errors', false);
#

# get_option
# ----------
# Determine setting from the passed option parameter.
#
function get_option($options, $name, $default = Null)
{
    if ( ! $options) {
        return $default;
    }

    // make array representation, as options can be a string 'foo, bar, baz'
    if ( ! is_array($options) || ! isset($options['.options-normalized']))
    normalize_options($options);

    if (array_key_exists($name, $options)) return $options[$name];
    return $default;
}

# normalize_options
# -----------------
# Build a canonical array representing options. Mutates the original array.
#
function normalize_options(&$options)
{
    if (is_array($options) && array_key_exists('.options-normalized', $options)) {
        // good
        return $options;

    } else if ( ! $options) {
        $options = array();
        return $options;
    } else if (is_string($options)) {
        # in: "option1, option2, no-option3"
        # out: [ 'option1', 'option2', 'no-option3' ]
        $options = array_from_comma_string($options);
    }

    # convert numeric parameters
    # out: [ 'option1', 'option2', 'no-option3' ]
    # out: [
    #   option1: true
    #   option2: true
    #   no-option3: true
    # ]
    foreach(array_keys($options) as $k) {
        if (is_numeric($k)) {
            $options[$options[$k]] = true;
            unset($options[$k]);
        }
    }

    # convert 'no-option':true to 'option':false and similar
    $keys = array_keys($options);
    foreach($keys as $k) {
        # no-something => false
        # get converted to
        # something => true
        if (starts_with('no-', $k) or starts_with('not-', $k) or starts_with('dont-', $k)) {
            list($_, $opt) = explode('-', $k, 2);
            $options[$opt] = ! $options[$k];
        }
    }
    $options['.options-normalized'] = true;
    return $options;
}

# normalize_opts
# --------------
# Build a canonical array representing options. Mutates the original array.
#
# A shorter alias for normalize_options.
#
function normalize_opts(&$options)
{
    return normalize_options($options);
}
