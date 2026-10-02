<?php
/**
 * Jednokratno GRUPISANJE Flex Stone proizvoda u data/products.json.
 *
 * Admin lista grupise proizvode po kategoriji REDOM kako stoje u fajlu. Nova
 * Flex Stone roba je dodata na kraj, pa se u adminu pojavljivala kao ZASEBNA
 * grupa na dnu, odvojeno od postojeceg Flex Stone panela. Ova skripta premjesta
 * SVE flex-stone proizvode zajedno, na mjesto gdje je prvi flex-stone — pa se u
 * adminu vide u jednoj grupi. Ne mijenja nista drugo (ni polja, ni ostale
 * kategorije). Na sajtu se izgled ne mijenja (kategorija ionako skuplja sve).
 *
 * Backup prije upisa. ?dry=1 za pregled. Pristup: samo deploy token.
 */
$token = (string) ($_GET['token'] ?? '');
if (!hash_equals('697113eed3779e99c7bcbf5953fecc2f541250ef', $token)) { http_response_code(403); die('403'); }
header('Content-Type: text/plain; charset=utf-8');
$dry = isset($_GET['dry']);
$cilj = isset($_GET['cat']) ? preg_replace('/[^a-z0-9\-]/', '', strtolower($_GET['cat'])) : 'flex-stone';

$root = dirname(__DIR__);
$put  = $root . '/data/products.json';
$sirovo = @file_get_contents($put);
$P = json_decode($sirovo, true);
if (!is_array($P)) { die('GRESKA: ne mogu procitati products.json'); }
$flat = isset($P['products']) ? $P['products'] : $P;

$grupa = []; $ostalo = []; $prviPos = null;
foreach ($flat as $p) {
    if (($p['category'] ?? '') === $cilj) {
        if ($prviPos === null) $prviPos = count($ostalo);
        $grupa[] = $p;
    } else {
        $ostalo[] = $p;
    }
}
if ($prviPos === null || count($grupa) < 2) { die("Nema sta da se grupise za '$cilj' (nadjeno: " . count($grupa) . ").\n"); }

array_splice($ostalo, $prviPos, 0, $grupa);
$novi = $ostalo;

echo $dry ? "=== PREGLED ===\n" : "=== UPIS ===\n";
echo "Kategorija: $cilj  |  grupisano komada: " . count($grupa) . "  |  ubaceno na poziciju: $prviPos\n";
echo "Redosljed grupe:\n";
foreach ($grupa as $g) echo "  - id " . ($g['id'] ?? '?') . "  " . ($g['sku'] ?? '') . "\n";

if ($dry) { echo "\n(dry-run — dodaj bez ?dry da upises)\n"; exit; }

$bkp = $root . '/data/products.backup-raspored-' . date('Ymd-His') . '.json';
@file_put_contents($bkp, $sirovo);
$out = json_encode(array_values($novi), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$tmp = $put . '.tmp';
if (@file_put_contents($tmp, $out, LOCK_EX) !== false && @rename($tmp, $put)) {
    echo "\nUPISANO. Backup: " . basename($bkp) . "\n";
} else {
    echo "\nGRESKA pri upisu!\n";
}
