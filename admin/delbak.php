<?php
/**
 * Make My Home – Jednokratno brisanje backup ZIP-ova sa servera
 * Web: /admin/delbak.php?key=mkhdel2026 (requires admin session)
 * Briše SAMO makemyhome-backup-*.zip fajlove. Sam se obriše.
 */
session_start();
if (empty($_SESSION['admin_logged'])) {
    http_response_code(403);
    die('Pristup odbijen – mora si prijavljen kao admin.');
}
if (($_GET['key'] ?? '') !== 'mkhdel2026') {
    die('Pogrešan ključ.');
}

$root = dirname(__DIR__);
echo '<pre style="font-family:monospace;font-size:14px;padding:20px;background:#111;color:#eee;min-height:100vh;">';
echo "=== Brisanje backup ZIP-ova ===\n\n";

$found = glob($root . '/makemyhome-backup-*.zip');
if (!$found) {
    echo "Nema backup ZIP fajlova za brisanje.\n";
} else {
    foreach ($found as $f) {
        $sz = round(filesize($f) / 1048576, 2);
        if (@unlink($f)) {
            echo "OBRISAN: " . basename($f) . " ({$sz} MB)\n";
        } else {
            echo "GREŠKA pri brisanju: " . basename($f) . "\n";
        }
    }
}
echo "\n<span style='color:#c9a86c;font-weight:bold;'>GOTOVO!</span>\n</pre>";

@unlink(__FILE__);
