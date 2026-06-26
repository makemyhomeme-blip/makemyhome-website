<?php
// Redirect stari WordPress /product/slug/ URL-ovi na ispravne produkt stranice
$uri  = $_SERVER['REQUEST_URI'] ?? '';
$slug = trim(preg_replace('#^/product/#', '', strtolower($uri)), '/');
$slug = preg_replace('/\?.*$/', '', $slug); // strip query string

$products = json_decode(@file_get_contents(__DIR__ . '/../data/products.json'), true) ?: [];

$bestId   = null;
$bestScore = 0;

foreach ($products as $p) {
    $name = strtolower($p['name'] ?? '');
    $sku  = strtolower($p['sku']  ?? '');

    // SKU exact match
    if ($sku && $sku === $slug) { $bestId = $p['id']; break; }

    // Slugified name match
    $nameSlug = preg_replace('/[^a-z0-9]+/', '-', $name);
    $nameSlug = trim($nameSlug, '-');
    if ($nameSlug === $slug) { $bestId = $p['id']; break; }

    // Partial match — score by overlap
    similar_text($slug, $nameSlug, $pct);
    if ($pct > $bestScore && $pct > 55) {
        $bestScore = $pct;
        $bestId    = $p['id'];
    }
}

if ($bestId) {
    header('Location: https://makemyhome.me/product.html?id=' . (int)$bestId, true, 301);
} else {
    header('Location: https://makemyhome.me/products.html', true, 301);
}
exit;
