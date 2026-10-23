<?php
/**
 * Jedno mjesto koje pravi adresu proizvoda i kategorije.
 * Koriste ga product.php, products.php, sitemap i slug-match.php,
 * pa ne moze da se desi da dva fajla naprave razlicitu adresu za isti proizvod.
 *
 * Adresa proizvoda:  /paneli/<tip>-<ime>     npr. /paneli/drveni-panel-golden-teak
 * Adresa kategorije: /kategorija/<kljuc>     npr. /kategorija/3d-letvice
 *
 * Tip se dodaje ispred imena jer imena kao "Deva", "Perla", "Blanc" sama za sebe
 * ne govore nista ni kupcu ni Google-u.
 */

/** Pretvara tekst u dio adrese: mala slova, bez kvacica, crtice umjesto razmaka. */
function mmhSlugify(string $s): string {
    $s = mb_strtolower(trim($s), 'UTF-8');
    $mapa = ['č'=>'c','ć'=>'c','š'=>'s','ž'=>'z','đ'=>'dj','–'=>'-','—'=>'-','×'=>'x',
             'Č'=>'c','Ć'=>'c','Š'=>'s','Ž'=>'z','Đ'=>'dj',
             // Strana slova iz imena proizvoda (Hermès i slicno). JavaScript verzija
             // ovo radi kroz normalize('NFKD'), pa PHP mora dati isti rezultat.
             'à'=>'a','á'=>'a','â'=>'a','ä'=>'a','ã'=>'a','å'=>'a','æ'=>'ae',
             'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
             'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
             'ò'=>'o','ó'=>'o','ô'=>'o','ö'=>'o','õ'=>'o','ø'=>'o',
             'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u',
             'ý'=>'y','ÿ'=>'y','ñ'=>'n','ç'=>'c','ß'=>'ss'];
    $s = strtr($s, $mapa);
    $s = preg_replace('/[^a-z0-9]+/u', '-', $s);
    return trim(preg_replace('/-+/', '-', $s), '-');
}

/** Rijec koja se dodaje ispred imena, po kategoriji. */
function mmhTipZaKategoriju(string $kat): string {
    static $tip = [
        'bambus-paneli'     => 'bambus-panel',
        'bambus-drveni'     => 'drveni-panel',
        'bambus-tekstilni'  => 'tekstilni-panel',
        'bambus-mermerni'   => 'mermerni-panel',
        'bambus-metalni'    => 'metalni-panel',
        'bambus-kozni'      => 'kozni-panel',
        'classic'           => 'classic-panel',
        '3d-letvice'        => '3d-letvica',
        'akusticni-paneli'  => 'akusticni-panel',
        'aluminijum-lajsne' => 'alu-lajsna',
        'spc-pod'           => 'spc-pod',
        'pu-kamen'          => 'pu-kamen',
        'mdf'               => 'mdf-panel',
        'flex-stone'        => 'flex-stone',
    ];
    return $tip[$kat] ?? 'panel';
}

/** Adresa proizvoda bez pocetne kose crte: "paneli/drveni-panel-golden-teak" */
function mmhSlugProizvoda(array $p): string {
    $ime = mmhSlugify((string)($p['name'] ?? ''));
    $tip = mmhTipZaKategoriju((string)($p['category'] ?? ''));
    // Ako ime vec sadrzi tip ("3D Letvica – Honey Oak", "Kožni Panel PW001"),
    // ne ponavljaj ga. Poredi se po cijelim dijelovima izmedju crtica, jer
    // "MDF001" sadrzi slova "mdf" a nije rijec "mdf" — takvom imenu tip treba.
    $dijelovi  = $ime === '' ? [] : explode('-', $ime);
    $prvaRijec = explode('-', $tip)[0];
    if ($ime !== '' && !in_array($prvaRijec, $dijelovi, true)) {
        $ime = $tip . '-' . $ime;
    }
    if ($ime === '') $ime = 'proizvod-' . (int)($p['id'] ?? 0);
    // Duge adrese se skracuju na granici rijeci — bez sjeckanja usred rijeci.
    if (strlen($ime) > 60) {
        $skraceno = substr($ime, 0, 60);
        $zadnja   = strrpos($skraceno, '-');
        $ime      = $zadnja > 20 ? substr($skraceno, 0, $zadnja) : $skraceno;
        $ime      = rtrim($ime, '-');
    }
    return 'paneli/' . $ime;
}

/** Puna adresa proizvoda */
function mmhUrlProizvoda(array $p): string {
    return 'https://makemyhome.me/' . mmhSlugProizvoda($p);
}

/** Adresa kategorije bez pocetne kose crte: "kategorija/3d-letvice" */
function mmhSlugKategorije(string $kat): string {
    return 'kategorija/' . $kat;
}

/** Puna adresa kategorije */
function mmhUrlKategorije(string $kat): string {
    return 'https://makemyhome.me/' . mmhSlugKategorije($kat);
}

/** Nadje proizvod po adresi. Vraca null ako ga nema. */
function mmhProizvodPoSlugu(string $slug, array $proizvodi): ?array {
    $slug = trim(strtolower($slug), '/ ');
    $slug = preg_replace('#^paneli/#', '', $slug);
    foreach ($proizvodi as $p) {
        if (mmhSlugProizvoda($p) === 'paneli/' . $slug) return $p;
    }
    return null;
}
