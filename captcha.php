<?php
session_start();

date_default_timezone_set("UTC");

$font_size = 27;
$nChars = 6;
$angle = 15;
$color = array('R' => 26, 'G' => 26, 'B' => 26);  // dark gray
$bgcolor = array('R' => 255, 'G' => 255, 'B' => 255);  // white
$alphabet = '?#+23456789abcdefhjkmnprstvwxyz';
$font = './LiberationMono-Regular.ttf';
$margin = 5;
$lines = mt_rand(15, 40);
$dots = mt_rand(20, 70);


$challenge = '';
for ($i=0; $i<$nChars; $i++) {
	$challenge .= substr($alphabet, mt_rand(0, strlen($alphabet)-1),	1);
}
$bbox = imagettfbbox($font_size, $angle, $font, $challenge);
$width = $bbox[2] - $bbox[1] + 2 * $margin;
$height = $bbox[1] - $bbox[5] + 2 * $margin;

$rotate = mt_rand(0, 1) === 0 ? $angle : 360 - $angle;

$image = @imagecreate($width, $height);

$xStart = $margin;
if ($rotate === $angle) {
	$yStart = $height - $margin;
} else {
	$yStart = $font_size + $margin;
}

imagecolorallocate($image, $bgcolor['R'], $bgcolor['G'], $bgcolor['B']);
$color = imagecolorallocate($image, $color['R'], $color['G'], $color['B']);

/* Random lines */
for( $count=0; $count<$lines; $count++ ) {
	imageline($image, mt_rand(0, $width), mt_rand(0, $height),
	mt_rand(0, $width), mt_rand(0, $height), $color);
}
/* Random dots */
for( $count=0; $count<$dots; $count++ ) {
	imagefilledellipse($image, mt_rand(0, $width), mt_rand(0, $height), 2,	3,
	$color);
}

imagettftext($image, $font_size, $rotate, $xStart, $yStart, $color, $font,
	$challenge);

header('Content-Type: image/jpeg');
imagejpeg($image);
imagedestroy($image);
$_SESSION['captcha'] = $challenge;

?>
