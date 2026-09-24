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
    // id 148 (Kožna Siva) je vec upisan 24.09.2026 — ne ponavljati.
    // Vlasnik je preimenovao id 73 (bila "Hladno Siva Teksturisana",
    // sifra I3D160JM001) u sjajnu bijelu i promijenio sliku, pa stari opis
    // vise ne odgovara. Ovdje ide nov tekst za sjajnu bijelu.
    73 => [
        'name' => '3D Letvica – Bijela Sjaj',
        'highlight' => 'Visoki sjaj koji posvjetljuje prostor',
        'description' =>
            "Bijela Sjaj je visoki sjaj — lakirana bijela površina koja odbija svjetlo skoro kao staklo. Za razliku od mat bijele, ova letvica „pali\" prostor: hvata svjetlo sa prozora i lampi i vraća ga nazad, pa manja ili mračnija soba odmah djeluje veća i svjetlija.\n\n"
          . "Sjaj naglašava i sam reljef — vertikalne brazde pod svjetlom dobiju tanke linije odsjaja, što zidu daje čist, luksuzan izgled.\n\n"
          . "Glatka lakirana površina se lako održava: obriše se vlažnom krpom i ne upija prašinu. Pod jakim direktnim svjetlom sjaj je izražen, pa je najbolja tamo gdje želite svijetao, moderan prostor — ne za prigušenu, mirnu atmosferu.\n\n"
          . "280×16 cm, jedna letvica pokriva 0,45 m². Za zid 3×2,6 m treba oko 18 komada. PVC — lijepi se silikonom, siječe skalpelom.",
        'features' => [
            'Dimenzije: 280×16cm po letvici',
            'Boja: bijela, visoki sjaj (lakirana površina)',
            'Sjajna površina – odbija svjetlo i posvjetljuje prostor',
            '3D reljefna površina – vertikalne letvice',
            'Materijal: PVC plastika',
            'Lako održavanje: briše se vlažnom krpom, ne upija prašinu',
            'Montaža: lijepi se silikonom, siječe se skalpelom',
            'Šifra: I3D160JM001',
        ],
        'idealFor' => ['Dnevna soba', 'Kupatilo', 'Hodnik', 'Manje prostorije'],
        'styleMatch' => ['Moderni', 'Glamurozni', 'Minimalistički', 'Skandinavski'],
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
