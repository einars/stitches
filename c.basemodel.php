<?php
class BaseModel {
    function __construct($id = null)
    {
        if ($id) {
            $this->from_db($id);
        }
    }

    function from_db($id)
    {
        R::warn('Not yet implemented');
    }
    function validate()
    {
        R::warn('Not yet implemented');
    }
    function to_db()
    {
        R::warn('Not yet implemented');
    }
    function save()
    {
        R::error('Use to_db or fetch_and_save instead.');
    }
    function from_post($fields)
    {
        R::warn('Not yet implemented');
    }
    // returns:
    // null - no need to save
    // failure - need to save, validation failed
    // true - saved
    function fetch_and_save($fields)
    {

        if (get('save')) {
            $this->from_post($fields);
            if (failed($res = $this->validate())) {
                return $res;
            }
            $this->to_db();
            return true;
        }
    }

    static function get_timestamp($var)
    {

        if ($single = get($var)) {
            return timestamp_from_dmy($single);
        }

        $y = get_int($var . '_y');
        $m = get_int($var . '_m');
        $d = get_int($var . '_d');

        $h = any(get_int($var . '_h'), 0);
        $i = any(get_int($var . '_i'), 0);

        if ($y and $m and $d) {
            return mktime($h, $i, 0, $m, $d, $y);
        } else {
            return null;
        }
    }

    function req_nonempty($field, $message = null)
    {
        if ( ! $this->$field) {
            if ( ! $message) {
                $message = hs('Field %s needs to be filled.', $field);
            }
            throw new ValidationError($message, $field);
        }
    }

}




class ValidationError extends Exception {
    var $field;
    var $message;
    function __construct($message = null, $field = null)
    {
        parent::__construct($message, 0, null);
        $this->field = $field;
        $this->message = $message;
    }
}
