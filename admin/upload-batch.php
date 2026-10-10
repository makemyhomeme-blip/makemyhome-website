<?php
/**
 * Batch upload vise slika u JEDNOM POST-u (da se smanji broj zahtjeva prema
 * Imunify firewall-u koji banuje posle rafala). POST polja: path0,data0,
 * path1,data1, ... (data = base64). Za svaku: upis + .webp + thumbnail.
 * Samo images/products/. Pristup: deploy token. GET ?ping=1 -> pong.
 */
$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
if (!hash_equals('697113eed3779e99c7bcbf5953fecc2f541250ef', $token)) { http_response_code(403); die('403'); }
header('Content-Type: text/plain; charset=utf-8');
if (isset($_GET['ping'])) { echo 'pong gd=' . (function_exists('imagecreatetruecolor') ? '1' : '0'); exit; }

$root = dirname(__DIR__);
$thumbDir = $root . '/images/products/thumbs/';
if (!is_dir($thumbDir)) @mkdir($thumbDir, 0755, true);

$ok = 0; $err = 0; $lines = [];
for ($i = 0; $i < 200; $i++) {
    if (!isset($_POST["path$i"])) continue;
    $path = (string) $_POST["path$i"];
    $data = (string) ($_POST["data$i"] ?? '');
    if (!preg_match('#^images/products/[A-Za-z0-9._\-]+\.(jpe?g|png|webp)$#', $path)) { $lines[] = "ERR path: $path"; $err++; continue; }
    $bin = base64_decode($data, true);
    if ($bin === false || strlen($bin) < 200) { $lines[] = "ERR b64: $path"; $err++; continue; }
    $full = $root . '/' . $path;
    if (@file_put_contents($full, $bin, LOCK_EX) === false) { $lines[] = "ERR write: $path"; $err++; continue; }
    // webp + thumbnail
    if (function_exists('imagecreatefromstring')) {
        $im = @imagecreatefromstring($bin);
        if ($im) {
            if (function_exists('imagewebp') && preg_match('/\.(jpe?g|png)$/i', $path)) {
                @imagewebp($im, preg_replace('/\.(jpe?g|png)$/i', '.webp', $full), 82);
            }
            $w = imagesx($im); $h = imagesy($im); $mx = 700; $r = $w > $mx ? $mx / $w : 1;
            $nw = max(1, (int)round($w * $r)); $nh = max(1, (int)round($h * $r));
            $dst = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($dst, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $thumb = $thumbDir . preg_replace('/\.(jpe?g|png|webp)$/i', '.jpg', basename($path));
            @imagejpeg($dst, $thumb, 80);
            if (function_exists('imagewebp')) @imagewebp($dst, preg_replace('/\.jpg$/', '.webp', $thumb), 80);
            imagedestroy($im); imagedestroy($dst);
        }
    }
    $lines[] = "OK " . strlen($bin) . "b $path";
    $ok++;
}
echo "BATCH OK=$ok ERR=$err\n" . implode("\n", $lines) . "\n";
