<?php
# Path API\Endpoint\public\dbMask\resources\incentive-show-paymentlist.php

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
    logError($dbMaskLog, "incentive-show-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-show-paymentlist.php but could not connect to database.");
    logError($pathToErrorLog, "incentive-show-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-show-paymentlist.php but could not connect to database.");
    echo "Connection to Database failed. Please look at error log for more information.";
    exit;
}

# Try to get action parameter from URL and if it is not set, echo webform
if (!empty($_POST['action'])) {
    $action = $_POST['action'];
} else if (!empty($_GET['action'])) {
    $action = $_GET['action'];
} else {
    # Log action
    logError($dbMaskLog, "incentive-show-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-show-paymentlist.php without action parameter.");

    echo "No action parameter set.";
    # echo button to go back to $webroot/public/dbMask/index.php
    echo "<br><br><a href='/dbMask/index.php' class='btn btn-primary'>Zurück</a>";
    exit;
}

# if $action is view-paymentlists show a list of all paymentlists
if ($action === "view-paymentlists") {
    # Get all paymentlists from database
    try {
        $stmt = $conn->prepare("SELECT * FROM Incentive_PaymentList");
        $stmt->execute();
        $paymentlists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        # Log action
        logError($dbMaskLog, "incentive-show-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-show-paymentlist.php but could not get paymentlists from database.");
        logError($pathToErrorLog, "incentive-show-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-show-paymentlist.php but could not get paymentlists from database.");
        echo "Could not get paymentlists from database. Please look at error log for more information.";
        exit;
    }

    # create variable to store html table
    $table = "<table class='table table-striped table-bordered table-hover'><thead><tr>
    <th scope='col'>PaymentListID</th>
    <th scope='col'>Auszahlung bestätigt am</th>
    <th scope='col'>created</th>
    <th scope='col'>modified</th>
    </tr></thead><tbody>";

    # loop through all paymentlists and add them to the table whilst making the PaymentListID a link to the paymentlist details
    foreach ($paymentlists as $paymentlist) {
        $table .= "<tr><td align=center><a href='incentive-show-paymentlist.php?action=view-single-paymentlist&paymentlistID=" . $paymentlist['paymentListID'] . "'>" . $paymentlist['paymentListID'] . "</a></td><td>" . $paymentlist['paymentConfirmedOn'] . "</td><td>" . $paymentlist['created'] . "</td><td>" . $paymentlist['modified'] . "</td></tr>";
    }

    # close table
    $table .= "</tbody></table>";

    # echo table
    echo $table;

    # echo button to go back to $webroot/public/dbMask/index.php
    echo '<br><br>';
    echo '<button onclick="location.href=\'../index.php\'" type="button">';
    echo 'Zurück zur Haupt-Verwaltungsseite';
    echo '</button>';
    echo '<br><br>';

    # Log action
    logError($dbMaskLog, "incentive-show-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-show-paymentlist.php with action=view-paymentlists.");

    exit;
}
if ($action == "view-single-paymentlist") {
    # Get the paymentlistID from the URL
    if (!empty($_GET['paymentlistID'])) {
        $paymentlistID = $_GET['paymentlistID'];
    } else {
        # Log action
        logError($dbMaskLog, "incentive-show-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-show-paymentlist.php without paymentlistID parameter.");

        echo "No paymentlistID parameter set.";
        # echo button to go back to $webroot/public/dbMask/index.php
        echo "<br><br><a href='/dbMask/index.php' class='btn btn-primary'>Zurück</a>";
        exit;
    }

    # Get the paymentlist from the database
    try {
        $stmt = $conn->prepare("SELECT * FROM Incentive_PaymentList WHERE paymentListID = :paymentlistID");
        $stmt->bindParam(':paymentlistID', $paymentlistID);
        $stmt->execute();
        $paymentlist = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        # Log action
        logError($dbMaskLog, "incentive-show-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-show-paymentlist.php but could not get paymentlist from database.");
        logError($pathToErrorLog, "incentive-show-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-show-paymentlist.php but could not get paymentlist from database.");
        echo "Could not get paymentlist from database. Please look at error log for more information.";
        exit;
    }

    # Get all payments from the database that are in the paymentlist
    try {
        $stmt = $conn->prepare("SELECT paymentID, subjectID, AES_DECRYPT(enc_name, :encryptionKey) as name, AES_DECRYPT(enc_email, :encryptionKey) as email, AES_DECRYPT(enc_iban, :encryptionKey) as iban, ip.statusTypeID, ist.statusTypeDescription, additionalText, created, modified FROM Incentive_Payment as ip LEFT JOIN Incentive_StatusType as ist ON ip.statusTypeID=ist.statusTypeID WHERE paymentListID = :paymentlistID");
        $stmt->bindValue(':encryptionKey', ENCRYPTION_KEY);
        $stmt->bindParam(':paymentlistID', $paymentlistID);
        $stmt->execute();
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        # Log action
        logError($dbMaskLog, "incentive-show-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-show-paymentlist.php but could not get payments from database.");
        logError($pathToErrorLog, "incentive-show-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-show-paymentlist.php but could not get payments from database.");
        echo "Could not get payments from database. Please look at error log for more information.";
        exit;
    }

    # Count number of payments
    $paymentCount = count($payments);

    $output = "";

    $output .= "<h2>Auszahlungsliste Nr. " . $paymentlist['paymentListID'] . "</h2>";

    # Iterate over payments, create html table and echo it
    $output .= "<table class='table table-striped table-bordered table-hover'><thead><tr>
<th scope='col' style='padding: 10px;'>PaymentID</th>
<th scope='col' style='padding: 10px;'>Name</th>
<th scope='col' style='padding: 10px;'>Email</th>
<th scope='col' style='padding: 10px;'>IBAN</th>
<th scope='col' style='padding: 10px;'>Summe</th>
<th scope='col' style='padding: 10px;'>Status</th>
<th scope='col' style='padding: 10px;'>Kommentar</th>
";

    $listSum = 0;

    foreach ($payments as $payment) {
        # Get all paymentPositions for this payment and sum them up
        try {
            $stmt = $conn->prepare("SELECT positionID, paymentID, ip.paymentTypeID, ipt.paymentTypeDescription, amount, additionalText created, modified FROM Incentive_Position as ip LEFT JOIN Incentive_PaymentType as ipt ON ip.paymentTypeID=ipt.paymentTypeID WHERE paymentID=:paymentID");
            $stmt->bindParam(':paymentID', $payment['paymentID']);
            $stmt->execute();
            $paymentPositions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            # Log action
            logError($dbMaskLog, "incentive-create-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-create-paymentlist.php but could not get PaymentPositions from database. Error: " . $e->getMessage() . ".");
            logError($pathToErrorLog, "incentive-create-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-create-paymentlist.php but could not get PaymentPositions from database. Error: " . $e->getMessage() . ".");
            echo "Could not get PaymentPositions from database. Please look at error log for more information.";
            exit;
        }

        $incentivePerType = array();

        # Iterate over paymentPositions, sum them up per paymentType and create html table
        foreach ($paymentPositions as $paymentPosition) {
            # Sum up incentive per paymentType
            if (array_key_exists($paymentPosition['paymentTypeDescription'], $incentivePerType)) {
                $incentivePerType[$paymentPosition['paymentTypeDescription']] += $paymentPosition['amount'];
            } else {
                $incentivePerType[$paymentPosition['paymentTypeDescription']] = $paymentPosition['amount'];
            }
        }

        # Get sum of all incentivePerType
        $sum = 0;
        foreach ($incentivePerType as $incentive) {
            $sum += $incentive;
        }
        $listSum += $sum;

        $output .= "<tr bgcolor= #dbdbdb><td style='padding: 10px;'>" . $payment['paymentID'] . "</td>";
        $output .= "<td style='padding: 10px;'>" . $payment['name'] . "</td>";
        $output .= "<td style='padding: 10px;'>" . $payment['email'] . "</td>";
        $output .= "<td style='padding: 10px;'>" . $payment['iban'] . "</td>";
        $output .= "<td style='padding: 10px;'>" . $sum . " €</td>";
        $output .= "<td style='padding: 10px;'>" . $payment['statusTypeDescription'] . "</td>";
        $output .= "<td style='padding: 10px;'>" . $payment['additionalText'] . "</td>";
        $output .= "</tr>";

        # Add indented table with values from $incentivePerType
        $output .= "<tr><td colspan='9'><table class='table table-striped table-bordered table-hover'><thead><tr><th scope='col'>Art</th><th scope='col'>Betrag</th></tr></thead><tbody>";

        foreach ($incentivePerType as $paymentType => $value) {
            $output .= "<tr><td>" . $paymentType . "</td><td>" . $value . " €</td></tr>";
        }

        $output .= "</tbody></table></td></tr>";

    }

    # Add horizontal fat line
    $output .= "<tr><td colspan='9'><hr></td></tr>";
    # Add sum of all payments in last row in Summe column
    $output .= "<tr><td colspan='4'>Summe</td><td>" . $listSum . " €</td><td colspan='4'></td></tr>";

    $output .= "</tbody></table>";

    # echo table
    echo $output;

    echo '<form action="incentive-show-paymentlist.php" method="post" style="margin: 10px;">
                <button id="view-paymentlists" type="submit" name="action" value="view-paymentlists" />Zurück zur Listenübersicht</button>
            </form>';

    # log action
    logError($dbMaskLog, "incentive-show-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-show-paymentlist.php for paymentlistID " . $paymentlistID . ".");

    exit;

}

# Close database connection
$conn = null;
?>