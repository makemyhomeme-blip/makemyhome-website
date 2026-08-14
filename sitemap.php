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
// php/dimenzije.php se OVDJE ne koristi — bio je ucitavan bez potrebe i uz
// njega i kes dimenzija slika od 10 kB, pri svakom zahtjevu za sitemapom.

// ---- Kes ----------------------------------------------------------------
// Google je u Search Console-u za dvije adrese javio "Sitemap: Temporary
// processing error". Sitemap se do sada pravio IZNOVA pri svakom zahtjevu:
// citanje products.json od 383 kB, obrada 117 proizvoda i ispis 447 slika,
// 0,6 do 1,5 sekunde. Za Googlebot je to nepotreban rizik — ako mu odgovor
// zakasni, prijavi gresku i ne procita spisak.
//
// Sada se gotov XML cuva u fajlu i sluzi se odatle. Ponovo se pravi samo kad
// se promijeni nesto od cega zavisi: podaci o proizvodima, kategorije ili sam
// ovaj fajl. Sadrzaj je isti do znaka, samo stize odmah.
$kesFajl = __DIR__ . '/data/sitemap-kes.xml';

// Spisak SVEGA od cega zavisi bilo koji datum u sitemapu.
//
// Ranije su ovdje bila samo cetiri fajla: dva sa podacima, slug.php i ovaj
// fajl. To je bilo premalo. Datum izmjene svake stranice racuna se i iz njenog
// HTML-a odnosno PHP-a (about.html, decor-box.php, product.php…), pa bi
// izmjena teksta na stranici promijenila njen lastmod — a kes se ne bi
// osvjezio i sitemap bi i dalje javljao stari datum. Google po lastmod-u
// odlucuje kad ce ponovo doci; stari datum znaci da ne dolazi.
//
// Zato spisak mora biti isti onaj iz kog se datumi i racunaju. Drzi se na
// jednom mjestu da se ne mogu razici.
$mmhStatika = [
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
$izvori = array_merge(
    ['data/products.json', 'data/categories.json', 'php/slug.php', 'sitemap.php',
     'index.html', 'pocetna.php', 'product.php', 'products.php',
     'inspiracija.php', 'cjenovnik.php', 'decor-box.php', 'data/decor-box-style.json'],
    array_column($mmhStatika, 0)
);
$najnoviji = 0;
foreach ($izvori as $f) {
    $t = @filemtime(__DIR__ . '/' . $f);
    if ($t && $t > $najnoviji) $najnoviji = $t;
}

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=0, must-revalidate');
if ($najnoviji) header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $najnoviji) . ' GMT');

if (is_file($kesFajl) && filemtime($kesFajl) >= $najnoviji && filesize($kesFajl) > 1000) {
    readfile($kesFajl);
    exit;
}

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

// Datum izmjene po adresi — ne izmisljamo, uzimamo sa diska.
//
// Ranije je SVIH 149 adresa dobijalo isti datum, i to onaj kad je zadnji put
// mijenjan products.json. To je Googleu davalo pogresan signal u oba smjera:
// stranica o firmi bi "se mijenjala" svaki put kad se doda proizvod, a
// izmjena izgleda ili teksta na vodicu se ne bi vidjela uopste. Google po
// lastmod-u odlucuje koliko brzo ce ponovo doci, pa je vrijedjelo srediti.
//
// Sada svaka adresa nosi datum onoga sto JOJ stvarno odredjuje sadrzaj:
// za stranicu proizvoda to su podaci + product.php + stilovi, za staticnu
// stranicu njen HTML, i tako redom. Uzima se najnoviji od tih datuma.
function mmhVrijeme(array $fajlovi): int
{
    $naj = 0;
    foreach ($fajlovi as $f) {
        $t = @filemtime(__DIR__ . '/' . ltrim($f, '/'));
        if ($t && $t > $naj) $naj = $t;
    }
    return $naj ?: time();
}

// U racun ulazi samo ono sto mijenja SADRZAJ — tekst, cijene, slike. Stilovi
// i skripte se namjerno NE broje: Google trazi da lastmod znaci stvarnu
// promjenu sadrzaja, a da se racuna i CSS, sitna izmjena izgleda bi javila da
// se promijenilo svih 149 stranica i signal bi izgubio vrijednost.
$mmhOkvir  = [];
$mmhPodaci = ['data/products.json', 'data/categories.json'];

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
$dodaj = function (string $loc, string $freq, string $prio, string $slike = '', int $kada = 0) use (&$izlaz) {
    $izlaz[] = "  <url>\n"
             . '    <loc>' . mmhX($loc) . "</loc>\n"
             . '    <lastmod>' . date('Y-m-d', $kada ?: time()) . "</lastmod>\n"
             . '    <changefreq>' . $freq . "</changefreq>\n"
             . '    <priority>' . $prio . "</priority>\n"
             . $slike
             . "  </url>\n";
};

// ---- Pocetna i staticne stranice ----------------------------------------
$dodaj($BAZA . '/', 'daily', '1.0',
       mmhSlikaXML('images/showcase-room.jpg', 'Make My Home Decor – zidni paneli, Podgorica'),
       mmhVrijeme(array_merge($mmhPodaci, ['index.html', 'pocetna.php'])));

foreach ($mmhStatika as [$f, $fr, $pr]) {
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
    // Cetiri stranice sastavlja PHP; njima se gleda i taj fajl, ne samo .html
    $izvori = array_merge($mmhOkvir, [$f]);
    if ($f === 'inspiracija.html')  $izvori = array_merge($mmhPodaci, ['inspiracija.php']);
    if ($f === 'cjenovnik.html')    $izvori = array_merge($mmhPodaci, ['cjenovnik.php']);
    if ($f === 'products.html')     $izvori = array_merge($mmhPodaci, ['products.php']);
    if ($f === 'decor-box.html')    $izvori = array_merge($mmhOkvir, ['decor-box.php', 'data/decor-box-style.json']);
    $dodaj($BAZA . '/' . $f, $fr, $pr, $sl, mmhVrijeme($izvori));
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
    $dodaj($BAZA . '/kategorija/' . $k, 'weekly', '0.9', $sl,
           mmhVrijeme(array_merge($mmhPodaci, ['products.php'])));
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
    $dodaj($BAZA . '/' . mmhSlugProizvoda($p), 'weekly', '0.8', $sl,
           mmhVrijeme(array_merge($mmhPodaci, ['product.php'])));
}

$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
     . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n"
     . '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n"
     . implode('', $izlaz)
     . "</urlset>\n";

// Prvo u privremeni fajl pa preimenovanje — da Googlebot nikad ne uhvati
// polovicno upisan sitemap ako naidje bas u trenutku pravljenja.
$priv = $kesFajl . '.tmp';
if (@file_put_contents($priv, $xml, LOCK_EX) !== false) {
    @rename($priv, $kesFajl);
}
echo $xml;
