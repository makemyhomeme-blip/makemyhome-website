<?php
/**
 * Make My Home – Optimizacija glavnih slika proizvoda (smanji veličinu fajla)
 * Diraju se SAMO glavne slike (polje "image"). Originali se čuvaju u backup folderu.
 * Web: /admin/optimize-main-images.php?key=mkhimgopt2025 (probni mod, ništa se ne mijenja)
 *      dodaj &apply=1 da stvarno primijeniš izmjene (čuva se backup originala)
 */
session_start();
if (empty($_SESSION['admin_logged'])) {
    http_response_code(403);
    die('Pristup odbijen – mora si prijavljen kao admin.');
}
if (($_GET['key'] ?? '') !== 'mkhimgopt2025') {
    die('Pogre&scaron;an klju&ccaron;. Dodaj ?key=mkhimgopt2025 u URL.');
}

$root         = dirname(__DIR__);
$productsFile = $root . '/data/products.json';
$products     = json_decode(@file_get_contents($productsFile), true) ?: [];

// Glavne slike (polje "image") - jedine koje ovaj alat dira
$toProcess = [];
foreach ($products as $p) {
    if (!empty($p['image'])) $toProcess[$p['image']] = true;
}

$dryRun           = ($_GET['apply'] ?? '') !== '1';
$maxW             = 1920;
$maxH             = 1440;
$quality          = 87;
$minSizeToProcess = 150 * 1024; // ispod ovoga se ne dira (već dovoljno male)

$backupDir = $root . '/images/products-backup/';
if (!$dryRun && !is_dir($backupDir)) mkdir($backupDir, 0755, true);

echo '<pre style="font-family:monospace;font-size:13px;padding:20px;background:#111;color:#eee;min-height:100vh;">';
echo "=== Optimizacija glavnih slika proizvoda ===\n";
echo "Kandidata za obradu (glavne slike): " . count($toProcess) . "\n";
echo $dryRun
    ? "\nMOD: PROBNI (dry-run) – ništa se ne mijenja na disku.\nDodaj &amp;apply=1 u URL da stvarno primijeniš izmjene.\n\n"
    : "\nMOD: PRIMJENA – slike se trajno mijenjaju (originali se čuvaju u images/products-backup/).\n\n";

$gdCreate = [
    'image/jpeg' => 'imagecreatefromjpeg',
    'image/jpg'  => 'imagecreatefromjpeg',
    'image/png'  => 'imagecreatefrompng',
    'image/webp' => 'imagecreatefromwebp',
];

$totalBefore = 0;
$totalAfter  = 0;
$count       = 0;
$skipped     = 0;

foreach (array_keys($toProcess) as $relPath) {
    $fullPath = $root . '/' . $relPath;
    if (!file_exists($fullPath)) {
        echo "PRESKOČENO (fajl ne postoji): $relPath\n";
        continue;
    }
    $sizeBefore = filesize($fullPath);
    if ($sizeBefore < $minSizeToProcess) {
        $skipped++;
        continue;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($fullPath);
    if (!isset($gdCreate[$mime])) {
        echo "PRESKOČENO (nepoznat format $mime): $relPath\n";
        continue;
    }

    $src = @$gdCreate[$mime]($fullPath);
    if (!$src) {
        echo "GREŠKA (čitanje slike): $relPath\n";
        continue;
    }
    $srcW = imagesx($src);
    $srcH = imagesy($src);
    $ratio = min($maxW / $srcW, $maxH / $srcH, 1.0); // nikad ne uvećavaj
    $dstW  = max(1, (int)round($srcW * $ratio));
    $dstH  = max(1, (int)round($srcH * $ratio));
    $dst   = imagecreatetruecolor($dstW, $dstH);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
    imagedestroy($src);

    if ($dryRun) {
        $tmp = tempnam(sys_get_temp_dir(), 'opt');
        imagejpeg($dst, $tmp, $quality);
        $sizeAfter = filesize($tmp);
        unlink($tmp);
    } else {
        copy($fullPath, $backupDir . basename($fullPath));
        imagejpeg($dst, $fullPath, $quality);
        $sizeAfter = filesize($fullPath);
    }
    imagedestroy($dst);

    $totalBefore += $sizeBefore;
    $totalAfter  += $sizeAfter;
    $count++;
    $pct = $sizeBefore > 0 ? round((1 - $sizeAfter / $sizeBefore) * 100) : 0;
    printf("%-55s %6d KB -> %6d KB  (-%d%%)  %dx%d -> %dx%d\n", $relPath, $sizeBefore / 1024, $sizeAfter / 1024, $pct, $srcW, $srcH, $dstW, $dstH);
}

echo "\nObrađeno: $count slika | Preskočeno (već male): $skipped\n";
if ($count > 0) {
    $savedMb = ($totalBefore - $totalAfter) / 1024 / 1024;
    $pctTotal = round((1 - $totalAfter / $totalBefore) * 100);
    printf("Ukupno: %.1f MB -> %.1f MB  (ušteda %.1f MB, -%d%%)\n", $totalBefore / 1024 / 1024, $totalAfter / 1024 / 1024, $savedMb, $pctTotal);
}
echo "</pre>";
