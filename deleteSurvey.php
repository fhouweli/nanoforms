<?PHP
session_start();

require 'common.php';

if (!username()) {
  header('Location: index.php');
  exit;
}

$surveyName = $errMsg = "";
if (isset($_SESSION['nano_surveyid'])) {
  $surveyName = test_input($_SESSION['nano_surveyid']);
}
if (!$surveyName) {
  header('Location: surveys.php');
  exit;
}

$errMsg = '';
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
  header('Location: survey.php');
  exit;
}

if (isset($_POST['deleteSurvey'])) {
  echo '<!DOCTYPE html>
        <html lang="en">
        <head>
        <meta charset="UTF-8">
        <title>Confirm delete</title>
        <link rel="stylesheet" type="text/css" href="nanoforms.css" />
        </head>
        <body>
          <div class="sidenav">
          <a href="subscribers.php">Subscribers</a>
          <a href="surveys.php">Surveys</a>
          <a href="survey.php">' . $surveyName . '</a>
          <a href="data.php">Data</a>
          <a href="logout.php">Log out</a>
          </div>
          <div class="sidemain">
        <div id="loader" style="display:none"></div>
        <form method="post" action="">
        <p class="huge center">
        Are you sure you want to delete survey ' . $surveyName . '?</p>
        <p class="big center">
        Removal cannot be undone.
        </p>
        <p class="center">
        <input type="submit" name="confirmDelete" id="confirmDelete"
        value="Yes, delete" /> &nbsp; &nbsp; &nbsp;
        <input type="submit" name="undoDelete" id="undoDelete"
        value="No, go back" />
        </p>
        </form>
        </div>
        <script>
        <!-- function showLoader() {
          document.getElementById("loader").style.display = "block";
          document.getElementById("confirmDelete").disabled=true;
        } -->
        </script>
        </body>
        </html>';
        exit;
  } else {
  if (isset($_POST['undoDelete'])) {
    header('Location: survey.php');
  } elseif (isset($_POST['confirmDelete'])) {
    $nForms = $nMails = $nPrivs = $nInvits = $nSubs = $nResps = 0;
    $db = new PDO("sqlite:data/nanoforms.sqlite");
    $sql = "SELECT ID FROM forms WHERE surveyName = 'Fake'";
    $statement = $db->prepare($sql);
    $statement->execute();
    while (($formID = $statement->fetchColumn()) !== false) {
      $nForms++;
      $sql = 'DELETE FROM submissions WHERE invitationToken IN
      (SELECT token FROM invitations WHERE formID = :fid)';
      $stm = $db->prepare($sql);
      $stm->bindParam(':fid', $formID, PDO::PARAM_INT);
      $stm->execute();
      $nSubs += $stm->rowCount();
      $sql = 'DELETE FROM invitations WHERE formID = :fid';
      $stm = $db->prepare($sql);
      $stm->bindParam(':fid', $formID, PDO::PARAM_INT);
      $stm->execute();
      $nInvits += $stm->rowCount();
      $sql = 'DELETE FROM forms WHERE ID = :fid';
      $stm = $db->prepare($sql);
      $stm->bindParam(':fid', $formID, PDO::PARAM_INT);
      $stm->execute();
    }
    $sql = 'DELETE FROM privacyStatements WHERE surveyName = :nam';
    $statement = $db->prepare($sql);
    $statement->bindParam(':nam', $surveyName, PDO::PARAM_STR);
    $statement->execute();
    $nPrivs = $statement->rowCount();
    $sql = 'DELETE FROM inviteMails WHERE surveyName = :nam';
    $statement = $db->prepare($sql);
    $statement->bindParam(':nam', $surveyName, PDO::PARAM_STR);
    $statement->execute();
    $nMails = $statement->rowCount();
    $sql = 'DELETE FROM surveys WHERE name = :nam';
    $statement = $db->prepare($sql);
    $statement->bindParam(':nam', $surveyName, PDO::PARAM_STR);
    $statement->execute();

    // in case of survey created with fakeData.php, remove subscribers
    $sql = "DELETE FROM subscribers WHERE status = 'fake'";
    $statement = $db->prepare($sql);
    $statement->execute();
    $nResps = $statement->rowCount();


    // Unset survey-specific session data (formid, mailid ...)
    foreach (array_keys($_SESSION) as $key) {
      if ($key != 'nano_isauth') {
        unset($_SESSION[$key]);
      }
    }

  }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Deleted</title>
  <link rel="stylesheet" type="text/css" href="nanoforms.css" />
</head>
<body>
  <div class="sidenav">
    <a href="subscribers.php">Subscribers</a>
    <a href="surveys.php">Surveys</a>
    <a href="logout.php">Log out</a>
  </div>
  <div class="sidemain">
    <p class="center">Survey <?php echo $surveyName;?> has been deleted</p>
    <table style="margin-left:auto;margin-right:auto">
      <tbody>
        <tr>
          <td class="right"><?php echo $nForms;?></td>
          <td>questionnaire forms</td>
        </tr>
        <tr>
          <td class="right"><?php echo $nPrivs;?></td>
          <td>privacy statements</td>
        </tr>
        <tr>
          <td class="right"><?php echo $nMails;?></td>
          <td>invite emails</td>
        </tr>
        <tr>
          <td class="right"><?php echo $nInvits;?></td>
          <td>invitations</td>
        </tr>
        <tr>
          <td class="right"><?php echo $nSubs;?></td>
          <td>submissions</td>
        </tr>
        <?php
        if ($nResps > 0) {
          echo '<tr><td class="right">' . $nResps .
          '</td><td>fake subscribers</td></tr>';
        }
        ?>
      </tbody>
    </table>


  </div>
  </body>
  </html>
