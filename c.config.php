<?php

# Configuration
#
# Simple persistent (if database used) configuration management
#
# Define configuration value:
#
# Config::
#   TK

class Config {
    static $values = [];
    static $types = [];

    static $initialized = false;

    static function load()
    {
        s::on('install', 'Config::install');
    }

    static function initialize()
    {
        // just fetch settings from db
        if (Config::$initialized) return;
        Config::$initialized = true;

        if ( ! s::db()) {
            return; // don't do anything
        }

        $res = db::query('select setting, value, type, default_value from configuration');
        while($r = db::fetch($res)) {
            $value = $r['value'] === null ? $r['default_value'] : $r['value'];
            Config::$values[$r['setting']] = Config::deserialize($value, $r['type']);
            Config::$types[$r['setting']] = $r['type'];
        }
    }

    static function deserialize($value, $type)
    {
        switch($type) {
        case 'bool':
            return ($value === 'true' ? true : false);
        case 'int':
            if ($value === null) return null;
            return (int)$value;
        default:
            if ($value === null) return null;
            return $value;
        }
    }

    static function serialize($value, $type)
    {
        if ($value === null) return null;
        switch($type) {
        case 'bool':
            if ($value === true) return 'true';
            if ($value === false) return 'false';
            s::error('Config::serialize: cannot serialize %s to bool.', repr($value));
        case 'int':
            if (is_int($value)) {
                return sprintf('%d', $value);
            }
            R::error('Cannot serialize %s to int.', repr($value));
        case 'string':
            return '' . $value;
        }
    }


    static function get($setting)
    {

        if (array_key_exists($setting, s::$config)) {
            // global $config forcefully overrides anything
            return s::$config[$setting];
        }

        if ( ! Config::$initialized) Config::initialize();

        if ( ! array_key_exists($setting, Config::$values)) {
            // attempt to reload configuration: possibly code has changed
            s::emit('configure');
            if ( ! array_key_exists($setting, Config::$values)) {
                s::error('Unknown configuration setting %s', $setting);
            }
        }

        return Config::$values[$setting];
    }

    static function set($setting, $value)
    {
        if ( ! Config::$initialized) Config::initialize();

        if ( ! array_key_exists($setting, Config::$values)) {
            // attempt to reload configuration: possibly code has changed
            s::emit('configure');
            if ( ! array_key_exists($setting, Config::$values)) {
                s::error('Unknown configuration setting %s', $setting);
            }
        }

        if (Config::$values[$setting] == $value) return;

        if (s::db()) {
            db::query('update configuration set value=%s where setting=%s'
                , config::serialize($value, Config::$types[$setting])
                , $setting
            );
        }
    }

    static function define($setting, $default_value, $description)
    {

        // Defines generally happen _only_ after a previously-unknown setting
        // is requested, and on installation.

        if ( ! Config::$initialized) Config::initialize();

        $type = 'string';
        if (is_int($default_value)) {
            $type = 'int';
        } else if ($default_value === true || $default_value === false) {
            $type = 'bool';
        }

        if ( ! s::db()) return;


        $default_value_s = Config::serialize($default_value, $type);

        if ( ! array_key_exists($setting, Config::$values)) {
            // new, unknown setting
            db::insert('configuration', array(
                'setting' => $setting,
                'type' => $type,
                'value' => null,
                'default_value' => $default_value_s,
                'description' => $description,
            ));

            Config::$values[$setting]   = $default_value;
            Config::$types[$setting]    = $type;
        } else {
            // maybe update an existing setting
            $existing = db::get('select type, default_value, description from configuration where setting=%s', $setting);
            if ($existing['type'] !== $type or $existing['default_value'] !== $default_value_s or $existing['description'] !== $description) {
                db::query('update configuration set type=%s, default_value=%s, description=%s where setting=%s'
                    , $type
                    , $default_value_s
                    , $description
                    , $setting
                );
            }
        }

    }

    static function install()
    {
        if ( ! s::db()) {
            return;
        }

        if ( ! db::table_exists('configuration')) {
            # currently supported types: string, bool
            db::query("
create table configuration(
    setting varchar(64) primary key,
    value varchar(250),
    type varchar(8),
    description varchar(250),
    is_deprecated bool default false,
    default_value varchar(250)
)
");
        }

        s::emit('configure');

    }

    static function deprecate($setting)
    {
        if (s::db()) {
            db::query('update configuration set is_deprecated=true where setting=%s', $setting);
        }
    }

}

