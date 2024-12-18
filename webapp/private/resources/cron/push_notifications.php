<?php
# Create webroot variable to directory above public and private folders
$webroot = dirname(dirname(dirname(dirname(__FILE__))));

include($webroot . '/private/key.php');

$password_options = ['cost' => 12];

# Require once pushNotificationFunctions.php
require_once($webroot . "/private/resources/api-functionality/pushNotificationFunctions.php");
require_once($webroot . "/private/resources/api-functionality/functions.php");

// Create lock file to prevent cron from spawning more than one process
$lock_file = $webroot . '/private/resources/cron/cron.lock';
$lock_handle = fopen($lock_file, 'w');
if (!flock($lock_handle, LOCK_EX | LOCK_NB)) {
    // Lock file is already locked, so another process is running
    logError($webroot . "/private/errorLog.txt", "push_notifications.php", "Lock file already locked, so another process is running. Terminating...");
    exit();
}

try {
    // Create connection to database
    $pdo = new PDO('mysql:host=' . SERVERNAME . ';dbname=' . DBNAME, USERNAME, PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    # Get all idAppUser from AppUser and cycle through them
    $stmt = $pdo->prepare("SELECT idAppUser FROM AppUser");
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json; charset=utf-8');

    $arr = [];

    foreach ($result as $row) {

        $idAppUser = $row['idAppUser'];
        # Check for inactivity (no Upload in the last 7 days) and send push notification
        $arr[$idAppUser]["_Inactivity"] = checkAndSendInactivityPush($idAppUser, $pdo, ENCRYPTION_KEY, CLEVERPUSH_API_KEY, CLEVERPUSH_CHANNEL_ID, $webroot . "/private/errorLog.txt");
        # Check for IBAN
        $arr[$idAppUser]["_IBAN"] = checkandSendMissingIBANPush($idAppUser, $pdo, ENCRYPTION_KEY, CLEVERPUSH_API_KEY, CLEVERPUSH_CHANNEL_ID, $webroot . "/private/errorLog.txt");
        # Check for new surveys
        $arr[$idAppUser]["_Survey"] = checkAndSendNewSurveyPush($idAppUser, $pdo, ENCRYPTION_KEY, CLEVERPUSH_API_KEY, CLEVERPUSH_CHANNEL_ID, $webroot . "/private/errorLog.txt");
        # Check if user has not logged in for 7 days and send push notification (if not already sent) (ongoing up to 3 times)
        $arr[$idAppUser]["_LoginReminder"] = checkAndSendUsageReminderViaEMail($idAppUser, $pdo, ENCRYPTION_KEY, $webroot, MAIL_FROM_ADDRESS, MAIL_FROM_NAME, $webroot . "/private/errorLog.txt");
        # Check if week 10 has been reached and sent mail to user with debriefing and reminder to put in IBAN
        $arr[$idAppUser]["_DebriefingAndIBANReminder"] = checkAndSendDebriefingAndIBANReminderViaEMail($idAppUser, $pdo, ENCRYPTION_KEY, $webroot, MAIL_FROM_ADDRESS, MAIL_FROM_NAME, $webroot . "/private/errorLog.txt");

    }

    echo json_encode($arr);

} catch (Exception $e) {
    logError($webroot . "/private/errorLog.txt", "pushtest.php main", $e->getMessage());
}

// Release lock on lock file
flock($lock_handle, LOCK_UN);
fclose($lock_handle);

?>