<?php
/**
 * Jednokratna zamjena OPISA za Flex Stone proizvode — jedinstveni tekstovi.
 *
 * Radi na SERVERU nad ZIVIM products.json (izvor istine). Mijenja SAMO polje
 * "description" za ID-eve iz data/flex-opisi.json — slike, cijene, galerije,
 * osobine i svi ostali proizvodi ostaju NETAKNUTI. Prije upisa pravi backup.
 * Ima ?dry=1 za pregled bez upisa.
 *
 * Zasto: 42/43 Flex Stone proizvoda imala su skoro identican (sablonski) opis
 * (npr. Milan i Roman Travertine Yellow = 99% isti tekst), pa ih je Google
 * tretirao kao duplikate i nije indeksirao. Ovi opisi su jedinstveni (<44%
 * medjusobne slicnosti).
 *
 * Pristup: samo deploy token (kao sync).
 */
$token = (string) ($_GET['token'] ?? '');
if (!hash_equals('697113eed3779e99c7bcbf5953fecc2f541250ef', $token)) { http_response_code(403); die('403'); }
header('Content-Type: text/plain; charset=utf-8');
$dry = isset($_GET['dry']);

$root = dirname(__DIR__);
$opisi = json_decode(@file_get_contents($root . '/data/flex-opisi.json'), true);
if (!is_array($opisi) || !$opisi) { die('GRESKA: ne mogu procitati data/flex-opisi.json'); }

$put = $root . '/data/products.json';
$sirovo = @file_get_contents($put);
$P = json_decode($sirovo, true);
if (!is_array($P)) { die('GRESKA: ne mogu procitati products.json'); }
$flat = isset($P['products']) ? $P['products'] : $P;

$promjene = []; $nadjeno = []; $isti = 0;
foreach ($flat as $i => $p) {
    $id = (string)($p['id'] ?? '');
    if ($id === '' || !isset($opisi[$id])) continue;
    $nadjeno[$id] = 1;
    $novo = trim((string)$opisi[$id]);
    if (trim((string)($p['description'] ?? '')) === $novo) { $isti++; continue; }
    $flat[$i]['description'] = $novo;
    $promjene[] = "ID $id  \"" . ($p['name'] ?? '') . "\"  ->  " . mb_substr($novo, 0, 60) . '…';
}
$nemaIh = array_values(array_diff(array_keys($opisi), array_keys($nadjeno)));

echo $dry ? "=== PREGLED (dry-run, NISTA se ne upisuje) ===\n" : "=== UPIS ===\n";
echo "Opisa u fajlu: " . count($opisi) . " | nadjeno na sajtu: " . count($nadjeno)
   . " | mijenjam: " . count($promjene) . " | vec isto: $isti\n\n";
foreach ($promjene as $r) echo "  $r\n";
if ($nemaIh) echo "\nNIJE nadjen ID na sajtu: " . implode(', ', $nemaIh) . "\n";

if ($dry) { echo "\n(dry-run — dodaj bez ?dry da upises)\n"; exit; }
if (!$promjene) { echo "\nNema sta da se mijenja (vec je tako).\n"; exit; }

$bkp = $root . '/data/products.backup-flexopis-' . date('Ymd-His') . '.json';
@file_put_contents($bkp, $sirovo);
$out = json_encode(array_values($flat), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$tmp = $put . '.tmp';
if ($out !== false && @file_put_contents($tmp, $out, LOCK_EX) !== false && @rename($tmp, $put)) {
    echo "\nUPISANO " . count($promjene) . " opisa. Backup: " . basename($bkp) . "\n";
} else {
    echo "\nGRESKA pri upisu!\n";
}
