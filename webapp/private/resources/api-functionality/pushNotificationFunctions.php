<?php
/**
 * Push Notifications
 *
 * @package API\Endpoint\private\resources\api-functionality
 */

require_once($webroot . "/private/resources/api-functionality/functions.php");

/**
 * Checks if user has push notifications enabled and if user has logged in for the first time.
 * If yes, sends push notification to user if user has not uploaded any data for 7 days.
 * Also resend after 2 days if user has not uploaded any data.
 * Returns array with status and message.
 * @param int $idAppuser
 * @param PDO $pdo
 * @param string $encryptionKey Might be used in the future for parallel mail and push notifications
 * @param string $cleverpush_apikey
 * @param string $cleverpush_channelid
 * @param string $pathToErrorLog
 * @return array<string>
 */
function checkAndSendInactivityPush(int $idAppuser, PDO $pdo, string $encryptionKey, string $cleverpush_apikey, string $cleverpush_channelid, string $pathToErrorLog)
{
    # Check if user logged in for the first time
    # If yes, continue
    # If no, return
    $resArray = getFirstLogin($idAppuser, $pdo, $pathToErrorLog);
    # Check if successfull
    if ($resArray["status"] == "error") {
        # log error
        logError($pathToErrorLog, "sendInactivityPushNotification() in PushNotificationsFunctions.php", "Could not get first login for idAppuser: " . $idAppuser);

        # build json response
        $response = array(
            "status" => "error",
            "message" => "Could not get first login for idAppuser: " . $idAppuser
        );
        return $response;
    } else if ($resArray["status"] == "success") {
        $firstLogin = $resArray["first_login"];
        if (is_null($firstLogin)) {
            # build json response
            $response = array(
                "status" => "success",
                "message" => "User with idAppuser=" . $idAppuser . " has not logged in for the first time. No further checks have been carried out."
            );
            return $response;
        } else {
            $firstLogin = DateTime::createFromFormat('Y-m-d H:i:s', $firstLogin);
            # User has already logged in for the first time. Continue.
        }
    } else {
        # log error
        logError($pathToErrorLog, "checkAndSendInactivityPush() in PushNotificationsFunctions.php", "Invalid status from getFirstLogin() for idAppuser: " . $idAppuser);
        # build json response
        $response = array(
            "status" => "error",
            "message" => "Invalid status from getFirstLogin() for idAppuser: " . $idAppuser
        );
        return $response;
    }

    # Check if user has push notifications enabled
    # If yes, continue
    # If no, return
    $resArray = getPushNotificationsStatusForIdAppuser($idAppuser, $pdo, $pathToErrorLog);
    # Check if successfull
    if ($resArray["status"] == "error") {
        # log error
        logError($pathToErrorLog, "sendInactivityPushNotification() in PushNotificationsFunctions.php", "Could not get push notifications status for idAppuser: " . $idAppuser);

        # build json response
        $response = array(
            "status" => "error",
            "message" => "Could not get push notifications status for idAppuser: " . $idAppuser
        );
        return $response;
    } else if ($resArray["status"] == "success") {
        $pushNotificationStatus = $resArray["receivesPush"];
        if ($pushNotificationStatus == 0) {
            # build json response
            $response = array(
                "status" => "success",
                "message" => "User with idAppuser=" . $idAppuser . " has push notifications disabled. No further checks have been carried out."
            );
            return $response;
        } else if ($pushNotificationStatus != 1) {
            # log error
            logError($pathToErrorLog, "checkAndSendInactivityPush() in PushNotificationsFunctions.php", "Invalid push notification status for idAppuser: " . $idAppuser);
            # build json response
            $response = array(
                "status" => "error",
                "message" => "Invalid push notifications status for idAppuser: " . $idAppuser
            );
            return $response;
        } else if ($pushNotificationStatus == 1 && $resArray['registration_id'] == "") {
            # Push notification have been enabled by the user but no registration_id is available.
            # build json response
            $response = array(
                "status" => "success",
                "message" => "User with idAppuser=" . $idAppuser . " has push notifications enabled but no registration_id is available. No further checks have been carried out."
            );
            return $response;

        } else if ($pushNotificationStatus == 1) {
            # Push notification have been enabled by the user. Continue.
        }
    } else {
        # log error
        logError($pathToErrorLog, "checkAndSendInactivityPush() in PushNotificationsFunctions.php", "Invalid status from getPushNotificationsStatusForIdAppuser() for idAppuser: " . $idAppuser);
        # build json response
        $response = array(
            "status" => "error",
            "message" => "Invalid status from getPushNotificationsStatusForIdAppuser() for idAppuser: " . $idAppuser
        );
        return $response;
    }

    # Check if user has entry in table Upload in the last 7 days
    # If yes, return
    # If no, continue
    $resArray = getLastUploadForIdAppuser($idAppuser, $pdo, $pathToErrorLog);

    # Check if successfull
    if ($resArray["status"] == "error") {
        # log error
        logError($pathToErrorLog, "sendInactivityPushNotification() in PushNotificationsFunctions.php", "Could not get last upload for idAppuser: " . $idAppuser);

        # build json response
        $response = array(
            "status" => "error",
            "message" => "Could not get last upload for idAppuser: " . $idAppuser
        );
        return $response;
    } else if ($resArray["status"] == "success") {
        $lastUpload = $resArray["timestamp"];

        if (empty($lastUpload)) {
            # User has not uploaded data.

            # Check if user has logged in for the first time more than 7 days ago
            # If yes, continue
            # If no, return
            $now = new DateTime();
            $interval = $now->diff($firstLogin);

            if ($interval->days < 7) {
                # build json response
                $response = array(
                    "status" => "success",
                    "message" => "User with idAppuser=" . $idAppuser . " has not been using the app for more than 7 days. No further checks have been carried out."
                );
                return $response;
            } else {
                # User has not uploaded data in the last 7 days. Continue.
            }

        } else {
            # User has already uploaded data. Continue.

            #$lastUpload = new DateTime(date("Y-m-d H:i:s", strtotime($lastUpload)));
            $lastUpload = DateTime::createFromFormat('Y-m-d H:i:s', $lastUpload);

            # Check if last upload is older than 7 days
            # If yes, continue
            # If no, return
            $now = new DateTime();
            $interval = $now->diff($lastUpload);
            if ($interval->days < 7) {
                # build json response
                $response = array(
                    "status" => "success",
                    "message" => "User with idAppuser=" . $idAppuser . " has uploaded data in the last 7 days. No further checks have been carried out."
                );
                return $response;
            } else {
                # User has not uploaded data in the last 7 days. Continue.
            }
        }
    } else {
        # log error
        logError($pathToErrorLog, "checkAndSendInactivityPush() in PushNotificationsFunctions.php", "Invalid status from getLastUploadForIdAppuser() for idAppuser: " . $idAppuser);
        # build json response
        $response = array(
            "status" => "error",
            "message" => "Invalid status from getLastUploadForIdAppuser() for idAppuser: " . $idAppuser
        );
        return $response;
    }

    # Check if there is an entry in table PushNotification for the PushNotificationType 6 in the last 2 days
    # If yes, return
    # If no, continue
    $resArray = getLastPushNotificationForIdAppuserAndPushNotificationType($idAppuser, 6, $pdo, $pathToErrorLog);
    # Check if successfull
    if ($resArray["status"] == "error") {
        # log error
        logError($pathToErrorLog, "sendInactivityPushNotification() in PushNotificationsFunctions.php", "Could not get last push notification for idAppuser: " . $idAppuser);

        # build json response
        $response = array(
            "status" => "error",
            "message" => "Could not get last push notification for idAppuser: " . $idAppuser
        );
        return $response;
    } else if ($resArray["status"] == "success") {
        $timestampPushNotification = $resArray["timestamp"];
        if (empty($timestampPushNotification)) {
            # No earlier push notification of type 6 has been sent to user. Continue.
        } else {
            # User has already received a push notification of type 6. Continue.
            $timestampPushNotification = DateTime::createFromFormat('Y-m-d H:i:s', $timestampPushNotification);

            # Check if last push notification is older than 2 days
            # If yes, continue
            # If no, return
            $now = new DateTime();
            $interval = $now->diff($timestampPushNotification);
            if ($interval->days < 2) {
                # build json response
                $response = array(
                    "status" => "success",
                    "message" => "User with idAppuser=" . $idAppuser . " has received a push notification of type 6 in the last 2 days. No further checks have been carried out."
                );
                return $response;
            } else {
                # User has not received a push notification of type 6 in the last 2 days. Continue.
            }
        }
    } else {
        # log error
        logError($pathToErrorLog, "checkAndSendInactivityPush() in PushNotificationsFunctions.php", "Invalid status from getLastPushNotificationForIdAppuserAndPushNotificationType() for idAppuser: " . $idAppuser);
        # build json response
        $response = array(
            "status" => "error",
            "message" => "Invalid status from getLast PushNotificationForIdAppuserAndPushNotificationType() for idAppuser: " . $idAppuser . " and pushNotificationType: 6"
        );
        return $response;
    }

    # Send push notification
    $resArray = sendPushNotification("Haben Sie etwas Neues entdeckt? Teilen Sie es mit uns!", "Erinnerung Studienteilnahme DESIVE²", $cleverpush_channelid, 6, $idAppuser, $pdo, $cleverpush_apikey, $pathToErrorLog);
    # Check if successfull
    if ($resArray["status"] == "error") {
        # log error
        logError($pathToErrorLog, "sendInactivityPushNotification() in PushNotificationsFunctions.php", "Could not send push notification to user with idAppuser: " . $idAppuser);

        # build json response
        $response = array(
            "status" => "error",
            "message" => "Could not send push notification to user with idAppuser: " . $idAppuser
        );
        return $response;
    } else if ($resArray["status"] == "success") {
        # build json response
        $response = array(
            "status" => "success",
            "message" => "Push notification of type 6 has been sent to user with idAppuser: " . $idAppuser
        );
        return $response;
    } else {
        # log error
        logError($pathToErrorLog, "checkAndSendInactivityPush() in PushNotificationsFunctions.php", "Invalid status from sendPushNotification() for idAppuser: " . $idAppuser);
        # build json response
        $response = array(
            "status" => "error",
            "message" => "Invalid status from sendPushNotification() for idAppuser: " . $idAppuser
        );
        return $response;
    }
}

/**
 * Check if user has an IBAN in Subject table and if not, send push notification
 * If push notification has already been sent before (no time limit), do not send another one.
 * @param int $idAppuser
 * @param PDO $pdo
 * @param string $encryptionKey Used to get IBAN from Subject table (cleartext IBAN is not used further)
 * @param string $cleverpush_apikey
 * @param string $cleverpush_channelid
 * @param string $pathToErrorLog
 * @return array<string>
 */
function checkandSendMissingIBANPush(int $idAppuser, PDO $pdo, string $encryptionKey, string $cleverpush_apikey, string $cleverpush_channelid, string $pathToErrorLog)
{
    # Define array with times for push notifications
    $timesInSeconds = array(
        1 => 7 * 7 * 24 * 60 * 60,
        # 7 week
        2 => 8 * 7 * 24 * 60 * 60,
        # 8 weeks
        3 => 9 * 7 * 24 * 60 * 60,
        # 9 weeks
    );

    # Get idSubject via idAppuser
    $resArray = getIdSubjectForIdAppuser($idAppuser, $pdo, $pathToErrorLog);
    # Check if successfull
    if ($resArray['status'] == "error") {
        logError($pathToErrorLog, "checkandSendMissingIBANPush() in PushNotificationsFunctions.php", "Could not get idSubject for idAppuser: " . $idAppuser);
        # build json response
        $response = array(
            "status" => "error",
            "message" => "Could not get idSubject for idAppuser: " . $idAppuser
        );
        return $response;
    } else if ($resArray['status'] == "success") {
        $idSubject = $resArray['idSubject'];
    } else {
        logError($pathToErrorLog, "checkandSendMissingIBANPush() in PushNotificationsFunctions.php", "Invalid status from getIdSubjectForIdAppuser() for idAppuser: " . $idAppuser);
        # build json response
        $response = array(
            "status" => "error",
            "message" => "Invalid status from getIdSubjectForIdAppuser() for idAppuser: " . $idAppuser
        );
        return $response;
    }

    # Check if user has push notifications enabled
    # If yes, continue
    # If no, return
    $resArray = getPushNotificationsStatusForIdAppuser($idAppuser, $pdo, $pathToErrorLog);
    # Check if successfull
    if ($resArray["status"] == "error") {
        # log error
        logError($pathToErrorLog, "checkandSendMissingIBANPush() in PushNotificationsFunctions.php", "Could not get push notifications status for idAppuser: " . $idAppuser);

        # build json response
        $response = array(
            "status" => "error",
            "message" => "Could not get push notifications status for idAppuser: " . $idAppuser
        );
        return $response;
    } else if ($resArray["status"] == "success") {
        $pushNotificationStatus = $resArray["receivesPush"];
        if ($pushNotificationStatus == 0) {
            # build json response
            $response = array(
                "status" => "success",
                "message" => "User with idAppuser=" . $idAppuser . " has push notifications disabled. No further checks have been carried out."
            );
            return $response;
        } else if ($pushNotificationStatus != 1) {
            # log error
            logError($pathToErrorLog, "checkandSendMissingIBANPush() in PushNotificationsFunctions.php", "Invalid push notification status for idAppuser: " . $idAppuser);
            # build json response
            $response = array(
                "status" => "error",
                "message" => "Invalid push notifications status for idAppuser: " . $idAppuser
            );
            return $response;

        } else if ($pushNotificationStatus == 1 && $resArray['registration_id'] == "") {
            # Push notification have been enabled by the user but no registration_id is available.
            # build json response
            $response = array(
                "status" => "success",
                "message" => "User with idAppuser=" . $idAppuser . " has push notifications enabled but no registration_id is available. No further checks have been carried out."
            );
            return $response;

        } else if ($pushNotificationStatus == 1) {
            # Push notification have been enabled by the user. Continue.
        }
    } else {
        # log error
        logError($pathToErrorLog, "checkandSendMissingIBANPush() in PushNotificationsFunctions.php", "Invalid status from getPushNotificationsStatusForIdAppuser() for idAppuser: " . $idAppuser);
        # build json response
        $response = array(
            "status" => "error",
            "message" => "Invalid status from getPushNotificationsStatusForIdAppuser() for idAppuser: " . $idAppuser
        );
        return $response;
    }

    # Check if user has IBAN in table Subject via getIBANForIDSubject()
    # If yes, return
    # If no, continue
    $resArray = getIBANForIDSubject($idSubject, $encryptionKey, $pdo, $pathToErrorLog);
    # Check if successfull
    if ($resArray['status'] == "error") {
        logError($pathToErrorLog, "checkandSendMissingIBANPush() in PushNotificationsFunctions.php", "Could not get IBAN for idSubject: " . $idSubject);
        # build json response
        $response = array(
            "status" => "error",
            "message" => "Could not get IBAN for idSubject: " . $idSubject
        );
        return $response;
    } else if ($resArray['status'] == "success") {
        $IBAN = $resArray['iban'];
        if (empty($IBAN)) {
            # User has no IBAN. Continue.
        } else {
            # User has IBAN. Return.
            # build json response
            $response = array(
                "status" => "success",
                "message" => "User with idAppuser=" . $idAppuser . " has IBAN. No further checks have been carried out."
            );
            return $response;
        }
    } else {
        logError($pathToErrorLog, "checkandSendMissingIBANPush() in PushNotificationsFunctions.php", "Invalid status from getIBANForIDSubject() for idSubject: " . $idSubject);
        # build json response
        $response = array(
            "status" => "error",
            "message" => "Invalid status from getIBANForIDSubject() for idSubject: " . $idSubject
        );
        return $response;
    }

    # Get first login of user
    $resArray = getFirstLoginForIdAppuser($idAppuser, $pdo, $pathToErrorLog);

    # Check if successful
    if ($resArray['status'] == "error") {
        logError($pathToErrorLog, "checkandSendMissingIBANPush() in PushNotificationsFunctions.php", "Could not get first login for idAppuser: " . $idAppuser);
        # build json response
        $response = array(
            "status" => "error",
            "message" => "Could not get first login for idAppuser: " . $idAppuser
        );
        return $response;
    } else if ($resArray['status'] == "success") {
        $first_login = $resArray['first_login'];

        # Check if first login is null or empty string
        if ($first_login == null || $first_login == "") {
            # User has not logged in yet. Return.
            $json = array(
                "status" => "success",
                "message" => "User with idAppuser=" . $idAppuser . " has not logged in yet. No further checks have been carried out."
            );
            return $json;
        }

    } else {
        logError($pathToErrorLog, "checkandSendMissingIBANPush() in PushNotificationsFunctions.php", "Could not get first login for idAppuser: " . $idAppuser);
        # build json response
        $response = array(
            "status" => "error",
            "message" => "Could not get first login for idAppuser: " . $idAppuser
        );
        return $response;
    }

    # Convert first login to timestamp
    $firstLoginTimestamp = strtotime($first_login);

    # Get current timestamp
    $currentTimestamp = time();

    # Calculate difference in seconds
    $differenceInSeconds = $currentTimestamp - $firstLoginTimestamp;

    # Cycle through times in seconds
    foreach ($timesInSeconds as $reminderNumber => $time) {
        # Check if reminder has already been sent
        $resArray = getAllPushNotificationsForIDAppuserAndPushNotificationType($idAppuser, 5, $pdo, $pathToErrorLog);

        # Check if successfull
        if ($resArray['status'] == "error") {
            logError($pathToErrorLog, "checkandSendMissingIBANPush() in PushNotificationsFunctions.php", "Could not get all push notifications for idAppuser: " . $idAppuser . " and pushNotificationType: 5");
            # build json response
            $response = array(
                "status" => "error",
                "message" => "Could not get all push notifications for idAppuser: " . $idAppuser . " and pushNotificationType: 5"
            );
            return $response;
        } else if ($resArray['status'] == "success") {
            $notifications = $resArray['notifications'];
        } else {
            logError($pathToErrorLog, "checkandSendMissingIBANPush() in PushNotificationsFunctions.php", "Invalid status from getAllPushNotificationsForIDAppuserAndPushNotificationType() for idAppuser: " . $idAppuser . " and pushNotificationType: 5");
            # build json response
            $response = array(
                "status" => "error",
                "message" => "Invalid status from getAllPushNotificationsForIDAppuserAndPushNotificationType() for idAppuser: " . $idAppuser . " and pushNotificationType: 5"
            );
            return $response;
        }

        # Get number of notifications
        $numberOfNotifications = count($notifications);

        # Check if difference in seconds is greater than time in seconds
        if ($differenceInSeconds > $time) {
            # Check if reminder has already been sent
            if ($numberOfNotifications >= $reminderNumber) {
                # Reminder has already been sent. Continue.
                continue;
            } else {
                # Reminder has not been sent yet. Send reminder.
                $resArray = sendPushNotification("Sie haben bisher noch keine IBAN eingetragen. Bitte holen Sie dies zeitnah in Ihren Einstellungen nach, um eine Aufwandsentschädigung erhalten zu können.", "Fehlende IBAN für Aufwandsentschädigung", $cleverpush_channelid, 5, $idAppuser, $pdo, $cleverpush_apikey, $pathToErrorLog);
                # Check if successfull
                if ($resArray['status'] == "error") {
                    logError($pathToErrorLog, "checkandSendMissingIBANPush() in PushNotificationsFunctions.php", "Could not send push notification for idAppuser: " . $idAppuser . " and pushNotificationType: 5");
                    # build json response
                    $response = array(
                        "status" => "error",
                        "message" => "Could not send push notification for idAppuser: " . $idAppuser . " and pushNotificationType: 5"
                    );
                    return $response;
                } else if ($resArray['status'] == "success") {
                    # build json response
                    $response = array(
                        "status" => "success",
                        "message" => "Push notification of type 5 has been sent to user with idAppuser: " . $idAppuser
                    );
                    return $response;
                } else {
                    logError($pathToErrorLog, "checkandSendMissingIBANPush() in PushNotificationsFunctions.php", "Invalid status from sendPushNotification() for idAppuser: " . $idAppuser . " and pushNotificationType: 5");
                    # build json response
                    $response = array(
                        "status" => "error",
                        "message" => "Invalid status from sendPushNotification() for idAppuser: " . $idAppuser . " and pushNotificationType: 5"
                    );
                    return $response;
                }
            }
        } else {
            # Difference in seconds is not greater than time in seconds. Continue.
            continue;
        }
    }

    # build json response
    $response = array(
        "status" => "success",
        "message" => "No reminder of type 5 has been sent to user with idAppuser: " . $idAppuser
    );
    return $response;
}

/**
 * Check if new survey is available and push notification should be sent (including reminder push notifications)
 * @param int $idAppuser
 * @param PDO $pdo
 * @param string $encryptionKey
 * @param string $cleverpush_apikey
 * @param string $cleverpush_channelid
 * @param string $pathToErrorLog
 * @return array<string>
 */
function checkAndSendNewSurveyPush(int $idAppuser, PDO $pdo, string $encryptionKey, string $cleverpush_apikey, string $cleverpush_channelid, string $pathToErrorLog)
{
    # Timeslots for Push Notifications
    # Survey 1:
    # - first_login + 30 minutes
    # - first_login + 4 days
    # - first_login + 6 days
    # Survey 2:
    # - first_login + 5 weeks
    # - first_login + 5 weeks + 4 days
    # - first_login + 5 weeks + 6 days
    # Survey 3:
    # - first_login + 10 weeks
    # - first_login + 10 weeks + 4 days
    # - first_login + 10 weeks + 6 days

    # Define times as laid out aboves
    $timesInMinutes = array(
        1 => array(
            1 => 30,
            2 => 5760,
            3 => 8640
        ),
        2 => array(
            1 => 50400,
            2 => 56160,
            3 => 59040
        ),
        3 => array(
            1 => 100800,
            2 => 106560,
            3 => 109440
        )
    );

    # Check if user has push notifications enabled
    # If yes, continue
    # If no, return
    $resArray = getPushNotificationsStatusForIdAppuser($idAppuser, $pdo, $pathToErrorLog);
    # Check if successfull
    if ($resArray["status"] == "error") {
        # log error
        logError($pathToErrorLog, "checkAndSendNewSurveyPush() in PushNotificationsFunctions.php", "Could not get push notifications status for idAppuser: " . $idAppuser);

        # build json response
        $response = array(
            "status" => "error",
            "message" => "Could not get push notifications status for idAppuser: " . $idAppuser
        );
        return $response;
    } else if ($resArray["status"] == "success") {
        $pushNotificationStatus = $resArray["receivesPush"];
        if ($pushNotificationStatus == 0) {
            # build json response
            $response = array(
                "status" => "success",
                "message" => "User with idAppuser=" . $idAppuser . " has push notifications disabled. No further checks have been carried out."
            );
            return $response;
        } else if ($pushNotificationStatus != 1) {
            # log error
            logError($pathToErrorLog, "checkAndSendNewSurveyPush() in PushNotificationsFunctions.php", "Invalid push notification status for idAppuser: " . $idAppuser);
            # build json response
            $response = array(
                "status" => "error",
                "message" => "Invalid push notifications status for idAppuser: " . $idAppuser
            );
            return $response;
        } else if ($pushNotificationStatus == 1 && $resArray['registration_id'] == "") {
            # Push notification have been enabled by the user but no registration_id is available.
            # build json response
            $response = array(
                "status" => "success",
                "message" => "User with idAppuser=" . $idAppuser . " has push notifications enabled but no registration_id is available. No further checks have been carried out."
            );
            return $response;
        } else if ($pushNotificationStatus == 1) {
            # Push notification have been enabled by the user. Continue.
        }
    } else {
        # log error
        logError($pathToErrorLog, "checkAndSendNewSurveyPush() in PushNotificationsFunctions.php", "Invalid status from getPushNotificationsStatusForIdAppuser() for idAppuser: " . $idAppuser);
        # build json response
        $response = array(
            "status" => "error",
            "message" => "Invalid status from getPushNotificationsStatusForIdAppuser() for idAppuser: " . $idAppuser
        );
        return $response;
    }

    # Get first login of user
    $resArray = getFirstLoginForIdAppuser($idAppuser, $pdo, $pathToErrorLog);

    # Check if successfull
    if ($resArray['status'] == "error") {
        logError($pathToErrorLog, "checkAndSendNewSurveyPush() in PushNotificationsFunctions.php", "Could not get first login for idAppuser: " . $idAppuser . " error: " . $resArray['message']);
        # build json response
        $response = array(
            "status" => "error",
            "message" => "Could not get first login for idAppuser: " . $idAppuser . " error: " . $resArray['message']
        );
        return $response;
    } else if ($resArray['status'] == "success") {
        # Check if first_login is empty, because the user has not logged in yet
        if (empty($resArray['first_login'])) {
            $response = array(
                "status" => "success",
                "message" => "User with idAppuser=" . $idAppuser . " has not logged in yet. No push notification has been sent."
            );
            return $response;

        } else {
            # Get first login
            $firstLogin = $resArray['first_login'];
        }
    } else {
        logError($pathToErrorLog, "checkAndSendNewSurveyPush() in PushNotificationsFunctions.php", "Invalid status from getFirstLoginForIdAppuser() for idAppuser: " . $idAppuser) . " error: " . $resArray['message'];
        # build json response
        $response = array(
            "status" => "error",
            "message" => "Invalid status from getFirstLoginForIdAppuser() for idAppuser: " . $idAppuser . " error: " . $resArray['message']
        );
        return $response;
    }

    # Convert $firstLogin to timestamp
    $firstLogin = strtotime($firstLogin);

    # Get current time
    $currentTime = time();

    # Get difference between current time and first login
    $difference = $currentTime - $firstLogin;

    # Get difference in minutes
    $differenceInMinutes = $difference / 60;

    # Go through nested array and check if difference in minutes is bigger than one of the times
    foreach ($timesInMinutes as $surveyNumber => $times) {
        foreach ($times as $pushNotificationNumber => $time) {
            # Check if survey has been answered already and if so, skip it
            if (!isSurveyEmptyForIDAppUser($idAppuser, $pdo, $surveyNumber, $pathToErrorLog)) {
                continue;
            }

            $difference = $differenceInMinutes - $time;
            if ($difference >= 0) {
                # Check if push notification has already been sent
                $resArray = getAllPushNotificationsForIDAppuserAndPushNotificationType($idAppuser, $surveyNumber, $pdo, $pathToErrorLog);
                # Check if successfull
                if ($resArray['status'] == "error") {
                    logError($pathToErrorLog, "checkAndSendNewSurveyPush() in PushNotificationsFunctions.php", "Could not get all push notifications for idAppuser: " . $idAppuser . " error: " . $resArray['message']);
                    $response = array(
                        "status" => "error",
                        "message" => "Could not get all push notifications for idAppuser: " . $idAppuser . " error: " . $resArray['message']
                    );
                    return $response;

                } else if ($resArray['status'] == "success") {
                    # Access nested array and count number of push notifications
                    $numberOfPushNotifications = count($resArray['notifications']);
                    # Check if number of push notifications is smaller than the number of push notifications that should have been sent
                    if ($numberOfPushNotifications < $pushNotificationNumber) {
                        if ($pushNotificationNumber == 1) {
                            $message = "Eine neue Umfrage steht für Sie bereit und kann eine Woche lang beantwortet werden. Um zu starten, navigieren Sie zum Menüpunkt 'Umfragen'.";
                        } else if ($pushNotificationNumber == 2) {
                            $message = "Die aktuelle Umfrage wartet noch 3 Tage auf Sie! Nehmen Sie sich 10 Minuten Zeit, um sich Ihren Bonus zu sichern.";
                        } else if ($pushNotificationNumber == 3) {
                            $message = "Sie haben noch einen Tag Zeit, um an der aktuellen Umfrage teilzunehmen. Nehmen Sie sich 10 Minuten Zeit, um sich Ihren Bonus zu sichern.";
                        }
                        # Send push notification
                        $resArray = sendPushNotification($message, "Umfrage-Erinnerung DESIVE²", $cleverpush_channelid, $surveyNumber, $idAppuser, $pdo, $cleverpush_apikey, $pathToErrorLog);
                        # Check if successfull
                        if ($resArray['status'] == "error") {
                            logError($pathToErrorLog, "checkAndSendNewSurveyPush() in PushNotificationsFunctions.php", "Could not send push notification for idAppuser: " . $idAppuser . " error: " . $resArray['message']);
                            $response = array(
                                "status" => "error",
                                "message" => "Could not send push notification for idAppuser: " . $idAppuser . " error: " . $resArray['message']
                            );
                            return $response;
                        } else if ($resArray['status'] == "success") {
                            $response = array(
                                "status" => "success",
                                "message" => "Push notification has been sent to user with idAppuser=" . $idAppuser
                            );
                            return $response;
                        } else {
                            logError($pathToErrorLog, "checkAndSendNewSurveyPush() in PushNotificationsFunctions.php", "Invalid status from sendPushNotification() for idAppuser: " . $idAppuser . " error: " . $resArray['message']);
                            $response = array(
                                "status" => "error",
                                "message" => "Invalid status from sendPushNotification() for idAppuser: " . $idAppuser . " error: " . $resArray['message']
                            );
                            return $response;
                        }
                    }

                }
            }
            # Send push notification for survey 4
            $openSurveysResult = getOpenSurveysForIDAppuser($idAppuser, $pdo, $pathToErrorLog);

            if ($openSurveysResult['status'] == "success") {
                $openSurveys = $openSurveysResult['surveys'];

                if ($openSurveys['survey4'] == true) {
                    # Check if push notification has already been sent
                    $resArray = getAllPushNotificationsForIDAppuserAndPushNotificationType($idAppuser, 4, $pdo, $pathToErrorLog);
                    # Check if successful
                    if ($resArray['status'] == "error") {
                        logError($pathToErrorLog, "checkAndSendNewSurveyPush() in PushNotificationsFunctions.php", "Could not get all push notifications for idAppuser: " . $idAppuser . " error: " . $resArray['message']);
                        $response = array(
                            "status" => "error",
                            "message" => "Could not get all push notifications for idAppuser: " . $idAppuser . " error: " . $resArray['message']
                        );
                        return $response;

                    } else if ($resArray['status'] == "success") {
                        # Access nested array and count number of push notifications
                        $numberOfPushNotifications = count($resArray['notifications']);

                        if ($numberOfPushNotifications < 1) {
                            $message = "Wir haben noch drei Fragen zu Ihrer Person, bevor die App-Studie zu Ende ist. Navigieren Sie innerhalb der nächsten drei Tage zum Menüpunkt 'Umfragen', um teilzunehmen.";
                            # Send push notification
                            $resArray = sendPushNotification($message, "Abschlussfragen DESIVE²", $cleverpush_channelid, 4, $idAppuser, $pdo, $cleverpush_apikey, $pathToErrorLog);
                            # Check if successfull
                            if ($resArray['status'] == "error") {
                                logError($pathToErrorLog, "checkAndSendNewSurveyPush() in PushNotificationsFunctions.php", "Could not send push notification for idAppuser: " . $idAppuser . " error: " . $resArray['message']);
                                $response = array(
                                    "status" => "error",
                                    "message" => "Could not send push notification for idAppuser: " . $idAppuser . " error: " . $resArray['message']
                                );
                                return $response;
                            } else if ($resArray['status'] == "success") {
                                $response = array(
                                    "status" => "success",
                                    "message" => "Push notification has been sent to user with idAppuser=" . $idAppuser
                                );
                                return $response;
                            } else {
                                logError($pathToErrorLog, "checkAndSendNewSurveyPush() in PushNotificationsFunctions.php", "Invalid status from sendPushNotification() for idAppuser: " . $idAppuser . " error: " . $resArray['message']);
                                $response = array(
                                    "status" => "error",
                                    "message" => "Invalid status from sendPushNotification() for idAppuser: " . $idAppuser . " error: " . $resArray['message']
                                );
                                return $response;
                            }
                        }
                    }
                }
            }
        }
    }
    $json = array(
        "status" => "success",
        "message" => "No push notification has been sent to user with idAppuser=" . $idAppuser . ". Either the user has already answered the current survey or the time has not yet come."
    );
    return $json;
}

/**
 * Send push notification to user and save it in table Notifications
 * @param string $message
 * @param string $title
 * @param string $channelID
 * @param int $pushNotificationType
 * @param string $idAppUser
 * @param PDO $pdo
 * @param string $cleverpushapikey
 * @param string $pathToErrorLog
 * @return array<string>
 */
function sendPushNotification(string $message, string $title, string $channelID, int $pushNotificationType, int $idAppUser, PDO $pdo, string $cleverpushapikey, string $pathToErrorLog)
{
    # Get registration id
    $resArray = getRegistrationIdForIdAppuser($idAppUser, $pdo, $pathToErrorLog);
    # Check if successfull
    if ($resArray["status"] != "success") {
        # log error
        logError($pathToErrorLog, "sendInactivityPushNotification() in PushNotificationsFunctions.php", "Could not get registration id for idAppuser: " . $idAppUser);

        # build json response
        $response = array(
            "status" => "error",
            "message" => "Could not get registration id for idAppuser: " . $idAppUser
        );
        return $response;
    } else if ($resArray["status"] == "success") {
        # Check if registration id is empty
        if (empty($resArray["registration_id"]) || $resArray["registration_id"] == "") {
            # log error
            #logError($pathToErrorLog, "sendInactivityPushNotification() in PushNotificationsFunctions.php", "Registration id is empty for idAppuser: " . $idAppUser);

            # build json response
            $response = array(
                "status" => "error",
                "message" => "Registration id is empty for idAppuser: " . $idAppUser
            );
            return $response;
        } else {
            # Continue
        }
    } else {
        # suus status
        logError($pathToErrorLog, "sendInactivityPushNotification() in PushNotificationsFunctions.php", "Wrong status while getting registration ID for idAppuser: " . $idAppUser);
        $json = array(
            "status" => "error",
            "message" => "Wrong status while getting registration ID for idAppuser: " . $idAppUser
        );
        return $json;
    }
    # Get registration_id from $resArray
    $registrationId = $resArray["registration_id"];

    /**
     * Send push notification start
     */
    # TODO remove logging of to-be-sent push notification
    #logError($pathToErrorLog, "sendInactivityPushNotification() in PushNotificationsFunctions.php", "Sending push notification to idAppuser: " . $idAppUser . " with message: " . $message . " and idPushNotificationType: " . $pushNotificationType . " and registrationId: " . $registrationId);

    // Set API URL
    $url = 'https://api.cleverpush.com/notification/send';

    // build postdata with http_build_query
    $postdata = json_encode(
        array(
            'channelId' => $channelID,
            'title' => $title,
            'text' => $message,
            'subscriptionId' => $registrationId,
        )
    );

    $data = $postdata;
    $crl = curl_init($url);
    curl_setopt($crl, CURLOPT_POST, 1);
    curl_setopt($crl, CURLOPT_POSTFIELDS, $data);
    curl_setopt($crl, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: ' . $cleverpushapikey));
    curl_setopt($crl, CURLOPT_RETURNTRANSFER, 1);
    $result = json_decode(curl_exec($crl), true);

    if (curl_errno($crl)) {
        // this would be your first hint that something went wrong
        logError($pathToErrorLog, "sendInactivityPushNotification() in pushNotificationsFunctions.php", "Could not send push notification to idAppuser: " . $idAppUser . " with message: " . $message . " and idPushNotificationType: " . $pushNotificationType . " and registrationId: " . $registrationId . " error: " . curl_error($crl));

        $json = array(
            "status" => "error",
            "message" => "Could not send push notification to idAppuser: " . $idAppUser . " with message: " . $message . " and idPushNotificationType: " . $pushNotificationType . " and registrationId: " . $registrationId . " error: " . curl_error($crl)
        );
        return $json;

    } else {
        // check the HTTP status code of the request
        $resultStatus = curl_getinfo($crl, CURLINFO_HTTP_CODE);
        if ($resultStatus == 200) {
            if ($result['success'] == true) {
                # successfully sent push notification
                # Continue
                # Delete pushErrorSince from AppUser table
                $sql = "UPDATE AppUser SET pushErrorSince = NULL WHERE idAppUser = :idAppUser";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':idAppUser', $idAppUser, PDO::PARAM_INT);
                try {
                    $stmt->execute();
                } catch (PDOException $e) {
                    logError($pathToErrorLog, "sendInactivityPushNotification() in pushNotificationsFunctions.php", "Could not delete pushErrorSince from AppUser table for idAppUser: " . $idAppUser . " Error: " . $e->getMessage());
                }
            } else {
                logError($pathToErrorLog, "sendInactivityPushNotification() in pushNotificationsFunctions.php", "Could not send push notification to idAppuser: " . $idAppUser . " with message: " . $message . " and idPushNotificationType: " . $pushNotificationType . " and registrationId: " . $registrationId . " error: " . $result['error']);

                $json = array(
                    "status" => "error",
                    "httpStatuscode" => $resultStatus,
                    "message" => "Could not send push notification to idAppuser: " . $idAppUser . " with message: " . $message . " and idPushNotificationType: " . $pushNotificationType . " and registrationId: " . $registrationId . " error: " . $result['error']
                );
                return $json;
            }
        } else {
            # check if error is 404 and if so, add pushErrorSince timestamp to AppUser table
            if ($resultStatus == 404) {
                # add pushErrorSince timestamp to AppUser table
                $sql = "UPDATE AppUser SET pushErrorSince = NOW() WHERE idAppUser = :idAppUser AND pushErrorSince IS NULL";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':idAppUser', $idAppUser, PDO::PARAM_INT);
                try {
                    $stmt->execute();
                } catch (PDOException $e) {
                    logError($pathToErrorLog, "sendInactivityPushNotification() in pushNotificationsFunctions.php", "Could not add pushErrorSince timestamp to AppUser table for idAppUser: " . $idAppUser . " Error: " . $e->getMessage());
                }

                # Check if timestamp in pushErrorSince is older than 1 day
                $sql = "SELECT TIMESTAMPDIFF(day, pushErrorSince, NOW()) as timediff FROM AppUser WHERE idAppUser = :idAppUser";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':idAppUser', $idAppUser, PDO::PARAM_INT);
                try {
                    $stmt->execute();
                } catch (PDOException $e) {
                    logError($pathToErrorLog, "sendInactivityPushNotification() in pushNotificationsFunctions.php", "Could not get pushErrorSince timestamp from AppUser table for idAppUser: " . $idAppUser . " Error: " . $e->getMessage());
                }
                $resArray = $stmt->fetch(PDO::FETCH_ASSOC);
                # Check if timediff return value is 1
                if ($resArray['timediff'] == 1) {
                    # Delete registration_id from AppUser table and set pushErrorSince to NULL
                    $sql = "UPDATE AppUser SET registration_id = NULL, pushErrorSince = NULL WHERE idAppUser = :idAppUser";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':idAppUser', $idAppUser, PDO::PARAM_INT);
                    try {
                        $stmt->execute();
                    } catch (PDOException $e) {
                        logError($pathToErrorLog, "sendInactivityPushNotification() in pushNotificationsFunctions.php", "Could not delete registration_id from AppUser table for idAppUser: " . $idAppUser . " Error: " . $e->getMessage());
                    }
                    # Log the deletion to errorLog
                    logError($pathToErrorLog, "sendInactivityPushNotification() in pushNotificationsFunctions.php", "Deleted registration_id from AppUser table for idAppUser: " . $idAppUser . " because pushErrorSince persisted for 1 day.");
                }
            }

            logError($pathToErrorLog, "sendInactivityPushNotification() in pushNotificationsFunctions.php", "Could not send push notification to idAppuser: " . $idAppUser . " with message: " . $message . " and idPushNotificationType: " . $pushNotificationType . " and registrationId: " . $registrationId . " error: " . $resultStatus);

            $json = array(
                "status" => "error",
                "httpStatuscode" => $resultStatus,
                "message" => "Could not send push notification to idAppuser: " . $idAppUser . " with message: " . $message . " and idPushNotificationType: " . $pushNotificationType . " and registrationId: " . $registrationId . " error: " . $resultStatus
            );
            return $json;
        }
    }

    curl_close($crl);

    /**
     * Send push notification end
     */

    try {
        # Save push notification to database
        $sql = "INSERT INTO Notifications (idAppUser, idPushNotificationType) VALUES (:idAppUser, :idPushNotificationType)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':idAppUser', $idAppUser, PDO::PARAM_INT);
        $stmt->bindParam(':idPushNotificationType', $pushNotificationType, PDO::PARAM_INT);
        $stmt->execute();

        $json = array(
            "status" => "success",
            "message" => "Push notification has been sent to idAppuser: " . $idAppUser . ". Push notification has been saved to database."
        );
        return $json;

    } catch (PDOException $e) {
        # log error
        logError($pathToErrorLog, "sendInactivityPushNotification() in PushNotificationsFunctions.php", "Could not save push notification to database for idAppuser: " . $idAppUser . " Error: " . $e->getMessage());

        # build json response
        $response = array(
            "status" => "error",
            "message" => "Push notification has been sent to idAppuser: " . $idAppUser . ". Push notification could NOT be saved to database."
        );
        return $response;
    }
}

function checkAndSendUsageReminderViaEMail(int $idAppuser, PDO $pdo, string $encryptionKey, string $webroot, string $mail_from_address, string $mail_from_name, string $pathToErrorLog)
{
    # Define array of points in time when to send reminder
    $timesInSeconds = array(
        1 => 604800,
        # 1 week
        2 => 1209600,
        # 2 weeks
        3 => 1814400,
        # 3 weeks
    );

    $notificationTypeID = 7;

    # Get first login of user
    $resArray = getFirstLoginForIdAppuser($idAppuser, $pdo, $pathToErrorLog);

    # Check if successful
    if ($resArray['status'] == "error") {
        logError($pathToErrorLog, "checkAndSendUsageReminderViaEMail() in PushNotificationsFunctions.php", "Could not get first login for idAppuser: " . $idAppuser);
        # build json response
        $response = array(
            "status" => "error",
            "message" => "Could not get first login for idAppuser: " . $idAppuser
        );
        return $response;
    } else if ($resArray['status'] == "success") {
        $first_login = $resArray['first_login'];

        # Check if first login is null or empty string
        if ($first_login != null || $first_login != "") {
            # User alraedy logged in -> abort
            $json = array(
                "status" => "success",
                "message" => "User already logged in. No reminder will be sent."
            );
            return $json;
        }

    } else {
        logError($pathToErrorLog, "checkAndSendUsageReminderViaEMail() in PushNotificationsFunctions.php", "Could not get first login for idAppuser: " . $idAppuser);
        # build json response
        $response = array(
            "status" => "error",
            "message" => "Could not get first login for idAppuser: " . $idAppuser
        );
        return $response;
    }

    # Get creation date of account
    $resArray = getCreationDateOfAccount($idAppuser, $pdo, $pathToErrorLog);

    # Check if successful
    if ($resArray['status'] == "error") {
        logError($pathToErrorLog, "checkAndSendUsageReminderViaEMail() in PushNotificationsFunctions.php", "Could not get creation date of account for idAppuser: " . $idAppuser);
        # build json response
        $response = array(
            "status" => "error",
            "message" => "Could not get creation date of account for idAppuser: " . $idAppuser
        );
        return $response;
    } else if ($resArray['status'] == "success") {
        $creation_timestamp = $resArray['creation_timestamp'];
    } else {
        logError($pathToErrorLog, "checkAndSendUsageReminderViaEMail() in PushNotificationsFunctions.php", "Could not get creation date of account for idAppuser: " . $idAppuser);
        # build json response
        $response = array(
            "status" => "error",
            "message" => "Could not get creation date of account for idAppuser: " . $idAppuser
        );
        return $response;
    }

    # Convert creation timestamp to seconds
    $creation_timestamp = strtotime($creation_timestamp);

    # Get current timestamp
    $current_timestamp = time();

    # Get difference between current timestamp and creation timestamp
    $difference = $current_timestamp - $creation_timestamp;

    # Cycle through times in seconds
    foreach ($timesInSeconds as $reminderNumber => $time) {
        # Check if a reminder has already been sent
        $resArray = getAllPushNotificationsForIDAppuserAndPushNotificationType($idAppuser, $notificationTypeID, $pdo, $pathToErrorLog);

        # Check if successful
        if ($resArray['status'] == "error") {
            logError($pathToErrorLog, "checkAndSendUsageReminderViaEMail() in PushNotificationsFunctions.php", "Could not get all push notifications for idAppuser: " . $idAppuser . " and push notification type: 7");
            # build json response
            $response = array(
                "status" => "error",
                "message" => "Could not get all push notifications for idAppuser: " . $idAppuser . " and push notification type: 7"
            );
            return $response;
        } else if ($resArray['status'] == "success") {
            $notifications = $resArray['notifications'];
        } else {
            logError($pathToErrorLog, "checkAndSendUsageReminderViaEMail() in PushNotificationsFunctions.php", "Could not get all push notifications for idAppuser: " . $idAppuser . " and push notification type: 7");
            # build json response
            $response = array(
                "status" => "error",
                "message" => "Could not get all push notifications for idAppuser: " . $idAppuser . " and push notification type: 7"
            );
            return $response;
        }

        # Get number of notifications
        $numberOfNotifications = count($notifications);

        #logError($pathToErrorLog, "checkAndSendUsageReminderViaEMail() in PushNotificationsFunctions.php", "Number of notifications: " . $numberOfNotifications . " for idAppuser: " . $idAppuser . " and push notification type: 7" . " and reminder number: " . $reminderNumber);

        # Check if difference is greater/== than time
        if ($difference >= $time) {
            # Check if a reminder has already been sent
            if ($numberOfNotifications < $reminderNumber) {
                # Get email address of user
                $resArray = getEmailAddressAndNameOfIDAppuser($idAppuser, $encryptionKey, $pdo, $pathToErrorLog);

                # Check if successful
                if ($resArray['status'] == "error") {
                    logError($pathToErrorLog, "checkAndSendUsageReminderViaEMail() in PushNotificationsFunctions.php", "Could not get email address of idAppuser: " . $idAppuser);
                    # build json response
                    $response = array(
                        "status" => "error",
                        "message" => "Could not get email address of idAppuser: " . $idAppuser
                    );
                    return $response;
                } else if ($resArray['status'] == "success") {
                    $email = $resArray['email'];
                    $name = $resArray['name'];
                } else {
                    logError($pathToErrorLog, "checkAndSendUsageReminderViaEMail() in PushNotificationsFunctions.php", "Could not get email address of idAppuser: " . $idAppuser) . " error: " . $resArray['message'];
                    # build json response
                    $response = array(
                        "status" => "error",
                        "message" => "Could not get email address of idAppuser: " . $idAppuser . " error: " . $resArray['message']
                    );
                    return $response;
                }

                # Send reminder
                # Build email to send to user
                $subject = "DESIVE² - Erinnerung: Nehmen Sie jetzt an unserer App-Studie teil";
                $preheader = "Loggen Sie sich jetzt in die DESIVE² App ein, um zu starten";
                $mailTitle = "Loggen Sie sich jetzt in die DESIVE² App ein, um zu starten";
                ob_start();
                include($webroot . "/private/resources/mail-templates/email-body-no-usage-reminder.html");
                $body = ob_get_clean();

                # Send mail via func
                $mailstatus = sendMail($email, $name, $mail_from_address, $mail_from_name, $subject, $preheader, $mailTitle, $body, $webroot, $pathToErrorLog);

                # Check if mail was sent successfully by reading the JSON in mailstatus
                if ($mailstatus['status'] != "success") {
                    logError($webroot . "/private/errorLog.txt", "checkAndSendUsageReminderViaEMail() in pushNotificationFunctions.php", "Reminder-mail to participant not sent. Mailstatus: " . $mailstatus['status'] . " - " . $mailstatus['message']);
                    # build json response
                    $response = array(
                        "status" => "error",
                        "message" => "Reminder-mail to participant not sent. Mailstatus: " . $mailstatus['status'] . " - " . $mailstatus['message']
                    );
                    return $response;
                } else {
                    try {
                        # Save push notification to database
                        $sql = "INSERT INTO Notifications (idAppUser, idPushNotificationType) VALUES (:idAppUser, :idPushNotificationType)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->bindParam(':idAppUser', $idAppuser, PDO::PARAM_INT);
                        $stmt->bindParam(':idPushNotificationType', $notificationTypeID, PDO::PARAM_INT);
                        $stmt->execute();

                        # build json response
                        $json = array(
                            "status" => "success",
                            "message" => "Reminder-mail to participant sent successfully. idAppuser: " . $idAppuser . ". Notification has been saved to database."
                        );
                        return $json;

                    } catch (PDOException $e) {
                        # log error
                        logError($pathToErrorLog, "sendInactivityPushNotification() in PushNotificationsFunctions.php", "Could not save push notification to database for idAppuser: " . $idAppuser . " Error: " . $e->getMessage());

                        # build json response
                        $response = array(
                            "status" => "error",
                            "message" => "Push notification has been sent to idAppuser: " . $idAppuser . ". Push notification could NOT be saved to database."
                        );
                        return $response;
                    }

                }
            }
        }
    }
    $json = array(
        "status" => "success",
        "message" => "No reminder was sent to idAppuser: " . $idAppuser . " because a reminder was already sent or the difference between current timestamp and creation timestamp was not greater than the time in seconds."
    );
    return $json;


}

function checkAndSendDebriefingAndIBANReminderViaEMail(int $idAppuser, PDO $pdo, string $encryptionKey, string $webroot, string $mail_from_address, string $mail_from_name, string $pathToErrorLog)
{
    # Define array of points in time when to send reminder
    $timesInSeconds = array(
        1 => 10 * 7 * 24 * 60 * 60,
        # 10 weeks
    );

    $notificationTypeID = 8;

    # Get first login of user
    $resArray = getFirstLoginForIdAppuser($idAppuser, $pdo, $pathToErrorLog);

    # Check if successful
    if ($resArray['status'] == "error") {
        logError($pathToErrorLog, "checkAndSendDebriefingAndIBANReminderViaEMail() in PushNotificationsFunctions.php", "Could not get first login for idAppuser: " . $idAppuser);
        # build json response
        $response = array(
            "status" => "error",
            "message" => "Could not get first login for idAppuser: " . $idAppuser
        );
        return $response;
    } else if ($resArray['status'] == "success") {
        $first_login = $resArray['first_login'];

        # Check if first login is null or empty string
        if ($first_login == null || $first_login == "") {
            # User alraedy logged in -> abort
            $json = array(
                "status" => "success",
                "message" => "User has not logged in yet. No reminder was sent to idAppuser: " . $idAppuser
            );
            return $json;
        } else {
            # User logged in -> check if reminder should be sent
        }

    } else {
        logError($pathToErrorLog, "checkAndSendDebriefingAndIBANReminderViaEMail() in PushNotificationsFunctions.php", "Could not get first login for idAppuser: " . $idAppuser);
        # build json response
        $response = array(
            "status" => "error",
            "message" => "Could not get first login for idAppuser: " . $idAppuser
        );
        return $response;
    }

    # Convert creation timestamp to seconds
    $first_login_timestamp = strtotime($first_login);

    # Get current timestamp
    $current_timestamp = time();

    # Get difference between current timestamp and creation timestamp
    $difference = $current_timestamp - $first_login_timestamp;

    # Cycle through times in seconds
    foreach ($timesInSeconds as $reminderNumber => $time) {
        # Check if a reminder has already been sent
        $resArray = getAllPushNotificationsForIDAppuserAndPushNotificationType($idAppuser, $notificationTypeID, $pdo, $pathToErrorLog);

        # Check if successful
        if ($resArray['status'] == "error") {
            logError($pathToErrorLog, "checkAndSendDebriefingAndIBANReminderViaEMail() in PushNotificationsFunctions.php", "Could not get all push notifications for idAppuser: " . $idAppuser . " and push notification type: " . $notificationTypeID);
            # build json response
            $response = array(
                "status" => "error",
                "message" => "Could not get all push notifications for idAppuser: " . $idAppuser . " and push notification type: " . $notificationTypeID
            );
            return $response;
        } else if ($resArray['status'] == "success") {
            $notifications = $resArray['notifications'];
        } else {
            logError($pathToErrorLog, "checkAndSendDebriefingAndIBANReminderViaEMail() in PushNotificationsFunctions.php", "Could not get all push notifications for idAppuser: " . $idAppuser . " and push notification type: " . $notificationTypeID);
            # build json response
            $response = array(
                "status" => "error",
                "message" => "Could not get all push notifications for idAppuser: " . $idAppuser . " and push notification type: " . $notificationTypeID
            );
            return $response;
        }

        # Get number of notifications
        $numberOfNotifications = count($notifications);

        # Check if difference is greater/== than time
        if ($difference >= $time) {
            # Check if a reminder has already been sent
            if ($numberOfNotifications < $reminderNumber) {
                # Get email address of user
                $resArray = getEmailAddressAndNameOfIDAppuser($idAppuser, $encryptionKey, $pdo, $pathToErrorLog);

                # Check if successful
                if ($resArray['status'] == "error") {
                    logError($pathToErrorLog, "checkAndSendDebriefingAndIBANReminderViaEMail() in PushNotificationsFunctions.php", "Could not get email address of idAppuser: " . $idAppuser);
                    # build json response
                    $response = array(
                        "status" => "error",
                        "message" => "Could not get email address of idAppuser: " . $idAppuser
                    );
                    return $response;
                } else if ($resArray['status'] == "success") {
                    $email = $resArray['email'];
                    $name = $resArray['name'];
                } else {
                    logError($pathToErrorLog, "checkAndSendDebriefingAndIBANReminderViaEMail() in PushNotificationsFunctions.php", "Could not get email address of idAppuser: " . $idAppuser) . " error: " . $resArray['message'];
                    # build json response
                    $response = array(
                        "status" => "error",
                        "message" => "Could not get email address of idAppuser: " . $idAppuser . " error: " . $resArray['message']
                    );
                    return $response;
                }

                # Send reminder
                # Build email to send to user
                $subject = "DESIVE² - Ihre Studienteilnahme nähert sich dem Ende";
                $preheader = "In dieser E-Mail finden Sie wichtige Informationen zu Ihrer Studienteilnahme.";
                $mailTitle = "Ihre Studienteilnahme nähert sich dem Ende";
                ob_start();
                include($webroot . "/private/resources/mail-templates/email-body-debrief-and-iban-reminder.html");
                $body = ob_get_clean();

                # Send mail via func
                $mailstatus = sendMail($email, $name, $mail_from_address, $mail_from_name, $subject, $preheader, $mailTitle, $body, $webroot, $pathToErrorLog);

                # Check if mail was sent successfully by reading the JSON in mailstatus
                if ($mailstatus['status'] != "success") {
                    logError($webroot . "/private/errorLog.txt", "checkAndSendUsageReminderViaEMail() in pushNotificationFunctions.php", "Reminder-mail to participant not sent. Mailstatus: " . $mailstatus['status'] . " - " . $mailstatus['message']);
                    # build json response
                    $response = array(
                        "status" => "error",
                        "message" => "Debrief-mail to participant not sent. Mailstatus: " . $mailstatus['status'] . " - " . $mailstatus['message']
                    );
                    return $response;
                } else {
                    try {
                        # Save push notification to database
                        $sql = "INSERT INTO Notifications (idAppUser, idPushNotificationType) VALUES (:idAppUser, :idPushNotificationType)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->bindParam(':idAppUser', $idAppuser, PDO::PARAM_INT);
                        $stmt->bindParam(':idPushNotificationType', $notificationTypeID, PDO::PARAM_INT);
                        $stmt->execute();

                        # build json response
                        $json = array(
                            "status" => "success",
                            "message" => "Debrief-mail to participant sent successfully. idAppuser: " . $idAppuser . ". Notification has been saved to database."
                        );
                        return $json;

                    } catch (PDOException $e) {
                        # log error
                        logError($pathToErrorLog, "checkAndSendDebriefingAndIBANReminderViaEMail() in PushNotificationsFunctions.php", "Could not save push notification to database for idAppuser: " . $idAppuser . " Error: " . $e->getMessage());

                        # build json response
                        $response = array(
                            "status" => "error",
                            "message" => "Push notification has been sent to idAppuser: " . $idAppuser . ". Push notification could NOT be saved to database."
                        );
                        return $response;
                    }

                }
            }
        }
    }
    $json = array(
        "status" => "success",
        "message" => "No Debrief-mail was sent to idAppuser: " . $idAppuser . " because a mail was already sent or the difference between current timestamp and creation timestamp was not greater than the time in seconds."
    );
    return $json;

}

?>