<?php
# Path API\Endpoint\public\dbMask\resources\incentive-details-participant.php

$webroot = dirname(dirname(dirname(dirname(__FILE__))));

include($webroot . '/private/key.php');

$pathToErrorLog = $webroot . "/private/errorLog.txt";
$dbMaskLog = $webroot . '/private/dbMaskLog.txt';

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/private/resources/api-functionality/functions.php");

# Try to get action parameter from URL and if it is not set, echo webform
if (!empty($_POST['action'])) {
    $action = $_POST['action'];
} else {
    # Log action
    logError($dbMaskLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php without action parameter.");

    echo "No action parameter set.";
    # echo button to go back to $webroot/public/dbMask/index.php
    echo "<br><br><a href='/dbMask/index.php' class='btn btn-primary'>Zurück</a>";
    exit;
}

# build PDO
try {
    $conn = new PDO("mysql:host=" . SERVERNAME . ";dbname=" . DBNAME, USERNAME, PASSWORD);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    # Log action
    logError($dbMaskLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php but could not connect to database.");
    logError($pathToErrorLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php but could not connect to database.");
    echo "Connection to Database failed. Please look at error log for more information.";
    exit;
}

# If $action is set, check if it is a valid action
if ($action == "showParticipantIncentive") {
    # Get SubjectIdentifier from POST
    if (!empty($_POST['SubjectIdentifier'])) {
        $subjectIdentifier = $_POST['SubjectIdentifier'];
    } else {
        # Log action
        logError($dbMaskLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action showParticipantIncentive but without SubjectIdentifier parameter.");
        logError($pathToErrorLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action showParticipantIncentive but without SubjectIdentifier parameter.");

        echo "No SubjectIdentifier parameter set.";
        exit;
    }

    $userInformation = getParticipantInformationByPseudonym($subjectIdentifier, ENCRYPTION_KEY, $conn, $pathToErrorLog);
    # Check if successful
    if ($userInformation['status'] == 'success') {
        $name = $userInformation['data']['name'];
        $email = $userInformation['data']['email'];
        $iban = $userInformation['data']['iban'];
        $app = $userInformation['data']['app'];
        $interview = $userInformation['data']['interview'];
    } else {
        # Log action
        logError($dbMaskLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #showParticipantIncentive but SubjectIdentifier " . $subjectIdentifier . " does not exist or another problem occured.");
        logError($pathToErrorLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #showParticipantIncentive but SubjectIdentifier " . $subjectIdentifier . " does not exist or another problem occured.");

        echo "SubjectIdentifier " . $subjectIdentifier . " does not exist or another problem occured.";
        exit;
    }

    if ($app == 1) {
        # Get idAppUser from SubjectIdentifier
        $idAppUser = getIDAppUserByPseudonym($subjectIdentifier, $conn, $pathToErrorLog);

        # Check if successful
        if ($idAppUser['status'] == 'success') {
            $idAppUser = $idAppUser['data'];
        } else {
            # Log action
            logError($dbMaskLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #showParticipantIncentive but could not get idAppUser by SubjectIdentifier " . $subjectIdentifier . ".");
            logError($pathToErrorLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #showParticipantIncentive but could not get idAppUser by SubjectIdentifier " . $subjectIdentifier . ".");

            echo "Could not get idAppUser by SubjectIdentifier " . $subjectIdentifier . ".";
            exit;
        }

        # Get number of completed surveys by idAppUser using getNumberOfSurveysForIdAppUser()
        $numberOfSurveys = getNumberOfSurveysForIdAppUser($idAppUser, $conn, $pathToErrorLog);

        # Check if successful
        if ($numberOfSurveys['status'] == 'success') {
            $numberOfSurveys = $numberOfSurveys['surveyCount'];
        } else {
            # Log action
            logError($dbMaskLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #showParticipantIncentive but could not get number of surveys by idAppUser " . $idAppUser . ".");
            logError($pathToErrorLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #showParticipantIncentive but could not get number of surveys by idAppUser " . $idAppUser . ".");

            echo "Could not get number of surveys by idAppUser " . $idAppUser . ".";
            exit;
        }

        # Get number of uploads by idAppUser using getNumberOfUploadsForIdAppUser()
        $numberOfUploads = getNumberOfUploadsForIdAppUser($idAppUser, $conn, $pathToErrorLog);

        # Check if successful
        if ($numberOfUploads['status'] == 'success') {
            $numberOfUploads = $numberOfUploads['uploadCount'];
        } else {
            # Log action
            logError($dbMaskLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #showParticipantIncentive but could not get number of uploads by idAppUser " . $idAppUser . ".");
            logError($pathToErrorLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #showParticipantIncentive but could not get number of uploads by idAppUser " . $idAppUser . ".");

            echo "Could not get number of uploads by idAppUser " . $idAppUser . ".";
            exit;
        }
    } else {
        $numberOfSurveys = 0;
        $numberOfUploads = 0;
    }

    # Get body of page
    $bodytop = file_get_contents($webroot . "/public/dbMask/resources/incentive-details-participant.html");

    # Replace placeholders
    $bodytop = str_replace("{{pseudonym}}", $subjectIdentifier, $bodytop);
    $bodytop = str_replace("{{name}}", $name, $bodytop);
    $bodytop = str_replace("{{email}}", $email, $bodytop);
    $bodytop = str_replace("{{numberOfSurveys}}", $numberOfSurveys, $bodytop);
    $bodytop = str_replace("{{numberOfUploads}}", $numberOfUploads, $bodytop);
    # Check if iban isset and not empty
    if (isset($iban) && !empty($iban)) {
        $bodytop = str_replace("{{iban}}", $iban, $bodytop);
    } else {
        $bodytop = str_replace("{{iban}}", "", $bodytop);
    }

    # get payments by pseudonym
    $payments = getPaymentsByPseudonym($subjectIdentifier, ENCRYPTION_KEY, $conn, $pathToErrorLog);

    # Check if successful
    if ($payments['status'] == 'success') {
        $payments = $payments['data'];
    } else {
        # Log action
        logError($dbMaskLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #showParticipantIncentive but SubjectIdentifier " . $subjectIdentifier . " does not exist or another problem occured.");
        logError($pathToErrorLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #showParticipantIncentive but SubjectIdentifier " . $subjectIdentifier . " does not exist or another problem occured.");

        echo "SubjectIdentifier " . $subjectIdentifier . " does not exist or another problem occured.";
        exit;
    }

    # Iterate over payments, create html table and echo it
    $table = "<table class='table table-striped table-bordered table-hover'><thead><tr>
    <th scope='col' style='padding: 10px;'>PaymentID</th>
    <th scope='col' style='padding: 10px;'>Name</th>
    <th scope='col' style='padding: 10px;'>Email</th>
    <th scope='col' style='padding: 10px;'>IBAN</th>
    <th scope='col' style='padding: 10px;'>PaymentListID</th>
    <th scope='col' style='padding: 10px;'>Status</th>
    <th scope='col' style='padding: 10px;'>Kommentar</th>
    <th scope='col' style='padding: 10px;'>Buchungsdatum (angelegt)</th>
    <th scope='col' style='padding: 10px;'>Buchungsdatum (geändert)</th>
    </tr></thead><tbody>";

    # Create list to add up payments per type
    $paymentsPerType = array();

    foreach ($payments as $payment) {
        $table .= "<tr bgcolor= #dbdbdb><td style='padding: 10px;'>" . $payment['paymentID'] . "</td><td style='padding: 10px;'>" . $payment['name'] . "</td><td style='padding: 10px;'>" . $payment['email'] . "</td><td style='padding: 10px;'>" . $payment['iban'] . "</td><td style='padding: 10px;'>" . $payment['paymentListID'] . "</td><td style='padding: 10px;'>" . $payment['statusTypeDescription'] . "</td><td style='padding: 10px;'>" . $payment['additionalText'] . "</td><td style='padding: 10px;'>" . $payment['created'] . "</td><td style='padding: 10px;'>" . $payment['modified'] . "</td></tr>";

        # get payment positions by paymentID
        $paymentPositions = getPaymentPositionsByPaymentID($payment['paymentID'], $conn, $pathToErrorLog);

        # Check if successful
        if ($paymentPositions['status'] == 'success') {
            $paymentPositions = $paymentPositions['data'];
        } else {
            # Log action
            logError($dbMaskLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #showParticipantIncentive but SubjectIdentifier " . $subjectIdentifier . " does not exist or another problem occured.");
            logError($pathToErrorLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #showParticipantIncentive but SubjectIdentifier " . $subjectIdentifier . " does not exist or another problem occured.");

            echo "SubjectIdentifier " . $subjectIdentifier . " does not exist or another problem occured.";
            exit;
        }

        # Iterate over paymentPositions, create html table and echo it
        $table .= "<tr><td colspan='9'><table class='table table-striped table-bordered table-hover'><thead><tr>
        <th scope='col'>PositionID</th>
        <th scope='col'>Typ</th>
        <th scope='col'>Betrag</th>
        <th scope='col'>Kommentar</th>
        <th scope='col'>created</th>
        <th scope='col'>modified</th>
        </tr></thead><tbody>";

        foreach ($paymentPositions as $paymentPosition) {
            $table .= "<tr><td>" . $paymentPosition['positionID'] . "</td><td>" . $paymentPosition['paymentTypeDescription'] . "</td><td>" . $paymentPosition['amount'] . " €</td><td>" . $paymentPosition['additionalText'] . "</td><td>" . $paymentPosition['created'] . "</td><td>" . $paymentPosition['modified'] . "</td></tr>";

            # Add up payments per type
            if (isset($paymentsPerType[$paymentPosition['paymentTypeDescription']])) {
                $paymentsPerType[$paymentPosition['paymentTypeDescription']] += $paymentPosition['amount'];
            } else {
                $paymentsPerType[$paymentPosition['paymentTypeDescription']] = $paymentPosition['amount'];
            }
        }
        $table .= "</tbody></table><br><br></td></tr>";

    }
    $table .= "</tbody></table>";

    # Given the following payment types:
    # Interview, Umfrage, Uploads, Experimente
    # Get the sum of all payments per type and put them into variables
    $incentivePaidInterview = 0;
    $incentivePaidSurvey = 0;
    $incentivePaidUploads = 0;
    $incentivePaidExperiments = 0;
    $incentivePaidBonus = 0;

    if (isset($paymentsPerType['Interview'])) {
        $incentivePaidInterview = $paymentsPerType['Interview'];
    }
    if (isset($paymentsPerType['Umfragen'])) {
        $incentivePaidSurvey = $paymentsPerType['Umfragen'];
    }
    if (isset($paymentsPerType['Uploads'])) {
        $incentivePaidUploads = $paymentsPerType['Uploads'];
    }
    if (isset($paymentsPerType['Experimente'])) {
        $incentivePaidExperiments = $paymentsPerType['Experimente'];
    }
    if (isset($paymentsPerType['Bonus'])) {
        $incentivePaidBonus = $paymentsPerType['Bonus'];
    }

    # Calculate total incentive paid
    $incentivePaidTotal = $incentivePaidInterview + $incentivePaidSurvey + $incentivePaidUploads + $incentivePaidExperiments + $incentivePaidBonus;

    # Replace placeholders in bodytop with variables
    $bodytop = str_replace("{{incentivePaidInterview}}", $incentivePaidInterview, $bodytop);
    $bodytop = str_replace("{{incentivePaidSurvey}}", $incentivePaidSurvey, $bodytop);
    $bodytop = str_replace("{{incentivePaidUploads}}", $incentivePaidUploads, $bodytop);
    $bodytop = str_replace("{{incentivePaidExperiments}}", $incentivePaidExperiments, $bodytop);
    $bodytop = str_replace("{{incentivePaidBonus}}", $incentivePaidBonus, $bodytop);
    $bodytop = str_replace("{{incentivePaidTotal}}", $incentivePaidTotal, $bodytop);

    # echo bodytop
    echo $bodytop;
    echo "<br><br>";

    echo $table;
    echo "<br><br>";

    # Log action
    logError($dbMaskLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #showParticipantIncentive for SubjectIdentifier " . $subjectIdentifier . ".");
}
if ($action == "bookNewIncentive") {
    # Check if open Payment exist to append new positions to
    # If not, create new open payment
    # Append new positions to open payment

    # Get SubjectIdentifier, name, email, iban from POST and check if they are set
    if (!empty($_POST['subjectIdentifier']) && !empty($_POST['name']) && !empty($_POST['email'])) {
        $subjectIdentifier = $_POST['subjectIdentifier'];
        $name = $_POST['name'];
        $email = $_POST['email'];
    } else {
        # Log action
        logError($dbMaskLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #bookNewIncentive but no SubjectIdentifier/name/email was given.");
        logError($pathToErrorLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #bookNewIncentive but no SubjectIdentifier/name/email was given.");

        echo "No SubjectIdentifier/name/email was given.";

        exit;
    }

    # Check if  $_POST['saveIBAN'] is set to get checkbox value
    if (isset($_POST['saveIBAN'])) {
        $saveIBAN = true;
    } else {
        $saveIBAN = false;
    }

    # Log action
    logError($dbMaskLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #bookNewIncentive for SubjectIdentifier " . $subjectIdentifier . ".");

    # Try to get iban from POST
    # If not set, set $ibanAvailable to false
    if (!empty($_POST['iban'])) {
        if ($_POST['iban'] != "") {
            $ibanAvailable = true;
            $iban = $_POST['iban'];
        } else {
            $ibanAvailable = false;
            $iban = "";
        }
    } else {
        $ibanAvailable = false;
        $iban = "";
    }

    # Get incentive values and comments from POST
    # Set to 0 oder "" if not set
    if (!empty($_POST['interview_incentive'])) {
        $interview_incentive = $_POST['interview_incentive'];
    } else {
        $interview_incentive = 0;
    }
    if (!empty($_POST['interview_info'])) {
        $interview_info = $_POST['interview_info'];
    } else {
        $interview_info = "";
    }
    if (!empty($_POST['survey_incentive'])) {
        $survey_incentive = $_POST['survey_incentive'];
    } else {
        $survey_incentive = 0;
    }
    if (!empty($_POST['survey_info'])) {
        $survey_info = $_POST['survey_info'];
    } else {
        $survey_info = "";
    }
    if (!empty($_POST['upload_incentive'])) {
        $uploads_incentive = $_POST['upload_incentive'];
    } else {
        $uploads_incentive = 0;
    }
    if (!empty($_POST['upload_info'])) {
        $uploads_info = $_POST['upload_info'];
    } else {
        $uploads_info = "";
    }
    if (!empty($_POST['experiment_incentive'])) {
        $experiment_incentive = $_POST['experiment_incentive'];
    } else {
        $experiment_incentive = 0;
    }
    if (!empty($_POST['experiment_info'])) {
        $experiment_info = $_POST['experiment_info'];
    } else {
        $experiment_info = "";
    }
    if (!empty($_POST['bonus_incentive'])) {
        $bonus_incentive = $_POST['bonus_incentive'];
    } else {
        $bonus_incentive = 0;
    }
    if (!empty($_POST['bonus_info'])) {
        $bonus_info = $_POST['bonus_info'];
    } else {
        $bonus_info = "";
    }

    # Check if any value is below 0 and exit with error message if so
    if ($interview_incentive < 0 || $survey_incentive < 0 || $uploads_incentive < 0 || $experiment_incentive < 0 || $bonus_incentive < 0) {
        # Log action
        logError($dbMaskLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #bookNewIncentive but at least one incentive value is below 0.");
        logError($pathToErrorLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #bookNewIncentive but at least one incentive value is below 0.");

        echo "At least one incentive value is below 0. No booking was made.";
        # echo Button to go to incentive-details-participant.php with action #showParticipantIncentive and POST SubjectIdentifier
        echo "<br><br><form action='incentive-details-participant.php' method='post'><input type='hidden' name='action' value='showParticipantIncentive'><input type='hidden' name='SubjectIdentifier' value='" . $subjectIdentifier . "'><input type='submit' value='Zurück zur Übersicht'></form>";

        exit;
    }

    # Get user information
    $userInformation = getParticipantInformationByPseudonym($subjectIdentifier, ENCRYPTION_KEY, $conn, $pathToErrorLog);
    # Check if successful
    if ($userInformation['status'] == 'success') {
        $idSubject = $userInformation['data']['idSubject'];
    } else {
        # Log action
        logError($dbMaskLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #bookNewIncentive but SubjectIdentifier " . $subjectIdentifier . " does not exist or another problem occured.");
        logError($pathToErrorLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #bookNewIncentive but SubjectIdentifier " . $subjectIdentifier . " does not exist or another problem occured.");

        echo "SubjectIdentifier " . $subjectIdentifier . " does not exist or another problem occured.";
        exit;
    }

    # Get all open payments and check if one exists for this subject
    $openPayments = getAllOpenPayments($conn, $pathToErrorLog);

    # Check if successful
    if ($openPayments['status'] == 'success') {
        $openPayments = $openPayments['data'];
    } else {
        # Log action
        logError($dbMaskLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #bookNewIncentive but no open payments exist or another problem occured.");
        logError($pathToErrorLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #bookNewIncentive but no open payments exist or another problem occured.");

        echo "No open payments exist or another problem occured.";
        exit;
    }

    # Check if open payment exists for this subject
    # Assumes that there is only one open payment per subject, selects the first one if there are multiple
    $openPaymentExists = false;
    $openPaymentID = 0;
    foreach ($openPayments as $openPayment) {
        if ($openPayment['subjectID'] == $idSubject) {
            $openPaymentExists = true;
            $openPaymentID = $openPayment['paymentID'];
            break;
        }
    }

    # If no open payment exists for this subject, create new open payment
    if (!$openPaymentExists) {
        $newPayment = createNewOpenPayment($idSubject, $name, $email, $iban, "", ENCRYPTION_KEY, $conn, $pathToErrorLog, $ibanAvailable);
        # Check if successful
        if ($newPayment['status'] == 'success') {
            $openPaymentID = $newPayment['data']['paymentID'];
        } else {
            # Log action
            logError($dbMaskLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #bookNewIncentive but no open payments exist and a new one could not be created.");
            logError($pathToErrorLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #bookNewIncentive but no open payments exist and a new one could not be created.");

            echo "No open payments exist and a new one could not be created.";
            exit;
        }
    } else {
        # Update name, email and iban of open payment
        $updatePayment = updateOpenPayment($openPaymentID, $name, $email, $iban, "", ENCRYPTION_KEY, $conn, $pathToErrorLog, $ibanAvailable);

        # Check if successful
        if ($updatePayment['status'] != 'success') {
            # Log action
            logError($dbMaskLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #bookNewIncentive but open payment could not be updated.");
            logError($pathToErrorLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #bookNewIncentive but open payment could not be updated.");

            echo "Open payment could not be updated.";
            exit;
        }
    }

    # Append new positions to open payment
    # For each incentive, check if it is > 0 and append to open payment
    if ($interview_incentive > 0) {
        $appendInterview = createNewPaymentPosition($openPaymentID, "1", $interview_incentive, $interview_info, $conn, $pathToErrorLog);
        # Check if successful
        if ($appendInterview['status'] != 'success') {
            # Log action
            logError($dbMaskLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #bookNewIncentive but incentive for interview could not be appended to open payment.");
            logError($pathToErrorLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #bookNewIncentive but incentive for interview could not be appended to open payment.");

            echo "Incentive for interview could not be appended to open payment.";
            exit;
        }
    }
    if ($survey_incentive > 0) {
        $appendSurvey = createNewPaymentPosition($openPaymentID, "2", $survey_incentive, $survey_info, $conn, $pathToErrorLog);
        # Check if successful
        if ($appendSurvey['status'] != 'success') {
            # Log action
            logError($dbMaskLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #bookNewIncentive but incentive for survey could not be appended to open payment.");
            logError($pathToErrorLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #bookNewIncentive but incentive for survey could not be appended to open payment.");

            echo "Incentive for survey could not be appended to open payment.";
            exit;
        }
    }
    if ($uploads_incentive > 0) {
        $appendUploads = createNewPaymentPosition($openPaymentID, "3", $uploads_incentive, $uploads_info, $conn, $pathToErrorLog);
        # Check if successful
        if ($appendUploads['status'] != 'success') {
            # Log action
            logError($dbMaskLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #bookNewIncentive but incentive for uploads could not be appended to open payment.");
            logError($pathToErrorLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #bookNewIncentive but incentive for uploads could not be appended to open payment.");

            echo "Incentive for uploads could not be appended to open payment.";
            exit;
        }
    }
    if ($experiment_incentive > 0) {
        $appendOther = createNewPaymentPosition($openPaymentID, "4", $experiment_incentive, $experiment_info, $conn, $pathToErrorLog);
        # Check if successful
        if ($appendOther['status'] != 'success') {
            # Log action
            logError($dbMaskLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #bookNewIncentive but incentive for other could not be appended to open payment.");
            logError($pathToErrorLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #bookNewIncentive but incentive for other could not be appended to open payment.");

            echo "Incentive for other could not be appended to open payment.";
            exit;
        }
    }
    if ($bonus_incentive > 0) {
        $appendBonus = createNewPaymentPosition($openPaymentID, "5", $bonus_incentive, $bonus_info, $conn, $pathToErrorLog);
        # Check if successful
        if ($appendBonus['status'] != 'success') {
            # Log action
            logError($dbMaskLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #bookNewIncentive but incentive for bonus could not be appended to open payment.");
            logError($pathToErrorLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #bookNewIncentive but incentive for bonus could not be appended to open payment.");

            echo "Incentive for bonus could not be appended to open payment.";
            exit;
        }
    }

    # If $saveIBAN is true, call setIBAN function to save iban
    if ($saveIBAN) {
        # Get idSubject from SubjectIdentifier
        $idSubject = getIDSubjectByPseudonym($subjectIdentifier, $conn, ENCRYPTION_KEY, $pathToErrorLog);

        # Check if successful
        if ($idSubject['status'] == 'success') {
            $idSubject = $idSubject['data']['idSubject'];
        } else {
            # Log action
            logError($dbMaskLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " tried to save IBAN for SubjectIdentifier " . $subjectIdentifier . " but could not get idSubject.");
            logError($pathToErrorLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " tried to save IBAN for SubjectIdentifier " . $subjectIdentifier . " but could not get idSubject.");

            echo "IBAN could not be saved.";
            exit;
        }

        $setIBAN = setIBAN($idSubject, $iban, $conn, ENCRYPTION_KEY, $pathToErrorLog);
        # Check if successful
        if ($setIBAN['status'] != 'success') {
            # Log action
            logError($dbMaskLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " tried to save IBAN for SubjectIdentifier " . $subjectIdentifier . " with idSubject " . $idSubject . " but it could not be saved.");
            logError($pathToErrorLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " tried to save IBAN for SubjectIdentifier " . $subjectIdentifier . " with idSubject " . $idSubject . " but it could not be saved.");

            echo "IBAN could not be saved.";
            exit;
        }
    }

    # Log action
    logError($dbMaskLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #bookNewIncentive for subject " . $idSubject . " and created/modifed open payment " . $openPaymentID . ".");

    if ($ibanAvailable) {
        # echo success
        echo "Die Buchung war erfolgreich.";
    } else {
        # echo success
        echo "Die Buchung war erfolgreich. Bitte beachten Sie, dass die IBAN nicht hinterlegt ist. Bitte kontaktieren Sie den/die Teilnehmer*in, um die IBAN zu erhalten. Eine Auszahlung ist derzeit nicht möglich.";
    }

    # echo Button to go to incentive-details-participant.php with action #showParticipantIncentive and POST SubjectIdentifier
    echo "<br><br><form action='incentive-details-participant.php' method='post'><input type='hidden' name='action' value='showParticipantIncentive'><input type='hidden' name='SubjectIdentifier' value='" . $subjectIdentifier . "'><input type='submit' value='Zurück zur Übersicht'></form>";
}

# Close connection
$conn = null;

?>