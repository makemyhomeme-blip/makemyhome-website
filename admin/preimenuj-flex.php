<?php
/**
 * Jednokratno: skida prefiks "Flex Stone " iz naziva svih flex-stone proizvoda
 * u ZIVOM products.json (u kategoriji su vec Flex Stone, pa je suvisno).
 * Backup prije upisa. ?dry=1 pregled. Pristup: deploy token.
 */
$token = (string) ($_GET['token'] ?? '');
if (!hash_equals('697113eed3779e99c7bcbf5953fecc2f541250ef', $token)) { http_response_code(403); die('403'); }
header('Content-Type: text/plain; charset=utf-8');
$dry = isset($_GET['dry']);

$root = dirname(__DIR__);
$put  = $root . '/data/products.json';
$sirovo = @file_get_contents($put);
$P = json_decode($sirovo, true);
if (!is_array($P)) { die('GRESKA: products.json'); }
$flat = isset($P['products']) ? $P['products'] : $P;

$pref = 'Flex Stone ';
$promjene = [];
foreach ($flat as $idx => $p) {
    if (($p['category'] ?? '') !== 'flex-stone') continue;
    $ime = (string)($p['name'] ?? '');
    if (mb_strpos($ime, $pref) === 0) {
        $novo = mb_substr($ime, mb_strlen($pref));
        $flat[$idx]['name'] = $novo;
        $promjene[] = "ID {$p['id']}: \"$ime\" -> \"$novo\"";
    }
}

echo $dry ? "=== PREGLED ===\n" : "=== UPIS ===\n";
echo "Preimenovano: " . count($promjene) . "\n";
foreach ($promjene as $r) echo "  $r\n";
if ($dry) { echo "\n(dry-run)\n"; exit; }
if (!$promjene) { echo "\nNema sta (nijedan ne pocinje sa '$pref').\n"; exit; }

$bkp = $root . '/data/products.backup-preimenuj-' . date('Ymd-His') . '.json';
@file_put_contents($bkp, $sirovo);
$out = json_encode(array_values($flat), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$tmp = $put . '.tmp';
if (@file_put_contents($tmp, $out, LOCK_EX) !== false && @rename($tmp, $put)) {
    echo "\nUPISANO. Backup: " . basename($bkp) . "\n";
} else { echo "\nGRESKA pri upisu!\n"; }
