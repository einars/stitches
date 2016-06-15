<?php

/*
 * Stitches, failure handling
 *
 * Functions are encouraged to return (and handle) failure($error_text) if the
 * function has failed;
 *
 * If the function encounters failure() as a return value to something, it is advised
 * to return it to caller as-is (in a monad-like fashion).
 *
 *
 *     $a = do_something_complicated();
 *     if (failed($a)) {
 *       return print_notice($a); // will display failure text
 *     }
 *
 */


# If using forms, you may specify $failed_field, so that print_notice will
# attempt to put keyboard focus on the first failed field.
#
function failure($text = '', $failed_field = Null)
{
    if ($failed_field) {
        $f = array(
            'stitches-failure-text' => $text,
            'failed-field' => $failed_field
        );
    } else {
        $f = array(
            'stitches-failure-text' => $text,
        );
    }
    return $f;
}

# success
# -------
# You may use it to signal success — as an opposite to failure() — but it isn't
# treated in any special way, it's just null or success text.
#
function success($text = null)
{
    return $text;
}

# failed
# ------
# Check if the function result you're looking at is failure
#
function failed($something)
{
    return (is_array($something) and array_key_exists('stitches-failure-text', $something));
}

# failure_text
# ------------
# Extract failure text from something, that might be failure
#
function failure_text($something)
{
    if (failed($something)) {
        return any(get('stitches-failure-text', $something), 'No error details specified.');
    }
}


# focus_to
# --------
# Selects field (by name) on which the focus should be set on page load.
# For multiple calls, only first call to focus_to is processed.
#
# Used internally by print_notice, to auotmatically focus on the errored field.
#
#   focus_to('password')
#
#   focus_to(failure('Password is incorrect', 'password'))
#
function focus_to($focus_to)
{
    static $focused_on = null;
    if (is_array($focus_to)) {
        $focus_to = get('failed-field', $focus_to);
    }
    if ( ! $focus_to or $focused_on) {
        return;
    }
    $focused_on = $focus_to;

    $js = "$(':input[name=$focus_to]:visible').first().focus().select();";
    Page::add_onload_js($js);
}

# print_notice
# ------------
# Display something, that might be a failure()
# or success() (a common string treated as a success message).
#
#   print_notice('Payment received.');
#   print_notice(failure('Something borked'));
#
function print_notice($maybe_failure)
{
    // html not escaped
    if (failed($maybe_failure)) {
        printf('<p class="notice notice-failure">%s</p>', failure_text($maybe_failure));
    } else if (is_string($maybe_failure) && $maybe_failure) {
        printf('<p class="notice notice-success">%s</p>', $maybe_failure);
    }
    return $maybe_failure;
}


# assert_success
# --------------
# A helper for a lazy, yet defensive programming.
# Require that the result of some function call was success, otherwise throw
# error and die (or, optionally, just warn).
#
#   $res = assert_success(do_something_complicated());
#   // no need to handle failure further
#
function assert_success($something, $die_on_failure = true)
{
    if (failed($something)) {
        if ($die_on_failure) {
            R::error(failure_text($something));
        } else {
            R::warn(failure_text($something));
        }
    }
    return $something;
}


# die_on_failure
# --------------
# A helper for a lazy, yet defensive programming.
#
#   $res = die_on_failure(do_something_complicated());
#   // no need to handle failure further
#
function die_on_failure($something)
{
    return assert_success($something, true);
}


# warn_on_failure
# --------------
# A helper for a lazy, yet defensive programming.
#
#   $res = warn_on_failure(do_something_complicated());
#   // you probably need to handle failure here
#
function warn_on_failure($something)
{
    return assert_success($something, false);
}

