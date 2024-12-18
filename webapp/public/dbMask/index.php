<?php
# Path: API\Endpoint\public\dbMask\index.php

# Create webroot variable to directory above public and private folders
$webroot = dirname(dirname(dirname(__FILE__)));

include($webroot . '/private/key.php');

$dbMaskLog = $webroot . '/private/dbMaskLog.txt';

require_once($webroot . "/private/resources/api-functionality/functions.php");

try {
    # Create connection to database
    $conn = new PDO("mysql:host=" . SERVERNAME . ";dbname=" . DBNAME, USERNAME, PASSWORD);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    # Try to get action parameter from URL and if it is not set, echo webform
    if (!empty($_POST['action'])) {
        $action = $_POST['action'];
    } else {
        # Log action
        logError($dbMaskLog, "index.php", $_SERVER['REMOTE_USER'] . " requested index.php without action parameter.");
        # echo index html file
        include(dirname(__FILE__) . '/resources/index.html');
        exit;
    }

    # Provide Masterlist as csv file for download
    if ($action == "downloadMasterlist") {
        # Log action
        logError($dbMaskLog, "downloadMasterlist in index.php", $_SERVER['REMOTE_USER'] . " requested download of masterlist.");

        # Build sql statement to select all data from Subject table
        $sql = "SELECT SubjectIdentifier, AES_DECRYPT(enc_name, :key ) AS name, AES_DECRYPT(enc_mail, :key ) AS mail, app, interview, AES_DECRYPT(enc_iban, :key) as iban, timestamp as registrierungszeitpunkt FROM Subject";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':key', ENCRYPTION_KEY);
        $stmt->execute();

        # If more than 0 rows are returned, build csv file
        if ($stmt->rowCount() > 0) {
            $delimiter = ",";
            $filename = "Teilnehmerliste-DESIVE2-Stand-" . date('Y-m-d') . ".csv";

            # Create a file pointer
            $f = fopen('php://memory', 'w');

            # Set headers to download file rather than displayed
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Encoding: UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '";');
            echo "\xEF\xBB\xBF"; // BOM header UTF-8

            # Set column headers
            $fields = array('PSEUDONYM', 'NAME', 'EMAIL', 'APP', 'INTERVIEW', 'IBAN', 'REGISTRIERUNGSZEITPUNKT');
            fputcsv($f, $fields, $delimiter);

            # Output each row of the data, format line as csv and write to file pointer
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $lineData = array($row['SubjectIdentifier'], $row['name'], $row['mail'], $row['app'], $row['interview'], $row['iban'], $row['registrierungszeitpunkt']);
                fputcsv($f, $lineData, $delimiter);
            }

            # Move back to beginning of file
            fseek($f, 0);

            # Output all remaining data on a file pointer
            fpassthru($f);

            # Close file pointer
            fclose($f);
        } else {
            echo "No participants found.";
            echo "<br>";
            echo "Click <a href='index.php'>here</a> to go back.";
        }
        exit();
    } elseif ($action == "printMasterlist") {
        # Log action
        logError($dbMaskLog, "printMasterlist() in index.php", $_SERVER['REMOTE_USER'] . " requested print of masterlist.");
        # Build sql statement to select all data from Subject table
        $sql = "SELECT
            s.SubjectIdentifier,
            AES_DECRYPT(s.enc_name, :encryptionKey) AS name,
            AES_DECRYPT(s.enc_mail, :encryptionKey) AS mail,
            s.app,
            s.interview,
            AES_DECRYPT(s.enc_iban, :encryptionKey) AS iban,
            s.timestamp AS registrierungszeitpunkt,
            s.number_of_surveys_answered,
            a.idAppUser,
            a.hasSetPassword,
            a.first_login,
            a.last_online,
            a.receivesPush,
            a.creation_timestamp,
            d.number_of_uploads            
        FROM Subject s
        LEFT JOIN AppUser a
            ON s.idSubject = a.idSubject
        LEFT JOIN (SELECT idAppUser, COUNT(*) as number_of_uploads FROM DiaryEntry GROUP BY idAppUser) d
        	ON a.idAppUser = d.idAppUser
        LEFT JOIN (SELECT idUser as idAppUser, COUNT(*) as number_of_surveys_answered FROM UserAnsweredSurveyQuestion GROUP BY idUser) s
            ON a.idAppUser = s.idAppUser
            ;";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':encryptionKey', ENCRYPTION_KEY);
        $stmt->execute();

        # If more than 0 rows are returned, build html table
        if ($stmt->rowCount() > 0) {
            # echo masterlist-table-top.html 
            include(dirname(__FILE__) . '/resources/masterlist-table-top.html');

            # add counter for app and interview
            $appCounter = 0;
            $interviewCounter = 0;
            $subjectCounter = 0;

            # Go through all rows and echo them as html table rows
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>";
                echo "<td>" . $row['SubjectIdentifier'] . "</td>";
                echo "<td>" . $row['name'] . "</td>";
                echo "<td>" . $row['mail'] . "</td>";
                if ($row['app'] == "1") {
                    echo "<td bgcolor='#96be25'>" . $row['app'] . " (idAppUser: " . $row['idAppUser'] . ")</td>";
                } else {
                    echo "<td bgcolor='#e46850'>" . $row['app'] . "</td>";
                }
                if ($row['interview'] == "1") {
                    echo "<td bgcolor='#96be25'>" . $row['interview'] . "</td>";
                } else {
                    echo "<td bgcolor='#e46850'>" . $row['interview'] . "</td>";
                }

                echo "<td>" . $row['iban'] . "</td>";
                echo "<td>" . $row['registrierungszeitpunkt'] . "</td>";
                echo "</tr>";
                echo "\n";
                if ($row['app'] == "1") {
                    echo "<tr>";
                    echo "<td colspan='3' bgcolor='e9ecef'></td>";
                    echo "<td>Erster Login: " . $row['first_login'] . "<br> Letzte Aktivität: " . $row['last_online'] . "</td>";
                    echo "<td>Passwort durch User gesetzt: " . $row['hasSetPassword'] . "
                    <br>
                    Push-Nachrichten aktiv: " . $row['receivesPush'] . "</td>";
                    echo "<td>Anzahl Tagebucheinträge: " . $row['number_of_uploads'] . "
                    <br>
                    Anzahl beantworteter Umfragen: " . $row['number_of_surveys_answered'] . "</td>";
                    echo "<td>Erstellungszeitpunkt App-Account:
                    <br>"
                        . $row['creation_timestamp'] .
                        "</td>";
                    echo "</tr>";
                    echo "\n";
                    echo "<tr>";
                    echo "<td colspan='3' bgcolor='e9ecef'></td>";
                    echo "<td colspan='5' bgcolor='#ffc2b3'>
                    Dangerzone: 
                    <a href='/dbMask/resources/masterlist-actions.php?action=resetPassword&mail=" . $row['mail'] . "' target='_blank'><button>Passwort zurücksetzen</button><a>
                    <a href='/dbMask/resources/masterlist-actions.php?action=resetUser&idAppUser=" . $row['idAppUser'] . "' target='_blank'><button>App-Account zurücksetzen</button><a>
                    <a href='/dbMask/resources/masterlist-actions.php?action=resetTimestamp&subjectIdentifier=" . $row['SubjectIdentifier'] . "' target='_blank'><button> creation_timestamp setzen (now())</button><a>               
                    <form action='/dbMask/resources/masterlist-actions.php' method='get' target='_blank' style ='float: right; padding-left: 5px'>
                        <input type='hidden' name='action' value='setFirstLoginTimestamp'>
                        <input type='hidden' name='idAppUser' value='" . $row['idAppUser'] . "'>
                        <button type='submit'>Setze First Login auf</button>
                        <input type='datetime-local' id='first_login' name='first_login' value='" . $row['first_login'] . "'>
                    </form>

                    <form action='resources/incentive-details-participant.php' method='post' style='margin: 10px;'>
                    <input type='text' id='SubjectIdentifier' name='SubjectIdentifier' hidden value='" . $row['SubjectIdentifier'] . "'/></p>
                    <button id='showParticipantIncentive' type='submit' name='action' value='showParticipantIncentive' />Incentive-Details aufrufen</button>
                    </form>
                    </td>";
                    echo "</tr>";
                    echo "\n";
                } else {
                    echo "<tr>";
                    echo "<td colspan='3' bgcolor='e9ecef'></td>";
                    echo "<td colspan='5'>
                    <form action='resources/incentive-details-participant.php' method='post' style='margin: 10px;'>
                    <input type='text' id='SubjectIdentifier' name='SubjectIdentifier' hidden value='" . $row['SubjectIdentifier'] . "'/></p>
                    <button id='showParticipantIncentive' type='submit' name='action' value='showParticipantIncentive' />Incentive-Details aufrufen</button>
                    </form>
                    </td>";
                    echo "</tr>";
                }


                # count app and interview
                if ($row['app'] == "1") {
                    $appCounter++;
                }
                if ($row['interview'] == "1") {
                    $interviewCounter++;
                }
                $subjectCounter++;
            }
            echo "  </tbody>
            </table>";

            # echo in bold the number of participants for app and interview
            echo "<p style='text-align: left;font-weight:bold'>Insgesamt haben sich " . $subjectCounter . " Personen angemeldet. Davon " . $appCounter . " für die App und " . $interviewCounter . " für das Interview.</p>";

            # echo masterlist-table-bottom.html
            include(dirname(__FILE__) . '/resources/masterlist-table-bottom.html');

        } else {
            echo "No participants found.";
            echo "<br>";
            echo "Click <a href='index.php'>here</a> to go back.";
        }
        exit();
    } elseif ($action == "changeParticipationInterest") {
        # Log action
        logError($dbMaskLog, "changeParticipationInterest() in index.php", $_SERVER['REMOTE_USER'] . " requested changeParticipationInterest.");
        # Check if POST SubjectIdentifier is empty
        if (empty($_POST['SubjectIdentifier'])) {
            echo "SubjectIdentifier is empty.";
            exit();
        }

        # Get SubjectIdentifier from POST
        $SubjectIdentifier = $_POST['SubjectIdentifier'];

        # modify $SubjectIdentifier to higher case
        $SubjectIdentifier = strtoupper($SubjectIdentifier);

        // Set API URL
        $url = API_BASE_URL . '/DBConnect.php?action=upgradeParticipationInterest';
        // build postdata with http_build_query
        $postdata = http_build_query(
            array(
                'subjectIdentifier' => $SubjectIdentifier,
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

        # unpack JSON from $result
        $result = json_decode($result);

        # check if the result is "Success"
        if ($result->status == "success") {
            echo $result->status;
            echo "<br>";
            echo $result->message;
        } elseif ($result->status == "error") {
            echo "Es gab einen Fehler:";
            echo "<br>";
            echo $result->status;
            echo "<br>";
            echo $result->message;
        }
        echo "<br>
        <br>
        Click <a href='index.php'>here</a> to go back.";
        exit();
    } else {
        echo "Action specified not known.";
    }
} catch (PDOException $e) {
    #echo "Connection failed: " . $e->getMessage();
    echo "An error occurred!";
    # Log Error
    logError($dbMaskLog, "PDO in index.php", $e->getMessage());
}
?>