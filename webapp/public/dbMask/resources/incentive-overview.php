<?php
# Path API\Endpoint\public\dbMask\resources\incentive-overview.php

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
    logError($dbMaskLog, "incentives-overview.php", $_SERVER['REMOTE_USER'] . " requested incentives-overview.php but could not connect to database.");
    logError($pathToErrorLog, "incentives-overview.php", $_SERVER['REMOTE_USER'] . " requested incentives-overview.php but could not connect to database.");
    echo "Connection to Database failed. Please look at error log for more information.";
    exit;
}

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Druckeinstellungen</title>
    <style>
        @media print {
            @page {
                size: landscape;
            }
        }
        /* class works for table row */
        table tr.page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>";

# Iterate over payments, create html table and echo it
$table = "<table class='table table-striped table-bordered table-hover'><thead style='padding: 10px;'><tr>
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


# Get all SubjectIdentifier from Subject table and iterate over them
try {
    $stmt = $conn->prepare("SELECT SubjectIdentifier FROM Subject");
    $stmt->execute();
    $subjectIdentifiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    # Log action
    logError($dbMaskLog, "incentives-overview.php", $_SERVER['REMOTE_USER'] . " requested incentives-overview.php but could not get SubjectIdentifiers from database.");
    logError($pathToErrorLog, "incentives-overview.php", $_SERVER['REMOTE_USER'] . " requested incentives-overview.php but could not get SubjectIdentifiers from database.");
    echo "Could not get SubjectIdentifiers from database. Please look at error log for more information.";
    exit;
}

# create variable to store sum of all payments
$sumOfAllPayments = 0;

foreach ($subjectIdentifiers as $subjectIdentifier) {
    # get payments by pseudonym
    $payments = getPaymentsByPseudonym(implode($subjectIdentifier), ENCRYPTION_KEY, $conn, $pathToErrorLog);
    # Check if successful
    if ($payments['status'] == 'success') {
        $payments = $payments['data'];
    } else {
        # Log action
        logError($dbMaskLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #showParticipantIncentive but SubjectIdentifier " . $subjectIdentifier . " does not exist or another problem occured.");
        logError($pathToErrorLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with action #showParticipantIncentive but SubjectIdentifier " . $subjectIdentifier . " does not exist or another problem occured.");

        echo "SubjectIdentifier " . implode($subjectIdentifier) . " does not exist or another problem occured.";
        exit;
    }

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

            echo "SubjectIdentifier " . implode($subjectIdentifier) . " does not exist or another problem occured.";
            exit;
        }
        
        # Iterate over paymentPositions, create html table and echo it
        $table .= "<tr><td colspan='9'><table class='table table-striped table-bordered table-hover'><thead style='padding: 5px;'><tr>
        <th scope='col' style='padding: 5px;'>PositionID</th>
        <th scope='col' style='padding: 5px;'>Typ</th>
        <th scope='col' style='padding: 5px;'>Betrag</th>
        <th scope='col' style='padding: 5px;'>Kommentar</th>
        <th scope='col' style='padding: 5px;'>Angelegt</th>
        <th scope='col' style='padding: 5px;'>Verändert</th>
        </tr></thead><tbody>";

        $sumOfCurrentPayment = 0;

        foreach ($paymentPositions as $paymentPosition) {
            $table .= "<tr><td>" . $paymentPosition['positionID'] . "</td><td>" . $paymentPosition['paymentTypeDescription'] . "</td><td align=center>" . $paymentPosition['amount'] . " €</td><td>" . $paymentPosition['additionalText'] . "</td><td>" . $paymentPosition['created'] . "</td><td>" . $paymentPosition['modified'] . "</td></tr>";

            # Add up sum of current payment
            $sumOfCurrentPayment += $paymentPosition['amount'];

            # Add up payments per type
            if (isset($paymentsPerType[$paymentPosition['paymentTypeDescription']])) {
                $paymentsPerType[$paymentPosition['paymentTypeDescription']] += $paymentPosition['amount'];
            } else {
                $paymentsPerType[$paymentPosition['paymentTypeDescription']] = $paymentPosition['amount'];
            }
        }

        # Print sum of current payment into table in new line in Betrag column
        $table .= "<tr><td></td><td></td><td style='padding: 10px;'><b>Auszahlungssumme: " . $sumOfCurrentPayment . " €</b></td><td colspan='7'></td></tr>";

        $table .= "</tbody></table><br><br></td></tr>";
    }

    # if paymentsPerType is empty, do not create entry line
    if (!empty($paymentsPerType)) {
        # Create line for total amount per type
        $table .= "<tr><td colspan='9'><table class='table table-striped table-bordered table-hover'><thead><tr>
        <th scope='col'>Typ</th>
        <th scope='col'>Betrag</th>
        </tr></thead><tbody>";

        foreach ($paymentsPerType as $paymentType => $value) {
            $table .= "<tr><td>" . $paymentType . "</td><td>" . $value . " €</td></tr>";
        }
        $table .= "</tbody></table><br><br></td></tr>";

        # Create line for total amount
        $table .= "<tr><td colspan='9'><table class='table table-striped table-bordered table-hover'><thead><tr>
        <th scope='col'>Gesamt (Teilnehmer)</th>
        <th scope='col'>" . array_sum($paymentsPerType) . " €</th>
        </tr></thead><tbody></tbody></table><br><br></td></tr>";

        # Add dark line
        $table .= "<tr class='page-break'><td colspan='9'><hr></td></tr>";

        # add page break 
        #$table .= "<div style='page-break-after: always;'>";
    }

    $sumOfAllPayments += array_sum($paymentsPerType);

}

# Get total amount of all payments using getPaymentTotals function
$paymentTotals = getPaymentTotals($conn, $pathToErrorLog);

# Check if successful
if ($paymentTotals['status'] == 'success') {
    $paymentTotals = $paymentTotals['data'];
} else {
    # Log action
    logError($dbMaskLog, "incentive-overview.php", $_SERVER['REMOTE_USER'] . " requested incentive-overview.php but could not get total amount of all payments from database.");
    logError($pathToErrorLog, "incentive-overview.php", $_SERVER['REMOTE_USER'] . " requested incentive-overview.php but could not get total amount of all payments from database.");
    echo "Could not get total amount of all payments from database. Please look at error log for more information.";
    exit;
}

# Create line for total amount
$table .= "<tr><td colspan='9'><table class='table table-striped table-bordered table-hover'><thead><tr>
    <th scope='col'>Gesamtansprüche (alle Teilnehmer)</th>
    <th scope='col'>" . $sumOfAllPayments . " €</th>
    </tr></thead><tbody></tbody></table><br><br></td></tr>

    <tr><td colspan='9'><table class='table table-striped table-bordered table-hover'><thead><tr>
    <th scope='col'>Davon ausgezahlt: </th>
    <th scope='col'>" . $paymentTotals['total_amount_paid'] . " €</th>
    </tr></thead><tbody></tbody></table><br><br></td></tr>
    
    <tr><td colspan='9'><table class='table table-striped table-bordered table-hover'><thead><tr>
    <th scope='col'>Davon nicht ausgezahlt aufgrund fehlender oder falscher IBAN: </th>
    <th scope='col'>" . $paymentTotals['total_amount_iban_missing'] . " €</th>
    </tr></thead><tbody></tbody></table><br><br></td></tr>";

$table .= "</tbody></table>";

echo '<button onclick="location.href=\'../index.php\'" type="button">';
echo 'Zurück zur Haupt-Verwaltungsseite';
echo '</button>';
echo '<br><br>';


echo $table;

echo "</body>
</html>";

# Close connection
$conn = null;

# Log action
logError($dbMaskLog, "incentive-overview.php", $_SERVER['REMOTE_USER'] . " requested incentive-overview.php.");

?>