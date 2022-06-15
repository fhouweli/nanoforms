<?php
session_start();

require 'common.php';
require 'fieldList.php';

$MAX_FILE_SIZE = 500000;


if (!username()) {
  header('Location: index.php');
  exit;
}

$surveyName = $_SESSION['nano_surveyid'];

$completeCond = '';
$completeDef = '';
$MAXCOND = 5;

if (!$surveyName) {
  header('Location: surveys.php');
  exit;
}

$formID = 0;
if (isset($_SESSION['nano_formid'])) {
  $formID = $_SESSION['nano_formid'];
}

$uniqID = uniqid("f");
$removeArr = array('"', "'", '@', '^', '.', ',', ';');
$fname = str_replace($removeArr, '', $uniqID);
$tmpdir = __DIR__ . DIRECTORY_SEPARATOR . 'tmp';
$tmpfname = $tmpdir . DIRECTORY_SEPARATOR . $fname . '.html';
$tmpForm = 'tmp/' . $fname . '.html';

// Take the opportunity to delete temp files
$tmpFiles = glob($tmpdir . DIRECTORY_SEPARATOR . 'f*.html');
$old = strtotime('now -2 days');   // time of server != UTC but never mind
for ($i=0; $i < count($tmpFiles); $i++) {
  if (filemtime($tmpFiles[$i]) < $old) {
    unlink($tmpFiles[$i]);
  }
}

$h = fopen($tmpfname, "w") or die ('Error creating ' . $tmpfname);
$html = '<!DOCTYPE html>
<html lang="en">
<head><title>No form</title></head>
<body>
<p>No form yet.</p>
</body>
</html>';
if (fwrite($h, $html) === FALSE) {
  echo($tmpfname . ' is not writable');
  exit;
}
fclose($h);

$errMsg = '';
$condErr = '';
$formName = '';

date_default_timezone_set("UTC");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  if (isset($_POST['uploadSubmit'])) {
    foreach ($_FILES as $file) {
      if ($file['error'] === UPLOAD_ERR_OK) {
        $fileName = basename(test_input($file['name']));
        if ($file['size'] > $MAX_FILE_SIZE) {
          $errMsg = "File size limit of " . $MAX_FILE_SIZE . "exceeded ";
        }
        if ($file['type'] != "text/html") {
          $errMsg .= "Wrong filetype ";
        }
        if (!$errMsg) {
          move_uploaded_file($file['tmp_name'], $tmpfname);
        }
      } elseif ($file['error'] != UPLOAD_ERR_NO_FILE) {
        $errMsg .= "Upload error " . $file['error'];
      }
    }
    if (!$errMsg) {
      $h = fopen($tmpfname, "r");
      $html = fread($h, filesize($tmpfname));
      fclose($h);
      if (preg_match('/<form [^>]*action\s*=\s*"%_TARGET_%"/i', $html) === 0 &&
      preg_match("/<form [^>]*action\s*=\s*'%_TARGET_%'/i", $html) === 0) {
        $errMsg = 'Form action target &quot;%_TARGET_%&quot; not found in html file';
      }
      if (preg_match('/\sname\s*=\s*"tk__"/i', $html) != 0 ||
      preg_match("/\sname\s*=\s*'tk__'/i", $html) != 0) {
        $errMsg = 'Form uses reserved variable name &quot;tk__&quot;';
      }
      if (!$errMsg) {
        $db = new PDO("sqlite:data/nanoforms.sqlite");
        $sql = 'SELECT ID, name, completeDefinition, timeUpload FROM
        forms WHERE surveyName = :surv AND name = :name';
        $statement = $db->prepare($sql);
        $statement->bindparam(':surv', $surveyName, PDO::PARAM_STR);
        $statement->bindparam(':name', $fileName, PDO::PARAM_STR);
        $statement->execute();
        if (($res = $statement->fetch()) != false) {
          $formID = $res['ID'];
          $completeDef = $res['completeDefinition'];
          $sql = 'UPDATE forms SET html = :form, timeUpload = :time WHERE ID = :id';
          $statement = $db->prepare($sql);
          $statement->bindparam(':form', $html, PDO::PARAM_STR);
          $statement->bindparam(':time', strtotime("now"), PDO::PARAM_INT);
          $statement->bindparam(':id', $formID, PDO::PARAM_INT);
          $statement->execute();
        } else {
          $sql = 'INSERT INTO forms (name, surveyName, html, timeUpload) VALUES
          (:name, :survey, :html, :time)';
          $statement = $db->prepare($sql);
          $statement->bindparam(':name', $fileName, PDO::PARAM_STR);
          $statement->bindparam(':survey', $surveyName, PDO::PARAM_STR);
          $statement->bindparam(':html', $html, PDO::PARAM_STR);
          $statement->bindparam(':time', strtotime("now"), PDO::PARAM_INT);
          $completeDef = '';
          $statement->execute();
          $formID = $db->lastInsertId();
        }
        $db = null;
        $_SESSION['nano_formid'] = $formID;
      } //  no error
    } //  no error
  } elseif (isset($_POST['submitCond'])) {
    if (!$formID) {
      $errMsg = 'No form selected - cannot process condition';
    }
    $completeCond = '';
    if (!$errMsg) {
      $openKeys = array_keys($_POST, 'oparen');
      $fieldKeys = array_keys($_POST, 'field');
      $operKeys = array_keys($_POST, 'oper');
      $valuKeys = array_keys($_POST, 'valu');
      $closeKeys = array_keys($_POST, 'cparen');
      $conjKeys = array_keys($_POST, 'conj');
      for ($x = 1; $x <= $MAXCOND; $x++) {
        if (isset($_POST['oparen'.$x]) && $_POST['oparen'.$x] == "yes") {
          $completeCond .= '(';
        }
        if (isset($_POST['field'.$x]) && $_POST['field'.$x] != '') {
          $completeCond .= $_POST['field'.$x];
          if (isset($_POST['oper'.$x]) && $_POST['oper'.$x] != '') {
            if ($_POST['oper'.$x] == "eq") {
              $completeCond .= ' == ';
            } elseif ($_POST['oper'.$x] == "ne") {
              $completeCond .= ' != ';
            } elseif ($_POST['oper'.$x] == "gt") {
              $completeCond .= ' &gt; ';
            } elseif ($_POST['oper'.$x] == "ge") {
              $completeCond .= ' &gt= ';
            } elseif ($_POST['oper'.$x] == "lt") {
              $completeCond .= ' &lt; ';
            } elseif ($_POST['oper'.$x] == "le") {
              $completeCond .= ' &lt;= ';
            }
            // note flags of test_input (to have apostrophes encoded as well)
            $valu = test_input($_POST['valu'.$x]);
            if (!is_numeric($valu)) {
              $valu = '&apos;' . $valu . '&apos;';
            }
            $completeCond .= $valu;
          } else {
            $condErr = 'Invalid: left hand field without operator';
            break;
          }
        }
        if (isset($_POST['cparen'.$x]) && $_POST['cparen'.$x] == "yes") {
          $completeCond .= ')';
        }
        if (isset($_POST['conj'.$x])) {
          if ($_POST['conj'.$x] == "and") {
            $completeCond .= ' && ';
          } elseif ($_POST['conj'.$x] == "or") {
            $completeCond .= ' || ';
          }
        }
      } // for
      if (substr(trim($completeCond), -2) == '&&' ||
      substr(trim($completeCond), -2) == '||') {
        $condErr = 'Invalid: ending logical operator';
      } elseif (substr_count($completeCond, '(') !=
      substr_count($completeCond, ')')) {
        $condErr = 'Invalid: mismatched parentheses';
      }
      if (!$condErr) {
        $db = new PDO("sqlite:data/nanoforms.sqlite");
        $sql = 'UPDATE forms SET completeDefinition = :def WHERE ID = :id';
        $statement = $db->prepare($sql);
        // we store the condition with htmlentities
        $statement->bindparam(':def', $completeCond, PDO::PARAM_STR);
        $statement->bindparam(':id', $formID, PDO::PARAM_INT);
        $statement->execute();
        $db = null;
      }  // no error in condition
    } // no error
  } else {     // change form displayed
    foreach (array_keys($_POST) as $elem) {
      if (preg_match('/^frm_([0-9]+)$/', $elem, $matches)) {
        $formID = test_input($matches[1]);
        $_SESSION['nano_formid'] = $formID;
        break;
      }
    }
  }
}  // post


$db = new PDO("sqlite:data/nanoforms.sqlite");

$sql = 'SELECT public FROM surveys WHERE name = :survey';
$statement = $db->prepare($sql);
$statement->bindparam(':survey', $surveyName, PDO::PARAM_STR);
$statement->execute();
$public = $statement->fetchColumn();

$formsArr = array();
$sql = "SELECT ID, name, html, completeDefinition, timeUpload FROM forms
 WHERE surveyName = :survey ORDER BY timeUpload";
$statement = $db->prepare($sql);
$statement->bindparam(':survey', $surveyName, PDO::PARAM_STR);
$statement->execute();
while (($res = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
  $formsArr[] = $res;
}
$db = null;
if (count($formsArr) > 0) {
  if (!$formID) {
    $res = end($formsArr);
    $formID = $res['ID'];
    $_SESSION['nano_formid'] = $formID;
  }
  for ($ndx = 0; $ndx < count($formsArr); $ndx++) {
    $res = $formsArr[$ndx];
    if ($res['ID'] == $formID) {
      break;
    }
  }
}
if ($formID) {
  $h = fopen($tmpfname, "w");
  fwrite($h, $res['html']);
  fclose($h);
  $formName = $res['name'];
  $formHtml = $res['html'];
} else {
  $formName = 'No form';
  $formHtml = '';
}

$fieldVec = array();
if ($formID) {
  $fieldVec = fieldList($formHtml);
  $fieldVec = array_intersect_key($fieldVec,
  array_unique(array_map('serialize', $fieldVec)));
  $h = fopen($tmpfname, "w");
  fwrite($h, $formHtml);
  fclose($h);
  $completeCond = $res['completeDefinition'];
} else {
  $completeCond = '';
}

header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forms</title>
  <link rel="stylesheet" type="text/css" href="nanoforms.css" />
</head>
<body>

  <!-- Side navigation. From w3schools.com -->
  <div class="sidenav">
    <a href="subscribers.php">Subscribers</a>
    <a href="surveys.php">Surveys</a>
    <a href="survey.php"><?php echo $surveyName; ?></a>
    <?php
    if ($formID) {
      if (!$public) {
        echo '<a href="mails.php">Mails</a>' . PHP_EOL;
      }
      echo '<a href="data.php">Data</a>' . PHP_EOL;
    }
    ?>
    <a href="logout.php">Log out</a>
  </div>

  <!-- Page content -->
  <div class="sidemain">
    <p style="margin-bottom:2em">
      <span class="huge">Nanoforms</span> questionnaire forms - survey:
      <strong><?php echo $surveyName; ?></strong>
    </p>

    <div id="showForms">
      <form method="post" action="">
      <table>
        <thead>
          <tr>
            <th>name</th>
            <th>uploaded (UTC)</th>
            <td></td>
            <td></td>
          </tr>
        </thead>
        <tbody>
          <?php
          foreach ($formsArr as $fArr) {
            echo '<tr>' . PHP_EOL .
            '<td>' . $fArr['name'] . '</td><td>' .
            date("Y-m-d H:i", $fArr['timeUpload']) .
            '</td><td><input type="submit" name="frm_' . $fArr['ID'] . '" ' .
            'value="Select"';
            if ($fArr['ID'] == $formID) {
              $formName = $fArr['name'];
              echo ' disabled';
            }
            echo ' /></td>' . PHP_EOL;
            echo '<td>';
            if ($fArr['ID'] == $formID) {
              if ($public) {
                $targ = 'publicLink';
              } else {
                $targ = 'recipients';
              }
              echo '<a href="' . $targ . '.php?formID=' . $fArr['ID'] .
              '">Invite subscribers</a>';
            }
            echo '</td>' . PHP_EOL .
            '</tr>' . PHP_EOL;
          }
          ?>
      </table>
    </form>
  </div>

    <!-- here show current form -->
    <div class="spacedout" id="formdiv">
      <p><strong><?php echo $formName;?></strong></p>
      <iframe id="formframe" src="<?php echo $tmpForm;?>" style="height:300px;width:700px;"
        title="Form <?php echo $formName;?>">
      </iframe>
    </div>


     <div id="uploadq">
       <p class="alarm"><?php echo $errMsg; ?></p>

       <p>Here you may upload your html questionnaire(s). Don&apos;t forget the
         <em>lang</em> attribute on the html tag. The
         <em>action</em> target of your form must be
         <strong><em>&quot;%_TARGET_%&quot;</em></strong>,
         and external local resources must be converted into data uris.
       </p>

       <p><span class="warning">Attention</span>: if a form with the filename
         already exists it will be replaced with the new version.</p>

       <form action=""  method="post" enctype="multipart/form-data">
         <table>
           <tbody>
             <tr>
               <td><label for="uploadForm">Html file:</label></td>
               <td><input type="file" id="uploadForm" name="formname"
                 accept=".html, .htm" /></td>
                 <td><input type="submit" name="uploadSubmit" value="UPLOAD" /></td>
               </tr>
             </tbody>
           </table>
         </form>
       </div>

       <div id="condition">
         <p>A questionnaire is considered complete if:</p>
         <p class="alarm"><?php echo $condErr;?></p>
           <p><span class="huge mono"><?php echo $completeCond; ?></span></p>
         </p>
         <form action=""  method="post">
           <table>
             <tr><th>(</th><th>field</th><th>relop</th><th>value</th><th>)</th>
               <th>and/or</th></tr>
             <?php
             for ($x = 1; $x <= $MAXCOND; $x++) {
               echo "<tr>\r\n";
               if ($x == $MAXCOND) {
                 echo "<td></td>\r\n";
               } else {
                 echo '<td><select id="oparen' . $x . '" name="oparen' . $x . "\">\r\n";
                 echo '<option value="no"> </option>' . "\r\n";
                 echo '<option value="yes">(</option>' . "\r\n";
                 echo '</select></td>' . "\r\n";
               }
               echo '<td><select id="field' . $x . '" name="field' . $x . "\">\r\n";
               echo '<option value=""> </option>' . "\r\n";
               foreach ($fieldVec as $fields) {
                 echo '<option value="' . $fields['name'] . '">' . $fields['name'] . '</option>' ."\r\n";
               }
               echo "</select></td>\r\n";
               echo '<td><select id="oper' . $x . '" name="oper' . $x . "\">\r\n";
               echo '<option value=""></option>
               <option value="eq">==</option>
               <option value="ne">!=</option>
               <option value="gt">&gt;</option>
               <option value="ge">&gt;=</option>
               <option value="lt">&lt;</option>
               <option value="le">&lt;=</option>
               </select></td>' . "\r\n";
               echo '<td><input type="text" id="valu' . $x .'" name="valu' . $x . "\" /></td>\r\n";
               if ($x == 1) {
                 echo "<td></td>\r\n";
               } else {
                 echo '<td><select id="cparen' . $x . '" name="cparen' . $x . "\">\r\n";
                 echo '<option value="no"> </option>' . "\r\n";
                 echo '<option value="yes">)</option>' . "\r\n";
                 echo '</select></td>' . "\r\n";
               }
               if ($x < $MAXCOND) {
                 echo '<td><select id="conj' . $x . '" name="conj' . $x . "\">\r\n";
                 echo '<option value=""></option>';
                 echo '<option value="and">and</option>
                 <option value="or">or</option>
                 </select></td>' . "\r\n";
               } else {
                 echo '<td><input type="submit" id="submitCond" name="submitCond"
                 value="SUBMIT" />' . "\r\n" . "</tr>\r\n";
               }
             }
             ?>
           </table>
         </form>
       </div>

  </div>
  </body>
  </html>
