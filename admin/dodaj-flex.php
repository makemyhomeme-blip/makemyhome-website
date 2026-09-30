<?php
/**
 * Jednokratno DODAVANJE Flex Stone proizvoda iz kataloga u data/products.json.
 *
 * Radi na SERVERU nad ZIVIM products.json (izvor istine): DODAJE nove proizvode
 * (tekst: naziv, opis, dimenzije, karakteristike) po sifri iz data/flex-tekst.json.
 * NE dira postojece proizvode. Novi proizvod dobija PRAZNU sliku i cijenu, pa se
 * NE prikazuje na sajtu dok mu vlasnik preko admina ne doda sliku i cijenu
 * (products.php/sitemap/pretraga to poštuju). Sifra koja vec postoji se preskace.
 *
 * Prije upisa pravi backup. ?dry=1 za pregled bez upisa.
 * Pristup: samo deploy token.
 */
$token = (string) ($_GET['token'] ?? '');
if (!hash_equals('697113eed3779e99c7bcbf5953fecc2f541250ef', $token)) { http_response_code(403); die('403'); }
header('Content-Type: text/plain; charset=utf-8');
$dry = isset($_GET['dry']);

$root = dirname(__DIR__);
$put  = $root . '/data/products.json';
$txtF = $root . '/data/flex-tekst.json';

$txt = json_decode(@file_get_contents($txtF), true);
if (!is_array($txt)) { die('GRESKA: ne mogu procitati data/flex-tekst.json'); }

$sirovo = @file_get_contents($put);
$P = json_decode($sirovo, true);
if (!is_array($P)) { die('GRESKA: ne mogu procitati products.json'); }
$flat = isset($P['products']) ? $P['products'] : $P;

// Postojece sifre (velika slova) da ne dupliramo
$imaSku = [];
foreach ($flat as $p) { $s = strtoupper(trim((string)($p['sku'] ?? ''))); if ($s !== '') $imaSku[$s] = 1; }
$maxId = 0;
foreach ($flat as $p) $maxId = max($maxId, (int)($p['id'] ?? 0));

$dodato = []; $preskoceno = [];
foreach ($txt as $sku => $d) {
    $SKU = strtoupper(trim((string)$sku));
    if ($SKU === '') continue;
    if (isset($imaSku[$SKU])) { $preskoceno[] = $sku; continue; }
    $maxId++;
    $flat[] = [
        'id'         => $maxId,
        'name'       => $d['name'] ?? $sku,
        'category'   => $d['category'] ?? 'flex-stone',
        'price'      => '',            // vlasnik upisuje
        'unit'       => $d['unit'] ?? 'kom',
        'discount'   => 0,
        'description'=> $d['description'] ?? '',
        'features'   => $d['features'] ?? [],
        'image'      => '',            // vlasnik dodaje preko admina
        'badge'      => $d['badge'] ?? '',
        'sku'        => $sku,
        'inStock'    => true,
        'featured'   => false,
        'highlight'  => $d['highlight'] ?? '',
        'idealFor'   => $d['idealFor'] ?? [],
        'styleMatch' => $d['styleMatch'] ?? [],
        'gallery'    => [],
    ];
    $imaSku[$SKU] = 1;
    $dodato[] = "ID $maxId  $sku  (" . ($d['name'] ?? '') . ")";
}

echo $dry ? "=== PREGLED (dry-run, NISTA se ne upisuje) ===\n" : "=== UPIS ===\n";
echo "Novih: " . count($dodato) . "   (vec postoji: " . count($preskoceno) . ")\n";
foreach ($dodato as $r) echo "  + $r\n";
if ($preskoceno) echo "\nPreskoceno (vec ima sifru): " . implode(', ', $preskoceno) . "\n";

if ($dry) { echo "\n(dry-run — dodaj bez ?dry da upises)\n"; exit; }
if (!$dodato) { echo "\nNema novih za dodavanje.\n"; exit; }

$bkp = $root . '/data/products.backup-flex-' . date('Ymd-His') . '.json';
@file_put_contents($bkp, $sirovo);
$novi = json_encode(array_values($flat), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$tmp = $put . '.tmp';
if (@file_put_contents($tmp, $novi, LOCK_EX) !== false && @rename($tmp, $put)) {
    echo "\nUPISANO. Backup: " . basename($bkp) . "\n";
    echo "Proizvodi su dodati ali se NE VIDE dok im ne dodas sliku i cijenu u adminu.\n";
} else {
    echo "\nGRESKA pri upisu!\n";
}
