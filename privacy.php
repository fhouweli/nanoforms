<?php
session_start();

require 'common.php';

$MAX_FILE_SIZE = 500000;

if (!username()) {
  header('Location: index.php');
  exit;
}

$surveyName = $_SESSION['nano_surveyid'];

if (!$surveyName) {
  header('Location: surveys.php');
  exit;
}

$formID = 0;
if (isset($_SESSION['nano_formid'])) {
  $formID = $_SESSION['nano_formid'];
}

$statmName = '';
$statmID = 0;
if (isset($_SESSION['nano_statmid'])) {
  $statmID = $_SESSION['nano_statmid'];
}

$uniqID = uniqid("p");
$removeArr = array('"', "'", '@', '^', '.', ',', ';');
$fname = str_replace($removeArr, '', $uniqID);
$tmpdir = __DIR__ . DIRECTORY_SEPARATOR . 'tmp';
$tmpfname = $tmpdir . DIRECTORY_SEPARATOR . $fname . '.html';
$tmpStatm = 'tmp/' . $fname . '.html';

// Take the opportunity to delete temp files
$tmpFiles = glob($tmpdir . DIRECTORY_SEPARATOR . 'p*.html');
$old = strtotime('now -2 days');
for ($i=0; $i < count($tmpFiles); $i++) {
  if (filemtime($tmpFiles[$i]) < $old) {
    unlink($tmpFiles[$i]);
  }
}

$h = fopen($tmpfname, "w") or die ('Error creating ' . $tmpfname);
$html = '<!DOCTYPE html>
<html lang="en">
<head><title>No statement</title></head>
<body>
<p>No statement yet.</p>
</body>
</html>';
if (fwrite($h, $html) === FALSE) {
  echo($tmpfname . ' is not writable');
  exit;
}
fclose($h);

$errMsg = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  if (isset($_POST['uploadSubmit'])) {
    foreach ($_FILES as $file) {
      if ($file['error'] === UPLOAD_ERR_OK) {
        $fileName = basename(test_input($file['name']));
        if ($file['size'] > $MAX_FILE_SIZE) {
          $errMsg = "File size limit of " . $MAX_FILE_SIZE . "exceeded ";
        }
        if ($file['type'] != "text/html") {
          $errMsg .= "Wrong filetype ";
        }
        if (!$errMsg) {
          move_uploaded_file($file['tmp_name'], $tmpfname);
        }
      } elseif ($file['error'] != UPLOAD_ERR_NO_FILE) {
        $errMsg .= "Upload error " . $file['error'];
      }
    }
    if (!$errMsg) {
      $h = fopen($tmpfname, "r");
      $html = fread($h, filesize($tmpfname));
      fclose($h);
      $db = new PDO("sqlite:data/nanoforms.sqlite");
      $sql = 'SELECT ID, name, timeUpload FROM
      privacyStatements WHERE surveyName = :surv AND name = :name';
      $statement = $db->prepare($sql);
      $statement->bindparam(':surv', $surveyName, PDO::PARAM_STR);
      $statement->bindparam(':name', $fileName, PDO::PARAM_STR);
      $statement->execute();
      if (($res = $statement->fetch()) != false) {
        $statmID = $res['ID'];
        $sql = 'UPDATE privacyStatements SET html = :stat, timeUpload = :time
        WHERE ID = :id';
        $statement = $db->prepare($sql);
        $statement->bindparam(':stat', $html, PDO::PARAM_STR);
        $statement->bindparam(':time', strtotime("now"), PDO::PARAM_INT);
        $statement->bindparam(':id', $statmID, PDO::PARAM_INT);
        $statement->execute();
      } else {
        $sql = 'INSERT INTO privacyStatements (name, surveyName, html, timeUpload)
        VALUES (:name, :survey, :html, :time)';
        $statement = $db->prepare($sql);
        $statement->bindparam(':name', $fileName, PDO::PARAM_STR);
        $statement->bindparam(':survey', $surveyName, PDO::PARAM_STR);
        $statement->bindparam(':html', $html, PDO::PARAM_STR);
        $statement->bindparam(':time', strtotime("now"), PDO::PARAM_INT);
        $statement->execute();
        $statmID = $db->lastInsertId();
      }
      $db = null;
      $_SESSION['nano_statmid'] = $statmID;
    } //  no error
  } else {
    foreach (array_keys($_POST) as $elem) {
      if (preg_match('/^prv_([0-9]+)$/', $elem, $matches)) {
        $statmID = test_input($matches[1]);
        $_SESSION['nano_statmid'] = $statmID;
        break;
      }
    }
  }
}  // post


$db = new PDO("sqlite:data/nanoforms.sqlite");

$sql = 'SELECT public FROM surveys WHERE name = :survey';
$statement = $db->prepare($sql);
$statement->bindparam(':survey', $surveyName, PDO::PARAM_STR);
$statement->execute();
$public = $statement->fetchColumn();

$statmsArr = array();
$sql = "SELECT ID, name, html, timeUpload FROM privacyStatements
WHERE surveyName = :survey ORDER BY timeUpload";
$statement = $db->prepare($sql);
$statement->bindparam(':survey', $surveyName, PDO::PARAM_STR);
$statement->execute();
while (($res = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
  $statmsArr[] = $res;
}
$db = null;
if (count($statmsArr) > 0) {
  if (!$statmID) {
    $res = end($statmsArr);
    $statmID = $res['ID'];
    $_SESSION['statmid'] = $statmID;
  }
  for ($ndx = 0; $ndx < count($statmsArr); $ndx++) {
    $res = $statmsArr[$ndx];
    if ($res['ID'] == $statmID) {
      break;
    }
  }
  if ($statmID) {
    $h = fopen($tmpfname, "w");
    fwrite($h, $res['html']);
    fclose($h);
    $statmName = $res['name'];
    $statmHtml = $res['html'];
  } else {
    $statmName = 'No statement';
    $statmHtml = '';
  }
}

header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Privacy statements</title>
  <link rel="stylesheet" type="text/css" href="nanoforms.css" />
</head>
<body>

  <!-- Side navigation. From w3schools.com -->
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
      <span class="huge">Nanoforms</span> privacy statements - survey:
      <strong><?php echo $surveyName; ?></strong>
    </p>

    <div id="showStatements">
      <form method="post" action="">
      <table>
        <thead>
          <tr>
            <th>name</th>
            <th>uploaded (UTC)</th>
          </tr>
        </thead>
        <tbody>
          <?php
          foreach ($statmsArr as $sArr) {
            echo '<tr>' . PHP_EOL .
            '<td>' . $sArr['name'] . '</td><td>' .
            date("Y-m-d H:i", $sArr['timeUpload']) .
            '</td><td><input type="submit" name="frm_' . $sArr['ID'] . '" ' .
            'value="Show"';
            if ($sArr['ID'] == $statmID) {
              echo ' disabled';
            }
            echo ' /></td></tr>' . PHP_EOL;
          }
          ?>
        </tbody>
      </table>
    </form>
  </div>

  <div class="spacedout" id="statmdiv">
    <!-- here show current statement -->
    <p><strong><?php echo $statmName;?></strong></p>
    <iframe id="statmframe" src="<?php echo $tmpStatm;?>" style="height:300px;width:700px;"
      title="Statement <?php echo $statmName;?>">
    </iframe>
  </div>

     <div id="uploadp">
       <p class="alarm"><?php echo $errMsg; ?></p>

       <p>Here you may upload your html privacy statement(s). Remember that
          external local resources must be converted into data uris.
          <a href="templatePrivacy.html" target="_">Show a template</a> to
          save and adapt.
       </p>

       <p><span class="warning">Attention</span>: if a statement with the
         filename already exists it will be replaced with the new version.</p>

       <form action=""  method="post" enctype="multipart/form-data">
         <table>
           <tbody>
             <tr>
               <td><label for="uploadStatm">Html file:</label></td>
               <td><input type="file" id="uploadStatm" name="statmname"
                 accept=".html, .htm" /></td>
                 <td><input type="submit" name="uploadSubmit" value="UPLOAD" /></td>
               </tr>
             </tbody>
           </table>
         </form>
       </div>

  </div>
  </body>
  </html>
