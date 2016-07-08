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

    static $class_button_wrapper = 'form-group form-button-bar';
    static $class_button = 'btn btn-default';
    static $class_button_submit = 'btn btn-primary';
    static $class_checkbox_wrapper = 'checkbox';
    static $class_form_group = 'form-group';
    static $class_form_control = 'form-control';
    static $class_optiongroup_wrapper = 'radios';
    static $class_radio_wrapper = 'radio';

    static $class_text = 'form-control-static';
    static $class_label = 'control-label';
    static $class_label_selected = 'control-label-selected';

    static $grid = [
        'grid' => 3,
        'layout' => 'form-horizontal',
        'label' => 'col-sm-3',
        'content' => 'col-sm-9',
        'offset' => 'col-sm-offset-3',
    ];

    static $handlers = [

        '*' => 'form::draw_control',

        'text'    => 'form::draw_static_text',
        
        'input'    => 'form::draw_control',
        'email'    => 'form::draw_control',
        'password' => 'form::draw_control',
        'textarea' => 'form::draw_control',
        'file'     => 'form::draw_control',
        'select'   => 'form::draw_control',

        'hidden' => 'form::direct_to_html',
        'h1' => 'form::direct_to_html',
        'h2' => 'form::direct_to_html',
        'h3' => 'form::direct_to_html',
        'h4' => 'form::direct_to_html',
        'p'  => 'form::direct_to_html',
    ];

    static function __callstatic($function, $args)
    {

        $all_args = func_get_args();
        if ( ! ($handler = get($function, Form::$handlers))) {
            // s::warn('Form has no handler for %s', $function);
            return s::call_array('form::draw_control', $all_args);
        } else {
            return s::call_array($handler, $all_args);
        }
    }

    static function set_grid($label_size)
    {
        Form::$grid['grid'] = $label_size;
        if ($label_size) {
            Form::$grid['label'] = sprintf('col-sm-%d', $label_size);
            Form::$grid['content'] = sprintf('col-sm-%d', 12 - $label_size);
            Form::$grid['offset'] = sprintf('col-sm-offset-%d', $label_size);
        } else {
            Form::$grid['label'] = '';
            // Form::$grid['content'] = 'col-sm-12';
            Form::$grid['content'] = '';
            Form::$grid['offset'] = '';
        }
    }


    static function begin($notice = null, $opts = null)
    {
        HTML::form($opts);
        $layout = get_option($opts, 'layout', Form::$grid['layout']);
        h('<div class="%s">', $layout);
        HTML::hidden('save', 'yes');
        if ($notice) {
            if (is_string($notice)) {
                $class = 'notice notice-success text-success bg-success';
                $text = $notice;
                Form::draw_static_text('text', ['', $notice, ['class' => 'notice notice-success']]);
            } else {
                $class = 'notice notice-failure text-danger bg-danger';
                $text = failure_text($notice);
            }
            Form::draw_static_text('text', ['', "<div class='$class'>$text</div>"]);
        }
    }

    static function buttons_start()
    {
        h('<div class="%s">', Form::$class_button_wrapper);
        h('<div class="%s"></div>', Form::$grid['label']);
        h('<div class="%s">', Form::$grid['content']);
    }
    static function buttons_end()
    {
        echo '</div>';
        echo '</div>';
    }


    static function end($submit_button_title = null)
    {
        if ($submit_button_title) {
            form::buttons_start();
            Form::submit($submit_button_title);
            form::buttons_end();
        }
        echo '</div>'; // $grid['content']
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

    static function ctrl_checkbox($name, $objvalue, $opts)
    {
        Form::normalize_opts($opts, $require_id = false);
        $opt_label = get_option($opts, 'label');

        $opts['type'] = 'checkbox';
        h('<div class="%s">', Form::$class_checkbox_wrapper);
        // h('<label class="%s">', Form::$class_label);
        h('<label>'); // no need for a label class in bootstrap checkbox
        HTML::checkbox($name, $objvalue, $opts);
        echo any($opt_label, $name);
        echo '</label>';
        echo '</div>';
    }


    # form::draw_control
    # ------------------
    # Draw a html::* control with a (name, value, opts) calling convention
    #
    static function draw_control($element_type, $args)
    {
        // html::inputs have calling convention of (name, value, opts)
        // label is expected to be in opts
        $name  = get(0, $args);
        $value = get(1, $args);
        $opts  = get(2, $args);

        Form::normalize_opts($opts, $require_id = true);
        $opt_label = get_option($opts, 'label', null);
        $opt_wrapper_class = get_option($opts, 'wrapper-class', null);
        $opt_form_hint = get_option($opts, 'form-hint', null);
        $opt_form_control_class = get_option($opts, 'form-control-class', Form::$class_form_control);

        $no_label_for = ['checkbox'];
        $no_control_class = ['checkbox', 'file'];

        $classes = [];
        $classes[] = Form::$class_form_group;
        $classes[] = 'form-group-' . safe_name($element_type, $delimiter = '-');
        if ($opt_wrapper_class) {
            $classes[] = $opt_wrapper_class;
        }

        h('<div class="%s">', implode(' ', $classes));

        if ($opt_label || form::$grid['grid']) {
            # make a copy
            $opts_for_label = $opts;
            unset($opts['label']);

            $opts_for_label['class'] = [
                Form::$class_label, Form::$grid['label']
            ];
            if (in_array($element_type, $no_label_for)) {
                // 
                HTML::label('&nbsp;', $opts_for_label);
            } else {
                HTML::label($opt_label, $opts_for_label);
            }

        }


        if ($opt_label || form::$grid['grid']) {
            h('<div class="%s">', Form::$grid['content']);
        } else {
            h('<div>');
        }

        if (in_array($element_type, $no_control_class) || ! $opt_form_control_class) {
            // skip class for file inputs:
        } else{
            $opts['class'][] = $opt_form_control_class;
        }
        switch($element_type) {
        case 'checkbox':
            form::ctrl_checkbox($name, $value, $opts + ['label' => $opt_label]);
            break;
        default:
            s::call('html::' . $element_type, $name, $value, $opts);
        }

        if ($opt_form_hint) {
            h('<p class="help-block">%s</p>', $opt_form_hint);
        }

        echo '</div>';

        echo '</div>';
    }



    # plain, labelled html
    static function draw_static_text($element_type, $args)
    {
        $label = array_shift($args);

        h('<div class="%s %s">'
            , Form::$class_form_group
            , 'form-group-text'
        );

        if ($label || form::$grid['grid']) {
            HTML::label($label, ['class' => [Form::$class_label, Form::$grid['label']]]);
        }

        if ($label !== false) {
            h('<div class="%s">', Form::$grid['content']);
        }
        h('<div class="%s">', Form::$class_text);
        if ($element_type === 'text') {
            echo $args[0];
        } else {
            s::call_array('html::' . $element_type, $args);
        }
        echo '</div>';
        if ($label !== false) {
            h('</div>');
        }

        echo '</div>';
    }


    static function ctrl_optiongroup($name, $objvalue, $opts)
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

        foreach($opt_choices as $k => $v) {
            h('<div class="%s">', Form::$class_radio_wrapper);

            $checked = ($k == $value); // sic: inexact comparison, == to simplify numeric indices
            
            if ($checked) {
                h('<label class="%s">', Form::$class_label_selected);
            } else {
                h('<label>', Form::$class_label);
            }
            html::radio($name, ($k == $value), [
                'value' => $k,
            ]);

            echo $v; // non-escaped

            echo '</label>';
            echo '</div>';
        }

    }

    static function optiongroup($name, $objvalue, $opts = null)
    {
        Form::normalize_opts($opts);
        $opt_label = get_option($opts, 'label');

        h('<div class="%s %s">'
            , Form::$class_form_group
            , 'form-group-optiongroup'
        );

        HTML::label($opt_label, ['class' => [Form::$class_label, Form::$grid['label']]]);

        h('<div class="%s">', Form::$grid['content']);

        Form::ctrl_optiongroup($name, $objvalue, $opts);

        echo '</div>'; // grid['content']
        echo '</div>'; // form-group
    }


    # form::confirm
    # -------------
    # A simple confirmation drop-in.
    #
    #   if (form::confirm('Really delete this record?')) {
    #     db::query('delete from my_table where record_id=%d', $id);
    #   }
    #
    static function confirm($message, $opts = null)
    {
        $opt_cancel_label = get_option($opts, 'cancel-label', 'Cancel');
        $key = md5($message);
        if (get('confirmed', $_POST) == $key) {
            return true;
        }
        $grid = form::$grid['grid'];
        form::set_grid(0);

        form::begin(null, array('class' => 'confirmation'));
        form::hidden('confirmed', $key);
        foreach($_GET as $k=>$v) {
            form::hidden($k, $v);
        }
        form::submit($message);
        if ($opt_cancel_label !== false) {
            h('<a href="?" class="btn btn-default small-margin-left">%s</a>', $opt_cancel_label);
        }
        form::end();
        form::set_grid($grid);
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
                $opts['class'] = explode(' ', $opts['class']);
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

    # form::install_control
    # ---------------------
    # Install a new custom control handler
    #
    #   form::install_control('my_elem', 'draw_my_elem');
    #   function draw_my_elem($name, $value, $opts) {
    #     $value = html::extract($name, $value);
    #     // draw your input element
    #     ...
    #   }
    #
    # Now you can use the new element:
    #
    #   form::my_elem($name, $value, [
    #     'label' => 'prettiness',
    #   ]);
    #
    static function install_control($control, $handler)
    {
        html::$handlers[$control] = $handler;
        form::$handlers[$control] = 'form::draw_control';
    }


}
