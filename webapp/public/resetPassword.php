<?php

  include(dirname(dirname(__FILE__)) . '/private/key.php');
  $creation_api_key = CREATION_API_KEY;
  $api_base_url = API_BASE_URL;

  # Check whether the POST variables are not empty
  if (!empty($_POST['email'])) {

    # Get the POST variables
    $email = $_POST['email'];

    // Set API URL
    $url = $api_base_url . '/DBConnect.php?action=resetPassword';

    // build postdata with http_build_query
    $postdata = http_build_query(
      array(
        'email' => $email,
        'api_key' => $creation_api_key
      )
    );

    // build opts
    $opts = array('http' =>
      array(
        'method'  => 'POST',
        'header'  => 'Content-type: application/x-www-form-urlencoded',
        'content' => $postdata
      )
    );

    // create context
    $context  = stream_context_create($opts);

    // fetch result
    $result = file_get_contents($url, false, $context);

    // check if the result is "Success"
    if ($result == "Success") {
      # echo ../private/resources/resetPasswordSuccess.html
      include(dirname(dirname(__FILE__)) . '/private/resources/resetPasswordSuccess.html');
    } else {
      # echo ../private/resources/resetPasswordFailure.html
      ob_start();
      include(dirname(dirname(__FILE__)) . '/private/resources/resetPasswordFailure.html');
      $errorpage = ob_get_clean();
      # replace $error with $result
      $errorpage = str_replace('$error', $result, $errorpage);
      echo $errorpage;
    }

  } else {
    # echo html from ../private/resources/resetPasswordForm.html
    include(dirname(dirname(__FILE__)) . '/private/resources/resetPasswordForm.html');
  }

?>