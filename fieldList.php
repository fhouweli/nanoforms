<?php

// returns value of item="value" string
function quotedString($lefthand, $html, $withOffset = 0) {
  $retVal = '';
  $offset = -1;
  if (preg_match("|\s$lefthand\s*=|i", $html)) {
    if (preg_match("/\s$lefthand\s*=\s*(\'|\")/i", $html, $quoMatch)) {
      $q = $quoMatch[1];
      if (preg_match("/\s$lefthand\s*=\s*$q([^$q]+)$q/i", $html, $submatch,
      PREG_OFFSET_CAPTURE)) {
        $retVal = $submatch[1][0];
        $offset = (int) $submatch[1][1];
      }
    }
  }
  if ($withOffset) {
    return array('value' => $retVal, 'offset' => $offset);
  } else {
    return $retVal;
  }
}

function fieldList($html) {
  // first make inventory of datalists
  $listsArr = array();
  $nListMatches = preg_match_all('|<datalist(\s[^>]*?)>.*?</datalist\s*?>|i',
  $html, $listMatches);
  for ($m = 0; $m < $nListMatches; $m++) {
    $optsArr = array();
    $wholeList = $listMatches[0][$m];
    $listAttribs = $listMatches[1][$m];
    $listID = quotedString('id', $listAttribs);
    if (!$listID) continue;
    $nOptMatches = preg_match_all('|<option[^>]+value\s*=\s*[^>]+>|i',
    $wholeList, $submatch);
    for ($o = 0; $o < $nOptMatches; $o++) {
      $option = $submatch[0][$o];
      if (($valu = quotedString('value', $option))) {
        $optsArr[] = $valu;
      }
    }
    $listsArr[$listID] = $optsArr;
  }
  // now just sequentially
  $fieldArr = array();
  $currentFormID = '';
  while (preg_match('/<(form|\/form|input|textarea|select|button)[^>]*?>/i',
  $html, $matches, PREG_OFFSET_CAPTURE)) {
    $whole = $matches[0][0];
    $start = (int) $matches[0][1];
    $next = $start + strlen($whole);
    $html = substr($html, $next);
    $tagType = strtolower($matches[1][0]);
    $name = quotedString('name', $whole);
    $ID = quotedString('id', $whole);
    if ($tagType == 'form') {
      $currentFormID = $ID;
      continue;
    } elseif ($tagType == '/form') {
      $currentFormID = '';
      continue;
    }
    $formID = $currentFormID;
    if (quotedString('form', $whole) != '') {
      $formID = quotedString('form', $whole);
    }
    $inputType = quotedString('type', $whole);
    // get value or value list
    $values = array();
    $currentValue = '';
    if (($listID = quotedString('list', $whole)) != '') {
      if (isset($listArr[$listID])) {
        $values = $listArr[$listID];
      }
    } elseif (quotedString('value', $whole) != '') {
      $values[] = quotedString('value', $whole);
      if (in_array($inputType, array('text', 'button')) ||
      $tagType == 'textarea' || preg_match('/\schecked/i', $whole)) {
        $currentValue = end($values);
      }
    } elseif (in_array($inputType, array('checkbox', 'radio'))) {
      $values[] = 'on';
      if (preg_match('/\schecked/i', $whole)) {
        $currentValue = 'on';
      }
    } elseif ($tagType == 'select') {
      $optsArr = array();
      if (preg_match('|^(.*?)</select\s*>|i', $whole, $submatch)) {
        $subhtml = $submatch[1];
        $nOptMatches = preg_match_all('|<option([^>]*?)>(.*?)</option|i',
        $subhtml, $submatch);
        for ($o = 0; $o < $nOptMatches; $o++) {
          $optionAttrs = $submatch[1][$o];
          $optionTxt = $submatch[2][$o];
          if (($valu = quotedString('value', $optionAttrs)) != '') {
            $optsArr[] = $valu;
          } else {
            $optsArr[] = $optionTxt;
          }
          if (preg_match('|\sselected|i', $optionAttrs)) {
            $currentValue = end($optsArr);
          }
        }
      }
    }
    if ($name) {
      $found = false;
      foreach ($fieldArr as &$fArr) {
        if ($fArr['name'] == $name) {
          $found = true;
          foreach ($values as $val) {
            if (!in_array($val, $fArr['values'])) {
              $fArr['values'][] = $val;
              if ($currentValue != '') {
                $fArr['currentValue'] = $currentValue;
              }
            }
          }
          break;
        }
      }
      if (!$found) {
        $fieldArr[] = array(
          'name' =>  $name,
          'formID' => $formID,
          'element' =>  $tagType,
          'inputType' => $inputType,
          'values' => $values,
          'currentValue' => $currentValue
        );
      }
    }  // have a name
  } // while form element
  return $fieldArr;
}

// presets value of one or more form elements with name $name
function setHtmlVal($html, $name, $valu) {
  $next = 0;
  while (preg_match('!<(input|textarea|select|button)[^>]*?\sname\s*?=\s*?(?:\'|")' .
  $name . '(?:\'|")[^>]*?(/>|>)!i', $html, $matches, PREG_OFFSET_CAPTURE, $next)) {
    $whole = $matches[0][0];
    $start = (int) $matches[0][1];
    $elem = strtolower($matches[1][0]);
    $slash = ($matches[2][0] == '/>');
    $next = $start + strlen($whole);
    if ($elem == 'input') {
      if (preg_match('/\stype\s*=/i', $whole)) {
        $inputType = strtolower(quotedString('type', $whole));
      } else {
        $inputType = 'text';
      }
      if (preg_match('/\svalue\s*=/i', $whole)) {
        $quotArr = quotedString('value', $whole, 1);
        $valStart = $quotArr['offset'];
        $currentValue = $quotArr['value'];
        $valNext = $valStart + strlen($currentValue);
        if (!in_array($inputType, array('checkbox', 'radio'))) {
          // one of  array('text', 'password', 'button', 'color', 'date',
          // 'datetime-local', 'email', 'file', 'month', 'number', 'range',
          // 'search', 'tel', 'time', 'url', 'week')
          if (strcasecmp($currentValue, $valu)) {
            $whole = substr($whole, 0, $valStart) . $valu .
            substr($whole, $valEnd);
          }
        } else {
          if (!strcasecmp($currentValue, $valu)) {
            if (!preg_match('/\schecked/i', $whole)) {
              if ($slash) {
                $whole = substr($whole, 0, strlen($whole) - 2) . ' checked />';
              } else {
                $whole = substr($whole, 0, strlen($whole) - 1) . ' checked>';
              }
            }
          } else {
            preg_replace('/\schecked/i', '', $whole);
          }
        }
      } else {   // no value=
        if (!in_array($inputType, array('checkbox', 'radio'))) {
          if (strpos($valu, '"') !== false) {
            $tail = " value='" . $valu . "'";
          } else {
            $tail = ' value="' . $valu . '"';
          }
          if ($slash) {
            $whole = substr($whole, 0, strlen($whole) - 2) . $tail . ' />';
          } else {
            $whole = substr($whole, 0, strlen($whole) - 1) . $tail . ' >';
          }
        } else {
          if (!strcasecmp($valu, 'on')) {
            if (!preg_match('/\schecked/i', $whole)) {
              $whole = substr($whole, 0, strlen($whole) - 1) . ' checked>';
            }
          } else {
            preg_replace('/\schecked/i', '', $Whole);
          }
        }
      }
    } elseif ($elem == 'select') {
      if (($pos = strripos($html, '</select', $next)) !== false) {
        $ops = substr($html, $next, $pos - $next);
        $opNext = 0;
        while (preg_match('|<option[^>]*>([^<]+)</option\s*>|i', $ops,
        $opmatches, PREG_OFFSET_CAPTURE, $opNext)) {
          $op = $opmatches[0][0];
          $opStart = (int) $opmatches[0][1];
          $opText = $opmatches[1][0];
          $opTextStart = $opmatches[1][1];
          $opNext = $opStart + strlen($op);
          if (preg_match('/\svalue\s*=/i', $op)) {
            $optArr = quotedString('value', $op, 1);
            $currentValue = $optArr['value'];
          } else {
            $currentValue = $opText;
          }
          if (!strcasecmp($valu, $currentValue)) {
            if (!preg_match('/\sselected/i', $op)) {
              $op = substr($op, 0, strpos($op, '>')) . ' selected' .
              substr($op, strpos($op, '>'));
            }
          } else {
            $op = preg_replace('/\sselected/i', '', $op);
          }
          $ops = substr($ops, 0, $opStart) . $op . substr($ops, $opNext);
        }
        $whole = $whole . $ops;
        $next = $pos;
      }
    } elseif ($elem == 'textarea') {
      if (($pos = strripos($html, '</textarea', $next)) !== false) {
        $subhtml = substr($html, $next, $pos - $next);
        if (strcasecmp($valu, $subhtml)) {
          $whole .= "\n" . $valu . "\n";
        } else {
          $whole .= $subhtml;
        }
        $next = $pos;
      }
    }
    $html = substr($html, 0, $start) . $whole . substr($html, $next);
  }  // while
  return $html;
}

?>
