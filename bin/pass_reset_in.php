<?php

require 'common.php';

$user = $pass1 = $pass2 = $errMsg = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {

  $user = test_input($_POST['userID']);
  $pass1 = test_input($_POST['pass1']);
  $pass2 = test_input($_POST['pass2']);

  if ($pass1 !== $pass2) {
    $errMsg = "Passwords differ";
  } else {
    $passHash = password_hash($pass1, PASSWORD_DEFAULT);
    $db = new PDO("sqlite:data/nanoforms.sqlite");
    $sql = "UPDATE users SET password = :pw WHERE ID = :user";
    $statement = $db->prepare($sql);
    $statement->bindParam(':pw', $passHash, PDO::PARAM_STR);
    $statement->bindParam(':user', $user, PDO::PARAM_STR);
    try {
      $statement->execute();
    } catch (PDOException $e) {
      $errMsg = $e->getMessage();
    }

    $sql = "DELETE FROM password_resets WHERE userID = :id";
    $statement = $db->prepare($sql);
    $statement->bindParam(':id', $user, PDO::PARAM_STR);
    $statement->execute();

    $db = null;

    if ($errMsg) {
      echo $errMsg;
      exit;
    }

    echo "<!DOCTYPE html>\r\n<html lang=\"en\">\r\n<head>\r\n" .
    "<meta charset=\"UTF-8\">\r\n<title>Nanoforms</title>\r\n" .
    "<link rel=\"stylesheet\" href=\"nanoforms.css\" />\r\n</head>\r\n" .
    "<body>\r\n<h3>Password updated.</h3>\r\n<p>" .
    "<a href=\"index.php\">Sign in</a></p>\r\n</body>\r\n</html>";

    exit;

  }

} else {

  if (!array_key_exists('tk', $_GET)) {
    header("Location: pass_reset.php");
    exit;
  }

  $token = "";
  $token = test_input($_GET['tk']);

  $db = new PDO("sqlite:data/nanoforms.sqlite");
  $sql = "SELECT userID, expires FROM password_resets WHERE token = :tok";
  $statement = $db->prepare($sql);
  $statement->bindParam(':tok', $token, PDO::PARAM_STR);
  $statement->execute();
  if (($res = $statement->fetch()) === false) {
    echo 'Reset request not found';
    $db = null;
    exit;
  }
  $db = null;

  $tz = date_default_timezone_get();
  if ($res['expires'] < strtotime("now")) {
    echo "Link has expired " . date('Y-m-d H:i', $res['expires']) . ' ' . $tz . '.';
    exit;
  }
  $userID = $res['userID'];

}  // get

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reset password</title>
  <link rel="stylesheet" href="nanoforms.css" />
</head>
<body>
  <p style="margin-bottom:2em">
    <span style="font-size:200%;font-weight:bold">Nanoforms</span>
     password reset.
  </p>

  <form method="post" action="">
    <input type="hidden" name="userID" value="<?php echo $userID; ?>" />
    <table>
      <tr>
        <td></td>
        <td class="alarm"><?php echo $errMsg; ?></td>
      </tr>
      <tr>
        <td><label for="pass1">New password:</label></td>
        <td><input type="password" size="20" id="pass1" name="pass1" /></td>
      </tr>
      <tr>
        <td><label for="pass2">Repeat:</label></td>
        <td><input type="password" size="20" id="pass2" name="pass2" /></td>
      </tr>
      <tr>
        <td></td>
        <td><input type="submit" name="passwsubmit"
          value="SET PASSWORD" /></td>
        </tr>
      </table>
    </form>
  </body>
  </html>
