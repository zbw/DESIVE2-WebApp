<?php
# Receive a .png and save it to a personalized folder-path
# Returns the id of the picture if the upload was successful, -1 if the POST of picture or token is empty, -2 if the token is invalid
if ($action == "uploadPicture") {
    # Validate whether the POST of idAppUser, picture and token are empty. Exit with -1 if so.
    if (empty($_POST['picture']) || empty($_POST['token'])) {
        $answerJSON = array(
            "status" => "400",
            "body" => array(
                "message" => "Bad Request - POST of picture or token is empty",
            )
        );
        echo json_encode($answerJSON);
        exit();
    }
    # Get the user-id and the picture from the body of the request
    $base64 = $_POST['picture'];
    $token = $_POST["token"];

    # Check token via TokenAuth function
    $tokenAuthRes = TokenAuth($pdo, $token, $webroot . "/private/errorLog.txt");

    # If the token is valid, save the file and update the database ($tokenAuthRes contains the user-id in this case)
    if ($tokenAuthRes > 0) {
        # Encode the picture in base64
        $img = str_replace('data:image/png;base64,', '', $base64);
        $img = str_replace(' ', '+', $img);
        $data = base64_decode($img);

        # Get MIME-type of $data
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($data);

        # Generate file extension based on MIME-type using switch-case
        switch ($mimeType) {
            case "image/png":
                $fileExtension = ".png";
                break;
            case "image/jpeg":
                $fileExtension = ".jpg";
                break;
            case "image/gif":
                $fileExtension = ".gif";
                break;
            case "image/bmp":
                $fileExtension = ".bmp";
                break;
            case "image/tiff":
                $fileExtension = ".tiff";
                break;
            case "image/webp":
                $fileExtension = ".webp";
                break;
            default:
                # MIME-type is not supported, create error message and exit
                logError($webroot . "/private/errorLog.txt", "uploadPicture() in AppDataUpload.php", "MIME-type is not supported. idAppUser: " . $tokenAuthRes . " MIME-type: " . $mimeType);
                $answerJSON = array(
                    "status" => "400",
                    "body" => array(
                        "message" => "Bad Request - MIME-type is not supported",
                    )
                );
                echo json_encode($answerJSON);
                exit();
        }

        # Build the path, where the picture should be saved
        $dirname = $webroot . "/private/uploads/" . $tokenAuthRes . "/images/" . date("Y-m-d") . "/";

        # If the directory does not exist, create it.
        # Check whether the dir is writable and exit with -1 if not.
        # TODO: Check if the directory should be created with a different permission setting
        if (!file_exists($dirname)) {
            mkdir($dirname, 0777, true);
        } else if (!is_writable($dirname)) {
            logError($webroot . "/private/errorLog.txt", "uploadPicture() in AppDataUpload.php", "Directory is not writable. idAppUser: " . $tokenAuthRes);
            $answerJSON = array(
                "status" => "500",
                "body" => array(
                    "message" => "Internal Server Error - Directory is not writable",
                )
            );
            echo json_encode($answerJSON);
            exit();
        }

        # Build the filename of the picture
        $file = $dirname . uniqid() . $fileExtension;
        # Check if $file exists already and use a different filename if so
        while (file_exists($file)) {
            $file = $dirname . uniqid() . $fileExtension;
        }

        # Save the file to the $file path
        $success = file_put_contents($file, $data);
        # Check $success and exit with -1 if it is false
        if ($success === FALSE) {
            logError($webroot . "/private/errorLog.txt", "uploadPicture() in AppDataUpload.php", "File could not be saved. idAppUser: " . $tokenAuthRes);
            $answerJSON = array(
                "status" => "500",
                "body" => array(
                    "message" => "Internal Server Error - File could not be saved",
                )
            );
            echo json_encode($answerJSON);
            exit();
        }

        # Save entry in Upload table
        $sql = "INSERT INTO Upload (idUser, uploadType, path) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        if (!$stmt->execute(array($tokenAuthRes, "3", $file))) {
            logError($webroot . "/private/errorLog.txt", "uploadPicture() in AppDataUpload.php", "Upload entry could not be saved in DB. idAppUser: " . $tokenAuthRes);
            $answerJSON = array(
                "status" => "500",
                "body" => array(
                    "message" => "Internal Server Error - Upload entry could not be saved in DB",
                )
            );
            echo json_encode($answerJSON);
            exit();
        }

        # Return the id of the Upload entry
        $answerJSON = array(
            "status" => "200",
            "body" => array(
                "message" => "OK",
                "idUpload" => $pdo->lastInsertId(),
            )
        );
        echo json_encode($answerJSON);
    } else {
        # Authentication failed
        $answerJSON = array(
            "status" => "401",
            "body" => array(
                "message" => "Unauthorized - Token is invalid",
            )
        );
        echo json_encode($answerJSON);
    }
    exit();
}
# Upload a PDF file and save it to a personalized folder-path
# Create an entry in the Upload table and return the id of the entry
if ($action == "uploadPDF") {
    # Validate whether the POST of idAppUser, token and $_FILES pdf are empty. Exit with -1 if so.
    if (empty($_POST['token']) || empty($_FILES['pdf'])) {
        $answerJSON = array(
            "status" => "400",
            "body" => array(
                "message" => "Bad Request - POST of token or pdf is empty",
            )
        );
        echo json_encode($answerJSON);
        exit();
    }

    # Get the user-id and the picture from the body of the request
    $token = $_POST["token"];

    # Check token via TokenAuth function
    $tokenAuthRes = TokenAuth($pdo, $token, $webroot . "/private/errorLog.txt");

    if ($tokenAuthRes > 0) {
        # Save the PDF file temporarily to $file_tmp
        $file_tmp = $_FILES["pdf"]["tmp_name"];
        # Build the path, where the picture should be saved (tokenAuthRes contains the user-id in this case)
        $dirname = $webroot . "/private/uploads/" . $tokenAuthRes . "/pdf/" . date("Y-m-d") . "/";

        # If the directory does not exist, create it
        # Check if the dir is writable and exit with -1 if not
        if (!file_exists($dirname)) {
            mkdir($dirname, 0777, true);
        } else if (!is_writable($dirname)) {
            logError($webroot . "/private/errorLog.txt", "uploadPDF() in AppDataUpload.php", "Directory is not writable. idAppUser: " . $tokenAuthRes);
            $answerJSON = array(
                "status" => "500",
                "body" => array(
                    "message" => "Internal Server Error - Directory is not writable",
                )
            );
            echo json_encode($answerJSON);
            exit();
        }

        # Check if MIME type of the file is application/pdf
        if ($_FILES["pdf"]["type"] != "application/pdf") {
            logError($webroot . "/private/errorLog.txt", "uploadPDF() in AppDataUpload.php", "File is not a PDF. idAppUser: " . $tokenAuthRes . " MIME type: " . $_FILES["pdf"]["type"]);
            $answerJSON = array(
                "status" => "400",
                "body" => array(
                    "message" => "Bad Request - File is not a PDF",
                )
            );
            echo json_encode($answerJSON);
            exit();
        }

        # Build the filename of the PDF file
        $file = $dirname . uniqid() . ".pdf";
        # Check if $file exists already and use a different filename if so
        while (file_exists($file)) {
            $file = $dirname . uniqid() . ".pdf";
        }

        # Save the PDF file to the $file path
        if (!move_uploaded_file($file_tmp, $file)) {
            logError($webroot . "/private/errorLog.txt", "uploadPDF() in AppDataUpload.php", "File could not be moved. idAppUser: " . $tokenAuthRes);
            $answerJSON = array(
                "status" => "500",
                "body" => array(
                    "message" => "Internal Server Error - File could not be moved",
                )
            );
            echo json_encode($answerJSON);
            exit();
        }
        # Create an entry in the Upload table
        $sql = "INSERT INTO Upload (idUser, uploadType, path) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        if (!$stmt->execute(array($tokenAuthRes, "1", $file))) {
            logError($webroot . "/private/errorLog.txt", "uploadPDF() in AppDataUpload.php", "Upload entry could not be saved in DB. idAppUser: " . $tokenAuthRes);
            $answerJSON = array(
                "status" => "500",
                "body" => array(
                    "message" => "Internal Server Error - Upload entry could not be saved in DB",
                )
            );
            echo json_encode($answerJSON);
            exit();
        }

        # Return the id of the Upload entry
        $answerJSON = array(
            "status" => "200",
            "body" => array(
                "message" => "OK",
                "idUpload" => $pdo->lastInsertId(),
            )
        );
        echo json_encode($answerJSON);
    } else {
        # Authentication failed
        $answerJSON = array(
            "status" => "401",
            "body" => array(
                "message" => "Unauthorized - Token is invalid",
            )
        );
        echo json_encode($answerJSON);
    }
    exit();
}
# Upload an audio file to the server
if ($action == "uploadAudio") {
    if (empty($_POST['token'])) {
        $answerJSON = array(
            "status" => "400",
            "body" => array(
                "message" => "Bad Request - POST  token is empty",
            )
        );
        echo json_encode($answerJSON);
        exit();
    }

    $token = $_POST["token"];

    # Check token via TokenAuth function
    $tokenAuthRes = TokenAuth($pdo, $token, $webroot . "/private/errorLog.txt");

    # If the token is valid, save the file and update the database ($tokenAuthRes contains the user-id in this case)
    if ($tokenAuthRes > 0) {
        # Build the path, where the audio should be saved
        $dirname = $webroot . "/private/uploads/" . $tokenAuthRes . "/audios/" . date("Y-m-d") . "/";

        # If the directory does not exist, create it.
        # Check whether the dir is writable and exit with -1 if not.
        if (!file_exists($dirname)) {
            mkdir($dirname, 0777, true);
        } else if (!is_writable($dirname)) {
            # directory not writable
            $answerJSON = array(
                "status" => "500",
                "body" => array(
                    "message" => "Internal Server Error - Directory is not writable",
                )
            );
            echo json_encode($answerJSON);
            exit();
        }

        # Check if MIME type of the file is audio/wav
        if ($_FILES["uploadfile"]["type"] != "audio/wav") {
            logError($webroot . "/private/errorLog.txt", "uploadAudio() in AppDataUpload.php", "File is not a WAV. idAppUser: " . $tokenAuthRes . " MIME type: " . $_FILES["uploadfile"]["type"]);
            $answerJSON = array(
                "status" => "400",
                "body" => array(
                    "message" => "Bad Request - File is not a WAV",
                )
            );
            echo json_encode($answerJSON);
            exit();
        }

        # Build the filename of the audio
        $file = $dirname . uniqid() . ".wav";
        # Check if $file exists already and use a different filename if so
        while (file_exists($file)) {
            $file = $dirname . uniqid() . ".wav";
        }

        # Save the audio file to the $file path
        if (!move_uploaded_file($_FILES['uploadfile']['tmp_name'], $file)) {
            logError($webroot . "/private/errorLog.txt", "uploadAudio() in AppDataUpload.php", "File could not be moved. idAppUser: " . $tokenAuthRes);
            $answerJSON = array(
                "status" => "500",
                "body" => array(
                    "message" => "Internal Server Error - File could not be moved",
                )
            );
            echo json_encode($answerJSON);
            exit();
        }

        # Create an entry in the Upload table
        $sql = "INSERT INTO Upload (idUser, uploadType, path) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        if (!$stmt->execute(array($tokenAuthRes, "2", $file))) {
            logError($webroot . "/private/errorLog.txt", "uploadAudio() in AppDataUpload.php", "Upload entry could not be saved in DB");
            $answerJSON = array(
                "status" => "500",
                "body" => array(
                    "message" => "Internal Server Error - Upload entry could not be saved in DB. idAppUser: " . $tokenAuthRes,
                )
            );
            echo json_encode($answerJSON);
            exit();
        }

        # Return the id of the Upload entry
        $answerJSON = array(
            "status" => "200",
            "body" => array(
                "message" => "OK",
                "idUpload" => $pdo->lastInsertId(),
            )
        );
        echo json_encode($answerJSON);
    } else {
        # Authentication failed
        $answerJSON = array(
            "status" => "401",
            "body" => array(
                "message" => "Unauthorized - Token is invalid",
            )
        );
        echo json_encode($answerJSON);
    }
    exit();
}
# Get the questions of a survey as a JSON object
# Returns -1 if the token is invalid, -2 if the survey does not exist or there's been an error in the sql query
# Returns the entry ID of the survey if the operation was successful
if ($action == "uploadSurveyAnswer") {
    # Check whether ipAppUser, answers, surveyID and token are empty in POST request
    if (empty($_POST['surveyID']) || empty($_POST['answers']) || empty($_POST['token'])) {
        logError($pathToErrorLog, "uploadSurveyAnswer() in AppDataUpload.php", "POST of surveyID, answers or token is empty");

        $answerJSON = array(
            "status" => "400",
            "body" => array(
                "message" => "Bad Request - POST of surveyID, answers or token is empty",
            )
        );
        echo json_encode($answerJSON);
        exit();
    }

    # Get the user-id, JSON and surveyID from POST
    $surveyID = $_POST["surveyID"];
    $answers = $_POST["answers"];
    $token = $_POST["token"];

    # Convert surveyID to integer
    $surveyID = intval($surveyID);

    # Validate if surveyID is a valid survey number
    if ($surveyID > 4 || $surveyID < 1) {
        logError($pathToErrorLog, "uploadSurveyAnswer() in AppDataUpload.php", "surveyID is not a positive integer or not matching number of surveys. surveyID: " . $surveyID);
        $answerJSON = array(
            "status" => "400",
            "body" => array(
                "message" => "Bad Request - surveyID is not a positive integer or not matching number of surveys",
            )
        );
        echo json_encode($answerJSON);
        exit();
    }

    # Check token via TokenAuth function
    $tokenAuthRes = TokenAuth($pdo, $token, $pathToErrorLog);

    # If the token is valid, save the answers and update the database ($tokenAuthRes contains the user-id in this case)
    if ($tokenAuthRes > 0) {

        # Check if survey is open for the user via getOpenSurveysForIDAppuser() function
        $openSurveys = getOpenSurveysForIDAppuser($tokenAuthRes, $pdo, $pathToErrorLog);

        if ($openSurveys['status'] == "error") {
            logError($pathToErrorLog, "uploadSurveyAnswer() in AppDataUpload.php", "Error in getOpenSurveysForIDAppuser() function. idAppUser: " . $tokenAuthRes);
            #$answerJSON = array(
            #    "status" => "500",
            #    "body" => array(
            #        "message" => "Internal Server Error - Error in getOpenSurveysForIDAppuser() function. idAppUser: " . $tokenAuthRes,
            #    )
            #);
            #exit();
        } else if ($openSurveys['surveys']['survey' . $surveyID] == false) {

            # Check if survey is open for the user
            logError($pathToErrorLog, "uploadSurveyAnswer() in AppDataUpload.php", "Survey is not open for the user. idAppUser: " . $tokenAuthRes . " with surveyID: " . $surveyID);
            #$answerJSON = array(
            #    "status" => "400",
            #    "body" => array(
            #        "message" => "Bad Request - Survey is not open for the user. idAppUser: " . $tokenAuthRes . " surveyID: " . $surveyID,
            #    )
            #);
            #echo json_encode($answerJSON);
            #exit();
        }

        # Prepare the SQL statement to insert the answers into UserAnsweredSurveyQuestion
        $sql = "INSERT INTO UserAnsweredSurveyQuestion (idUser, Answer, SurveyID) VALUES (?, ?, ?)";
        # Execute the SQL statement
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute(array($tokenAuthRes, $answers, $surveyID))) {
            # Execution successful
            # Echo id of the UserAnsweredSurveyQuestion entry
            $answerJSON = array(
                "status" => "200",
                "body" => array(
                    "message" => "OK",
                    "idUpload" => $pdo->lastInsertId(),
                )
            );
            echo json_encode($answerJSON);
        } else {
            # Return error for an unsuccessful execution
            $answerJSON = array(
                "status" => "500",
                "body" => array(
                    "message" => "Internal Server Error - SurveyAnswer could not be saved to DB. idAppUser: " . $tokenAuthRes,
                )
            );
            echo json_encode($answerJSON);
        }
    } else {
        # Authentication failed
        $answerJSON = array(
            "status" => "401",
            "body" => array(
                "message" => "Unauthorized - Token is invalid",
            )
        );
        echo json_encode($answerJSON);
    }
    exit();
}
# Save a diary entry to the db
if ($action == "uploadDiaryEntry") {

    # Check whether entry and token are empty in POST request
    if (!isset($_POST['entry']) || empty($_POST['idUpload']) || empty($_POST['token'])) {
        $answerJSON = array(
            "status" => "400",
            "body" => array(
                "message" => "Bad Request - POST of entry, idUpload or token is empty",
            )
        );
        echo json_encode($answerJSON);
        exit();
    }
    # Check for optional audioID (e.g. if the user has recorded an audio for the entry)
    if (empty($_POST['additionID'])) {
        $additionID = "";
    } else {
        $additionID = $_POST['additionID'];
    }

    # Get fixed parameters from POST
    $entry = $_POST["entry"];
    $idUpload = $_POST["idUpload"]; // DB ID of the uploaded file (returned by the upload functions)
    $token = $_POST["token"];

    # Check token via TokenAuth function
    $tokenAuthRes = TokenAuth($pdo, $token, $webroot . "/private/errorLog.txt");

    # If the token is valid, save the entry and update the database ($user contains the user-id in this case)
    if ($tokenAuthRes > 0) {
        # Build the SQL statement to insert the entry into DiaryEntry. Using NULLIF to put a NULL value into the idUploadAdditionalInformation column if $additionID is empty
        $sql = "INSERT INTO DiaryEntry (idAppUser, entry, idUploadAdditionalInformation, idUpload) VALUES (?, ?, NULLIF(?,''), ?)";

        # Prepare the SQL statement to insert the entry into UserUploads
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute(array($tokenAuthRes, $entry, $additionID, $idUpload))) {
            # Execution successful
            # Echo id of the UserUploads entry
            $answerJSON = array(
                "status" => "200",
                "body" => array(
                    "message" => "OK",
                    "idUpload" => $pdo->lastInsertId(),
                )
            );
            echo json_encode($answerJSON);
        } else {
            # Return statement for an unsuccessful execution
            $answerJSON = array(
                "status" => "500",
                "body" => array(
                    "message" => "Internal Server Error - DiaryEntry could not be saved to DB. idAppUser: " . $tokenAuthRes,
                )
            );
            echo json_encode($answerJSON);
        }
    } else {
        # Authentication failed
        $answerJSON = array(
            "status" => "401",
            "body" => array(
                "message" => "Unauthorized - Token is invalid",
            )
        );
        echo json_encode($answerJSON);
    }
    exit();
}
if ($action == "modifyDiaryEntry") {
    # Check whether entry and token are empty in POST request
    if (empty($_POST['idDiaryEntry']) || !isset($_POST['idAdditionalInformation']) || empty($_POST['token'])) {
        $answerJSON = array(
            "status" => "400",
            "body" => array(
                "message" => "Bad Request - POST of idDiaryEntry, idAdditionalInformation or token is empty",
            )
        );
        echo json_encode($answerJSON);
        exit();
    }

    # Get fixed parameters from POST
    $idDiaryEntry = $_POST["idDiaryEntry"];
    $idAdditionalInformation = $_POST["idAdditionalInformation"];
    $token = $_POST["token"];
    $entry = $_POST["entry"];
    

    # Check token via TokenAuth function
    $tokenAuthRes = TokenAuth($pdo, $token, $webroot . "/private/errorLog.txt");

    if ($tokenAuthRes > 0) {
        #Check if idAdditionalInformation is provided, if not: dont enter it into the database
        $res = null;
        if (strlen($idAdditionalInformation) == 0) {
            $sql = "UPDATE DiaryEntry SET entry = ? WHERE idDiaryEntry = ?";
            $stmt = $pdo->prepare($sql);
            $res = $stmt->execute(array($entry, $idDiaryEntry));
        } else {
            $sql = "UPDATE DiaryEntry SET idUploadAdditionalInformation = ?, entry = ? WHERE idDiaryEntry = ?";
            $stmt = $pdo->prepare($sql);
            $res = $stmt->execute(array($idAdditionalInformation, $entry, $idDiaryEntry));

        }
        if ($res) {
            # Execution successful
            # Build Json
            $answerJSON = array(
                "status" => "200",
                "body" => array(
                    "message" => "OK",
                )
            );

            echo json_encode($answerJSON);
        } else {
            logError($webroot . "/private/errorLog.txt", "modifyDiaryEntry() in AppDataUpload.php", "DiaryEntry could not be modified. idAppUser: " . $tokenAuthRes);
            # Build Json
            $answerJSON = array(
                "status" => "500",
                "body" => array(
                    "message" => "Internal Server Error - DiaryEntry could not be modified",
                )
            );
            echo json_encode($answerJSON);
        }
    } else {
        # Build Json
        $answerJSON = array(
            "status" => "401",
            "body" => array(
                "message" => "Unauthorized - Token is invalid",
            )
        );
        echo json_encode($answerJSON);
    }
    exit();
}
?>