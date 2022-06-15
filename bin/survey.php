<?php
session_start();

require 'common.php';

if (!username()) {
  header('Location: index.php');
  exit;
}

$surveyName = $errMsg = "";
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['name'])) {
  $surveyName = test_input($_GET['name']);
  $_SESSION['nano_surveyid'] = $surveyName;
} elseif (isset($_SESSION['nano_surveyid'])) {
  $surveyName = test_input($_SESSION['nano_surveyid']);
}
if (!$surveyName) {
  header('Location: surveys.php');
  exit;
}

// New survey: unset survey-specific session data (formid, mailid ...)
foreach (array_keys($_SESSION) as $key) {
  if ($key != 'nano_isauth' && $key != 'nano_surveyid') {
    unset($_SESSION[$key]);
  }
}

$errMsg = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $db = new PDO("sqlite:data/nanoforms.sqlite");
  $sql = 'SELECT public, active, allowRevisit, testMode, linkValidity FROM surveys
  WHERE name = :name';
  $statement = $db->prepare($sql);
  $statement->bindParam(':name', $surveyName, PDO::PARAM_STR);
  $statement->execute();
  if (($res = $statement->fetch(PDO::FETCH_ASSOC)) === false) {
    $errMsg = 'Survey not found';
    $db = null;
    echo $errMsg;
    exit;
  }
  if (isset($_POST['public'])) {
    $public = test_input($_POST['public']);
  } else {
    $public = $res['public'];
  }
  if (isset($_POST['status'])) {
    $active = test_input($_POST['status']) != 3;
    $testMode = test_input($_POST['status']) == 1;
  } else {
    $active = $res['active'];
    $testMode = $res['testMode'];
  }
  if (isset($_POST['revis'])) {
    $revis = test_input($_POST['revis']);
  } else {
    $revis = $res['allowRevisit'];
  }
  if (isset($_POST['validity'])) {
    $validity = test_input($_POST['validity']);
    if (strtotime('now + ' . $validity) === false) {
      $errMsg = 'Please submit a valid validity value';  // sic!
      $validity = $res['linkValidity'];
    }
  } else {
    $validity = $res['linkValidity'];
  }
  $sql = 'UPDATE surveys SET public = :pub, active = :act, testMode = :mod,
  linkValidity = :val, allowRevisit = :revi WHERE name = :name';
  $statement = $db->prepare($sql);
  $statement->bindParam(':pub', $public, PDO::PARAM_INT);
  $statement->bindParam(':act', $active, PDO::PARAM_INT);
  $statement->bindParam(':mod', $testMode, PDO::PARAM_INT);
  $statement->bindParam(':name', $surveyName, PDO::PARAM_STR);
  $statement->bindParam(':val', $validity, PDO::PARAM_STR);
  $statement->bindParam(':revi', $revis, PDO::PARAM_INT);
  $statement->execute();
  $db = null;
}

$db = new PDO("sqlite:data/nanoforms.sqlite");

$sql = 'SELECT title, public, active, testMode, allowRevisit, linkValidity
FROM surveys
WHERE name = :name';
$statement = $db->prepare($sql);
$statement->bindParam(':name', $surveyName, PDO::PARAM_STR);
$statement->execute();
if (($res = $statement->fetch(PDO::FETCH_ASSOC)) === false) {
  $errMsg = 'Survey not found';
  $db = null;
  echo $errMsg;
  exit;
}
$title = $res['title'];
$public = $res['public'];
$active = $res['active'];
$testMode = $res['testMode'];
$revis = $res['allowRevisit'];
$linkValidity = $res['linkValidity'];

$formName = '';
$sql = "SELECT name, timeUpload FROM forms
WHERE surveyName = :survey ORDER BY timeUpload DESC LIMIT 1";
$statement = $db->prepare($sql);
$statement->bindparam(':survey', $surveyName, PDO::PARAM_STR);
$statement->execute();
if (($res = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
  $formName = $res['name'];
}

$statName = '';
$sql = "SELECT name, timeUpload FROM privacyStatements
WHERE surveyName = :survey ORDER BY timeUpload DESC LIMIT 1";
$statement = $db->prepare($sql);
$statement->bindparam(':survey', $surveyName, PDO::PARAM_STR);
$statement->execute();
if (($res = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
  $statName = $res['name'];
}

$mailName = '';
$sql = "SELECT name, timeUpload FROM inviteMails
WHERE surveyName = :survey ORDER BY timeUpload DESC LIMIT 1";
$statement = $db->prepare($sql);
$statement->bindparam(':survey', $surveyName, PDO::PARAM_STR);
$statement->execute();
if (($res = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
  $mailName = $res['name'];
}


$sql = 'SELECT COUNT(*), a.status, a.formID, c.name, d.stratum
FROM invitations a
INNER JOIN (SELECT email, MAX(time) AS maxTime FROM invitations
GROUP BY email) b ON a.email = b.email and a.time = b.maxTime
INNER JOIN forms c ON a.formID = c.ID
INNER JOIN subscribers d ON a.email = d.email
WHERE c.surveyName = :surv AND a.testing <> 1
GROUP BY d.stratum, a.formID, a.status';

$statement = $db->prepare($sql);
$statement->bindparam(':surv', $surveyName, PDO::PARAM_STR);
$statement->execute();
$invites = array();
$forms = array();
$statuses = array();
$strata = array();
while (($res = $statement->fetch()) !== false) {
  if (!in_array($res['stratum'], $strata)) {
    $strata[] = $res['stratum'];
  }
  if (!in_array($res['name'], $forms)) {
    $forms[] = $res['name'];
  }
  if (!in_array($res['status'], $statuses)) {
    $statuses[] = $res['status'];
  }
  $invites[$res['stratum']][$res['name']][$res['status']] = $res[0];
}

$submits = array();
$sql = 'SELECT COUNT(*), SUM(a.complete), d.name, e.stratum FROM submissions a
INNER JOIN (SELECT email, MAX(time) AS maxTime FROM submissions
WHERE testing <> 1 GROUP BY email) b
ON a.email = b.email AND a.time = b.maxTime
INNER JOIN invitations c ON a.invitationToken = c.token
INNER JOIN forms d ON c.formID = d.ID
INNER JOIN subscribers e ON a.email = e.email
WHERE d.surveyName = :surv
GROUP BY e.stratum, d.name';
$statement = $db->prepare($sql);
$statement->bindparam(':surv', $surveyName, PDO::PARAM_STR);
$statement->execute();
$submits = array();
while (($res = $statement->fetch()) !== false) {
  if (!in_array($res['stratum'], $strata)) {  // should not happen
    $strata[] = $res['stratum'];
  }
  if (!in_array($res['name'], $forms)) {     // should not happen
    $forms[] = $res['name'];
  }
  $submits[$res['stratum']][$res['name']]['total'] = $res[0];
  $submits[$res['stratum']][$res['name']]['completes'] = $res[1];
}


$db = null;

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?php echo $surveyName; ?></title>
  <link rel="stylesheet" type="text/css" href="nanoforms.css" />
</head>
<body>

  <!-- Side navigation. From w3schools.com -->
  <div class="sidenav">
    <a href="subscribers.php">Subscribers</a>
    <a href="surveys.php">Surveys</a>
    <a href="data.php">Data</a>
    <a href="logout.php">Log out</a>
  </div>

  <!-- Page content -->
  <div class="sidemain">
    <p style="margin-bottom:2em">
      <span class="huge">Nanoforms</span> survey</p>

    <h2 style="display:inline"><?php echo $surveyName; ?> - <?php echo $title; ?></h2>
    <form method="post" action="deleteSurvey.php" style="display:inline">
        <input type="submit" name="deleteSurvey" id="deleteSurvey"
        value="Delete this survey" />
      </form>



    <p class="alarm"><?php echo $errMsg;?></p>


    <div id="settings">
      <form method="post" action="" onsubmit="confirm()">
        <table>
          <tbody>
            <tr>
              <td class="right"><label for="public">Survey mode:</label></td>
              <td>
                <select name="public" id="public" onchange="toggleMail()">
                  <option value="0"
                  <?php echo $public ? '' : ' selected';?>>by invitation</option>
                  <option value="1"
                  <?php echo $public ? ' selected' : '';?>>public</option>
                </select>
              </td>
            </tr>
            <tr>
              <td class="right"><a href="forms.php">Questionnaire form</a>:</td>
              <td class="big">
                <?php echo $formName == '' ? '&#10007;' : '&#10003;';?>
              </td>
            </tr>
            <tr id="privrow">
              <td class="right"><a href="privacy.php">Privacy statement</a>:</td>
              <td class="big">
                <?php echo $statName == '' ? '&#10007;' : '&#10003;';?>
              </td>
            </tr>
            <tr id="mailrow">
              <td class="right"><a href="mails.php">Invite email</a>:</td>
              <td class="big">
                <?php echo $mailName == '' ? '&#10007;' : '&#10003;';?>
              </td>
            </tr>
            <tr>
              <td class="right"><label for="validity">Validity of links:</label></td>
              <td><input type="text" name="validity" id="validity"
              size="5" value="<?php echo $linkValidity;?>" /></td>
            <tr>
              <td class="right">
                <label for="revis">Respondent may revisit after submit:</label>
              </td>
              <td>
                <select name="revis" id="revis">
                  <option value="1">yes</option>
                  <option value="2">if not complete</option>
                  <option value="3">read only</option>
                  <option value="4">no</option>
                </select>
              </td>
            </tr>
            <tr>
              <td class="right"><label for="status">Status:</label></td>
              <td>
                <select name="status" id="status">
                  <option value="1"
                  <?php echo ($active && $testMode) ? ' selected' : '';?>>test mode</option>
                  <option value="2"
                  <?php echo ($active && !$testMode) ? ' selected' : '';?>>live</option>
                  <option value="3"
                  <?php echo !$active ? ' selected' : '';?>>halted</option>
                </select>
              </td>
            </tr>
            <tr>
              <td></td>
              <td class="big">
                <input type="submit" name="subsurv" id="subsurv" value="Submit changes" />
              </td>
            </tr>
          </tbody>
        </table>
      </form>
    </div>

    <!-- here dashboard with status, counts ? -->
    <div id="overview">

<table class="spacedout">
  <caption><?php echo date('Y-m-d H:i', strtotime('now')) . ' UTC';?>
  </caption
  <thead>
    <tr>
      <th>stratum</th>
      <th>form</th>
      <th>involved</th>
      <?php
      foreach ($statuses as $stat) {
        echo '<th>' . $stat . '</th>' . PHP_EOL;
      }
      ?>
      <th>submitted</th>
      <th>completes</th>
      <th>incompletes</th>
    </tr>
  </thead>
  <tbody>
    <?php
    $totAr = array();
    $totAr['subscribers'] = 0;
    foreach ($statuses as $stat) {
      $totAr[$stat] = 0;
    }
    $totAr['submitted'] = 0;
    $totAr['completes'] = 0;
    $totAr['incompletes'] = 0;

    foreach ($strata as $strat) {
      foreach ($forms as $form) {
        $row = '<tr><td>' . $strat . '</td><td>' . $form . '</td>';
        $subscribers = 0;
        $statRow = '';
        foreach ($statuses as $stat) {
          if (isset($invites[$strat][$form][$stat])) {
            $subscribers += $invites[$strat][$form][$stat];
            $statRow .= '<td class="right">' . $invites[$strat][$form][$stat] . '</td>';
            $totAr[$stat] += $invites[$strat][$form][$stat];
          } else {
            $statRow .= '<td class="right">0</td>';
          }
        }
        $row .= '<td class="right">' . $subscribers . '</td>' . $statRow;
        $totAr['subscribers'] += $subscribers;
        if (isset($submits[$strat][$form]['total'])) {
          $total = $submits[$strat][$form]['total'];
        } else {
          $total = 0;
        }
        $row .= '<td class="right">' . $total . '</td>';
        $totAr['submitted'] += $total;
        if (isset($submits[$strat][$form]['completes'])) {
          $completes = $submits[$strat][$form]['completes'];
        } else {
          $completes = 0;
        }
        $row .= '<td class="right">' . $completes . '</td>';
        $totAr['completes'] += $completes;
        $incompletes = $total - $completes;
        $row .= '<td class="right">' . $incompletes . '</td>';
        $totAr['incompletes'] += $incompletes;
        $row .= '</tr>' . PHP_EOL;
        echo $row;
      }
    }
    $row = '<tr><td colspan="2"></td>';
    $row .= '<td class="right totalrow">' . $totAr['subscribers'] . '</td>';
    foreach ($statuses as $stat) {
      $row .= '<td class="right totalrow">' . $totAr[$stat] . '</td>';
    }
    $row .= '<td class="right totalrow">' . $totAr['submitted'] . '</td>';
    $row .= '<td class="right totalrow">' . $totAr['completes'] . '</td>';
    $row .= '<td class="right totalrow">' . $totAr['incompletes'] . '</td>';
    $row .= '</tr>' . PHP_EOL;
    echo $row;
   ?>
 </tbody>
</table>

    </div>

  </div>

<script>
function confirm() {
  document.getElementById('subsurv').value = "OK!";
  setTimeout(function(){
    document.getElementById("subsurv").value = "Submit changes";
  },3000);
}

function toggleMail() {
  if (document.getElementById('public').value == "0") {
    document.getElementById('privrow').style.display = "none";
    document.getElementById('mailrow').style.display = "";
  } else {
    document.getElementById('mailrow').style.display = "none";
    document.getElementById('privrow').style.display = "";
  }
}
window.onload = toggleMail();
</script>

</body>
</html>
