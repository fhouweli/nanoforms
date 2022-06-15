<?php
session_start();

require 'common.php';

if (!username()) {
  header('Location: index.php');
  exit;
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

$surveyName = $_SESSION['nano_surveyid'];
if (!$surveyName) {
  header('Location: surveys.php');
  exit;
}

$errAr = array();
$errMsg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $newline = test_input($_POST['crlf']);
  $colsep = trim($_POST['colsep']);
  if (strlen($colsep) != 1 && $colsep != '\t') {
    $errAr[] = 'Column separator must be a single character, or \t';
  }
  $dateFormat = test_input($_POST['datetime']);
  if (($dateAr = date_parse(date($dateFormat, strtotime('now')))) === false) {
    $errAr[] = "Invalid date/time format";
  } elseif ($dateAr['error_count'] != 0) {
    $errAr = array_merge($errAr, $dateAr['errors']);
  }
  $final = isset($_POST['final']) && $_POST['final'] == 1;
  $compl = isset($_POST['compl']) && $_POST['compl'] == 1;
  $test = isset($_POST['test']) && $_POST['test'] == 1;
  $wemail = isset($_POST['wemail']) && $_POST['wemail'] == 1;
  $start = isset($_POST['start']) && $_POST['start'] == 1;

  if ($newline == 'lf') {
    $EOL = "\n";
  } else {
    $EOL = "\r\n";
  }

  $errMsg = implode('<br />', $errAr);
  if (!$errMsg) {

    $andCond = '';
    if ($compl) {
      $andCond .= ' AND complete = 1';
    }
    if (!$test) {
      $andCond .= ' AND s.testing <> 1';
    }

    $db = new PDO("sqlite:data/nanoforms.sqlite");

    // first need to look at all data for the column names
    if ($start) {
      $sql = 'SELECT startData FROM submissions s
      INNER JOIN invitations i ON i.token = s.invitationToken
      INNER JOIN forms o ON o.ID = i.formID
      INNER JOIN subscribers r ON i.email = r.email
      WHERE surveyName = :surv' . $andCond;
      $statement = $db->prepare($sql);
      $statement->bindParam(':surv', $surveyName, PDO::PARAM_STR);
      $statement->execute();
      $startCols = array();
      while (($startData = $statement->fetchColumn()) !== false) {
        $startAr = explode(';', $startData);
        foreach ($startAr as $tuple) {
          $tuplAr = explode('=', $tuple);
          $vari = quotEscape($tuplAr[0], $colsep);
          if (!in_array($vari, $startCols)) {
            $startCols[] = $vari;
          }
        }
      }
    }

    $sql = 'SELECT formData FROM submissions s
    INNER JOIN invitations i ON i.token = s.invitationToken
    INNER JOIN forms o ON o.ID = i.formID
    WHERE surveyName = :surv' . $andCond;
    $statement = $db->prepare($sql);
    $statement->bindParam(':surv', $surveyName, PDO::PARAM_STR);
    $statement->execute();
    $formCols = array();
    while (($formData = $statement->fetchColumn()) !== false) {
      $formAr = explode(';', $formData);
      foreach ($formAr as $tuple) {
        $tuplAr = explode('=', $tuple);
        $vari = quotEscape($tuplAr[0], $colsep);
        if (!in_array($vari, $formCols)) {
          $formCols[] = $vari;
        }
      }
    }

    $headerAr = array();
    $headerAr[] = 'recno';
    if ($wemail) {
      $headerAr[] = 'email';
    }
    $headerAr = array_merge($headerAr, array('stratum', 'selfSubscribed'));
    if ($start) {
      $headerAr = array_merge($headerAr, $startCols);
    }
    $headerAr = array_merge($headerAr, array('globalStatus', 'inviteStatus',
    'formName', 'inviteTime', 'ipaddr'));
    $headerAr = array_merge($headerAr, $formCols);
    $headerAr = array_merge($headerAr, array('testing', 'complete', 'submitTime'));

    header("Content-type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"nanoforms-data.csv\"");

    $outstream = fopen("php://output", 'w');

    fwrite($outstream, implode($colsep, $headerAr) . $EOL);

    $sql = 'SELECT r.email, r.ROWID AS recno, r.stratum, selfSubscribed, startData,
    r.status AS globalStatus, i.status AS inviteStatus,
    o.name AS formName, i.time AS
    inviteTime, ipaddr, formData, s.testing,
    s.complete, s.time AS submitTime
    FROM submissions s';
    if ($final) {
      $sql .= ' INNER JOIN (SELECT email, MAX(time) AS maxTime FROM submissions
      GROUP BY email) x ON s.email = x.email and s.time = x.maxTime';
    }
    $sql .= ' INNER JOIN invitations i ON i.token = s.invitationToken
    INNER JOIN forms o ON i.formID = o.ID
    INNER JOIN subscribers r ON i.email = r.email
    WHERE surveyName = :surv' . $andCond . ' ORDER BY submitTime';

    $statement = $db->prepare($sql);
    $statement->bindParam(':surv', $surveyName, PDO::PARAM_STR);
    $statement->execute();
    $respAr = array();
    while (($res = $statement->fetch()) !== false) {
      foreach ($headerAr as $key) {
        $respAr[$key] = '';
      }
      $respAr['recno'] = $res['recno'];
      if ($wemail) {
        $respAr['email'] = quotEscape($res['email'], $colsep);
      }
      $respAr['stratum'] = quotEscape($res['stratum'], $colsep);
      $respAr['selfSubscribed'] = quotEscape($res['selfSubscribed'], $colsep);
      if ($start) {
        $startAr = explode(';', $res['startData']);
        foreach ($startAr as $tuple) {
          $tuplAr = explode('=', $tuple);
          $vari = quotEscape($tuplAr[0], $colsep);
          $valu = quotEscape($tuplAr[1], $colsep);
          $respAr[$vari] = $valu;
        }
      }
      foreach (array('globalStatus', 'inviteStatus', 'formName') as $key) {
        $respAr[$key] = $res[$key];
      }
      $respAr['inviteTime'] = quotEscape(date($dateFormat, $res['inviteTime']),
      $colsep);
      $respAr['ipaddr'] = quotEscape($res['ipaddr'], $colsep);
      $formAr = explode(';', $res['formData']);
      foreach ($formAr as $tuple) {
        $tuplAr = explode('=', $tuple);
        $vari = quotEscape($tuplAr[0], $colsep);
        $valu = quotEscape($tuplAr[1], $colsep);
        $respAr[$vari] = $valu;
      }
      foreach (array('testing', 'complete') as $key) {
          $respAr[$key] = $res[$key];
      }
      $respAr['submitTime'] = quotEscape(date($dateFormat, $res['submitTime']),
      $colsep);
      $outAr = array();
      foreach ($headerAr as $key) {
        $outAr[] = $respAr[$key];
      }
      fwrite($outstream, implode($colsep, $outAr) . $EOL);
    }
    fclose($outstream);
    $db = null;
    exit;
  }  // no error

}  // POST

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Data</title>
  <link rel="stylesheet" type="text/css" href="nanoforms.css" />
</head>
<body>

  <!-- Side navigation. From w3schools.com -->
  <div class="sidenav">
    <a href="subscribers.php">Subscribers</a>
    <a href="surveys.php">Surveys</a>
    <a href="survey.php"><?php echo $surveyName;?></a>
    <a href="logout.php">Log out</a>
  </div>

  <!-- Page content -->
  <div id="datapage" class="sidemain">
    <p style="margin-bottom:2em">
      <span class="huge">Nanoforms</span> data for survey
      <?php echo $surveyName;?></p>

      <p>Data are served as a .csv file attachment.</p>

    <p class="alarm"><?php echo $errMsg;?></p>

    <form method="post" action=""  onsubmit="oneSubmit();">
      <table>
        <tbody>
          <tr>
            <td class="right">line break:</label></td>
              <td><input type="radio" name="crlf" id="crlf" value="crlf" />
              <label for="crlf">CR LF (Windows)</label>
            </td>
            <td><input type="radio" name="crlf" id="lf" value="lf" checked />
              <label for="lf">LF (Linux, macOS)</label>
            </td>
          </tr>
          <tr>
            <td class="right"><label for="colsep">column separator:</label></td>
            <td colspan="2">
              <input type="text" name="colsep" id="colsep" size="1" value=";" />
            </td>
          </tr>
          <tr>
            <td class="right"><label for="datetime">date/time format:</label></td>
            <td colspan="2">
              <input type="text" name="datetime" id="datetime" value="Y-m-d H:i" />
            </td>
          </tr>
          <tr>
            <td class="right"><label for="final">final submits only:</label></td>
            <td colspan="2">
              <input type="checkbox" name="final" id="final" value="1" checked />
            </td>
          </tr>
          <tr>
            <td class="right"><label for="compl">completes only:</label></td>
            <td colspan="2">
              <input type="checkbox" name="compl" id="compl" value="1" checked />
            </td>
          </tr>
          <tr>
            <td class="right"><label for="test">include test data:</label></td>
            <td colspan="2">
              <input type="checkbox" name="test" id="test" value="1" />
            </td>
          </tr>
          <tr>
            <td class="right"><label for="wemail">include email:</label></td>
            <td colspan="2">
              <input type="checkbox" name="wemail" id="wemail" value="1" />
            </td>
          </tr>
          <tr>
            <td class="right"><label for="start">include start data:</label></td>
            <td colspan="2">
              <input type="checkbox" name="start" id="start" value="1" />
            </td>
          </tr>
          <tr>
            <td colspan="3" class="center">
              <input type="submit" name="downSubmit" id="downSubmit" value="GET DATA" />
            </td>
          </tr>
        </tbody>
      </table>
    </form>

<h3 id="confirm"></h3>

</div>

<script>
  function oneSubmit() {
    document.getElementById('downSubmit').disabled = true;
    document.getElementById('confirm').innerHTML = 'Data downloaded';
  }
</script>

</body>
</html>
