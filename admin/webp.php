<?php
/**
 * Pravljenje .webp verzija pored postojecih slika.
 *
 * Zasto:
 * U images/products stoji 263 fotografije, ukupno 34 MB, prosjecno 134 kB.
 * Kategorija sa 28 letvica povuce preko 6 MB dok se skroluje. WebP je na
 * istom kvalitetu upola manji, a svi pregledaci ga danas citaju.
 *
 * Zasto pored, a ne umjesto:
 * Originali se NE diraju. Pravi se fajl istog imena sa nastavkom .webp, a
 * .htaccess ga servira samo pregledacu koji je u Accept zaglavlju rekao da
 * razumije WebP. Ko ne razumije — dobije original. Ako se webp obrise,
 * sajt radi kao i prije; nema tacke u kojoj slika nestane.
 *
 * Zasto se ne deployuje sa lokalnog:
 * Vlasnik dodaje slike kroz admin, pa na serveru stoje fotografije kojih u
 * gitu nema. Zato ovaj alat radi na serveru, nad onim sto tamo stvarno jeste.
 *
 * Web: /admin/webp.php?key=mkhwebp2025          (probni mod, samo racuna)
 *      /admin/webp.php?key=mkhwebp2025&apply=1  (stvarno pravi fajlove)
 *
 * Alat je bezbjedan za ponovno pokretanje: preskace slike koje vec imaju
 * svjeziji .webp od originala, pa se moze pustiti koliko god puta treba.
 * Ako istekne vrijeme izvrsavanja, samo ga pokreni ponovo — nastavlja dalje.
 */
require_once __DIR__ . '/sesija.php';
if (empty($_SESSION['admin_logged'])) {
    http_response_code(403);
    die('Pristup odbijen – moras biti prijavljen kao admin.');
}
if (($_GET['key'] ?? '') !== 'mkhwebp2025') {
    die('Pogresan kljuc. Dodaj ?key=mkhwebp2025 u adresu.');
}

@set_time_limit(0);
@ini_set('memory_limit', '512M');

$root  = dirname(__DIR__);
$probni = ($_GET['apply'] ?? '') !== '1';
$KVALITET = 82;

// Folderi cije se slike konvertuju. Sve ostalo (favicon, logo, ikone) se ne
// dira — te su ionako sitne, a logo je PNG sa prozirnoscu.
$FOLDERI = ['images/products', 'images/categories', 'images/rooms', 'images/hero-slides'];

echo '<pre style="font-family:monospace;font-size:13px;padding:20px;background:#111;color:#eee;min-height:100vh;">';
echo "=== WebP verzije slika ===\n\n";

if (!function_exists('imagewebp')) {
    echo "GD nema podrsku za WebP na ovom serveru. Nista nije uradjeno.\n</pre>";
    exit;
}

echo $probni
    ? "MOD: PROBNI — nista se ne upisuje na disk.\n      Dodaj &apply=1 u adresu da se fajlovi stvarno naprave.\n\n"
    : "MOD: PRIMJENA — prave se .webp fajlovi pored originala.\n      Originali se NE diraju.\n\n";

$ukPrije = 0;   // zbir originala koji su obradjeni
$ukPoslije = 0; // zbir napravljenih webp-ova
$napravljeno = 0;
$preskoceno = 0;
$gore = 0;      // webp ispao veci od originala — takav se brise, nema smisla
$greske = [];

foreach ($FOLDERI as $rel) {
    $dir = $root . '/' . $rel;
    if (!is_dir($dir)) { echo "  (nema foldera $rel)\n"; continue; }

    $fajlovi = glob($dir . '/*.{jpg,jpeg,JPG,JPEG,png,PNG}', GLOB_BRACE) ?: [];
    echo "--- $rel: " . count($fajlovi) . " slika\n";

    foreach ($fajlovi as $put) {
        $webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $put);
        if ($webp === $put) continue;

        $velOrig = filesize($put);

        // Vec postoji i noviji je od originala — nema sta da se radi
        if (is_file($webp) && filemtime($webp) >= filemtime($put)) {
            $preskoceno++;
            continue;
        }

        if ($probni) {
            $napravljeno++;
            $ukPrije += $velOrig;
            $ukPoslije += (int)($velOrig * 0.55); // gruba procjena za prikaz
            continue;
        }

        $info = @getimagesize($put);
        if (!$info) { $greske[] = basename($put) . ' (nije citljiva slika)'; continue; }

        $img = null;
        if ($info[2] === IMAGETYPE_PNG)       $img = @imagecreatefrompng($put);
        elseif ($info[2] === IMAGETYPE_JPEG)  $img = @imagecreatefromjpeg($put);
        if (!$img) { $greske[] = basename($put) . ' (GD je ne moze otvoriti)'; continue; }

        if ($info[2] === IMAGETYPE_PNG) {
            imagealphablending($img, false);
            imagesavealpha($img, true);
        }

        $priv = $webp . '.tmp';               // prvo u privremeni, pa preimenuj —
        $ok = @imagewebp($img, $priv, $KVALITET);  // da .htaccess nikad ne uhvati polusnimljen fajl
        imagedestroy($img);

        if (!$ok || !is_file($priv)) {
            @unlink($priv);
            $greske[] = basename($put) . ' (snimanje nije uspjelo)';
            continue;
        }

        $velNovi = filesize($priv);
        if ($velNovi >= $velOrig) {
            // Kod nekih PNG-ova sa malo boja WebP ispadne veci. Tada ga nema
            // smisla drzati — original je bolji i .htaccess ga i dalje servira.
            @unlink($priv);
            if (is_file($webp)) @unlink($webp);
            $gore++;
            continue;
        }

        if (!@rename($priv, $webp)) {
            @unlink($priv);
            $greske[] = basename($put) . ' (preimenovanje nije uspjelo)';
            continue;
        }
        @touch($webp, filemtime($put) + 1);  // da sljedece pokretanje zna da je svjez

        $napravljeno++;
        $ukPrije += $velOrig;
        $ukPoslije += $velNovi;
    }
}

$mb = fn($b) => number_format($b / 1048576, 2, ',', '.') . ' MB';

echo "\n==================================================\n";
echo "napravljeno .webp fajlova : $napravljeno\n";
echo "vec postojali (preskoceno): $preskoceno\n";
if ($gore)   echo "webp ispao veci, odbacen : $gore\n";
if ($greske) {
    echo "greske (" . count($greske) . "):\n";
    foreach (array_slice($greske, 0, 20) as $g) echo "   - $g\n";
    if (count($greske) > 20) echo "   ... i jos " . (count($greske) - 20) . "\n";
}
if ($ukPrije > 0) {
    echo "\noriginali obradjenih slika: " . $mb($ukPrije) . "\n";
    echo "iste slike kao webp       : " . $mb($ukPoslije) . "\n";
    echo "ustedjeno                 : " . $mb($ukPrije - $ukPoslije)
       . '  (' . round((1 - $ukPoslije / $ukPrije) * 100) . "%)\n";
}
if ($probni) {
    echo "\nOvo je bila samo procjena. Za stvarno pravljenje fajlova:\n";
    echo "   /admin/webp.php?key=mkhwebp2025&apply=1\n";
}
echo "</pre>";
