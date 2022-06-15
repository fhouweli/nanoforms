<?php
session_start();

require 'common.php';

$LINK_VALIDITY = '4 hours';

$email = $errMsg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $email = test_input($_POST['email']);
  $turtest = test_input($_POST['turtest']);
  if (strcasecmp($_SESSION['captcha'], $turtest) != 0) {
    $errMsg = 'Entered captcha code does not match. Please try again.';
  }
  if ($email) {
    $db = new PDO("sqlite:data/nanoforms.sqlite");
    // get the From: field
    $statement = $db->query("SELECT mail_from FROM config");
    $res = $statement->fetch();
    $mailFrom = $res['mail_from'];

    $sql = "SELECT password FROM users WHERE ID = :mail";
    $statement = $db->prepare($sql);
    $statement->bindParam(':mail', $email, PDO::PARAM_STR);
    try {
      $statement->execute();
    } catch (PDOException $e) {
      $errMsg = $e->getMessage();
    }
    if (!$errMsg) {
      $token = openssl_random_pseudo_bytes(16);
      $token = bin2hex($token);
      $tz = date_default_timezone_get();
      $expiry = strtotime('now + ' . $LINK_VALIDITY);
      $script = substr($_SERVER['SCRIPT_URI'], 0,
      strrpos($_SERVER['SCRIPT_URI'], '/') + 1) . "pass_reset_in.php";
      // generate mail
      $to = $email;
      $subject = "Nanoforms password reset";
      $message = "Hi! This message was sent to you by Nanoforms following a " .
      "request to reset your password.\r\n\r\n" .
      "You can proceed to reset your password at the following link:\r\n\r\n" .
      "   " . $script . "?tk=" . $token . "\r\n\r\n" .
      "This link expires " . date('Y-m-d H:i:s', $expiry) . " " . $tz . "\r\n\r\n" .
      "Enjoy!\r\n" .
      "Nanoforms";

      // Always set content-type when sending HTML email
      $headers = "MIME-Version: 1.0" . "\r\n";
      $headers .= "Content-type:text/plain;charset=UTF-8" . "\r\n";

      // More headers
      $headers .= "From: " . $mailFrom . "\r\n";

      $sql = "INSERT INTO password_resets (userID, token, expires) VALUES
      (:user, :token, :expires)";
      $statement = $db->prepare($sql);
      try {
        $statement->execute([
          ':user' => $email,
          ':token' => $token,
          ':expires' => $expiry
        ]);
      } catch (PDOException $e) {
        $errMsg = $e->getMessage();
      }
    }
    $db = null;

    if (!$errMsg) {
      mail($to, $subject, $message, $headers, '-f ' . $mailFrom);
      echo "<h3>Link has been sent to ". $to . ".</h3>";
      exit;
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Password reset</title>
  <link rel="stylesheet" href="nanoforms.css" />
</head>
<body>
  <p style="margin-bottom:2em">
    <span class="huge">Nanoforms</span>
     password reset.
  </p>

  <p class="alarm"><?php echo $errMsg;?></p>

  <form method="post" action="">
    <table>
      <tr>
        <td class="right"><label for="usermail">Your email address:</label></td>
        <td><input type="email" size="60" id="usermail" name="email"
          value="<?php echo $email;?>" /></td>
      </tr>
      <tr>
        <td class="center">
          <label for="turtest">What do you read?*</label><br />
          <input type="text" name="turtest" id="turtest" size="10" /><br />
          <a href='javascript: refreshCaptcha();'>new image</a>
        </td>
        <td>
          <img src="captcha.php?rand=<?php echo rand(); ?>"
          id="captcha_image" alt="captcha image" />
        </td>
      </tr>
      <tr>
        <td></td>
        <td><input type="submit" name="mailsubmit"
          value="SEND RESET LINK" /></td>
        </tr>
      </table>
    </form>

    <script>
    function refreshCaptcha(){
      var img = document.images['captcha_image'];
      img.src = img.src.substring(
        0, img.src.lastIndexOf("?")
      ) + "?rand="+Math.random()*1000;
    }
    </script>

  </body>
  </html>
