<?php
session_start();

require 'common.php';

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


if (!username()) {
  header('Location: index.php');
  exit;
}

if (isset($_SESSION['nano_surveyid'])) {
  $surveyName = $_SESSION['nano_surveyid'];
}
if (!$surveyName) {
  header('Location: surveys.php');
  exit;
}

$errMsg = "";
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['formID'])) {
  $formID = test_input($_GET['formID']);
} else {
  header('Location: survey.php');
  exit;
}

$db = new PDO("sqlite:data/nanoforms.sqlite");
$sql = 'SELECT a.name AS surveyName, a.public, a.title, a.testMode, b.name AS formName
FROM surveys a
INNER JOIN forms b ON b.surveyName = a.name
WHERE b.ID = :fid';
$statement = $db->prepare($sql);
$statement->bindparam(':fid', $formID, PDO::PARAM_INT);
$statement->execute();
$res = $statement->fetch();
$db = null;
if ($res['surveyName'] != $surveyName) {
  errHtml('Internal server error: surveyName conflict.<br />
  Please contact your webmaster.');
  exit;
}
if (!$res['public']) {
  errHtml($surveyName . ' is not a public survey. Each respondent needs a ' .
  'personal link.');
  exit;
}
$title = $res['title'];
$formName = $res['formName'];
$testMode = $res['testMode'];

$hmac = substr(hash_hmac('sha1', $formID, 'nano'), 0, 5);

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Public link</title>
  <link rel="stylesheet" type="text/css" href="nanoforms.css" />
</head>
<body>

  <!-- Side navigation. From w3schools.com -->
  <div class="sidenav">
    <a href="subscribers.php">Subscribers</a>
    <a href="surveys.php">Surveys</a>
    <a href="survey.php"><?php echo $surveyName;?></a>
    <a href="data.php">Data</a>
    <a href="logout.php">Log out</a>
  </div>

  <!-- Page content -->
  <div class="sidemain">
    <p style="margin-bottom:2em">
      <span class="huge">Nanoforms</span> survey</p>

    <h2><?php echo $surveyName; ?> - <?php echo $title; ?></h2>

    <p class="warning big"><?php echo $testMode ? 'Warning: TEST mode' : '';?></p>

    <p >Publish the followink link to the survey questionnaire
      <?php echo $formName;?>:</p>

    <p class="big mono"><span id="publicUrl"><?php echo dirname(curPageURL()) .
    '/participate.php?f=' . $formID . '&amp;c=' . $hmac;?></span>
    <input type="button" id="copyBut" onclick="copyUrl()" value="Copy url" />
  </p>

    <p>If the medium you publish the link on corresponds to a particular
      stratum (say <em>sports</em>), for the sake of easy bookkeeping you
      may add the stratum to the url like so:</p>

    <p class="mono"><?php echo dirname(curPageURL()) .
    '/participate.php?f=' . $formID . '&amp;c=' . $hmac .
    '&amp;stratum=sports';?></p>

    <p>Lastly, if you find the querystring too ugly and if you promise to use
      only one questionnaire form for the survey, you may use the survey name
      instead, like so:</p>
      <p><span class="mono"><?php echo dirname(curPageURL()) .
      '/participate.php?survey=' . $surveyName;?></span> &nbsp; or <br />
      <span class="mono"><?php echo dirname(curPageURL()) .
      '/participate.php?survey=' . $surveyName . '&amp;stratum=sports';?></p>

  </div>

<script>
function copyUrl() {
  var r = document.createRange();
  r.selectNode(document.getElementById("publicUrl"));
  window.getSelection().removeAllRanges();
  window.getSelection().addRange(r);
  document.execCommand('copy');
  window.getSelection().removeAllRanges();
  document.getElementById('copyBut').value = "copied!";
  setTimeout(function(){
    document.getElementById('copyBut').value = "Copy url";
  }, 2000);
}
</script>

</body>
</html>
