<?php
session_start();

require 'common.php';

define('SUPPORTED_LANGUAGES', ['en', 'it', 'nl']);

$submitValue = array();
$submitValue['en'] = 'Show';
$submitValue['it'] = 'Mostra';
$submitValue['nl'] = 'Tonen';

$submitValue2 = array();
$submitValue2['en'] = 'Submit';
$submitValue2['it'] = 'Inoltra';
$submitValue2['nl'] = 'Versturen';

$refreshCapt = array();
$refreshCapt['en'] = 'new image';
$refreshCapt['it'] = 'altra immagine';
$refreshCapt['nl'] = 'vernieuwen';

$msg1 = array();
$msg1['en'] = 'Privacy statement and terms of service';
$msg1['it'] = 'Informativa privacy e condizioni generali';
$msg1['nl'] = 'Privacyverklaring en algemene voorwaarden';

$msg2 = array();
$msg2['en'] = 'Please enter your contact details. A link to the questionnaire will
be mailed to you';
$msg2['it'] = 'Inserisca i suoi dati di contatto. Le sarà inviato un link al
questionario';
$msg2['nl'] = 'Vul a.u.b. uw contactgegevens in. Een link naar de vragenlijst zal
naar u worden verstuurd per email';

$msg3 = array();
$msg3['en'] = 'Name';
$msg3['it'] = 'Nome';
$msg3['nl'] = 'Voornaam';

$msg4 = array();
$msg4['en'] = 'Surname';
$msg4['it'] = 'Cognome';
$msg4['nl'] = 'Achternaam';

$msg5 = array();
$msg5['en'] = 'Email';
$msg5['it'] = 'Email';
$msg5['nl'] = 'Email';

$msg6 = array();
$msg6['en'] = 'What do you see?';
$msg6['it'] = 'Che cosa vedi?';
$msg6['nl'] = 'Wat lees je hier?';

$msg7 = array();
$msg7['en'] = 'I accept the privacy statement and terms of service';
$msg7['it'] = 'Ho letto l\'informativa privacy e accetto le condizioni generali';
$msg7['nl'] = 'Ik heb de privacyverklaring gelezen en ga accoord met de
algemene voorwaarden';

$errMsg1 = array();
$errMsg1['en'] = 'Please fill out name';
$errMsg1['it'] = 'Fornire il nome per favore';
$errMsg1['nl'] = 'Vul Uw naam in a.u.b.';

$errMsg2 = array();
$errMsg2['en'] = 'Please fill out surname';
$errMsg2['it'] = 'Fornire il cognome per favore';
$errMsg2['nl'] = 'Vul Uw achternaam in a.u.b.';

$errMsg3 = array();
$errMsg3['en'] = 'Please fill out email';
$errMsg3['it'] = 'Fornire l\'indirizzo di posta elettronica per favore';
$errMsg3['nl'] = 'Vul Uw e-mail adres in a.u.b.';

$errMsg4 = array();
$errMsg4['en'] = 'To participate you must accept our privacy statement and
      terms of service';
$errMsg4['it'] = 'Per partecipare è necessario accettare l\'informativa sulla
privacy e i condizioni generali';
$errMsg4['nl'] = 'Om deel te nemen moet u onze privacyverklaring en voorwaarden
accepteren';

$errMsg5 = array();
$errMsg5['en'] = 'Please fill out the captcha field';
$errMsg5['it'] = 'Compilare il campo captcha per favore';
$errMsg5['nl'] = 'Vul het captcha-veld in a.u.b.';

$errMsg6 = array();
$errMsg6['en'] = 'Entered captcha code does not match. Please try again';
$errMsg6['it'] = 'Il codice captcha inserito non corrisponde. Per favore riprova';
$errMsg6['nl'] = 'Ingevoerde captcha-code komt niet overeen. Probeer het opnieuw';

$mailSubject1 = array();
$mailSubject1['en'] = 'Your link to the ';
$mailSubject1['it'] = 'Il Suo collegamento al questionario ';
$mailSubject1['nl'] = 'Uw link naar de ';

$mailSubject2 = array();
$mailSubject2['en'] = ' questionnaire';
$mailSubject2['it'] = '';
$mailSubject2['nl'] = ' vragenlijst';

$mailMsg1 = array();
$mailMsg1['en'] = 'Hello!' . "\r\n\r\n" . 'To participate in the survey ';
$mailMsg1['it'] = 'Ciao!' . "\r\n\r\n" . 'Per partecipare al sondaggio ';
$mailMsg1['nl'] = 'Hallo!' . "\r\n\r\n" . 'Gebruik de volgende link om deel te
nemen aan de enquête';

$mailMsg2 = array();
$mailMsg2['en'] = ', please use the following link:';
$mailMsg2['it'] = ' utilizza il seguente link:';
$mailMsg2['nl'] = ':';

$mailMsg3 = array();
$mailMsg3['en'] = 'This link expires on ';
$mailMsg3['it'] = 'Questo collegamento scade il ';
$mailMsg3['nl'] = 'Deze link verloopt op ';

$mailErrMsg1 = array();
$mailErrMsg1['en'] = 'Could not send email to ';
$mailErrMsg1['it'] = 'Impossibile inviare e-mail a ';
$mailErrMsg1['nl'] = 'Kon geen e-mail sturen naar ';

$mailErrMsg2 = array();
$mailErrMsg2['en'] = '. Please check your email address';
$mailErrMsg2['it'] = '. Per favore controlla il tuo indirizzo email';
$mailErrMsg2['nl'] = '. Controleer uw e-mailadres';

$mailResult = array();
$mailResult['en'] = 'Email mailed. Thank you very much';
$mailResult['it'] = 'E-mail inviata. Grazie mille';
$mailResult['nl'] = 'E-mail verstuurd. Heel erg bedankt';

function escapeEq($txt) {
  return str_replace(array('=', ';'), array(':', ','), $txt);
}

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

$name = $surname = $email = $agree = $turtest = $stratum = '';
$title = '';
$linkValidity = '1 year';
$language = 'en';
$surveyName = 'survey';
$statsArr = array();

$errArr = array();
$errMsg = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

  if (isset($_SESSION['nano_surveyname'])) {
    $surveyName = $_SESSION['nano_surveyname'];
  }
  if (isset($_SESSION['nano_formid'])) {
    $formID = $_SESSION['nano_formid'];
  }
  if (isset($_SESSION['nano_statmid'])) {
    $statID = $_SESSION['nano_statmid'];
  }
  if (isset($_SESSION['nano_language'])) {
    $language = $_SESSION['nano_language'];
  }

  if (isset($_POST['partSubmit'])) {
    if ($_POST['name'] == '') {
      $errArr[] = $errMsg1[$language];
    } else {
      $name = test_input($_POST['name']);
    }
    if ($_POST['surname'] == '') {
      $errArr[] = $errMsg2[$language];
    } else {
      $surname = test_input($_POST['surname']);
    }
    if ($_POST['email'] == '') {
      $errArr[] = $errMsg3[$language];
    } else {
      $email = test_input($_POST['email']);
    }
    if (!isset($_POST['agree']) || test_input($_POST['agree']) != 1) {
      $errArr[] = $errMsg4[$language];
    }
    if ($_POST['turtest'] == '') {
      $errArr[] = $errMsg5[$language];
    } else {
      $turtest = test_input($_POST['turtest']);
      if (strcasecmp($_SESSION['captcha'], $turtest) != 0) {
        $errArr[] = $errMsg6[$language];
      }
    }
    if (isset($_POST['stratum'])) {
      $stratum = $_POST['stratum'];
    } else {
      $stratum = '';
    }
    if (count($errArr) > 0) {
      $errMsg = implode('<br />', $errArr);
    } else {
      // send mail

      $email = test_input($_POST['email']);
      $db = new PDO("sqlite:data/nanoforms.sqlite");

      $sql = 'SELECT linkValidity, testMode FROM surveys WHERE name = :nom';
      $statement = $db->prepare($sql);
      $statement->bindparam(':nom', $surveyName, PDO::PARAM_STR);
      $statement->execute();
      if (($res = $statement->fetch()) !== false) {
        $linkValidity = $res['linkValidity'];
        $testMode = $res['testMode'];
      }

      $sql = 'SELECT a.complete
      FROM submissions a
      INNER JOIN invitations b ON a.invitationToken = b.token
      WHERE a.email = :mail AND b.formID = :frm';
      $statement = $db->prepare($sql);
      $statement->bindparam(':mail', $email, PDO::PARAM_STR);
      $statement->bindparam(':frm', $formID, PDO::PARAM_INT);
      $statement->execute();
      if (($res = $statement->fetch()) !== false) {  // has submitted before
        errHtml('Email address ' . $email . ' has already answered.');
        exit;
      }

      $sql = 'SELECT stratum, startData FROM subscribers WHERE email = :mail';
      $statement = $db->prepare($sql);
      $statement->bindparam(':mail', $email, PDO::PARAM_STR);
      $statement->execute();
      if (($res = $statement->fetch()) === false) {
        // $stratum has already been taken care of
        $startData = 'name=' . escapeEq($name) . ';surname=' .
        escapeEq($surname);
        $sql = "INSERT INTO subscribers (email, stratum, fileID, selfSubscribed,
        startData, status) VALUES (:mail, :strat, 0, 1, :data, '')";
        $statement = $db->prepare($sql);
        $statement->bindparam(':mail', $email, PDO::PARAM_STR);
        $statement->bindparam(':strat', $stratum, PDO::PARAM_STR);
        $statement->bindparam(':data', $startData, PDO::PARAM_STR);
        $statement->execute();
      } else {
        if ($stratum == '') {
          $stratum = $res['stratum'];
        }
        $startData = $res['startData'];
        if (!preg_match('/name=/i', $startData)) {
          $startData .= ';name=' . escapeEq($name) . ';surname=' .
          escapeEq($surname);
          $sql = 'UPDATE subscribers SET stratum = :strat, startData = :data
          WHERE email = :mail';
          $statement = $db->prepare($sql);
          $statement->bindparam(':mail', $email, PDO::PARAM_STR);
          $statement->bindparam(':strat', $stratum, PDO::PARAM_STR);
          $statement->bindparam(':data', $startData, PDO::PARAM_STR);
          $statement->execute();
        }
      }

      $sql = 'SELECT mail_from, mail_signature from config';
      $statement = $db->prepare($sql);
      $statement->execute();
      $res = $statement->fetch();
      $from = $res['mail_from'];
      $signature = $res['mail_signature'];
      $subject = $mailSubject1[$language] . $surveyName . $mailSubject2[$language];
      $headers = 'MIME-Version: 1.0' . "\r\n";
      $headers .= 'Content-type: text/plain;charset=UTF-8' . "\r\n";
      $headers .= 'From: ' . $from . "\r\n";
      $headers .= 'Return-Path: ' . $from . "\r\n";
      $token = openssl_random_pseudo_bytes(16);
      $token = bin2hex($token);
      $uri = dirname(curPageURL()) . '/deploy.php?tk=' . $token;
      $expiry = strtotime('now + ' . $linkValidity);
      $message = $mailMsg1[$language] . $surveyName . $mailMsg2[$language] .
      "\r\n\r\n" . $uri . "\r\n\r\n" .
      $mailMsg3[$language] . date('Y-m-d H:i', $expiry) . ' UTC.' .
      "\r\n\r\n" . $signature;
      if (!mail($email, $subject, $message, $headers, '-f ' . $from)) {
        errHtml($mailErrMsg1[$language] . $email . $mailErrMsg2[$language]);
        $status = 'refused';
      } else {
        $status = 'mailed';
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
        <meta charset="UTF-8">
        <title>Mail sent</title>
        <link rel="stylesheet" type="text/css" href="nanoforms.css" />
        </head>
        <body>
        <p>' . $mailResult[$language] . '.</p>
        </body>
        </html>';
      }
      $sql = 'INSERT INTO invitations (token, email, inviteMailID,
      stratum, status, formID, privacyStatementID, expires, testing, time)
      VALUES (:tok, :mail, 0, :strat, :stat, :frm, :priv, :xpir, :test, :tim)';
      $statement = $db->prepare($sql);
      $statement->bindparam(':tok', $token, PDO::PARAM_STR);
      $statement->bindparam(':mail', $email, PDO::PARAM_STR);
      $statement->bindparam(':strat', $stratum, PDO::PARAM_STR);
      $statement->bindparam(':stat', $status, PDO::PARAM_STR);
      $statement->bindparam(':frm', $formID, PDO::PARAM_INT);
      $statement->bindparam(':priv', $statID, PDO::PARAM_INT);
      $statement->bindparam(':xpir', $expiry, PDO::PARAM_INT);
      $statement->bindparam(':test', $testMode, PDO::PARAM_INT);
      $statement->bindparam(':tim', strtotime('now'), PDO::PARAM_INT);
      $statement->execute();
      $db = null;
      exit;
    }
  } else {
    foreach (array_keys($_POST) as $elem) {
      if (preg_match('/^show_([0-9]+)$/', $elem, $matches)) {
        $statID = test_input($matches[1]);
        break;
      }
    }
    $_SESSION['nano_statmid'] = $statID;
  }
} else {  // GET
  // uninvited: must have ?f=[formid]&c=[hmac] or ?survey=name query (in the
  // latter case the most recent form is used). Optionally, the query string
  // may contain $stratum=[stratum]
  if (!isset($_GET['f']) && !isset($_GET['survey'])) {
    errHtml('Survey not given. Please contact the survey coordinator');
    exit;
  }
  $formID = 0;
  $surveyName = '';
  // if survey given, assume most recent form
  $db = new PDO("sqlite:data/nanoforms.sqlite");
  if (isset($_GET['survey'])) {
    $surveyName = test_input($_GET['survey']);
    $sql = 'SELECT ID, html, timeUpload FROM forms WHERE surveyName = :surv
    ORDER BY timeUpload DESC LIMIT 1';
    $statement = $db->prepare($sql);
    $statement->bindparam(':surv', $surveyName, PDO::PARAM_STR);
    $statement->execute();
    if (($res = $statement->fetch()) === false) {
      errHtml('Form not found. Please contact the survey coordinator');
      exit;
    }
    $formID = $res['ID'];
    $formHtml = $res['html'];
  } else {
    $formID = test_input($_GET['f']);
    $hmac = test_input($_GET['c']);
    if (substr(hash_hmac('sha1', $formID, 'nano'), 0, 5) != $hmac) {
      errHtml('Internal server error. Please contact the survey coordinator');
      exit;
    }
    $sql = 'SELECT surveyName, html FROM forms WHERE ID = :id';
    $statement = $db->prepare($sql);
    $statement->bindparam(':id', $formID, PDO::PARAM_INT);
    $statement->execute();
    if (( $res = $statement->fetch()) === false) {
      errHtml('Survey not found. Please contact the survey coordinator');
    }
    $surveyName = $res['surveyName'];
    $formHtml = $res['html'];
  }

  $stratum = '';
  if (isset($_GET['stratum'])) {
    $stratum = $_GET['stratum'];
  }
  $sql = 'SELECT public, testMode, active, title from surveys WHERE name = :nom';
  $statement = $db->prepare($sql);
  $statement->bindparam(':nom', $surveyName, PDO::PARAM_STR);
  $statement->execute();
  if (($res = $statement->fetch()) === false) {
    errHtml('Survey not found. Please contact the survey coordinator');
    $db = null;
    exit;
  }
  $title = '"' . $res['title'] . '"';
  $testMode = $res['testMode'];
  $public = $res['public'];

  if (!$public) {
    errHtml('Private party. Go away!');
    exit;
  }

  $sql = 'SELECT ID, name, html, timeUpload from privacyStatements
  WHERE surveyName = :surv
  ORDER BY timeUpload';
  $statement = $db->prepare($sql);
  $statement->bindparam(':surv', $surveyName, PDO::PARAM_STR);
  $statement->execute();
  while (($res = $statement->fetch()) !== false) {
    $statsArr[] = $res;
    $statID = $res['ID'];
    $statName = $res['name'];
    $statHtml = $res['html'];
  }
  $tmpdir = __DIR__ . DIRECTORY_SEPARATOR . 'tmp';
  $uniqID = uniqid("p");
  $removeArr = array('"', "'", '@', '^', '.', ',', ';');
  $fname = str_replace($removeArr, '', $uniqID);
  $tmpfname = $tmpdir . DIRECTORY_SEPARATOR . $fname . '.html';
  $tmpStat = 'tmp/' . $fname . '.html';

  // Take the opportunity to delete temp files
  $tmpFiles = glob($tmpdir . DIRECTORY_SEPARATOR . 'p*.html');
  $old = strtotime('now -2 days');
  for ($i=0; $i < count($tmpFiles); $i++) {
    if (filemtime($tmpFiles[$i]) < $old) {
      unlink($tmpFiles[$i]);
    }
  }

  $h = fopen($tmpfname, "w") or die ('Error creating ' . $tmpfname);
  if (fwrite($h, $statHtml) === FALSE) {
    errHtml($tmpfname . ' is not writable. Please contact the survey coordinator');
    exit;
  }
  fclose($h);

  if (!preg_match('/<html\s+[^>]*lang\s*=\s*(?:\"|\')([a-z][a-z])/i', $statHtml,
  $matches)) {
    $language = SUPPORTED_LANGUAGES[0];
  } else {
    $language = strtolower($matches[1]);
    if (!in_array($language, SUPPORTED_LANGUAGES)) {
      $language = SUPPORTED_LANGUAGES[0];
    }
  }

  $_SESSION['nano_surveyname'] = $surveyName;
  $_SESSION['nano_formid'] = $formID;
  $_SESSION['nano_statmid'] = $statID;
  $_SESSION['nano_language'] = $language;

  $db = null;

}  // GET

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?php echo $surveyName; ?></title>
  <link rel="stylesheet" type="text/css" href="nanoforms.css" />
</head>
<body>
  <h1><?php echo $title; ?></h1>

    <?php
    if (count($statsArr) > 1) {
      echo '<div id="chooseStat">
      <form method="post" action="">
      <table>
      <thead>
      <tr>
      <th>file</th>
      <th></th>
      </tr>
      </thead>
      <tbody>';
      foreach ($statsArr as $sArr) {
        echo '<tr>' . PHP_EOL .
        '<td>' . $sArr['name'] . '</td>' .
        '<td><input type="submit" name="show_' . $sArr['ID'] . '" ' .
        'value="' . $submitValue[$language] . '"';
        if ($sArr['ID'] === $statID) {
          echo ' disabled';
        }
        echo ' /></td></tr>' . PHP_EOL;
      }
      echo '</tbody>
      </table>
    </form>
    </div>';
  }
  ?>

  <div class="spacedout" id="privdiv">
    <p><strong><?php echo $msg1[$language];?></strong></p>
    <iframe src="<?php echo $tmpStat;?>" style="height:200px;width:700px;"
      title="<?php echo $tmpStat;?>" id="privframe">
    </iframe>
  </div>

  <div id="optin">

    <p class="alarm"><?php echo $errMsg; ?></p>

    <p><?php echo $msg2[$language];?>.</p>
    <form method="post" action="">
      <input type="hidden" name="stratum" value="<?php echo $stratum;?>" />
      <table>
        <tbody>
          <tr>
            <td>
              <label for="name"><?php echo $msg3[$language];?>*:</label><br />
              <input type="text" name="name" id="name"
              value="<?php echo $name;?>" />
            </td>
            <td>
              <label for="surname"><?php echo $msg4[$language];?>*:</label><br />
              <input type="text" name="surname" id="surname" size="26"
              value="<?php echo $surname;?>" />
            </td>
          </tr>
          <tr>
            <td colspan="2">
              <label for="email"><?php echo $msg5[$language];?>*:</label><br />
              <input type="email" name="email" id="email" size="50"
              value="<?php echo $email;?>" />
            </td>
          </tr>
          <tr>
            <td class="center">
              <label for="turtest"><?php echo $msg6[$language];?>*</label><br />
              <input type="text" name="turtest" id="turtest" size="10"
              value="<?php echo $turtest;?>" /><br />
              <a href="javascript: refreshCaptcha();"><?php echo $refreshCapt[$language];?></a>
            </td>
            <td>
              <img src="captcha.php?rand=<?php echo rand(); ?>"
              id="captcha_image" alt="captcha image" />
            </td>
          </tr>
          <tr>
            <td colspan="2">
              <input type="checkbox" name="agree" id="agree" value="1" />
              <label for="agree"><?php echo $msg7[$language];?>*</label>
            </td>
          </tr>
          <tr>
            <td colspan="2" class="center">
              <input type="submit" name="partSubmit"
              value="<?php echo $submitValue2[$language];?>">
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
      0, img.src.lastIndexOf("?")
    )+"?rand="+Math.random()*1000;
  }
  </script>
</body>
</html>
