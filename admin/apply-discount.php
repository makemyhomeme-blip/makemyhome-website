<?php
/**
 * Make My Home – Masovno postavljanje popusta na kategoriju
 * Postavlja "discount" polje za proizvode iz zadatih kategorija, ALI samo
 * onima koji TRENUTNO nemaju popust (discount === 0) – postojeći popusti se ne diraju.
 * Web: /admin/apply-discount.php?key=mkhdisc2025 (probni mod, ništa se ne mijenja)
 *      dodaj &apply=1 da stvarno primijeniš izmjene (čuva se backup products.json)
 */
require_once __DIR__ . '/sesija.php';
if (empty($_SESSION['admin_logged'])) {
    http_response_code(403);
    die('Pristup odbijen – mora si prijavljen kao admin.');
}
if (($_GET['key'] ?? '') !== 'mkhdisc2025') {
    die('Pogre&scaron;an klju&ccaron;. Dodaj ?key=mkhdisc2025 u URL.');
}

$root         = dirname(__DIR__);
$productsFile = $root . '/data/products.json';
$products     = json_decode(@file_get_contents($productsFile), true) ?: [];

$targetCategories = ['bambus-drveni', 'bambus-tekstilni', 'bambus-mermerni', 'bambus-kozni', 'bambus-metalni', '3d-letvice'];
$newDiscount      = 20;
$revert           = ($_GET['revert'] ?? '') === '1';
$dryRun           = ($_GET['apply'] ?? '') !== '1';

echo '<pre style="font-family:monospace;font-size:13px;padding:20px;background:#111;color:#eee;min-height:100vh;">';
echo $revert
    ? "=== Skidanje popusta ({$newDiscount}%) sa bambus + 3D letvice ===\n"
    : "=== Postavljanje popusta ({$newDiscount}%) na bambus + 3D letvice ===\n";
echo "Kategorije: " . implode(', ', $targetCategories) . "\n";
echo $dryRun
    ? "\nMOD: PROBNI (dry-run) – ništa se ne mijenja na disku.\nDodaj &amp;apply=1 u URL da stvarno primijeniš izmjene.\n\n"
    : "\nMOD: PRIMJENA – products.json se mijenja (backup se čuva u data/).\n\n";

$changed  = 0;
$skipped  = 0;
foreach ($products as &$p) {
    if (!in_array($p['category'] ?? '', $targetCategories, true)) continue;
    $current = (int)($p['discount'] ?? 0);

    if ($revert) {
        if ($current !== $newDiscount) {
            $skipped++;
            printf("PRESKOČENO (popust %d%%, nije %d%% koji smo dodali): #%d %s\n", $current, $newDiscount, $p['id'], $p['name']);
            continue;
        }
        printf("%s #%-4d %-30s %d%% -> 0%%\n", $dryRun ? '[BI]' : '[OK]', $p['id'], $p['name'], $current);
        if (!$dryRun) $p['discount'] = 0;
        $changed++;
        continue;
    }

    if ($current > 0) {
        $skipped++;
        printf("PRESKOČENO (već ima popust %d%%): #%d %s\n", $current, $p['id'], $p['name']);
        continue;
    }
    printf("%s #%-4d %-30s 0%% -> %d%%\n", $dryRun ? '[BI]' : '[OK]', $p['id'], $p['name'], $newDiscount);
    if (!$dryRun) $p['discount'] = $newDiscount;
    $changed++;
}
unset($p);

echo "\nPromijenjeno: $changed | Preskočeno (već imaju popust): $skipped\n";

if (!$dryRun && $changed > 0) {
    // Backup po istom obrascu kao admin/actions.php (max 5 zadrzanih backupa)
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
        echo "\nGREŠKA pri snimanju products.json!\n";
    } else {
        echo "\nSnimljeno u products.json. Backup originala sačuvan.\n";
    }
}
echo "</pre>";
