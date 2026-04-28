<?php
/**
 * Make My Home – File sync from GitHub
 * Access: /admin/sync.php?key=mkhsync2025
 */
session_start();
if (empty($_SESSION['admin_logged'])) {
    http_response_code(403);
    die('Pristup odbijen – mora si prijavljen kao admin.');
}
if (($_GET['key'] ?? '') !== 'mkhsync2025') {
    die('Pogre&scaron;an klju&ccaron;. Dodaj ?key=mkhsync2025 u URL.');
}

$branch = 'claude/build-product-website-6CvHG';
$repo   = 'makemyhomeme-blip/makemyhome-website';
$base   = "https://raw.githubusercontent.com/{$repo}/{$branch}";
$root   = dirname(__DIR__);

$files = [
    // HTML stranice
    $root . '/index.html'       => $base . '/index.html',
    $root . '/product.html'     => $base . '/product.html',
    $root . '/products.html'    => $base . '/products.html',
    $root . '/about.html'       => $base . '/about.html',
    $root . '/contact.html'     => $base . '/contact.html',
    $root . '/korpa.html'       => $base . '/korpa.html',
    $root . '/checkout.html'    => $base . '/checkout.html',
    $root . '/hvala.html'       => $base . '/hvala.html',
    // JS
    $root . '/js/cart.js'       => $base . '/js/cart.js',
    $root . '/js/products.js'   => $base . '/js/products.js',
    // CSS
    $root . '/css/style-v5.css' => $base . '/css/style-v5.css',
    // Admin
    __DIR__ . '/dashboard.php'  => $base . '/admin/dashboard.php',
    __DIR__ . '/actions.php'    => $base . '/admin/actions.php',
];

echo '<pre style="font-family:monospace;font-size:14px;padding:20px;background:#111;color:#eee;min-height:100vh;">';
echo "=== Make My Home &ndash; Sync fajlova ===\n\n";

$allOk = true;
foreach ($files as $dest => $url) {
    $label = str_replace($root . '/', '', $dest);
    echo str_pad("Preuzimam: $label", 45) . " ... ";
    $ctx = stream_context_create(['http' => ['timeout' => 20]]);
    $content = @file_get_contents($url, false, $ctx);
    if ($content === false || strlen($content) < 10) {
        echo "<span style='color:#e74c3c;'>GREŠKA – nije moguće preuzeti fajl.</span>\n";
        $allOk = false;
    } else {
        $bytes = file_put_contents($dest, $content);
        if ($bytes === false) {
            echo "<span style='color:#e74c3c;'>GREŠKA – nije moguće pisati na disk.</span>\n";
            $allOk = false;
        } else {
            echo "<span style='color:#2ecc71;'>OK (" . round($bytes / 1024, 1) . " KB)</span>\n";
        }
    }
}

echo "\n";
if ($allOk) {
    echo "<span style='color:#c9a86c;font-weight:bold;'>Sve ažurirano! Svi fajlovi su sinhronizovani.</span>\n";
} else {
    echo "<span style='color:#e74c3c;'>Neke stavke NISU ažurirane. Provjeri greške iznad.</span>\n";
}
echo "\n<a href='../index.html' style='color:#c9a86c;'>&rarr; Otvori sajt</a>  ";
echo "<a href='dashboard.php' style='color:#c9a86c;margin-left:20px;'>&rarr; Admin panel</a>\n";
echo '</pre>';
