<?php
/**
 * Make My Home – Jednokratno čišćenje zaostalih privremenih skripti
 * Web: /admin/janitor.php?key=mkhjanitor2026 (requires admin session)
 * Briše samo poznate privremene fajlove i sebe.
 */
session_start();
if (empty($_SESSION['admin_logged'])) {
    http_response_code(403);
    die('Pristup odbijen – mora si prijavljen kao admin.');
}
if (($_GET['key'] ?? '') !== 'mkhjanitor2026') {
    die('Pogrešan ključ.');
}

$root  = dirname(__DIR__);
$admin = __DIR__;

echo '<pre style="font-family:monospace;font-size:14px;padding:20px;background:#111;color:#eee;min-height:100vh;">';
echo "=== Čišćenje zaostalih fajlova ===\n\n";

$targets = [
    $admin . '/cleanup.php',
    $admin . '/backup.php',
    $admin . '/delbak.php',
    $root  . '/go.php',
    $root  . '/go2.php',
    $root  . '/deploy.php',
];
foreach (glob($root . '/makemyhome-backup-*.zip') as $z) $targets[] = $z;

foreach ($targets as $t) {
    if (file_exists($t)) {
        echo (@unlink($t) ? "OBRISAN: " : "GREŠKA: ") . str_replace($root . '/', '', $t) . "\n";
    }
}

echo "\n<span style='color:#c9a86c;font-weight:bold;'>GOTOVO!</span>\n</pre>";
@unlink(__FILE__);
