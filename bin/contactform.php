<?php
session_start();

require 'common.php';

$name = $surname = $email = $turtest = $message = '';

$errMsg = '';
$errArr = array();
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  if ($_POST['name'] == '') {
    $errArr[] = 'Please fill out name';
  } else {
    $name = test_input($_POST['name']);
  }
  if ($_POST['surname'] == '') {
    $errArr[] = 'Please fill out surname';
  } else {
    $surname = test_input($_POST['surname']);
  }
  if ($_POST['email'] == '') {
    $errArr[] = 'Please fill out email';
  } else {
    $email = test_input($_POST['email']);
  }
  if ($_POST['turtest'] == '') {
    $errArr[] = 'Please fill out the captcha field';
  } else {
    $turtest = test_input($_POST['turtest']);
    if (strcasecmp($_SESSION['captcha'], $turtest) != 0) {
      $errArr[] = 'Entered captcha code does not match. Please try again.';
    }
  }
  if (trim($_POST['message']) == '') {
    $errArr[] = 'Please fill out comment or message';
  } else {
    $message = test_input($_POST['message']);
  }
  $errMsg = implode('<br />', $errArr);
  if ($errMsg == '') {
    $db = new PDO("sqlite:data/nanoforms.sqlite");
    $sql = 'SELECT mail_to, mail_from from config';
    $statement = $db->prepare($sql);
    $statement->execute();
    $res = $statement->fetch();
    $recip = $res['mail_to'];
    $from = $res['mail_from'];
    $subject = 'New contact form submission ' . $_SERVER["SERVER_NAME"];
    $headers = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-type: text/plain;charset=UTF-8' . "\r\n";
    $headers .= 'From: ' . $from . "\r\n";
    $body = 'Name: ' . $name . '   ' . $surname . "\r\n" .
    'Email: ' . $email . "\r\n" .
    'Comment or message:' . "\r\n" . $message . "\r\n";
    $mailParams = '-f ' . $from;
    if (!mail($recip, $subject, $body, $headers, $mailParams)) {
      $msg = 'Could not deliver your message. Please try later or write to ' .
      $from . '.';
    } else {
      $msg = 'Your message has been delivered. Thank you very much.';
      echo '<!DOCTYPE html>
      <html lang="en">
      <head>
      <meta charset="UTF-8">
      <title>Contact us</title>
      <link rel="stylesheet" type="text/css" href="nanoforms.css" />
      </head>
      <body>
      <p>' . $msg . '</p>
      </body>
      </html>';
    }
    exit;
  }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Contact us</title>
  <link rel="stylesheet" type="text/css" href="nanoforms.css" />
</head>
<body>
  <h1>Contact form</h1>
  <div id="contact">

    <p class="alarm"><?php echo $errMsg; ?></p>

    <form method="post" action="">
          <table>
          <tbody>
            <tr>
              <td>
                <label for="name">Name*:</label><br />
                <input type="text" name="name" id="name"
                value="<?php echo $name;?>" />
              </td>
              <td>
                <label for="surname">Surname*:</label><br />
                <input type="text" name="surname" id="surname" size="26"
                 value="<?php echo $surname;?>" />
              </td>
            </tr>
            <tr>
              <td colspan="2">
                <label for="email">Email*:</label><br />
                <input type="email" name="email" id="email" size="50"
                value="<?php echo $email;?>" />
              </td>
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
              <td colspan="2">
                <label for="message">Comment or message*</label><br />
                <textarea name="message" id="message" rows="9" cols="50">
                  <?php echo $message;?>
                </textarea>
            <tr>
              <td colspan="2" class="center">
                <input type="submit" class="big" name="submit" value="Submit" />
              </td>
            </tr>
          </tbody>
        </table>
      </form>
    </div>
    <script>
    function refreshCaptcha(){
      var img = document.images['captcha_image'];
      img.src = img.src.substring(
        0,img.src.lastIndexOf("?")
      )+"?rand="+Math.random()*1000;
    }
    </script>

  </body>
  </html>
