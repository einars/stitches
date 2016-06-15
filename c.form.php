<?php

# Form
# ====
#
# A helper for simpler form design.
#
# Via __callstatic magic, form wraps calls to matching HTML::* elements and
# wraps them into a proper, labelled context (currently — bootstrap).
#
# Form::input('input_name', $value, [
#  'label' => 'I am an input',
#  ... opts for html::input ...
# ]
#
# will result in bootstrap'ish
#
# <div class="form-group">
#   <label for="form-id-1">I am an input</label>
#   <input class="form-control" id="form-id-1" value="...">
# </div>

class Form {

    static $class_button_wrapper = 'form-buttons';
    static $class_button = 'btn';
    static $class_button_submit = 'btn btn-primary';
    static $class_checkbox_wrapper = 'checkbox';
    static $class_form_group = 'form-group';
    static $class_form_control = 'form-control';
    static $class_optiongroup_wrapper = 'radios';
    static $class_radio_wrapper = 'radio';

    static $class_checkbox_label = 'checkbox-label';
    static $class_radio_label = 'radio-label';
    static $class_radio_label_selected = 'radio-label-selected';
    

    static $handlers = [
        '*' => 'form::form_group',
        'hidden' => 'form::direct_to_html',
        'h1' => 'form::direct_to_html',
        'h2' => 'form::direct_to_html',
        'h3' => 'form::direct_to_html',
        'h4' => 'form::direct_to_html',
        'p' => 'form::direct_to_html',
        'p' => 'form::direct_to_html',
    ];

    static function __callstatic($function, $args)
    {

        $all_args = func_get_args();
        if ( ! ($handler = get($function, Form::$handlers))) {
            $handler = get('*', Form::$handlers, 'form::form_group');
        }
        return s::call_array($handler, $all_args);
    }


    static function begin($notice = null, $opts = null)
    {
        HTML::form($opts);
        HTML::hidden('save', 'yes');
        print_notice($notice);
    }


    static function end($submit_button_title = null)
    {
        if ($submit_button_title) {
            h('<div class="%s">', Form::$class_button_wrapper);
            Form::submit($submit_button_title);
            echo '</div>';
        }
        echo '</form>';
    }

    static function direct_to_html($function, $args)
    {
        return s::call_array('html::' . $function, $args);
    }

    static function submit($submit_title, $opts = null)
    {
        Form::normalize_opts($opts, $require_id = false);
        $opts['class'][] = Form::$class_button_submit;
        HTML::submit($submit_title, $opts);
    }

    static function button($title, $opts = null)
    {
        Form::normalize_opts($opts, $require_id = false);
        $opts['class'][] = Form::$class_button;
        HTML::button($title, $opts);
    }

    static function checkbox($name, $objvalue, $opts)
    {
        Form::normalize_opts($opts, $require_id = false);
        $opt_label = get_option($opts, 'label');
        $opts['type'] = 'checkbox';
        h('<div class="%s">', Form::$class_checkbox_wrapper);
        h('<label class="%s">', Form::$class_checkbox_label);
        HTML::checkbox($name, $objvalue, $opts);
        echo any($opt_label, $name);
        echo '</label>';
        echo '</div>';
    }

    static function form_group($element_type, $args)
    {
        $name  = get(0, $args);
        $value = get(1, $args);
        $opts  = get(2, $args);

        Form::normalize_opts($opts, $require_id = true);

        $opt_label = get('label', $opts, null);

        h('<div class="%s %s-%s">'
            , Form::$class_form_group
            , Form::$class_form_group
            , safe_name($element_type, $delimiter = '-')
        );

        if ($opt_label) {
            $opts_no_class = $opts;
            unset($opts_no_class['class']);
            HTML::label($opt_label, $opts_no_class);

            unset($opts['label']);
        }

        $opts['class'][] = Form::$class_form_control;
        s::call('html::' . $element_type, $name, $value, $opts);

        echo '</div>';
    }

    static function optiongroup($name, $objvalue, $opts = null)
    {
        Form::normalize_opts($opts);
        $opt_choices = get_option($opts, 'choices');

        if ( ! $opt_choices) {
            s::warn('Form::optiongroup(%s) requires option "choices", associative array of optiongroup choices', $name);
            return;
        }

        $value = HTML::extract($name, $objvalue);

        $has_selected_element = isset($opt_choices[$value]);

        if ( ! $has_selected_element) {
            $value = first_key($opt_choices);
        }

        $opts['class'][] = Form::$class_optiongroup_wrapper;
        HTML::tag('div', $opts);

        foreach($opt_choices as $k => $v) {
            h('<div class="%s">', Form::$class_radio_wrapper);

            $checked = ($k == $value); // sic: inexact comparison, == to simplify numeric indices
            
            if ($checked) {
                h('<label class="%s %s">', Form::$class_radio_label, Form::$class_radio_label_selected);
            } else {
                h('<label class="%s">', Form::$class_radio_label);
            }
            html::radio($name, ($k == $value), [
                'value' => $value,
            ]);

            echo $v; // non-escaped

            echo '</label>';
            echo '</div>';
        }
        echo '</div>';
    }

    # form::confirm
    # -------------
    # A simple confirmation drop-in.
    #
    # Caveat: doesn't forward request query parameters, all of the data needs
    # to be inside the url - /users/12, not users/?id=12
    # 
    #   if (form::confirm('Really delete this record?')) {
    #     db::query('delete from my_table where record_id=%d', $id);
    #   }
    #
    static function confirm($message, $opts = null)
    {
        $key = md5($message);
        if (get('confirmed', $_POST) == $key) {
            return true;
        }
        form::begin(array('class' => 'confirmation'));
        form::hidden('confirmed', $key);
        form::end($message);
    }



    # normalize_opts, but with some form-specific additions
    #
    # require_id: require 'id' attribute in the options. Will invent a new,
    # unique id, if missing.
    static function normalize_opts(&$opts, $require_id = false, $id_hint = null)
    {
        normalize_opts($opts);

        # convert 'class' to a list of classes
        if (isset($opts['class']) && $opts['class']) {
            if ( ! is_array($opts['class'])) {
                $opts['class'] = explode(' ', $class);
            }
        } else {
            $opts['class'] = [];
        }

        if ($require_id && ! isset($opts['id'])) {
            $opts['id'] = Form::next_id(any($id_hint, 'input'));
        }
    }

    # form::next_id
    # -------------
    # Generate a sequence, prefixed with $key
    #
    #   form::next_id('something');
    #   > something-1
    #   form::next_id('something');
    #   > something-2
    #   form::next_id('something-else');
    #   > something-else-1
    #
    static function next_id($key)
    {
        static $ids = [];

        # make keys prettier
        $key = str_replace('_', '-', $key);

        $ids[$key] = (($id = get($key, $ids, 1))) + 1;
        return $key . '-' . $id;
    }
}
