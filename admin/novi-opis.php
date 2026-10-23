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
if (!hash_equals('58a51c2e80e100fa2f0b022c29b87f10d2e3c8ad', $token)) { http_response_code(403); die('403'); }
header('Content-Type: text/plain; charset=utf-8');
$dry = isset($_GET['dry']);

$root = dirname(__DIR__);
$put  = $root . '/data/products.json';

// ---- STA MIJENJAMO (po ID-u) — samo tekstualna polja ----
$dopune = [
    // poslije upisa se mapa isprazni da ponovno pokretanje ne prepise izmjene
    // koje vlasnik u medjuvremenu unese preko admina.
    // 23.10.2026: id 196 (Travertino Bianco, SW111 mermerni panel).
    196 => [
        'description' =>
            "Travertino Bianco je svijetli, kremasto-bijeli travertin — onaj mirni mediteranski kamen koji prostoriju odmah učini svjetlijom i skupljom. Za razliku od dramatičnih mermera sa jakim žilama, travertin ima nježne vodoravne slojeve i sitne prirodne pore, pa djeluje mekano i elegantno, a ne napadno.\n\n"
          . "Baš zbog te smirenosti lako ga je staviti — ne bori se sa namještajem nego mu daje topao, prirodan okvir. Pod bočnim svjetlom tekstura travertina blago izbije i zid dobije dubinu, a i dalje ostane svijetao.\n\n"
          . "Najviše ga biramo za dnevne sobe, zid iza TV-a, recepcije i kupatila — svuda gdje treba luksuz koji ne viče. Prelijepo leži uz svijetlo i tamno drvo, mesing, staklo i zelenilo.\n\n"
          . "280×122 cm po panelu, pokriva 3,42 m². Za zid 3×2,6 m treba 3 panela. Vodootporan i otporan na buđ (idealan za kupatilo), vatrootporan klasa B1, UV zaštita. Lijepi se silikonom, siječe se skalpelom.",
        'features' => [
            "Dimenzije: 280×122cm (3.42 m² po komadu)",
            "Debljina: 5mm",
            "Boja: kremasto-bijeli travertin",
            "Šara: nježni vodoravni slojevi i sitne pore – prirodni travertin",
            "Površinska UV zaštita – ne blijedi na suncu",
            "Montaža: lijepi se silikonom, siječe se skalpelom",
            "Pogodan za zidove i plafone – dnevne sobe, spavaće sobe, kafići, restorani, hoteli, uredi",
            "Vodootporan – ne nabrekne i ne deformiše se od vode",
            "Otporan na buđ i vlagu – idealan za kupatila i vlažne prostorije",
            "Vatrootporan (klasa B1) – usporava širenje plamena",
            "Otporan na prljavštinu – lako se čisti vlažnom krpom",
            "Šifra: SW111",
        ],
        'idealFor'   => ['Dnevna soba', 'Kupatilo', 'Hodnik', 'Recepcija', 'Trpezarija', 'Spavaća soba', 'Apartman', 'Hotel'],
        'styleMatch' => ['Moderni', 'Luksuzni', 'Minimalistički', 'Klasični'],
        'highlight'  => 'Svijetli kremasto-bijeli travertin – mirno i luksuzno',
        'badge'      => 'Preporučujemo',
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
