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
    // id 78 (I3D160016): vlasnik promijenio sliku — vise NIJE hladno siva nego
    // topla greige. Nov naziv + opis. Bez "Novo" oznake.
    78 => [
        'name' => '3D Letvica – Latte',
        'highlight' => 'Topla greige nijansa koja smiruje',
        'description' =>
            "Latte je topla greige — bež sa blagim sivim podtonom, negdje između pijeska i taupe. Nije ni hladno siva ni žuta bež, nego mirna neutralna nijansa koja se lako uklapa uz drvo, kamen i tekstil.\n\n"
          . "Toplina je ono što je izdvaja: za razliku od hladne sive, Latte i u prostoriji bez mnogo sunca ostaje prijatna i ne djeluje sivo. Zbog toga dobro radi u spavaćim i dnevnim sobama, kao mirna pozadina koja ne vuče pažnju na sebe.\n\n"
          . "Mat površina ne blista pod svjetlom, pa reljef letvica ostaje suptilan i elegantan, bez jakih sjenki.\n\n"
          . "280×16 cm, jedna letvica pokriva 0,45 m². Za zid 3×2,6 m treba oko 18 komada. PVC — lijepi se silikonom, siječe skalpelom.",
        'features' => [
            'Dimenzije: 280×16cm po letvici',
            'Boja: topla greige (bež sa sivim podtonom)',
            'Mat površina – ne blista, suptilan reljef',
            '3D reljefna površina – vertikalne letvice',
            'Materijal: PVC plastika',
            'Montaža: lijepi se silikonom, siječe se skalpelom',
            'Pogodan za zidove, plafone i pregradne panele',
            'Šifra: I3D160016',
        ],
        'idealFor' => ['Spavaća soba', 'Dnevna soba', 'Hodnik', 'Kancelarija'],
        'styleMatch' => ['Skandinavski', 'Japandi', 'Prirodni', 'Minimalistički'],
        'badge' => '',
    ],
    // id 150 (I3D160025): nova 3D letvica, svijetla hladna siva. Bez "Novo".
    150 => [
        'name' => '3D Letvica – Svijetlo Siva',
        'highlight' => 'Svijetla, mirna siva za moderan prostor',
        'description' =>
            "Svijetlo Siva je čista, svijetla siva sa blago hladnim tonom — dovoljno neutralna da bude pozadina, a dovoljno svijetla da ne zatvara prostor kao tamnije sive.\n\n"
          . "Zbog svjetline lijepo radi u manjim ili slabije osvijetljenim prostorijama: reflektuje svjetlo i drži prostor prozračnim, a opet daje zidu tihu boju umjesto obične bijele.\n\n"
          . "Kao neutralna pozadina ističe sve što se stavi ispred nje — policu, fotelju, biljku — bez da mijenja njihovu boju. U kancelariji je siguran izbor za zid iza leđa na video pozivima.\n\n"
          . "Mat površina drži reljef suptilnim; uz toplo drvo pravi ravnotežu, uz crni metal ide u moderan, industrijski pravac.\n\n"
          . "280×16 cm, jedna letvica pokriva 0,45 m². Za zid 3×2,6 m treba oko 18 komada. PVC — lijepi se silikonom, siječe skalpelom.",
        'features' => [
            'Dimenzije: 280×16cm po letvici',
            'Boja: svijetla siva (blago hladan ton)',
            'Mat površina – suptilan reljef, ne blista',
            '3D reljefna površina – vertikalne letvice',
            'Materijal: PVC plastika',
            'Montaža: lijepi se silikonom, siječe se skalpelom',
            'Pogodan za zidove, plafone i pregradne panele',
            'Šifra: I3D160025',
        ],
        'idealFor' => ['Dnevna soba', 'Spavaća soba', 'Kancelarija', 'Hodnik'],
        'styleMatch' => ['Minimalistički', 'Moderni', 'Skandinavski', 'Japandi'],
        'badge' => '',
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
