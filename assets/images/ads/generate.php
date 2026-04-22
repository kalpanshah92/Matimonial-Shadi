<?php
// Generate sample ad and sponsor images
// Run once: php generate.php

$images = [
    ['ad-left.jpg', 160, 600, [192, 57, 43], 'Ad Space'],
    ['ad-right.jpg', 160, 600, [44, 62, 80], 'Ad Space'],
    ['../sponsors/sponsor1.jpg', 400, 220, [255, 215, 0], 'Sponsor 1'],
    ['../sponsors/sponsor2.jpg', 400, 220, [192, 57, 43], 'Sponsor 2'],
    ['../sponsors/sponsor3.jpg', 400, 220, [44, 62, 80], 'Sponsor 3'],
];

foreach ($images as [$file, $w, $h, $bg, $label]) {
    $img = imagecreatetruecolor($w, $h);
    $bgColor = imagecolorallocate($img, $bg[0], $bg[1], $bg[2]);
    $white = imagecolorallocate($img, 255, 255, 255);
    $dark = imagecolorallocate($img, 50, 50, 50);
    imagefill($img, 0, 0, $bgColor);
    
    // Border
    imagerectangle($img, 2, 2, $w-3, $h-3, $white);
    
    // Text
    $textColor = ($bg[0] > 200) ? $dark : $white;
    $fontSize = 4;
    $tw = imagefontwidth($fontSize) * strlen($label);
    imagestring($img, $fontSize, ($w - $tw) / 2, $h / 2 - 20, $label, $textColor);
    
    $sub = 'Your Ad Here';
    $sw = imagefontwidth($fontSize) * strlen($sub);
    imagestring($img, $fontSize, ($w - $sw) / 2, $h / 2 + 5, $sub, $textColor);
    
    $dim = "{$w}x{$h}";
    $dw = imagefontwidth(2) * strlen($dim);
    imagestring($img, 2, ($w - $dw) / 2, $h / 2 + 30, $dim, $textColor);
    
    imagejpeg($img, __DIR__ . '/' . $file, 90);
    imagedestroy($img);
    echo "Created: $file\n";
}
echo "Done!\n";
