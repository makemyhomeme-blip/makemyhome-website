<?php
/**
 * Make My Home – Jednokratno čišćenje WordPress ostataka
 * Web: /admin/cleanup.php?key=mkhclean2026 (requires admin session)
 * Briše SAMO wp-* ostatke. Nikad ne dira custom sajt (images, css, js, data, *.html, *.php).
 * Sam se obriše nakon izvršavanja.
 */
session_start();
if (empty($_SESSION['admin_logged'])) {
    http_response_code(403);
    die('Pristup odbijen – mora si prijavljen kao admin.');
}
if (($_GET['key'] ?? '') !== 'mkhclean2026') {
    die('Pogrešan ključ.');
}

$root = dirname(__DIR__);

function dirSize($dir) {
    if (!is_dir($dir)) return 0;
    $size = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
        $size += $f->getSize();
    }
    return $size;
}

function rrmdir($dir) {
    if (!is_dir($dir)) return;
    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
    }
    @rmdir($dir);
}

// SAMO ovi WordPress ostaci - ništa drugo
$wpDirs = ['wp-content', 'wp-admin', 'wp-includes'];
$wpFiles = ['wp-login.php', 'wp-cron.php', 'xmlrpc.php', 'wp-config.php',
            'wp-config-sample.php', 'wp-settings.php', 'wp-load.php',
            'wp-blog-header.php', 'wp-links-opml.php', 'wp-mail.php',
            'wp-signup.php', 'wp-activate.php', 'wp-trackback.php',
            'wp-comments-post.php', 'readme.html', 'license.txt'];

echo '<pre style="font-family:monospace;font-size:14px;padding:20px;background:#111;color:#eee;min-height:100vh;">';
echo "=== Make My Home – Čišćenje WordPress ostataka ===\n\n";

$freed = 0;
foreach ($wpDirs as $d) {
    $path = $root . '/' . $d;
    if (is_dir($path)) {
        $sz = dirSize($path);
        $freed += $sz;
        rrmdir($path);
        $ok = is_dir($path) ? "GREŠKA - nije obrisan" : "OBRISAN (" . round($sz/1048576, 2) . " MB)";
        echo str_pad("Folder: $d", 28) . " ... <span style='color:#2ecc71;'>$ok</span>\n";
    } else {
        echo str_pad("Folder: $d", 28) . " ... (ne postoji)\n";
    }
}
foreach ($wpFiles as $f) {
    $path = $root . '/' . $f;
    if (file_exists($path)) {
        $freed += filesize($path);
        @unlink($path);
        echo str_pad("Fajl: $f", 28) . " ... <span style='color:#2ecc71;'>obrisan</span>\n";
    }
}

echo "\n<span style='color:#c9a86c;font-weight:bold;'>GOTOVO! Oslobođeno ukupno " . round($freed/1048576, 2) . " MB.</span>\n";
echo "</pre>";

// Sam se obriši
@unlink(__FILE__);
