<?php
include('./config.php');

connect_db();
function connect_db()
{
    global $db;
    @$db = new mysqli(DATABASE_HOSTNAME, DATABASE_USERNAME, DATABASE_PASSWORD);
    $db->debug = false;
    if ($connection_error = mysqli_connect_error()) {
        $errors[] = 'There was an error trying to connect to database at ' . DATABASE_HOSTNAME . ':<br><b>' . $connection_error . '</b>';
        die();
    }

   /* mysqli_select_db($db, DATABASE_DATABASE) or error_out('<b>Error:</b><i> ' . mysqli_error($db) . '</i><br>
		This usually means that MySQL does not have a database called <b>' . DATABASE_DATABASE . '</b>.<br><br>
		Create that database and import some structure into it from <b>doc/database.sql</b> file<br>');*/
    mysqli_query($db, "SET NAMES utf8");
    mysqli_query($db, "SET CHARACTER utf8");

}

function q($sql, & $query_pointer = NULL, $debug = FALSE)
{
    global $db;
    if ($db->debug) {
        print "<pre>$sql</pre>";
    }
    $query_pointer = mysqli_query($db, $sql) or db_error_out();
    switch (substr($sql, 0, 6)) {
        case 'SELECT':
            exit("q($sql): Please don't use q() for SELECTs, use get_one() or get_first() or get_all() instead.");
        case 'INSE':
            debug_print_backtrace();
            exit("q($sql): Please don't use q() for INSERTs, use insert() instead.");
        case 'UPDA':
            exit("q($sql): Please don't use q() for UPDATEs, use update() instead.");
        default:
            return mysqli_affected_rows($db);
    }
}

function get_one($sql, $debug = FALSE)
{
    global $db;


    if ($debug) { // kui debug on TRUE
        print "<pre>$sql</pre>";
    }
    switch (substr($sql, 0, 6)) {
        case 'SELECT':
            $start_time = microtime(1);
            $q = mysqli_query($db, $sql) or db_error_out();
            if ($db->debug) var_dump((round((microtime(1) - $start_time) * 1000, 0)) . "ms $sql");
            $result = mysqli_fetch_array($q);
            return empty($result) ? NULL : $result[0];
        default:
            exit('get_one("' . $sql . '") failed because get_one expects SELECT statement.');
    }
}

function get_all($sql)
{
    global $db;
    $start_time = microtime(1);
    $q = mysqli_query($db, $sql) or db_error_out();
    if ($db->debug) var_dump((round((microtime(1) - $start_time) * 1000, 0)) . "ms $sql");
    while (($result[] = mysqli_fetch_assoc($q)) || array_pop($result)) {
        ;
    }
    return $result;
}

function get_first($sql)
{
    global $db;
    $start_time = microtime(1);
    $q = mysqli_query($db, $sql) or db_error_out();
    if ($db->debug) var_dump((round((microtime(1) - $start_time) * 1000, 0)) . "ms $sql");
    $first_row = mysqli_fetch_assoc($q);
    return empty($first_row) ? array() : $first_row;
}

function db_error_out()
{
    global $db;
    $db_error = mysqli_error($db);

    if (strpos($db_error, 'You have an error in SQL syntax') !== FALSE) {
        $db_error = '<b>Syntax error in</b><pre> ' . substr($db_error, 135) . '</pre>';

    }
    $backtrace = debug_backtrace();
    $file = $backtrace[1]['file'];
    $line = $backtrace[0]['line'];
    $function = isset($backtrace[2]['function']) ? $backtrace[2]['function'] : NULL;
    $args = isset($backtrace[1]['args']) ? $backtrace[1]['args'] : NULL;
    if (!empty($args)) {
        foreach ($args as $arg) {
            if (is_array($arg)) {
                $args2[] = implode(',', $arg);
            } else {
                $args2[] = $arg;
            }
        }
    }

    $args = empty($args2) ? '' : '"' . implode('", "', $args2) . '"';
    $location = "In file <b>$file</b>, line <b>$line</b>";
    if (!empty($function)) {
        $location .= ", function <b>$function</b>( $args )";
    }

    // Display <pre>SQL QUERY</pre> only if it is set
    $sql = isset($sql) ? '<pre style="text-align: left;">' . $sql . '</pre><br/>' : '';

    // Generate stack trace
    $e = new Exception();
    $trace = print_r(preg_replace('/#(\d+) \//', '#$1 ', str_replace(dirname(dirname(__FILE__)), '', $e->getTraceAsString())), 1);
    $trace = nl2br(preg_replace('/(#1.*)\n/', "<b>$1</b>\n", $trace));

    $output = '<h2><strong style="color: red">' . $db_error . '</strong></h2><br/>' . $sql . '<p>' . $location . "</p><br><h2>Stack trace:</h2>$trace";


    if (isset($_GET['ajax'])) {
        ob_end_clean();
        echo strip_tags($output);
    } else {
        $errors[] = $output;
    }
    die();

}

/**
 * @param $table string The name of the table to be inserted into.
 * @param $data array Array of data. For example: array('field1' => 'mystring', 'field2' => 3);
 * @return bool|int Returns the ID of the inserted row or FALSE when fails.
 */
function insert($table, $data)
{
    global $db;
    if ($table and is_array($data) and !empty($data)) {
        $values = implode(',', escape($data));
        $sql = "INSERT INTO `{$table}` SET {$values} ON DUPLICATE KEY UPDATE {$values}";
        $q = mysqli_query($db, $sql) or db_error_out();
        $id = mysqli_insert_id($db);
        return $id;
    } else {
        return FALSE;
    }
}

function update($table, array $data, $where)
{
    global $db;
    if ($table and is_array($data) and !empty($data)) {
        $values = implode(',', escape($data));

        if (isset($where)) {
            $sql = "UPDATE `{$table}` SET {$values} WHERE {$where}";
        } else {
            $sql = "UPDATE `{$table}` SET {$values}";
        }
        $id = mysqli_query($db, $sql) or db_error_out();
        return ($id > 0) ? $id : FALSE;
    } else {
        return FALSE;
    }
}

function escape(array $data)
{
    global $db;
    $values = array();
    if (!empty($data)) {
        foreach ($data as $field => $value) {
            if ($value === NULL) {
                $values[] = "`$field`=NULL";
            } elseif (is_array($value) && isset($value['no_escape'])) {
                $values[] = "`$field`=" . mysqli_real_escape_string($db, $value['no_escape']);
            } else {
                $values[] = "`$field`='" . mysqli_real_escape_string($db, $value) . "'";
            }
        }
    }
    return $values;
}