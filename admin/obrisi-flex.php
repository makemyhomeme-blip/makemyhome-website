<?php
/**
 * Jednokratno brisanje proizvoda po ID-u iz ZIVOG products.json.
 * Koristi se za "Roman Pillar" (id 168) — to je naziv SERIJE stubova, ne
 * proizvod (greskom kreiran). Backup prije upisa. ?dry=1. Pristup: deploy token.
 */
$token = (string) ($_GET['token'] ?? '');
if (!hash_equals('697113eed3779e99c7bcbf5953fecc2f541250ef', $token)) { http_response_code(403); die('403'); }
header('Content-Type: text/plain; charset=utf-8');
$dry = isset($_GET['dry']);
$brisi = [168]; // Roman Pillar (fantom)

$root = dirname(__DIR__);
$put  = $root . '/data/products.json';
$sirovo = @file_get_contents($put);
$P = json_decode($sirovo, true);
if (!is_array($P)) { die('GRESKA: products.json'); }
$flat = isset($P['products']) ? $P['products'] : $P;

$obrisano = []; $novi = [];
foreach ($flat as $p) {
    if (in_array((int)($p['id'] ?? 0), $brisi, true)) {
        $obrisano[] = "id {$p['id']}  " . ($p['sku'] ?? '') . "  (" . ($p['name'] ?? '') . ")";
    } else {
        $novi[] = $p;
    }
}

echo $dry ? "=== PREGLED ===\n" : "=== BRISANJE ===\n";
echo "Brisem: " . count($obrisano) . "\n";
foreach ($obrisano as $r) echo "  - $r\n";
if ($dry) { echo "\n(dry-run)\n"; exit; }
if (!$obrisano) { echo "\nNema sta (id vec ne postoji).\n"; exit; }

$bkp = $root . '/data/products.backup-brisanje-' . date('Ymd-His') . '.json';
@file_put_contents($bkp, $sirovo);
$out = json_encode(array_values($novi), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$tmp = $put . '.tmp';
if (@file_put_contents($tmp, $out, LOCK_EX) !== false && @rename($tmp, $put)) {
    echo "\nOBRISANO. Backup: " . basename($bkp) . "\n";
} else { echo "\nGRESKA pri upisu!\n"; }
