<?php
# Path API\Endpoint\public\dbMask\resources\incentive-create-paymentlist.php

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
    logError($dbMaskLog, "incentive-create-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-create-paymentlist.php but could not connect to database.");
    logError($pathToErrorLog, "incentive-create-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-create-paymentlist.php but could not connect to database.");
    echo "Connection to Database failed. Please look at error log for more information.";
    exit;
}

# If GET-Parameter createPaymentList is set, create PaymentList
# if not, show form to create PaymentList
if (isset($_GET['createPaymentList'])) {
    # Get value from GET-Parameter
    $createPaymentList = $_GET['createPaymentList'];
} else {
    $createPaymentList = false;
}

if (!$createPaymentList) {

    # Log action
    logError($dbMaskLog, "incentive-create-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-create-paymentlist.php without GET-parameter");

    $output = "";

    # Get all Payments from Incentive_Paymnet where PaymentListID is NULL
    try {
        $stmt = $conn->prepare("SELECT ip.paymentID, ip.subjectID, AES_DECRYPT(ip.enc_name, :encryptionKey) as name, 
        AES_DECRYPT(ip.enc_email, :encryptionKey) as email, AES_DECRYPT(ip.enc_iban, :encryptionKey) as iban, 
        ip.statusTypeID, ist.statusTypeDescription, ip.additionalText, ip.created, ip.modified
        FROM Incentive_Payment as ip
        LEFT JOIN Incentive_StatusType as ist ON ip.statusTypeID = ist.statusTypeID
        LEFT JOIN Incentive_Position as pos ON ip.paymentID = pos.paymentID
        WHERE ip.statusTypeID = 1 AND ip.paymentListID IS NULL AND pos.paymentID IS NOT NULL
        GROUP BY ip.paymentID;
        ");
        $stmt->bindValue(':encryptionKey', ENCRYPTION_KEY);
        $stmt->execute();
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        # Log action
        logError($dbMaskLog, "incentive-create-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-create-paymentlist.php but could not get Payments from database. Error: " . $e->getMessage() . ".");
        logError($pathToErrorLog, "incentive-create-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-create-paymentlist.php but could not get Payments from database. Error: " . $e->getMessage() . ".");
        echo "Could not get Payments from database. Please look at error log for more information.";
        exit;
    }

    # Count number of payments
    $paymentCount = count($payments);

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

        $output .= "<tr bgcolor= #dbdbdb><td>" . $payment['paymentID'] . "</td>";
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

    #
    # Get all Payments from Incentive_Paymnet where PaymentListID is statusTypeID = 5 (created-iban-missing)
    #
    try {
        $stmt = $conn->prepare("SELECT
        *
    FROM
        (
        SELECT
            paymentID,
            subjectID,
            AES_DECRYPT(enc_name, :encryptionKey) AS name,
            AES_DECRYPT(enc_email, :encryptionKey) AS email,
            AES_DECRYPT(enc_iban, :encryptionKey) AS iban,
            ip.statusTypeID,
            ist.statusTypeDescription,
            additionalText,
            created,
            modified
        FROM
            Incentive_Payment AS ip
        LEFT JOIN Incentive_StatusType AS ist
        ON
            ip.statusTypeID = ist.statusTypeID
        WHERE
            ip.statusTypeID = 5 AND paymentListID IS NULL
    ) AS openPaymentsMissingIBAN
    LEFT JOIN `Subject` AS subj
    ON
        openPaymentsMissingIBAN.subjectID = subj.idSubject;");
        $stmt->bindValue(':encryptionKey', ENCRYPTION_KEY);
        $stmt->execute();
        $iban_missing_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        # Log action
        logError($dbMaskLog, "incentive-create-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-create-paymentlist.php but could not get Payments from database. Error: " . $e->getMessage() . ".");
        logError($pathToErrorLog, "incentive-create-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-create-paymentlist.php but could not get Payments from database. Error: " . $e->getMessage() . ".");
        echo "Could not get Payments from database. Please look at error log for more information.";
        exit;
    }

    # Count number of payments with statusTypeID = 5 (created-iban-missing)
    $paymentCountMissingIBAN = count($iban_missing_payments);

    $output_missing_IBAN = "";
    if ($paymentCountMissingIBAN > 0) {
        # Add vertical fat line
        $output_missing_IBAN .= "<tr><td colspan='9'><hr></td></tr>";
        $output_missing_IBAN .= "<h3>Offene Zahlungen ohne IBAN</h3>";
        $output_missing_IBAN .= "<p>Es gibt " . $paymentCountMissingIBAN . " offene Zahlungen ohne IBAN. Bitte überprüfen Sie die Zahlungen und weisen Sie ihnen eine IBAN zu. Eine Buchung zur Auszahlung ist für diese Zahlungen derzeit nicht möglich.</p>";
        $output_missing_IBAN .= "<p>Die Zahlungen werden in der folgenden Tabelle angezeigt. Sie können die Zahlungen direkt bearbeiten, indem Sie auf den Button 'IBAN hinzufügen' klicken.</p>";
        $output_missing_IBAN .= "<p>Geben Sie auf der folgenden Seite im Feld 'IBAN' die für die aktuelle Zahlung zu nutzende IBAN an und klicken Sie auf 'Auszahlung anlegen'. Die Beträge können für diesen Vorgang 0 bleiben.</p>";
        $output_missing_IBAN .= "<table class='table table-striped table-bordered table-hover'><thead><tr><th scope='col' style='padding: 10px;'>ID</th><th scope='col' style='padding: 10px;'>Name</th><th scope='col' style='padding: 10px;'>E-Mail</th><th scope='col' style='padding: 10px;'>Status</th><th scope='col' style='padding: 10px;'>Zusatztext</th><th scope='col' style='padding: 10px;'>IBAN hinzufügen</th></tr></thead><tbody>";

        foreach ($iban_missing_payments as $payment) {
            $output_missing_IBAN .= "<tr bgcolor= #dbdbdb><td style='padding: 10px;'>" . $payment['paymentID'] . "</td>";
            $output_missing_IBAN .= "<td style='padding: 10px;'>" . $payment['name'] . "</td>";
            $output_missing_IBAN .= "<td style='padding: 10px;'>" . $payment['email'] . "</td>";
            $output_missing_IBAN .= "<td style='padding: 10px;'>" . $payment['statusTypeDescription'] . "</td>";
            $output_missing_IBAN .= "<td style='padding: 10px;'>" . $payment['additionalText'] . "</td>";
            # Add form with hidden input field for SubjectIdentifier and submit button to incentive-details-participant.php
            $output_missing_IBAN .= "<td style='padding: 10px;'><form action='incentive-details-participant.php' method='post'><input type='hidden' name='SubjectIdentifier' value='" . $payment['SubjectIdentifier'] . "'><button id='showParticipantIncentive' type='submit' name='action' value='showParticipantIncentive' />IBAN hinzufügen</button></form></td></tr>";
        }

        $output_missing_IBAN .= "</tbody></table>";

    } else {
        echo "<h3>Keine offenen Zahlungen ohne IBAN</h3>";
    }

    # Add button back to overview
    echo '<button onclick="location.href=\'../index.php\'" type="button">';
    echo 'Zurück zur Haupt-Verwaltungsseite';
    echo '</button>';
    echo '<br><br>';

    echo $output;

    if ($paymentCount == 0) {
        echo "Keine offenen Zahlungen vorhanden.";
    } else {
        echo '<br><br>';

        # Add button to create paymentList with GET parameter
        echo '<button onclick="location.href=\'incentive-create-paymentlist.php?createPaymentList=true\'" type="button">';
        echo 'Zahlungsliste erstellen';
        echo '</button>';
    }

    echo '<br><br>';
    echo $output_missing_IBAN;

} else {
    # Create new PaymentList
    # Update all Payments with PaymentListID and statusTypeID WHERE PaymentListID is NULL
    # Echo list of all Payments with PaymentListID 

    # Create new PaymentList
    try {
        $sql = "INSERT INTO Incentive_PaymentList (paymentListID, created, modified) VALUES (NULL, NOW(), NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $paymentListID = $conn->lastInsertId();
    } catch (PDOException $e) {
        # Log action
        logError($dbMaskLog, "incentive-create-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-create-paymentlist.php but could not create new PaymentList. Error: " . $e->getMessage() . ".");
        logError($pathToErrorLog, "incentive-create-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-create-paymentlist.php but could not create new PaymentList. Error: " . $e->getMessage() . ".");
        echo "Could not create new PaymentList. Please look at error log for more information.";
        exit;
    }

    # Update all Payments with PaymentListID and statusTypeID WHERE PaymentListID is NULL AND statusTypeID = 1 (created)
    try {
        $sql = "UPDATE Incentive_Payment SET paymentListID = :paymentListID, statusTypeID = 2
        WHERE paymentListID IS NULL AND statusTypeID = 1 AND paymentID IN (
            SELECT DISTINCT ip.paymentID
            FROM Incentive_Payment as ip
            LEFT JOIN Incentive_Position as pos ON ip.paymentID = pos.paymentID
            WHERE pos.paymentID IS NOT NULL
        );
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':paymentListID', $paymentListID);
        $stmt->execute();
    } catch (PDOException $e) {
        # Log action
        logError($dbMaskLog, "incentive-create-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-create-paymentlist.php but could not update Payments. Error: " . $e->getMessage() . ".");
        logError($pathToErrorLog, "incentive-create-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-create-paymentlist.php but could not update Payments. Error: " . $e->getMessage() . ".");
        echo "Could not update Payments. Please look at error log for more information.";
        exit;
    }

    # Get all Payments with PaymentListID
    try {
        $sql = "SELECT  paymentID, subjectID, AES_DECRYPT(enc_name, :encryptionKey) as name, AES_DECRYPT(enc_email, :encryptionKey) as email, AES_DECRYPT(enc_iban, :encryptionKey) as iban, ip.statusTypeID, ist.statusTypeDescription, additionalText, created, modified FROM Incentive_Payment as ip LEFT JOIN Incentive_StatusType as ist ON ip.statusTypeID=ist.statusTypeID WHERE paymentListID=:paymentListID";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':encryptionKey', ENCRYPTION_KEY);
        $stmt->bindParam(':paymentListID', $paymentListID);
        $stmt->execute();
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        # Log action
        logError($dbMaskLog, "incentive-create-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-create-paymentlist.php but could not get Payments. Error: " . $e->getMessage() . ".");
        logError($pathToErrorLog, "incentive-create-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-create-paymentlist.php but could not get Payments. Error: " . $e->getMessage() . ".");
        echo "Could not get Payments. Please look at error log for more information.";
        exit;
    }

    $output = "";

    $output .= "<h2>Auszahlungsliste Nr. " . $paymentListID . "</h2>";

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

    echo '<br><br>';

    echo "Die Zahlungsliste wurde erfolgreich erstellt. Bitte überprüfen Sie die Daten und drucken Sie die Liste aus.";

    echo $output;

    echo '<br><br>';

    # Add button back to overview
    echo '<button onclick="location.href=\'../index.php\'" type="button">';
    echo 'Zurück zur Haupt-Verwaltungsseite';
    echo '</button>';
    echo '<br><br>';

    # Log action
    logError($dbMaskLog, "incentive-create-paymentlist.php", $_SERVER['REMOTE_USER'] . " requested incentive-create-paymentlist.php and created new PaymentList with ID " . $paymentListID . ".");

}
# Close connection
$conn = null;
?>