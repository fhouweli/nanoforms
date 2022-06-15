<?php
session_start();

require 'common.php';

if (!username()) {
  header('Location: index.php');
  exit;
}

$surveyName = '';
if (isset($_SESSION['nano_surveyid'])) {
  $surveyName = test_input($_SESSION['nano_surveyid']);
}
$fileID = '';
if (isset($_SESSION['nano_fileid'])) {
  $fileID = test_input($_SESSION['nano_fileid']);
}
$stratum = '';
if (isset($_SESSION['nano_stratum'])) {
  $stratum = test_input($_SESSION['nano_stratum']);
}
$email = '';
if (isset($_SESSION['nano_email'])) {
  $email = test_input($_SESSION['nano_email']);
}


$errMsg = '';
$report = '';
$oper = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  if (isset($_POST['doremove'])) {
    $db = new PDO("sqlite:data/nanoforms.sqlite");
    $sql = 'DELETE FROM submissions WHERE email = :mail1';
    $st1 = $db->prepare($sql);
    $sql = 'DELETE FROM invitations WHERE email = :mail2';
    $st2 = $db->prepare($sql);
    $sql = 'DELETE FROM subscribers WHERE email = :mail3';
    $st3 = $db->prepare($sql);
    try {
      $db->beginTransaction();
      $st1->bindparam(':mail1', $email, PDO::PARAM_STR);
      $st1->execute();
      $st2->bindparam(':mail2', $email, PDO::PARAM_STR);
      $st2->execute();
      $st3->bindparam(':mail3', $email, PDO::PARAM_STR);
      $st3->execute();
      $db->commit();
      $report = $email . ' has been removed.';
      $email = '';
    } catch(PDOException $e) {
      $db->rollBack();
      $errMsg = 'Removal of ' . $email . ' failed: '. $e;
    }
  } elseif (isset($_POST['dontremove'])) {
    header('Location: subscribers_detail.php');
    exit;
  } elseif (isset($_POST['subStratum'])) {
    $nwStratum = test_input($_POST['nwStratum']);
    $db = new PDO("sqlite:data/nanoforms.sqlite");
    $sql = 'UPDATE subscribers SET stratum=:strat WHERE email=:mail';
    $statement = $db->prepare($sql);
    $statement->bindparam(':strat', $nwStratum, PDO::PARAM_STR);
    $statement->bindparam(':mail', $email, PDO::PARAM_STR);
    $statement->execute();
    $db = null;
  } elseif (isset($_POST['nwStart'])) {
    $db = new PDO("sqlite:data/nanoforms.sqlite");
    $sql = 'SELECT startData FROM subscribers WHERE email = :mail';
    $statement = $db->prepare($sql);
    $statement->bindparam(':mail', $email, PDO::PARAM_STR);
    $statement->execute();
    $res = $statement->fetch();
    $startData = test_input($res['startData']);
    $startAr = explode(";", $startData);
    foreach (array_keys($_POST) as $postKey) {
      if (preg_match('/^del_(.+)$/', $postKey, $matches) === 1) {
        $delKey = test_input($matches[1]);
        for ($i = count($startAr) - 1; $i >= 0; $i--) {
          list($key, $val) = explode("=", $startAr[$i]);
          if ($key == $delKey) {
            unset($startAr[$i]);
            $startAr = array_values($startAr);
          }
        }
      }
    }
    if (isset($_POST['nwKey'])) {
      $key = test_input($_POST['nwKey']);
      if ($key) {
        $val = '';
        if (isset($_POST['nwVal'])) {
          $val = test_input($_POST['nwVal']);
        }
        $startAr[] = $key . '=' . $val;
      }
    }
    $startData = implode(';', $startAr);
    $sql = 'UPDATE subscribers SET startData=:start WHERE email=:mail';
    $statement = $db->prepare($sql);
    $statement->bindparam(':start', $startData, PDO::PARAM_STR);
    $statement->bindparam(':mail', $email, PDO::PARAM_STR);
    $statement->execute();
    $db = null;
  } else {
    foreach (array_keys($_POST) as $key) {
      if (preg_match('/^(mod|rem)_(.+)$/', $key, $matches) === 1) {
        $oper = $matches[1];
        $email = base64_decode($matches[2]);
        break;
      }
    }
  }
}

$_SESSION['nano_email'] = $email;

if ($email) {
  $db = new PDO("sqlite:data/nanoforms.sqlite");
  $sql = 'SELECT stratum, status, startData FROM subscribers WHERE email = :mail';
  $statement = $db->prepare($sql);
  $statement->bindparam(':mail', $email, PDO::PARAM_STR);
  $statement->execute();
  if (!($res = $statement->fetch())) {
    $db = null;
    header('Location: index.php');
    exit;
  }
  $stratum = test_input($res['stratum']);
  $status = test_input($res['status']);
  $startData = test_input($res['startData']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Single subscriber</title>
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
      <span class="huge">Nanoforms</span> <strong>subscriber
      <?php echo $email; ?></strong>
    </p>

<?php
if ($oper == 'rem') {
  echo '<form method="post" action="">' . PHP_EOL .
  '<h2>Do you really want to remove subscriber <span class="alarm">' . $email .
  '</span>?</h2>' . PHP_EOL;
  echo '<p style="text-align:center"><input type="submit" name="doremove" ' .
  'value="Yes, remove" /> &nbsp;  &nbsp;  &nbsp;  &nbsp; <input type="submit" '.
  'name="dontremove" value="No,don' . "'" . 't remove" /></p>' . PHP_EOL .
  '</form>' .PHP_EOL;
  exit;
} elseif (isset($_POST['doremove'])) {
  echo '<p><span class="alarm">' . $errMsg . '</span>' . $report . '</p>' .
  PHP_EOL;
  echo '<p style="text-align: center"><a href="subscribers_detail.php">' .
  'back</a></p>' . PHP_EOL;
  exit;
}
?>

<div id="mod_subscrib">
  <form method="post" action="">
  <table>
    <tbody>
      <tr>
        <td><label for="nwStratum">stratum</label></td><td>
          <input type="text" name="nwStratum" id="nwStratum"
          value="<?php echo $stratum; ?>"</td>
          <td><input type="submit" name="subStratum" value="submit" /></td>
      </tr>
    </tbody>
  </table>

  <table>
    <thead>
      <tr>
        <th colspan="2">Start data:</th><th></th>
      </tr>
      <tr>
        <th>key</th><th>value</th><th>Remove</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $startAr = explode(";", $startData);
      foreach ($startAr as $tuple) {
        list($key, $val) = explode("=", $tuple);
        echo '<tr><td>' . $key . '</td><td>' . $val . '</td><td>' .
        '<input type="checkbox" name="del_' . $key .'" value="1" /></td>' .
        '</tr>' . PHP_EOL;
      }
      ?>
      <tr>
        <td><input type="text" name="nwKey" /></td>
        <td><input type="text" name="nwVal" /></td>
        <td><input type="submit" name="nwStart" value="submit" /></td>
      </tr>
    </tbody>
  </table>

  <p style="text-align: center;margin-top:3em;">
    <a href="subscribers_detail.php">back</a>
  </p>


  </body>
  </html>
