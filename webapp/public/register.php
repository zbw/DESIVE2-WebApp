<?php

  include(dirname(dirname(__FILE__)) . '/private/key.php');
  $creation_api_key = CREATION_API_KEY;
  $api_base_url = API_BASE_URL;
  $system_username = SYSTEM_AUTHORIZATION_USER;
  $system_password = SYSTEM_AUTHORIZATION_PASSWORD;

  # Check whether the POST variables are not empty
  if (!empty($_POST['name']) && !empty($_POST['email']) && !empty($_POST['participation_interest']) && !empty($_POST['privacy'])) {

    # Get the POST variables
    $name = $_POST['name'];
    $email = $_POST['email'];
    $participation_interest = $_POST['participation_interest'];
    $privacy = $_POST['privacy'];

    // Set API URL
    $url = $api_base_url . '/DBConnect.php?action=createUser';

    // build postdata with http_build_query
    $postdata = http_build_query(
      array(
        'name' => $name,
        'email' => $email,
        'participation_interest' => $participation_interest,
        'privacy' => $privacy,
        'api_key' => $creation_api_key
      )
    );

    // build opts
    $opts = array('http' =>
      array(
        'method'  => 'POST',
        'header'  => 'Content-type: application/x-www-form-urlencoded' . "\r\n" .
                     'Authorization: Basic ' . base64_encode("$system_username:$system_password"),
        'content' => $postdata
      )
    );

    // create context
    $context  = stream_context_create($opts);

    // fetch result
    $result = file_get_contents($url, false, $context);

    // check if the result is "Success"
    if ($result == "Success") {
      # echo ../private/resources/registerSuccess.html
      include(dirname(dirname(__FILE__)) . '/private/resources/registerSuccess.html');
    } else {
      ob_start();
      include(dirname(dirname(__FILE__)) . '/private/resources/registerFailure.html');
      $errorpage = ob_get_clean();
      # replace $error with $result
      $errorpage = str_replace('$error', $result, $errorpage);
      echo $errorpage;
    }

  } else {
    # echo html from ../private/resources/registerForm.html
    include(dirname(dirname(__FILE__)) . '/private/resources/registerForm.html');
  }

?>