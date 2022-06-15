<?php

date_default_timezone_set("UTC");

function test_input($data) {
  $data = trim($data);
  $data = stripslashes($data);
  $data = htmlspecialchars($data);
  return $data;
}

function username() {
  if (array_key_exists('nano_isauth', $_SESSION)) {
    return $_SESSION['nano_isauth'];
  }
  return "";
}

function errHtml($errMsg) {
  echo '<!DOCTYPE html>
  <html lang="en">
  <head>
  <meta charset="UTF-8">
  <title>Nanoforms</title>
  <link rel="stylesheet" type="text/css" href="nanoforms.css" />
  </head>
  <body>
  <p class="alarm">' . $errMsg . '</p>
  </body>
  </html>';
}

?>
