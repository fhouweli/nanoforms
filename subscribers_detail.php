<?php
session_start();

require 'common.php';

if (!username()) {
  header('Location: index.php');
  exit;
}

function first($txt, $max, $tail = '') {
  if (strlen($txt) > $max) {
    return substr($txt, 0, $max) . $tail;
  }
  return $txt;
}


$surveyName = '';
if (isset($_SESSION['nano_surveyid'])) {
  $surveyName = test_input($_SESSION['nano_surveyid']);
}

date_default_timezone_set("UTC");

$errMsg = '';
$fileID = '';
if (isset($_SESSION['nano_fileid'])) {
  $fileID = test_input($_SESSION['nano_fileid']);
}
$fileName = '';
$stratum = 'all';
if (isset($_SESSION['nano_stratum'])) {
  $stratum = test_input($_SESSION['nano_stratum']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  if (isset($_POST['stratums'])) {
    $stratum = test_input($_POST['stratums']);
    $_SESSION['nano_stratum'] = $stratum;
  } elseif (isset($_POST['dett_all'])) {
    $fileID = '';
    $_SESSION['nano_fileid'] = $fileID;
  } else {
    foreach (array_keys($_POST) as $key) {
      if (preg_match('|^dett_([0-9]+)$|', $key, $matches) !== false) {
        $fileID = $matches[1];
        $_SESSION['nano_fileid'] = $fileID;
        break;
      }
    }
  }
}

$db = new PDO("sqlite:data/nanoforms.sqlite");
if ($fileID) {
  $sql = 'SELECT fileName, timeUpload FROM subscriberFiles WHERE fileID = :fil';
  $statement = $db->prepare($sql);
  $statement->bindparam(':fil', $fileID, PDO::PARAM_INT);
  $statement->execute();
  if (!($res = $statement->fetch())) {
    $db = null;
    header('Location: index.php');
    exit;
  }
  $fileName = $res['fileName'];
  $uploadDate = $res['timeUpload'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Subscribers detail</title>
  <link rel="stylesheet" type="text/css" href="nanoforms.css" />
</head>
<body>

<!-- Side navigation. From w3schools.com -->
<div class="topnav">
  <a href="subscribers.php">Subscribers</a>
  <a href="surveys.php">Surveys</a>
  <a href="survey.php"><?php echo $surveyName;?></a>
  <a href="data.php">Data</a>
  <a href="logout.php">Log out</a>
</div>

  <!-- Page content -->
  <div class="topmain">
    <p style="margin-bottom:2em">
      <span class="huge">Nanoforms</span> <strong>
        <?php
        if ($fileName) {
          echo 'subscriber file ' . $fileName . ' uploaded ' .
          date("Y-m-d H:i", $uploadDate) . ' UTC';
        } else {
          echo 'all subscribers';
        }
        ?></strong>
    </p>

    <form method="post" action="">
    <label for="stratums">Stratum:</label>
    <select id="stratums" name="stratums">
    <option value="all"<?php if ($stratum == 'all') echo(' selected'); ?>
    >all</option>
    <?php
    if ($fileID) {
      $sql = "SELECT stratum, COUNT(*) FROM subscribers WHERE fileID = :fil " .
      "GROUP BY stratum";
    } else {
      $sql = "SELECT stratum, COUNT(*) FROM subscribers GROUP BY stratum";
    }
    $statement = $db->prepare($sql);
    if ($fileID) {
      $statement->bindparam(':fil', $fileID, PDO::PARAM_INT);
    }
    $statement->execute();
    while (($res = $statement->fetch()) !== false) {
      echo '<option value="' . test_input($res['stratum']) . '"';
      if (test_input($res['stratum']) == $stratum) {
        echo ' selected';
      }
      echo '>' . test_input($res['stratum']) . '</option>' . PHP_EOL;
    }
    ?>
    </select>
    <input type="submit" name="stratum_select" value="Submit" />
    </form>

    <div id="subscriberList">
    <form method="post" action="single_subscriber.php">
    <table>
    <thead>
    <tr>
    <th>email</th>
    <th>stratum</th>
    <th>status</th>
    <th>invi-<br />tations</th>
    <th>sub-<br />missions</th>
    <th>com-<br />pletes</th>
    <th>start data</th>
    <th></th>
    <th></th>
    </tr>
    </thead>
    <tbody>
<?php
  $sql = 'SELECT email, stratum, status, startData FROM subscribers';
  if ($fileID) {
    $sql .= ' WHERE fileID = :fil';
    if ($stratum != 'all') {
      $sql .= ' AND stratum = :stratum';
    }
  } else {
    if ($stratum != 'all') {
      $sql .= ' WHERE stratum = :stratum';
    }
  }

  $statement = $db->prepare($sql);
  if ($fileID) {
    $statement->bindparam(':fil', $fileID, PDO::PARAM_INT);
  }
  if ($stratum != 'all') {
    $statement->bindparam(':stratum', $stratum, PDO::PARAM_STR);
  }
  $statement->execute();
  while (($res = $statement->fetch()) !== false) {
    echo '<tr><td>' . first($res['email'], 20, '&hellip;') . '</td><td>' .
     first($res['stratum'], 10, '&hellip;') . '</td><td>' .
     first($res['status'], 10, '&hellip;') . '</td>';

    $sql = 'SELECT COUNT(invitations.email) as invites, ' .
    'COUNT(submissions.complete) as submits, ' .
    'SUM(submissions.complete) as completes ' .
    'FROM invitations INNER JOIN submissions ' .
    'ON submissions.invitationToken = invitations.token WHERE ' .
    'invitations.email = :mail';
    $stm = $db->prepare($sql);
    $stm->bindparam(':mail', $res['email'], PDO::PARAM_STR);
    $stm->execute();
    $res2 = $stm->fetch();
    echo '<td class="right">' . $res2['invites'] . '</td><td class="right">' .
    $res2['submits'] . '</td><td class="right">' . $res2['completes'] . '</td><td>';
    echo first($res['startData'], 40, '...');
    echo '</td><td><input type="submit" name="mod_' .
    base64_encode($res['email']) .
    '" value="modify" />';
    echo '</td><td><input type="submit" name="rem_' .
    base64_encode($res['email']) .
    '" value="remove" /></td></tr>' . PHP_EOL;
  }
  $db = null;
  ?>
  </tbody>
  </table>
  </form>
  </div>

  </body>
  </html>
