<?php
session_start();

require 'common.php';

if (!username()) {
  header('Location: index.php');
  exit;
}

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

function quotEscape($txt, $sep) {
  $txt = trim($txt);
  $txt = str_replace('"', '""', $txt);
  foreach (array($sep, '"', "\n", "\r", "\v") as $qchar) {
    if (strpos($txt, $qchar) !== false) {
      return '"' . $txt . '"';
    }
  }
  return $txt;
}

if (!isset($_SESSION['nano_surveyid'])) {
  errHtml('An error has occurred: missing survey ID');
  exit;
}
$surveyName = $_SESSION['nano_surveyid'];

if (!isset($_SESSION['nano_tempdb'])) {
  errHtml('An error has occurred: missing temporary file');
  exit;
}
$fname = $_SESSION['nano_tempdb'];

$tmpdir = __DIR__ . DIRECTORY_SEPARATOR . 'tmp';

if (!file_exists($tmpdir . DIRECTORY_SEPARATOR . $fname . '.sqlite')) {
  errHtml('An error has occurred: missing temporary file ' . $fname . '.sqlite');
  exit;
}

$tmpRecipFile = 'tmp/' . $fname . '.sqlite';

// Take the opportunity to delete temp files
$tmpFiles = glob($tmpdir . DIRECTORY_SEPARATOR . 'q*.sqlite');
$old = strtotime('now -2 days');
for ($i=0; $i < count($tmpFiles); $i++) {
  if (filemtime($tmpFiles[$i]) < $old) {
    unlink($tmpFiles[$i]);
  }
}

$db = new PDO("sqlite:data/nanoforms.sqlite");

$sql = 'SELECT linkValidity, testMode, active FROM surveys WHERE name = :surv';
$statement = $db->prepare($sql);
$statement->bindparam(':surv', $surveyName, PDO::PARAM_STR);
$statement->execute();
$res = $statement->fetch();
$linkValidity = $res['linkValidity'];
$testMode = $res['testMode'];
$active = $res['active'];
if (!$active) {
  errHtml('Survey ' . $surveyName . ' is currently not active.');
  exit;
}
if (($expires = strtotime('now + ' . $linkValidity)) === false) {
  $expires = strtotime('now + 1 week');
}

$mailID = $formID = 0;
$mailName = $formName = $body = $formHtml = '';

// Mail may not be present (in case of links generation)
$mailsArr = array();
$sql = 'SELECT ID, name, html, timeUpload from inviteMails WHERE surveyName = :surv
ORDER BY timeUpload';
$statement = $db->prepare($sql);
$statement->bindparam(':surv', $surveyName, PDO::PARAM_STR);
$statement->execute();
while (($res = $statement->fetch()) !== false) {
  $mailsArr[] = $res;
  if (!$mailID) {
    $mailID = $res['ID'];
  }
}
// we now have ID of most recent mail file. Override if mail has been chosen.
if (isset($_SESSION['nano_mailid'])) {
  $mailID = $_SESSION['nano_mailid'];
}
if (isset($_GET['mailID'])) {
  $mailID = test_input($_GET['mailID']);
}

// form file MUST be present
$formsArr = array();
$sql = 'SELECT ID, name, html, timeUpload from forms WHERE surveyName = :surv
ORDER BY timeUpload';
$statement = $db->prepare($sql);
$statement->bindparam(':surv', $surveyName, PDO::PARAM_STR);
$statement->execute();
while (($res = $statement->fetch()) !== false) {
  $formsArr[] = $res;
  $formID = $res['ID'];
}
if (!$formID) {
  errHtml('No questionnaire form has been uploaded yet');
  exit;
}
// override
if (isset($_SESSION['nano_formid'])) {
  $formID = $_SESSION['nano_formid'];
}
if (isset($_GET['formID'])) {
  $formID = test_input($_GET['formID']);
}


// need to get current name and html back from arrays
if ($mailID) {
  foreach ($mailsArr as $mArr) {
    if ($mArr['ID'] == $mailID) {
      $mailName = $mArr['name'];
      $body = $mArr['html'];
    }
  }
}
foreach ($formsArr as $fArr) {
  if ($fArr['ID'] == $formID) {
    $formName = $fArr['name'];
    $formHtml = $fArr['html'];
  }
}

$sql = 'SELECT mail_from FROM config';
$stm = $db->prepare($sql);
$stm->execute();
$mailFrom = $stm->fetchColumn();

$db = null;

// Create temp file for iframe: mail
$uniqID = uniqid("m");
$removeArr = array('"', "'", '@', '^', '.', ',', ';');
$mname = str_replace($removeArr, '', $uniqID);
$tmpmname = $tmpdir . DIRECTORY_SEPARATOR . $mname . '.html';
$tmpMail = 'tmp/' . $mname . '.html';

// Take the opportunity to delete temp files
$tmpFiles = glob($tmpdir . DIRECTORY_SEPARATOR . 'm*.html');
$old = strtotime('now -2 days');
for ($i=0; $i < count($tmpFiles); $i++) {
  if (filemtime($tmpFiles[$i]) < $old) {
    unlink($tmpFiles[$i]);
  }
}

$h = fopen($tmpmname, "w") or die ('Error creating ' . $tmpmname);
if (fwrite($h, $body) === FALSE) {
  echo($tmpmname . ' is not writable');
  exit;
}
fclose($h);

// Create temp file for iframe: form
$uniqID = uniqid("f");
$removeArr = array('"', "'", '@', '^', '.', ',', ';');
$fname = str_replace($removeArr, '', $uniqID);
$tmpfname = $tmpdir . DIRECTORY_SEPARATOR . $fname . '.html';
$tmpForm = 'tmp/' . $fname . '.html';

// Take the opportunity to delete temp files
$tmpFiles = glob($tmpdir . DIRECTORY_SEPARATOR . 'f*.html');
$old = strtotime('now -2 days');  // server time
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


$tempDB = new PDO("sqlite:" . $tmpRecipFile);
$sql = 'SELECT COUNT(*) FROM subs WHERE selected = 1';
$stm = $tempDB->prepare($sql);
$stm->execute();
$selectedN = $stm->fetchColumn();
$tempDB = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

  foreach (array_keys($_POST) as $elem) {
    if (preg_match('/^showf_([0-9]+)$/', $elem, $matches)) {
      $formID = test_input($matches[1]);
      break;
    } elseif (preg_match('/^showm_([0-9]+)$/', $elem, $matches)) {
      $mailID = test_input($matches[1]);
      break;
    }
  }

  if (isset($_POST['action'])) {

    $action = test_input($_POST['action']);
    $from = $subject = '';
    if ($action == 'send') {
      $from = test_input($_POST['from']);
      if (!$from) {
        errHtml('An error occurred: missing sender');
        exit;
      }
      $subject = test_input($_POST['subject']);
      if (!$subject) {
        errHtml('An error occurred: missing subject');
        exit;
      }
    }
    $validity = test_input($_POST['validity']);
    if (!$validity) {
      $validity = '12 months';
    }
    if (strtotime('now + ' . $validity) !== false) {
      $expires = strtotime('now + ' . $validity);
    } else {
      $expires = strtotime('now + 12 months');
    }

    $uri = test_input($_POST['uri']);
    if (!$uri) {
      errHtml('An error occurred: missing url');
      exit;
    }

    $EOL = "\r\n";
    if (isset($_POST['newline'])) {
      if ($_POST['newline'] == 'lf') {
        $EOL = "\n";
      }
    }
    $colsep = ";";
    if (isset($_POST['colsep'])) {
      $colsep = $_POST['colsep'];
    }

    $db = new PDO("sqlite:data/nanoforms.sqlite");

    if ($action == 'send') {
      $sql = "SELECT html FROM inviteMails WHERE ID = :id";
      $statement = $db->prepare($sql);
      $statement->bindparam(':id', $mailID, PDO::PARAM_INT);
      $statement->execute();
      if (($res = $statement->fetch()) === false) {
        errHtml('An error has occurred: unknown mail ID');
        exit;
      }
      $body = $res['html'];

      $replaceKeys = array();
      if (preg_match_all('/%_[^%_]+_%/', $body, $matches) > 0) {
        foreach ($matches[0] as $match) {
          $key = strtolower(substr($match, 2, strlen($match) - 4));
          $replaceKeys[$key] = $match;
        }
      }

      // Always set content-type when sending HTML email
      $headers = 'MIME-Version: 1.0' . "\r\n";
      $headers .= 'Content-type:text/html;charset=UTF-8' . "\r\n";
      // More headers
      $headers .= 'From: ' . $from . "\r\n";
    }

    $headerAr = array(
      quotEscape('serialno', $colsep),
      quotEscape('email', $colsep),
      $action == "send" ? quotEscape('status', $colsep) : quotEscape('url', $colsep)
    );

    header("Content-type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"nanoforms-log.csv\"");

    $outstream = fopen("php://output", 'w');

    fwrite($outstream, implode($colsep, $headerAr) . $EOL);

    $tmpDB= new PDO("sqlite:" . $tmpRecipFile);
    $sql = "SELECT email, startData FROM subs WHERE selected = 1";
    $stm = $tmpDB->prepare($sql);
    $stm->execute();

    $counter = 0;
    $mailed = $refused = $misses = 0;
    $mailParams = '-f ' . $from;  // hosting provider recommends this
    $startTime = strtotime('now'); // same for all so we can identify mailing
    while (($res = $stm->fetch()) !== false) {
      $recAr = array();
      $counter++;
      $token = openssl_random_pseudo_bytes(16);
      $token = bin2hex($token);

      // $body --> $message
      $uri .= '/deploy.php?tk=' . $token;
      $email = $res['email'];

      if ($action == 'send') {
        $startData = $res['startData'];
        $startAr = explode(";", $startData);
        $message = $body;
        $recipStart = array();
        foreach ($startAr as $tuple) {
          list($key, $val) = explode("=", $tuple);
          $recipStart[strtolower($key)] = $val;
        }
        $errs = array();
        foreach ($replaceKeys as $lower=>$orig) {
          if ($lower == 'link') {
            $message = str_replace($orig, $uri, $message);
          } elseif (array_key_exists($lower, $recipStart)) {
            $message = str_replace($orig, $recipStart[$lower], $message);
          } else {
            $errs[] = $orig;
          }
        }
        if (count($errs) == 0) {

          // Verp
          $emailArr = explode('@', $email);
          $senderArr = explode('@', $from);
          $headers .= 'Return-Path: ' . $senderArr[0] . '+' . implode('=', $emailArr) .
          '@' . $senderArr[1] . "\r\n";

          if (mail($email, $subject, $message, $headers, $mailParams)) {
            $status = 'mailed';
            $mailed++;
          } else {
            $status = 'refused';
            $refused++;
          }

        } else {  // we have errors
          $status = 'missing ' . implode(($colsep == ',' ? '; ' : ', '), $errs);
          $status = quotEscape($status, $colsep);
          $misses++;
        }

      } else {  // links
        $status = 'link generated';
      }

      $sql = 'INSERT INTO invitations (token, email, inviteMailID,
        status, formID, privacyStatementID, expires, testing, time) VALUES
        (:tok, :mail, :invmid, :stat, :frm, :prv, :xpir, :test, :when)';
        $statement = $db->prepare($sql);
        $statement->bindparam(':tok', $token, PDO::PARAM_STR);
        $statement->bindparam(':mail', $email, PDO::PARAM_STR);
        $statement->bindparam(':invmid', $mailID, PDO::PARAM_INT);
        $statement->bindparam(':stat', $status, PDO::PARAM_STR);
        $statement->bindparam(':frm', $formID, PDO::PARAM_INT);
        $statement->bindparam(':prv', $statmID, PDO::PARAM_INT);
        $statement->bindparam(':xpir', $expires, PDO::PARAM_INT);
        $statement->bindparam(':test', $testMode, PDO::PARAM_INT);
        $statement->bindparam(':when', $startTime, PDO::PARAM_INT);
        $statement->execute();

        $recAr = array($counter, $email, $action == "send" ? $status : $uri);
        fwrite($outstream, implode($colsep, $recAr) . $EOL);

      }

      fclose($outstream);
      $db = null;
      $tmpDB = null;
      exit;
    }   // subMailParms

  }  // POST

$_SESSION['nano_formid'] = $formID;
$_SESSION['nano_mailid'] = $mailID;

?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Mailing</title>
  <link rel="stylesheet" type="text/css" href="nanoforms.css" />
</head>
<body>

  <div class="sidenav">
    <a href="subscribers.php">Subscribers</a>
    <a href="surveys.php">Surveys</a>
    <a href="survey.php"><?php echo $surveyName; ?></a>
    <a href="data.php">Data</a>
    <a href="logout.php">Log out</a>
  </div>

  <!-- Page content -->
  <div class="sidemain">

    <p style="margin-bottom:2em">
      <span class="huge">Mailing</span> survey
      <strong><?php echo $surveyName; ?></strong>: invite
      <span class="huge"><?php echo $selectedN; ?></span> selected recipients.
    </p>

    <?php if ($testMode) { echo '<h2 class="warning">Warning: TEST!</h2>'; } ?>

    <h3>Questionnaire for this mailing:</h3>

    <div id="chooseForm">
    <?php
    if (count($formsArr) > 1) {
      echo '
      <form method="post" action="">
      <table>
        <thead>
          <tr>
            <th>name</th>
            <th>uploaded</th>
            <th></th>
          </tr>
        </thead>
        <tbody>';
        foreach ($formsArr as $fArr) {
          echo '<tr>' . PHP_EOL .
          '<td>' . $fArr['name'] . '</td>' .
          '<td>' . date("Y-m-d H:i", $fArr['timeUpload']) .
          '</td><td><input type="submit" name="showf_' . $fArr['ID'] . '" ' .
          'value="Select"';
          if ($fArr['ID'] == $formID) {
            echo ' disabled';
          }
          echo ' /></td></tr>' . PHP_EOL;
        }
        echo '
        </tbody>
      </table>
    </form>';
    }
    ?>

    <div id="formdiv">
      <p><?php echo $formName; ?></p>
      <iframe src="<?php echo $tmpForm;?>" style="height:200px;width:700px;"
        title="<?php echo $tmpForm;?>">
      </iframe>
    </div>

  </div>

  <h3>Email body for this mailing:</h3>

    <div id="chooseMail">
      <?php
      if (count($mailsArr) > 1) {
        echo '
      <form method="post" action="">
      <table>
        <thead>
          <tr>
            <th>name</th>
            <th>uploaded</th>
            <th></th>
          </tr>
        </thead>
        <tbody>';
        foreach ($mailsArr as $mArr) {
          echo '<tr>' . PHP_EOL .
          '<td>' . $mArr['name'] . '</td>' .
          '<td>' . date("Y-m-d H:i", $mArr['timeUpload']) .
          '</td><td><input type="submit" name="showm_' . $mArr['ID'] . '" ' .
          'value="Select"';
          if ($mArr['ID'] == $mailID) {
            echo ' disabled';
          }
          echo ' /></td></tr>' . PHP_EOL;
        }
        echo '
        </tbody>
      </table>
    </form>';
    }
    ?>

    <div id="maildiv">
      <p><?php echo $mailName; ?></p>
      <iframe src="<?php echo $tmpMail;?>" style="height:200px;width:700px;"
        id="mailframe" title="<?php echo $mailName;?>">
      </iframe>
    </div>

  </div>

  <div class="spacedout" id="checkparams">

    <h3>Complete, check and correct:</h3>

    <form method="post" action="" onsubmit="showLoader();">
      <table>
        <tbody>
          <tr>
            <td><label for="action">Action:</label></td>
            <td>
              <select name="action" id="action" onchange="toggleFrom()">
                <option value="send">Send invite emails</option>
                <option value="links">Generate links
                <?php echo $mailID ? '' : ' selected';?></option>
              </select>
            </td>
          </tr>
          <tr>
            <td><label for="from">From:</label></td>
            <td><input type="text" size="80" name="from" id="from"
            value="<?php echo $mailFrom;?>"</td>
          </tr>
          <tr>
            <td><label for="subject">Subject:</label></td>
            <td><input type="text" size="80" name="subject" id="subject"
            </td>
          </tr>
          <tr>
            <td><label for="validity">Link validity:</label></td>
            <td><input type="text" name="validity" id="validity" size="8"
            value="<?php echo $linkValidity;?>" /></td>
          </tr>
          <tr>
            <td><label for="uri">This site:</label></td>
            <td><input type="text" size="80" name="uri" id="uri"
            value="<?php echo dirname(curPageURL());?>"</td>
          </tr>
          <td><label for="newline">csv file line break:</label></td>
          <td>
            <select name="newline" id="newline">
              <option value="lf">LF (Linux, macOS)</option>
              <option value="crlf">CR LF (Windows)</option>
            </select>
          </td>
        </tr>
        <tr>
          <td><label for="colsep">column separator:</label></td>
          <td>
            <input type="text" name="colsep" id="colsep" size="1" value=";" />
          </td>
        </tr>
        <tr>
          <td><input type="submit" name="subMailParms"
          id="subMailParms" class="big" value="SUBMIT" /></td>
          <td id="logger" style="display:none">
            <span class="big">Check your Downloads folder for
            <em>nanoforms-log.csv</em></span>
          </td>
        </tr>
      </tbody>
    </table>
  </form>
</div>

</div>

<script>
function showLoader() {
  document.getElementById('logger').style.display = "table-cell";
  document.getElementById('subMailParms').disabled = true;
}
function toggleFrom() {
  if (document.getElementById('action').value == "links") {
    document.getElementById('from').disabled = true;
    document.getElementById('subject').disabled = true;
  } else {
    document.getElementById('from').disabled = false;
    document.getElementById('subject').disabled = false;
  }
}
window.onload = toggleFrom();
</script>

</body>
</html>
