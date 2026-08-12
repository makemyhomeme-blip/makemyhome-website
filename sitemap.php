<?php
/**
 * Sitemap koji se pravi iz podataka, sa SLIKAMA.
 *
 * Zasto:
 * Stari sitemap.xml je bio obican spisak od 149 adresa, bez ijedne slike.
 * Google slike otkriva prvenstveno preko sitemapa — bez toga 220 fotografija
 * panela i soba prakticno ne postoji za pretragu slika. Za prodavnicu obloga
 * to je citav jedan kanal koji je stajao zatvoren.
 *
 * Zasto PHP a ne fajl:
 * Vlasnik dodaje proizvode i fotografije kroz admin. Fajl bi zastario cim se
 * doda nova soba, a niko se ne bi sjetio da ga osvjezi. Ovako je sitemap
 * uvijek tacan — pravi se iz istih podataka iz kojih se pravi i sajt.
 *
 * Servira se na adresi /sitemap.xml (pravilo u .htaccess), pa se za Google
 * nista nije promijenilo.
 */
require_once __DIR__ . '/php/slug.php';
require_once __DIR__ . '/php/dimenzije.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=0, must-revalidate');

$BAZA = 'https://makemyhome.me';
$P = json_decode(@file_get_contents(__DIR__ . '/data/products.json'), true) ?: [];
if (isset($P['products'])) $P = $P['products'];

$katImena = [
    'bambus-paneli' => 'Bambus Paneli', 'bambus-drveni' => 'Drveni Paneli',
    'bambus-tekstilni' => 'Tekstilni Paneli', 'bambus-mermerni' => 'Mermerni Paneli',
    'bambus-metalni' => 'Metalni Paneli', 'bambus-kozni' => 'Kožni Paneli',
    '3d-letvice' => '3D Letvice', 'akusticni-paneli' => 'Akustični Paneli',
    'aluminijum-lajsne' => 'Aluminijum Lajsne', 'spc-pod' => 'SPC Pod',
    'pu-kamen' => 'PU Kamen', 'classic' => 'Classic Paneli',
    'mdf' => 'MDF Paneli', 'flex-stone' => 'Flex Stone',
];

// Datum posljednje izmjene podataka — ne izmisljamo, uzimamo sa diska
$datPod = @filemtime(__DIR__ . '/data/products.json');
$danas  = date('Y-m-d', $datPod ?: time());

function mmhX(string $s): string
{
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/** Jedna <image:image> stavka; naslov je ono sto Google prikaze uz sliku. */
function mmhSlikaXML(string $rel, string $naslov): string
{
    $put = 'https://makemyhome.me/' . ltrim($rel, '/');
    return "    <image:image>\n"
         . '      <image:loc>' . mmhX($put) . "</image:loc>\n"
         . '      <image:title>' . mmhX(mb_substr($naslov, 0, 100)) . "</image:title>\n"
         . "    </image:image>\n";
}

$izlaz = [];
$dodaj = function (string $loc, string $freq, string $prio, string $slike = '') use (&$izlaz, $danas) {
    $izlaz[] = "  <url>\n"
             . '    <loc>' . mmhX($loc) . "</loc>\n"
             . '    <lastmod>' . $danas . "</lastmod>\n"
             . '    <changefreq>' . $freq . "</changefreq>\n"
             . '    <priority>' . $prio . "</priority>\n"
             . $slike
             . "  </url>\n";
};

// ---- Pocetna i staticne stranice ----------------------------------------
$dodaj($BAZA . '/', 'daily', '1.0',
       mmhSlikaXML('images/showcase-room.jpg', 'Make My Home Decor – zidni paneli, Podgorica'));

$statika = [
    ['products.html', 'weekly', '0.9'], ['cjenovnik.html', 'weekly', '0.9'],
    ['inspiracija.html', 'weekly', '0.9'], ['montaza.html', 'weekly', '0.7'],
    ['faq.html', 'weekly', '0.7'], ['about.html', 'weekly', '0.7'],
    ['contact.html', 'weekly', '0.7'], ['decor-box.html', 'weekly', '0.7'],
    ['paneli-za-kupatilo.html', 'weekly', '0.7'], ['tv-zid.html', 'weekly', '0.7'],
    ['paneli-ili-lamperija.html', 'weekly', '0.7'], ['spc-ili-laminat.html', 'weekly', '0.7'],
    ['akusticni-paneli-kancelarija.html', 'weekly', '0.7'], ['dostava-crna-gora.html', 'weekly', '0.7'],
    ['uslovi.html', 'weekly', '0.7'], ['reklamacije.html', 'weekly', '0.7'],
    ['privatnost.html', 'weekly', '0.7'],
];
foreach ($statika as [$f, $fr, $pr]) {
    // Inspiracija dobija SVE fotografije prostora — to je stranica zbog koje
    // uopste i pravimo sitemap sa slikama.
    $sl = '';
    if ($f === 'inspiracija.html') {
        foreach ($P as $p) {
            foreach (($p['gallery'] ?? []) as $gi => $g) {
                $kat = $katImena[$p['category'] ?? ''] ?? 'Zidni panel';
                $sl .= mmhSlikaXML($g, $p['name'] . ' u enterijeru ' . ($gi + 1) . ' – ' . $kat);
            }
        }
    }
    $dodaj($BAZA . '/' . $f, $fr, $pr, $sl);
}

// ---- Kategorije ----------------------------------------------------------
$poKat = [];
foreach ($P as $p) {
    $k = $p['category'] ?? '';
    if ($k !== '') $poKat[$k][] = $p;
}
foreach ($katImena as $k => $ime) {
    $sl = '';
    foreach (($poKat[$k] ?? []) as $p) {
        if (!empty($p['image'])) $sl .= mmhSlikaXML($p['image'], $p['name'] . ' – ' . $ime);
    }
    $dodaj($BAZA . '/kategorija/' . $k, 'weekly', '0.9', $sl);
}

// ---- Proizvodi -----------------------------------------------------------
foreach ($P as $p) {
    $ime = $p['name'] ?? '';
    $kat = $katImena[$p['category'] ?? ''] ?? 'Zidni panel';
    $sl  = '';
    if (!empty($p['image'])) $sl .= mmhSlikaXML($p['image'], $ime . ' – ' . $kat);
    foreach (($p['gallery'] ?? []) as $gi => $g) {
        $sl .= mmhSlikaXML($g, $ime . ' u enterijeru ' . ($gi + 1) . ' – ' . $kat);
    }
    $dodaj($BAZA . '/' . mmhSlugProizvoda($p), 'weekly', '0.8', $sl);
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
echo '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
echo implode('', $izlaz);
echo "</urlset>\n";
