<?php
session_start();

require 'common.php';

$MAX_FILE_SIZE = 500000;

if (!username()) {
  header('Location: index.php');
  exit;
}

$surveyName = test_input($_SESSION['nano_surveyid']);

if (!$surveyName) {
  header('Location: surveys.php');
  exit;
}

$tmpdir = __DIR__ . DIRECTORY_SEPARATOR . 'tmp';

// Take the opportunity to delete temp files
$tmpFiles = glob($tmpdir . DIRECTORY_SEPARATOR . 'q*.sqlite');
$old = strtotime('now -2 days');   // server time
for ($i=0; $i < count($tmpFiles); $i++) {
  if (filemtime($tmpFiles[$i]) < $old) {
    unlink($tmpFiles[$i]);
  }
}

$fname = "x";
if (array_key_exists('nano_tempdb', $_SESSION)) {
  $fname = $_SESSION['nano_tempdb'];
}
if (file_exists($tmpdir . DIRECTORY_SEPARATOR . $fname . '.sqlite')) {
  $tmpFile = 'tmp/' . $fname . '.sqlite';
  $tempDB = new PDO("sqlite:" . $tmpFile);
  $sql = 'UPDATE subs SET selected = 0';
  $stm = $tempDB->prepare($sql);
  $stm->execute();
} else {
  $uniqID = uniqid("q");
  $removeArr = array('"', "'", '@', '^', '.', ',', ';');
  $fname = str_replace($removeArr, '', $uniqID);
  $tmpFile = 'tmp/' . $fname . '.sqlite';
  $tempDB = new PDO("sqlite:" . $tmpFile);
  $tempDB->exec("CREATE TABLE subs (
    recno INTEGER PRIMARY KEY AUTOINCREMENT,
    fileName TEXT,
    timeUpload INTEGER,
    stratum TEXT,
    email TEXT,
    startData TEXT,
    status TEXT,
    lastInviteTime INTEGER,
    lastInviteSurvey TEXT,
    lastSubmitTime INTEGER,
    lastSubmitSurvey TEXT,
    lastComplete TEXT,
    selected INTEGER)");
  $db = new PDO("sqlite:data/nanoforms.sqlite");
  $sql = 'SELECT a.fileID, a.fileName, a.timeUpload, b.stratum, b.email,
  b.startData, b.status FROM subscriberFiles a INNER JOIN subscribers b
  ON a.fileID = b.fileID';
  $statement = $db->prepare($sql);
  $statement->execute();
  while (($res = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
    $sql = 'SELECT a.surveyName, b.time FROM inviteMails a INNER JOIN
    invitations b ON b.inviteMailID = a.ID WHERE b.email = :mail ORDER
    BY b.time DESC LIMIT 1';
    $stm2 = $db->prepare($sql);
    $stm2->bindparam(':mail', $res['email'], PDO::PARAM_STR);
    $stm2->execute();
    if (($res2 = $stm2->fetch(PDO::FETCH_ASSOC)) !== false) {
      $lastInviteTime = date("Ymd", $res2['time']);
      $lastInviteSurvey = $res2['surveyName'];
    } else {
      $lastInviteTime = '';
      $lastInviteSurvey = '';
    }
    $sql = 'SELECT a.time, a.complete, c.surveyName FROM submissions a
    INNER JOIN invitations b ON b.token = a.invitationToken
    INNER JOIN inviteMails c ON b.inviteMailID = c.ID
    WHERE b.email = :mail ORDER BY a.time DESC LIMIT 1';
    $stm3 = $db->prepare($sql);
    $stm3->bindparam(':mail', $res['email'], PDO::PARAM_STR);
    $stm3->execute();
    if (($res3 = $stm3->fetch(PDO::FETCH_ASSOC)) !== false) {
      $lastSubmitTime = date("Ymd", $res3['time']);
      $lastSubmitSurvey = $res3['surveyName'];
      $lastComplete = $res3['complete'];
    } else {
      $lastSubmitTime = '';
      $lastSubmitSurvey = '';
      $lastComplete = '';
    }
    $sql = 'INSERT INTO subs (fileName, timeUpload, stratum, email,
      startData, status, lastInviteTime, lastInviteSurvey, lastSubmitTime,
      lastSubmitSurvey, lastComplete, selected) VALUES
      (:f1, :f2, :f3, :f4,
        :f5, :f6, :f7, :f8, :f9,
        :f10, :f11, 0)';
        $stm4 = $tempDB->prepare($sql);
        $stm4->bindparam(':f1', $res['fileName'], PDO::PARAM_STR);
        $stm4->bindparam(':f2', date("Ymd", $res['timeUpload']), PDO::PARAM_INT);
        $stm4->bindparam(':f3', $res['stratum'], PDO::PARAM_STR);
        $stm4->bindparam(':f4', $res['email'], PDO::PARAM_STR);
        $stm4->bindparam(':f5', $res['startData'], PDO::PARAM_STR);
        $stm4->bindparam(':f6', $res['status'], PDO::PARAM_STR);
        $stm4->bindparam(':f7', $lastInviteTime, PDO::PARAM_INT);
        $stm4->bindparam(':f8', $lastInviteSurvey, PDO::PARAM_STR);
        $stm4->bindparam(':f9', $lastSubmitTime, PDO::PARAM_INT);
        $stm4->bindparam(':f10', $lastSubmitSurvey, PDO::PARAM_STR);
        $stm4->bindparam(':f11', $lastComplete, PDO::PARAM_INT);
        $stm4->execute();
      }
      $db = null;
      $tempDB = null;
      $_SESSION['nano_tempdb'] = $fname;
  }

function numeric_filt($txt, &$errs) {
  if (strlen($txt) == 0) {
    return '';
  }
  foreach (array('&lt;'=>'<', '&le;'=>'<=', '&gt;'=>'>', '&ge;'=>'>=')
  as $key=>$val) {
    $txt = str_replace($key, $val, $txt);
  }
  if (preg_match('/^[<=!> ]{1,2}[0-9]+$/', $txt) !== 1) {
    $errs[] = 'Invalid filter for numeric field: ' . $txt;
  }
  return $txt;
}

function storeFilt($key, $numeric, &$arr, &$errs) {
  if (isset($_POST[$key])) {
    $cond = test_input($_POST[$key]);
    if (strlen($cond) > 0) {
      if ($numeric) {
        $arr[$key] = numeric_filt($cond, $errs);
      } else {
        if (strpos($cond, '%') === false) {
          $arr[$key] = '%' . $cond . '%';
        } else {
          $arr[$key] = $cond;
        }
      }
    }
  } else {
    $arr[$key] = '';
  }
  return;
}

$err_arr = array();
$filt_arr = array('recno'=>'', 'fileName'=>'', 'timeUpload'=>'',
 'stratum'=>'', 'email'=>'', 'startData'=>'', 'status'=>'',
 'lastInviteTime'=>'', 'lastInviteSurvey'=>'', 'lastSubmitTime'=>'',
 'lastSubmitSurvey'=>'', 'lastComplete'=>'');
$numeric_arr = array('recno'=>true, 'fileName'=>false, 'timeUpload'=>true,
 'stratum'=>false, 'email'=>false, 'startData'=>false, 'status'=>false,
 'lastInviteTime'=>true, 'lastInviteSurvey'=>false, 'lastSubmitTime'=>true,
 'lastSubmitSurvey'=>false, 'lastComplete'=>false);
 $whereArr = array();

if (array_key_exists('nano_dispmax', $_SESSION)) {
  $DISPLAY_MAX = $_SESSION['nano_dispmax'];
} else {
  $DISPLAY_MAX = 50;
}

if (array_key_exists('nano_mailid', $_SESSION)) {
  $mailID = $_SESSION['nano_mailid'];
}
if (array_key_exists('nano_mailname', $_SESSION)) {
  $mailName = $_SESSION['nano_mailname'];
}


$mailID = $formID = $testMode = $public = 0;
$mailName = $formName = '';
if ($_SERVER["REQUEST_METHOD"] == "GET") {
  if (isset($_GET['mailID'])) {
    $mailID = test_input($_GET['mailID']);
    $_SESSION['nano_mailid'] = $mailID;
    $db = new PDO("sqlite:data/nanoforms.sqlite");
    $sql = 'SELECT a.name, a.surveyName, b.public, b.testMode
    FROM inviteMails a
    INNER JOIN surveys b ON b.name = a.surveyName
    WHERE a.ID = :mid';
    $stm = $db->prepare($sql);
    $stm->bindparam(':mid', $mailID, PDO::PARAM_INT);
    $stm->execute();
    $res = $stm->fetch();
    $db = null;
    $mailName = $res['name'];
  } else  if (isset($_GET['formID'])) {
    $formID = test_input($_GET['formID']);
    $_SESSION['nano_formid'] = $formID;
    $db = new PDO("sqlite:data/nanoforms.sqlite");
    $sql = 'SELECT a.name, a.surveyName, b.public, b.testMode
    FROM forms a
    INNER JOIN surveys b ON b.name = a.surveyName
    WHERE a.ID = :fid';
    $stm = $db->prepare($sql);
    $stm->bindparam(':fid', $formID, PDO::PARAM_INT);
    $stm->execute();
    $res = $stm->fetch();
    $db = null;
    $formName = $res['name'];
  }
  $surveyName = $res['surveyName'];
  $public = $res['public'];
  $testMode = $res['testMode'];
  if ($public) {
    errHtml('Error: ' . $surveyName . 'is a public survey!');
    exit;
  }
} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
  if (isset($_POST['filters']) || isset($_POST['select'])) {
    storeFilt('recno', true, $filt_arr, $err_arr);
    storeFilt('fileName', false, $filt_arr, $err_arr);
    storeFilt('timeUpload', true, $filt_arr, $err_arr);
    storeFilt('stratum', false, $filt_arr, $err_arr);
    storeFilt('email', false, $filt_arr, $err_arr);
    storeFilt('startData', false, $filt_arr, $err_arr);
    storeFilt('status', false, $filt_arr, $err_arr);
    storeFilt('lastInviteTime', true, $filt_arr, $err_arr);
    storeFilt('lastInviteSurvey', false, $filt_arr, $err_arr);
    storeFilt('lastSubmitTime', true, $filt_arr, $err_arr);
    storeFilt('lastSubmitSurvey', false, $filt_arr, $err_arr);
    storeFilt('lastComplete', false, $filt_arr, $err_arr);
    if (count($err_arr) === 0) {
      foreach ($filt_arr as $key=>$val) {
        if ($val) {
          if ($numeric_arr[$key]) {
            $whereArr[] = $key . ' ' . $val;
          } else {
            $whereArr[] = $key . " LIKE '" . $val . "'";
          }
        }
      }
      if (isset($_POST['select'])) {
        $sql = 'UPDATE subs SET selected = 1';
        if (count($whereArr) > 0) {
          $sql .= ' WHERE ' . implode(' AND ', $whereArr);
        }
        $tempDB = new PDO("sqlite:" . $tmpFile);
        $stm = $tempDB->prepare($sql);
        $stm->execute();
        $tempDB = null;
        header('Location: mailing.php');
      }
    }
  } elseif (isset($_POST['submitMax'])) {
    $DISPLAY_MAX = test_input($_POST['changeMax']);
    $_SESSION['nano_dispmax'] = $DISPLAY_MAX;
  }
}  // POST

$sql = 'SELECT * FROM subs';
if (count($whereArr) > 0) {
  $sql .= ' WHERE ' . implode(' AND ', $whereArr);
}

if (count($err_arr) === 0) {
  $tempDB = new PDO("sqlite:" . $tmpFile);
  $stm = $tempDB->prepare($sql);
  $stm->execute();
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Recipients</title>
  <link rel="stylesheet" type="text/css" href="nanoforms.css" />
</head>
<body>

  <div class="topnav">
    <a href="subscribers.php">Subscribers</a>
    <a href="surveys.php">Surveys</a>
    <a href="survey.php"><?php echo $surveyName; ?></a>
    <a href="data.php">Data</a>
    <a href="logout.php">Log out</a>
  </div>

  <!-- Page content -->
  <div class="topmain">
    <p style="margin-bottom:2em">
      <span class="huge">Nanoforms</span> select recipients for
       <?php echo $mailID ? 'invitation email' : 'questionnaire form';?>
      <strong><?php echo $mailID ? $mailName : $formName; ?></strong> of survey
      <strong><?php echo $surveyName; ?></strong>.
    </p>

    <p class="warning big"><?php echo $public ? 'Warning: TEST mode!' : '';?></p>

    <div id="errors">
      <p class="alarm">
        <?php echo implode('<br />', $err_arr) ?>
      </p>
    </div>

    <p>You may select the mail recipients from the table below. For numeric
    columns use a comparison operator, like <span class="mono">&gt;20220425</span>
    for a date after april 25, 2022 or <span class="mono">=20220425</span> for
    exactly that day.<br />
    For the other (string) columns use a substring, like
    <span class="mono">yahoo</span> to select
    <span class="mono">mary@yahoo.com</span> and
    <span class="mono">john@yahoo.net</span>, where <span class="mono">yahoo</span> is
    shorthand for <span class="mono">%yahoo%</span>. You may use
    <span class="mono">mary%</span> or <span class="mono">%yahoo.com</span> to
    select strings beginning or ending with the substring.</p>

    <div id="showSubs">
      <form method="post" action="">
      <table class="wideTable">
        <caption>Columns marked with <sup>n</sup> are numeric, dates/times are UTC.</caption>
        <thead>
          <tr>
            <td></td>
            <th colspan="5">Origin</th>
            <th>Current</th>
            <th colspan="5">Last</th>
            <td></td>
            <td></td>
          </tr>
          <tr>
            <th><label for="recno">rec.<sup>n</sup></label><br />
            <input type="text" name="recno" id="recno"
            value="<?php echo $filt_arr['recno'];?>" />/th>
            <th><label for="fileName">file</label><br />
            <input type="text" name="fileName" id="fileName"
            value="<?php echo $filt_arr['fileName'];?>" /></th>
            <th><label for="timeUpload">uploaded<sup>n</sup></label><br />
            <input type="text" name="timeUpload" id="timeUpload"
            value="<?php echo $filt_arr['timeUpload'];?>" /></th>
            <th><label for="stratum">stratum</label><br />
            <input type="text" name="stratum" id="stratum"
            value="<?php echo $filt_arr['stratum'];?>" /></th>
            <th><label for="email">email</label><br />
            <input type="text" name="email" id="email"
            value="<?php echo $filt_arr['email'];?>" /></th>
            <th><label for="startData">start data</label><br />
            <input type="text" name="startData" id="startData"
            value="<?php echo $filt_arr['startData'];?>" /></th>
            <th><label for="status">status</label><br />
            <input type="text" name="status" id="status"
            value="<?php echo $filt_arr['status'];?>" /></th>
            <th><label for="lastInviteTime">invite<sup>n</sup></label><br />
            <input type="text" name="lastInviteTime" id="lastInviteTime"
            value="<?php echo $filt_arr['lastInviteTime'];?>" /></th>
            <th><label for="lastInviteSurvey">survey</label><br />
            <input type="text" name="lastInviteSurvey" id="lastInviteSurvey"
            value="<?php echo $filt_arr['lastInviteSurvey'];?>"/></th>
            <th><label for="lastSubmitTime">submit<sup>n</sup></label><br />
            <input type="text" name="lastSubmitTime" id="lastSubmitTime"
            value="<?php echo $filt_arr['lastSubmitTime'];?>" /></th>
            <th><label for="lastSubmitSurvey">survey</label><br />
            <input type="text" name="lastSubmitSurvey" id="lastSubmitSurvey"
            value="<?php echo $filt_arr['lastSubmitSurvey'];?>"  /></th>
            <th><label for="lastComplete">complete</label><br />
            <input type="text" name="lastComplete" id="lastComplete"
            value="<?php echo $filt_arr['lastComplete'];?>" /></th>
            <td><br /><button type="submit" name="filters">
            Apply<br />filters</button></td>
          </tr>
        </thead>
        <tbody>
          <?php
          $counter = 0;
          if (count($err_arr) === 0) {
            while (($res = $stm->fetch()) != false) {
              $counter++;
              if ($DISPLAY_MAX == -1 || $counter <= $DISPLAY_MAX) {
                echo '<tr>' . PHP_EOL . '<td class="right">' . $res['recno'] . '</td>' .
                PHP_EOL;
                foreach (array_slice(array_keys($filt_arr), 1) as $key) {
                  if ($numeric_arr[$key]) {
                    echo '<td class="right">';
                  } else {
                    echo '<td>';
                  }
                  echo $res[$key] . '</td>' . PHP_EOL;
                }
                echo '</tr>' . PHP_EOL;
              }
            }
          }
          $tempDB = null;
          ?>
        </tbody>
      </table>

      <p><?php echo $counter; ?> records selected,
        <?php echo ($counter < $DISPLAY_MAX) ? $counter : $DISPLAY_MAX; ?>
        displayed. <label for="changeMax">Change display max.</label>
        <input type="text" name="changeMax" id="changeMax" size="6"
        value="<?php echo $DISPLAY_MAX; ?>" />
        <input type="submit" name="submitMax" value="Apply" />
      </p>
      <p class="center">
        <?php
        if (count($err_arr) === 0) {
          echo '<input type="submit" name="select" class="big" ' .
          'value="Confirm selection"> <em>Have you applied your filters?</em>';
        }
        ?>
      </p>
    </form>

  </div>
</body>
</html>
