<?php
session_start();
session_destroy();

date_default_timezone_set("UTC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Logout</title>
  <link rel="stylesheet" href="nanoforms.css" />
</head>
<body>
  <h3>You have been logged out.</h3>
  <p>
    <a href="index.php">Sign in again</a>
  </p>
</body>
</html>
