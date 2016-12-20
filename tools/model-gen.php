<?php

# model-gen.php
# -------------
#
# Generate a model class,
#
# php path/to/model-gen.php
#
# Database connection is taken from the config.php file, expected to be in
# current directory you're running this from.
#
# config.php must create variable called $config.

{
    isset($_SERVER['REMOTE_ADDR']) and die('This script is to be started manually.');

    # load stitches
    require_once('config.php');
    require_once(dirname(__FILE__) . '/../stitches.php');

    if (sizeof($_SERVER['argv']) == 1) {
        $self = __FILE__;
        echo <<<USAGE
Usage: php $self TABLE_NAME > OUTPUT_FILE
Connect to database and build a model for TABLE_NAME.

Class name will be singularized table name (customers -> CustomerModel).

Example:
    php libs/stitches/model-gen.php customers > site/customermodel.class.php

If run from app-root, it will create a CustomerModel class in the app's site folder.
Thanks to autoload features, you can use it automatically:
    \$c = new CustomerModel();
    \$c = new CustomerModel(\$customer_id);


USAGE;
        die();
    }

    s::configure($config);
    $tables = array_slice($_SERVER['argv'], 1);
    foreach($tables as $table) {
        generate_model($table);
    }
}


function generate_model($table)
{
    $engine = s::get('database');

    echo "<?php\n\n";

    switch($engine) {
    case 'postgresql':
        output_model(generate_pgsql_repr($table)); break;
    case 'mysql':
        output_model(generate_mysql_repr($table)); break;
    default:
        s::error('generate_model: database engine %s is not supported.', $engine);
    }
}


function generate_mysql_models($tables = null)
{
    $r = generate_mysql_repr($table);
    output_model($r);
}

function singular_of_plural($s)
{
    if (substr($s, -3) == 'ies') {
        return substr($s, 0, -3) . 'y';
    } else if (substr($s, -3) == 'ses') {
        return substr($s, 0, -2);
    } else if (substr($s, -2) == 'es') {
        return substr($s, 0, -2) . 'e';
    } else if (substr($s, -1) == 's') {
        return substr($s, 0, -1);
    } else {
        return $s;
    }

}
function should_remove_prefix($p)
{
    if (strlen($p) < 3) return false;

    return true;
}

function camel_of_underscore($s)
{
    // handle exceptions
    $s = singular_of_plural($s);
    $parts = explode('_', $s);
    $out = array_filter(array_map('ucfirst', $parts), 'should_remove_prefix');
    return implode('', $out);
}


function skip_field_name($field_name)
{
    if (substr($field_name, 0, 3) == 'ex_') {
        // old fields, unused
        return true;
    }

    if (substr($field_name, 0, 4) == 'del_') {
        // manually backed up removed fields, don't touch
        return true;
    }

}

function generate_mysql_repr($table)
{
    $repr = array(
        'table' => $table,
        'use-ordering' => false,
        'key' => null,
        'fields' => array (),
    );

    $res = db::query('select * from %S limit 0', $table);
    $n_fields = mysqli_num_fields($res);

    $types = array(
        'bigint' => 'int',
        'int' => 'int',
        'varchar' => 'string',
        'mediumtext' => 'string',
        'datetime' => 'datetime',
        'date' => 'date',
        'tinyint(1)' => 'bool',
        'char(36)' => 'string', // uuid
        'char(2)' => 'string', // something
        'text' => 'string', // uuid
        'decimal' => 'money',
    );

    $fields = db::get_assoc_list('show fields from %S', $table);
    foreach($fields as $f) {

        $f = (object)$f;
        $name = $f->Field;
        $type = $f->Type;

        if ($f->Key == 'PRI') {
            $repr['key'] = $name;
        }

        if (skip_field_name($name)) continue;

        if ($name == 'ordering') {
            $repr['use-ordering'] = true;
            continue;
        }

        $fd = array();
        $fd['name'] = $name;

        list($simple_type) = explode('(', $type);
        if ($ttype = get($type, $types)) {
            $fd['type'] = $ttype;
        } else if ($ttype = get($simple_type, $types)) {
            $fd['type'] = $ttype;
        } else {
            s::error('Unknown type %s for %s.%s', $type, $table, $name);
        }

        $repr['fields'][$name] = $fd;
    }

    $repr['uuid'] = strpos($repr['key'], '_uuid') !== false;

    return $repr;
}

function generate_pgsql_repr($table)
{
    $repr = array(
        'table' => $table,
        'use-ordering' => false,
        'key' => null,
        'fields' => array (),
    );

    $res = db::query('select * from %S limit 0', $table);
    $n_fields = pg_num_fields($res);

    $types = array(
        'int4' => 'int',
        'varchar' => 'string',
        'text' => 'string',
        'bool' => 'bool',
        'timestamptz' => 'datetime',
        'timestamp' => 'datetime',
        'date' => 'date',
        'numeric' => 'money',
        'bpchar' => 'money',
    );

    for($ii = 0; $ii < $n_fields; $ii++) {
        $type = pg_field_type($res, $ii);
        $name = pg_field_name($res, $ii);

        if (skip_field_name($name)) continue;

        if ($name == 'ordering') {
            $repr['use-ordering'] = true;
            continue;
        }

        $fd = array();
        $fd['name'] = $name;

        if ( ! isset($types[$type])) {
            s::error('Unknown type %s for %s.%s', $type, $table, $name);
        }
        $fd['type'] = $types[$type];

        $repr['fields'][$name] = $fd;
    }

    $repr['key'] = db::one("
SELECT pg_attribute.attname
FROM pg_index, pg_class, pg_attribute
WHERE
  pg_class.oid = %s::regclass AND
  indrelid = pg_class.oid AND
  pg_attribute.attrelid = pg_class.oid AND
  pg_attribute.attnum = any(pg_index.indkey)
  AND indisprimary"
    , $table);

    if ( ! isset($repr['fields'][ ($repr['key']) ])) {
        s::error('Cannot determine the correct primary key for %s, got %s', $table, $repr['key']);
    }

    $repr['uuid'] = strpos($repr['key'], '_uuid') !== false;

    return $repr;
}

function get_adjustment($r, $field)
{
    $adj_max = 0;
    foreacH($r['fields'] as $f)  {
        $l = strlen($f['name']);
        $adj_max = max($l, $adj_max);
    }
    return str_repeat(' ', (1 + $adj_max - strlen($field['name'])));
}

function get_recdef($r)
{
    $o = array();
    foreach($r['fields'] as $f) {
        $name = $f['name'];
        $spacer = get_adjustment($r, $f);
        $type = $f['type'];
        if ($r['uuid'] and $f['name'] == $r['key']) {
            $o[]= "'{$name}' => \$this->{$name} ? \$this->{$name} : uuid(),";
            continue;
        }
        if ($type == 'date') {
            $o[] = "'{$name}'{$spacer}=> \$this->{$name} ? \$this->{$name} : null,";
        } else if ($type == 'datetime') {
            $o[] = "'{$name}'{$spacer}=> \$this->{$name} ? date('Y-m-d H:i:s', \$this->{$name}) : null,";
        } else {
            $o[] = "'{$name}'{$spacer}=> \$this->{$name},";
        }
    }

    return $o;
}


function get_orderingdef($r)
{
    if ($r['use-ordering']) {
        $table = $r['table'];
        $id = $r['key'];
        return "
        if ( ! \$this->$id) {
            db::query('update $table set ordering=$id where ordering is null');
        }";
    }
}

function get_vardef($r)
{
    $defaults = array(
        'int'=> 'null',
        'string'=> 'null',
        'bool'=> 'false',
        'datetime'=> 'null',
        'date'=> 'null',
        'money'=> 'null',
    );

    $o = array();
    foreach($r['fields'] as $f) {
        $name = $f['name'];
        $adj = get_adjustment($r, $f);
        $default = $defaults[($f['type'])];
        $o[] = "var \$$name$adj= $default;";
    }
    return $o;
}


function get_fetchdef($r)
{
    $o = array();
    foreach($r['fields'] as $f) {
        $name = $f['name'];
        $adj = get_adjustment($r, $f);
        if ($f['type'] == 'date') {
            $o[]= "\$this->{$name}{$adj}= \$r['{$name}'];";
        } else if ($f['type'] == 'datetime') {
            $o[]= "\$this->{$name}{$adj}= \$r['{$name}'];";
        } else {
            $o[] = "\$this->{$name}{$adj}= \$r['{$name}'];";
        }
    }
    return $o;
}

function get_boolfixdef($r)
{
    $bools = array();
    foreach($r['fields'] as $f) {
        if ($f['type'] == 'bool') {
            $bools[] = $f['name'];
        }
    }

    if ($bools) {
        return array(sprintf("db::booleanize(\$r, '%s');", implode(', ', $bools)));
    }
}


function get_dbdef($r)
{
    $o = array();
    foreach($r['fields'] as $idx => $f) {
        if ($f['type'] == 'date') {
            $o[] = $f['name'];
        } else if ($f['type'] == 'datetime') {

            if (s::get('r:db-engine') == 'mysql') {
                $o[] = sprintf('unix_timestamp(%s) as %s',
                    $f['name'], $f['name']);
            } else {
                $o[] = sprintf('%s::abstime::int as %s',
                    $f['name'], $f['name']);
            }
        } else {
            $o[] = $f['name'];
        }
    }
    return implode(', ', $o);
}

function get_postdef($r)
{
    $o = array();
    foreach($r['fields'] as $f) {

        $name = $f['name'];

        if ($name == $r['key']) {
            continue;
        }

        $adj = get_adjustment($r, $f);

        switch($f['type']) {
        case 'int':
            $o[] = "case '$name': $adj \$this->$name = get_int('$name'); break;";
            break;
        case 'string':
            $o[] = "case '$name': $adj \$this->$name = trim('' . get('$name')); break;";
            break;
        case 'money':
            $o[] = "case '$name': $adj \$this->$name = trim(str_replace(' ', '', str_replace(',', '.', '' . get('$name')))); break;";
            break;
        case 'bool':
            $o[] = "case '$name': $adj \$this->$name = get_bool('$name'); break;";
            break;
        case 'date':
            $o[] = "case '$name': $adj \$this->$name = ymd_from_timestamp(timestamp_from_ymd('$name')); break;";
            break;
        case 'datetime':
            $o[] = "case '$name': $adj \$this->$name = BaseModel::get_timestamp('$name'); break;";
            break;
        default:
            dump($f);
            s::error('I cannot understand this type.');
        }
    }
    return $o;
}

function indent($lines, $spaces = 4)
{
    if ( ! $lines) return;
    $indentation = str_repeat(' ', $spaces);
    $out = array();
    foreach($lines as $line) {
        $out[] = $indentation . trim($line);
    }
    return implode("\n", $out);
}
function output_model($r)
{
    $table = $r['table'];
    $id = $r['key'];
    $classname = camel_of_underscore($table);
    $singular = singular_of_plural($table);
    $singular_lower = strtolower_utf($singular);
    $recdef = indent(get_recdef($r), 12);
    $orderingdef = get_orderingdef($r);
    $vardef = indent(get_vardef($r), 4);
    $fetchdef = indent(get_fetchdef($r), 8);
    $boolfixdef = indent(get_boolfixdef($r), 8);
    $dbdef = get_dbdef($r);
    $postdef = indent(get_postdef($r), 12);

    $known_fields = array();
    foreach($r['fields'] as $f) {
        $known_fields[] = "'" . $f['name'] . "'";
    }
    $known_fields = implode(', ', $known_fields);


    if ($r['uuid']) {
        $idspec = '%s';
        $idget = 'get_uuid';
    } else {
        $idspec = '%d';
        $idget = 'get_int';
    }


    $model = "
class {$classname}Model extends BaseModel
{
$vardef
    var \$_underlying_table = '$table';
    var \$_primary_key = '$id';
    var \$_known_fields = array(
        $known_fields
    );

    function __construct(\${$id} = null)
    {
        parent::__construct(\${$id});
    }

    function from_db(\${$id})
    {
        \$r = db::fget('select $dbdef
          from $table where $id=$idspec', \${$id});
$boolfixdef
$fetchdef
    }

    function from_post(\$fields)
    {
        if (is_string(\$fields)) {
          \$fields = array_from_comma_string(\$fields);
        }
        foreach(\$fields as \$field) {
            switch(\$field) {
$postdef
            }
        }
    }

    function validate()
    {
        \$func = 'validate_{$singular}';
        \$ret = null;
        if (function_exists(\$func)) {
            try {
                \$ret = \$func(\$this);
            } catch (ValidationError \$e) {
                \$ret = failure(\$e->message, \$e->field);
            }
        }
        return \$ret;
    }

    function to_db(\$deadly_validation_failure = true)
    {
        if (failed(\$notice = \$this->validate())) {
            if (\$deadly_validation_failure) {
                s::error(text_from_notice(\$notice));
            }
            return \$notice;
        }
        \$rec = array(
$recdef
        );
        if (s::get('models:use-history')) {
            history::db_replace('{$table}', \$rec, '$id');
        } else {
            db::replace('{$table}', \$rec, '$id');
        }
        {$orderingdef}
        \$this->{$id} = \$rec['{$id}'];
        \$this->broadcast_update();
    }

    function broadcast_update()
    {
        s::emit('models:update', array(
            'name' => '$singular_lower',
            'model' =>  \$this
        ));
        s::emit('models:update.$singular_lower', \$this);
    }
}
";
    echo "# This file was generated automatically\n";
    echo "# Commands used to build this:\n";
    echo '#   cd ' . getcwd() . "\n";
    echo "#   php ", implode(' ', $_SERVER['argv']), "\n";
    echo $model;

}




