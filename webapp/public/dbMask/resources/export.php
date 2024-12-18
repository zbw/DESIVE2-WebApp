<?php
# Path: API\Endpoint\public\dbMask\export.php

$webroot = dirname(dirname(dirname(dirname(__FILE__))));

include($webroot . '/private/key.php');
$servername = SERVERNAME;
$username = USERNAME;
$password = PASSWORD;
$dbname = DBNAME;
$key = ENCRYPTION_KEY;
$creation_api_key = CREATION_API_KEY;
$api_base_url = API_BASE_URL;
$pathToErrorLog = $webroot . "/private/errorLog.txt";
$dbMaskLog = $webroot . '/private/dbMaskLog.txt';


# Require once ../private/resources/api-functionality/functions.php
require_once($webroot . "/private/resources/api-functionality/functions.php");

$export_json; // Will be used to store the JSON data, but used as an object until the end of the script


# Try to get action parameter from POST and if it is not set, echo webform
if (!empty($_POST['action'])) {
    $action = $_POST['action'];
    # Verify that the action parameter is exportAppData
    if ($action != "exportAppData") {
        # Log action
        logError($dbMaskLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php with invalid action parameter.");

        echo "Invalid action parameter set.";
        # echo button to go back to $webroot/public/dbMask/index.php
        echo "<br><br><a href='/dbMask/index.php' class='btn btn-primary'>Zurück</a>";
        exit;
    } else {
        $action = $_POST['action'];
    }
} else {
    # Log action
    logError($dbMaskLog, "incentive-details-participant.php", $_SERVER['REMOTE_USER'] . " requested incentive-details-participant.php without action parameter.");

    echo "No action parameter set.";
    # echo button to go back to $webroot/public/dbMask/index.php
    echo "<br><br><a href='/dbMask/index.php' class='btn btn-primary'>Zurück</a>";
    exit;
}

# Try reading the startDate/endDate of the form data and verify that they are valid
if (!empty($_POST['exportStartDate']) && !empty($_POST['exportEndDate'])) {
    $exportStartDate = $_POST['exportStartDate'];
    $exportEndDate = $_POST['exportEndDate'];

    # Verify that the dates are valid
    $startDate = DateTime::createFromFormat('Y-m-d', $exportStartDate);
    $endDate = DateTime::createFromFormat('Y-m-d', $exportEndDate);

    if (!$startDate || !$endDate || $endDate < $startDate) {
        # Log action
        logError($dbMaskLog, "export.php", $_SERVER['REMOTE_USER'] . " requested export.php with invalid exportStartDate or exportEndDate parameter.");
        logError($pathToErrorLog, "export.php", $_SERVER['REMOTE_USER'] . " requested export.php with invalid exportStartDate or exportEndDate parameter.");

        echo "Invalid exportStartDate or exportEndDate parameter set.";
        # echo button to go back to $webroot/public/dbMask/index.php
        echo "<br><br><a href='/dbMask/index.php' class='btn btn-primary'>Zurück</a>";
        exit;
    }

} else {
    # Log action
    logError($dbMaskLog, "export.php", $_SERVER['REMOTE_USER'] . " requested export.php without exportStartDate or exportEndDate parameter.");

    echo "No exportStartDate or exportEndDate parameter set.";
    # echo button to go back to $webroot/public/dbMask/index.php
    echo "<br><br><a href='/dbMask/index.php' class='btn btn-primary'>Zurück</a>";
    exit;
}


try {
    # Create connection to database
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    # Create zip file
    $zip = new ZipArchive();
    # set the name of the zip file
    $zipFile = "./DESIVE2-Export_" . date("Y-m-d_H-i-s") . "_Registration_From_" . $startDate->format("Y-m-d") . "_To_" . $endDate->format("Y-m-d") . ".zip";

    ###
    # Get all users from database
    ###
    $allUsersRes = getAppUsers($startDate, $endDate, $pdo, $webroot . "/private/errorLog.txt");
    # Check if successfull
    if ($allUsersRes['status'] == 'success' && !empty($allUsersRes['appUsers'])) {
        $allUsers = $allUsersRes['appUsers'];
    } else {
        logError($pathToErrorLog, "export() in export.php", "Could not get all users from database or there exist none. " . $allUsersRes['message']);
        logError($dbMaskLog, "export() in export.php", $_SERVER['REMOTE_USER'] . " requested an export, but could not get all users from database or there exist none. " . $allUsersRes['message']);
        $json = array(
            'status' => 'error',
            'message' => 'Could not get all users from database or there exist none yet. ' . $allUsersRes['message'],
        );
        echo json_encode($json);

        # echo button to close the tab
        echo "<br><br><a href='javascript:window.close()' class='btn btn-primary'>Schließen</a>";

        # close the database connection
        $db = null;
        exit();
    }

    # Iterate over $allusers and get all data for each user
    foreach ($allUsers as $user) {
        # Get idSubject and pseudonym for the given user
        $idSubject = $user['idSubject'];

        # Get pseudonym for the given user
        $pseudonym = getPseudonymForIDSubject($idSubject, $pdo, $webroot . "/private/errorLog.txt");

        # Check if successfull
        if ($pseudonym['status'] == 'success') {
            $pseudonym = $pseudonym['pseudonym'];
        } else {
            logError($webroot . "/private/errorLog.txt", "export() in export.php", "Could not get pseudonym for idSubject " . $idSubject . ". " . $pseudonym['message']);
            logError($dbMaskLog, "export() in export.php", $_SERVER['REMOTE_USER'] . " requested an export, but could not get pseudonym for idSubject " . $idSubject . ". " . $pseudonym['message']);
            $json = array(
                'status' => 'error',
                'message' => 'Could not get pseudonym for idSubject ' . $idSubject . '. ' . $pseudonym['message'],
            );
            echo json_encode($json);
            exit();
        }

        ###
        # Get the first_login value for the given user
        ###
        $first_login = getFirstLoginForIdAppUser($user['idAppUser'], $pdo, $webroot . "/private/errorLog.txt");
        # Check if successfull
        if ($first_login['status'] == 'success') {
            $first_login = $first_login['first_login'];
        } else {
            logError($webroot . "/private/errorLog.txt", "export() in export.php", "Could not get first_login for idAppUser " . $user['idAppUser'] . ". " . $first_login['message']);
            logError($dbMaskLog, "export() in export.php", $_SERVER['REMOTE_USER'] . " requested an export, but could not get first_login for idAppUser " . $user['idAppUser'] . ". " . $first_login['message']);
            $json = array(
                'status' => 'error',
                'message' => 'Could not get first_login for idAppUser ' . $user['idAppUser'] . '. ' . $first_login['message'],
            );
            echo json_encode($json);
            exit();
        }

        ###
        # Get the number of uploads for the given user (excluding voice memos that are used as additional info for another upload)
        ###
        $numberOfUploads = getNumberOfUploadsForIdAppUser($user['idAppUser'], $pdo, $webroot . "/private/errorLog.txt");
        # Check if successfull
        if ($numberOfUploads['status'] == 'success' && isset($numberOfUploads['uploadCount'])) {
            $uploadCount = $numberOfUploads['uploadCount'];
        } else {
            logError($webroot . "/private/errorLog.txt", "export() in export.php", "Could not get number of uploads for idAppUser " . $user['idAppUser'] . ". " . $numberOfUploads['message']);
            logError($dbMaskLog, "export() in export.php", $_SERVER['REMOTE_USER'] . " requested an export, but could not get number of uploads for idAppUser " . $user['idAppUser'] . ". " . $numberOfUploads['message']);
            $json = array(
                'status' => 'error',
                'message' => 'Could not get number of uploads for idAppUser ' . $user['idAppUser'] . '. ' . $numberOfUploads['message'],
            );
            echo json_encode($json);
            exit();
        }

        ###
        # Get the number of surveys for the given user
        ###
        $surveyCount = getNumberOfSurveysForIdAppUser($user['idAppUser'], $pdo, $webroot . "/private/errorLog.txt");
        # Check if successfull
        if ($surveyCount['status'] == 'success' && isset($surveyCount['surveyCount'])) {
            $surveyCount = $surveyCount['surveyCount'];
        } else {
            logError($webroot . "/private/errorLog.txt", "export() in export.php", "Could not get number of surveys for idAppUser " . $user['idAppUser'] . ". " . $surveyCount['message']);
            logError($dbMaskLog, "export() in export.php", $_SERVER['REMOTE_USER'] . " requested an export, but could not get number of surveys for idAppUser " . $user['idAppUser'] . ". " . $surveyCount['message']);
            $json = array(
                'status' => 'error',
                'message' => 'Could not get number of surveys for idAppUser ' . $user['idAppUser'] . '. ' . $surveyCount['message'],
            );
            echo json_encode($json);
            exit();
        }

        ###
        # Get the surveys for the given user
        ###
        $surveys = getSurveysForIdAppUser($user['idAppUser'], $pdo, $webroot . "/private/errorLog.txt");
        # Check if successfull
        if ($surveys['status'] == 'success') {
            # Check if there are any surveys. If not set $surveys to an empty array
            if (empty($surveys['surveys']) || $surveys['surveys'] == "") {
                $surveys = array();
            } else {
                $surveys = $surveys['surveys'];
            }
        } else {
            logError($webroot . "/private/errorLog.txt", "export() in export.php", "Could not get surveys for idAppUser " . $user['idAppUser'] . ". " . $surveys['message']);
            logError($dbMaskLog, "export() in export.php", $_SERVER['REMOTE_USER'] . " requested an export, but could not get surveys for idAppUser " . $user['idAppUser'] . ". " . $surveys['message']);
            $json = array(
                'status' => 'error',
                'message' => 'Could not get surveys for idAppUser ' . $user['idAppUser'] . '. ' . $surveys['message'],
            );
            echo json_encode($json);
            exit();
        }

        ###
        # Get the diary entries for the given user
        ###
        $diary_entries = getDiaryEntriesForIdAppUser($user['idAppUser'], $pdo, $webroot . "/private/errorLog.txt");
        # Check if successfull
        if ($diary_entries['status'] == 'success') {
            $diary_entries = $diary_entries['diaryEntries'];
        } else {
            logError($webroot . "/private/errorLog.txt", "export() in export.php", "Could not get diary entries for idAppUser " . $user['idAppUser'] . ". " . $diary_entries['message']);
            logError($dbMaskLog, "export() in export.php", $_SERVER['REMOTE_USER'] . " requested an export, but could not get diary entries for idAppUser " . $user['idAppUser'] . ". " . $diary_entries['message']);
            $json = array(
                'status' => 'error',
                'message' => 'Could not get diary entries for idAppUser ' . $user['idAppUser'] . '. ' . $diary_entries['message'],
            );
            echo json_encode($json);
            exit();
        }

        ###
        # Iterate over the diary entries, modify them and add the files defined by the paths to the zip file
        ###
        $uploadsArray = array(); # Array to store the uploads from $diary_entries

        # if no diary entries exist, create an empty array
        if (empty($diary_entries)) {
            $diary_entries = array();
        } else {
            # if diary entries exist, iterate over them

            # Iterate over entries for the given user in DiaryEntry
            foreach ($diary_entries as $diary_entry) {
                # Create/Open the zip file
                if ($zip->open($zipFile, ZipArchive::CREATE) !== TRUE) {
                    logError($webroot . "/private/errorLog.txt", "export() in export.php", "Could not create/open zip file " . $zipFile);
                    logError($dbMaskLog, "export() in export.php", $_SERVER['REMOTE_USER'] . " requested an export, but could not create/open zip file " . $zipFile);
                    $json = array(
                        'status' => 'error',
                        'message' => 'Could not create zip file ' . $zipFile,
                    );
                    echo json_encode($json);
                    exit();
                }

                # Prepare entry by splitting it
                $entry = json_decode($diary_entry['diaryEntry_Questions'], true);

                if (is_array($entry) == false) {
                    logError($webroot . "/private/errorLog.txt", "export() in export.php", "Could not decode diary entry " . $diary_entry['diaryEntry_ID'] . " for idAppUser " . $user['idAppUser']);
                    logError($dbMaskLog, "export() in export.php", $_SERVER['REMOTE_USER'] . " requested an export, but could not decode diary entry " . $diary_entry['diaryEntry_ID'] . " for idAppUser " . $user['idAppUser']);
                    $json = array(
                        'status' => 'error',
                        'message' => 'Could not decode diary entry ' . $diary_entry['diaryEntry_ID'] . ' for idAppUser ' . $user['idAppUser'],
                    );
                    echo json_encode($json);
                    exit();
                }

                # Count number of entries in $entry
                $entryCount = count($entry);

                if ($entryCount < 2 || $entryCount > 2) {
                    logError($webroot . "/private/errorLog.txt", "export() in export.php", "Could not split diary entry " . $diary_entry['diaryEntry_ID'] . " for idAppUser " . $user['idAppUser']);
                    logError($dbMaskLog, "export() in export.php", $_SERVER['REMOTE_USER'] . " requested an export, but could not split diary entry " . $diary_entry['diaryEntry_ID'] . " for idAppUser " . $user['idAppUser']);
                    $json = array(
                        'status' => 'error',
                        'message' => 'Could not split diary entry ' . $diary_entry['diaryEntry_ID'] . ' for idAppUser ' . $user['idAppUser'],
                    );
                    echo json_encode($json);
                    exit();
                } else {
                    $uploadsArray[] = array(
                        'idDiaryEntry' => $diary_entry['diaryEntry_ID'],
                        'idUpload' => $diary_entry['baseFile_ID'],
                        'uploadType' => $diary_entry['baseFile_UploadType'],
                        'uploadTimestamp' => $diary_entry['baseFile_Timestamp'],
                        'filename' => null,
                        'additonalInfo' => array(
                            'itemAdditionalText' => $entry[0],
                            'itemAdditionalVoiceMemo' => array(
                                'idUpload' => $diary_entry['additionalInfoFile_ID'],
                                'uploadType' => $diary_entry['additionalInfoFile_UploadType'],
                                'uploadTimestamp' => $diary_entry['additionalInfoFile_Timestamp'],
                                'filename' => null,
                            ),
                            'itemAdditionalQuestions' => $entry[1]
                        ),
                    );
                }

                # Get the path of the main file of the diary entry
                $path = $diary_entry['baseFile_Path'];

                # Check if file exists and is readable
                if (!file_exists($path) || !is_readable($path)) {
                    logError($webroot . "/private/errorLog.txt", "export() in export.php", "Could not read file " . $path . " for idAppUser " . $user['idAppUser'] . ".");
                    logError($dbMaskLog, "export() in export.php", $_SERVER['REMOTE_USER'] . " requested an export, but could not read file " . $path . " for idAppUser " . $user['idAppUser'] . ".");
                    $json = array(
                        'status' => 'error',
                        'message' => 'Could not read file ' . $path . ' for idAppUser ' . $user['idAppUser'] . '.',
                    );
                    echo json_encode($json);
                    exit();
                }

                # Determine file ending
                $fileEnding = pathinfo($path, PATHINFO_EXTENSION);

                # Generate new filename with format "type_pseudonym_diaryEntryID_timestamp".ending
                $newFilename = $diary_entry['baseFile_UploadType'] . "_" . $pseudonym . "_" . $diary_entry['diaryEntry_ID'] . "_" . str_replace(":", "_", $diary_entry['baseFile_Timestamp']) . "." . $fileEnding;

                # addFile to the zip file with the new filename
                $zip->addFile($path, $newFilename);

                # Add the new filename to the uploadsArray
                $uploadsArray[count($uploadsArray) - 1]['filename'] = $newFilename;

                # Check if there is an additional file for the diary entry
                if ($diary_entry['additionalInfoFile_ID'] != null && $diary_entry['additionalInfoFile_ID'] != "") {

                    # Do the same for the itemAdditionalVoiceMemo
                    # Get the path of the main file of the diary entry
                    $path = $diary_entry['additionalInfoFile_Path'];

                    # Check if file exists and is readable
                    if (!file_exists($path) || !is_readable($path)) {
                        logError($webroot . "/private/errorLog.txt", "export() in export.php", "Could not read file " . $path . " for idAppUser " . $user['idAppUser'] . ".");
                        logError($dbMaskLog, "export() in export.php", $_SERVER['REMOTE_USER'] . " requested an export, but could not read file " . $path . " for idAppUser " . $user['idAppUser'] . ".");
                        $json = array(
                            'status' => 'error',
                            'message' => 'Could not read file ' . $path . ' for idAppUser ' . $user['idAppUser'] . '.',
                        );
                        echo json_encode($json);
                        exit();
                    }

                    # Determine file ending
                    $fileEnding = pathinfo($path, PATHINFO_EXTENSION);

                    # Generate new filename with format "type_pseudonym_diaryEntryID_timestamp".ending
                    $newFilename = $diary_entry['additionalInfoFile_UploadType'] . "_" . $pseudonym . "_" . $diary_entry['diaryEntry_ID'] . "_" . str_replace(":", "_", $diary_entry['additionalInfoFile_Timestamp']) . "." . $fileEnding;

                    # addFile to the zip file with the new filename
                    $zip->addFile($path, $newFilename);

                    # Add the new filename to the uploadsArray
                    $uploadsArray[count($uploadsArray) - 1]['additonalInfo']['itemAdditionalVoiceMemo']['filename'] = $newFilename;
                } else {
                    # Add null to the uploadsArray
                    $uploadsArray[count($uploadsArray) - 1]['additonalInfo']['itemAdditionalVoiceMemo'] = array();
                }

                # Close the zip file
                $zip->close();
            }
        }

        # Save the user data to the export_json object
        $export_json[] = array(
            'pseudonym' => $pseudonym,
            'first_login' => $first_login,
            'last_online' => $user['last_online'],
            'uploadCount' => $uploadCount,
            'surveyCount' => $surveyCount,
            'surveys' => $surveys,
            'uploads' => $uploadsArray,
        );

    }

    # Write the export_json object to a json file in the zip file
    $jsonFile = "DESIVE2-Export_" . date("Y-m-d_H-i-s") . ".json";
    $zip->open($zipFile, ZipArchive::CREATE);
    $zip->addFromString($jsonFile, json_encode($export_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ZipArchive::FL_ENC_UTF_8);
    $zip->close();

    # Send the zip file to the user
    header('Content-Type: application/zip');
    header('Content-disposition: attachment; filename=' . basename($zipFile));
    header('Content-Length: ' . filesize($zipFile));
    readfile($zipFile);

    # Delete the zip file
    unlink($zipFile);

    # Close the database connection
    $db = null;

    echo "Export successful!";

    logError($dbMaskLog, "export() in export.php", $_SERVER['REMOTE_USER'] . " requested an export and successfully downloaded it.");

    # Exit the script
    exit();

} catch (PDOException $e) {
    #echo "Connection failed: " . $e->getMessage();
    echo "An error occurred!";
    logError($pathToErrorLog, "export() in export.php", "SQL statement in export.php" . " - " . $e->getMessage());
    exit();
}