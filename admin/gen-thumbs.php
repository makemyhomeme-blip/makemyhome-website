<?php
/**
 * Make My Home – Generisanje THUMBNAIL-a za kartice/liste.
 * Za svaku sliku u images/products/ pravi sitnu verziju u
 * images/products/thumbs/<isto ime> (~700px) + WebP twin.
 * Velike (originalne) slike ostaju netaknute — one su za stranicu proizvoda i zumiranje.
 * Web: /admin/gen-thumbs.php?key=mkhthumb2025          (probni mod — nista se ne mijenja)
 *      /admin/gen-thumbs.php?key=mkhthumb2025&apply=1   (stvarno napravi thumbnaile)
 * Idempotentno: preskace thumb koji je noviji od originala.
 */
require_once __DIR__ . '/sesija.php';
if (empty($_SESSION['admin_logged'])) {
    http_response_code(403);
    die('Pristup odbijen – moras biti prijavljen kao admin.');
}
if (($_GET['key'] ?? '') !== 'mkhthumb2025') {
    die('Pogresan kljuc. Dodaj ?key=mkhthumb2025 u URL.');
}

$root     = dirname(__DIR__);
$srcDir   = $root . '/images/products/';
$thumbDir = $srcDir . 'thumbs/';
$dryRun   = ($_GET['apply'] ?? '') !== '1';
$maxW     = 700;
$maxH     = 800;
$qJpg     = 80;
$qWebp    = 78;

if (!$dryRun && !is_dir($thumbDir)) mkdir($thumbDir, 0755, true);

$gdCreate = [
    'image/jpeg' => 'imagecreatefromjpeg',
    'image/png'  => 'imagecreatefrompng',
    'image/webp' => 'imagecreatefromwebp',
];

echo '<pre style="font-family:monospace;font-size:13px;padding:20px;background:#111;color:#eee;min-height:100vh;">';
echo "=== Generisanje thumbnail-a (kartice/liste) ===\n";
echo $dryRun
    ? "\nMOD: PROBNI – nista se ne pravi. Dodaj &apply=1 da stvarno napravis.\n\n"
    : "\nMOD: PRIMJENA – thumbnaili se prave u images/products/thumbs/.\n\n";

$files = glob($srcDir . '*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
$made = 0; $skip = 0; $err = 0;

foreach ($files as $full) {
    $name = basename($full);
    if (strpos($name, '.') === 0) continue;
    // preskoci vec-webp twin glavne slike? Ne — pravimo thumb i za jpg i za png.
    $thumbFull = $thumbDir . preg_replace('/\.(jpe?g|png|webp)$/i', '.jpg', $name);
    // idempotentno: postoji i noviji je od originala
    if (is_file($thumbFull) && filemtime($thumbFull) >= filemtime($full)) { $skip++; continue; }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($full);
    if (!isset($gdCreate[$mime])) { echo "PRESKOCENO (format $mime): $name\n"; $skip++; continue; }

    $src = @$gdCreate[$mime]($full);
    if (!$src) { echo "GRESKA citanja: $name\n"; $err++; continue; }
    $sW = imagesx($src); $sH = imagesy($src);
    $ratio = min($maxW / $sW, $maxH / $sH, 1.0); // nikad ne uvecavaj
    $dW = max(1, (int)round($sW * $ratio));
    $dH = max(1, (int)round($sH * $ratio));

    if ($dryRun) {
        printf("[BI] %-40s %dx%d -> %dx%d\n", $name, $sW, $sH, $dW, $dH);
        imagedestroy($src); $made++; continue;
    }

    $dst = imagecreatetruecolor($dW, $dH);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $dW, $dH, $sW, $sH);
    imagedestroy($src);

    $okJpg  = @imagejpeg($dst, $thumbFull, $qJpg);
    $webpFull = preg_replace('/\.jpg$/', '.webp', $thumbFull);
    $okWebp = function_exists('imagewebp') ? @imagewebp($dst, $webpFull, $qWebp) : false;
    imagedestroy($dst);

    if ($okJpg) { printf("[OK] %-40s -> %dx%d  (webp:%s)\n", $name, $dW, $dH, $okWebp ? 'da' : 'ne'); $made++; }
    else { echo "GRESKA snimanja: $name\n"; $err++; }
}

echo "\nNapravljeno/za pravljenje: $made | Preskoceno (vec postoji): $skip | Greske: $err\n";
echo "Ukupno slika u images/products/: " . count($files) . "\n";
echo "</pre>";
