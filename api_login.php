<?php
// Start output buffering to catch any output

require 'authorisation.php';

// Connecting, selecting database
$link = db_connect();

$login = addslashes($_REQUEST['username']);
$passwd = addslashes($_REQUEST['password']);
$ip = $_SERVER['REMOTE_ADDR'];

$query = "select usePk from tblUser where useLogin='$login' and usePassword='$passwd'";
$result = mysql_query($query) or die('Query failed: ' . mysql_error());

if (mysql_num_rows($result) > 0)
{
    $usePk = mysql_result($result,0,0);
}
else
{
    $usePk = 0;
}
if ($usePk > 0)
{
    $magic = rand() % 100000000000;
    $query= "insert into tblUserSession (usePk, useSession, useIP) values ($usePk, '$magic', '$ip')";
    $result = mysql_query($query) or die('Query failed: ' . mysql_error());
    
    if (setcookie("XCauth", $magic))
    {
        echo json_encode([
            "success" => true,
            "message" => "Login successful"
        ]);
        mysql_close($link);
        exit;
    }

}


// Closing connection
mysql_close($link);
echo json_encode([
    "success" => false,
    "message" => "Login failed"
]);

?>
