<?php
# Create User through POST-call (getting Full name, e-mail-address and interest in study parts)
# Returning "User created. ID: xxx"
if ($action == "createUser") {
    # Check if all required parameters are set
    if (empty($_POST['email']) || empty($_POST['name']) || empty($_POST['participation_interest']) || empty($_POST['privacy']) || empty($_POST['api_key'])) {
        $answerJSON = array(
            "status" => "400",
            "body" => array(
                "message" => "Bad request. POST of email, name, participation_interest, privacy or api_key is empty."
            )
        );
        echo json_encode($answerJSON);
        exit();
    }

    # Check API-Key from POST. If Key is not correct, return "Not authorized"
    if ($_POST["api_key"] != CREATION_API_KEY) {
        logError($pathToErrorLog, "createUser() in UserManagement.php", "API-Key is not correct.");
        $answerJSON = array(
            "status" => "401",
            "body" => array(
                "message" => "Unauthorized. API-Key is not correct."
            )
        );
        echo json_encode($answerJSON);
        exit();
    }

    # Get the full name, e-mail-address and interest in study parts from POST
    $name = $_POST["name"];
    $email = $_POST["email"];
    $interest = $_POST["participation_interest"];
    $privacy = $_POST["privacy"];

    # convert $mail to lowercase
    $email = strtolower($email);

    # Check if email is a valid email address
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "Invalid email";
        die();
    }

    # Remove leading and trailing whitespaces from $name
    $name = trim($name);

    # Check, if email already exists in database
    $sql = "SELECT AES_DECRYPT(enc_name, :key) as name FROM Subject WHERE enc_mail = AES_ENCRYPT(:email, :key)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':key', ENCRYPTION_KEY);
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        # Log double registration to errorLog.txt
        logError($pathToErrorLog, "createUser() in UserManagement.php", "Double registration detected. Email: " . $email);

        # Get name from stmt
        $name_from_db = $stmt->fetch(PDO::FETCH_ASSOC)['name'];

        # Build email to send to user
        $subject = "DESIVE² - Wiederholte Anmeldung für die Teilnahme an der Studie";
        $preheader = "Sie haben sich bereits für die Teilnahme an der Studie 'DESIVE2' angemeldet.";
        $mailTitle = "Wiederholter Anmeldeversuch für die Teilnahme an der Studie 'DESIVE2'";
        ob_start();
        include($webroot . "/private/resources/mail-templates/email-body-duplicate-registration.html");
        $body = ob_get_clean();

        # Send mail via func
        $mailstatus = sendMail($email, $name_from_db, MAIL_FROM_ADDRESS, MAIL_FROM_NAME, $subject, $preheader, $mailTitle, $body, $webroot, $webroot . "/private/errorLog.txt");

        # Check if mail was sent successfully by reading the JSON in mailstatus
        if ($mailstatus['status'] == "success") {
            # Mail was sent successfully
            echo "error - duplicate. Email already exists. Mail sent to user.";
        } else {
            echo "error - duplicate. Email already exists. Mail could not be transmitted to user..";
            logError($webroot . "/private/errorLog.txt", "createUser() in UserManagement.php", "Double registration. Mail not sent. Mailstatus: " . $mailstatus['status'] . " - " . $mailstatus['message']);
        }
        exit();
    }

    # If teilnahme = 1, the user is interested in interview and app usage. Set app = 1 and interview = 1 and pseudonym = A
    # If teilnahme = 2, the user is interested in interview only. Set app = 0 and interview = 1 and pseudonym = B
    # If teilnahme = 3, the user is interested in app usage only. Set app = 1 and interview = 0 and pseudonym = C
    if ($interest == 1) {
        $app = 1;
        $interview = 1;
    } else if ($interest == 2) {
        $app = 0;
        $interview = 1;
    } else if ($interest == 3) {
        $app = 1;
        $interview = 0;
    } else {
        logError($pathToErrorLog, "createUser() in UserManagement.php", "Invalid interest. Interest: " . $interest);
        echo "Invalid interest";
        die();
    }

    # Generate pseudonym with one letter and a random number of 4 digits and check if pseudonym is already in use.
    # If pseudonym is already in use, generate a new pseudonym and check again until pseudonym is unique
    $pseudonym = chr(rand(65, 90)) . rand(1000, 9999);
    $sql = "SELECT * FROM Subject WHERE SubjectIdentifier = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($pseudonym));
    while ($stmt->rowCount() > 0) {
        # Generate new pseudonym
        $pseudonym = chr(rand(65, 90)) . rand(1000, 9999);
        $stmt->execute(array($pseudonym));
    }

    # Prepare the SQL statement to insert the user into Subject and execute it
    $sql = "INSERT INTO Subject (SubjectIdentifier, enc_name, enc_mail, app, interview) VALUES (:SubjectIdentifier, AES_ENCRYPT(:name, :key), AES_ENCRYPT(:email, :key), :app, :interview)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':SubjectIdentifier', $pseudonym);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':app', $app);
    $stmt->bindParam(':interview', $interview);
    $stmt->bindValue(':key', ENCRYPTION_KEY);
    if ($stmt->execute()) {
        # Send information mail to ZBW/IBI if interview is set to 1
        if ($interview == 1) {
            # Build email to send to IBI
            $subject = "DESIVE² - Neue Anmeldung zu Interview";
            $preheader = "Neue Anmeldung zu einem Interview an der Studie 'DESIVE2'.";
            $mailTitle = "Neue Anmeldung zu Interview";
            ob_start();
            include($webroot . "/private/resources/mail-templates/email-body-new-interview-participant.html");
            $body = ob_get_clean();

            # Replace placeholders in email body
            $body = str_replace("{{name}}", $name, $body);
            $body = str_replace("{{email}}", $email, $body);
            $body = str_replace("{{pseudonym}}", $pseudonym, $body);

            # Send mail via func
            $mailstatus = sendMail(MAIL_TO_ADDRESS_INTERVIEW, MAIL_TO_NAME_INTERVIEW, MAIL_FROM_ADDRESS, MAIL_FROM_NAME, $subject, $preheader, $mailTitle, $body, $webroot, $webroot . "/private/errorLog.txt");

            # Check if mail was sent successfully by reading the JSON in mailstatus
            if ($mailstatus['status'] != "success") {
                logError($webroot . "/private/errorLog.txt", "createUser() in UserManagement.php", "Mail for new interview participant not sent. Mailstatus: " . $mailstatus['status'] . " - " . $mailstatus['message']);
            }
        }

        # If waitlist is active, only send an email to the user and do not create an app account
        # If waitlist is not active, create an app account as well (if app = 1)
        if ($waitlist_active) {
            # Send different mail to user and do not create an app account
            # Build email to send to user
            $subject = "DESIVE² - Vielen Dank für Ihre Anmeldung zur Studie";
            $preheader = "Vielen Dank für Ihre Anmeldung an der Studie 'DESIVE2'.";
            $mailTitle = "Anmeldebestätigung für die DESIVE²-Studie";
            ob_start();
            include($webroot . "/private/resources/mail-templates/email-body-registration-confirmation-waitlist.html");
            $body = ob_get_clean();

            # Send mail via func
            $mailstatus = sendMail($email, $name, MAIL_FROM_ADDRESS, MAIL_FROM_NAME, $subject, $preheader, $mailTitle, $body, $webroot, $webroot . "/private/errorLog.txt");

            # Check if mail was sent successfully by reading the JSON in mailstatus
            if ($mailstatus['status'] != "success") {
                logError($webroot . "/private/errorLog.txt", "createUser() in UserManagement.php", "Mail for new interview participant not sent. Mailstatus: " . $mailstatus['status'] . " - " . $mailstatus['message']);
                echo "Vorgang konnte nicht abgeschlossen werden. Bitte wenden Sie sich über das Kontaktformular an uns.";
                exit();
            } else {
                echo "Success";
            }

        } else {
            # Send confirmation mail to user
            # Build email to send to user
            $subject = "DESIVE² - Vielen Dank für Ihre Anmeldung zur Studie";
            $preheader = "Vielen Dank für Ihre Anmeldung an der Studie 'DESIVE2'.";
            $mailTitle = "Anmeldebestätigung für die DESIVE²-Studie";
            ob_start();
            include($webroot . "/private/resources/mail-templates/email-body-registration-confirmation.html");
            $body = ob_get_clean();

            # Send mail via func
            $mailstatus = sendMail($email, $name, MAIL_FROM_ADDRESS, MAIL_FROM_NAME, $subject, $preheader, $mailTitle, $body, $webroot, $webroot . "/private/errorLog.txt");

            # Check if mail was sent successfully by reading the JSON in mailstatus
            if ($mailstatus['status'] != "success") {
                logError($webroot . "/private/errorLog.txt", "createUser() in UserManagement.php", "Confirmation-mail to new participant not sent. #Mailstatus: " . $mailstatus['status'] . " - " . $mailstatus['message'] . " - Zieladresse: " . $email);
                echo "Confirmation-mail to new participant not sent. Bitte wenden Sie sich über das Kontaktformular an uns!";
                exit();
            }

            # Generate app account if app is set to 1
            if ($app == 1) {
                # Create app account
                $resultAppAccountCreation = createAppAccount($pdo, $email, ENCRYPTION_KEY, $webroot . "/private/errorLog.txt");
                # Check the result of the createAppAccount function 
                # if -1 app account already exists, if -2 email not found in Subject table, if -3 DB statement failed, if 1 app account was created successfully
                if ($resultAppAccountCreation == -1) {
                    echo "App account already exists!";
                    logError($webroot . "/private/errorLog.txt", "createUser() in UserManagement.php", "App account already exists for user " . $email . "!");
                    die();
                } else if ($resultAppAccountCreation == -2) {
                    echo "Email not found in Subject table!";
                    logError($webroot . "/private/errorLog.txt", "createUser() in UserManagement.php", "Email " . $email . " not found in Subject table!");
                    die();
                } else if ($resultAppAccountCreation == -3) {
                    echo "DB statement failed!";
                    logError($webroot . "/private/errorLog.txt", "createUser() in UserManagement.php", "DB statement for account creation failed! Email: " . $email);
                    die();
                } else if ($resultAppAccountCreation == 1) {
                    # Reset password for the user
                    $resultPasswordReset = resetPassword($pdo, $email, ENCRYPTION_KEY, $password_options, MAIL_FROM_ADDRESS, MAIL_FROM_NAME, $webroot, $webroot . "/private/errorLog.txt");
                    # Check the result of the resetPassword function (if -1, the user with this email was not found, if -2 the DB statement failed, if 1 the password was reset successfully)
                    if ($resultPasswordReset == -1) {
                        echo "User for password reset not found!";
                        logError($webroot . "/private/errorLog.txt", "createUser() in UserManagement.php", "User " . $email . "for password reset not found!");
                        die();
                    } else if ($resultPasswordReset == -2) {
                        echo "DB statement failed!";
                        logError($webroot . "/private/errorLog.txt", "createUser() in UserManagement.php", "DB statement for password reset failed! Email: " . $email);
                        die();
                    } else if ($resultPasswordReset == -3) {
                        echo "Password reset mail failed!";
                        logError($webroot . "/private/errorLog.txt", "createUser() in UserManagement.php", "Password reset mail failed! Email: " . $email);
                        die();
                    } else if ($resultPasswordReset == 1) {
                        echo "Success";
                    }
                }
            } else {
                echo "Success";
            }
        }
    } else {
        echo "DB statement failed!";
        logError($webroot . "/private/errorLog.txt", "createUser() in UserManagement.php", "DB statement for user creation in Subject failed! Email: " . $email);
        die();
    }
    exit();
}
# If action is resetPassword, reset the password of the user and mail it
# Input e-mail-address via POST
# Returns "Success" if the password was reset successfully, "User not found!" if the email was not found, "DB statement failed!" if the DB statement failed
if ($action == "resetPassword") {
    # Validate whether the POST of email is empty. Exit with error message if so.
    # Validate the api-key and exit with error message if it is wrong
    if (empty($_POST['email']) || empty($_POST['api_key'])) {
        echo "No email specified or API-Key is missing";
        exit();
    } else if ($_POST['api_key'] != CREATION_API_KEY) {
        logError($webroot . "/private/errorLog.txt", "resetPassword() in UserManagement.php", "API-Key is wrong!");
        echo "API-Key is wrong";
        exit();
    }

    # Get the email from the body of the request
    $email = $_POST['email'];
    # convert $mail to lowercase
    $email = strtolower($email);

    # Verify email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        logError($webroot . "/private/errorLog.txt", "resetPassword() in UserManagement.php", "Invalid email format of " . $email);
        echo "Invalid email format";
        exit();
    }
    # Call resetPassword function
    $result = resetPassword($pdo, $email, ENCRYPTION_KEY, $password_options, MAIL_FROM_ADDRESS, MAIL_FROM_NAME, $webroot, $webroot . "/private/errorLog.txt");

    # Check the result of the resetPassword function (if -1, the user with this email was not found, if -2 the DB statement failed, if 1 the password was reset successfully)
    if ($result == -1) {
        logError($webroot . "/private/errorLog.txt", "resetPassword() in UserManagement.php", "User " . $email . " not found!");
        echo "User not found!";
        exit();
    } else if ($result == -2) {
        logError($webroot . "/private/errorLog.txt", "resetPassword() in UserManagement.php", "DB statement for password reset failed! Email: " . $email);
        echo "DB statement failed!";
        exit();
    } else if ($result == -3) {
        logError($webroot . "/private/errorLog.txt", "resetPassword() in UserManagement.php", "Password reset mail failed! Email: " . $email);
        echo "Password Mail failed!";
        exit();
    } else if ($result == 1) {
        echo "Success";
    }
    exit();
}
# Change the participation interest of a subject in the db and generate corresponding data (app login/mail/...)    
if ($action == "upgradeParticipationInterest") {
    # Check if all required parameters are set
    if (empty($_POST["subjectIdentifier"]) || empty($_POST["api_key"])) {
        # Build JSON object
        $json = array(
            "status" => "error",
            "message" => "Missing parameters"
        );
        echo json_encode($json);
        die();
    }

    # Check API-Key from Post. If key is not correct, return "Not authorized"
    if ($_POST["api_key"] != CREATION_API_KEY) {
        logError($webroot . "/private/errorLog.txt", "upgradeParticipationInterest() in UserManagement", "API-Key is wrong!");
        # Build JSON object
        $json = array(
            "status" => "error",
            "message" => "Not authorized"
        );
        echo json_encode($json);
        die();
    }

    # Get the subjectIdentifier from the POST
    $subjectIdentifier = $_POST["subjectIdentifier"];

    # Check if the subjectIdentifier is in the DB
    $sql = "SELECT AES_DECRYPT(enc_name, :key) AS name, AES_DECRYPT(enc_mail, :key) as mail, SubjectIdentifier, app, interview FROM Subject WHERE SubjectIdentifier = :SubjectIdentifier";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':key', ENCRYPTION_KEY);
    $stmt->bindParam(':SubjectIdentifier', $subjectIdentifier);

    if ($stmt->execute()) {
        if ($stmt->rowCount() == 0) {
            logError($webroot . "/private/errorLog.txt", "upgradeParticipationInterest() in UserManagement", "SubjectIdentifier not found" . $subjectIdentifier);
            # Build JSON object
            $json = array(
                "status" => "error",
                "message" => "SubjectIdentifier not found"
            );
            echo json_encode($json);
            die();
        } else if ($stmt->rowCount() > 1) {
            logError($webroot . "/private/errorLog.txt", "upgradeParticipationInterest() in UserManagement", "SubjectIdentifier not unique" . $subjectIdentifier);
            # Build JSON object
            $json = array(
                "status" => "error",
                "message" => "SubjectIdentifier not unique"
            );
            echo json_encode($json);
            die();
        } else if ($stmt->rowCount() == 1) {
            # Get name and mail
            $row = $stmt->fetch(PDO::FETCH_OBJ);
            $name = $row->name;
            $email = $row->mail;

            # Check if the user already has an app account
            if ($row->app == 1 && $row->interview == 0) {
                # Set the interview value for this user to 1 as well
                $sql = "UPDATE Subject SET interview = 1 WHERE SubjectIdentifier = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute(array($subjectIdentifier))) {
                    # Build email to send to IBI
                    $subject = "DESIVE² - Nachträgliche Anmeldung zu Interview";
                    $preheader = "Nachträgliche Anmeldung zu einem Interview an der Studie 'DESIVE2'.";
                    $mailTitle = "Nachträgliche Anmeldung zu Interview";
                    ob_start();
                    include($webroot . "/private/resources/mail-templates/email-body-new-interview-participant.html");
                    $body = ob_get_clean();

                    # Replace placeholders in email body
                    $body = str_replace("{{name}}", $name, $body);
                    $body = str_replace("{{email}}", $email, $body);
                    $body = str_replace("{{pseudonym}}", $subjectIdentifier, $body);

                    # Send mail via func
                    $mailstatus = sendMail(MAIL_TO_ADDRESS_INTERVIEW, MAIL_TO_NAME_INTERVIEW, MAIL_FROM_ADDRESS, MAIL_FROM_NAME, $subject, $preheader, $mailTitle, $body, $webroot, $webroot . "/private/errorLog.txt");

                    # Check if mail was sent successfully by reading the JSON in mailstatus
                    if ($mailstatus['status'] != "success") {
                        logError($webroot . "/private/errorLog.txt", "createUser() in UserManagement.php", "Mail for new interview participant  not sent. Mailstatus: " . $mailstatus['status'] . " - " . $mailstatus['message']);
                    }

                    # Build JSON object
                    $json = array(
                        "status" => "success",
                        "message" => "The value for interview participation for " . $subjectIdentifier . " has been set to 1."
                    );
                    echo json_encode($json);
                    die();
                } else {
                    logError($webroot . "/private/errorLog.txt", "upgradeParticipationInterest() in UserManagement", "DB statement failed! (Update interview = 1) SubjectIdentifier: " . $subjectIdentifier);
                    # Build JSON object
                    $json = array(
                        "status" => "error",
                        "message" => "DB statement failed"
                    );
                    echo json_encode($json);
                    die();
                }

            } else if ($row->app == 0 && $row->interview == 1) {
                # Create app account
                $resultAppAccountCreation = createAppAccount($pdo, $email, ENCRYPTION_KEY, $webroot . "/private/errorLog.txt");
                # Check the result of the createAppAccount function 
                # if -1 app account already exists, if -2 email not found in Subject table, if -3 DB statement failed, if 1 app account was created     successfully
                if ($resultAppAccountCreation == -1) {
                    logError($webroot . "/private/errorLog.txt", "upgradeParticipationInterest() in UserManagement", "App account already exists! SubjectIdentifier: " . $subjectIdentifier);
                    $json = array(
                        "status" => "error",
                        "message" => "App account already exists!"
                    );
                    echo json_encode($json);
                    die();
                } else if ($resultAppAccountCreation == -2) {
                    logError($webroot . "/private/errorLog.txt", "upgradeParticipationInterest() in UserManagement", "Email not found in Subject table! SubjectIdentifier: " . $subjectIdentifier);
                    $json = array(
                        "status" => "error",
                        "message" => "Email not found in Subject table!"
                    );
                    echo json_encode($json);
                    die();
                } else if ($resultAppAccountCreation == -3) {
                    logError($webroot . "/private/errorLog.txt", "upgradeParticipationInterest() in UserManagement", "DB statement failed! SubjectIdentifier: " . $subjectIdentifier);
                    $json = array(
                        "status" => "error",
                        "message" => "DB statement failed!"
                    );
                    echo json_encode($json);
                    die();
                } else if ($resultAppAccountCreation == 1) {
                    # Reset password for the user
                    $resultPasswordReset = resetPassword($pdo, $email, ENCRYPTION_KEY, $password_options, MAIL_FROM_ADDRESS, MAIL_FROM_NAME, $webroot, $webroot . "/private/errorLog.txt");
                    # Check the result of the resetPassword function (if -1, the user with this email was not found, if -2 the DB statement failed,     if 1 the password was reset 		successfully)
                    if ($resultPasswordReset == -1) {
                        logError($webroot . "/private/errorLog.txt", "upgradeParticipationInterest() in UserManagement", "User for password reset not found! SubjectIdentifier: " . $subjectIdentifier);
                        $json = array(
                            "status" => "error",
                            "message" => "User for password reset not found!"
                        );
                        echo json_encode($json);
                        die();
                    } else if ($resultPasswordReset == -2) {
                        logError($webroot . "/private/errorLog.txt", "upgradeParticipationInterest() in UserManagement", "DB statement failed! SubjectIdentifier: " . $subjectIdentifier);
                        $json = array(
                            "status" => "error",
                            "message" => "DB statement failed!"
                        );
                        echo json_encode($json);
                        die();
                    } else if ($resultPasswordReset == -3) {
                        logError($webroot . "/private/errorLog.txt", "upgradeParticipationInterest() in UserManagement", "Password reset mail failed! SubjectIdentifier: " . $subjectIdentifier);
                        $json = array(
                            "status" => "error",
                            "message" => "Password reset mail failed!"
                        );
                        echo json_encode($json);
                        die();
                    } else if ($resultPasswordReset == 1) {
                        # Set the app value for this user to 1 as well
                        $sql = "UPDATE Subject SET app = 1 WHERE SubjectIdentifier = ?";
                        $stmt = $pdo->prepare($sql);
                        if ($stmt->execute(array($subjectIdentifier))) {
                            # Build email to send to ZBW
                            $subject = "DESIVE² - Nachträgliche Anmeldung zur App";
                            $preheader = "Nachträgliche Anmeldung zur App-Nutzung in der Studie 'DESIVE2'.";
                            $mailTitle = "Nachträgliche Anmeldung zu App";
                            ob_start();
                            include($webroot . "/private/resources/mail-templates/email-body-new-app-participant.html");
                            $body = ob_get_clean();

                            # Replace placeholders in email body
                            $body = str_replace("{{name}}", $name, $body);
                            $body = str_replace("{{email}}", $email, $body);
                            $body = str_replace("{{pseudonym}}", $subjectIdentifier, $body);

                            # Send mail via func
                            $mailstatus = sendMail(MAIL_FROM_ADDRESS, MAIL_FROM_NAME, MAIL_FROM_ADDRESS, MAIL_FROM_NAME, $subject, $preheader, $mailTitle, $body, $webroot, $webroot . "/private/errorLog.txt");

                            # Check if mail was sent successfully by reading the JSON in mailstatus
                            if ($mailstatus['status'] != "success") {
                                logError($webroot . "/private/errorLog.txt", "createUser() in UserManagement.php", "Mail for new interview participant  not sent. Mailstatus: " . $mailstatus['status'] . " - " . $mailstatus['message']);
                                exit();
                            }

                            $json = array(
                                "status" => "success",
                                "message" => "The value for app participation for " . $subjectIdentifier . " has been set to 1. App account created and password reset mail sent!"
                            );
                            echo json_encode($json);
                            die();
                        } else {
                            logError($webroot . "/private/errorLog.txt", "upgradeParticipationInterest() in UserManagement", "DB statement failed! (Update app = 1) SubjectIdentifier: " . $subjectIdentifier);
                            # Build JSON object
                            $json = array(
                                "status" => "error",
                                "message" => "DB statement failed"
                            );
                            echo json_encode($json);
                            die();
                        }
                    }
                }
                die();
            } else if ($row->app == 1 && $row->interview == 1) {
                # Build JSON object
                $json = array(
                    "status" => "error",
                    "message" => "The values for interview and app participation for " . $subjectIdentifier . " are already 1."
                );
                echo json_encode($json);
                die();
            }
        }
    } else {
        logError($webroot . "/private/errorLog.txt", "upgradeParticipationInterest() in UserManagement.php", "DB statement failed. User not found!");
        # Build JSON object
        $json = array(
            "status" => "error",
            "message" => "DB statement failed. User not found!"
        );
        echo json_encode($json);
        die();
    }
}
# Set new password for app-user
if ($action == "setPassword") {
    # Validate whether the POST of token and password are empty. Exit with -1 if so.
    if (empty($_POST['token']) || empty($_POST['password'])) {
        $answerJSON = array(
            "status" => "400",
            "body" => array(
                "message" => "Bad Request - POST parameter token or password empty!"
            )
        );
        echo json_encode($answerJSON);
        exit();
    }
    $pass = $_POST["password"];
    $token = $_POST["token"];

    # Check token via TokenAuth function
    $tokenAuthRes = TokenAuth($pdo, $token, $webroot . "/private/errorLog.txt");

    # If the token is valid, a user-id is returned and the password can be set
    if ($tokenAuthRes > 0) {
        # Hash the password with php function password_hash and PASSWORD_BCRYPT
        $pass = password_hash($pass, PASSWORD_DEFAULT, $password_options);

        # Update the password in the database for the user where the AppUser.idAppUser = $TokenAuthRes
        $sql = "UPDATE AppUser SET enc_password  = ?, hasSetPassword = 1 WHERE idAppUser = ?";

        $stmt = $pdo->prepare($sql);
        if ($stmt->execute(array($pass, $tokenAuthRes))) {
            $answerJSON = array(
                "status" => "200",
                "body" => array(
                    "message" => "Password set successfully!"
                )
            );
            echo json_encode($answerJSON);
            exit();
        } else {
            logError($webroot . "/private/errorLog.txt", "setPassword() in UserManagement.php", "DB statement failed! (Set enc_password) for idAppUser: " . $tokenAuthRes);
            $answerJSON = array(
                "status" => "500",
                "body" => array(
                    "message" => "Internal Server Error - SQL query failed!"
                )
            );
            echo json_encode($answerJSON);
            exit();
        }
    } else {
        $answerJSON = array(
            "status" => "401",
            "body" => array(
                "message" => "Unauthorized - Token invalid!"
            )
        );
        echo json_encode($answerJSON);
    }
    exit();
}
# Receive and save IBAN
# Return 1 if the IBAN was saved successfully, -1 if the token is invalid, -2 if sql query failed
if ($action == "uploadIBANandName") {

    # Check whether idAppUser, iban and token are empty in POST request
    if (empty($_POST['token']) || !isset($_POST['iban']) || !isset($_POST['name'])) {
        $answerJSON = array(
            "status" => "400",
            "body" => array(
                "message" => "Bad Request - POST parameter token, iban or name empty!"
            )
        );
        echo json_encode($answerJSON);
        exit();
    }

    # Get the user-id and IBAN from POST
    $iban = $_POST["iban"];
    $token = $_POST["token"];
    $name = $_POST["name"];

    # Remove spaces from IBAN
    $iban = str_replace(' ', '', $iban);

    # Check if IBAN is only alphanumeric
    if ($iban != "") {
        if (!ctype_alnum($iban)) {
            $answerJSON = array(
                "status" => "400",
                "body" => array(
                    "message" => "Bad Request - IBAN contains invalid characters!"
                )
            );
            echo json_encode($answerJSON);
            exit();
        }
    }

    # Remove any non chars or spaces (UTF-8) from name based on https://stackoverflow.com/a/55326624
    $name = preg_replace('/[^\p{L}\p{M}\s-]+/u', '', $name);

    # Check token via TokenAuth function
    $tokenAuthRes = TokenAuth($pdo, $token, $pathToErrorLog);

    # If the token is valid, save the IBAN and update the database ($user contains the user-id in this case)
    if ($tokenAuthRes > 0) {
        # Get idSubject for idAppuser
        $resArray = getIdSubjectForIdAppuser($tokenAuthRes, $pdo, $pathToErrorLog);

        # Check if successfull
        if ($resArray['status'] == "error") {
            logError($pathToErrorLog, "uploadIBANandName() in UserManagement.php", "Could not get idSubject for idAppuser: " . $tokenAuthRes);
            # build json response
            $response = array(
                "status" => "500",
                "body" => array(
                    "message" => "Could not get idSubject for idAppuser: " . $tokenAuthRes
                )
            );
            echo json_encode($response);
            exit();
        } else if ($resArray['status'] == "success") {
            $idSubject = $resArray['idSubject'];
        } else {
            logError($pathToErrorLog, "uploadIBANandName() in UserManagement.php", "Could not get idSubject for idAppuser: " . $tokenAuthRes) . " error: " . $resArray['message'];
            # build json response
            $response = array(
                "status" => "500",
                "body" => array(
                    "message" => "Could not get idSubject for idAppuser: " . $tokenAuthRes . ". See error in errorLog.txt"
                )
            );
            echo json_encode($response);
            exit();
        }

        # call setIBAN function
        $resArraySetIBAN = setIBAN($idSubject, $iban, $pdo, ENCRYPTION_KEY, $pathToErrorLog);

        # Check if successfull
        if ($resArraySetIBAN['status'] == "error") {
            logError($pathToErrorLog, "uploadIBANandName() in UserManagement.php", "Could not set IBAN for idSubject: " . $idSubject);
            # build json response
            $response = array(
                "status" => "500",
                "body" => array(
                    "message" => "Could not set IBAN for idSubject: " . $idSubject
                )
            );
            echo json_encode($response);
            exit();
        } else if ($resArraySetIBAN['status'] == "success") {
            # successful set IBAN
            # Continue
        } else {
            logError($pathToErrorLog, "uploadIBANandName() in UserManagement.php", "Could not set IBAN for idSubject: " . $idSubject) . " error: " . $resArraySetIBAN['message'];
            # build json response
            $response = array(
                "status" => "500",
                "body" => array(
                    "message" => "Could not set IBAN for idSubject: " . $idSubject . ". See error in errorLog.txt"
                )
            );
            echo json_encode($response);
            exit();
        }

        # call setName function
        $resArraySetName = setName($idSubject, $name, $pdo, ENCRYPTION_KEY, $pathToErrorLog);

        # Check if successfull
        if ($resArraySetName['status'] == "error") {
            logError($pathToErrorLog, "uploadIBANandName() in UserManagement.php", "Could not set name for idSubject: " . $idSubject);
            # build json response
            $response = array(
                "status" => "500",
                "body" => array(
                    "message" => "Could not set name for idSubject: " . $idSubject
                )
            );
            echo json_encode($response);
            exit();
        } else if ($resArraySetName['status'] == "success") {
            # name successfully set
            $response = array(
                "status" => "200",
                "body" => array(
                    "message" => "Name successfully set!"
                )
            );
            echo json_encode($response);
            exit();
        } else {
            logError($pathToErrorLog, "uploadIBANandName() in UserManagement.php", "Could not set name for idSubject: " . $idSubject) . " error: " . $resArraySetName['message'];
            # build json response
            $response = array(
                "status" => "500",
                "body" => array(
                    "message" => "Could not set name for idSubject: " . $idSubject . ". See error in errorLog.txt"
                )
            );
            echo json_encode($response);
            exit();
        }

    } else {
        # Invalid token
        $answerJSON = array(
            "status" => "401",
            "body" => array(
                "message" => "Unauthorized - Token invalid!"
            )
        );
        echo json_encode($answerJSON);
    }
    exit();
}

if ($action == "deleteUser") {
    # Validate whether the POST of token is empty. Return status 400 if so.
    if (empty($_POST['token'])) {
        $answerJSON = array(
            "status" => "400",
            "body" => array(
                "message" => "Bad Request - POST parameter token empty!"
            )
        );
        echo json_encode($answerJSON);
        exit();
    }
    $token = $_POST["token"];

    # Check token via TokenAuth function
    $tokenAuthRes = TokenAuth($pdo, $token, $webroot . "/private/errorLog.txt");

    # If the token is valid, a mail using sendMail() is send to the ZBW
    if ($tokenAuthRes > 0) {
        # Get the email address and name of the user via getEmailAddressandNameOfIDAppuser()
        $resArray = getEmailAddressAndNameOfIDAppuser($tokenAuthRes, ENCRYPTION_KEY, $pdo, $pathToErrorLog);

        # Check if successful
        if ($resArray['status'] == "error") {
            logError($pathToErrorLog, "deleteUser() in UserManagement.php", "Could not get email address of idAppuser: " . $tokenAuthRes);
            # build json response
            $response = array(
                "status" => "500",
                "message" => "Could not get email address of idAppuser: " . $tokenAuthRes
            );
            echo json_encode($response);
            exit();
        } else if ($resArray['status'] == "success") {
            $email = $resArray['email'];
            $name = $resArray['name'];
        } else {
            logError($pathToErrorLog, "deleteUser() in UserManagement.php", "Could not get email address of idAppuser: " . $tokenAuthRes) . " error: " . $resArray['message'];
            # build json response
            $response = array(
                "status" => "500",
                "message" => "Could not get email address of idAppuser: " . $tokenAuthRes . " error: " . $resArray['message']
            );
            echo json_encode($response);
            exit();
        }

        # Get idSubject for idAppuser
        $resArray = getIdSubjectForIdAppuser($tokenAuthRes, $pdo, $pathToErrorLog);

        # Check if successful
        # Check if successfull
        if ($resArray['status'] == "error") {
            logError($pathToErrorLog, "deleteUser() in UserManagement.php", "Could not get idSubject for idAppuser: " . $tokenAuthRes);
            # build json response
            $response = array(
                "status" => "500",
                "message" => "Could not get idSubject for idAppuser: " . $tokenAuthRes
            );
            echo json_encode($response);
            exit();
        } else if ($resArray['status'] == "success") {
            $idSubject = $resArray['idSubject'];
        } else {
            logError($pathToErrorLog, "deleteUser() in UserManagement.php", "Invalid status from getIdSubjectForIdAppuser() for idAppuser: " . $tokenAuthRes);
            # build json response
            $response = array(
                "status" => "500",
                "message" => "Invalid status from getIdSubjectForIdAppuser() for idAppuser: " . $tokenAuthRes
            );
            echo json_encode($response);
            exit();
        }

        # Send new password mail to user
        # Build email to send to user
        $subject = "DESIVE² - Datenlöschung angefordert";
        $preheader = "Ein User hat die Löschung seiner Daten angefordert.";
        $mailTitle = "Anfrage DSGVO Datenlöschung";
        ob_start();
        include($webroot . "/private/resources/mail-templates/email-body-user-deletion.html");
        $body = ob_get_clean();

        # Replace placeholders in email body
        $body = str_replace("{{name}}", $name, $body);
        $body = str_replace("{{email}}", $email, $body);
        $body = str_replace("{{idSubject}}", $idSubject, $body);

        # Send mail via func
        $mailstatus = sendMail(MAIL_FROM_ADDRESS, MAIL_FROM_NAME, MAIL_FROM_ADDRESS, MAIL_FROM_NAME, $subject, $preheader, $mailTitle, $body, $webroot, $pathToErrorLog);

        # Check if mail was sent successfully by reading the JSON in mailstatus
        if ($mailstatus['status'] != "success") {
            logError($webroot . "/private/errorLog.txt", "deleteUser() in UserManagement.php", "Data deletion mail could not be sent. Mailstatus: " . $mailstatus['status'] . " - " . $mailstatus['message']);
            # build json response
            $response = array(
                "status" => "500",
                "message" => "Internal Server Error - Mail could not be sent!"
            );
            echo json_encode($response);
            exit();
        }

        # build json response
        $response = array(
            "status" => "200",
            "message" => "Mail sent successfully!"
        );
        echo json_encode($response);

    } else {
        # Invalid token
        $answerJSON = array(
            "status" => "401",
            "body" => array(
                "message" => "Unauthorized - Token invalid!"
            )
        );
        echo json_encode($answerJSON);
    }
}
?>