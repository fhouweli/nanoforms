<?php

define('SUPPORTED_LANGUAGES', ['en', 'it', 'nl']);
$msg1 = array();
$msg1['en'] = 'This link has expired';
$msg1['it'] = 'Questo collegamento è scaduto';
$msg1['nl'] = 'Deze link is verlopen';

$msg2 = array();
$msg2['en'] = 'Request new link';
$msg2['it'] = 'Richiedi nuovo collegamento';
$msg2['nl'] = 'Vraag nieuwe link aan';

$msg3 = array();
$msg3['en'] = 'You have already answered the questionnaire. Thank you.';
$msg3['it'] = 'Ha già risposto al questionario. Grazie.';
$msg3['nl'] = 'U heeft de vragenlijst al beantwoord. Dank U.';

require 'common.php';
require 'fieldList.php';

// https://stackoverflow.com/questions/5216172/getting-current-url
function curPageURL() {
    $pageURL = 'http';
    if ($_SERVER["HTTPS"] == "on") {
      $pageURL .= "s";
    }
    $pageURL .= "://";
    if ($_SERVER["SERVER_PORT"] != "80") {
      $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].
      $_SERVER["REQUEST_URI"];
    } else {
      $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
    }
    return $pageURL;
}

if (!isset($_GET['tk'])) {
  errHtml('Your invitation is ill-formed. Please contact the survey
  coordinator');
  exit;
}
$token = test_input($_GET['tk']);
$db = new PDO("sqlite:data/nanoforms.sqlite");
$sql = 'SELECT a.email, a.formID, a.expires, b.surveyName, b.html, c.allowRevisit
from invitations a
INNER JOIN forms b ON b.ID = a.formID
INNER JOIN surveys c ON c.name = b.surveyName
WHERE a.token = :tok';
$statement = $db->prepare($sql);
$statement->bindparam(':tok', $token, PDO::PARAM_STR);
$statement->execute();
if (($res = $statement->fetch()) === false) {  // token does not exist
  errHtml('Your invitation is ill-formed.<br />Please contact the survey
  coordinator');
  exit;
}

$email = $res['email'];
$formID = $res['formID'];
$expires = $res['expires'];
$surveyName = $res['surveyName'];
$formHtml = $res['html'];
$revis = $res['allowRevisit'];

if (!preg_match('/<html\s+[^>]*lang\s*=\s*(?:\"|\')([a-z][a-z])/i', $formHtml,
$matches)) {
  $language = SUPPORTED_LANGUAGES[0];
} else {
  $language = strtolower($matches[1]);
  if (!in_array($language, SUPPORTED_LANGUAGES)) {
    $language = SUPPORTED_LANGUAGES[0];
  }
}

if ($expires < strtotime('now')) {
  $hmac = substr(hash_hmac('sha1', $formID, 'nano'), 0, 5);
 errHtml($msg1[$language] . '.<br />
  <a href="participate.php?f=' . $formID . '&amp;c=' . $hmac . '">' . $msg2[$language] . '</a>');
  exit;
}

$sql = 'SELECT stratum, startData FROM subscribers WHERE email = :mail';
$statement = $db->prepare($sql);
$statement->bindparam(':mail', $email, PDO::PARAM_STR);
$statement->execute();
if (($res = $statement->fetch()) === false) {  // should never happen
  echo 'Internal server error: subscriber not found';
  exit;
}
$stratum = $res['stratum'];
$startData = $res['startData'];
$startAr = array();
$tempAr = explode(";", $startData);
foreach ($tempAr as $temp) {
  list($key, $val) = explode("=", $temp);
  $startAr[strtolower($key)] = $val;
}

$formData = '';
$complete = 0;
$sql = 'SELECT formData, complete, time FROM submissions WHERE invitationToken = :tok
AND testing = 0 ORDER BY time';
$statement = $db->prepare($sql);
$statement->bindparam(':tok', $token, PDO::PARAM_STR);
$statement->execute();
while (($res = $statement->fetch()) !== false) {
  $formData = $res['formData'];
  $complete = $res['complete'];
}

$db = null;

if ($formData) {
  if ($revis == 4 || ($complete && $revis == 2)) {
    errHtml($msg3[$language]);
    exit;
  }
}


$formAr = array();
$tempAr = explode(';', $formData);
foreach ($tempAr as $temp) {
  list($key, $val) = explode("=", $temp);
  $formAr[strtolower($key)] = $val;
}

$fieldArr = fieldList($formHtml);
foreach ($fieldArr as $fArr) {
  $name = strtolower($fArr['name']);
  if (isset($formAr[$name])) {
    $newVal = $formAr[$name];
  } elseif (isset($startAr[$name])) {
    $newVal = $startAr[$name];
  } elseif ($name == 'stratum') {
    $newVal = $stratum;
  } else {
    continue;
  }
  $formHtml = setHtmlVal($formHtml, $name, $newVal);
}

if ($revis == 3) /* read only */ {
  $uri = '#';
} else {
  $uri = curPageURL();
  if (($pos = strpos($uri, '?tk=')) !== false) {
    $uri = substr($uri, 0, $pos);
  }
  $uri = dirname($uri) . '/incoming.php';
}

while (preg_match('/%_TARGET_%[^>]+>/i', $formHtml,  $matches,
PREG_OFFSET_CAPTURE)) {
  // todo what if quotes missing?
  $wholeTag = $matches[0][0];
  $absStart = $matches[0][1];
  $absNext = $absStart + strlen('%_TARGET_%');
  $absEnd = $absStart + strlen($wholeTag);
  $restHtml = substr($formHtml, $absEnd);
  $formHtml = substr($formHtml, 0, $absStart) . $uri .
  substr($formHtml, $absNext, $absEnd - $absNext);
  if ($revis == 2) {
    if (str_ends_with($formHtml, '/>')) {
      $formHtml = substr($formHtml, -2) . ' disabled />';
    } else {
      $formHtml = substr($formHtml, -1) . ' disabled />';
    }
  }
  $formHtml .= PHP_EOL . '<input type="hidden" name="tk__" value="' .
  $token . '" />' . PHP_EOL . $restHtml;
}

$uniqID = uniqid("q");
$removeArr = array('"', "'", '@', '^', '.', ',', ';');
$fname = str_replace($removeArr, '', $uniqID);
$tmpdir = __DIR__ . DIRECTORY_SEPARATOR . 'tmp';
$tmpfname = $tmpdir . DIRECTORY_SEPARATOR . $fname . '.html';
$tmpForm = 'tmp/' . $fname . '.html';

// Take the opportunity to delete temp files
$tmpFiles = glob($tmpdir . DIRECTORY_SEPARATOR . 'q*.html');
$old = strtotime('now -2 days');
for ($i=0; $i < count($tmpFiles); $i++) {
  if (filemtime($tmpFiles[$i]) < $old) {
    unlink($tmpFiles[$i]);
  }
}

$h = fopen($tmpfname, "w") or die ('Error creating ' . $tmpfname);
if (fwrite($h, $formHtml) === FALSE) {
  echo($tmpfname . ' is not writable');
  exit;
}
fclose($h);
header('Location: ' . $tmpForm);
?>
