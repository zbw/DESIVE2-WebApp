<?php
/* 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); 
*/
# Create webroot variable to directory above public and private folders
$webroot = dirname(dirname(__FILE__));

include($webroot . '/private/key.php');

$waitlist_active_value = WAITLIST_ACTIVE;
$waitlist_active = isset($waitlist_active_value) ? $waitlist_active_value : false;
$password_options = ['cost' => 12];
$pathToErrorLog = $webroot . "/private/errorLog.txt";

# Require once ../private/resources/api-functionality/functions.php
require_once($webroot . "/private/resources/api-functionality/functions.php");
# Require once ../private/resources/api-functionality/pushNotificationFunctions.php
require_once($webroot . "/private/resources/api-functionality/pushNotificationFunctions.php");

try {

    // Create connection to database
    $pdo = new PDO('mysql:host=' . SERVERNAME . ';dbname=' . DBNAME, USERNAME, PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    # Try to get action parameter from URL and if it is not set, echo "No action specified" and exit
    if (isset($_GET['action'])) {
        $action = $_GET['action'];
    } else {
        echo "No action specified";
        exit;
    }

    # Require once ../private/resources/api-functionality/UserManagement.php
    require_once($webroot . "/private/resources/api-functionality/UserManagement.php");
    # Require once ../private/resources/api-functionality/AppUserFunctions.php
    require_once($webroot . "/private/resources/api-functionality/AppUserFunctions.php");
    # Require once ../private/resources/api-functionality/AppDataUpload.php
    require_once($webroot . "/private/resources/api-functionality/AppDataUpload.php");

} catch (Exception $e) {
    // echo $e->getMessage();
    echo "An error occurred!";
    # Append error to errorLog.txt located in ../private/errorLog.txt to get the path of the current file
    $myfile = fopen($webroot . "/private/errorLog.txt", "a") or die("Unable to open file!");
    $txt = date("Y-m-d H:i:s") . " - " . "DBConnect.php main" . " - " . $e->getMessage() . PHP_EOL;
    fwrite($myfile, $txt);
    fclose($myfile);
}

?>