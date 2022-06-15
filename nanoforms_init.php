<?php

if (file_exists("data/nanoforms.sqlite")) {
  exit("Nanoforms has already been initialized");
};

function test_input($data) {
  $data = trim($data);
  $data = stripslashes($data);
  $data = htmlspecialchars($data);
  return $data;
}

$mail_from = $userID = $userPass = $userPass2 = $signature = $linkValidity = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $mail_to = test_input($_POST['mailto']);
  $mail_from = test_input($_POST['mailfrom']);
  $userID = test_input($_POST['userID']);
  $userPass = test_input($_POST['userPass']);
  $userPass2 = test_input($_POST['userPass2']);
  $signature = test_input($_POST['signature']);
  $linkValidity = test_input($_POST['linkval']);

  if (!($userID && $mail_to && $mail_from && $userPass && $userPass2 && $linkValidity)) {
    exit('Not enough data- no action taken');
  }

  if ($userPass2 !== $userPass) {
    exit('Passwords differ - no action taken');
  }

  if (strtotime('now + ' . $linkValidity) == false) {
    exit('Invalid default link validity - try strings like "3 days" or "2 weeks"');
  }

  try {
    $db = new PDO("sqlite:data/nanoforms.sqlite");
  } catch (PDOException $e) {
    exit("Failed to create database: " . $e->message());
  };

  $db->exec("CREATE TABLE config (
    mail_to TEXT,
    mail_from TEXT,
    mail_signature TEXT,
    default_link_validity TEXT)");

  $db->exec("CREATE TABLE users (
    ID TEXT PRIMARY KEY NOT NULL,
    password TEXT)");

  $db->exec("CREATE TABLE password_resets (
    ID INTEGER PRIMARY KEY AUTOINCREMENT,
    userID TEXT NOT NULL,
    token TEXT NOT NULL UNIQUE,
    expires TEXT)");

  $db->exec("CREATE TABLE surveys (
    name TEXT PRIMARY KEY NOT NULL,
    title TEXT,
    public INTEGER,
    linkValidity TEXT,
    allowRevisit INTEGER,
    testMode INTEGER,
    active INTEGER)");

  $db->exec("CREATE TABLE forms (
    ID INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    surveyName TEXT NOT NULL,
    html TEXT  OT NULL,
    timeUpload INTEGER,
    completeDefinition TEXT)");

  $db->exec("CREATE TABLE privacyStatements (
    ID INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    surveyName TEXT NOT NULL,
    html TEXT NOT NULL,
    timeUpload INTEGER)");

  $db->exec("CREATE TABLE inviteMails (
    ID INTEGER PRIMARY KEY AUTOINCREMENT,
    surveyName TEXT NOT NULL,
    name TEXT NOT NULL,
    html TEXT NOT NULL,
    timeUpload INTEGER)");

  $db->exec("CREATE TABLE subscriberFiles (
    fileID INTEGER PRIMARY KEY AUTOINCREMENT,
    fileName TEXT NOT NULL,
    timeUpload INTEGER)");

  $db->exec("CREATE TABLE subscribers (
    email TEXT PRIMARY KEY,
    stratum TEXT,
    fileID INTEGER,
    selfSubscribed INTEGER,
    startData TEXT,
    status TEXT)");

  $db->exec("CREATE INDEX subscribers_fileID ON subscribers(fileID)");

  $db->exec("CREATE TABLE invitations (
    token TEXT PRIMARY KEY,
    email TEXT NOT NULL,
    inviteMailID INTEGER,
    stratum TEXT,
    status TEXT,
    formID INTEGER,
    privacyStatementID INTEGER,
    expires INTEGER,
    testing INTEGER,
    time INTEGER)");

  $db->exec("CREATE INDEX invitations_email ON invitations(email)");
  $db->exec("CREATE INDEX invitations_formID ON invitations(formID)");

  $db->exec("CREATE TABLE submissions (
    ID INTEGER PRIMARY KEY AUTOINCREMENT,
    invitationToken TEXT NOT NULL,
    email TEXT NOT NULL,
    ipaddr TEXT,
    stratum TEXT,
    formData TEXT,
    testing INTEGER,
    complete INTEGER,
    time INTEGER)");

  $db->exec("CREATE INDEX submissions_invitationToken ON
    submissions(invitationToken)");
  $db->exec("CREATE INDEX submissions_email ON submissions(email)");

  // populate with admin data

  $sql = 'INSERT INTO config (mail_to, mail_from, mail_signature,
    default_link_validity) VALUES (:tomail, :fromail, :signat, :valid)';
    $statement = $db->prepare($sql);
    $statement->bindparam(':tomail', $mail_to, PDO::PARAM_STR);
    $statement->bindparam(':fromail', $mail_from, PDO::PARAM_STR);
    $statement->bindparam(':signat', $signature, PDO::PARAM_STR);
    $statement->bindparam(':valid', $linkValidity, PDO::PARAM_STR);
    $statement->execute();

  $sql = "INSERT INTO users (ID, password) VALUES (:user, :pass)";
  $statement = $db->prepare($sql);
  $statement->bindparam(':user', $userID, PDO::PARAM_STR);
  $statement->bindparam(':pass', password_hash($userPass, PASSWORD_DEFAULT),
  PDO::PARAM_STR);
  $statement->execute();

  $db = null;

  mkdir('tmp');
  
  echo "<!DOCTYPE html>\r\n<html lang=\"en\">\r\n<head>\r\n" .
  "<meta charset=\"UTF-8\">\r\n<title>Nanoforms</title>\r\n" .
  "<link rel=\"stylesheet\" href=\"nanoforms.css\" />\r\n</head>\r\n" .
  "<body>\r\n<h3>Nanoforms initialized.</h3>\r\n<p>" .
  "<a href=\"index.php\">Sign in</a></p>\r\n</body>\r\n</html>";

  exit;

}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Nanoforms</title>
  <link rel="stylesheet" href="nanoforms.css" />
</head>
<body>
  <p style="margin-bottom:2em">
    <span class="huge">Nanoforms</span>
    needs these data to get started:
  </p>
  <form method="post" action="">
    <table>
      <tbody>
        <tr>
          <td style="text-align:right"><label for="userid">User ID (email address):</label></td>
          <td><input type="email" id="userid" name="userID" /></td>
        </tr>
        <tr>
          <td style="text-align:right"><label for="userpass">User password:</label></td>
          <td><input type="password" id="userpass" name="userPass" /></td>
        </tr>
        <tr>
          <td style="text-align:right"><label for="userpass2">Repeat user password:</label></td>
          <td><input type="password" id="userpass2" name="userPass2" /></td>
        </tr>
        <tr>
          <td style="text-align:right"><label for="mailto">Your email for contact forms:</td>
          <td><input type="email" id="mailto" name="mailto" /></td>
        </tr>
        <tr>
          <td style="text-align:right"><label for="mailfrom">From: for emails to subscribers:</td>
          <td><input type="email" id="mailfrom" name="mailfrom" /></td>
        </tr>
        <tr>
          <td style="text-align:right"><label for="signature">Signature in emails to subscribers:</td>
          <td>
            <textarea id="signature" name="signature" rows="3" cols="50">
Kind regards,

The nanoforms team
            </textarea>
          </td>
        </tr>
        <tr>
          <td style="text-align:right"><label for="linkval">Default validity of links:</td>
          <td><input type="text" id="linkval" name="linkval" size="8"
            value="4 days" />
          <em>(e.g. 3 weeks, 2 days, 1 month)</em></td>
        </tr>
        <tr>
          <td colspan="2" style="text-align:center">
            <input type="submit" name="config" value="SUBMIT" />
          </td>
        </tr>
      </tbody>
    </table>
  </form>
</body>
</html>
