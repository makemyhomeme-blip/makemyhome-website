<?php
// Redirect stari WordPress /product/slug/ URL-ovi na ispravne produkt stranice
$uri = strtolower(trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/'));

if (preg_match('#^product/([^/]+)(/feed)?/?$#', $uri, $m)) {
    $slug     = $m[1];
    $products = json_decode(@file_get_contents(__DIR__ . '/data/products.json'), true) ?: [];
    $bestId   = null;
    $bestPct  = 0;
    foreach ($products as $p) {
        $sku      = strtolower($p['sku']  ?? '');
        $nameSlug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($p['name'] ?? '')), '-');
        if ($sku === $slug || $nameSlug === $slug) { $bestId = $p['id']; break; }
        similar_text($slug, $nameSlug, $pct);
        if ($pct > $bestPct && $pct > 55) { $bestPct = $pct; $bestId = $p['id']; }
    }
    $target = $bestId
        ? 'https://makemyhome.me/product.html?id=' . (int)$bestId
        : 'https://makemyhome.me/products.html';
    header('Location: ' . $target, true, 301);
    exit;
}

// Nije product slug — prikaži normalnu 404 stranicu
http_response_code(404);
readfile(__DIR__ . '/404.html');
