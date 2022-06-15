<?php

define('SUPPORTED_LANGUAGES', ['en', 'it', 'nl']);

require 'common.php';

function escapeEq($txt) {
  return str_replace(array('=', ';'), array(':', ','), $txt);
}

// Evaluate every var-operator-value (in)equality in $condition against
// $data and return a copy with each replaced with 'true' or 'false'
function substCond($condition, &$data) {
  $out = '';
  while (preg_match('/(\w+?) ([!=<>]{1,2}?) ([^\(\)]+?)([\(\)]*?)($| && | \|\| )/', $condition,
  $matches, PREG_OFFSET_CAPTURE)) {
    $start = $matches[0][1];
    $field = $matches[1][0];
    $oper = $matches[2][0];
    $valu = $matches[3][0];
    if (preg_match('/^&apos;(.*)&apos;$/', $valu, $submatch)) {
      $valu = $submatch[1];
    }
    // flags used by forms.php (incluse &apos;)
    $valu = htmlspecialchars_decode($valu, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5);
    $pos = $matches[4][1];
    $out .= substr($condition, 0, $start);  // dangerous?
    $truth = false;
    if (isset($data[$field])) {
      if ($oper == '==') {
        $truth = ($valu == $data[$field]);
      } elseif ($oper == '!=') {
        $truth = ($valu != $data[$field]);
      } elseif ($oper == '>') {
        $truth = ($valu > $data[$field]);
      } elseif ($oper == '>=') {
        $truth = ($valu >= $data[$field]);
      } elseif ($oper == '<') {
        $truth = ($valu < $data[$field]);
      } elseif ($oper == '<=') {
        $truth = ($valu <= $data[$field]);
      }
    }
    $out .= ($truth ? 'true' : 'false');
    $condition = substr($condition, $pos);
  }
  $out .= $condition;   // dangerous?
  return $out;
}

$outcome = array();
foreach (SUPPORTED_LANGUAGES as $key) {
  $outcome[$key] = '';
}

if ($_SERVER["REQUEST_METHOD"] != "POST") {
  header('Location: participate.php');
}
if (!isset($_POST['tk__'])) {
  header('Location: participate.php');
  exit;
}
$token = test_input($_POST['tk__']);
$db = new PDO("sqlite:data/nanoforms.sqlite");
$sql = 'SELECT a.email, a.expires, a.formID, a.time, b.stratum
FROM invitations a
INNER JOIN subscribers b ON a.email = b.email
WHERE a.token = :tok';
$statement = $db->prepare($sql);
$statement->bindparam(':tok', $token, PDO::PARAM_STR);
$statement->execute();
if (($res = $statement->fetch()) === false) {  // token does not exist
  header('Location: participate.php');
  exit;
}
$email = $res['email'];
$expires = $res['expires'];
$formID = $res['formID'];
$stratum = $res['stratum'];

// I guess it doesn't make sense to refuse latecomers. See deploy.php.
if ($expires < strtotime('now')) {
  $outcome['en'] = 'Your responses have been submitted after the survey\'s
  closing time. Please contact the survey coordinator.';
  $outcome['it'] = 'Le sue risposte sono state inoltrate oltre il termine di
  chiusura. Prego contattare il coordinatore del sondaggio.';
  $outcome['nl'] = 'Uw antwoorden zijn ingediend na de sluitingstermijn. Neem
  aub contact op met de onderzoeks coordinator.';
}

$postAr = array();
foreach (array_keys($_POST) as $key) {
  $postAr[] = escapeEq(test_input($key)) . '=' .
  escapeEq(test_input($_POST[$key]));
}
$postData = implode(';', $postAr);

$sql = 'SELECT a.html, a.completeDefinition, b.testMode, b.active
FROM forms a
INNER JOIN surveys b ON b.name = a.surveyName
WHERE a.ID = :frm';
$statement = $db->prepare($sql);
$statement->bindparam(':frm', $formID, PDO::PARAM_INT);
$statement->execute();
$res = $statement->fetch();
$html = $res['html'];
$testMode = $res['testMode'];
$active = $res['active'];
$def = $res['completeDefinition'];

if (!preg_match('/<html\s+[^>]*lang\s*=\s*(?:\"|\')([a-z][a-z])/i', $html,
$matches)) {
  $language = SUPPORTED_LANGUAGES[0];
} else {
  $language = strtolower($matches[1]);
  if (!in_array($language, SUPPORTED_LANGUAGES)) {
    $language = SUPPORTED_LANGUAGES[0];
  }
}

if (!$active) {
  $outcome['en'] = 'The survey you have submitted responses for does not
  appear to be active. Please contact the survey coordinator.';
  $outcome['it'] = 'Il sondaggio per cui ha inoltrato risposte non
  risulta attivo. Prego contattare il coordinatore del sondaggio.';
  $outcome['nl'] = 'Het onderzoek in het kader waarvan u antwoorden heeft
  ingediend blijkt niet actief. Neem aub contact op met de coordinator.';
}
if ($def) {
  $substed = substCond($def, $_POST);
  // Need to safely evaluate the complete condition
  if (!preg_match('/^(true|false|\(|\)|&|\|| )+$/', $substed)) {
    $result = false;
    $complete = -1;  // can't throw error here; this should signal the error
  } else {
    $result = eval('return (' . $substed . ');');
    $complete = ($result ? 1 : 0);
  }
} else {
  $complete = 0;
}

// https://stackoverflow.com/questions/15699101/get-the-client-ip-address-using-php
$ipAddr = getenv('HTTP_CLIENT_IP')?:
getenv('HTTP_X_FORWARDED_FOR')?:
getenv('HTTP_X_FORWARDED')?:
getenv('HTTP_FORWARDED_FOR')?:
getenv('HTTP_FORWARDED')?:
getenv('REMOTE_ADDR');

// We always just append new submissions
$sql = 'INSERT INTO submissions (invitationToken, email, ipaddr, stratum,
formData, testing, complete, time) VALUES (:tok, :mail, :ip, :strat, :data,
 :test, :compl, :tim)';
$statement = $db->prepare($sql);
$statement->bindparam(':tok', $token, PDO::PARAM_STR);
$statement->bindparam(':mail', $email, PDO::PARAM_STR);
$statement->bindparam(':ip', $ipAddr, PDO::PARAM_STR);
$statement->bindparam(':strat', $stratum, PDO::PARAM_STR);
$statement->bindparam(':data', $postData, PDO::PARAM_STR);
$statement->bindparam(':test', $testMode, PDO::PARAM_INT);
$statement->bindparam(':compl', $complete, PDO::PARAM_INT);
$statement->bindparam(':tim', strtotime('now'), PDO::PARAM_INT);
if (!$statement->execute()) {
  $outcome['en'] = 'Internal server error while registering your responses. Please
  contact the survey coordinator';
  $outcome['it'] = 'Errore interno del server durante la registrazione delle Sue
  risposte. Prego contattare il coordinatore del sondaggio.';
  $outcome['nl'] = 'Interne serverfout bij de opslag van Uw antwoorden. Neem
  aub contact op met de onderzoeks coordinator.';
} else {
  // $substID = $db->lastInsertId();
  if (!$outcome[$language]) {
    $outcome['en'] = 'Your responses have been registered. Thank you very much!';
    $outcome['it'] = 'Le Sue risposte sono state registrate. Grazie mille!';
    $outcome['nl'] = 'Uw antwoorden zijn verwerkt. Hartelijk dank!';
  }
}

$db = null;

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Questionnaire submit</title>
  <link rel="stylesheet" type="text/css" href="nanoforms.css" />
</head>
<body>
  <h1><?php echo $outcome[$language];?></h1>
</body>
</html>
