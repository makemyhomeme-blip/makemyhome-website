<?php
/**
 * ruter.php — lijepe adrese na lokalnom PHP serveru.
 *
 * Zasto postoji:
 * `php -S` ne cita .htaccess, pa na lokalnoj kopiji adrese /kategorija/<kljuc>
 * i /paneli/<ime> ne postoje — padaju na 404 stranicu. Alat koji mjeri sajt u
 * pregledacu je zbog toga za osam stranica izmjerio istu 404 stranicu i to
 * prijavio kao nalaz: "cijene se pojavljuju tek poslije JavaScripta". Nijedna
 * od tih stranica nije ni bila ucitana.
 *
 * Ovdje su prepisana ista pravila koja .htaccess primjenjuje na serveru, pa
 * lokalna kopija odgovara na iste adrese kao i pravi sajt.
 *
 * Koristi se SAMO za provjere, ne deployuje se:
 *     php -S 127.0.0.1:8899 alat/ruter.php
 */
$put = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$fajl = __DIR__ . '/..' . $put;

/* PRESLIKAVANJA IDU PRVA — .htaccess na serveru prepisuje ove adrese na PHP
   iako istoimeni .html fajl postoji na disku. Prva verzija ovog rutera je
   prvo gledala postoji li fajl, pa je za /products.html posluzila zastarjeli
   staticni fajl (0 kartica kategorija, 2 h2) umjesto products.php (8 kartica,
   11 h2). Mjerenje je zbog toga za tu stranicu pokazalo da "JavaScript dodaje
   66% teksta" — a razlika je bila u tome sto lokalna kopija nije servirala
   istu stranicu kao sajt. */
if ($put === '/' || $put === '/index.html') {
    require __DIR__ . '/../pocetna.php';
    return true;
}
if (preg_match('~^/kategorija/([a-z0-9-]+)/?$~', $put, $m)) {
    $_GET['k'] = $m[1];
    require __DIR__ . '/../products.php';
    return true;
}
if (preg_match('~^/paneli/([a-z0-9-]+)/?$~', $put, $m)) {
    $_GET['slug'] = $m[1];
    require __DIR__ . '/../product.php';
    return true;
}
foreach ([
    '/products.html'    => 'products.php',
    '/product.html'     => 'product.php',
    '/cjenovnik.html'   => 'cjenovnik.php',
    '/inspiracija.html' => 'inspiracija.php',
    '/decor-box.html'   => 'decor-box.php',
    '/sitemap.xml'      => 'sitemap.php',
] as $adresa => $skripta) {
    if ($put === $adresa) {
        require __DIR__ . '/../' . $skripta;
        return true;
    }
}

// Pravi fajl koji postoji — posluzi ga takav kakav jeste (slike, css, js).
if ($put !== '/' && is_file($fajl) && !preg_match('~\.(php)$~', $put)) {
    return false;
}

// Sve ostalo: obican fajl ili 404
if (is_file($fajl)) {
    return false;
}
http_response_code(404);
if (is_file(__DIR__ . '/../404.php')) { require __DIR__ . '/../404.php'; return true; }
if (is_file(__DIR__ . '/../404.html')) { readfile(__DIR__ . '/../404.html'); return true; }
echo '404';
return true;
