<?php

/*
 * HTML helpers
 *
 * work in progress
 *
 * all form input generators can take either direct value, or array with $name key as a value
 *
 */

class HTML {

    # HTML::$handlers
    # ---------------
    # Here are stored custom html handlers that are handled via callstatic magic.
    #
    static $handlers = [
        'sample' => 'html::input',
    ];

    static function __callstatic($function, $args)
    {
        $all_args = func_get_args();
        if ( ! ($handler = get($function, HTML::$handlers))) {
            s::warn('html::__callstatic: no handler for control %s', $function);
            return false;
        }
        s::call_array($handler, $args);
    }

    static function select($name, $objvalue = Null, $opts = Null)
    {
        normalize_options($opts);
        $opt_choices = get_option($opts, 'choices', null);
        $opt_empty   = get_option($opts, 'empty', true);

        $opts['name'] = $name;

        $value = HTML::extract($name, $objvalue);

        if ($opt_choices === null) {
            s::warn('HTML::select requires [choices] to be specified.');
            return;
        }


        HTML::tag('select', $opts);

        if ($opt_empty and ! isset($opt_choices['']) and !isset($opt_choices[0])) {
            echo '<option></option>';
        }

        if ($value and ! isset($opt_choices[$value])) {
            h('<option value="%s">%s?</option>', trim($value), $value);
        }

        foreach($opt_choices as $val=>$text) {
            printf('<option value="%s"%s>%s</option>',
                htmlspecialchars(trim($val)),
                ("$value" == "$val" ? ' selected="selected"' : Null),
                   htmlspecialchars($text));
        }

        echo '</select>';
    }


    # html::label
    # -----------
    # Display a label.
    #
    # Different from other elements, label might print only "class" and "id"
    # options (id gets transformed to "for").
    #
    static function label($title, $opts = Null)
    {
        $opt_id = get_option($opts, 'id');
        $opt_for = get_option($opts, 'for');
        $opt_class = get_option($opts, 'class');

        if ($title === false) return;

        # only class and id/for are passed to label.
        $label_opts = [
            'class' => $opt_class,
            'for' => $opt_for ? $opt_for : $opt_id
        ];

        HTML::wrapped_tag('label', $title, $label_opts);
    }

    # html::hidden
    # ------------
    # Draw input type="hidden"
    #
    static function hidden($name, $objvalue = Null, $opts = Null)
    {
        normalize_options($opts);
        $opts['type'] = 'hidden';
        HTML::input($name, $objvalue, $opts);
    }

    # html::password
    # --------------
    # Draw input type="password"
    #
    static function password($name, $objvalue = Null, $opts = null)
    {
        $opts['type'] = 'password';
        return HTML::input($name, $objvalue, $opts);
    }


    # html::email
    # -----------
    # Draw input type="email"
    #
    static function email($name, $objvalue = Null, $opts = null)
    {
        $opts['type'] = 'email';
        return HTML::input($name, $objvalue, $opts);
    }


    # html::input
    # -----------
    # Draw any input
    #
    static function input($name, $objvalue = Null, $opts = Null)
    {
        normalize_options($opts);

        $opts['value'] = HTML::extract($name, $objvalue);
        $opts['name'] = $name;

        HTML::tag('input', $opts);
    }

    # html::checkbox
    # --------------
    # Draw checked/unchecked checkbox
    static function checkbox($name, $objvalue = Null, $opts = Null)
    {
        normalize_options($opts);
        $opts['type'] = 'checkbox';
        $opts['name'] = $name;

        $value = HTML::extract($name, $objvalue);
        $value = $value === true || strtolower_utf($value) === 'on' || strtolower_utf($value) === 't';

        if ($value) {
            $opts['checked'] = 'checked';
        }

        HTML::tag('input', $opts);
    }
    
    # html::radio
    # -----------
    # Draw input type="radio"
    #
    # $objvalue should be checked/unchecked status.
    # Value should go into the $opts['value']
    #
    static function radio($name, $objvalue = Null, $opts = Null)
    {
        normalize_options($opts);
        $opts['type'] = 'radio';
        $opts['name'] = $name;

        $checked = HTML::extract($name, $objvalue);
        $checked = $checked === true || strtolower_utf($checked) === 'on' || strtolower_utf($checked) === 't';

        if ( ! array_key_exists('value', $opts)) {
            s::warn('html::radio: value not specified for %s', $name);
        }

        if ($checked) {
            $opts['checked'] = 'checked';
        }

        HTML::tag('input', $opts);
    }


    # html::file
    # ----------
    # input(type="file")
    static function file($obj_name, $object = null, $opts = Null)
    {
        normalize_options($opts);

        $opts['value'] = HTML::extract($name, $object);
        $opts['name'] = $name;
        $opts['type'] = 'file';

        HTML::tag('input', $opts);
    }


    # html::clear
    # -----------
    # Insert an ugly float-clearing div
    #
    # Not sure if this needs to be here
    #
    static function clear($where = null)
    {
        echo '<div style="margin:0;height:1px;overflow:hidden;';
        switch ($where) {
        case 'l':
        case 'left':
            echo 'clear:left;';
            break;
        case 'r':
        case 'right':
            echo 'clear:right;';
            break;
        default:
            echo 'clear:both;';
            break;
        }
        echo '"></div>';
    }


    # html::submit
    # ------------
    # Draw any button
    #
    static function button($title = 'Click', $opts = null/* alias of submit */)
    {
        $args = func_get_args();
        return HTML::wrapped_tag('button', $title, $opts);
    }

    # html::submit
    # ------------
    # Draw a submit button
    #
    static function submit($title='Submit', $opts = null)
    {
        normalize_options($opts);
        $opts['type'] = 'submit';
        HTML::wrapped_tag('button', $title, $opts);
    }


    # html::textarea
    # --------------
    # Draw a textarea
    static function textarea($name, $objvalue = Null, $opts = Null)
    {
        normalize_options($opts);
        $value = HTML::extract($name, $objvalue);
        $opts['name'] = $name;

        HTML::wrapped_tag('textarea', htmlspecialchars($value), $opts);

    }


    static function form($opts = Null)
    {
        normalize_options($opts);
        $opt_method    = get_option($opts, 'method', 'post');
        $opt_action    = get_option($opts, 'action', '?');
        $opt_multipart = get_option($opts, 'multipart', false);

        $opts['method'] = $opt_method;
        $opts['action'] = $opt_action;
        if ($opt_multipart) {
            $opts['enctype'] = 'multipart/form-data';
            unset($opts['multipart']);
        }

        HTML::tag('form', $opts);
        if (isset($_GET['XDEBUG_PROFILE']) || isset($_POST['XDEBUG_PROFILE'])) {
            HTML::hidden('XDEBUG_PROFILE', 'yes');
        }
    }

    static function h1($text, $opts = Null)
    {
        HTML::wrapped_tag('h1', $text, $opts);
    }

    static function h2($text, $opts = Null)
    {
        HTML::wrapped_tag('h2', $text, $opts);
    }

    static function h3($text, $opts = Null)
    {
        HTML::wrapped_tag('h3', $text, $opts);
    }

    static function h4($text, $opts = Null)
    {
        HTML::wrapped_tag('h4', $text, $opts);
    }

    static function p($text, $opts = Null)
    {
        HTML::wrapped_tag('p', $text, $opts);
    }




    static function extract($name, &$objvalue, $default = null)
    {
        if (is_array($objvalue)) {
            return array_key_exists($name, $objvalue) ? $objvalue[$name] : $default;
        } else if (is_object($objvalue)) {
            return property_exists($objvalue, $name) ? $objvalue->$name : $default;
        } else {
            return $objvalue;
        }
    }

    static function wrapped_tag($tag, $text = Null, $opts = Null)
    {
        HTML::tag($tag, $opts);
        echo $text;
        echo "</$tag>";
    }

    static function tag($tag, $opts = null)
    {
        normalize_opts($opts);

        $attrs = array();
        $skip_attrs = ['choices','layout', 'label', 'wrapper-class'];
        foreach($opts as $k=>$v) {

            # skip anything starting with a dot — some system entries
            if ($k && $k[0] == '.') continue;
            if (in_array($k, $skip_attrs)) continue;
            if (starts_with('form-', $k)) continue;

            if ($v === false) {
                // hmm
                if ($k == 'autocomplete') {
                    $attrs[] = hs('%s="off"', $k);
                } else {
                    s::warn('Unsupported false option %s', $k);
                }
            } else if (is_array($v)) {
                $attrs[] = hs('%s="%s"', $k, implode(' ', $v));
            } else if ($v === true) {
                $attrs[] = hs('%s="%s"', $k, $k);
            } else {
                $attrs[] = hs('%s="%s"', $k, $v);
            }
        }
        $attrs = implode(' ', $attrs);
        echo '<', $tag, ($attrs ? ' ' . $attrs : ''), '>';
    }


}

