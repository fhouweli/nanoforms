<?php
session_start();

require 'common.php';

if (!file_exists("data/nanoforms.sqlite")) {
  exit("data/nanoforms.sqlite does noet exist\n");
}

$username = $password = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $username = test_input($_POST["nanouser"]);
  $password = test_input($_POST["nanopass"]);
}

if (!$username) {
  $_SESSION['nano_isauth'] = "";
  header("Location: index.php");
  exit;
}

try {
  $db = new PDO("sqlite:data/nanoforms.sqlite");
} catch (PDOException $e) {
  exit("Failed to open database: " . $e->message() . "\n");
}
$sql = "SELECT password from users WHERE ID = :id";
$statement = $db->prepare($sql);
$statement->bindParam(':id', $username, PDO::PARAM_STR);
$statement->execute();
$storedHash = $statement->fetchColumn();

if (!$storedHash) {
  $db = null;
  $_SESSION['nano_isauth'] = "";
  header("Location: index.php");
  exit;
}
if (!password_verify($password, $storedHash)) {
  $db = null;
  $_SESSION['nano_isauth'] = "";
  header("Location: index.php");
  exit;
}

$_SESSION['nano_isauth'] = $username;

$sql = "SELECT name, rowid FROM surveys ORDER BY rowid DESC LIMIT 1;";
$statement = $db->prepare($sql);
$statement->execute();
$surveyName = $statement->fetchColumn();
$_SESSION['nano_surveyid'] = $surveyName;

$db = null;

if (!$surveyName) {
  header("Location: surveys.php");
} else {
  header("Location: survey.php");
}
