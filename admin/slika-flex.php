<?php
/**
 * Postavljanje SLIKA na Flex Stone proizvode iz data/flex-slike.json (po sifri).
 *
 * Radi na SERVERU nad ZIVIM products.json. Postavlja "image" (glavna) i
 * "gallery" na proizvod cija sifra postoji u mapi. Glavnu sliku postavlja SAMO
 * ako je prazna (da ne prepise sliku koju je vlasnik vec dodao). Za svaku sliku
 * napravi thumbnail (images/products/thumbs/) i .webp, kao admin pri uploadu.
 *
 * Backup prije upisa. ?dry=1 pregled. Pristup: samo deploy token.
 */
$token = (string) ($_GET['token'] ?? '');
if (!hash_equals('697113eed3779e99c7bcbf5953fecc2f541250ef', $token)) { http_response_code(403); die('403'); }
header('Content-Type: text/plain; charset=utf-8');
$dry = isset($_GET['dry']);

$root = dirname(__DIR__);
$put  = $root . '/data/products.json';
$mapF = $root . '/data/flex-slike.json';

$map = json_decode(@file_get_contents($mapF), true);
if (!is_array($map)) { die('GRESKA: ne mogu procitati data/flex-slike.json'); }

$sirovo = @file_get_contents($put);
$P = json_decode($sirovo, true);
if (!is_array($P)) { die('GRESKA: ne mogu procitati products.json'); }
$flat = isset($P['products']) ? $P['products'] : $P;

// ---- pomocne: thumbnail + webp (isto kao admin) ----
function fsWebp($jpg, $q = 82) {
    $wp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $jpg);
    if ($wp === $jpg || !function_exists('imagewebp') || !is_file($jpg)) return;
    if (is_file($wp) && filemtime($wp) >= filemtime($jpg)) return; // vec napravljen
    $im = @imagecreatefromjpeg($jpg);
    if ($im) { @imagewebp($im, $wp, $q); imagedestroy($im); }
}
function fsThumb($root, $rel, $q = 80, $maxW = 700) {
    $full = $root . '/' . $rel;
    if (!function_exists('imagecreatetruecolor') || !is_file($full)) return;
    $thumbDir = $root . '/images/products/thumbs/';
    if (!is_dir($thumbDir)) @mkdir($thumbDir, 0755, true);
    $thumb = $thumbDir . preg_replace('/\.(jpe?g|png|webp)$/i', '.jpg', basename($rel));
    if (is_file($thumb) && filemtime($thumb) >= filemtime($full)) { fsWebp($thumb, $q); return; } // vec napravljen
    $info = @getimagesize($full); if (!$info) return;
    $src = $info[2] === IMAGETYPE_PNG ? @imagecreatefrompng($full) : @imagecreatefromjpeg($full);
    if (!$src) return;
    $w = imagesx($src); $h = imagesy($src);
    $r = $w > $maxW ? $maxW / $w : 1;
    $nw = (int)round($w * $r); $nh = (int)round($h * $r);
    $dst = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagejpeg($dst, $thumb, $q);
    imagedestroy($src); imagedestroy($dst);
    fsWebp($thumb, $q);
}

$promjene = []; $slike = [];
foreach ($flat as $idx => $p) {
    $s = strtoupper(trim((string)($p['sku'] ?? '')));
    if ($s === '' || !isset($map[$s])) continue;
    $d = $map[$s];
    if (!empty($d['image']) && trim((string)($p['image'] ?? '')) === '') {
        $flat[$idx]['image'] = $d['image'];
        $slike[] = $d['image'];
        $promjene[] = "ID {$p['id']}  $s  glavna: {$d['image']}";
    }
    if (array_key_exists('gallery', $d) && is_array($d['gallery'])) {
        $flat[$idx]['gallery'] = array_values($d['gallery']);
        foreach ($d['gallery'] as $g) $slike[] = $g;
        $promjene[] = "ID {$p['id']}  $s  galerija: " . count($d['gallery']) . " sl."
                    . (count($d['gallery']) === 0 ? "  (ocisceno)" : "");
    }
}

echo $dry ? "=== PREGLED ===\n" : "=== UPIS ===\n";
echo "Izmjena: " . count($promjene) . "\n";
foreach ($promjene as $r) echo "  $r\n";
if ($dry) { echo "\n(dry-run)\n"; exit; }
if (!$promjene) { echo "\nNista (sifra nije nadjena ili glavna vec postoji).\n"; exit; }

// 1) UPIS products.json PRVO (kriticno) — da ne zavisi od sporog pravljenja
// thumbnaila. Ranije se products.json upisivao TEK posle svih thumbnaila, pa
// kad bi zahtjev istekao usred thumbnaila, galerije se ne bi sacuvale.
$bkp = $root . '/data/products.backup-slike-' . date('Ymd-His') . '.json';
@file_put_contents($bkp, $sirovo);
$out = json_encode(array_values($flat), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$tmp = $put . '.tmp';
if (@file_put_contents($tmp, $out, LOCK_EX) !== false && @rename($tmp, $put)) {
    echo "\nUPISANO. Backup: " . basename($bkp) . "\n";
} else {
    echo "\nGRESKA pri upisu!\n"; exit;
}

// 2) thumbnail + webp (best-effort, preskace vec napravljene). Ako zahtjev
// istekne ovdje, products.json je vec sacuvan; ponovni poziv dovrsi ostatak.
@set_time_limit(0);
echo "Pravim thumbnaile...\n"; @ob_flush(); @flush();
$tn = 0;
foreach (array_unique($slike) as $rel) { fsThumb($root, $rel); fsWebp($root . '/' . $rel); $tn++; }
echo "Thumbnaili gotovi ($tn).\n";
