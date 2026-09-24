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
    // Vec upisano: id 148 (Kožna Siva), id 73 (Bijela Sjaj) — 24.09.2026.
    // Nova 3D letvica za dizajn BW222 (isti dezen kao panel "Pura", id 44).
    149 => [
        'name' => '3D Letvica – Pura',
        'highlight' => 'Topla bijela sa tkanom teksturom',
        'description' =>
            "Pura je topla bijela sa mekanom tkanom teksturom — u profilu 3D letvice ta tekstura dobija i dubinu, pa zid izgleda kao mekano platno presavijeno u vertikalna rebra.\n\n"
          . "Toplina nijanse je ono zbog čega je biramo: hladna bijela u prostoriji bez mnogo sunca djeluje sivo, a Pura ostaje topla i pod oblačnim danom i pod sijalicom. Zbog toga najbolje radi u spavaćim i dnevnim sobama — prostorima gdje zid treba da smiruje.\n\n"
          . "Ista nijansa postoji i kao ravan panel Pura (BW222), pa možete kombinovati letvice i panel na istom zidu za slojevit izgled.\n\n"
          . "280×16 cm, jedna letvica pokriva 0,45 m². Za zid 3×2,6 m treba oko 18 komada. PVC — lijepi se silikonom, siječe skalpelom.",
        'features' => [
            'Dimenzije: 280×16cm po letvici',
            'Boja: topla bijela sa tkanom (tekstilnom) teksturom',
            '3D reljefna površina – vertikalne letvice',
            'Ista nijansa kao panel Pura (BW222) – panel i letvica u kompletu',
            'Materijal: PVC plastika',
            'Montaža: lijepi se silikonom, siječe se skalpelom',
            'Pogodan za zidove, plafone i pregradne panele',
            'Šifra: I3D160BW222',
        ],
        'idealFor' => ['Spavaća soba', 'Dnevna soba', 'Hodnik', 'Recepcija'],
        'styleMatch' => ['Skandinavski', 'Japandi', 'Moderni', 'Cozy'],
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
