<?php require_once('mysql_constants.php'); ?>
<?php require_once('mysql_constants.php'); ?>
<?php require_once('mysql_constants.php'); ?>
<?php require_once('mysql_constants.php'); ?>
<?php
/**
 * backwards compatibility old php functions - from info@itxplain.nl
 */
define('MYSQL_ASSOC', 0);
define('MYSQL_NUM', 1);

if (!function_exists("mysql_connect"))
{

function mysql_connect($server, $username, $password, $new_link = false, $client_flags = null) 
{
    $key = $server.$username.$password;
    if (isset($GLOBALS['mysql_cons']) == false) {
        $GLOBALS['mysql_cons'] = false;
    }
    if ($new_link == false && isset($GLOBALS['mysql_cons'][$key])) {
        return $GLOBALS['mysql_cons'][$key];
    }

    $con = new mysqli($server, $username, $password);

    if (isset($GLOBALS['mysql_cons'][$key]) == false) {
        $GLOBALS['mysql_cons'][$key] = $con;
    }

    if (isset($GLOBALS['mysql_cons']['default']) == false) {
       $GLOBALS['mysql_cons']['default'] = $con;
    }

    $r = $con->connect($server, $username, $password);

    if ($r === false)
        return false;

    return $con;
}

function mysql_select_db($dbname, $con=null) 
{
    if ($con == null) {
        $con = $GLOBALS['mysql_cons']['default'];
    }

    $r = $con->select_db($dbname);
    return $r;
}

function mysql_query($query, $con=null) 
{
    ob_start();
    if ($con == null) {
        $con = $GLOBALS['mysql_cons']['default'];
    }

    $result = $con->query($query);
    ob_clean();
    return $result;
}

function mysql_real_escape_string($val, $con=null) 
{
    if ($con == null) {
        $con = $GLOBALS['mysql_cons']['default'];
    }

    return $con->escape_string($val);
}


function mysql_insert_id($con=null) 
{
    if ($con == null) {
        $con = $GLOBALS['mysql_cons']['default'];
    }

    return $con->insert_id;
}

function mysql_error($con=null) 
{
    if ($con == null) {
        $con = $GLOBALS['mysql_cons']['default'];
    }

    return $con->error;
}

function mysql_fetch_assoc($result) 
{
    $row = $result->fetch_assoc();

    return $row;
}

function mysql_fetch_array($result, $how = MYSQL_BOTH) 
{
    $row = $result->fetch_assoc();

    return $row;
}

function mysql_result($result, $rown, $col) 
{
    $row = $result->fetch_array();
    if ($row === null) {
        return false;
    }
    
    // Convert to numeric array if needed
    $row = array_values($row);
    
    if (isset($row[$col])) {
        return $row[$col];
    }
    
    return false;
}

function mysql_num_rows($result) 
{
	return $result->num_rows;
}

function mysql_close($con=null) 
{
	return null;
}

}

if (!function_exists("split"))
{
function split($pattern, $string, $limit=-1) 
{
	return preg_split('/'.$pattern.'/', $string, $limit);
}
}

?>
