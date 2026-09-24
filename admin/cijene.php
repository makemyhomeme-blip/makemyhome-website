<?php
/**
 * Jednokratna izmjena CIJENA i POPUSTA u data/products.json — po SIFRI.
 *
 * Radi na SERVERU nad ZIVIM products.json (izvor istine), mijenja SAMO polja
 * "price" i "discount" za navedene sifre, i NISTA drugo (slike, galerije, opisi
 * ostaju netaknuti). Prije upisa pravi backup. Ima ?dry=1 za pregled bez upisa.
 *
 * Pristup: samo deploy token (kao sync).
 */
$token = (string) ($_GET['token'] ?? '');
if (!hash_equals('697113eed3779e99c7bcbf5953fecc2f541250ef', $token)) { http_response_code(403); die('403'); }
header('Content-Type: text/plain; charset=utf-8');
$dry = isset($_GET['dry']);

$root = dirname(__DIR__);
$put  = $root . '/data/products.json';

// ---- STA MIJENJAMO (po sifri, velika slova) ----
$novaCijena = []; // prazno: izmjene su vec upisane
$noviPopust = []; // prazno: popusti (039/022/052/cs022 20%, 170 M51-01 40%) vec upisani 24.09.2026

$sirovo = @file_get_contents($put);
$P = json_decode($sirovo, true);
if (!is_array($P)) { die('GRESKA: ne mogu procitati products.json'); }
$flat = isset($P['products']) ? $P['products'] : $P;

$promjene = []; $nadjeno = [];
foreach ($flat as $idx => $p) {
    $s = strtoupper((string)($p['sku'] ?? ''));
    if ($s === '') continue;
    if (isset($novaCijena[$s])) {
        $nadjeno[$s] = 1;
        $staro = (string)($p['price'] ?? '');
        if ($staro !== $novaCijena[$s]) {
            $promjene[] = "CIJENA  $s (\"" . ($p['name'] ?? '') . "\"): $staro -> " . $novaCijena[$s];
            $flat[$idx]['price'] = $novaCijena[$s];
        }
    }
    if (isset($noviPopust[$s])) {
        $nadjeno[$s] = 1;
        $staro = $p['discount'] ?? 0;
        if ((int)$staro !== (int)$noviPopust[$s]) {
            $promjene[] = "POPUST  $s (\"" . ($p['name'] ?? '') . "\"): {$staro}% -> " . $noviPopust[$s] . "%";
            $flat[$idx]['discount'] = $noviPopust[$s];
        }
    }
}

// sifre koje nisu nadjene
$sveTrazene = array_merge(array_keys($novaCijena), array_keys($noviPopust));
$nemaIh = array_values(array_diff($sveTrazene, array_keys($nadjeno)));

echo $dry ? "=== PREGLED (dry-run, NISTA se ne upisuje) ===\n" : "=== UPIS ===\n";
echo "Promjena: " . count($promjene) . "\n";
foreach ($promjene as $r) echo "  $r\n";
if ($nemaIh) echo "\nNIJE nadjeno na sajtu: " . implode(', ', $nemaIh) . "\n";

if ($dry) { echo "\n(dry-run — dodaj bez ?dry da upises)\n"; exit; }
if (!$promjene) { echo "\nNema sta da se mijenja (vec je tako).\n"; exit; }

// backup pa atomski upis
$bkp = $root . '/data/products.backup-cijene-' . date('Ymd-His') . '.json';
@file_put_contents($bkp, $sirovo);
$novi = json_encode(array_values($flat), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
$tmp = $put . '.tmp';
if (@file_put_contents($tmp, $novi, LOCK_EX) !== false && @rename($tmp, $put)) {
    echo "\nUPISANO. Backup: " . basename($bkp) . "\n";
} else {
    echo "\nGRESKA pri upisu!\n";
}
