<?php
/**
 * Jednokratna dopuna TEKSTA za novododate proizvode — po ID-u.
 *
 * Radi na SERVERU nad ZIVIM products.json (izvor istine). Mijenja SAMO
 * tekstualna polja (name, description, features, idealFor, styleMatch,
 * highlight, badge) za navedeni ID — slika, cijena, galerija i svi ostali
 * proizvodi ostaju netaknuti. Prije upisa pravi backup. Ima ?dry=1 za pregled.
 *
 * Pristup: samo deploy token (kao sync).
 */
$token = (string) ($_GET['token'] ?? '');
if (!hash_equals('697113eed3779e99c7bcbf5953fecc2f541250ef', $token)) { http_response_code(403); die('403'); }
header('Content-Type: text/plain; charset=utf-8');
$dry = isset($_GET['dry']);

$root = dirname(__DIR__);
$put  = $root . '/data/products.json';

// ---- STA MIJENJAMO (po ID-u) — samo tekstualna polja ----
$dopune = [
    148 => [
        'name' => '3D Letvica – Kožna Siva',
        'highlight' => 'Meka siva sa efektom kože',
        'description' =>
            "Kožna Siva se razlikuje od ostalih sivih letvica po površini — nije glatka ni ravno mat, nego ima finu zrnastu teksturu nalik koži, koja svjetlo lomi mekano umjesto da ga odbija.\n\n"
          . "Zbog toga zid djeluje toplije i „skuplje\" nego kod obične sive: nijansa je neutralna, ali joj tekstura daje karakter. Ne blista pod svjetlom i ne pokazuje otiske prstiju, pa lijepo stoji i u prostoriji sa jakim osvjetljenjem.\n\n"
          . "Radi kao mirna pozadina za TV zid, iza kreveta ili u hodniku. Uz toplo drvo omekša prostor, a uz crni metal pravi elegantan, suzdržan kontrast.\n\n"
          . "280×16 cm, jedna letvica pokriva 0,45 m². Za zid 3×2,6 m treba oko 18 komada. PVC — lijepi se silikonom, siječe skalpelom.",
        'features' => [
            'Dimenzije: 280×16cm po letvici',
            'Boja: siva sa kožnom (zrnastom) teksturom',
            'Meka mat površina – ne blista i ne hvata otiske prstiju',
            '3D reljefna površina – vertikalne letvice',
            'Materijal: PVC plastika',
            'Montaža: lijepi se silikonom, siječe se skalpelom',
            'Pogodan za zidove, plafone i pregradne panele',
        ],
        'idealFor' => ['Dnevna soba', 'Spavaća soba', 'Hodnik', 'Kancelarija'],
        'styleMatch' => ['Minimalistički', 'Moderni', 'Japandi', 'Skandinavski'],
        'badge' => 'Novo',
    ],
];

$sirovo = @file_get_contents($put);
$P = json_decode($sirovo, true);
if (!is_array($P)) { die('GRESKA: ne mogu procitati products.json'); }
$flat = isset($P['products']) ? $P['products'] : $P;

$promjene = []; $nadjeno = [];
foreach ($flat as $idx => $p) {
    $id = $p['id'] ?? null;
    if ($id === null || !isset($dopune[$id])) continue;
    $nadjeno[$id] = 1;
    foreach ($dopune[$id] as $polje => $vrijednost) {
        $flat[$idx][$polje] = $vrijednost;
        $prikaz = is_array($vrijednost) ? implode(' | ', $vrijednost) : (string)$vrijednost;
        if (mb_strlen($prikaz) > 80) $prikaz = mb_substr($prikaz, 0, 80) . '…';
        $promjene[] = "ID $id  $polje = $prikaz";
    }
}

$nemaIh = array_values(array_diff(array_keys($dopune), array_keys($nadjeno)));

echo $dry ? "=== PREGLED (dry-run) ===\n" : "=== UPIS ===\n";
echo "Polja: " . count($promjene) . "\n";
foreach ($promjene as $r) echo "  $r\n";
if ($nemaIh) echo "\nNIJE nadjen ID: " . implode(', ', $nemaIh) . "\n";

if ($dry) { echo "\n(dry-run — dodaj bez ?dry da upises)\n"; exit; }
if (!$promjene) { echo "\nNema sta da se mijenja.\n"; exit; }

$bkp = $root . '/data/products.backup-opis-' . date('Ymd-His') . '.json';
@file_put_contents($bkp, $sirovo);
$novi = json_encode(array_values($flat), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$tmp = $put . '.tmp';
if (@file_put_contents($tmp, $novi, LOCK_EX) !== false && @rename($tmp, $put)) {
    echo "\nUPISANO. Backup: " . basename($bkp) . "\n";
} else {
    echo "\nGRESKA pri upisu!\n";
}
