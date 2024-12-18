<?php
# Provide the version number
if ($action == "androidVersion") {

    $sql = "SELECT MAX(`VersionName`) AS version FROM AppVersion";
    $stmt = $pdo->prepare($sql);

    if ($stmt->execute()) {
        # Success
        # Print the result

        # Check if more than 1 row was returned
        if ($stmt->rowCount() > 1 || $stmt->rowCount() == 0) {
            logError($webroot . "/private/errorLog.txt", "version() in AppUserFunctions.php", "More than one or none version number was returned");
            $answerJSON = array(
                "status" => "500",
                "body" => array(
                    "message" => "Internal Server Error - More than one or none version number was returned"
                ),
            );
            echo json_encode($answerJSON);
            exit();
        } else {
            $answerJSON = array(
                "status" => "200",
                "body" => array(
                    "message" => "OK",
                    "version" => $stmt->fetch(PDO::FETCH_ASSOC)['version']
                )
            );
            echo json_encode($answerJSON);
        }
    } else {
        # Error
        logError($webroot . "/private/errorLog.txt", "version() in AppUserFunctions.php", "DB statement failed. Could not get version number");
        $answerJSON = array(
            "status" => "500",
            "body" => array(
                "message" => "Internal Server Error - Could not get version number"
            )
        );
        echo json_encode($answerJSON);
    }
    exit();
}
if ($action == "appleVersion") {

    $sql = "SELECT MAX(`AppleVersion`) AS version FROM AppVersion";
    $stmt = $pdo->prepare($sql);

    if ($stmt->execute()) {
        # Success
        # Print the result

        # Check if more than 1 row was returned
        if ($stmt->rowCount() > 1 || $stmt->rowCount() == 0) {
            logError($webroot . "/private/errorLog.txt", "version() in AppUserFunctions.php", "More than one or none version number was returned");
            $answerJSON = array(
                "status" => "500",
                "body" => array(
                    "message" => "Internal Server Error - More than one or none version number was returned"
                ),
            );
            echo json_encode($answerJSON);
            exit();
        } else {
            $answerJSON = array(
                "status" => "200",
                "body" => array(
                    "message" => "OK",
                    "version" => $stmt->fetch(PDO::FETCH_ASSOC)['version']
                )
            );
            echo json_encode($answerJSON);
        }
    } else {
        # Error
        logError($webroot . "/private/errorLog.txt", "version() in AppUserFunctions.php", "DB statement failed. Could not get version number");
        $answerJSON = array(
            "status" => "500",
            "body" => array(
                "message" => "Internal Server Error - Could not get version number"
            )
        );
        echo json_encode($answerJSON);
    }
    exit();
}
# Login-Procedure using the email and password
# Returns the idAppUser, hasSetPassword and a generated token as a JSON object, if the login was successful
if ($action == "login") {

    # Validate the email and token by checking if they are empty
    if (empty($_POST['mail']) || empty($_POST['password'])) {
        $answerJSON = array(
            "status" => "400",
            "body" => array(
                "message" => "Bad Request - POST of mail or password is empty"
            )
        );
        echo json_encode($answerJSON);
        exit();
    }
    # Get Email and Token from URL
    $mail = $_POST['mail'];
    $pass = $_POST['password'];

    # convert $mail to lowercase
    $mail = strtolower($mail);

    # Verifiy if $mail is valid e-mail address
    if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
        $answerJSON = array(
            "status" => "400",
            "body" => array(
                "message" => "Bad Request - POST of mail is not a valid e-mail address"
            )
        );
        echo json_encode($answerJSON);
        exit();
    }

    # Look up the user-id ("idAppUser") in the database using the email and token (which can be seen as a password)
    $sql = "SELECT idAppUser, hasSetPassword, enc_password, first_login FROM (AppUser JOIN Subject ON AppUser.idSubject = Subject.idSubject) WHERE enc_mail = AES_ENCRYPT(:email, :key) ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':email', $mail, PDO::PARAM_STR);
    $stmt->bindValue(':key', ENCRYPTION_KEY, PDO::PARAM_STR);

    if ($stmt->execute()) {
        # Check if more than one row was returned
        if ($stmt->rowCount() > 1) {
            logError($webroot . "/private/errorLog.txt", "login() in AppUserFunctions.php", "More than one user was returned for email: " . $mail);
            $answerJSON = array(
                "status" => "500",
                "body" => array(
                    "message" => "Internal Server Error - More than one user was returned"
                )
            );
            echo json_encode($answerJSON);
            exit();

        } else if ($stmt->rowCount() == 0) {
            # No user was found
            $answerJSON = array(
                "status" => "401",
                "body" => array(
                    "message" => "Unauthorized - No user was found for the given mail and password"
                )
            );
            echo json_encode($answerJSON);
            exit();
        } else if ($stmt->rowCount() == 1) {
            # Build object from the result with idAppUser and hasSetPassword
            $idAndHasSetPassword = $stmt->fetch(PDO::FETCH_OBJ);
            if (password_verify($pass, $idAndHasSetPassword->enc_password)) {
                # Check whether the password needs to be rehashed (in case of updated PASSWORD_DEFAULT or $password_options)
                if (password_needs_rehash($idAndHasSetPassword->enc_password, PASSWORD_DEFAULT, $password_options)) {
                    logError($webroot . "/private/errorLog.txt", "login() in AppUserFunctions.php", "Password needs to be rehashed for user with idAppUser: " . $idAndHasSetPassword->idAppUser . ". Rehashing password... (This is only a warning and can be ignored)");

                    # Hash the password with php function password_hash and PASSWORD_BCRYPT
                    $pass = password_hash($pass, PASSWORD_DEFAULT, $password_options);

                    # Update the password in the database for the user where the AppUser.idAppUser = $idAndHasSetPassword->idAppUser
                    $sql = "UPDATE AppUser SET enc_password  = ? WHERE idAppUser = ?";
                    $stmt = $pdo->prepare($sql);
                    if ($stmt->execute(array($pass, $idAndHasSetPassword->idAppUser))) {
                        logError($webroot . "/private/errorLog.txt", "login() in AppUserFunctions.php", "Password has been rehashed for user with idAppUser: " . $idAndHasSetPassword->idAppUser . ". (This is only a warning and can be ignored)");
                    } else {
                        logError($webroot . "/private/errorLog.txt", "login() in AppUserFunctions.php", "Password could not be rehashed for user with idAppUser: " . $idAndHasSetPassword->idAppUser);
                    }
                }

                # Generate session token
                $token = bin2hex(random_bytes(32));

                # Update the session token in the database for the user
                $sql = "UPDATE AppUser SET enc_token = ? WHERE idAppUser = '$idAndHasSetPassword->idAppUser'";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute(array($token))) {
                    # Check if first_login column has been set already and populate it, if not
                    if ($idAndHasSetPassword->first_login == null) {
                        $sql = "UPDATE AppUser SET first_login = NOW() WHERE idAppUser = '$idAndHasSetPassword->idAppUser'";
                        $stmt = $pdo->prepare($sql);
                        if (!$stmt->execute()) {
                            logError($webroot . "/private/errorLog.txt", "login() in AppUserFunctions.php", "Could not set first_login for user with idAppUser: " . $idAndHasSetPassword->idAppUser);
                        }
                    }

                    # Build the result object
                    $answerJSON = array(
                        "status" => "200",
                        "body" => array(
                            "message" => "OK",
                            "idAppUser" => $idAndHasSetPassword->idAppUser,
                            "hasSetPassword" => $idAndHasSetPassword->hasSetPassword,
                            "token" => $token,
                        )
                    );
                    echo json_encode($answerJSON);
                } else {
                    logError($webroot . "/private/errorLog.txt", "login() in AppUserFunctions.php", "DB statement failed. Could not update token for user with idAppUser: " . $idAndHasSetPassword->idAppUser);
                    $answerJSON = array(
                        "status" => "500",
                        "message" => "Internal Server Error - DB statement failed. Could not update token for user with idAppUser: " . $idAndHasSetPassword->idAppUser
                    );
                    echo json_encode($answerJSON);
                }
            } else {
                # Password is wrong
                $answerJSON = array(
                    "status" => "401",
                    "body" => array(
                        "message" => "Unauthorized - No user was found for the given mail and password"
                    )
                );
                echo json_encode($answerJSON);
            }
        }
    } else {
        logError($webroot . "/private/errorLog.txt", "login() in AppUserFunctions.php", "DB statement failed. Could not get idAppUser for mail: " . $mail);
        $answerJSON = array(
            "status" => "500",
            "body" => array(
                "message" => "Internal Server Error - DB statement failed. Could not get idAppUser for mail: " . $mail
            )
        );
        echo json_encode($answerJSON);
        exit();
    }
    exit();
}
# Save the device ID for push notifications
# Returns -1 if the token is invalid or data's missing, -2 if there's been an error in the sql query
# Returns 1 if the operation was successful
if ($action == "uploadRegistrationID") {

    # Validate whether the POST of idAppUser and registrationID are empty. Exit with -1 if so.
    if (empty($_POST['registrationID']) || empty($_POST['token'])) {
        $answerJSON = array(
            "status" => "400",
            "body" => array(
                "message" => "Bad Request - POST of registrationID or token is empty"
            )
        );
        echo json_encode($answerJSON);
        exit();
    }

    # Get the token and registration ID from POST
    $registrationID = $_POST["registrationID"];
    $token = $_POST["token"];

    # Check token via TokenAuth function
    $tokenAuthRes = TokenAuth($pdo, $token, $webroot . "/private/errorLog.txt");

    # If the token is valid, save the registration ID and update the database ($tokenAuthRes contains the user-id in this case)
    if ($tokenAuthRes > 0) {
        # Prepare the SQL statement to insert the registration ID into AppUser
        $sql = "UPDATE AppUser SET registration_id = ? WHERE idAppUser = ?";
        $stmt = $pdo->prepare($sql);

        # Execute the SQL statement
        if ($stmt->execute(array($registrationID, $tokenAuthRes))) {

            # Token saved successfully
            $answerJSON = array(
                "status" => "200",
                "body" => array(
                    "message" => "OK"
                )
            );
            echo json_encode($answerJSON);
            exit();
        } else {
            # Token could not be saved
            logError($webroot . "/private/errorLog.txt", "uploadRegistrationID() in AppUserFunctions.php", "DB statement failed. Could not save registration ID for user with idAppUser: " . $tokenAuthRes);
            $answerJSON = array(
                "status" => "500",
                "body" => array(
                    "message" => "Internal Server Error - DB statement failed. Could not save registration ID for user with idAppUser: " . $tokenAuthRes
                )
            );
            echo json_encode($answerJSON);
            exit();
        }
    } else {
        # Invalid token
        $answerJSON = array(
            "status" => "401",
            "body" => array(
                "message" => "Unauthorized - token is invalid"
            )
        );
        echo json_encode($answerJSON);
    }
    exit();
}
# Return the full name of a user by the user-id as a JSON object
if ($action == "getName") {
    # Check whether idAppUser and token are empty in POST request
    if (empty($_POST['token'])) {
        $answerJSON = array(
            "status" => "400",
            "body" => array(
                "message" => "Bad Request - POST of token is empty"
            )
        );
        echo json_encode($answerJSON);
        exit();
    }

    # Get the user-id from POST
    $token = $_POST["token"];

    # Check token via TokenAuth function
    $tokenAuthRes = TokenAuth($pdo, $token, $webroot . "/private/errorLog.txt");

    # If the token is valid, get the name from Subject db matching through the idSubject ($user contains the user-id in this case)
    if ($tokenAuthRes > 0) {
        # Prepare the SQL statement to get the full name from Subject where the idSubject matches
        $sql = "SELECT AES_DECRYPT(enc_name, :key) AS name FROM Subject WHERE idSubject = (SELECT idSubject FROM AppUser WHERE idAppUser = :idAppUser)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':key', ENCRYPTION_KEY, PDO::PARAM_STR);
        $stmt->bindParam(':idAppUser', $tokenAuthRes, PDO::PARAM_INT);
        # Execute the SQL statement
        if ($stmt->execute()) {
            # Succesfully executed
            # Fetch the result as an associative array
            $answerJSON = array(
                "status" => "200",
                "body" => array(
                    "message" => "OK",
                    "name" => $stmt->fetch(PDO::FETCH_OBJ)->name
                )
            );
            echo json_encode($answerJSON);
            exit();

        } else {
            # Return statement for an unsuccessful execution
            logError($webroot . "/private/errorLog.txt", "getName() in AppUserFunctions.php", "DB statement failed. Could not get name for user with idAppUser: " . $tokenAuthRes);
            $answerJSON = array(
                "status" => "500",
                "body" => array(
                    "message" => "Internal Server Error - DB statement failed. Could not get name for user with idAppUser: " . $tokenAuthRes
                )
            );
            echo json_encode($answerJSON);
            exit();
        }
    } else {
        # Invalid token
        $answerJSON = array(
            "status" => "401",
            "body" => array(
                "message" => "Unauthorized - token is invalid"
            )
        );
        echo json_encode($answerJSON);
    }
    exit();
}
# Return the number of entries by user-id
if ($action == "getNumOfEntries") {
    # Check whether idAppUser and token are empty in POST request
    if (empty($_POST['token'])) {
        $answerJSON = array(
            "status" => "400",
            "body" => array(
                "message" => "Bad Request - POST of token is empty"
            )
        );
        echo json_encode($answerJSON);
        exit();
    }

    # Get the token from POST
    $token = $_POST["token"];

    # Check token via TokenAuth function
    $tokenAuthRes = TokenAuth($pdo, $token, $webroot . "/private/errorLog.txt");

    # If the token is valid, get the number of entries from Upload where the user-id matches ($user contains the user-id in this case)
    if ($tokenAuthRes > 0) {
        $numberOfUploads = getNumberOfUploadsForIdAppUser($tokenAuthRes, $pdo, $webroot . "/private/errorLog.txt");

        if ($numberOfUploads['status'] == 'success' && isset($numberOfUploads['uploadCount'])) {
            $numberOfUploads = $numberOfUploads['uploadCount'];

            $answerJSON = array(
                "status" => "200",
                "body" => array(
                    "message" => "OK",
                    "numOfEntries" => $numberOfUploads
                )
            );
            echo json_encode($answerJSON);
            exit();
        } else {
            logError($webroot . "/private/errorLog.txt", "getNumOfEntries() in AppUserFunctions.php", "Could not get number of uploads for idAppUser " . $user['idAppUser'] . ". " . $numberOfUploads['message']);
            $json = array(
                'status' => '500',
                'body' => array(
                    'message' => 'Could not get number of uploads for idAppUser ' . $user['idAppUser'] . '. ' . $numberOfUploads['message'],
                ),
            );
            echo json_encode($json);
            exit();
        }
    } else {
        # Invalid token
        $answerJSON = array(
            "status" => "401",
            "body" => array(
                "message" => "Unauthorized - token is invalid"
            )
        );
        echo json_encode($answerJSON);
    }
    exit();
}
# Get IBAN from Subject table using a match with subjectID
if ($action == "GetIBAN") {

    # Check whether idAppUser and token are empty in POST request
    if (empty($_POST['token'])) {
        $answerJSON = array(
            "status" => "400",
            "body" => array(
                "message" => "Bad Request - POST of token is empty"
            )
        );
        echo json_encode($answerJSON);
        exit();
    }

    # Get the user-id from POST
    $token = $_POST["token"];

    # Check token via TokenAuth function
    $tokenAuthRes = TokenAuth($pdo, $token, $webroot . "/private/errorLog.txt");

    # If the token is valid, get the IBAN from Subject table matching through the idSubject ($user contains the user-id in this case)
    if ($tokenAuthRes > 0) {

        # Prepare the SQL statement to get the IBAN from Subject where the idSubject matches
        $sql = "SELECT AES_DECRYPT(enc_IBAN, :key) as iban FROM Subject WHERE idSubject = (SELECT idSubject FROM AppUser WHERE idAppUser = :idAppUser)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':key', ENCRYPTION_KEY, PDO::PARAM_STR);
        $stmt->bindParam(':idAppUser', $tokenAuthRes, PDO::PARAM_INT);
        # Execute the SQL statement
        if ($stmt->execute()) {
            # Succesfully executed
            $answerJSON = array(
                "status" => "200",
                "body" => array(
                    "message" => "OK",
                    "iban" => $stmt->fetch(PDO::FETCH_OBJ)->iban
                )
            );
            echo json_encode($answerJSON);
            exit();
        } else {
            # Return statement for an unsuccessful execution
            logError($webroot . "/private/errorLog.txt", "GetIBAN() in AppUserFunctions.php", "DB statement failed. Could not get IBAN for user with idAppUser: " . $tokenAuthRes);
            $answerJSON = array(
                "status" => "500",
                "body" => array(
                    "message" => "Internal Server Error - DB statement failed. Could not get IBAN for user with idAppUser: " . $tokenAuthRes
                )
            );
            echo json_encode($answerJSON);
            exit();
        }
    } else {
        # Invalid token
        $answerJSON = array(
            "status" => "401",
            "body" => array(
                "message" => "Unauthorized - token is invalid"
            )
        );
        echo json_encode($answerJSON);
    }
    exit();
}
# Set if the user wants to receive push notifications
if ($action == "setPushNotification") {
    # Check whether idAppUser, push are empty in POST request
    # CAUTION: We are expection either 0 or 1 as values for push. A check with empty() returns true for 0, so we have to check with isset()
    if (!isset($_POST['push']) || empty($_POST['token'])) {
        $answerJSON = array(
            "status" => "400",
            "body" => array(
                "message" => "Bad Request - POST of push or token is empty"
            )
        );
        echo json_encode($answerJSON);
        exit();
    }

    # Get the token and push-notification-setting from POST
    $push = $_POST["push"];
    $token = $_POST["token"];

    # Check if push is 0 or 1
    if ($push != 0 && $push != 1) {
        $answerJSON = array(
            "status" => "400",
            "body" => array(
                "message" => "Bad Request - POST of push is not 0 or 1"
            )
        );
        echo json_encode($answerJSON);
        exit();
    }

    # Check token via TokenAuth function
    $tokenAuthRes = TokenAuth($pdo, $token, $webroot . "/private/errorLog.txt");

    # If the token is valid, update the push-notification-setting in AppUser
    if ($tokenAuthRes > 0) {
        # Prepare the SQL statement to update the push-notification-setting in AppUser
        $sql = "UPDATE AppUser SET receivesPush = ? WHERE idAppUser = ?";
        $stmt = $pdo->prepare($sql);
        # Execute the SQL statement
        if ($stmt->execute(array($push, $tokenAuthRes))) {
            # Succesfully executed
            $answerJSON = array(
                "status" => "200",
                "body" => array(
                    "message" => "OK"
                )
            );
            echo json_encode($answerJSON);
            exit();
        } else {
            # Return as statement for an unsuccessful execution
            logError($webroot . "/private/errorLog.txt", "setPushNotification() in AppUserFunctions.php", "DB statement failed. Could not set push-notification-setting for user with idAppUser: " . $tokenAuthRes);
            $answerJSON = array(
                "status" => "500",
                "body" => array(
                    "message" => "Internal Server Error - DB statement failed. Could not set push-notification-setting for user with idAppUser: " . $tokenAuthRes
                )
            );
            echo json_encode($answerJSON);
            exit();
        }
    } else {
        # Invalid token
        $answerJSON = array(
            "status" => "401",
            "body" => array(
                "message" => "Unauthorized - token is invalid"
            )
        );
        echo json_encode($answerJSON);
    }
    exit();
}
# Return an array with boolean values for the surveys
if ($action == "getOpenSurveys") {
    if (!isset($_POST["token"])) {
        $answerJSON = array(
            "status" => "400",
            "body" => array(
                "message" => "Bad Request - POST of token is empty"
            )
        );
        echo json_encode($answerJSON);
        exit();
    }

    # Get the token from the POST
    $token = $_POST["token"];

    # Check the token via TokenAuth function
    $tokenAuthRes = TokenAuth($pdo, $token, $webroot . "/private/errorLog.txt");

    # If the token is valid, get the open surveys for the user
    if ($tokenAuthRes > 0) {
        $res = getOpenSurveysForIDAppuser($tokenAuthRes, $pdo, $webroot . "/private/errorLog.txt");
        if ($res['status'] == "error") {
            logError($webroot . "/private/errorLog.txt", "getOpenSurveys() in AppUserFunctions.php", "DB statement failed. Could not get open surveys for user with idAppUser: " . $tokenAuthRes . " - " . $res['message']);
            $answerJSON = array(
                "status" => "500",
                "body" => array(
                    "message" => "Internal Server Error - DB statement failed. Could not get open surveys for user with idAppUser: " . $tokenAuthRes
                )
            );
            echo json_encode($answerJSON);
            exit();
        } else if ($res['status'] == "success") {
            if (empty($res['surveys'])) {
                $answerJSON = array(
                    "status" => "200",
                    "body" => array(
                        "message" => "success",
                        "surveys" => array()
                    )
                );
                echo json_encode($answerJSON);
                exit();
            } else {
                $answerJSON = array(
                    "status" => "200",
                    "body" => array(
                        "message" => "success",
                        "surveys" => $res['surveys']
                    )
                );
                echo json_encode($answerJSON);
                exit();
            }

        } else {
            $answerJSON = array(
                "status" => "500",
                "body" => array(
                    "message" => "Internal Server Error - Unknown error"
                )
            );
            echo json_encode($answerJSON);
            exit();
        }
    } else {
        # Invalid token
        $answerJSON = array(
            "status" => "401",
            "body" => array(
                "message" => "Unauthorized - token is invalid"
            )
        );
        echo json_encode($answerJSON);
        exit();
    }
}
#Get Cleverpush Channel ID and return it
if ($action == "getChannelId") {
    # Check whether idAppUser and token are empty in POST request
    if (empty($_POST['token'])) {
        $answerJSON = array(
            "status" => "400",
            "body" => array(
                "message" => "Bad Request - POST of token is empty"
            )
        );
        echo json_encode($answerJSON);
        exit();
    }

    # Get the user-id from POST
    $token = $_POST["token"];

    # Check token via TokenAuth function
    $tokenAuthRes = TokenAuth($pdo, $token, $webroot . "/private/errorLog.txt");

    # If the token is valid, get the name from Subject db matching through the idSubject ($user contains the user-id in this case)
    if ($tokenAuthRes > 0) {
        # Prepare the SQL statement to get the full name from Subject where the idSubject matches
        $sql = "SELECT CleverPushChannelId FROM CleverpushChannels";
        $stmt = $pdo->prepare($sql);

        # Execute the SQL statement
        if ($stmt->execute()) {
            # Succesfully executed
            # Fetch the result as an associative array

            $answerJSON = array(
                "status" => "200",
                "body" => array(
                    "message" => "OK",
                    "CleverPushChannelId" => $stmt->fetch(PDO::FETCH_OBJ)->CleverPushChannelId
                )
            );
            echo json_encode($answerJSON);
            exit();

        } else {
            # Return statement for an unsuccessful execution
            logError($webroot . "/private/errorLog.txt", "getName() in AppUserFunctions.php", "DB statement failed. Could not get name for user with idAppUser: " . $tokenAuthRes);
            $answerJSON = array(
                "status" => "500",
                "body" => array(
                    "message" => "Internal Server Error - DB statement failed. Could not get name for user with idAppUser: " . $tokenAuthRes
                )
            );
            echo json_encode($answerJSON);
            exit();
        }
    } else {
        # Invalid token
        $answerJSON = array(
            "status" => "401",
            "body" => array(
                "message" => "Unauthorized - token is invalid"
            )
        );
        echo json_encode($answerJSON);
    }
    exit();
}
#Get status of user if he or she wants to receive push notifications
if ($action == "getPushNotificationStatus") {
    # Check whether idAppUser and token are empty in POST request
    if (empty($_POST['token'])) {
        $answerJSON = array(
            "status" => "400",
            "body" => array(
                "message" => "Bad Request - POST of token is empty"
            )
        );
        echo json_encode($answerJSON);
        exit();
    }

    # Get the user-id from POST
    $token = $_POST["token"];

    # Check token via TokenAuth function
    $tokenAuthRes = TokenAuth($pdo, $token, $webroot . "/private/errorLog.txt");

    # If the token is valid, get the name from Subject db matching through the idSubject ($user contains the user-id in this case)
    if ($tokenAuthRes > 0) {
        # Prepare the SQL statement to get the full name from Subject where the idSubject matches
        $sql = "SELECT receivesPush FROM AppUser WHERE idAppUser = ?";
        $stmt = $pdo->prepare($sql);

        # Execute the SQL statement
        if ($stmt->execute(array($tokenAuthRes))) {
            # Succesfully executed
            # Fetch the result as an associative array

            $answerJSON = array(
                "status" => "200",
                "body" => array(
                    "message" => "OK",
                    "receivesPush" => $stmt->fetch(PDO::FETCH_OBJ)->receivesPush
                )
            );
            echo json_encode($answerJSON);
            exit();

        } else {
            # Return statement for an unsuccessful execution
            logError($webroot . "/private/errorLog.txt", "getPushNotificationStatus() in AppUserFunctions.php", "DB statement failed. Could not get name for user with idAppUser: " . $tokenAuthRes);
            $answerJSON = array(
                "status" => "500",
                "body" => array(
                    "message" => "Internal Server Error - DB statement failed. Could not get name for user with idAppUser: " . $tokenAuthRes
                )
            );
            echo json_encode($answerJSON);
            exit();
        }
    } else {
        # Invalid token
        $answerJSON = array(
            "status" => "401",
            "body" => array(
                "message" => "Unauthorized - token is invalid"
            )
        );
        echo json_encode($answerJSON);
    }
    exit();
}
# Log user out and delete app token
if ($action == "Logout") {
    # Validate wehter the POST of token is set
    if (!isset($_POST["token"])) {
        $answerJSON = array(
            "status" => "400",
            "body" => array(
                "message" => "Bad Request - POST of token is empty"
            )
        );
        echo json_encode($answerJSON);
        exit();
    }

    # Get the token from the POST
    $token = $_POST["token"];

    # Check the token via TokenAuth function
    $result = TokenAuth($pdo, $token, $webroot . "/private/errorLog.txt");

    # If the token is valid, set the login_token to '' in the DB
    if ($result > 0) {
        $sql = "UPDATE AppUser SET enc_token = NULL, registration_id = NULL WHERE idAppUser = ?";
        $stmt = $pdo->prepare($sql);

        if ($stmt->execute(array($result))) {
            $answerJSON = array(
                "status" => "200",
                "body" => array(
                    "message" => "OK"
                )
            );
            echo json_encode($answerJSON);
            exit();
        } else {
            logError($webroot . "/private/errorLog.txt", "Logout() in AppUserFunctions.php", "DB statement failed. Could not logout user with idAppUser: " . $result);
            $answerJSON = array(
                "status" => "500",
                "body" => array(
                    "message" => "Internal Server Error - DB statement failed. Could not logout user with idAppUser: " . $result
                )
            );
            echo json_encode($answerJSON);
            echo -2;
        }
    } else {
        $answerJSON = array(
            "status" => "401",
            "body" => array(
                "message" => "Unauthorized - token is invalid"
            )
        );
        echo json_encode($answerJSON);
    }
    exit();
}

?>