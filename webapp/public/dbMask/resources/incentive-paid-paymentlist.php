<?php
# Path API\Endpoint\public\dbMask\resources\incentive-paid-paymentlist.php

$webroot = dirname(dirname(dirname(dirname(__FILE__))));

include($webroot . '/private/key.php');

$pathToErrorLog = $webroot . "/private/errorLog.txt";
$dbMaskLog = $webroot . '/private/dbMaskLog.txt';

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/private/resources/api-functionality/functions.php");

# build PDO
try {
    $conn = new PDO("mysql:host=" . SERVERNAME . ";dbname=" . DBNAME, USERNAME, PASSWORD);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    # Log action
    logError($dbMaskLog, "incentive-paid-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-paid-paymentlist.php but could not connect to database.");
    logError($pathToErrorLog, "incentive-paid-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-paid-paymentlist.php but could not connect to database.");
    echo "Connection to Database failed. Please look at error log for more information.";
    exit;
}

# Try to get action parameter from POST and if it is not set, echo webform
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

# Try to get paymentlistID parameter from POST and if it is not set, echo error message
if (!empty($_POST['paymentListID'])) {
    $paymentlistID = $_POST['paymentListID'];
} else {
    # Log action
    logError($dbMaskLog, "incentive-paid-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-paid-paymentlist.php without paymentlistID parameter.");

    echo "No paymentlistID parameter set.";
    # echo button to go back to $webroot/public/dbMask/index.php
    echo "<br><br><a href='/dbMask/index.php' class='btn btn-primary'>Zurück</a>";
    exit;
}

if ($action == "paid-paymentlist") {
    # Check if paymentlist exists in Incentive_PaymentList and if not, echo error message and exit
    try {
        $stmt = $conn->prepare("SELECT paymentlistID FROM Incentive_PaymentList WHERE paymentlistID = :paymentlistID");
        $stmt->bindParam(':paymentlistID', $paymentlistID);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        # Log action
        logError($dbMaskLog, "incentive-paid-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-paid-paymentlist.php but could not select paymentlistID " . $paymentlistID . ". Error: " . $e->getMessage());
        logError($pathToErrorLog, "incentive-paid-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-paid-paymentlist.php but could not select paymentlistID " . $paymentlistID . ". Error: " . $e->getMessage());
        echo "Payment could not be confirmed. Please look at error log for more information.";
        exit;
    }

    if (empty($result)) {
        # Log action
        logError($dbMaskLog, "incentive-paid-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-paid-paymentlist.php but paymentlistID " . $paymentlistID . " does not exist.");

        echo "Die Liste Nr. " . $paymentlistID . " existiert nicht.";
        # echo button to go back to $webroot/public/dbMask/index.php
        echo "<br><br><a href='/dbMask/index.php' class='btn btn-primary'>Zurück</a>";
        exit;
    }

    # Check if paymentlist is already paid by checking if paymentConfirmedOn is NOT NULL and if yes, echo error message and exit
    try {
        $stmt = $conn->prepare("SELECT paymentConfirmedOn FROM Incentive_PaymentList WHERE paymentlistID = :paymentlistID");
        $stmt->bindParam(':paymentlistID', $paymentlistID);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        # Log action
        logError($dbMaskLog, "incentive-paid-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-paid-paymentlist.php but could not select paymentlistID " . $paymentlistID . ". Error: " . $e->getMessage());
        logError($pathToErrorLog, "incentive-paid-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-paid-paymentlist.php but could not select paymentlistID " . $paymentlistID . ". Error: " . $e->getMessage());
        echo "Payment could not be confirmed. Please look at error log for more information.";
        exit;
    }

    if (!empty($result[0]['paymentConfirmedOn'])) {
        # Log action
        logError($dbMaskLog, "incentive-paid-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-paid-paymentlist.php but paymentlistID " . $paymentlistID . " is already paid.");

        echo "Die Liste Nr. " . $paymentlistID . " wurde bereits ausgezahlt.";
        # echo button to go back to $webroot/public/dbMask/index.php
        echo "<br><br><a href='/dbMask/index.php' class='btn btn-primary'>Zurück</a>";
        exit;
    }

    # Set paymentConfirmedOn to current date WHERE paymentlistID = $paymentlistID
    try {
        $stmt = $conn->prepare("UPDATE Incentive_PaymentList SET paymentConfirmedOn = CURRENT_TIMESTAMP WHERE paymentlistID = :paymentlistID");
        $stmt->bindParam(':paymentlistID', $paymentlistID);
        $stmt->execute();
    } catch (PDOException $e) {
        # Log action
        logError($dbMaskLog, "incentive-paid-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-paid-paymentlist.php but could not update paymentlistID " . $paymentlistID . ". Error: " . $e->getMessage());
        logError($pathToErrorLog, "incentive-paid-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-paid-paymentlist.php but could not update paymentlistID " . $paymentlistID . ". Error: " . $e->getMessage());
        echo "Payment could not be confirmed. Please look at error log for more information.";
    }

    # Set statusTypeID in Incentive_Payment to 3 (paid) WHERE paymentlistID = $paymentlistID
    try {
        $stmt = $conn->prepare("UPDATE Incentive_Payment SET statusTypeID = 3 WHERE paymentlistID = :paymentlistID");
        $stmt->bindParam(':paymentlistID', $paymentlistID);
        $stmt->execute();
    } catch (PDOException $e) {
        # Log action
        logError($dbMaskLog, "incentive-paid-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-paid-paymentlist.php but could not update paymentlistID " . $paymentlistID . ". Error: " . $e->getMessage());
        logError($pathToErrorLog, "incentive-paid-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-paid-paymentlist.php but could not update paymentlistID " . $paymentlistID . ". Error: " . $e->getMessage());
        echo "Payment could not be confirmed. Please look at error log for more information.";
    }
    # Log action
    logError($dbMaskLog, "incentive-paid-paymentlist.php", $_SERVER['REMOTE_USER'] . " confirmed payment for paymentlistID " . $paymentlistID . ".");

    # echo success message and button to go back to $webroot/public/dbMask/index.php
    echo "Die Auszahlung für die Liste Nr. " . $paymentlistID . " wurde bestätigt.";
    echo "<br><br><a href='/dbMask/index.php' class='btn btn-primary'>Zurück</a>";
    exit;
}


# Close connection to database
$conn = null;
?>