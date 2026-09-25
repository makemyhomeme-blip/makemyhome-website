<?php
/**
 * Make My Home – Skidanje SVIH popusta osim SPC poda + "tkanje" -> "tekstura"
 * Cita TRENUTNI products.json sa servera (cuva slike/galerije/cijene), mijenja samo:
 *   - discount = 0 za sve proizvode OSIM kategorije 'spc-pod' (SPC pod ostaje 30%)
 *   - imenicu "tkanje/tkanjem" -> "tekstura/teksturom" (gramaticki ispravno)
 * Web: /admin/skini-popuste.php?key=mkhpop2025            (probni mod)
 *      /admin/skini-popuste.php?key=mkhpop2025&apply=1     (stvarno primijeni)
 */
require_once __DIR__ . '/sesija.php';
if (empty($_SESSION['admin_logged'])) {
    http_response_code(403);
    die('Pristup odbijen – moras biti prijavljen kao admin.');
}
if (($_GET['key'] ?? '') !== 'mkhpop2025') {
    die('Pogresan kljuc. Dodaj ?key=mkhpop2025 u URL.');
}

$root         = dirname(__DIR__);
$productsFile = $root . '/data/products.json';
$products     = json_decode(@file_get_contents($productsFile), true) ?: [];
$dryRun       = ($_GET['apply'] ?? '') !== '1';

// tkanje (srednji rod) -> tekstura (zenski rod): mijenja se i pridjev/zamjenica
$REPL = [
    'Krupno tkanje u bijelo-bež tonu'      => 'Krupna tekstura u bijelo-bež tonu',
    'Krupno tkanje na zidu radi nešto'     => 'Krupna tekstura na zidu radi nešto',
    'Hladna siva sa grubim tkanjem'        => 'Hladna siva sa grubom teksturom',
    'iz blizine se vidi tkanje'            => 'iz blizine se vidi tekstura',
    'sa mnogo delikatnijim tkanjem'        => 'sa mnogo delikatnijom teksturom',
    'Svijetla siva sa finim tkanjem'       => 'Svijetla siva sa finom teksturom',
    'nego srednje tkanje koje se vidi'     => 'nego srednja tekstura koja se vidi',
];

echo '<pre style="font-family:monospace;font-size:13px;padding:20px;background:#111;color:#eee;min-height:100vh;">';
echo "=== Skidanje popusta (sve osim SPC pod) + tkanje->tekstura ===\n";
echo $dryRun
    ? "\nMOD: PROBNI (dry-run) – nista se ne mijenja na disku.\nDodaj &apply=1 u URL da stvarno primijenis.\n\n"
    : "\nMOD: PRIMJENA – products.json se mijenja (backup se cuva u data/).\n\n";

$discChanged = 0; $tkChanged = 0; $spcKept = 0;
foreach ($products as &$p) {
    $cat = $p['category'] ?? '';
    // 1) discount
    if ($cat === 'spc-pod') {
        $spcKept++;
    } else {
        $cur = (int)($p['discount'] ?? 0);
        if ($cur !== 0) {
            printf("[popust] #%-4d %-32s %d%% -> 0%%\n", $p['id'] ?? 0, $p['name'] ?? '', $cur);
            if (!$dryRun) $p['discount'] = 0;
            $discChanged++;
        }
    }
    // 2) tkanje -> tekstura
    foreach ($p as $k => $v) {
        if (is_string($v) && stripos($v, 'tkanj') !== false) {
            $nv = strtr($v, $REPL);
            if ($nv !== $v) {
                printf("[tekst]  #%-4d .%s: tkanje -> tekstura\n", $p['id'] ?? 0, $k);
                if (!$dryRun) $p[$k] = $nv;
                $tkChanged++;
            }
        }
    }
}
unset($p);

// koliko je ostalo "tkanj" (kontrola)
$left = 0;
foreach ($products as $p) foreach ($p as $v) if (is_string($v) && stripos($v, 'tkanj') !== false) $left++;

echo "\nPopusta skinuto: $discChanged | SPC pod zadrzano: $spcKept | tkanje sredjeno: $tkChanged | preostalo 'tkanj': $left\n";

if (!$dryRun && ($discChanged > 0 || $tkChanged > 0)) {
    $dir  = dirname($productsFile);
    $base = basename($productsFile, '.json');
    $backups = glob($dir . '/' . $base . '.bak.*.json');
    if (count($backups) >= 5) {
        sort($backups);
        foreach (array_slice($backups, 0, count($backups) - 4) as $old) @unlink($old);
    }
    copy($productsFile, $dir . '/' . $base . '.bak.' . date('YmdHis') . '.json');

    $json = json_encode(array_values($products), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $tmp  = $productsFile . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $productsFile)) {
        echo "\nGRESKA pri snimanju products.json!\n";
    } else {
        echo "\nSnimljeno u products.json. Backup originala sacuvan.\n";
    }
} elseif (!$dryRun) {
    echo "\nNema promjena za snimanje (mozda je vec sredjeno).\n";
}
echo "</pre>";
