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
 * Zasto se ipak servira STATICKI fajl a ne PHP:
 * Sitemap se pravi iz podataka (products.json, kategorije, slike) da bi uvijek
 * bio tacan kad vlasnik doda proizvod. Ali PRAVITI ga pri svakom zahtjevu je
 * skupo (citanje 383 kB JSON-a, obrada 117 proizvoda, ispis ~450 slika —
 * 1 do 2,5 sekunde). Google-ov obradjivac sitemapa ima kratak timeout; kad mu
 * odgovor zakasni prijavi "Sitemap: Temporary processing error" i ne procita
 * spisak. Na dijeljenom hostingu, pod opterecenjem, to se desavalo iznova.
 *
 * Zato se gotov XML upisuje u PRAVI staticki fajl /sitemap.xml, koji Apache
 * servira za ~50 ms bez ijednog reda PHP-a. Fajl se regenerise pri svakom
 * sync-u/deployu (admin/sync.php ga pozove na kraju) i preko cron-a svakih par
 * minuta, pa je uvijek tacan. .htaccess servira taj staticki fajl kad postoji,
 * a pada na ovaj PHP samo ako ga (jos) nema — tada ga PHP napravi, upise za
 * sljedeci put, i posluzi.
 *
 * Fajl /sitemap.xml pravi SAM SERVER i NE salje se sa lokalnog (nije u repou
 * ni u sync-listi) — inace bi ga svaki sync prepisao zastarjelom verzijom.
 */
require_once __DIR__ . '/php/slug.php';
require_once __DIR__ . '/php/lastmod.php';

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

// Datum izmjene po adresi — ne izmisljamo, uzimamo sa diska. U racun ulazi
// samo ono sto mijenja SADRZAJ (tekst, cijene, slike), ne CSS/JS: Google trazi
// da lastmod znaci stvarnu promjenu sadrzaja.
function mmhVrijeme(array $fajlovi): int
{
    $naj = 0;
    foreach ($fajlovi as $f) {
        $t = @filemtime(__DIR__ . '/' . ltrim($f, '/'));
        if ($t && $t > $naj) $naj = $t;
    }
    return $naj ?: time();
}

/**
 * Sastavlja cio XML sitemapa iz podataka i vraca ga kao string.
 */
function mmhSitemapGradi(): string
{
    $BAZA = 'https://makemyhome.me';
    $P = json_decode(@file_get_contents(__DIR__ . '/data/products.json'), true) ?: [];
    if (isset($P['products'])) $P = $P['products'];

    // Datum izmjene po POJEDINOM proizvodu — vidi php/lastmod.php. Sablon
    // (product.php) se mijenja pri skoro svakom deployu; da to ne bi javljalo
    // da su se SVE stranice promijenile, datum se racuna iz SADRZAJA proizvoda.
    $mmhDatumi = mmhDatumiProizvoda($P);
    $mmhDatum  = function (array $p) use ($mmhDatumi): int {
        $d = $mmhDatumi[(string)($p['id'] ?? '')] ?? '';
        return $d ? (int)strtotime($d) : 0;
    };

    $mmhStatika = [
        ['products.html', 'weekly', '0.9'], ['cjenovnik.html', 'weekly', '0.9'],
        ['inspiracija.html', 'weekly', '0.9'], ['montaza.html', 'weekly', '0.7'],
        ['faq.html', 'weekly', '0.7'], ['about.html', 'weekly', '0.7'],
        ['contact.html', 'weekly', '0.7'], ['decor-box.html', 'weekly', '0.7'],
        ['paneli-za-kupatilo.html', 'weekly', '0.7'], ['tv-zid.html', 'weekly', '0.7'], ['spc-ili-laminat.html', 'weekly', '0.7'],
        ['akusticni-paneli-kancelarija.html', 'weekly', '0.7'], ['dostava-crna-gora.html', 'weekly', '0.7'],
        ['blog.html', 'weekly', '0.7'],
        ['dekorativni-zidni-paneli-vodic.html', 'weekly', '0.7'], ['kako-izabrati-panele-po-prostoriji.html', 'weekly', '0.7'],
        ['pu-kamen-izgled-kamena.html', 'weekly', '0.7'], ['koliko-kostaju-zidni-paneli.html', 'weekly', '0.7'],
        ['uslovi.html', 'weekly', '0.7'], ['reklamacije.html', 'weekly', '0.7'],
        ['privatnost.html', 'weekly', '0.7'],
    ];

    $katImena = [
        'bambus-paneli' => 'Bambus Paneli', 'bambus-drveni' => 'Drveni Paneli',
        'bambus-tekstilni' => 'Tekstilni Paneli', 'bambus-mermerni' => 'Mermerni Paneli',
        'bambus-metalni' => 'Metalni Paneli', 'bambus-kozni' => 'Kožni Paneli',
        '3d-letvice' => '3D Letvice', 'akusticni-paneli' => 'Akustični Paneli',
        'aluminijum-lajsne' => 'Aluminijum Lajsne', 'spc-pod' => 'SPC Pod',
        'pu-kamen' => 'PU Kamen', 'classic' => 'Classic Paneli',
        'mdf' => 'MDF Paneli', 'flex-stone' => 'Flex Stone',
    ];

    $mmhPodaci = ['data/products.json', 'data/categories.json'];

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
           max(mmhVrijeme(['index.html']), max([0] + array_map($mmhDatum, $P))));

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
        $izvori = [$f];
        if ($f === 'inspiracija.html')  $izvori = $mmhPodaci;
        if ($f === 'cjenovnik.html')    $izvori = $mmhPodaci;
        if ($f === 'products.html')     $izvori = $mmhPodaci;
        if ($f === 'decor-box.html')    $izvori = ['decor-box.php', 'data/decor-box-style.json'];
        $dodaj($BAZA . '/' . $f, $fr, $pr, $sl, mmhVrijeme($izvori));
    }

    // ---- Kategorije ----------------------------------------------------------
    $poKat = [];
    foreach ($P as $p) {
        $k = $p['category'] ?? '';
        if ($k !== '') $poKat[$k][] = $p;
    }

    // bambus-paneli je NADREDJENA kategorija — nijedan proizvod je ne nosi u
    // polju "category", nego se na njoj prikazuju svi bambus podtipovi.
    $mmhBambus = ['bambus-drveni', 'bambus-tekstilni', 'bambus-mermerni',
                  'bambus-kozni', 'bambus-metalni', 'classic'];
    $poKat['bambus-paneli'] = [];
    foreach ($mmhBambus as $k) {
        foreach (($poKat[$k] ?? []) as $p) $poKat['bambus-paneli'][] = $p;
    }
    foreach ($katImena as $k => $ime) {
        $sl = '';
        foreach (($poKat[$k] ?? []) as $p) {
            if (!empty($p['image'])) $sl .= mmhSlikaXML($p['image'], $p['name'] . ' – ' . $ime);
        }
        // Kategorija se mijenja kad se promijeni neki proizvod u njoj — ne kad
        // se dira products.php.
        $kadaKat = 0;
        foreach (($poKat[$k] ?? []) as $p) { $t = $mmhDatum($p); if ($t > $kadaKat) $kadaKat = $t; }
        $dodaj($BAZA . '/kategorija/' . $k, 'weekly', '0.9', $sl,
               $kadaKat ?: mmhVrijeme($mmhPodaci));
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
               $mmhDatum($p) ?: mmhVrijeme($mmhPodaci));
    }

    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
         . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n"
         . '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n"
         . implode('', $izlaz)
         . "</urlset>\n";
}

/**
 * Pravi sitemap i upisuje ga u STATICKI fajl /sitemap.xml, ali samo ako se
 * sadrzaj stvarno promijenio — da vrijeme izmjene (Last-Modified) fajla ostane
 * stabilno i Google ne misli da se sitemap mijenja pri svakom cron prolazu.
 * Upis ide prvo u .tmp pa atomsko preimenovanje, da Googlebot nikad ne uhvati
 * polovicno upisan fajl. Vraca true ako je fajl zaista prepisan.
 */
function mmhSitemapUpisiStaticki(): bool
{
    $xml  = mmhSitemapGradi();
    $put  = __DIR__ . '/sitemap.xml';
    if (is_file($put) && @file_get_contents($put) === $xml) return false;
    $priv = $put . '.tmp';
    if (@file_put_contents($priv, $xml, LOCK_EX) !== false && @rename($priv, $put)) return true;
    // Ako preimenovanje ne uspije, probaj direktan upis (bolje nego nista).
    return @file_put_contents($put, $xml, LOCK_EX) !== false;
}

// --- Ukljucen kao biblioteka (npr. iz admin/sync.php) — samo definisi
//     funkcije, ne izvrsavaj nista. Ova provjera MORA biti prije CLI grane:
//     sync se preko cron-a pokrece kao `php sync.php` (CLI SAPI) i ukljucuje
//     ovaj fajl, pa bi inace okinuo CLI granu umjesto da samo ucita funkcije.
if (defined('MMH_SITEMAP_LIB')) return;

// --- CLI: `php sitemap.php` samo regenerise staticki fajl -------------------
if (PHP_SAPI === 'cli') {
    echo mmhSitemapUpisiStaticki() ? "sitemap.xml: regenerisan\n" : "sitemap.xml: nepromijenjen\n";
    return;
}

// --- Web serviranje (fallback) ---------------------------------------------
// Dovde se dolazi SAMO kad statickog /sitemap.xml nema (.htaccess ga inace
// servira direktno). Napravi ga, upisi za sljedeci put, i posluzi odmah.
$xml = mmhSitemapGradi();
$put = __DIR__ . '/sitemap.xml';
$priv = $put . '.tmp';
if (@file_put_contents($priv, $xml, LOCK_EX) !== false) @rename($priv, $put);

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo $xml;
