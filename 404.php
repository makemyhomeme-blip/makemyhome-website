<?php
// Redirect stari WordPress /product/slug/ URL-ovi na ispravne produkt stranice
$uri = strtolower(trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/'));

if (preg_match('#^product/([^/]+)(/feed)?/?$#', $uri, $m)) {
    require_once __DIR__ . '/php/slug-match.php';
    $products = json_decode(@file_get_contents(__DIR__ . '/data/products.json'), true) ?: [];
    header('Location: ' . mmhSlugTarget($m[1], $products), true, 301);
    exit;
}

// Nije product slug — prikaži normalnu 404 stranicu
http_response_code(404);
readfile(__DIR__ . '/404.html');
