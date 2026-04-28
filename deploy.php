<?php
// Deploy script - OBRIŠI NAKON UPOTREBE
if (($_GET['k'] ?? '') !== 'mmhdeploy9x7') { http_response_code(403); die('403'); }

$branch = 'claude/build-product-website-6CvHG';
$repo   = 'makemyhomeme-blip/makemyhome-website';
$base   = "https://raw.githubusercontent.com/{$repo}/{$branch}";
$root   = __DIR__;

$files = [
    'index.html'       => $base . '/index.html',
    'product.html'     => $base . '/product.html',
    'products.html'    => $base . '/products.html',
    'about.html'       => $base . '/about.html',
    'contact.html'     => $base . '/contact.html',
    'korpa.html'       => $base . '/korpa.html',
    'checkout.html'    => $base . '/checkout.html',
    'hvala.html'       => $base . '/hvala.html',
    'js/cart.js'       => $base . '/js/cart.js',
    'js/products.js'   => $base . '/js/products.js',
    'css/style-v5.css' => $base . '/css/style-v5.css',
    'admin/sync.php'   => $base . '/admin/sync.php',
];

$ok = 0; $fail = 0;
echo '<pre style="font:14px monospace;padding:20px;background:#111;color:#eee;">';
echo "=== Deploy ===\n\n";
foreach ($files as $rel => $url) {
    $dest = $root . '/' . $rel;
    $content = @file_get_contents($url, false, stream_context_create(['http'=>['timeout'=>20]]));
    if ($content === false || strlen($content) < 50) {
        echo str_pad($rel, 35) . " GRESKA\n"; $fail++;
    } else {
        file_put_contents($dest, $content);
        echo str_pad($rel, 35) . " OK (" . round(strlen($content)/1024,1) . " KB)\n"; $ok++;
    }
}
echo "\n$ok OK, $fail greska\n";
echo "\nOBRISI ovaj fajl! => unlink(__FILE__) ili kroz cPanel File Manager.\n";
echo '</pre>';
