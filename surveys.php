<?php
session_start();

require 'common.php';

if (!username()) {
  header('Location: index.php');
  exit;
}

$newname = $newtitle = $newpublic = $defaultValidity = $errMsg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $newname = test_input($_POST['newname']);
  $newtitle = test_input($_POST['newtitle']);
  $newpublic = isset($_POST['newpublic'])? 1 : 0;
  if ($newname && $newtitle) {
    $db = new PDO("sqlite:data/nanoforms.sqlite");
    $sql = 'SELECT COUNT(*) FROM surveys WHERE name = :name';
    $statement = $db->prepare($sql);
    $statement->bindparam(':name', $newname, PDO::PARAM_STR);
    $statement->execute();
    $count = $statement->fetchColumn();
    if ($count > 0) {
      $errMsg = 'Survey exists - no action taken';
    } else {
      $sql = 'SELECT default_link_validity FROM config';
      $statement = $db->prepare($sql);
      $statement->execute();
      $res = $statement->fetch();
      $defValidity = $res['default_link_validity'];
      $sql = 'INSERT INTO surveys (name, title, public, linkValidity,
        allowRevisit, active, testMode)
        VALUES (:nam, :titl, :publ, :vali, 1, 1, 1)';
      $statement = $db->prepare($sql);
      $statement->bindparam(':nam', $newname, PDO::PARAM_STR);
      $statement->bindparam(':titl', $newtitle, PDO::PARAM_STR);
      $statement->bindparam(':publ', $newpublic, PDO::PARAM_INT);
      $statement->bindparam(':vali', $defValidity, PDO::PARAM_STR);
      $statement->execute();
    }
    $db = null;
    if (!$errMsg) {
      $_SESSION['nano_surveyid'] = $newname;
      header('Location: survey.php');
    }
  }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Surveys</title>
  <link rel="stylesheet" type="text/css" href="nanoforms.css" />
</head>
<body>

<!-- Side navigation. From w3schools.com -->
<div class="sidenav">
  <a href="subscribers.php">Subscribers</a>
  <a href="logout.php">Log out</a>
</div>

<!-- Page content -->
<div class="sidemain">
  <p style="margin-bottom:2em">
    <span class="huge">Nanoforms</span> surveys:
  </p>

  <p class="alarm"><?php echo $errMsg;?></p>

  <form method="post" action="">
  <table>
  <thead>
  <tr>
  <th class="left">name</th><th class="left">title</th>
  <th class="left">public</th>
  </tr>
  </thead>
  <tbody>

  <?php
  $db = new PDO("sqlite:data/nanoforms.sqlite");

  $sql = "SELECT name, title, public, rowid FROM surveys ORDER BY rowid DESC";
  $statement = $db->prepare($sql);
  $statement->execute();
  $res = $statement->fetchAll(PDO::FETCH_ASSOC);

  $db = null;

  $html = "";
  foreach ($res as $row) {
    if ($row['public']) {
      $box = '&check;';
    } else {
      $box = '';
    }
    $html .= "\r\n<tr>\r\n<td>" . '<a href="survey.php?name=' . $row['name'] . '">';
    $html .= $row['name'] . "</td>\r\n<td>" . $row['title'] . "</td>\r\n";
    $html .= '<td class="center">' . $box . "</td>\r\n</tr>";
  }

  echo $html;

  ?>

  <tr><td colspan="3">Add new:</td></tr>
  <tr>
  <td><input type="text" name="newname" size="15" /></td>
  <td><input type="text" name="newtitle" size="60" /></td>
  <td class="center"><input type="checkbox" name="newpublic" value="1" /></td>
  </tr>
  <tr>
  <td colspan="3"><input type="submit" name="add_new" value="ADD" />
  </tr>
  </tbody>
  </table>
</form>
</div>
</body>
</html>
