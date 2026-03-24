<?php
/**
 * Make My Home – One-time file sync from GitHub
 * Access: /admin/sync.php?key=mkhsync2025
 * DELETE THIS FILE after use!
 */
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    die('Pristup odbijen – mora si prijavljen kao admin.');
}
if (($_GET['key'] ?? '') !== 'mkhsync2025') {
    die('Pogrešan ključ. Dodaj ?key=mkhsync2025 u URL.');
}

$branch = 'claude/build-product-website-6CvHG';
$repo   = 'makemyhomeme-blip/makemyhome-website';
$base   = "https://raw.githubusercontent.com/{$repo}/{$branch}";

$files = [
    __DIR__ . '/dashboard.php' => $base . '/admin/dashboard.php',
    __DIR__ . '/actions.php'   => $base . '/admin/actions.php',
];

echo '<pre style="font-family:monospace;font-size:14px;padding:20px;">';
echo "=== Make My Home – Sync fajlova ===\n\n";

$allOk = true;
foreach ($files as $dest => $url) {
    echo "Preuzimam: " . basename($dest) . " ... ";
    $ctx = stream_context_create(['http' => ['timeout' => 15]]);
    $content = @file_get_contents($url, false, $ctx);
    if ($content === false || strlen($content) < 500) {
        echo "GREŠKA – nije moguće preuzeti fajl.\n";
        $allOk = false;
    } else {
        $bytes = file_put_contents($dest, $content);
        if ($bytes === false) {
            echo "GREŠKA – nije moguće pisati na disk.\n";
            $allOk = false;
        } else {
            echo "OK (" . round($bytes / 1024, 1) . " KB)\n";
        }
    }
}

echo "\n";
if ($allOk) {
    echo "Sve ažurirano! Fajlovi su sinhronizovani.\n";
    echo "\nMOŽEŠ OBRISATI ovaj fajl (sync.php) sada.\n";
    echo "\n<a href='dashboard.php?section=cat-images' style='color:#c9a86c;'>→ Idi na Slike Kategorija</a>\n";
} else {
    echo "Neke stavke NISU ažurirane. Provjeri greške iznad.\n";
}
echo '</pre>';
