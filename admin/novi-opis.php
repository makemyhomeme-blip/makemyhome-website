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
    // Nov tekstilni panel BW105 "VELARÉ" (id 152) — ime vec postavio vlasnik,
    // ovdje idu samo opis i prateca polja. Bez "Novo" oznake.
    152 => [
        'highlight' => 'Topli bronzani ton sa tkanom teksturom',
        'description' =>
            "VELARÉ je topla bronzano-taupe nijansa sa finom tkanom teksturom i suptilnim odsjajem — pod svjetlom mijenja dubinu, čas mekši šampanjac, čas dublja mokka. To je najluksuzniji ton u tekstilnoj liniji.\n\n"
          . "Sjaj nije jak ni metalan, nego tih — daje zidu „skup\" izgled kao svila ili laneni damast, a površina ostaje topla i prijatna. Zbog toplog podtona lijepo stoji i uveče pod sijalicom, ne djeluje hladno.\n\n"
          . "Biramo ga za akcentne zidove u dnevnim i spavaćim sobama i luksuznim enterijerima — iza kreveta, iza TV-a ili u recepciji, gdje treba da ostavi utisak.\n\n"
          . "Uz toplo drvo, mesing i baršun izgleda bogato; uz staklo i crni metal pravi elegantan kontrast.\n\n"
          . "280×122 cm po panelu, pokriva 3,42 m². Za zid 3×2,6 m treba 3 panela. Bambusovo vlakno, vodootporan i otporan na buđ — smije i u kupatilo. Lijepi se silikonom, siječe skalpelom.",
        'features' => [
            'Dimenzije: 280×122cm (3.42 m² po komadu)',
            'Debljina: 5mm',
            'Boja: topla bronzano-taupe sa blagim sjajem',
            'Šara: fina tkana tekstura sa suptilnim odsjajem',
            'Površinska UV zaštita – ne blijedi na suncu',
            'Montaža: lijepi se silikonom, siječe se skalpelom',
            'Pogodan za zidove i plafone – dnevne sobe, spavaće sobe, kafići, restorani, hoteli, uredi',
            'Vodootporan – ne nabrekne i ne deformiše se od vode',
            'Otporan na buđ i vlagu – idealan za kupatila i vlažne prostorije',
            'Vatrootporan (klasa B1) – usporava širenje plamena',
            'Otporan na prljavštinu – lako se čisti vlažnom krpom',
            'Šifra: BW105',
        ],
        'idealFor' => ['Dnevna soba', 'Spavaća soba', 'Recepcija', 'Restoran', 'Hotel', 'Apartman'],
        'styleMatch' => ['Luksuzni', 'Glamurozni', 'Moderni', 'Art Deco'],
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
