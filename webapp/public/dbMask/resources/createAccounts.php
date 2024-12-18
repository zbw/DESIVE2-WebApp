<?php
# Path API/Endpoint/public/dbMask/resources/createAccounts.php

$webroot = dirname(dirname(dirname(dirname(__FILE__))));

include($webroot . '/private/key.php');

$waitlist_active_value = WAITLIST_ACTIVE;
$waitlist_active = isset($waitlist_active_value) ? $waitlist_active_value : false;
$password_options = ['cost' => 12];
$pathToErrorLog = $webroot . "/private/errorLog.txt";
$dbMaskLog = $webroot . '/private/dbMaskLog.txt';

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/private/resources/api-functionality/functions.php");

try {
    $conn = new PDO("mysql:host=" . SERVERNAME . ";dbname=" . DBNAME, USERNAME, PASSWORD);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    logError($dbMaskLog, "createAccounts.php", $_SERVER['REMOTE_USER'] . " requested incentive-create-paymentlist.php but could not connect to database. Error:" . $e->getMessage());
    logError($pathToErrorLog, "createAccounts.php", $_SERVER['REMOTE_USER'] . " requested incentive-create-paymentlist.php but could not connect to database. Error:" . $e->getMessage());
    echo "Could not connect to database. Please check the error log.";
}

# Log action
logError($dbMaskLog, "createAccounts.php", $_SERVER['REMOTE_USER'] . " requested createAccounts.php.");

# Call createAppAccountForWaitlist function
$result = createAppAccountForWaitlist($conn, ENCRYPTION_KEY, $password_options, MAIL_FROM_ADDRESS, MAIL_FROM_NAME, $webroot, $pathToErrorLog);

# Check if status is success
if ($result['status'] == "success") {
    echo "The action was successful.";
    # Print message and data from result
    print_r($result['message']);
    print_r($result['data']);
} else {
    echo "The action was not successful.";
    # Print message and data from result
    print_r($result['message']);
    print_r($result['data']);
}
echo "<br><br>";

echo "<button onclick=\"location.href='../index.php'\" type='button'>
Zurück zur Haupt-Verwaltungsseite
</button>";

?>