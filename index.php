<?php
session_start();

require 'common.php';

function warn() {
  if (array_key_exists('nano_isauth', $_SESSION)) {
    return !$_SESSION['nano_isauth'];
  }
  return false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login</title>
  <link rel="stylesheet" type="text/css" href="nanoforms.css" />
</head>
<body>
  <p style="margin-bottom:2em">
    <span class="huge">Nanoforms</span> - helper app for deploying
     simple web questionnaires.
  </p>

  <form method="post" action="login.php">
    <table>
      <?php if (warn()) {
        echo '<tr>
        <td colspan="2"
          class="alarm">
          Login failure</td>
          </tr>';
        }
        ?>
      <tr>
        <td style="text-align:right">
          <label for="nanouser">User name (email):</label>
        </td>
        <td>
          <input type="text" size="25" id="nanouser" name="nanouser"
            value="<?php echo username() ?>" />
        </td>
      </tr>
      <tr>
        <td style="text-align:right">
          <label for="nanopass">Password:</label>
        </td>
        <td>
          <input type="password" size="25" id="nanopass" name="nanopass" />
        </td>
      </tr>
      <tr>
        <td colspan="2" style="text-align:center">
          <input type="submit" name="login_submit" value="LOGIN" />
        </td>
      </tr>
      <tr>
        <td colspan="2" class="tiny" style="text-align:center;padding-top:20px">
          Forgot password? <a href="pass_reset.php">Reset</a>
        </td>
      </tr>
    </table>
  </form>

</body>
</html>
