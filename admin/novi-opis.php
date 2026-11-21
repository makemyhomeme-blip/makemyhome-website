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
    // 21.11.2026: novi akusticni paneli A1/A2/A3 (id 197,198,199).
    197 => [
        'description' =>
            "Timber Echo je akustični panel sa drvenim rebrima u toplom prirodnom tonu drveta na crnom filcu — izgled koji se danas viđa u svakom modernom studiju, kancelariji i dnevnoj sobi.\n\n"
          . "Panel ne zaustavlja zvuk da ne pređe u drugu prostoriju, nego ga upija unutar sobe i smanjuje eho. Razlika se čuje odmah — glas prestane da se vraća sa zidova, pa razgovor i snimak postanu čistiji.\n\n"
          . "Topli drveni ton je najuniverzalniji izbor: lijepo leži uz bijelo, sivo, beton i drvo, unosi toplinu, a ne zatvara prostoriju kao tamne varijante.\n\n"
          . "Radi u kancelariji, kućnoj radnoj sobi, studiju za snimanje i home cinema sobi, ali i kao topli dekorativni zid u dnevnoj ili spavaćoj sobi.\n\n"
          . "280×60 cm po panelu, pokriva 1,68 m². Za zid 5×2,6 m treba oko 8 panela. Akustični filc debljine 9 mm, NRC 0,65–0,85 (upija 65–85 % zvuka koji ga pogodi). Lijepi se ljepilom ili vješa na sistem kuka — bez podkonstrukcije.",
        'features' => [
            "Dimenzije: 280×60cm (1,68 m² po komadu)",
            "Debljina: 9mm (akustični filc)",
            "Materijal: akustični filc sa dekorativnom drvenom oblogom",
            "Boja: topli prirodni drveni ton",
            "Zvučna apsorpcija: NRC koeficijent 0,65–0,85",
            "Smanjuje eho i reverberaciju u prostoriji",
            "Montaža: lijepi se ljepilom ili sistem kuka – bez podkonstrukcije",
            "Pogodan za kancelarije, studije, home cinema i dnevne sobe",
            "Šifra: A1",
        ],
        'idealFor'   => ['Home cinema', 'Ured', 'Studija za snimanje', 'Dnevna soba'],
        'styleMatch' => ['Moderni', 'Skandinavski', 'Prirodni', 'Industrijski'],
        'highlight'  => 'Topli drveni ton koji upija zvuk',
        'badge'      => 'Novo',
    ],
    198 => [
        'description' =>
            "Noir Wave je akustični panel sa drvenim rebrima u dubokoj crnoj nijansi na crnom filcu — dramatičan, moderan izgled koji zidu daje karakter i dubinu.\n\n"
          . "Osim što izgleda moćno, panel upija zvuk unutar prostorije i smanjuje eho. Glas se prestane vraćati sa zidova, pa je razgovor jasniji, a snimak čistiji.\n\n"
          . "Crna varijanta je „statement” izbor — najbolje radi na jednom fokalnom zidu, iza TV-a ili u studiju, uz dobro usmjereno svjetlo (spot ili LED traka) koje istakne reljef rebara.\n\n"
          . "Najčešće se bira za home cinema, studije za snimanje, moderne kancelarije i TV zidove.\n\n"
          . "280×60 cm po panelu, pokriva 1,68 m². Za zid 5×2,6 m treba oko 8 panela. Akustični filc debljine 9 mm, NRC 0,65–0,85 (upija 65–85 % zvuka koji ga pogodi). Lijepi se ljepilom ili vješa na sistem kuka — bez podkonstrukcije.",
        'features' => [
            "Dimenzije: 280×60cm (1,68 m² po komadu)",
            "Debljina: 9mm (akustični filc)",
            "Materijal: akustični filc sa dekorativnom drvenom oblogom",
            "Boja: duboka crna",
            "Zvučna apsorpcija: NRC koeficijent 0,65–0,85",
            "Smanjuje eho i reverberaciju u prostoriji",
            "Montaža: lijepi se ljepilom ili sistem kuka – bez podkonstrukcije",
            "Pogodan za home cinema, studije, moderne kancelarije i TV zidove",
            "Šifra: A2",
        ],
        'idealFor'   => ['Home cinema', 'Studija za snimanje', 'Ured', 'TV zid'],
        'styleMatch' => ['Moderni', 'Industrijski', 'Luksuzni', 'Minimalistički'],
        'highlight'  => 'Dramatična crna – upija zvuk i daje karakter',
        'badge'      => 'Novo',
    ],
    199 => [
        'description' =>
            "Amber Echo je akustični panel sa drvenim rebrima u toploj medeno-ćilibarnoj (amber) nijansi na crnom filcu — bogata, topla boja koja prostoriju odmah učini ugodnijom.\n\n"
          . "Panel upija zvuk unutar sobe i smanjuje eho, pa glas prestane da se vraća sa zidova — razgovor postaje lakši, a snimak čistiji.\n\n"
          . "Topla amber nijansa lijepo grije prostor: odlično leži uz tamni namještaj, zeleno i mesing, i daje toplu „cozy” atmosferu dnevnoj sobi, baru ili radnoj sobi.\n\n"
          . "Radi u kancelariji, studiju, home cinema sobi i dnevnoj sobi gdje se traži toplina.\n\n"
          . "280×60 cm po panelu, pokriva 1,68 m². Za zid 5×2,6 m treba oko 8 panela. Akustični filc debljine 9 mm, NRC 0,65–0,85 (upija 65–85 % zvuka koji ga pogodi). Lijepi se ljepilom ili vješa na sistem kuka — bez podkonstrukcije.",
        'features' => [
            "Dimenzije: 280×60cm (1,68 m² po komadu)",
            "Debljina: 9mm (akustični filc)",
            "Materijal: akustični filc sa dekorativnom drvenom oblogom",
            "Boja: topla medeno-ćilibarna (amber)",
            "Zvučna apsorpcija: NRC koeficijent 0,65–0,85",
            "Smanjuje eho i reverberaciju u prostoriji",
            "Montaža: lijepi se ljepilom ili sistem kuka – bez podkonstrukcije",
            "Pogodan za kancelarije, studije, home cinema i dnevne sobe",
            "Šifra: A3",
        ],
        'idealFor'   => ['Dnevna soba', 'Ured', 'Home cinema', 'Studija za snimanje'],
        'styleMatch' => ['Moderni', 'Skandinavski', 'Prirodni', 'Luksuzni'],
        'highlight'  => 'Topla amber nijansa koja upija zvuk',
        'badge'      => 'Novo',
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
