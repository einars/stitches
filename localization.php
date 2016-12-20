<?php

function L($x)
{
    static $translations = null;
    if ($translations === null) {
        $translations = s::event('load-translations', array());
        if ($translations === null) {
            $translations = array();
        }
    }

    $o = isset($translations[$x]) ? $translations[$x] : s::event('missing-translation', $x);
    if (false !== strpos($o, '{')) {
        return s::event('process-translation-macros', $o);
    } else {
        return $o;
    }
}

function load_language_file($file, $use_index = 1)
{
    $ts = array();
    if ($use_index) {
        $block = array();
        $f = fopen($file, 'r');
        while ($line = fgets($f)) {
            $line = rtrim($line);
            if ( ! $line) {
                // import translations
                if ($block) {
                    if ($block[$use_index] != 'TK') {
                        $ts[ $block[0] ] = $block[$use_index];
                    }
                    $block = array();
                }
            } else {
                if ($line[0] != '#') {
                    $block[] = $line;
                }
            }
        }
        if ($block) {
            if ($block[$use_index] != 'TK') {
                $ts[ $block[0] ] = $block[$use_index];
            }
        }
    }
    return $ts;
}


function set_language($language)
{
    // R::warning('set-language %s', $language);
    s::set('language', $language);
    s::event('set-language', $language);
}


function languages()
{
    return s::event('languages', array());
}
