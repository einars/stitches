<?php

class Installer {

    # class prefixes that are searched by installer
    static $class_prefix_order = array(
        'inst_',     // install tables
        'patch_',    // alter tables
        'populate_', // fill empty tables with data
        'fix_',      // any other fixes
    );

    # { class_name => installation_status } is gathered here in response to
    # 'install' event.
    static $steps = array();


    # Use Installer::load() to autoinclude all of the installer class definitions
    static function load()
    {
    }

    static function run()
    {
        set_time_limit(600);
        # in response to this event, the Installer::$tasks list will be populated
        s::emit('install');

        foreach(installer::$class_prefix_order as $prefix) {
            Installer::add_steps($prefix);
        }

        if (array_key_exists('auto', $_REQUEST) or s::cli()) {
            return Installer::autoinstall();
        }

        echo '<div class="installer">';
        h('<a class="installer-autoinstall" href="?auto=1">%s</a>', 'Install all automatically');

        $applied_fixes_to_print = 0;
        $applied_fixes_unprinted = 0;

        foreach(Installer::$steps as $class_name => $_) {
            $obj = new $class_name;

            if ( ! is_subclass_of($obj, 'InstallerTask')) {
                s::error('Installer: %s really needs to extend InstallerTask.', $class_name);
            }

            $title   = $obj->get_title();
            $is_applied = $obj->is_applied();

            Installer::$steps[ $class_name ] = $is_applied;

            if ($is_applied) continue;

            echo '<div class="installer-task installer-task-unapplied">';
            h('<h2 class="installer-task-title">%s</h2>' , $title);

            if ($title != $class_name) {
                h('<span class="installer-task-class">%s</span>', $class_name);
            }

            if (Installer::confirm($class_name)) {
                if (Installer::exec_step($obj)) {
                    StPage::reload();
                } else {
                    $is_applied = false;
                    h('<p class="installer-message">%s</p>'
                        , 'Installation attempted, but remains incomplete.');

                }
            }

            echo '</div>';
        }

        $n_applied = 0;
        $n_remaining = 0;

        foreach(Installer::$steps as $class_name => $is_applied) {
            if ($is_applied) {
                $n_applied++;
            } else {
                $n_remaining++;
            }
        }

        if ( ! $n_remaining) {
            StPage::add_style('.installer-autoinstall { display: none }');
            h('<p class="installer-success">%s</p>', 'Installation complete.');
            # reload configuration
            s::emit('configure');
            s::emit('install-done');
        }
    }


    static function classes_starting_with($pattern)
    {
        $classes = get_declared_classes();
        $out = array();
        foreach($classes as $class_name) {
            if (starts_with($pattern, $class_name)) {
                $out[] = $class_name;
            }
        }
        return $out;
    }

    static function autoinstall()
    {
        $broken = false;
        $something_done = false;

        while (true) {
            $something_done = false;
            $broken = false;

            foreach(Installer::$steps as $class_name=>$foo) {
                $obj = new $class_name();
                if ( ! $obj->is_applied()) {

                    if (s::cli()) {
                        h("%s\n", $class_name);
                    }

                    
                    if (Installer::exec_step($obj)) {
                        $something_done = true;
                    } else {
                        $broken = true;
                        break;
                    }

                }
            }

            if ( ! $something_done) {
                break;
            }
        }

        # reload configuration
        s::emit('configure');
        s::emit('install-done');

        if (s::cli()) {
            exit;
        } else {
            StPage::redirect('/install');
        }
    }

    static function confirm($class_name)
    {
        if (get('confirm') === $class_name) {
            return true;
        }
        form::begin(['class' => 'installer-confirmation']);
        form::hidden('confirm', $class_name);
        form::submit('install');
        form::end();
    }

    static function exec_step($step_obj)
    {
        $res = false;
        if ( ! $step_obj->is_applied()) {
            $step_obj->apply();
            $res = $step_obj->is_applied();
            if ($res) {
                $step_obj->when_applied();
            }
        }
        return $res;
    }

    static function add_step($class_name)
    {
        Installer::$steps[$class_name] = false;
    }

    static function add_steps($class_prefix)
    {
        foreach(Installer::classes_starting_with($class_prefix) as $class_name) {
            Installer::add_step($class_name);
        }
    }
}

class InstallerTask
{
    function apply()
    {
        s::warn('You have to override InstallerTask::apply');
        // override me
    }

    function is_applied()
    {
        s::warn('You have to override InstallerTask::is_applied');
        return false;
    }

    public function when_applied()
    {
        // you may override this, for some additional steps to be taken
    }

    public function get_title()
    {
        if (isset($this->title)) {
            return $this->title;
        } else {
            return get_class($this);
        }
    }
}


# InstallerTableTask
# ------------------
# Base class for tasks that install table.
# Convention: prefix InstallerTableTasks class names with inst_ 
# so that they would run first.
#
# Set $sql memeber in overriden class to create table sql:
#
#   class inst_some_table extends InstallerTableTask {
#     var $sql = 'create table some_table (id serial primary key, something varchar(250))';
#   }
#
class InstallerTableTask extends InstallerTask
{
    protected $sql = 'create table override_me (field varchar(32))';

    function get_title()
    {
        return hs('Create table %s', $this->get_name());
    }

    function is_applied()
    {

        if ( ! s::db()) {
            return true;
        }

        $name = $this->get_name();
        if ($name == 'sample') {
            return true;
        }
        return db::table_exists($name);
    }

    function apply()
    {
        db::query($this->get_sql());
    }

    function get_sql()
    {
        return $this->adapt_for_db_engine($this->sql, s::get('database'));
    }

    function adapt_for_db_engine($sql, $filter)
    {
        $lines = array();
        foreach(explode("\n", $sql) as $line) {
            $line = trim($line);
            if (preg_match('/^[a-z]+\:/', $line)) {
                if (starts_with("$filter:", $line)) {
                    $lines[] = substr($line, strlen($filter) + 1);
                }
            } else {
                $lines[] = $line;
            }
        }
        return implode("\n", $lines);
    }

    function get_name()
    {
        preg_match('/table +[`"]?([a-zA-Z0-9_.]+)/i', $this->get_sql(), $matches);
        return $matches[1];
    }
}
