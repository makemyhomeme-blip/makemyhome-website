<?php
/**
 * Make My Home – Jednokratni backup cijelog sajta u ZIP
 * Web: /admin/backup.php?key=mkhbackup2026 (requires admin session)
 * Pravi ZIP trenutnog stanja sajta i daje link za skidanje.
 * Sam se obriše nakon kreiranja ZIP-a.
 */
session_start();
if (empty($_SESSION['admin_logged'])) {
    http_response_code(403);
    die('Pristup odbijen – mora si prijavljen kao admin.');
}
if (($_GET['key'] ?? '') !== 'mkhbackup2026') {
    die('Pogrešan ključ.');
}
@set_time_limit(300);
@ini_set('memory_limit', '512M');

$root = dirname(__DIR__);
$stamp = date('Ymd-His');
$token = substr(md5(uniqid()), 0, 8);
$zipName = "makemyhome-backup-{$stamp}-{$token}.zip";
$zipPath = $root . '/' . $zipName;

echo '<pre style="font-family:monospace;font-size:14px;padding:20px;background:#111;color:#eee;min-height:100vh;">';
echo "=== Make My Home – Backup sajta ===\n\n";

if (!class_exists('ZipArchive')) {
    echo "<span style='color:#e74c3c;'>GREŠKA: ZipArchive nije dostupan na serveru.</span>\n</pre>";
    @unlink(__FILE__);
    exit;
}

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    echo "<span style='color:#e74c3c;'>GREŠKA: ne mogu kreirati ZIP.</span>\n</pre>";
    @unlink(__FILE__);
    exit;
}

// Preskoči: druge backup zipove, .git, privremene fajlove
$skip = ['.git', 'node_modules'];
$count = 0;
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($it as $file) {
    $path = $file->getRealPath();
    $rel  = substr($path, strlen($root) + 1);
    // Preskoči sam ZIP i ostale backupove
    if (preg_match('/^makemyhome-backup-.*\.zip$/', $rel)) continue;
    if (preg_match('/\.(zip|tar|gz)$/i', basename($rel)) && stripos($rel, 'backup') !== false) continue;
    $first = explode('/', $rel)[0];
    if (in_array($first, $skip, true)) continue;

    if ($file->isDir()) {
        $zip->addEmptyDir($rel);
    } else {
        $zip->addFile($path, $rel);
        $count++;
    }
}
$zip->close();

$sizeMb = round(filesize($zipPath) / 1048576, 2);
$url = 'https://makemyhome.me/' . $zipName;

echo "Fajlova u backupu: <span style='color:#2ecc71;'>{$count}</span>\n";
echo "Veličina ZIP-a:    <span style='color:#2ecc71;'>{$sizeMb} MB</span>\n\n";
echo "<span style='color:#c9a86c;font-weight:bold;'>GOTOVO! Skini backup ovdje:</span>\n\n";
echo "<a href='{$url}' style='color:#2ecc71;font-size:18px;font-weight:bold;'>&darr; {$zipName}</a>\n\n";
echo "<span style='color:#aaa;'>Nakon što skineš fajl, obriši ga sa servera (preko File Manager-a) da ne stoji javno.</span>\n";
echo "</pre>";

// Skripta se sama briše, ali ZIP ostaje za skidanje
@unlink(__FILE__);
