<?php

# querydebug 
#
# gather query information and show query debug information on page bottom
#

class querydebug {

    static $log_limit = 100;
    static $log = array();

    static function load()
    {
        s::on('query', 'querydebug::log');
        s::on('page:body-end', 'querydebug::show');
    }

    static function log($ev)
    {
        if (sizeof(querydebug::$log) > querydebug::$log_limit) {
            return;
        }
        querydebug::$log[] = array(
            'time' => $ev['time'] * 1000,
            'query' => $ev['query'],
            'recs' => is_bool($ev['result']) ? -1 : db::num_rows($ev['result']),
        );
    }

    static function show()
    {
        if ( ! querydebug::$log) return;

        echo <<<STYLE
<style>
.querydebug { clear: both; background: white; font-size: 12px; width: 100%; margin: 2em 0;}
.querydebug th { font-weight: normal; text-align: right; width: 5em; }
</style>
STYLE;

        echo '<table class="querydebug table table-condensed">';
        $sql_cumul = 0;
        $queries = array();
        foreach(querydebug::$log as $idx => $r) {
            $sql_cumul += $r['time'];
            $query = $r['query'];
            $md5 = md5($query);
            printf('<tr class="%s %s"><th>%.1f</th><th>%d</th><td>%s%s</td></tr>',
                // isset($queries[$md5]) ? 'querydebug-duplicate bg-warning' : '',
                isset($queries[$md5]) ? 'querydebug-duplicate' : '',
                $r['time'] > 30 ? 'querydebug-long bg-danger' : '',
                $r['time'],
                $r['recs'],
                isset($queries[$md5]) ? '<b class="text-danger">dup!</b> ' : '',
                r_htmlspecialchars($r['query']));
            $queries[$md5] = true;
        }
        h('<tr><th>t = <b>%.1f</b></th><th></th><td>Queries: %d</td></tr>', $sql_cumul, sizeof(querydebug::$log));
        echo '</table>';
    }

}

