<?php
/**
 * Validate the session-token for the user
 * @param PDO $conn
 * @param $token
 * @param $pathToErrorLog
 * @return int -1 if token exists more than once, -2 if PDOException is thrown, otherwise return the AppUserID (always positive)
 */
function TokenAuth(PDO $conn, $token, $pathToErrorLog)
{

    # Check if the token is valid for a user
    $sql = "SELECT idAppUser FROM AppUser WHERE enc_token = ?";
    $stmt = $conn->prepare($sql);
    # Check if result is more than 1 rows
    # Build try/catch for PDOException
    try {
        if ($stmt->execute(array($token))) {
            if ($stmt->rowCount() === 1) {
                # User found. Save idAppUser from result
                $idAppuser = $stmt->fetchColumn();

                # Update last_online column in AppUser table
                $sql = "UPDATE AppUser SET last_online = NOW() WHERE idAppUser = ?";
                $stmt = $conn->prepare($sql);

                # Try/Catch for pdoException
                try {
                    if ($stmt->execute(array($idAppuser))) {
                        # Return the AppUserID
                        return $idAppuser;
                    }
                } catch (PDOException $e) {
                    # Append error to errorLog.txt located in ../private/errorLog.txt to get the path of the current file
                    logError($pathToErrorLog, "DBConnect.php TokenAuth()", $e->getMessage());
                    return -2;
                }
            }
            return -1;
        }
    } catch (PDOException $e) {
        # Append error to errorLog.txt located in ../private/errorLog.txt to get the path of the current file
        logError($pathToErrorLog, "DBConnect.php TokenAuth()", $e->getMessage());
        return -2;
    }
    return -2;
}

# Create app account by inserting the user into the AppUser table
# Checks if the email is already in use and if so, returns -1.
# If PDOException is thrown, returns -2.
function createAppAccount($pdo, $email, $encryptionKey, $pathToErrorLog)
{
    # Check if app user with the given email already exists.
    if (appUserExists($pdo, $email, $encryptionKey, $pathToErrorLog)) {
        return -1;
    }

    # Get the idSubject from the Subject table
    $idSubject = getIDSubject($pdo, $email, $encryptionKey, $pathToErrorLog);
    # If idSubject is -1 or -2, the operation was not successful, return -1 and die
    if ($idSubject == -1 || $idSubject == -2) {
        return -2;
    }

    # Create random string to be used as preliminary password
    $password = bin2hex(random_bytes(32));

    # Create new user line in AppUser table
    # Build try catch for PDOException
    try {
        # Prepare the SQL statement to insert the user into AppUser and execute it
        $sql = "INSERT INTO AppUser (idSubject, enc_password) VALUES (?, ?)";
        $stmt = $pdo->prepare($sql);

        $stmt->execute(array($idSubject, $password));
    } catch (PDOException $e) {
        # Append error to errorLog.txt located in ../private/errorLog.txt using to get the path of the current file
        logError($pathToErrorLog, "DBConnect.php createAppAccount()", $e->getMessage());
        return -3;

    }

    return 1;
}

/**
 * Create app account for all users in the Waitlist table
 * @param PDO $conn
 * @param string $encryptionKey
 * @param array $password_options
 * @param string $mail_from_address
 * @param string $mail_from_name
 * @param string $webroot
 * @param string $pathToErrorLog
 * @return array array("status" => "success", "message" => "string", "data" => array(array("email" => "email", "status" => "")
 */
function createAppAccountForWaitlist(PDO $conn, string $encryptionKey, $password_options, $mail_from_address, $mail_from_name, $webroot, $pathToErrorLog)
{
    $createdUsers = array();

    # Get all Waitlist users
    $waitlistUsers = getAllWaitlistUsers($conn, $encryptionKey, $pathToErrorLog);

    # Check if successful
    if ($waitlistUsers["status"] == "success") {

        # Loop through all waitlist users
        foreach ($waitlistUsers["data"] as $waitlistUser) {

            # Create app account for each waitlist user
            $result = createAppAccount($conn, $waitlistUser["email"], $encryptionKey, $pathToErrorLog);

            # Check if successful
            if ($result == -1) {
                # User already exists, log error
                logError($pathToErrorLog, "DBConnect.php createAppAccountForWaitlist()", "Error while creating app account. User already exists. Email: " . $waitlistUser["email"] . "");
            } elseif ($result == -2 || $result == -3) {
                # Error while creating app account, log error
                logError($pathToErrorLog, "DBConnect.php createAppAccountForWaitlist()", "Error while creating app account. Email: " . $waitlistUser["email"] . "");
            } elseif ($result == 1) {

                # App account successfully created, send email to user
                # Reset password for the user
                $resultPasswordReset = resetPassword($conn, $waitlistUser["email"], $encryptionKey, $password_options, $mail_from_address, $mail_from_name, $webroot, $webroot . "/private/errorLog.txt");
                # Check the result of the resetPassword function (if -1, the user with this email was not found, if -2 the DB statement failed, if 1 the password was reset successfully)
                if ($resultPasswordReset == -1) {
                    logError($webroot . "/private/errorLog.txt", "createAppAccountForWaitlist() in UserManagement.php", "User " . $waitlistUser["email"] . "for password reset not found!");
                    # Add email address and status to createdUsers array
                    $createdUsers[] = array("email" => $waitlistUser["email"], "status" => "not found");
                } else if ($resultPasswordReset == -2) {
                    echo "DB statement failed!";
                    logError($webroot . "/private/errorLog.txt", "createAppAccountForWaitlist() in UserManagement.php", "DB statement for password reset failed! Email: " . $waitlistUser["email"]);
                    # Add email address and status to createdUsers array
                    $createdUsers[] = array("email" => $waitlistUser["email"], "status" => "db statement failed");
                } else if ($resultPasswordReset == -3) {
                    echo "Password reset mail failed!";
                    logError($webroot . "/private/errorLog.txt", "createAppAccountForWaitlist() in UserManagement.php", "Password reset mail failed! Email: " . $waitlistUser["email"]);
                    # Add email address and status to createdUsers array
                    $createdUsers[] = array("email" => $waitlistUser["email"], "status" => "mail failed");
                } else if ($resultPasswordReset == 1) {
                    # Add email address and status to createdUsers array
                    $createdUsers[] = array("email" => $waitlistUser["email"], "status" => "success");
                }
            } else {
                # unknown error occurred, log error
                logError($pathToErrorLog, "DBConnect.php createAppAccountForWaitlist()", "Unknown error while creating app account. Email: " . $waitlistUser["email"] . " Result: " . $result . "");
            }
        }

        # Return array with all created users and overall status and message
        return array("status" => "success", "message" => "Created app accounts for all waitlist users. See data for details on success.", "data" => $createdUsers);
    } else {
        logError($pathToErrorLog, "DBConnect.php createAppAccountForWaitlist()", "Error while getting all waitlist users. See data for details. Data: " . json_encode($waitlistUsers) . "");
        # Error while getting all waitlist users, return error
        return array("status" => "error", "message" => "Error while getting all waitlist users. See data for details.", "data" => $waitlistUsers);
    }
}

/**
 * Get all waitlist users
 * @param PDO $conn
 * @param $encryptionKey
 * @param $pathToErrorLog
 * @return array(string status, string message, array data (idSubject, name, email)))
 */
function getAllWaitlistUsers(PDO $conn, $encryptionKey, $pathToErrorLog)
{
    # Build try catch for PDOException
    try {
        # Prepare the SQL statement to get all waitlist users
        $sql = "SELECT idSubject, AES_DECRYPT(enc_name, :encryptionKey) as name, AES_DECRYPT(enc_mail, :encryptionKey) as email FROM Subject LEFT JOIN AppUser USING(idSubject) WHERE AppUser.idSubject IS NULL AND Subject.app=1";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':encryptionKey', $encryptionKey);
        $stmt->execute();

        # Build Json
        $json = array(
            "status" => "success",
            "message" => "Successfully retrieved all waitlist users",
            "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
        return $json;

    } catch (PDOException $e) {
        # Append error to errorLog.txt located in ../private/errorLog.txt using dirname(__FILE__) to get the path of the current file
        logError($pathToErrorLog, "DBConnect.php getAllWaitlistUsers()", $e->getMessage());
        return array(
            "status" => "error",
            "message" => "Error while retrieving all waitlist users",
            "data" => array()
        );
    }
}

# Get idSubject from Subject table by email
# Returns -1 if DB statement fails
# Returns -2 if more than one or no rows are returned
function getIDSubject(PDO $conn, $email, $encryptionKey, $pathToErrorLog)
{
    # Build try catch for PDOException
    try {
        # Prepare the SQL statement to insert the user into AppUser and execute it
        $sql = "SELECT `idSubject` FROM `Subject` WHERE `enc_mail` = AES_ENCRYPT(?,'$encryptionKey')";
        $stmt = $conn->prepare($sql);
        $stmt->execute(array($email));
    } catch (PDOException $e) {
        # Append error to errorLog.txt located in ../private/errorLog.txt using dirname(__FILE__) to get the path of the current file
        logError($pathToErrorLog, "DBConnect.php getIDSubject()", $e->getMessage());
        return -1;
    }
    # Count number of rows
    $count = $stmt->rowCount();
    # If there is only one row, return the idSubject
    if ($count == 1) {
        # Get the idSubject
        $idSubject = $stmt->fetchColumn();
        return $idSubject;
    } else {
        logError($pathToErrorLog, "DBConnect.php getIDSubject()", "More than one or no rows returned. Count: $count");
        # If there is more or less than one row, return -2
        return -2;
    }
}

/**
 * Get the idSubject for the given Pseudonym (=SubjectIdentifier)
 * @param string $pseudonym
 * @param PDO $conn
 * @param string $encryptionKey
 * @param string $pathToErrorLog
 * @return array with status, message and data (idSubject)
 */
function getIDSubjectByPseudonym(string $pseudonym, PDO $conn, string $encryptionKey, string $pathToErrorLog)
{
    # Build try catch for PDOException
    try {
        # Prepare the SQL statement to insert the user into AppUser and execute it
        $sql = "SELECT idSubject FROM Subject WHERE SubjectIdentifier = :pseudonym";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':pseudonym', $pseudonym);
        $stmt->execute();
    } catch (PDOException $e) {
        logError($pathToErrorLog, "functions.php getIDSubjectByPseudonym()", $e->getMessage());
        return array(
            "status" => "error",
            "message" => "Error while retrieving idSubject by pseudonym",
            "data" => array()
        );
    }
    # Count number of rows
    $count = $stmt->rowCount();
    # If there is only one row, return the idSubject
    if ($count == 1) {
        # Get the idSubject
        $idSubject = $stmt->fetchColumn();
        return array(
            "status" => "success",
            "message" => "Successfully retrieved idSubject by pseudonym",
            "data" => array(
                "idSubject" => $idSubject
            )
        );
    } else {
        logError($pathToErrorLog, "DBConnect.php getIDSubjectByPseudonym()", "More than one or no rows returned. Count: $count");
        return array(
            "status" => "error",
            "message" => "Error while retrieving idSubject by pseudonym",
            "data" => array()
        );
    }
}

# Reset password for user with given email. Checks if user exists.
# Generate random, alpha-numeric string of length 10, save it in the database, change hasSetPassword to 0 and send mail to user
# Return 1 if successful, -1 if user does not exist, -2 if DB statement fails
function resetPassword(PDO $conn, $email, $encryptionKey, $password_options, $mail_from_address, $mail_from_name, $webroot, $pathToErrorLog)
{
    # Check if user with the given email exists. If not, return -1
    if (!appUserExists($conn, $email, $encryptionKey, $pathToErrorLog)) {
        return -1;
    }

    # Generate random, alpha-numeric string of length 10
    $password = substr(str_shuffle("23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ"), 0, 10);
    # Hash the password using php's password_hash function with BCRYPT algorithm
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT, $password_options);

    # Build try catch for PDOException for setting the password in the database
    try {
        # Update the user with the given email. Password is stored in enc_password, hasSetPassword is set to 0
        $sql = "UPDATE AppUser SET enc_password = ?, hasSetPassword = 0, enc_token = NULL WHERE idSubject = (SELECT idSubject FROM Subject WHERE AES_ENCRYPT(?, '$encryptionKey') = enc_mail)";
        $stmt = $conn->prepare($sql);
        $stmt->execute(array($hashedPassword, $email));
    } catch (PDOException $e) {
        # Append error to errorLog.txt located in ../private/errorLog.txt using dirname(__FILE__) to get the path of the current file
        logError($pathToErrorLog, "functions.php resetPassword()", $e->getMessage());
        return -2;
    }

    # Get name of user with given email via getName function and decode JSON response to get name field
    $res = getNameViaMail($conn, $email, $encryptionKey, $pathToErrorLog);
    if ($res["status"] == "success") {
        $name = $res["name"];
    } else {
        return -4;
    }

    # Send new password mail to user
    # Build email to send to user
    $subject = "DESIVE² - Neues Passwort für die DESIVE²-App";
    $preheader = "In dieser E-Mail erhalten Sie ein neues Passwort für die DESIVE2-App.";
    $mailTitle = "Neues Passwort für die DESIVE²-App";
    ob_start();
    include($webroot . "/private/resources/mail-templates/email-body-new-app-password.html");
    $body = ob_get_clean();

    # Replace placeholders in email body
    $body = str_replace("{{email}}", $email, $body);
    $body = str_replace("{{password}}", $password, $body);

    # Send mail via func
    $mailstatus = sendMail($email, $name, $mail_from_address, $mail_from_name, $subject, $preheader, $mailTitle, $body, $webroot, $pathToErrorLog);

    # Check if mail was sent successfully by reading the JSON in mailstatus
    if ($mailstatus['status'] != "success") {
        logError($webroot . "/private/errorLog.txt", "resetPassword() in functions.php", "Reset-password-mail to participant not sent. Mailstatus: " . $mailstatus['status'] . " - " . $mailstatus['message']);
        return -3;
    }

    return 1;
}
# Check if exactly one user with given email exists
function appUserExists(PDO $conn, $email, $key, $pathToErrorLog)
{
    try {
        $sql = "SELECT idAppUser FROM AppUser WHERE idSubject = (SELECT idSubject FROM Subject WHERE AES_ENCRYPT(:email, :key) = enc_mail)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':key', $key);
        if ($stmt->execute() && $stmt->rowCount() === 1) {
            return true;
        } else {
            return false;
        }
    } catch (PDOException $e) {
        # Append error to errorLog.txt
        logError($pathToErrorLog, "DBConnect.php appUserExists()", $e->getMessage());
        return false;
    }
}

/**
 * Set the IBAN in the Subject table for the given idSubject
 * @param int $idSubject valid idSubject
 * @param string $IBAN needs to be filtered before calling this function
 * @param PDO $conn valid PDO connection
 * @param string $key encryption key
 * @param string $pathToErrorLog path to errorLog.txt
 * @return array with status and message
 */
function setIBAN(int $idSubject, string $IBAN, PDO $conn, string $key, string $pathToErrorLog)
{
    try {
        $sql = "UPDATE Subject SET enc_IBAN = AES_ENCRYPT(:iban, :enc_key) WHERE idSubject = :idSubject";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':iban', $IBAN);
        $stmt->bindParam(':enc_key', $key);
        $stmt->bindParam(':idSubject', $idSubject);
        $stmt->execute();

        $json = array(
            'status' => 'success',
            'message' => 'IBAN successfully set.'
        );
        return $json;

    } catch (PDOException $e) {
        logError($pathToErrorLog, "functions.php setIBAN()", "Error setting IBAN. idSubject: " . $idSubject . " IBAN: " . $IBAN . " - " . $stmt->errorInfo()[2]);
        $json = array(
            'status' => 'error',
            'message' => 'Error setting IBAN.'
        );
        return $json;
    }
}

function setName(int $idSubject, string $name, PDO $conn, string $key, string $pathToErrorLog)
{
    try {
        $sql = "UPDATE Subject SET enc_name = AES_ENCRYPT(:name, :enc_key) WHERE idSubject = :idSubject";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':enc_key', $key);
        $stmt->bindParam(':idSubject', $idSubject);
        $stmt->execute();

        $json = array(
            'status' => 'success',
            'message' => 'Name successfully set.'
        );
        return $json;

    } catch (PDOException $e) {
        logError($pathToErrorLog, "functions.php setName()", "Error setting name. idSubject: " . $idSubject . " name: " . $name . " - " . $stmt->errorInfo()[2]);
        $json = array(
            'status' => 'error',
            'message' => 'Error setting name.'
        );
        return $json;
    }
}

function sendMail($to, $recipientName, $fromAddress, $fromName, $subject, $preheader, $mailTitle, $messageBetweenHeaderAndFooter, $webroot, $pathToErrorLog)
{
    # Create email from parts
    # Variables in email-header template: $title, $preheader, $mailTitle, $name
    ob_start();
    include($webroot . '/private/resources/mail-templates/email-header.html');
    $mailContent = ob_get_clean();
    # add $messageBetweenHeaderAndFooter to $mailContent
    $mailContent .= $messageBetweenHeaderAndFooter;
    # add email-footer to $mailContent
    ob_start();
    include($webroot . '/private/resources/mail-templates/email-footer.html');
    $mailContent .= ob_get_clean();
    # Replace placeholders with actual values
    $mailContent = str_replace('$title', $mailTitle, $mailContent);
    $mailContent = str_replace('$preheader', $preheader, $mailContent);
    $mailContent = str_replace('$mailTitle', $mailTitle, $mailContent);
    $mailContent = str_replace('$name', $recipientName, $mailContent);

    # Set content-type header for sending HTML email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

    # Convert to quoted-printable encoding in order to support special characters
    # https://www.hagen-bauer.de/2015/09/gmx-webde-ptr-entry.html
    $encodedfromName = mb_encode_mimeheader($fromName, "UTF-8", "Q");
    $encodedRecipientName = mb_encode_mimeheader($recipientName, "UTF-8", "Q");
    $encodedSubject = mb_encode_mimeheader($subject, "UTF-8", "Q");

    # Additional headers
    $headers .= 'From: ' . $encodedfromName . ' <' . $fromAddress . '>' . "\r\n";
    $headers .= 'Reply-To: ' . $fromAddress . "\r\n";
    $headers .= 'Bcc: ' . $fromAddress . "\r\n";
    # Add Date header
    $headers .= 'Date: ' . date('r', $_SERVER['REQUEST_TIME']) . "\r\n";

    # Build recipient to prevent duplicate in header
    $to = $encodedRecipientName . ' <' . $to . '>';

    # Send email
    if (mail($to, $encodedSubject, $mailContent, $headers)) {
        # build JSON response
        $json = array(
            'status' => 'success',
            'message' => 'Email sent successfully.'
        );
        return $json;
    } else {
        # log error
        logError($pathToErrorLog, "sendMail() in functions.php", "Email transmission failed.");

        # build JSON response
        $json = array(
            'status' => 'error',
            'message' => 'Email transmission failed.'
        );
        return $json;
    }
}

# Get name of user with given email
function getNameViaMail(PDO $conn, $email, $encryptionKey, $pathToErrorLog)
{
    try {
        $sql = "SELECT AES_DECRYPT(enc_name, '$encryptionKey') AS name FROM Subject WHERE AES_ENCRYPT(?, '$encryptionKey') = enc_mail";
        $stmt = $conn->prepare($sql);
        $stmt->execute(array($email));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        # Build Json response
        $json = array(
            'status' => 'success',
            'message' => 'Name successfully retrieved.',
            'name' => $result['name']
        );
        return $json;
    } catch (PDOException $e) {
        # Append error to errorLog.txt located in ../private/errorLog.txt using dirname(__FILE__) to get the path of the current file
        logError($pathToErrorLog, "DBConnect.php getNameViaMail()", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error retrieving name.',
            'name' => ''
        );
        return $json;
    }
}

/**
 * Check if given survey is empty for given idAppUser. Return true if empty, false if not empty/or error.
 * @param int $idAppUser
 * @param PDO $pdo
 * @param int $surveynumber
 * @param string $pathToErrorLog
 * @return boolean
 */
function isSurveyEmptyForIDAppUser(int $idAppUser, PDO $pdo, int $surveynumber, string $pathToErrorLog)
{
    try {
        $sql = "SELECT COUNT(*) as count FROM UserAnsweredSurveyQuestion WHERE idUser = ? AND SurveyID = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($idAppUser, $surveynumber));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] == 0) {
            return true;
        } else if ($result['count'] == 1) {
            return false;
        } else {
            logError($pathToErrorLog, "isSurveyEmptyForIDAppUser() in DBConnect.php", "More than one entry for idAppUser: " . $idAppUser . " and surveynumber: " . $surveynumber);
            # TODO work on a better error handling
            return null; // Error
        }
    } catch (PDOException $e) {
        # Append error to errorLog.txt
        logError($pathToErrorLog, "isSurveyEmptyForIDAppUser() in DBConnect.php", $e->getMessage());
        return false;
    }
}

/**
 * Check the status of the given survey for the given user. Return aray with status and message and surveyStatus.
 * @param int $idAppUser
 * @param PDO $pdo
 * @param int $surveynumber
 * @param string $pathToErrorLog
 * @return array
 */
function timecheckerSurvey(int $idAppUser, PDO $pdo, int $surveynumber, string $pathToErrorLog)
{
    # Timeslots for Push Notifications
    # Survey 1:
    # - from first_login
    # - until first_login + 7 days
    # Survey 2:
    # - from first_login + 5 weeks
    # - until first_login + 6 weeks
    # Survey 3:
    # - from first_login + 10 weeks
    # - until first_login + 11 weeks
    # Survey 4 (only active, if survey 1 was not completed):
    # - from first_login + 10 weeks
    # - until first_login + 11 weeks + 3 days
    $surveyTimes = array(
        1 => array(
            'from' => 0,
            'until' => 604800
        ),
        2 => array(
            'from' => 3024000,
            'until' => 3628800
        ),
        3 => array(
            'from' => 6048000,
            'until' => 6652800
        ),
        4 => array(
            'from' => 6048000,
            'until' => 6912000
        )
    );

    $res = array(
        'status' => '',
        'message' => '',
        'surveyStatus' => ''
    );

    try {
        $firstLogin = getFirstLoginForIDAppUser($idAppUser, $pdo, $pathToErrorLog);
        if ($firstLogin['status'] == 'success') {
            if ($firstLogin['first_login'] != null) {
                # Convert firstLogin to timestamp
                $firstLogin = strtotime($firstLogin['first_login']);
                # Calculate difference between firstLogin and now
                $timeSinceFirstLogin = time() - $firstLogin;

                # Check if timeSinceFirstLogin is in range of surveyTimes[$surveynumber]['from'] and surveyTimes[$surveynumber]['until']
                if ($timeSinceFirstLogin >= $surveyTimes[$surveynumber]['from'] && $timeSinceFirstLogin <= $surveyTimes[$surveynumber]['until']) {
                    $res['status'] = 'success';
                    $res['message'] = 'Time for survey ' . $surveynumber . ' is correct.';
                    $res['surveyStatus'] = 'open';
                    return $res;
                } else if ($timeSinceFirstLogin < $surveyTimes[$surveynumber]['from']) {
                    $res['status'] = 'success';
                    $res['message'] = 'Time for survey ' . $surveynumber . ' is too early.';
                    $res['surveyStatus'] = 'notyet';
                    return $res;
                } else if ($timeSinceFirstLogin > $surveyTimes[$surveynumber]['until']) {
                    $res['status'] = 'success';
                    $res['message'] = 'Time for survey ' . $surveynumber . ' is too late.';
                    $res['surveyStatus'] = 'closed';
                    return $res;
                }

                logError($pathToErrorLog, "timecheckerSurvey() in DBConnect.php", "Error calculating firstLogin for idAppUser: " . $idAppUser);
                $res['status'] = 'error';
                $res['message'] = 'Error calculating firstLogin for idAppUser: ' . $idAppUser;
                $res['surveyStatus'] = '';
                return $res;
            } else if ($firstLogin['first_login'] == null) {

                $res['status'] = 'success';
                $res['message'] = 'User idAppUser: ' . $idAppUser . ' has no firstLogin.';
                $res['surveyStatus'] = '';
                return $res;
            }
        } else {
            logError($pathToErrorLog, "timecheckerSurvey() in DBConnect.php", "Error retrieving firstLogin for idAppUser: " . $idAppUser);
            $res['status'] = 'error';
            $res['message'] = 'Error retrieving firstLogin for idAppUser: ' . $idAppUser . ' - ' . $firstLogin['message'];
            return $res;
        }
    } catch (PDOException $e) {
        # Append error to errorLog.txt
        logError($pathToErrorLog, "timecheckerSurvey() in DBConnect.php", $e->getMessage());
        $res['status'] = 'error';
        $res['message'] = 'Error retrieving firstLogin for idAppUser: ' . $idAppUser . ' - ' . $e->getMessage();
        return $res;
    }
    # TODO check if necessary
    return $res;
}

/**
 * Get all open surveys for the given user.
 * @param int $idAppUser
 * @param PDO $pdo
 * @param string $pathToErrorLog
 * @return array with status, message, surveys(survey1, survey2, survey3, survey4 and end_of_study_reached (true or false))
 */
function getOpenSurveysForIDAppuser(int $idAppUser, PDO $pdo, string $pathToErrorLog)
{
    $res = array(
        "survey1" => false,
        "survey2" => false,
        "survey3" => false,
        "survey4" => false,
        "end_of_study_reached" => false
    );
    $surveyStatus = array(
        1 => array(
            'time' => null,
            'empty' => null
        ),
        2 => array(
            'time' => null,
            'empty' => null
        ),
        3 => array(
            'time' => null,
            'empty' => null
        ),
        4 => array(
            'time' => null,
            'empty' => null
        )
    );

    # Iterate from 1 to 4
    for ($i = 1; $i <= 4; $i++) {

        # Check if survey is open with respect to time
        $timechecker = timecheckerSurvey($idAppUser, $pdo, $i, $pathToErrorLog);
        # Check if survey has been answered already with isSurveyEmptyForIDAppUser()
        $surveyEmpty = isSurveyEmptyForIDAppUser($idAppUser, $pdo, $i, $pathToErrorLog);

        # If timechecker returns success save time and empty status in surveyStatus array
        if ($timechecker['status'] == 'success') {
            $surveyStatus[$i]['time'] = $timechecker['surveyStatus'];
            $surveyStatus[$i]['empty'] = $surveyEmpty;
        } else {
            logError($pathToErrorLog, "getOpenSurveysForIDAppuser() in DBConnect.php", "Error checking time for survey " . $i . " for idAppUser: " . $idAppUser . " - " . $timechecker['message']);
            return array(
                'status' => 'error',
                'message' => 'Error checking time for survey ' . $i . ' for idAppUser: ' . $idAppUser . ' - ' . $timechecker['message'],
                'surveys' => null
            );
        }
    }

    for ($i = 1; $i <= 3; $i++) {
        if ($surveyStatus[$i]['time'] == 'open' && $surveyStatus[$i]['empty']) {
            $res['survey' . $i] = true;
            break;
        }
    }

    if (
        $surveyStatus[4]['time'] == 'open'
        && $surveyStatus[4]['empty']
        && $surveyStatus[1]['empty']
        && ($surveyStatus[3]['empty'] == false || $surveyStatus[3]['time'] == 'closed')
    ) {
        $res['survey4'] = true;
    }

    # 1 not empty and 3 not empty
    # 1 not empty and 3 over
    # 1 empty and 4 not empty
    # 1 empty and 4 over
    if (
        ($surveyStatus[1]['empty'] == false && $surveyStatus[3]['empty'] == false)
        || ($surveyStatus[1]['empty'] == false && $surveyStatus[3]['time'] == 'closed')
        || ($surveyStatus[1]['empty'] && $surveyStatus[4]['empty'] == false)
        || ($surveyStatus[1]['empty'] && $surveyStatus[4]['time'] == 'closed')
    ) {
        $res['end_of_study_reached'] = true;
    }

    return array(
        'status' => 'success',
        'message' => 'Successfully retrieved open surveys for idAppUser: ' . $idAppUser,
        'surveys' => $res
    );
}

/**
 * Returns the registrationId stored in the AppUser table for the given idAppUser
 * @param int $idAppuser
 * @param PDO $pdo
 * @param string $pathToErrorLog
 * @return array registration_id
 */
function getRegistrationIdForIdAppuser($idAppuser, $pdo, $pathToErrorLog)
{
    try {
        $sql = "SELECT registration_id FROM AppUser WHERE idAppUser = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($idAppuser));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        # Check if result is empty
        if (empty($result)) {
            # Log error 
            logError($pathToErrorLog, "getRegistrationIdForIdAppuser() in functions.php", "No result for idAppUser: " . $idAppuser);
            # Build Json response
            $json = array(
                'status' => 'error',
                'message' => 'Error retrieving registration_id for idAppUser: ' . $idAppuser . '.',
            );
            return $json;
        }

        # Build Json response
        $json = array(
            'status' => 'success',
            'message' => 'registration_id successfully retrieved for idAppUser: ' . $idAppuser . '.',
            'registration_id' => $result['registration_id']
        );
        return $json;

    } catch (PDOException $e) {
        # Append error to errorLog.txt located in ../private/errorLog.txt using dirname(__FILE__) to get the path of the current file
        logError($pathToErrorLog, "getRegistrationIdForIdAppuser() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error retrieving registration_id for idAppUser: ' . $idAppuser . '',
            'registrationId' => ''
        );
        return $json;
    }
}

/**
 * Returns the push notifications status stored in the AppUser table for the given idAppUser
 * @param int $idAppuser
 * @param PDO $pdo
 * @param string $pathToErrorLog
 * @return array receivesPush
 */
function getPushNotificationsStatusForIdAppuser($idAppuser, $pdo, $pathToErrorLog)
{
    try {
        $sql = "SELECT receivesPush, registration_id FROM AppUser WHERE idAppUser = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($idAppuser));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        # Check if result is empty
        if (empty($result)) {
            # Log error 
            logError($pathToErrorLog, "getPushNotificationsStatusForIdAppuser() in functions.php", "No result for idAppUser: " . $idAppuser);
            # Build Json response
            $json = array(
                'status' => 'error',
                'message' => 'Error retrieving receivesPush for idAppUser: ' . $idAppuser . '.',
            );
            return $json;
        }

        # Build Json response
        $json = array(
            'status' => 'success',
            'message' => 'receivesPush successfully retrieved for idAppUser: ' . $idAppuser . '.',
            'receivesPush' => $result['receivesPush'],
            'registration_id' => $result['registration_id']
        );
        return $json;

    } catch (PDOException $e) {
        # Append error to errorLog.txt located in ../private/errorLog.txt using dirname(__FILE__) to get the path of the current file
        logError($pathToErrorLog, "getPushNotificationsStatusForIdAppuser() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error retrieving receivesPush for idAppUser: ' . $idAppuser . '',
        );
        return $json;
    }
}

/**
 * Return the datetime first_login stored in the AppUser table for the given idAppUser.
 * Returns error if user is not found.
 * Return must be checked for status = success and if first_login is NULL.
 * If first_login is NULL, the user has not logged in before.
 * @param int $idAppuser
 * @param PDO $pdo
 * @param string $pathToErrorLog
 * @return array
 */
function getFirstLogin(int $idAppuser, PDO $pdo, string $pathToErrorLog)
{
    try {
        $sql = "SELECT first_login FROM AppUser WHERE idAppUser = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($idAppuser));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        # Check if result is empty
        if (empty($result)) {
            # Log error 
            logError($pathToErrorLog, "getFirstLogin() in functions.php", "No result for idAppUser: " . $idAppuser);
            # Build Json response
            $json = array(
                'status' => 'error',
                'message' => 'Error retrieving firstLogin for idAppUser: ' . $idAppuser . '.',
            );
            return $json;
        }

        # Build Json response
        $json = array(
            'status' => 'success',
            'message' => 'firstLogin successfully retrieved for idAppUser: ' . $idAppuser . '.',
            'first_login' => $result['first_login']
        );
        return $json;

    } catch (PDOException $e) {
        # Append error to errorLog.txt located in ../private/errorLog.txt using dirname(__FILE__) to get the path of the current file
        logError($pathToErrorLog, "getFirstLogin() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error retrieving first_login for idAppUser: ' . $idAppuser . '',
        );
        return $json;
    }
}

/**
 * Return array including idUpload and timestamp for the last upload of the provided user.
 * @param int $idAppuser
 * @param PDO $pdo
 * @param string $pathToErrorLog
 * @return array
 */
function getLastUploadForIdAppuser(int $idAppuser, PDO $pdo, string $pathToErrorLog)
{
    try {
        $sql = "SELECT idUpload, timestamp FROM Upload WHERE timestamp = (SELECT MAX(timestamp) FROM Upload WHERE idUser = ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($idAppuser));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($result)) {
            # User has not uploaded any data yet
            $json = array(
                'status' => 'success',
                'message' => 'No lastUpload for idAppUser: ' . $idAppuser . '.',
                'idUpload' => '',
                'timestamp' => ''
            );
            return $json;
        } else {
            # TODO remove
            #logError($pathToErrorLog, "getLastUploadForIdAppuser() in functions.php", "timestamp=" . $result['timestamp']);

            # User has uploaded data before
            $json = array(
                'status' => 'success',
                'message' => 'LastUpload successfully retrieved for idAppUser: ' . $idAppuser . ' on ' . $result['timestamp'] . '.',
                'idUpload' => $result['idUpload'],
                'timestamp' => $result['timestamp']
            );
            return $json;
        }
    } catch (PDOException $e) {
        # Append error to errorLog.txt located in ../private/errorLog.txt using dirname(__FILE__) to get the path of the current file
        logError($pathToErrorLog, "getLastUploadForIdAppuser() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error retrieving lastUpload for idAppUser: ' . $idAppuser . '',
        );
        return $json;
    }

}

/**
 * Returns an array with the idPushNotification and timestamp of the last push notification sent to the given idAppUser and pushNotificationType.
 * Returns empty values if user has not received any push notifications yet. The status is still success.
 * @param int $idAppuser
 * @param int $pushNotificationType
 * @param PDO $pdo
 * @param string $pathToErrorLog
 * @return array<string>
 */
function getLastPushNotificationForIdAppuserAndPushNotificationType(int $idAppuser, int $pushNotificationType, PDO $pdo, string $pathToErrorLog)
{
    try {
        $sql = "SELECT idPushNotification, timestamp FROM Notifications WHERE timestamp = (SELECT MAX(timestamp) FROM Notifications WHERE idAppUser = ? AND idPushNotificationType = ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($idAppuser, $pushNotificationType));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($result)) {
            # User has not received any push notifications yet
            $json = array(
                'status' => 'success',
                'message' => 'No last push notification for idAppUser: ' . $idAppuser . ' and pushNotificationType: ' . $pushNotificationType . ' found.',
                'idPushNotification' => '',
                'timestamp' => ''
            );
            return $json;

        } else {
            # User has received push notifications before
            $json = array(
                'status' => 'success',
                'message' => 'Last push notification successfully retrieved for idAppUser: ' . $idAppuser . ' and pushNotificationType: ' . $pushNotificationType . ' on ' . $result['timestamp'] . '.',
                'idPushNotification' => $result['idPushNotification'],
                'timestamp' => $result['timestamp']
            );
            return $json;
        }

    } catch (PDOException $e) {
        # Append error to errorLog.txt located in ../private/errorLog.txt using dirname(__FILE__) to get the path of the current file
        logError($pathToErrorLog, "getLastPushNotificationForIdAppuserAndPushNotificationType() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error retrieving lastPushNotification for idAppUser: ' . $idAppuser . ' and pushNotificationType: ' . $pushNotificationType . '.',
        );
        return $json;
    }
}

/**
 * Returns all push notifications for the given idAppUser and pushNotificationType.
 * Returns empty array if user has not received any push notifications yet. The status is still success.
 * @param int $idAppuser
 * @param int $pushNotificationType
 * @param PDO $pdo
 * @param string $pathToErrorLog
 * @return array
 */
function getAllPushNotificationsForIDAppuserAndPushNotificationType(int $idAppuser, int $pushNotificationType, PDO $pdo, string $pathToErrorLog)
{
    try {
        $sql = "SELECT idPushNotification, timestamp FROM Notifications WHERE idAppUser = ? AND idPushNotificationType = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($idAppuser, $pushNotificationType));
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($result)) {
            # User has not received any push notifications yet
            $json = array(
                'status' => 'success',
                'message' => 'No push notifications for idAppUser: ' . $idAppuser . ' and pushNotificationType: ' . $pushNotificationType . ' found.',
                'notifications' => array()
            );
            return $json;

        } else {
            # Build array from $result
            $notifications = array();
            foreach ($result as $row) {
                $notification = array(
                    'idPushNotification' => $row['idPushNotification'],
                    'timestamp' => $row['timestamp']
                );
                array_push($notifications, $notification);
            }

            # User has received push notifications before
            $json = array(
                'status' => 'success',
                'message' => 'Push notifications successfully retrieved for idAppUser: ' . $idAppuser . ' and pushNotificationType: ' . $pushNotificationType . '.',
                'notifications' => $notifications
            );
            return $json;
        }

    } catch (PDOException $e) {
        logError($pathToErrorLog, "getAllPushNotificationsForIDAppuserAndPushNotificationType() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error retrieving push notifications for idAppUser: ' . $idAppuser . ' and pushNotificationType: ' . $pushNotificationType . '.',
            'notifications' => array()
        );
        return $json;
    }
}

/**
 * Returns the idSubject for the given idAppUser.
 * @param int $idAppuser
 * @param PDO $pdo
 * @param string $pathToErrorLog
 * @return array
 */
function getIdSubjectForIdAppuser(int $idAppuser, PDO $pdo, string $pathToErrorLog)
{
    try {
        $sql = "SELECT idSubject FROM AppUser WHERE idAppUser = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($idAppuser));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($result)) {
            # There exists no user with this idAppUser (or there is an inconsistency in the database)
            $json = array(
                'status' => 'error',
                'message' => 'There exists no user with this idAppUser (or there is an inconsistency in the database) idAppUser: ' . $idAppuser . '.',
                'idSubject' => ''
            );
            return $json;
        } else {
            # User exists
            $json = array(
                'status' => 'success',
                'message' => 'idSubject successfully retrieved for idAppUser: ' . $idAppuser . '.',
                'idSubject' => $result['idSubject']
            );
            return $json;
        }

    } catch (PDOException $e) {
        # Append error to errorLog.txt located in ../private/errorLog.txt using dirname(__FILE__) to get the path of the current file
        logError($pathToErrorLog, "getIdSubjectForIdAppuser() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error retrieving idSubject for idAppUser: ' . $idAppuser . '',
        );
        return $json;
    }
}

/**
 * Returns the IBAN for the given idSubject.
 * If IBAN is not set, returns an empty string.
 * @param int $idSubject
 * @param string $encryptionKey
 * @param PDO $pdo
 * @param string $pathToErrorLog
 * @return array
 */
function getIBANForIDSubject(int $idSubject, string $encryptionKey, PDO $pdo, string $pathToErrorLog)
{
    try {
        $sql = "SELECT AES_DECRYPT(enc_iban, '$encryptionKey') as iban FROM Subject WHERE idSubject = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($idSubject));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($result)) {
            # There exists no user with this idSubject (or there is an inconsistency in the database)
            $json = array(
                'status' => 'error',
                'message' => 'There exists no user with this idSubject (or there is an inconsistency in the database) idAppUser: ' . $idSubject . '.',
                'iban' => ''
            );
            return $json;
        } else {
            # User exists
            $json = array(
                'status' => 'success',
                'message' => 'IBAN successfully retrieved for idSubject: ' . $idSubject . '.',
                'iban' => $result['iban']
            );
            return $json;
        }

    } catch (PDOException $e) {
        logError($pathToErrorLog, "getIBANForIDSubject() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error retrieving IBAN for idSubject: ' . $idSubject . '',
        );
        return $json;
    }
}

/**
 * Returns the mail for the given idSubject.
 * @param int $idSubject
 * @param string $encryptionKey
 * @param PDO $pdo
 * @param string $pathToErrorLog
 * @return array
 */
function getMailForIDSubject(int $idSubject, string $encryptionKey, PDO $pdo, string $pathToErrorLog)
{
    try {
        $sql = "SELECT AES_DECRYPT(enc_mail, '$encryptionKey') as mail FROM Subject WHERE idSubject = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($idSubject));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($result)) {
            # There exists no user with this idSubject (or there is an inconsistency in the database)
            $json = array(
                'status' => 'error',
                'message' => 'There exists no user with this idSubject (or there is an inconsistency in the database) idAppUser: ' . $idSubject . '.',
                'mail' => ''
            );
            return $json;
        } else {
            # User exists
            $json = array(
                'status' => 'success',
                'message' => 'Mail successfully retrieved for idSubject: ' . $idSubject . '.',
                'mail' => $result['mail']
            );
            return $json;
        }
    } catch (PDOException $e) {
        logError($pathToErrorLog, "getMailForIDSubject() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error retrieving Mail for idSubject: ' . $idSubject . '',
        );
        return $json;
    }
}

/**
 * Returns the first_login for the given idAppUser as a string in an array.
 * If first_login is not set, returns an empty string.
 * @param int $idAppuser
 * @param PDO $pdo
 * @param string $pathToErrorLog
 * @return array 
 */
function getFirstLoginForIdAppuser(int $idAppuser, PDO $pdo, string $pathToErrorLog)
{
    try {
        $sql = "SELECT first_login FROM AppUser WHERE idAppUser = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($idAppuser));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($result)) {
            # There exists no user with this idAppUser (or there is an inconsistency in the database)
            $json = array(
                'status' => 'error',
                'message' => 'There exists no user with this idAppUser (or there is an inconsistency in the database) idAppUser: ' . $idAppuser . '.',
                'first_login' => ''
            );
            return $json;
        } else {
            # User exists
            $json = array(
                'status' => 'success',
                'message' => 'firstLogin successfully retrieved for idAppUser: ' . $idAppuser . '.',
                'first_login' => $result['first_login']
            );
            return $json;
        }

    } catch (PDOException $e) {
        logError($pathToErrorLog, "getFirstLoginForIdAppuser() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error retrieving firstLogin for idAppUser: ' . $idAppuser . '.',
        );
        return $json;
    }
}

/**
 * Returns idAppUser, idSubject, first_login and last_online for all AppUsers as an array within an array.
 * @param DateTime $startDate
 * @param DateTime $endDate
 * @param PDO $pdo
 * @param string $pathToErrorLog
 * @return array
 */
function getAppUsers(DateTime $startDate, DateTime $endDate, PDO $pdo, string $pathToErrorLog)
{
    # Convert DateTime for use in SQL query
    $startDate = $startDate->format('Y-m-d');
    $endDate = $endDate->format('Y-m-d');

    try {
        $sql = "SELECT idAppUser, idSubject, first_login, last_online FROM AppUser WHERE creation_timestamp BETWEEN :startDate AND :endDate";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':startDate', $startDate);
        $stmt->bindParam(':endDate', $endDate);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($result)) {
            # There exist no surveys for this idAppUser (or there is an inconsistency in the database)
            $json = array(
                'status' => 'success',
                'message' => 'There exists no AppUser within the given registration period or in general (or there is an inconsistency in the database).',
                'appUsers' => array()
            );
            return $json;
        } else {
            # survey for user exists
            $json = array(
                'status' => 'success',
                'message' => 'AppUsers successfully retrieved.',
                'appUsers' => $result
            );
            return $json;
        }

    } catch (PDOException $e) {
        logError($pathToErrorLog, "getAppUsers() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error retrieving AppUsers.',
        );
        return $json;
    }
}

/**
 * Returns the SubjectIdentifier/Pseudonym for the given idSubject as a string in an array.
 * @param int $idSubject
 * @param PDO $pdo
 * @param string $pathToErrorLog
 * @return array
 */
function getPseudonymForIDSubject(int $idSubject, PDO $pdo, string $pathToErrorLog)
{
    try {
        $sql = "SELECT SubjectIdentifier FROM Subject WHERE idSubject = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($idSubject));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($result)) {
            # There exists no entry for this idSubject (or there is an inconsistency in the database)
            $json = array(
                'status' => 'success',
                'message' => 'There exists no entry for this idSubject (or there is an inconsistency in the database).',
                'pseudonym' => ''
            );
            return $json;
        } else {
            # Subject exists
            $json = array(
                'status' => 'success',
                'message' => 'Pseudonym successfully retrieved.',
                'pseudonym' => $result['SubjectIdentifier']
            );
            return $json;
        }
    } catch (PDOException $e) {
        logError($pathToErrorLog, "getPseudonymForIDSubject() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error retrieving pseudonym for idSubject: ' . $idSubject . '.',
        );
        return $json;
    }
}

/**
 * Returns the number of uploads for the given idAppUser as an int in an array.
 * This does only include entries in the DiaryEntry table to avoid counting voice memos that have been paired with a diary entry.
 * @param int $idAppUser
 * @param PDO $pdo
 * @param string $pathToErrorLog
 * @return array
 */
function getNumberOfUploadsForIdAppUser(int $idAppUser, PDO $pdo, string $pathToErrorLog)
{
    try {
        $sql = "SELECT COUNT(idDiaryEntry) as uploadCount FROM DiaryEntry  WHERE idAppUser = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($idAppUser));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($result)) {
            # There exists no entry for this idSubject (or there is an inconsistency in the database)
            $json = array(
                'status' => 'success',
                'message' => 'There exists no entry for this idSubject (or there is an inconsistency in the database).',
                'uploadCount' => 0
            );
            return $json;
        } else {
            # Subject exists
            $json = array(
                'status' => 'success',
                'message' => 'Upload count successfully retrieved.',
                'uploadCount' => $result['uploadCount']
            );
            return $json;
        }
    } catch (PDOException $e) {
        logError($pathToErrorLog, "getNumberOfUploadsForIdAppUser() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error retrieving upload count for idAppUser: ' . $idAppUser . '.',
        );
        return $json;
    }
}

/**
 * Returns the number of surveys for the given idAppUser as an int in an array.
 * Does not allow to calculate, which surveys have been answered by the user.
 * @param int $idAppUser
 * @param PDO $pdo
 * @param string $pathToErrorLog
 * @return array
 */
function getNumberOfSurveysForIdAppUser(int $idAppUser, PDO $pdo, string $pathToErrorLog)
{
    try {
        $sql = "SELECT COUNT(idUserAnswer) as surveyCount FROM UserAnsweredSurveyQuestion WHERE idUser = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($idAppUser));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($result)) {
            # There exists no entry for this idSubject (or there is an inconsistency in the database)
            $json = array(
                'status' => 'success',
                'message' => 'There exists no entry for this idSubject (or there is an inconsistency in the database).',
                'surveyCount' => 0
            );
            return $json;
        } else {
            # Subject exists
            $json = array(
                'status' => 'success',
                'message' => 'Survey count successfully retrieved.',
                'surveyCount' => $result['surveyCount']
            );
            return $json;
        }
    } catch (PDOException $e) {
        logError($pathToErrorLog, "getNumberOfSurveysForIdAppUser() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error retrieving survey count for idAppUser: ' . $idAppUser . '.',
        );
        return $json;
    }
}

/**
 * Returns the surveys for the given idAppUser as an array.
 * @param int $idAppUser
 * @param PDO $pdo
 * @param string $pathToErrorLog
 * @return array
 */
function getSurveysForIdAppUser(int $idAppUser, PDO $pdo, string $pathToErrorLog)
{
    try {
        $sql = "SELECT idUserAnswer as databaseID, SurveyID as surveyID, Answer as surveyAnswers, timestamp as finished FROM UserAnsweredSurveyQuestion WHERE idUser = ? ORDER BY `finished` ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($idAppUser));
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);


        if (empty($result)) {
            # There exists no entry for this idSubject (or there is an inconsistency in the database)
            $json = array(
                'status' => 'success',
                'message' => 'There exists no entry for this idSubject (or there is an inconsistency in the database).',
                'surveys' => ''
            );
            return $json;
        } else {
            $arr = [];

            for ($i = 0; $i < count($result); $i++) {
                $arr[$i]["SurveyID"] = $result[$i]['surveyID'];
                $arr[$i]["finished"] = $result[$i]['finished'];
                $arr[$i]["surveyAnswers"] = json_decode($result[$i]['surveyAnswers']);
            }
            # Subject exists
            $json = array(
                'status' => 'success',
                'message' => 'Surveys successfully retrieved.',
                'surveys' => $arr
            );
            return $json;
        }
    } catch (PDOException $e) {
        logError($pathToErrorLog, "getSurveysForIdAppUser() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error retrieving surveys for idAppUser: ' . $idAppUser . '.',
        );
        return $json;
    }
}

/**
 * Returns the diary entries for the given idAppUser as an array (array is empty if there are no entries).
 * @param int $idAppUser
 * @param PDO $pdo
 * @param string $pathToErrorLog
 * @return array
 */
function getDiaryEntriesForIdAppUser(int $idAppUser, PDO $pdo, string $pathToErrorLog)
{
    try {
        $sql =
            "SELECT 
            d.idDiaryEntry as 'diaryEntry_ID',
            d.idUpload as 'baseFile_ID',
            t.TypeDescription as 'baseFile_UploadType',
            u.path as 'baseFile_Path',
            u.timestamp as 'baseFile_Timestamp',
            d.entry as 'diaryEntry_Questions',
            d.timestamp as 'diaryEntry_Timestamp',
            d.idUploadAdditionalInformation as 'additionalInfoFile_ID',
            tt.TypeDescription as 'additionalInfoFile_UploadType',
            uu.path as 'additionalInfoFile_Path',
            uu.timestamp as 'additionalInfoFile_Timestamp'
        FROM DiaryEntry d
        JOIN Upload u
            ON d.idUpload=u.idUpload
        LEFT JOIN UploadType t
            ON u.uploadType=t.idUploadType
        LEFT JOIN Upload uu
            ON d.idUploadAdditionalInformation=uu.idUpload
        LEFT JOIN UploadType tt
            ON uu.uploadType=tt.idUploadType
        WHERE d.idAppUser = ?
        ORDER BY d.timestamp ASC;";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($idAppUser));
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($result)) {
            # There exists no entry for this idSubject (or there is an inconsistency in the database)
            $json = array(
                'status' => 'success',
                'message' => 'There exists no entry for this idSubject (or there is an inconsistency in the database).',
                'diaryEntries' => array()
            );
            return $json;
        } else {
            # Subject exists
            $json = array(
                'status' => 'success',
                'message' => 'Diary entries successfully retrieved.',
                'diaryEntries' => $result
            );
            return $json;
        }
    } catch (PDOException $e) {
        logError($pathToErrorLog, "getDiaryEntriesForIdAppUser() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error retrieving diary entries for idAppUser: ' . $idAppUser . '.',
        );
        return $json;
    }
}

/**
 * Returns the creation date of the account for the given idAppUser as an array.
 * @param mixed $idAppuser
 * @param mixed $pdo
 * @param mixed $pathToErrorLog
 * @return array
 */
function getCreationDateOfAccount(int $idAppuser, PDO $pdo, string $pathToErrorLog)
{
    try {
        $sql = "SELECT creation_timestamp FROM AppUser WHERE idAppUser = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($idAppuser));
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($result)) {
            # There exists no entry for this idSubject (or there is an inconsistency in the database)
            $json = array(
                'status' => 'success',
                'message' => 'There exists no entry for this idSubject (or there is an inconsistency in the database).',
                'creation_timestamp' => ''
            );
            return $json;
        } else {
            # Subject exists
            $json = array(
                'status' => 'success',
                'message' => 'Creation date successfully retrieved.',
                'creation_timestamp' => $result[0]['creation_timestamp']
            );
            return $json;
        }
    } catch (PDOException $e) {
        logError($pathToErrorLog, "getCreationDateOfAccount() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error retrieving creation date of account for idAppUser: ' . $idAppuser . '.',
        );
        return $json;
    }
}

function getEmailAddressAndNameOfIDAppuser(int $idAppuser, string $encryptionKey, PDO $pdo, string $pathToErrorLog)
{
    try {
        $sql =
            "SELECT
                AES_DECRYPT(enc_name, '$encryptionKey') AS name,
                AES_DECRYPT(enc_mail, '$encryptionKey') AS email
            FROM
                AppUser a
            LEFT JOIN Subject s ON
                a.idSubject = s.idSubject
            WHERE
                idAppUser = ?;";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($idAppuser));
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        #TODO check for more than 1 row as error-handling

        if (empty($result)) {
            # There exists no entry for this idSubject (or there is an inconsistency in the database)
            $json = array(
                'status' => 'success',
                'message' => 'There exists no entry for this idSubject (or there is an inconsistency in the database).',
                'email' => '',
                'name' => ''
            );
            return $json;
        } else {
            # Subject exists
            $json = array(
                'status' => 'success',
                'message' => 'Email address and name successfully retrieved.',
                'email' => $result[0]['email'],
                'name' => $result[0]['name']
            );
            return $json;
        }
    } catch (PDOException $e) {
        logError($pathToErrorLog, "getEmailAddressAndNameOfIDAppuser() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error retrieving email address and name of account for idAppUser: ' . $idAppuser . '.',
        );
        return $json;
    }
}

/**
 * Returns success, if the app user has been reset to the initial state.
 * Keeps the account as well as the password and creation date!
 * 
 * @param int $idAppuser
 * @param PDO $pdo
 * @param string $basepath (e.g. /var/www/html)
 * @param string $pathToErrorLog
 * @return array
 */
function resetAppUser(int $idAppuser, PDO $pdo, string $basepath, string $pathToErrorLog)
{

    # Delete DiaryEntries first (because of foreign key constraint)
    try {
        $sql = "DELETE FROM DiaryEntry WHERE idAppUser = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($idAppuser));
        # Continue
    } catch (PDOException $e) {
        logError($pathToErrorLog, "resetAppUser() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error deleting DiaryEntry for idAppUser: ' . $idAppuser . '.',
        );
        return $json;
    }

    # Delete Uploads
    try {
        $sql = "DELETE FROM Upload WHERE idUser = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($idAppuser));
        # Continue
    } catch (PDOException $e) {
        logError($pathToErrorLog, "resetAppUser() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error deleting Upload for idAppUser: ' . $idAppuser . '.',
        );
        return $json;
    }

    # Delete files on server
    $pathToUploads = $basepath . '/private/uploads/' . $idAppuser;

    # Check if folder for idAppUser exists in private/uploads
    if (is_dir($pathToUploads)) {
        if (deleteFolderAndFiles($pathToUploads)['status'] == 'error') {
            logError($pathToErrorLog, "resetAppUser() in functions.php", "Error deleting files on server for idAppUser: " . $idAppuser . ".");
            # Build Json response
            $json = array(
                'status' => 'error',
                'message' => 'Error deleting files on server for idAppUser: ' . $idAppuser . '.',
            );
            return $json;
        }
    }

    # Delete SurveyAnswers
    try {
        $sql = "DELETE FROM UserAnsweredSurveyQuestion WHERE idUser = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($idAppuser));
        # Continue
    } catch (PDOException $e) {
        logError($pathToErrorLog, "resetAppUser() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error deleting UserAnsweredSurveyQuestion for idAppUser: ' . $idAppuser . '.',
        );
        return $json;
    }

    # Delete Notifications
    try {
        $sql = "DELETE FROM Notifications WHERE idAppUser = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($idAppuser));
        # Continue
    } catch (PDOException $e) {
        logError($pathToErrorLog, "resetAppUser() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error deleting Notifications for idAppUser: ' . $idAppuser . '.',
        );
        return $json;
    }

    # Modify entry in AppUser: set enc_token to NULL, first_login to NULL, last_online to NULL, registration_id to NULL, receivesPush to 0
    # Keeping password
    try {
        $sql = "UPDATE AppUser SET enc_token = NULL, first_login = NULL, last_online = NULL, registration_id = NULL, receivesPush = 0 WHERE idAppUser = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array($idAppuser));
        # Continue
    } catch (PDOException $e) {
        logError($pathToErrorLog, "resetAppUser() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error resetting AppUser for idAppUser: ' . $idAppuser . '.',
        );
        return $json;
    }

    # Build Json response
    $json = array(
        'status' => 'success',
        'message' => 'AppUser successfully reset. idAppUser: ' . $idAppuser . '.',
    );
    return $json;
}

function deleteFolderAndFiles($pathToFolder)
{
    $files = glob($pathToFolder . '/*'); // get all file names
    foreach ($files as $file) { // iterate files
        if (is_file($file)) {
            if (!unlink($file)) { // delete file
                return array(
                    'status' => 'error',
                    'message' => 'Error deleting file: ' . $file . '.',
                );
            }
        }
        # Delete subfolders
        else if (is_dir($file)) {
            if (deleteFolderAndFiles($file)['status'] == 'error') {
                return array(
                    'status' => 'error',
                    'message' => 'Error deleting folder: ' . $file . '.',
                );
            }
        }
    }
    if (!rmdir($pathToFolder)) {
        return array(
            'status' => 'error',
            'message' => 'Error deleting folder: ' . $pathToFolder . '.',
        );
    }
    return array(
        'status' => 'success',
        'message' => 'Folder and files successfully deleted.',
    );
}

/**
 * Function to get the participant information (name, email, iban) by pseudonym
 * Asserts that only one participant is found for the given pseudonym
 * @param string $pseudonym
 * @param string $encryptionKey
 * @param PDO $pdo
 * @param string $pathToErrorLog
 * @return array name, email, iban
 */
function getParticipantInformationByPseudonym(string $pseudonym, string $encryptionKey, PDO $pdo, string $pathToErrorLog)
{
    # Get decrypted name, e-mail and IBAN by pseudonym
    try {
        $sql = "SELECT idSubject, AES_DECRYPT(enc_name, :encryptionKey) AS name, AES_DECRYPT(enc_mail, :encryptionKey) AS email, AES_DECRYPT(enc_iban, :encryptionKey) AS iban, app, interview FROM Subject WHERE SubjectIdentifier = :pseudonym";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':encryptionKey', $encryptionKey, PDO::PARAM_STR);
        $stmt->bindParam(':pseudonym', $pseudonym, PDO::PARAM_STR);
        $stmt->execute();

        # Build Json response
        $json = array(
            'status' => 'success',
            'message' => 'Participant information successfully retrieved.',
            'data' => $stmt->fetch(PDO::FETCH_ASSOC),
        );
        return $json;

    } catch (PDOException $e) {
        logError($pathToErrorLog, "getParticipantInformationByPseudonym() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error getting participant information by pseudonym: ' . $pseudonym . '.',
        );
        return $json;
    }
}

/**
 * Function to get the participant information (name, email, iban) by pseudonym
 * Asserts that only one participant is found for the given pseudonym
 * @param int $idAppUser
 * @param string $encryptionKey
 * @param PDO $pdo
 * @param string $pathToErrorLog
 * @return array name, email, data: idAppUser
 */
function getIDAppUserByPseudonym(string $pseudonym, PDO $pdo, string $pathToErrorLog)
{
    # Get idAppUser by pseudonym
    try {
        $sql = "SELECT idAppUser FROM AppUser WHERE idSubject = (SELECT idSubject FROM Subject WHERE SubjectIdentifier = :pseudonym)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':pseudonym', $pseudonym, PDO::PARAM_STR);
        $stmt->execute();
        $idAppUser = $stmt->fetch(PDO::FETCH_ASSOC)['idAppUser'];

        $json = array(
            'status' => 'success',
            'message' => 'idAppUser successfully retrieved.',
            'data' => $idAppUser,
        );
        return $json;
    } catch (PDOException $e) {
        logError($pathToErrorLog, "getIDAppUserByPseudonym() in functions.php", $e->getMessage());
        $json = array(
            'status' => 'error',
            'message' => 'Error getting idAppUser by pseudonym: ' . $pseudonym . '.',
        );
        return $json;
    }
}

/**
 * Function to get the payments by pseudonym
 * @param string $pseudonym
 * @param string $encryptionKey
 * @param PDO $pdo
 * @param string $pathToErrorLog
 * @return array status, message, data: array of payments (paymentID, name, email, iban, paymentListID, status, additionalText, created, modified)
 */
function getPaymentsByPseudonym(string $pseudonym, string $encryptionKey, PDO $pdo, string $pathToErrorLog)
{
    # Get subject id by pseudonym
    try {
        $sql = "SELECT idSubject FROM Subject WHERE SubjectIdentifier = :pseudonym";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':pseudonym', $pseudonym, PDO::PARAM_STR);
        $stmt->execute();
        $idSubject = $stmt->fetch(PDO::FETCH_ASSOC)['idSubject'];
    } catch (PDOException $e) {
        logError($pathToErrorLog, "getPaymentsByPseudonym() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error getting subject id by pseudonym: ' . $pseudonym . '.',
        );
        return $json;
    }

    # Get payments by subject id
    try {
        $sql = "SELECT paymentID, AES_DECRYPT(enc_name, :encryptionKey) as name, AES_DECRYPT(enc_email, :encryptionKey) as email, AES_DECRYPT(enc_iban, :encryptionKey) as iban, paymentListID, ist.statusTypeDescription, additionalText, created, modified FROM Incentive_Payment as ip LEFT JOIN Incentive_StatusType as ist ON ip.statusTypeID=ist.statusTypeID WHERE subjectID = :subjectID";
        $stmt = $pdo->prepare($sql);

        $stmt->bindParam(':encryptionKey', $encryptionKey, PDO::PARAM_STR);
        $stmt->bindParam(':subjectID', $idSubject, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        # iterate over rows and echo
        foreach ($rows as $key => $row) {
            # Get payment positions by payment id
            $rows[$key]['paymentPositions'] = getPaymentPositionsByPaymentID($row['paymentID'], $pdo, $pathToErrorLog)['data'];
        }

        # Build Json response
        $json = array(
            'status' => 'success',
            'message' => 'Payments successfully retrieved.',
            'data' => $rows,
        );
        return $json;
    } catch (PDOException $e) {
        logError($pathToErrorLog, "getPaymentsByPseudonym() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error getting payments by pseudonym: ' . $pseudonym . '.',
        );
        return $json;
    }
}

/**
 * Function to get the payment positions by payment id 
 * @param int $paymentID
 * @param PDO $pdo
 * @param string $pathToErrorLog
 * @return array status, message, data: array of payment positions (positionID, paymentID, paymentTypeDescription, amount, additionalText, created, modified)
 */
function getPaymentPositionsByPaymentID(int $paymentID, PDO $pdo, string $pathToErrorLog)
{
    try {
        $sql = "SELECT positionID, paymentID, ipt.paymentTypeDescription, amount, additionalText, created, modified FROM Incentive_Position as ip LEFT JOIN Incentive_PaymentType as ipt ON ip.paymentTypeID=ipt.paymentTypeID WHERE paymentID = :paymentID";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':paymentID', $paymentID, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll();
        # Build Json response
        $json = array(
            'status' => 'success',
            'message' => 'Payment positions successfully retrieved.',
            'data' => $result,
        );
        return $json;
    } catch (PDOException $e) {
        logError($pathToErrorLog, "getPaymentPositionsByPaymentID() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error getting payment positions by paymentID: ' . $paymentID . '.',
        );
        return $json;
    }
}

/**
 * Function to get all open payments
 * @param PDO $pdo
 * @param string $pathToErrorLog
 * @return array status, message, data: array of payments (paymentID, subjectID, name, email, iban, paymentListID, statusID, statusDescription, additionalText, created, modified)
 */
function getAllOpenPayments(PDO $pdo, string $pathToErrorLog)
{
    try {
        $sql = "SELECT paymentID, subjectID, AES_DECRYPT(enc_name, :encryptionKey) as name, AES_DECRYPT(enc_email, :encryptionKey) as email, AES_DECRYPT(enc_iban, :encryptionKey) as iban, paymentListID, ip.statusTypeID as statusID, ist.statusTypeDescription as statusDescription, additionalText, created, modified FROM Incentive_Payment as ip LEFT JOIN Incentive_StatusType as ist ON ip.statusTypeID=ist.statusTypeID WHERE ip.statusTypeID = 1 OR ip.statusTypeID = 5";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':encryptionKey', $encryptionKey, PDO::PARAM_STR);
        $stmt->execute();

        # Build Json response
        $json = array(
            'status' => 'success',
            'message' => 'Open payments successfully retrieved.',
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        );
        return $json;
    } catch (PDOException $e) {
        logError($pathToErrorLog, "getAllOpenPayments() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error getting all open payments.',
        );
        return $json;
    }
}

/**
 * Function to create a new open payment for a subject
 * @param int $idSubject
 * @param string $name
 * @param string $email
 * @param string $iban
 * @param string $additionalText
 * @param string $encryptionKey
 * @param PDO $pdo
 * @param string $pathToErrorLog
 * @return array status, message, data: array of payments (paymentID, subjectID, name, email, iban, paymentListID, statusID, statusDescription, additionalText, created, modified)
 */
function createNewOpenPayment(int $idSubject, string $name, string $email, string $iban, string $additionalText, string $encryptionKey, PDO $pdo, string $pathToErrorLog, bool $ibanAvailable = true)
{
    if (!$ibanAvailable) {
        $statusTypeID = 5;
    } else {
        $statusTypeID = 1;
    }
    try {
        $sql = "INSERT INTO Incentive_Payment (subjectID, enc_name, enc_email, enc_iban, statusTypeID, additionalText) VALUES (:subjectID, AES_ENCRYPT(:name, :encryptionKey), AES_ENCRYPT(:email, :encryptionKey), AES_ENCRYPT(:iban, :encryptionKey), :statusTypeID, :additionalText)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':subjectID', $idSubject, PDO::PARAM_INT);
        $stmt->bindParam(':encryptionKey', $encryptionKey, PDO::PARAM_STR);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':iban', $iban, PDO::PARAM_STR);
        $stmt->bindParam(':statusTypeID', $statusTypeID, PDO::PARAM_INT);
        $stmt->bindParam(':additionalText', $additionalText, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll();
        # Get last inserted id
        $lastInsertID = $pdo->lastInsertId();

        # Build Json response
        $json = array(
            'status' => 'success',
            'message' => 'New open payment successfully created.',
            'data' => array(
                'paymentID' => $lastInsertID,
                'result' => $result,
            )
        );
        return $json;
    } catch (PDOException $e) {
        logError($pathToErrorLog, "createNewOpenPayment() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error creating new open payment.',
        );
        return $json;
    }
}

/**
 * Function to update an open payment
 * @param int $openPaymentID
 * @param string $name
 * @param string $email
 * @param string $iban
 * @param string $additionalText
 * @param string $encryptionKey
 * @param PDO $pdo
 * @param string $pathToErrorLog
 * @return array status, message
 */
function updateOpenPayment(int $openPaymentID, string $name, string $email, string $iban, string $additionalText, string $encryptionKey, PDO $pdo, string $pathToErrorLog, bool $ibanAvailable = true)
{
    if (!$ibanAvailable) {
        $statusTypeID = 5;
    } else {
        $statusTypeID = 1;
    }
    try {
        $sql = "UPDATE Incentive_Payment SET enc_name = AES_ENCRYPT(:name, :encryptionKey), enc_email = AES_ENCRYPT(:email, :encryptionKey), enc_iban = AES_ENCRYPT(:iban, :encryptionKey), statusTypeID = :statusTypeID, additionalText = :additionalText WHERE paymentID = :paymentID";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':encryptionKey', $encryptionKey, PDO::PARAM_STR);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':iban', $iban, PDO::PARAM_STR);
        $stmt->bindParam(':statusTypeID', $statusTypeID, PDO::PARAM_INT);
        $stmt->bindParam(':additionalText', $additionalText, PDO::PARAM_STR);
        $stmt->bindParam(':paymentID', $openPaymentID, PDO::PARAM_INT);
        $stmt->execute();

        # Build Json response
        $json = array(
            'status' => 'success',
            'message' => 'Open payment successfully updated.',
        );
        return $json;
    } catch (PDOException $e) {
        logError($pathToErrorLog, "updateOpenPayment() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error updating open payment.',
        );
        return $json;
    }
}

/**
 * Function to create a new payment position
 * @param int $paymentID
 * @param int $paymentTypeID
 * @param float $amount
 * @param string $additionalText
 * @param PDO $pdo
 * @param string $pathToErrorLog
 * @return array status, message, data: array of payments (positionID, paymentID, paymentTypeID, amount, additionalText, created, modified)
 */
function createNewPaymentPosition(int $paymentID, int $paymentTypeID, float $amount, string $additionalText, PDO $pdo, string $pathToErrorLog)
{
    try {
        $sql = "INSERT INTO Incentive_Position (paymentID, paymentTypeID, amount, additionalText) VALUES (:paymentID, :paymentTypeID, :amount, :additionalText)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':paymentID', $paymentID, PDO::PARAM_INT);
        $stmt->bindParam(':paymentTypeID', $paymentTypeID, PDO::PARAM_INT);
        $stmt->bindParam(':amount', $amount, PDO::PARAM_STR);
        $stmt->bindParam(':additionalText', $additionalText, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll();
        # Build Json response
        $json = array(
            'status' => 'success',
            'message' => 'New payment position successfully created.',
            'data' => $result,
        );
        return $json;
    } catch (PDOException $e) {
        logError($pathToErrorLog, "createNewPaymentPosition() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error creating new payment position.',
        );
        return $json;
    }
}

/**
 * Function to get the total amount paid and the total amount with missing IBANs
 * @param PDO $pdo
 * @param string $pathToErrorLog
 * @return array status, message, data: array of payments (total_amount_paid, total_amount_iban_missing)
 */
function getPaymentTotals(PDO $pdo, string $pathToErrorLog)
{
    try {
        $sql = "SELECT SUM(ipos.amount) AS total_amount_paid FROM Incentive_Payment ip JOIN Incentive_Position ipos ON ip.paymentID = ipos.paymentID WHERE ip.statusTypeID = 3";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $total_amount_paid = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount_paid'];

        $sql = "SELECT SUM(ipos.amount) AS total_amount_iban_missing FROM Incentive_Payment ip JOIN Incentive_Position ipos ON ip.paymentID = ipos.paymentID WHERE ip.statusTypeID = 5";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $total_amount_iban_missing = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount_iban_missing'];

        # Build Json response
        $json = array(
            'status' => 'success',
            'message' => 'Payment totals successfully retrieved.',
            'data' => array(
                'total_amount_paid' => $total_amount_paid,
                'total_amount_iban_missing' => $total_amount_iban_missing,
            ),
        );
        return $json;
    } catch (PDOException $e) {
        logError($pathToErrorLog, "getPaymentTotals() in functions.php", $e->getMessage());

        # Build Json response
        $json = array(
            'status' => 'error',
            'message' => 'Error getting payment totals.',
        );
        return $json;
    }
}

/**
 * log error to errorLog.txt in ../private/errorLog.txt
 * @param mixed $pathToErrorLog
 * @param mixed $errorGeneratingComponent
 * @param mixed $message
 * @return void
 */
function logError($pathToErrorLog, $errorGeneratingComponent, $message)
{
    # Append error to errorLog.txt located in ../private/errorLog.txt using dirname(__FILE__) to get the path of the current file
    $myfile = fopen($pathToErrorLog, "a") or die("Unable to open file!");
    $txt = date("Y-m-d H:i:s") . " - " . $errorGeneratingComponent . " - " . $message . PHP_EOL;
    fwrite($myfile, $txt);
    fclose($myfile);
}

?>