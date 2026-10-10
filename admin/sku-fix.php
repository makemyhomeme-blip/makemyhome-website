<?php
/**
 * Izmjena SIFRE (sku) proizvoda po ID-u, u ZIVOM products.json.
 * Backup prije upisa, atomic upis. ?dry=1 za pregled. Pristup: deploy token.
 * Parametri: ?id=<broj>&sku=<nova_sifra>
 */
$token = (string) ($_GET['token'] ?? '');
if (!hash_equals('697113eed3779e99c7bcbf5953fecc2f541250ef', $token)) { http_response_code(403); die('403'); }
header('Content-Type: text/plain; charset=utf-8');
$dry = isset($_GET['dry']);
$id  = (int) ($_GET['id'] ?? 0);
$sku = trim((string) ($_GET['sku'] ?? ''));
if ($id <= 0 || $sku === '' || !preg_match('/^[A-Za-z0-9._\-]{1,40}$/', $sku)) { die('GRESKA: id/sku'); }

$root = dirname(__DIR__);
$put  = $root . '/data/products.json';
$sirovo = @file_get_contents($put);
$P = json_decode($sirovo, true);
if (!is_array($P)) { die('GRESKA: products.json'); }
$flat = isset($P['products']) ? $P['products'] : $P;

$nadjen = false; $staro = '';
foreach ($flat as $i => $p) {
    if ((int)($p['id'] ?? 0) === $id) {
        $staro = (string)($p['sku'] ?? '');
        $flat[$i]['sku'] = $sku;
        $nadjen = true;
        echo "ID $id  \"" . ($p['name'] ?? '') . "\"\n  sku: \"$staro\" -> \"$sku\"\n";
        break;
    }
}
if (!$nadjen) { die("\nGRESKA: nema proizvoda sa id $id\n"); }
if ($dry) { echo "\n(dry-run)\n"; exit; }

$bkp = $root . '/data/products.backup-sku-' . date('Ymd-His') . '.json';
@file_put_contents($bkp, $sirovo);
$out = json_encode(array_values($flat), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$tmp = $put . '.tmp';
if (@file_put_contents($tmp, $out, LOCK_EX) !== false && @rename($tmp, $put)) {
    echo "\nUPISANO. Backup: " . basename($bkp) . "\n";
} else {
    echo "\nGRESKA pri upisu!\n";
}
