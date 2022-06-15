<?php
session_start();

require 'common.php';
require 'fieldList.php';

$MAX_FILE_SIZE = 500000;

if (!username()) {
  header('Location: index.php');
  exit;
}

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


$errMsg = '';
$formID = 0;
$formName = '';
$lorem = "Lorem ipsum dolor sit amet, consectetur adipiscing elit,
sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.";

$db = new PDO("sqlite:data/nanoforms.sqlite");
$sql = "SELECT COUNT(*) FROM surveys WHERE name = 'Fake'";
$statement = $db->prepare($sql);
$statement->execute();
$Fake_present = ($statement->fetchColumn() != 0);
$db = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $surveyName = test_input($_POST['survey']);
  $samplesize = (int) test_input($_POST['samplesize']);
  if (!$surveyName || !$samplesize) {
    exit;
  }

  header('Content-type: text/plain' . "\r\n");

  $csvName = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR .
  '500_fake_subscribers.csv';
  if (($h = fopen($csvName, "r")) === false) {
    exit("Could not open $csvName");
  }
  $header = strtolower(fgets($h));
  $headerAr = str_getcsv($header, ';');
  $nCols = count($headerAr);
  $emailNdx = array_search('email', $headerAr);
  $stratumNdx = array_search('stratum', $headerAr);

  $db = new PDO("sqlite:data/nanoforms.sqlite");

  $sql = 'SELECT public FROM surveys WHERE name = :surv';
  $statement = $db->prepare($sql);
  $statement->bindParam(':surv', $surveyName, PDO::PARAM_STR);
  $statement->execute();
  $public = $statement->fetchColumn();

  // Deletes independent of public status (may have been by invitation before)
  $sql = "SELECT fileID FROM subscriberFiles WHERE fileName = 'Fake_subscribers'";
  $statement = $db->prepare($sql);
  $statement->execute();
  if (($fileID = $statement->fetchColumn()) !== false) {
    $sql = "DELETE FROM subscribers WHERE fileID = :fid";
    $statement = $db->prepare($sql);
    $statement->bindParam(':fid', $fileID, PDO::PARAM_INT);
    $statement->execute();
    $sql = "DELETE FROM subscriberFiles WHERE fileID = :fid";
    $statement = $db->prepare($sql);
    $statement->bindParam(':fid', $fileID, PDO::PARAM_INT);
    $statement->execute();
  }
  if (!$public) {
    $thisMoment = strtotime('now');
    $sql = "INSERT INTO subscriberFiles (fileName, timeupload) values
    ('Fake_subscribers', :stamp)";
    $statement = $db->prepare($sql);
    $statement->bindParam(':stamp', $thisMoment, PDO::PARAM_INT);
    $statement->execute();
    $fileID = $db->lastInsertId();
  } else {
    $fileID = 0;
  }

  if ($Fake_present) {
    $sql = "SELECT ID FROM forms WHERE surveyName = 'Fake'";
    $statement = $db->prepare($sql);
    $statement->execute();
    while (($formID = $statement->fetchColumn()) !== false) {
      $sql = 'DELETE FROM submissions WHERE invitationToken IN
      (SELECT token FROM invitations WHERE formID = :fid)';
      $stm = $db->prepare($sql);
      $stm->bindParam(':fid', $formID, PDO::PARAM_INT);
      $stm->execute();
      $sql = 'DELETE FROM invitations WHERE formID = :fid';
      $stm = $db->prepare($sql);
      $stm->bindParam(':fid', $formID, PDO::PARAM_INT);
      $stm->execute();
      $sql = 'DELETE FROM forms WHERE ID = :fid';
      $stm = $db->prepare($sql);
      $stm->bindParam(':fid', $formID, PDO::PARAM_INT);
      $stm->execute();
    }
    $sql = "UPDATE surveys SET public = :pub WHERE name = 'Fake'";
  } else {
    $sql = "INSERT INTO surveys (name, title, public, linkValidity, testMode,
    active) VALUES ('Fake', 'A fake survey for training purposes', :pub,
      '2 days', 0, 1)";
  }
  $statement = $db->prepare($sql);
  $statement->bindParam(':pub', $public, PDO::PARAM_INT);
  $statement->execute();

  $sql = 'SELECT COUNT(*) FROM forms WHERE surveyName = :surv';
  $statement = $db->prepare($sql);
  $statement->bindParam(':surv', $surveyName, PDO::PARAM_STR);
  $statement->execute();
  $nForms = $statement->fetchColumn();

  $sql = 'SELECT name, html, completeDefinition FROM forms
  WHERE surveyName = :surv';
  $statement = $db->prepare($sql);
  $statement->bindParam(':surv', $surveyName, PDO::PARAM_STR);
  $statement->execute();

  echo 'Generating ' . $samplesize . ' fake records for survey ' . $surveyName .
  '. ' . date('Y-m-d H:i:s', strtotime('now')) . "\r\n";

  // outer loop: forms
  while (($res = $statement->fetch()) != false) {
    $formName = $res['name'];
    echo '------ form: ' . $formName . ' -------' . "\r\n";
    $html = $res['html'];
    $completeDefinition = $res['completeDefinition'];
    $thisMoment = strtotime('now');
    $sql = "INSERT INTO forms (name, surveyName, html, timeUpload, completeDefinition)
    VALUES (:name, 'Fake', :htm, :tim, :def)";
    $stm = $db->prepare($sql);
    $stm->bindParam(':name', $formName, PDO::PARAM_STR);
    $stm->bindParam(':htm', $html, PDO::PARAM_STR);
    $stm->bindParam(':tim', $thisMoment, PDO::PARAM_INT);
    $stm->bindParam(':def', $completeDefinition, PDO::PARAM_STR);
    $stm->execute();
    $formID = $db->lastInsertId();
    $fieldArr = fieldList($html);
    $formSamplesize = (int) round($samplesize / $nForms);

    // inner loop: subscribers
    $records = 0;
    while (($lineAr = fgetcsv($h, 0, ';')) !== false && $records < $formSamplesize) {
      $records++;
      $email = $lineAr[$emailNdx];
      $stratum = '';
      if ($stratumNdx !== false) {
        $stratum = $lineAr[$stratumNdx];
      }
      $extraAr = array();
      for ($i = 0; $i < min(count($lineAr), $nCols); $i++) {
        if ($i != $emailNdx && $i != $stratumNdx) {
          $extraAr[] = escapeEq($headerAr[$i]) . '=' .
          escapeEq($lineAr[$i]);
        }
      }
      $startData = implode(';', $extraAr);
      $sql = "INSERT OR IGNORE INTO subscribers (email, stratum, fileID,
      selfSubscribed, startData, status) VALUES
      (:mail, :strat, :fid, :sub, :dat, 'fake')";
      $stm = $db->prepare($sql);
      $stm->bindParam(':mail', $email, PDO::PARAM_STR);
      $stm->bindParam(':strat', $stratum, PDO::PARAM_STR);
      $stm->bindParam(':fid', $fileID, PDO::PARAM_INT);
      $stm->bindParam(':sub', $public, PDO::PARAM_INT);
      $stm->bindParam(':dat', $startData, PDO::PARAM_STR);
      $stm->execute();
      $token = openssl_random_pseudo_bytes(16);
      $token = bin2hex($token);
      // assume 95% correct email
      if (mt_rand(1, 100) <= 95) {
        $status = 'mailed';
      } else {
        $status = 'refused';
      }
      $thisMoment = strtotime('now');
      $expires = strtotime('now + 3 days');
      $sql = "INSERT INTO invitations (token, email, inviteMailID,
      stratum, status, formID, privacyStatementID, expires, testing, time)
      VALUES (:tok, :mail, 0, :strat, :stat, :form, 0, :xpir, 0, :tim)";
      $stm = $db->prepare($sql);
      $stm->bindParam(':tok', $token, PDO::PARAM_STR);
      $stm->bindParam(':mail', $email, PDO::PARAM_STR);
      $stm->bindParam(':strat', $stratum, PDO::PARAM_STR);
      $stm->bindParam(':stat', $status, PDO::PARAM_STR);
      $stm->bindParam(':form', $formID, PDO::PARAM_INT);
      $stm->bindParam(':xpir', $expires, PDO::PARAM_INT);
      $stm->bindParam(':tim', $thisMoment, PDO::PARAM_INT);
      $stm->execute();
      // assume 95% response rate
      if ($status == 'mailed' && mt_rand(1, 100) <= 95) {
        // allow for double and triple submissions
        $nSubs = 1;
        if (($rand = mt_rand(1, 100)) >= 90) {
          $nSubs = 3;
        } elseif ($rand >= 75) {
          $nSubs = 2;
        }
        for ($i = 1; $i <= $nSubs; $i++) {
          $ipAddr = '127.0.0.' . $i;
          $postArr = array();
          $ansArr = array();
          foreach ($fieldArr as $field) {
            // assume 95% completion rate
            if (mt_rand(1, 100) <= 95 || $field['inputType'] == 'submit') {
              $ans = '';
              if (($choices = count($field['values'])) > 1) {
                $ans = $field['values'][mt_rand(0, $choices - 1)];
              } elseif ($choices == 1) {
                if ($field['element'] == 'input' &&
                $field['inputType'] == 'checkbox') {
                  // assume one third of checkboxes checked
                  if (mt_rand(1, 100) <= 33) {
                    $ans = $field['values'][0];
                  }
                } else {  // like submits
                  $ans = $field['values'][0];
                }
              } else {
                if ($field['element'] == 'textarea') {
                  $ans = $lorem;
                } else {
                  // assume half of text inputs answered
                  if (mt_rand(1, 100) <= 50) {
                    $ans = substr($lorem, 0, mt_rand(5, 30));
                  }
                }
              }
              if ($ans != '') {
                $postArr[] = escapeEq($field['name']) . '=' . escapeEq($ans);
                $ansArr[$field['name']] = $ans;
              }
            }
            $postData = implode(';', $postArr);
          }
          if ($completeDefinition) {
            $substed = substCond($completeDefinition, $ansArr);
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
          $thisMoment = strtotime('now') + $i;
          $sql = 'INSERT INTO submissions (invitationToken, email, ipaddr, stratum,
          formData, testing, complete, time)
          VALUES (:tok, :mail, :ip, :strat, :data, 0, :compl, :tim)';
          $stm = $db->prepare($sql);
          $stm->bindparam(':tok', $token, PDO::PARAM_STR);
          $stm->bindparam(':mail', $email, PDO::PARAM_STR);
          $stm->bindparam(':ip', $ipAddr, PDO::PARAM_STR);
          $stm->bindparam(':strat', $stratum, PDO::PARAM_STR);
          $stm->bindparam(':data', $postData, PDO::PARAM_STR);
          $stm->bindparam(':compl', $complete, PDO::PARAM_INT);
          $stm->bindparam(':tim', $thisMoment, PDO::PARAM_INT);
          $stm->execute();
        } // for 1,2 3 submissions
      }  // submission
      if ($records % 50 == 0) {
        echo $records . "\r\n";
      }
    }  // inner loop subscribers
  }  // outer loop forms

  fclose($h);
  $db = null;
  $_SESSION['nano_surveyid'] = 'Fake';
  echo 'Finished. ' . date('Y-m-d H:i:s', strtotime('now')) . "\r\n";
  exit;

}  // POST

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Fake data</title>
  <link rel="stylesheet" type="text/css" href="nanoforms.css" />
</head>
<body>

  <!-- Side navigation. From w3schools.com -->
  <div class="sidenav">
    <a href="subscribers.php">Subscribers</a>
    <a href="surveys.php">Surveys</a>
    <?php
    if ($formName != '') {
      echo '<a href="data.php">Data</a>' . PHP_EOL;
    }
     ?>
    <a href="logout.php">Log out</a>
  </div>

  <!-- Page content -->
  <div class="sidemain">
    <p style="margin-bottom:2em">
      <span class="huge">Nanoforms</span> survey: Fake</p>

    <h2>Fake</h2>

    <div id="loader" style="display:none"></div>

    <p class="warning"><?php echo $Fake_present ? 'Warning: survey Fake exists' : ''; ?></p>

    <form method="post" action=""  onsubmit="showLoader();">
      <table>
        <tbody>
          <tr>
            <td><label for="survey">Survey to copy:</label></td>
            <td>
              <select name="survey" id="survey">
  <?php
  $db = new PDO("sqlite:data/nanoforms.sqlite");
  $sql = "SELECT name, title, public, rowid FROM surveys
  WHERE name <> 'Fake' AND name IN (SELECT surveyName FROM forms)
  ORDER BY rowid DESC";
  $statement = $db->prepare($sql);
  $statement->execute();
  $html = "";
  while (($row = $statement->fetch()) != false) {
    $html .= '<option value="' . $row['name'] . '">' .
    $row['name'] . ' - ' . $row['title'] . ' (' .
    ($row['public'] == 1 ? 'public' : 'invitation') .
    ')</option>' . "\r\n";
  }
  $db = null;
  echo $html;
  ?>
              </select>
            </td>
          </tr>
          <tr>
            <td>
              <label for="samplesize">Sample size:</label>
            </td>
            <td>
              <select name="samplesize" id="samplesize">
                <option value="20">20</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="200">200</option>
                <option value="500">500</option>
              </select>
            </td>
          </tr>
          <tr>
            <td colspan="2">
              <input type="submit" name="dofake" id="dofake" value="Generate fakes" />
            </td>
          </tr>
        </tbody>
      </table>
    </form>

  </div>

  <script>
  function showLoader() {
    document.getElementById("loader").style.display = "block";
    document.getElementById('dofake').disabled=true;
  }
</script>

</body>
</html>
