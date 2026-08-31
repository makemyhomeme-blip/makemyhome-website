<?php
// Redirect stari WordPress /product/slug/ URL-ovi na ispravne produkt stranice
$uri = strtolower(trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/'));

// Stari WooCommerce query oblik ?product=slug (npr. /?product=spc02). .htaccess ga
// interno prebaci ovamo (cuvajuci query) — razrjesava se ISTIM resolverom kao
// /product/slug/, pa ide na pravu adresu jednim 301 skokom, umjesto homepage 200.
if (isset($_GET['product']) && $_GET['product'] !== '') {
    require_once __DIR__ . '/php/slug-match.php';
    $products = json_decode(@file_get_contents(__DIR__ . '/data/products.json'), true) ?: [];
    header('Location: ' . mmhSlugTarget($_GET['product'], $products), true, 301);
    exit;
}

if (preg_match('#^product/([^/]+)(/feed)?/?$#', $uri, $m)) {
    require_once __DIR__ . '/php/slug-match.php';
    $products = json_decode(@file_get_contents(__DIR__ . '/data/products.json'), true) ?: [];
    header('Location: ' . mmhSlugTarget($m[1], $products), true, 301);
    exit;
}

// Nije product slug — prikaži normalnu 404 stranicu
http_response_code(404);
readfile(__DIR__ . '/404.html');
