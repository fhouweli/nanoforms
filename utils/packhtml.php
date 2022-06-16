<?php

if ($argc != 3) {
  echo 'Usage: php ' . $argv[0] . " input_html_file output_html_file\r\n";
  exit;
}

$dirName = dirname($argv[1]);

$h = fopen($argv[1], 'r') or die("Unable to open input file!\r\n");
$html = fread($h, filesize($argv[1]));
fclose($h);

$matches = $submatches = $subsubmatches = array();
$ptr = 0;
$counters = array('js'=>0, 'css'=>0, 'img'=>0);
while (preg_match('/<(img|link|script )[^>]+(>)/i', $html, $matches,
PREG_OFFSET_CAPTURE, $ptr)) {
  $ptr = $matches[0][1] + strlen($matches[0][0]);
  if (preg_match('/\s+(?:href|src)\s*=\s*(\"|\')/i', $matches[0][0],
   $submatches, PREG_OFFSET_CAPTURE)) {
    $q = preg_quote($submatches[1][0]);
    if (preg_match("/$q([^$q]+)+$q/", $matches[0][0], $subsubmatches,
    PREG_OFFSET_CAPTURE)) {
      $fileName = $subsubmatches[1][0];
      if ($dirName != ".") {
        $fileName = $dirName . DIRECTORY_SEPARATOR . $fileName;
      }
      if (file_exists($fileName) && $h = fopen($fileName, "r")) {
        $mimeType = mime_content_type($fileName);
        $replacement = fread($h, filesize($fileName));
        fclose($h);
        $extension = substr(strrchr($fileName, "."), 1);
        $doReplace = true;
        if (strtolower($matches[1][0]) == 'script ' &&
        strtolower($extension) == 'js') {
          $replacement = "\r\n<script>\r\n" . $replacement. "\r\n";
          $counters['js']++;
        } elseif (strtolower($matches[1][0]) == 'link' &&
        strtolower($extension) == 'css') {
          $replacement = "\r\n<style>\r\n" . $replacement. "\r\n</style>\r\n";
          $counters['css']++;
        } elseif (strtolower($matches[1][0]) == 'img') {
          // Need to leave other attributes in place - replace filename only
          $replacement = substr($matches[0][0], 0, $subsubmatches[0][1] + 1) .
          'data:' . $mimeType . ';base64,' . base64_encode($replacement) .
          substr($matches[0][0], $subsubmatches[0][1] +
          strlen($subsubmatches[1][0]) + 1) .
          "\r\n";
          $counters['img']++;
        } else {
          $doReplace = false;
        }
        if ($doReplace) {
          echo sprintf('%6d', $matches[0][1]) . ": " . $matches[0][0]. "\r\n";
          $head = substr($html, 0, $matches[0][1] - 1);
          $tail = substr($html, $matches[0][1] + strlen($matches[0][0]));
          $html = $head . $replacement;
          $ptr = strlen($html);
          $html .= $tail;
        }
      }
    }
  }
}

$h = fopen($argv[2], 'w') or die("Unable to open output file!\r\n");
fwrite($h, $html);
fclose($h);

echo $counters['js'] . ' script, ' . $counters['css'] . ' link, ' .
$counters['img'] . ' img processed.' . "\r\n"

?>
