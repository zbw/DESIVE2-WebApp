<?php

# Create webroot variable to directory above public and private folders
$webroot = dirname(dirname(dirname(dirname(__FILE__))));

include($webroot . '/private/key.php');

$pathToErrorLog = $webroot . "/private/errorLog.txt";
$dbMaskLog = $webroot . '/private/dbMaskLog.txt';

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/private/resources/api-functionality/functions.php");

# Check if GET action parameter isset
if (!isset($_GET['action'])) {
    # Log to dbMaskLog.txt
    logError($dbMaskLog, "masterlist-actions.php", $_SERVER['REMOTE_USER'] . " requested with no action specified");
    echo "Action is empty.";
    exit();
} else {
    $action = $_GET['action'];
}

if ($action == "resetPassword") {
    # Check if GET mail parameter isset
    if (!isset($_GET['mail'])) {
        echo "Mail is empty.";
        exit();
    } else {
        $mail = $_GET['mail'];
    }

    // Set API URL
    $url = API_BASE_URL . '/DBConnect.php?action=resetPassword';
    // build postdata with http_build_query
    $postdata = http_build_query(
        array(
            'email' => $mail,
            'api_key' => CREATION_API_KEY
        )
    );

    // build opts
    $opts = array(
        'http' =>
        array(
            'method' => 'POST',
            'header' => 'Content-type: application/x-www-form-urlencoded',
            'content' => $postdata
        )
    );

    // create context
    $context = stream_context_create($opts);

    // fetch result
    $result = file_get_contents($url, false, $context);

    # check if the result is "Success"
    if ($result == "Success") {
        echo "Passwort für " . $mail . " wurde zurückgesetzt und an die Mailadresse gesendet.";
        # Button um Tab zu schließen
        echo "<br>";
        echo "<br>";
        echo "<button onclick='window.close()'>Schließen</button>";
        # Log action
        logError($dbMaskLog, "masterlist-actions.php", $_SERVER['REMOTE_USER'] . " sucessfully requested resetPassword() for Email " . $mail . ".");
    } elseif ($result == "error") {
        echo "Es gab einen Fehler:";
        echo "<br>";
        echo $result;
        # Button um Tab zu schließen
        echo "<br>";
        echo "<br>";
        echo "<button onclick='window.close()'>Schließen</button>";

        logError($dbMaskLog, "masterlist-actions.php", $_SERVER['REMOTE_USER'] . " requested resetPassword() for Email " . $mail . " but got an error.");
        logError($pathToErrorLog, "resetPassword() in masterlist-actions.php", "Error while resetting password for " . $mail . ". Error: " . $result);
    }

    exit();
} else if ($action == "resetTimestamp") {
    # Check if GET subjectIdentifier parameter isset
    if (!isset($_GET['subjectIdentifier'])) {
        echo "subjectIdentifier is empty.";
        exit();
    } else {
        $subjectIdentifier = $_GET['subjectIdentifier'];
    }

    try {
        $pdo = new PDO("mysql:host=" . SERVERNAME . ";dbname=" . DBNAME, USERNAME, PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = 'UPDATE AppUser SET creation_timestamp = NOW() WHERE idSubject = (SELECT idSubject FROM `Subject` WHERE SubjectIdentifier = :subjectIdentifier)';
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':subjectIdentifier', $subjectIdentifier);
        $stmt->execute();

        echo "Timestamp für " . $subjectIdentifier . " wurde zurückgesetzt.";
        # Button um Tab zu schließen
        echo "<br>";
        echo "<br>";
        echo "<button onclick='window.close()'>Schließen</button>";
        # Log action
        logError($dbMaskLog, "masterlist-actions.php", $_SERVER['REMOTE_USER'] . " sucessfully requested resetTimestamp() for SubjectIdentifier " . $subjectIdentifier . ".");
        exit();
    } catch (PDOException $e) {
        echo "Es gab einen Fehler:";
        echo "<br>";
        # Button um Tab zu schließen
        echo "<br>";
        echo "<br>";
        echo "<button onclick='window.close()'>Schließen</button>";

        logError($dbMaskLog, "masterlist-actions.php", $_SERVER['REMOTE_USER'] . " requested resetTimestamp() for SubjectIdentifier " . $subjectIdentifier . " but got an error.");
        logError($pathToErrorLog, "resetTimestamp() in masterlist-actions.php", "Error while resetting timestamp for " . $subjectIdentifier . ". Error: " . $e->getMessage());
    }

    exit();
} else if ($action == "setFirstLoginTimestamp") {
    # Check if GET parameters idAppUser, first_login are set
    if (!isset($_GET['idAppUser']) || !isset($_GET['first_login'])) {
        # Log action
        logError($dbMaskLog, "masterlist-actions.php", $_SERVER['REMOTE_USER'] . " requested setFirstLoginTimestamp() but idAppUser or first_login is empty.");
        echo "idAppUser or first_login is empty.";
        exit();
    } else {
        $idAppUser = $_GET['idAppUser'];
        try {
            $first_login = new DateTime($_GET['first_login']);
            $first_login = $first_login->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            # Log action
            logError($dbMaskLog, "masterlist-actions.php", $_SERVER['REMOTE_USER'] . " requested setFirstLoginTimestamp() but first_login is not a valid timestamp.");
            echo "first_login is not a valid timestamp.";
            exit();
        }
    }


    try {
        $pdo = new PDO("mysql:host=" . SERVERNAME . ";dbname=" . DBNAME, USERNAME, PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = 'UPDATE AppUser SET first_login = :newFirstLoginTimestamp WHERE idAppUser = :idAppUser';
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':newFirstLoginTimestamp', $first_login);
        $stmt->bindParam(':idAppUser', $idAppUser);
        $stmt->execute();

        echo "Timestamp für idAppUser=" . $idAppUser . " wurde gesetzt auf " . $first_login . ".";
        # Button um Tab zu schließen
        echo "<br>";
        echo "<br>";
        echo "<button onclick='window.close()'>Schließen</button>";
        # Log action
        logError($dbMaskLog, "masterlist-actions.php", $_SERVER['REMOTE_USER'] . " sucessfully requested setFirstLoginTimestamp() for idAppUser " . $idAppUser . ".");
        exit();
    } catch (PDOException $e) {
        echo "Es gab einen Fehler. Bitte nutzen Sie die Logdatei.";
        echo "<br>";
        # Button um Tab zu schließen
        echo "<br>";
        echo "<br>";
        echo "<button onclick='window.close()'>Schließen</button>";

        logError($dbMaskLog, "masterlist-actions.php", $_SERVER['REMOTE_USER'] . " requested setFirstLoginTimestamp() for idAppUser " . $idAppUser . " but got an error.");
        logError($pathToErrorLog, "resetTimestamp() in masterlist-actions.php", "Error while resetting timestamp for idAppUser " . $idAppUser . ". Error: " . $e->getMessage());
    }
    exit();

} else if ($action == "resetUser") {
    # Check if GET resetUser parameter is set
    if (!isset($_GET['idAppUser'])) {
        echo "resetUser is empty.";
        # Log action
        logError($dbMaskLog, "masterlist-actions.php", $_SERVER['REMOTE_USER'] . " requested resetUser() but idAppUser is empty.");
        exit();
    } else {
        $idAppUser = $_GET['idAppUser'];
    }

    echo "Starting reset for idAppUser=" . $idAppUser . " ...";
    echo "<br>";
    echo "Deleting Uploads, DiaryEntries, Notifications, Surveys and resetting user (except for email and password)...";
    echo "<br>";

    # Call resetAppUser() in functions.php
    $pdo = new PDO("mysql:host=" . SERVERNAME . ";dbname=" . DBNAME, USERNAME, PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $res = resetAppUser($idAppUser, $pdo, $webroot, $pathToErrorLog);

    if ($res["status"] == "success") {
        echo "Reset for idAppUser=" . $idAppUser . " was successful.";
        echo "<br>";
        echo "<br>";
        echo "<button onclick='window.close()'>Schließen</button>";
        # Log action
        logError($dbMaskLog, "masterlist-actions.php", $_SERVER['REMOTE_USER'] . " sucessfully requested resetUser() for idAppUser " . $idAppUser . ".");
        exit();
    } else {
        echo "Reset for idAppUser=" . $idAppUser . " failed.";
        echo "<br>";
        echo "Error: " . $res['message'];
        echo "<br>";
        echo "<button onclick='window.close()'>Schließen</button>";
        # Log action
        logError($dbMaskLog, "masterlist-actions.php", $_SERVER['REMOTE_USER'] . " requested resetUser() for idAppUser " . $idAppUser . " but got an error.");
        logError($pathToErrorLog, "resetUser() in masterlist-actions.php", "Error while resetting user for idAppUser " . $idAppUser . ". Error: " . $res['message']);
        exit();
    }
}

?>