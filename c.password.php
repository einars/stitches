<?php

# Password
#
# Provides Pasword::hash() and Password::verify() functions
# that hash and verify password hashes using phpass password
# hashing library.

class Password {

    public static function hash($something, $options = Null)
    {
        $opt_iteration_count = get_option($options, 'iteration-count', 8);
        $opt_portable_hashes = get_option($options, 'portable-hashes', true);

        require_once(dirname(__FILE__) . '/libs/phpass-0.3/PasswordHash.php');

        $hasher = new PasswordHash($opt_iteration_count, $opt_portable_hashes);
        // errors silenced as safe_mode may emit harmless "not permitted" warnings
        // when accessing /dev/random
        return @$hasher->HashPassword($something);
    }


    public static function verify($something, $hash, $options = Null)
    {
        $opt_iteration_count = get_option($options, 'iteration-count', 8);
        $opt_portable_hashes = get_option($options, 'portable-hashes', true);

        require_once(dirname(__FILE__) . '/libs/phpass-0.3/PasswordHash.php');

        $hasher = new PasswordHash($opt_iteration_count, $opt_portable_hashes);
        return $hasher->CheckPassword($something, $hash);
    }

    public static function test($t)
    {
        $t->test_function('Password::verify');
        $t->test('rabbid', '$P$Br4JOMQh6vAZtLfhY45SNMvzN25QxE1', true);
        $t->test('rabbid', '$P$Br4JOMQh6vAZtLfhY45SNMvzN25QxE2', false);
    }


}
