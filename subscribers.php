<?php
session_start();

require 'common.php';

$MAX_FILE_SIZE = 500000;

if (!username()) {
  header('Location: index.php');
  exit;
}

function escapeEq($txt) {
  return str_replace(array('=', ';'), array(':', ','), $txt);
}

$surveyName = '';
if (isset($_SESSION['nano_surveyid'])) {
  $surveyName = test_input($_SESSION['nano_surveyid']);
}

$errMsg = '';
$warnAr = array();
$sumAr = array();
$records = 0;
$duplicates = 0;
$empties = 0;
$report = '';

$removeArr = array('"', "'", '@', '^', '.', ',', ';');
$fname = str_replace($removeArr, '', username() . '_' . $surveyName);
$tmpdir = __DIR__ . DIRECTORY_SEPARATOR;
$tmpfname = $tmpdir . 'tmp' . DIRECTORY_SEPARATOR . $fname . '.csv';
$tmpSubs = 'tmp/' . $fname . '.csv';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  if (isset($_POST['uploadSubmit']) && isset($_FILES['subsname'])) {
    $file = $_FILES['subsname'];
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
      $errMsg .= "Upload error " . $file['error'];
    } elseif ($file['error'] === UPLOAD_ERR_OK) {
      $fileName = basename(test_input($file['name']));
      if ($file['size'] > $MAX_FILE_SIZE) {
        $errMsg = "File size limit of " . $MAX_FILE_SIZE . "exceeded ";
      } elseif (strpos($file['type'], 'text/') !== 0) {
        $errMsg .= "Wrong filetype ";
      }
    }
    $substitute = $_POST['substitute'];
    if (!$errMsg) {
      move_uploaded_file($file['tmp_name'], $tmpfname);
      if (($h = fopen($tmpfname, "r")) === false) {
        $errMsg = "Could not open $fileName";
      }
      if (!$errMsg) {
        $line = fgets($h);  // header
        $nCols = 0;
        $sepChar = "";
        foreach (array(',', ';', "\t") as $sep) {
          if (($k = count(str_getcsv($line, $sep))) > $nCols) {
            $sepChar = $sep;
            $nCols = $k;
          }
        }
        if (!$sepChar) {
          $errMsg = $fileName . ": no valid separator (comma, semicolon or " .
          ' tab) found';
        }
        if (!$errMsg) {
          $headerAr = str_getcsv($line, $sepChar);
          $lineAr = str_getcsv(strtolower($line), $sepChar);
          if (($emailNdx = array_search('email', $lineAr)) === false) {
            $errMsg = $fileName . ": column 'email' not found";
          } else {
            $stratumNdx = array_search('stratum', $lineAr);
          }
        }
        if (!$errMsg) {
          $db = new PDO("sqlite:data/nanoforms.sqlite");
          $sql = 'INSERT INTO subscriberFiles (fileName, timeUpload) ' .
          'VALUES (:fil, :ymd)';
          $statement = $db->prepare($sql);
          $statement->bindparam(':fil', $fileName, PDO::PARAM_STR);
          $statement->bindparam(':ymd', strtotime("now"), PDO::PARAM_INT);
          try {
            $statement->execute();
          } catch (PDOException $e) {
            $errMsg = $e->getMessage();
          }
          if (!$errMsg) {
            $fileID = $db->lastInsertId();

            while (($lineAr = fgetcsv($h, 0, $sepChar)) !== false) {
              $records++;
              if (count($lineAr) > $nCols) {
                if (!$warnMsg) {
                  $warnAr[] = 'Extra columns in record ' . str($records);
                }
              }
              $email = $lineAr[$emailNdx];
              if (!$email) {
                $empties++;
                $warnAr[] = 'Email missing in record ' . $records;
                continue;
              }
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
              $sql = 'SELECT stratum, startData FROM subscribers
              WHERE email = :email';
              $statement = $db->prepare($sql);
              $statement->bindparam(':email', $email, PDO::PARAM_STR);
              $statement->execute();
              $res = $statement->fetch();
              if ($res !== false) {
                $warnAr[] = 'Duplicate email in record ' . $records;
                $duplicates++;
                if (!$substitute == 0) continue;
                if ($substitute == 2) {
                  $currAr = array();
                  $cAr = explode(';', $res['startData']);
                  foreach ($cAr as $tupl) {
                    $tupAr = explode('=', $tupl);
                    $currAr[$tupAr[0]] = $tupAr[1];
                  }
                  $oldAr = array();
                  foreach ($extraAr as $tupl) {
                    $tupAr = explode('=', $tupl);
                    $oldAr[$tupAr[0]] = $tupAr[1];
                  }
                  $newAr = array_merge($oldAr, $currAr);
                  $keys = array_keys($newAr);
                  $newStart = array();
                  for ($i = 0; $i < count($keys); $i++) {
                    $newStart[] = $keys[$i] . '=' . $newAr[$keys[$i]];
                  }
                  $startData =  implode(';', $newStart);
                }
                $sql = "UPDATE subscribers SET stratum = :strat, fileID = :fil,
                startData = :start, selfSubscribed = 0 WHERE email = :mail";
              } else {
                $sql = "INSERT INTO subscribers (email, stratum, fileID,
                  startData, selfSubscribed) VALUES
                  (:mail, :strat, :fil, :start, 0)";
              }
              $statement = $db->prepare($sql);
              $statement->bindparam(':mail', $email, PDO::PARAM_STR);
              $statement->bindparam(':strat', $stratum, PDO::PARAM_STR);
              $statement->bindparam(':fil', $fileID, PDO::PARAM_INT);
              $statement->bindparam(':start', $startData, PDO::PARAM_STR);
              try {
                $statement->execute();
              } catch (PDOException $e) {
                $warnAr[] = 'Error at record ' . $records . ': ' .
                $e->getMessage();
              }
            } // while
            $report = $fileName . ' uploaded: ' . $records . ' records, ' .
            $empties . ' without email, ' . $duplicates . ' duplicates ';
            if ($substitute == 2) {
              $report .= 'updated.';
            } elseif ($substitute == 1) {
              $report .= 'substituted.';
            } else {
              $report .= 'discarded.';
            }
            // remove subs database, if exists (see recipients.php)
            if (array_key_exists('nano_tempdb', $_SESSION)) {
              $fname = $_SESSION['nano_tempdb'];
              if (file_exists($tmpdir . DIRECTORY_SEPARATOR . $fname . '.sqlite')) {
                unlink($tmpdir . DIRECTORY_SEPARATOR . $fname . '.sqlite');
              }
            }

          } //  no error subscriberFiles
        } // no error missing email
        $db = null;
      } // no error fopen
      fclose($h);
    } // no error upload
  }  // isset
} // post request

$db = new PDO("sqlite:data/nanoforms.sqlite");
$sql = "SELECT fileID, fileName, timeUpload FROM subscriberFiles";
$statement = $db->prepare($sql);
$statement->execute();
$sumAr = array();
$sql = "SELECT COUNT(*) FROM subscribers WHERE fileID = NULL";
$stm2 = $db->prepare($sql);
$stm2->execute();
$res2 = $stm2->fetchColumn();
$recAr = array('', '(no file)', '', $res2);
$sumAr[] = $recAr;
while (($res = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
  $recAr = array($res['fileID'], $res['fileName'],
  date("Y-m-d H:i", $res['timeUpload']));
  $sql = "SELECT COUNT(*) FROM subscribers WHERE fileID = :file";
  $stm2 = $db->prepare($sql);
  $stm2->bindparam(':file', $res['fileID'], PDO::PARAM_INT);
  $stm2->execute();
  $res2 = $stm2->fetchColumn();
  $recAr[] = $res2;
  $sumAr[] = $recAr;
}
$db = null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Subscriber files</title>
  <link rel="stylesheet" type="text/css" href="nanoforms.css" />
</head>
<body>

  <!-- Side navigation. From w3schools.com -->
  <div class="sidenav">
    <a href="surveys.php">Surveys</a>
    <?php
    if ($surveyName) {
      echo '<a href="survey.php">' . $surveyName . '</a>' . PHP_EOL;
      echo '<a href="data.php">Data</a>' . PHP_EOL;
    }
    ?>
    <a href="logout.php">Log out</a>
  </div>

  <!-- Page content -->
  <div class="sidemain">
    <p style="margin-bottom:2em">
      <span class="huge">Nanoforms</span> <strong>subscribers</strong>
    </p>

    <div id="summary">
      <form method="post" action="subscribers_detail.php">
        <table>
          <thead>
            <tr>
              <th>Filename</th>
              <th>Uploaded (UTC)</th>
              <th>Records</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php
            $granTotal = 0;
            foreach ($sumAr as $recAr) {
              if ($recAr[3] > 0) {
                $granTotal += $recAr[3];
                echo '<tr>' . PHP_EOL . '<td>' . $recAr[1] . '</td><td>' .
                $recAr[2] . '</td><td class="right">' . $recAr[3];
                if ($recAr[0]) {
                  echo '</td><td><input type="submit" name="dett_' . $recAr[0] .
                  '" value="details" />' .
                  '</td>' . PHP_EOL . '</tr>';
                } else {
                  echo '</td><td><input type="submit" name="dett_0' .
                  '" value="details" />' .
                  '</td>' . PHP_EOL . '</tr>';
                }
              }
            }
            echo '<tr>' . PHP_EOL . '<td colspan="2">Total</td><td class="right">' .
            $granTotal;
            echo '</td><td><input type="submit" name="dett_all" ' .
            'value="details" />' .
            '</td>' . PHP_EOL . '</tr>';
             ?>
            </tbody>
          </table>
        </form>
      </div>

    <div id="upload_s">
      <p class="alarm"><?php echo $errMsg; ?></p>

      <p><?php echo $report; ?></p>

      <div class="warning"><?php
       if (count($warnAr)) {
         echo '<p>There were ' . count($warnAr) . ' warnings ' .
         '<button type="button" class="collapsible" onclick="toggleVis(this)">' .
         'Show all</button></p>' .
         PHP_EOL . '<div id="warn_det" style="display:none">' . PHP_EOL . '<ol>' . PHP_EOL;
         foreach ($warnAr as $warn) {
           echo '<li>' . htmlspecialchars($warn) . '</li>' . PHP_EOL;
         }
         echo '</ol>' . PHP_EOL . '</div>' .PHP_EOL;
       }
        ?>
      </div>

      <p id="csv_upl">Here you may upload a list of subscribers as a csv file.<br />
        A column called <strong><em>email</em></strong> must be present.
          An optional column <strong><em>stratum</em></strong> (a subgroup
          identifier like <em>North</em> and <em>South</em> or <em>student</em>
           and <em>teacher</em>) will enable separate counts.
          Other columns, if present, will populate a series of
          <em>name=value</em> pairs to be used in the questionnaire form or in
          invite emails.
      </p>

      <form action=""  method="post" enctype="multipart/form-data">
        <table>
          <tbody>
            <tr>
              <td><label for="uploadSubs">Csv file:</label></td>
              <td><input type="file" id="uploadSubs" name="subsname"
                accept=".csv, .tsv, .txt" /></td>
                <td rowspan="2">
                  <input type="submit" name="uploadSubmit" value="UPLOAD" /></td>
              </tr>
              <tr>
                <td>Duplicates:</td>
                <td>
                  <input type="radio" id="sub_0" name="substitute" value="0"
                  checked /> <label for="sub_0">discard</label>
                  &nbsp; &nbsp; &nbsp;
                  <input type="radio" id="sub_1" name="substitute" value="1" />
                  <label for="sub_1">substitute</label>
                  &nbsp; &nbsp; &nbsp;
                  <input type="radio" id="sub_2" name="substitute" value="2" />
                  <label for="sub_2">update</label>
                  &nbsp; &nbsp; &nbsp;
              </td>
              </tr>
            </tbody>
          </table>
        </form>
      </div>
    </div>

    <script>
    function toggleVis(elem) {
      var x = document.getElementById("warn_det");
      if (x.style.display === "block") {
          x.style.display = "none";
          elem.innerHTML = "Show all";
        } else {
          x.style.display = "block";
          elem.innerHTML = "Hide";
        }
      };
    </script>

  </body>
  </html>
