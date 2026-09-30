<?php
/**
 * Pouzdan upload slike preko same skripte sajta (ne cPanel Fileman, koji je
 * tiho padao). POST: path (images/products/<ime>.jpg) + data (base64 slike).
 * Dekodira, upisuje, pravi .webp i thumbnail. Samo images/products/.
 * Pristup: deploy token. GET ?ping=1 vraca "pong" (provjera da je ziv).
 */
$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
if (!hash_equals('697113eed3779e99c7bcbf5953fecc2f541250ef', $token)) { http_response_code(403); die('403'); }
header('Content-Type: text/plain; charset=utf-8');
if (isset($_GET['ping'])) { echo 'pong gd=' . (function_exists('imagecreatetruecolor') ? '1' : '0'); exit; }

$root = dirname(__DIR__);
$path = (string) ($_POST['path'] ?? '');
$data = (string) ($_POST['data'] ?? '');

if (!preg_match('#^images/products/[A-Za-z0-9._\-]+\.(jpe?g|png|webp)$#', $path)) { die('GRESKA: nedozvoljen path: ' . $path); }
$bin = base64_decode($data, true);
if ($bin === false || strlen($bin) < 200) { die('GRESKA: neispravan base64 (' . strlen($bin) . 'b)'); }

$full = $root . '/' . $path;
if (@file_put_contents($full, $bin, LOCK_EX) === false) { die('GRESKA: ne mogu da upisem ' . $path); }

// .webp pored originala
$napravljeno = [];
if (function_exists('imagewebp') && preg_match('/\.(jpe?g|png)$/i', $path)) {
    $wp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $full);
    $im = @imagecreatefromstring($bin);
    if ($im) { if (@imagewebp($im, $wp, 82)) $napravljeno[] = 'webp'; imagedestroy($im); }
}
// thumbnail (~700px) + njegov webp
if (function_exists('imagecreatetruecolor')) {
    $thumbDir = $root . '/images/products/thumbs/';
    if (!is_dir($thumbDir)) @mkdir($thumbDir, 0755, true);
    $thumb = $thumbDir . preg_replace('/\.(jpe?g|png|webp)$/i', '.jpg', basename($path));
    $src = @imagecreatefromstring($bin);
    if ($src) {
        $w = imagesx($src); $h = imagesy($src); $mx = 700;
        $r = $w > $mx ? $mx / $w : 1;
        $nw = max(1, (int)round($w * $r)); $nh = max(1, (int)round($h * $r));
        $dst = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        if (@imagejpeg($dst, $thumb, 80)) $napravljeno[] = 'thumb';
        if (function_exists('imagewebp')) { $tw = preg_replace('/\.jpg$/', '.webp', $thumb); if (@imagewebp($dst, $tw, 80)) $napravljeno[] = 'thumb-webp'; }
        imagedestroy($src); imagedestroy($dst);
    }
}

echo 'OK ' . strlen($bin) . 'b  ' . $path . '  [' . implode(',', $napravljeno) . ']';
