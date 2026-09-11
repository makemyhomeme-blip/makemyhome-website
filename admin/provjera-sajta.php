<?php
/**
 * Provjera zdravlja sajta — SAMO CITA, nista ne mijenja.
 *
 * Trazi stvarne greske koje se ne vide spolja a kvare i utisak i SEO:
 *  1) Slike: glavna + galerija — postoji li fajl na disku, je li validna slika,
 *     i je li "prazna" (jednobojni uzorak / sicusan fajl) tamo gdje bi trebala
 *     biti fotografija.
 *  2) Tanak sadrzaj: proizvodi bez opisa ili sa vrlo kratkim opisom / bez
 *     osobina — to su bas stranice koje Google "crawl-uje ali ne indeksira".
 *  3) Sudar adresa (slug): ako dva proizvoda daju istu adresu, jedan postaje
 *     nedostupan (404). Racuna se istom funkcijom kao na sajtu (php/slug.php).
 *
 * Pristup: admin sesija ili deploy token.
 */
if (php_sapi_name() !== 'cli') {
    $token = (string) ($_GET['token'] ?? '');
    if (!hash_equals('697113eed3779e99c7bcbf5953fecc2f541250ef', $token)) {
        require_once __DIR__ . '/sesija.php';
        if (empty($_SESSION['admin_logged'])) { http_response_code(403); die('403 – samo admin.'); }
    }
}
header('Content-Type: text/plain; charset=utf-8');

$root = dirname(__DIR__);
require_once $root . '/php/slug.php';

$P = json_decode(@file_get_contents($root . '/data/products.json'), true) ?: [];
if (isset($P['products'])) $P = $P['products'];
echo "Proizvoda ukupno: " . count($P) . "\n";
echo str_repeat('=', 60) . "\n";

/** Da li je JPEG jednobojan (prazan uzorak boje)? */
function mmhJednobojna(string $aps): bool {
    $im = @imagecreatefromjpeg($aps);
    if (!$im) return false;
    $w = imagesx($im); $h = imagesy($im);
    $mn = 255; $mx = 0;
    for ($i = 0; $i < 120; $i++) {
        $rgb = imagecolorat($im, random_int(0, $w - 1), random_int(0, $h - 1));
        $g = (($rgb >> 16 & 255) + ($rgb >> 8 & 255) + ($rgb & 255)) / 3;
        $mn = min($mn, $g); $mx = max($mx, $g);
    }
    imagedestroy($im);
    return ($mx - $mn) < 8;
}

$problemi = ['nema_slike' => [], 'prazna_slika' => [], 'tanak_opis' => [], 'bez_osobina' => [], 'slug_sudar' => []];
$slugovi = [];

foreach ($P as $p) {
    $sku = $p['sku'] ?? ('id' . ($p['id'] ?? '?'));
    $ime = $p['name'] ?? $sku;

    // --- slike ---
    $sve = [];
    if (!empty($p['image']))  $sve[] = $p['image'];
    foreach (($p['gallery'] ?? []) as $g) $sve[] = $g;
    foreach ($sve as $rel) {
        $aps = $root . '/' . ltrim($rel, '/');
        if (!is_file($aps)) { $problemi['nema_slike'][] = "$sku ($ime): $rel"; continue; }
        $sz = @filesize($aps);
        if ($sz !== false && $sz < 5000 && mmhJednobojna($aps)) {
            $problemi['prazna_slika'][] = "$sku ($ime): $rel (" . round($sz / 1024, 1) . " kB, jednobojna)";
        }
    }

    // --- sadrzaj ---
    $opis = trim(strip_tags((string) ($p['description'] ?? '')));
    if ($opis === '') $problemi['tanak_opis'][] = "$sku ($ime): NEMA opisa";
    elseif (mb_strlen($opis) < 120) $problemi['tanak_opis'][] = "$sku ($ime): opis " . mb_strlen($opis) . " znakova";
    $osob = $p['features'] ?? $p['highlights'] ?? [];
    if (!$osob) $problemi['bez_osobina'][] = "$sku ($ime)";

    // --- slug ---
    $slug = function_exists('mmhSlugProizvoda') ? mmhSlugProizvoda($p) : '';
    if ($slug !== '') {
        if (isset($slugovi[$slug])) $problemi['slug_sudar'][] = "$slug  <-  {$slugovi[$slug]}  I  $sku";
        else $slugovi[$slug] = $sku;
    }
}

foreach ([
    'nema_slike'  => 'SLIKE KOJE FALE NA DISKU (404 slika)',
    'prazna_slika'=> 'PRAZNE / JEDNOBOJNE SLIKE (gdje bi trebala fotografija)',
    'slug_sudar'  => 'SUDAR ADRESA (dva proizvoda ista adresa -> 404)',
    'tanak_opis'  => 'TANAK ILI PRAZAN OPIS (slabo za indeksiranje)',
    'bez_osobina' => 'BEZ OSOBINA / HIGHLIGHTS',
] as $k => $naslov) {
    $lst = $problemi[$k];
    echo "\n## $naslov — " . count($lst) . "\n";
    foreach (array_slice($lst, 0, 60) as $r) echo "  - $r\n";
    if (count($lst) > 60) echo "  ... i jos " . (count($lst) - 60) . "\n";
}
echo "\n" . str_repeat('=', 60) . "\nGotovo.\n";
