<?php
require_once __DIR__ . '/slug.php';
/**
 * Pronalazi odrediste za stari WordPress /product/slug/ URL.
 *
 * Redoslijed:
 *   1. tacan SKU (npr. bw003 -> BW003)
 *   2. tacan slug imena
 *   3. preklapanje rijeci (npr. linear-travertine-white -> "Flex Stone White Linear Travertine")
 *   4. proizvod vise ne postoji -> vodi na NJEGOVU kategoriju, ne na opsti katalog
 *
 * Vraca puni URL na koji treba 301-ovati.
 */
function mmhSlugTarget(string $slug, array $products): string
{
    $BASE = 'https://makemyhome.me';
    $slug = strtolower(trim($slug, '/ '));
    if ($slug === '') return $BASE . '/products.html';

    $norm = fn(string $s) => preg_replace('/[^a-z0-9]+/', '', strtolower($s));
    $tok  = function (string $s): array {
        $s = mb_strtolower($s, 'UTF-8');
        $s = strtr($s, ['č'=>'c','ć'=>'c','ž'=>'z','š'=>'s','đ'=>'d','×'=>' ']);
        $t = preg_split('/[^a-z0-9]+/', $s, -1, PREG_SPLIT_NO_EMPTY);
        // izbaci rijeci koje ne razlikuju proizvode
        $stop = ['flex','stone','panel','paneli','fleksibilni','kamen','m','cm','1','2','0','6','12'];
        return array_values(array_diff($t, $stop));
    };

    $slugNorm = $norm($slug);
    $slugTok  = $tok($slug);

    // 1 + 2: tacan pogodak
    foreach ($products as $p) {
        if ($norm($p['sku'] ?? '') !== '' && $norm($p['sku']) === $slugNorm) {
            return mmhUrlProizvoda($p);
        }
        if ($norm($p['name'] ?? '') === $slugNorm) {
            return mmhUrlProizvoda($p);
        }
    }

    // 3: preklapanje rijeci — bira se onaj sa najvise zajednickih rijeci
    $best = null; $bestScore = 0;
    foreach ($products as $p) {
        $nt = $tok($p['name'] ?? '');
        if (!$nt || !$slugTok) continue;
        $zajedno = count(array_intersect($slugTok, $nt));
        if ($zajedno === 0) continue;
        // normalizuj da duga imena ne pobjeduju samo zato sto imaju vise rijeci
        $score = $zajedno / max(count($slugTok), 1) + $zajedno / max(count($nt), 1);
        if ($score > $bestScore) { $bestScore = $score; $best = $p; }
    }
    // prag 1.2 — sa 1.0 je 'rouge-stone-white' pogadjao 'White Marble' samo zbog rijeci 'white'
    if ($best && $bestScore >= 1.2) {
        return mmhUrlProizvoda($best);
    }

    // 4: proizvod vise ne postoji — posalji ga na kategoriju kojoj pripada
    // Prefiksi su preuzeti iz stvarnih sifara u ponudi. Ranije su BW, SW, MW, CQ i PW
    // svi vodili na opsti "bambus-paneli", a CS, JS, UV, TR, TS, TW i YL nisu ni
    // postojali — takvi ugaseni proizvodi zavrsavali su na spisku svih kategorija.
    // Redoslijed je od duzeg ka kracem, da 'pu' ne pokupi 'pw' i slicno.
    $poPrefiksu = [
        'aku' => 'akusticni-paneli',
        'i3d' => '3d-letvice',
        'spc' => 'spc-pod',
        'mdf' => 'mdf',
        'pu'  => 'pu-kamen',
        'bw'  => 'bambus-tekstilni',
        'sw'  => 'bambus-mermerni',
        'yl'  => 'bambus-mermerni',
        'tr'  => 'bambus-mermerni',
        'uv'  => 'bambus-mermerni',   // stara WP kategorija "uv-paneli" = mermerni UV print
        'mw'  => 'bambus-drveni',
        'cq'  => 'bambus-drveni',
        'pw'  => 'bambus-kozni',
        'cs'  => 'classic',
        'js'  => 'bambus-metalni',
        'ts'  => 'bambus-metalni',
        'tw'  => 'mdf',
        'ps'  => 'pu-kamen',        // stari PS-F30 i slicno iz WP kategorije "pu-stone"
    ];
    foreach ($poPrefiksu as $pref => $kat) {
        if (str_starts_with($slugNorm, $pref)) return mmhUrlKategorije($kat);
    }
    // Lajsne se zovu L1..L8 — samo slovo L pa cifra, da 'linear-travertine'
    // ne bi zavrsio kao lajsna.
    if (preg_match('/^l\d/', $slugNorm)) return mmhUrlKategorije('aluminijum-lajsne');
    $poRijeci = [
        'travertine' => 'flex-stone', 'travertino' => 'flex-stone', 'granite' => 'flex-stone',
        'romantine'  => 'flex-stone', 'weaving'    => 'flex-stone', 'dolomitic' => 'flex-stone',
        'rouge'      => 'flex-stone', 'luna'       => 'flex-stone',
        'muretto'    => 'pu-kamen',   'mushroom'   => 'pu-kamen',   'brick'   => 'pu-kamen',
        'rock'       => 'pu-kamen',   'obloga'     => 'pu-kamen',   'cut-stone' => 'pu-kamen',
        'letvica'    => '3d-letvice', 'letvice'    => '3d-letvice',
        'lajsna'     => 'aluminijum-lajsne', 'lajsne' => 'aluminijum-lajsne',
        'akusticni'  => 'akusticni-paneli', 'aku'   => 'akusticni-paneli',
        'laminat'    => 'spc-pod',    'pod'        => 'spc-pod',
    ];
    foreach ($poRijeci as $rijec => $kat) {
        if (str_contains($slug, $rijec)) return mmhUrlKategorije($kat);
    }

    return $BASE . '/products.html';
}
