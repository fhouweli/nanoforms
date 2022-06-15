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

$uniqID = uniqid("m");
$removeArr = array('"', "'", '@', '^', '.', ',', ';');
$fname = str_replace($removeArr, '', $uniqID);
$tmpdir = __DIR__ . DIRECTORY_SEPARATOR . 'tmp';
$tmpfname = $tmpdir . DIRECTORY_SEPARATOR . $fname . '.html';
$tmpMail = 'tmp/' . $fname . '.html';

// Take the opportunity to delete temp files
$tmpFiles = glob($tmpdir . DIRECTORY_SEPARATOR . 'm*.html');
$old = strtotime('now -2 days');
for ($i=0; $i < count($tmpFiles); $i++) {
  if (filemtime($tmpFiles[$i]) < $old) {
    unlink($tmpFiles[$i]);
  }
}

$h = fopen($tmpfname, "w") or die ('Error creating ' . $tmpfname);
$html = '<!DOCTYPE html>
<html lang="en">
<head><title>No mails</title></head>
<body>
<p>No mails yet.<br /><!-- %_LINK_% (to avoid warnings) --> </p>
</body>
</html>';
if (fwrite($h, $html) === FALSE) {
  echo($tmpfname . ' is not writable');
  exit;
}
fclose($h);


date_default_timezone_set("UTC");

$errMsg = '';
$warnMsg = '';
$mailID = '';
$mailName='';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  if (isset($_POST['uploadMail'])) {
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
      if (preg_match('/%_LINK_%"/i', $html) === 0) {
        $warnMsg = 'Link %_LINK_% to questionnaire not found in html file';
      }
      if (!$errMsg) {
        $db = new PDO("sqlite:data/nanoforms.sqlite");

        $sql = 'SELECT ID, timeUpload FROM inviteMails WHERE surveyName = :surv
        AND name = :name';
        $statement = $db->prepare($sql);
        $statement->bindparam(':surv', $surveyName, PDO::PARAM_STR);
        $statement->bindparam(':name', $fileName, PDO::PARAM_STR);
        $statement->execute();
        if (($res = $statement->fetch()) !== false) {
          $mailID = $res['ID'];
          $sql = 'UPDATE inviteMails SET html = :body, timeUpload = :time WHERE
          ID = :id';
          $statement = $db->prepare($sql);
          $statement->bindparam(':body', $html, PDO::PARAM_STR);
          $statement->bindparam(':time', strtotime("now"), PDO::PARAM_INT);
          $statement->bindparam(':id', $mailID, PDO::PARAM_INT);
          $statement->execute();
        } else {
          $sql = "INSERT INTO inviteMails (surveyName, name, html, timeUpload) VALUES
          (:surv, :name, :html, :time)";
          $statement = $db->prepare($sql);
          $statement->bindparam(':surv', $surveyName, PDO::PARAM_STR);
          $statement->bindparam(':name', $fileName, PDO::PARAM_STR);
          $statement->bindparam(':html', $html, PDO::PARAM_STR);
          $statement->bindparam(':time', strtotime("now"), PDO::PARAM_INT);
          $statement->execute();
          $mailID = $db->lastInsertId();
        }
        $db = null;
      } //  no error
    } //  no error
  } else {
    foreach (array_keys($_POST) as $elem) {
      if (preg_match('/^show_([0-9]+)$/', $elem, $matches)) {
        $mailID = test_input($matches[1]);
        break;
      }
    }
  }
}  // post

$mailsArr = array();
$db = new PDO("sqlite:data/nanoforms.sqlite");
$sql = 'SELECT public FROM surveys WHERE name = :surv';
$statement = $db->prepare($sql);
$statement->bindparam(':surv', $surveyName, PDO::PARAM_STR);
$statement->execute();
$public = $statement->fetchColumn();
if ($public) {
  errHtml($surveyName . ' is of type <strong>public</strong>. No mails required');
  exit;
}

$sql = "SELECT ID, name, html, timeUpload FROM inviteMails
 WHERE surveyName = :surv ORDER BY timeUpload";
$statement = $db->prepare($sql);
$statement->bindparam(':surv', $surveyName, PDO::PARAM_STR);
$statement->execute();
while (($res = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
  $mailsArr[] = $res;
}
$db = null;
if (count($mailsArr) > 0) {
  if (!$mailID) {
    $res = end($mailsArr);
    $mailID = $res['ID'];
  }
  for ($ndx = 0; $ndx < count($mailsArr); $ndx++) {
    $res = $mailsArr[$ndx];
    if ($res['ID'] == $mailID) {
      break;
    }
  }
  if ($mailID) {
    $h = fopen($tmpfname, "w");
    fwrite($h, $res['html']);
    fclose($h);
    $mailName = $res['name'];
  } else {
    $mailName = 'No mail';
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
  <title>Mails</title>
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
      <span class="huge">Nanoforms</span> invitation emails. Survey:
      <strong><?php echo $surveyName; ?></strong>
    </p>

    <div id="showMails">
      <form method="post" action="">
      <table>
        <thead>
          <tr>
            <th>name</th>
            <th>uploaded (UTC)</th>
            <td></td>
            <td></td>
          </tr>
        </thead>
        <tbody>
          <?php
          foreach ($mailsArr as $mArr) {
            echo '<tr>' . PHP_EOL .
            '<td>' . $mArr['name'] . '</td>' .
            '<td>' . date("Y-m-d H:i", $mArr['timeUpload']) .
            '</td><td><input type="submit" name="show_' . $mArr['ID'] . '" ' .
            'value="Select"';
            if ($mArr['ID'] === $mailID) {
              echo ' disabled';
            }
            echo ' /></td><td>';
            if ($mArr['ID'] === $mailID) {
              echo '<a href="recipients.php?mailID=' . $mArr['ID'] .
              '">Invite subscribers</a>';
            }
            echo '</td></tr>' . PHP_EOL;
          }
          ?>
        </tbody>
      </table>
    </form>

    <!-- here show current mail -->
    <div class="spacedout" id="mailsdiv">
    <p><strong><?php echo $mailName; ?></strong></p>
    <iframe src="<?php echo $tmpMail;?>" style="height:300px;width:700px;"
       id="mailsFrame" height=""title="Form <?php echo $mailName;?>">
     </iframe>

     <div id="uploadml">
       <p class="alarm"><?php echo $errMsg; ?></p>
       <p class="warning"><?php echo $warnMsg; ?></p>

       <p>Here you may upload your html emails. In the email body, mark
         the link to the questionnaire as <strong><em>%_LINK_%</em></strong>,
         either as text (your subscriber&apos;s email client will transform it
         into a link) or as anchor (like in <em>Please fill out our &lt;a
           href="%_LINK_%"&gt;questionnaire&lt;/a&gt;</em>).<br />
           Other strings of the format <em>%_key_%</em> (where key is from a
           <em>key=value</em> pair in subscriber&apos;s start data) will be
           substituted with the corresponding value.<br />
           External local resources must be converted into data uris.
         </p>

       <p><span class="warning">Attention</span>: if a mail with the filename
         already exists it will be replaced with the new version.</p>

       <form action=""  method="post" enctype="multipart/form-data">
         <table>
           <tbody>
             <tr>
               <td><label for="uploadMail">Html file for email body:</label></td>
               <td><input type="file" id="uploadMail" name="mailName"
                 accept=".html, .htm" /></td>
                 <td><input type="submit" name="uploadMail" value="UPLOAD" /></td>
               </tr>
             </tbody>
           </table>
         </form>
       </div>

  </div>
  </body>
  </html>
