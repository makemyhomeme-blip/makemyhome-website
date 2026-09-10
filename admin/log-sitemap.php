<?php
/**
 * Dijagnostika: STA Googlebot stvarno dobije kad povuce /sitemap.xml.
 *
 * Sitemap je tehnicki ispravan (validan XML, 200, staticki, brz), a Search
 * Console i dalje javlja "Temporary processing error". Jedino sto se ne vidi
 * spolja jeste kojim statusom server odgovara Googlebot-u u trenutku kad on
 * povuce sitemap — a na ovom hostingu firewall (LFD/CSF) zna da PREKINE vezu
 * (HTTP 000) posjetiocu koji napravi vise zahtjeva zaredom. Ako to radi i
 * Googlebot-u, njegov zahtjev za sitemapom padne i GSC prijavi gresku.
 *
 * Ovaj skript cita SIROVI Apache access log i pokazuje statuse za /sitemap.xml
 * (posebno za Googlebot). Ako veza puca na mrezi (000), taj red se u logu i NE
 * pojavi — pa je i odsustvo Googlebot-ovih sitemap zahtjeva (dok GSC pokusava)
 * samo po sebi trag da ga firewall odbija prije Apache-a.
 *
 * Pristup: samo admin sesija ili deploy token.
 */
if (php_sapi_name() !== 'cli') {
    $token = (string) ($_GET['token'] ?? '');
    if (!hash_equals('697113eed3779e99c7bcbf5953fecc2f541250ef', $token)) {
        require_once __DIR__ . '/sesija.php';
        if (empty($_SESSION['admin_logged'])) { http_response_code(403); die('403 – samo admin.'); }
    }
}
header('Content-Type: text/plain; charset=utf-8');

$home = dirname(dirname(dirname(__DIR__))); // /home/mmhdecor  (…/public_html/makemyhome.me/admin -> gore 3)
if (!is_dir($home . '/access-logs')) $home = '/home/mmhdecor';

$kandidati = [
    "$home/access-logs/makemyhome.me",
    "$home/access-logs/makemyhome.me-ssl_log",
    "$home/access-logs/makemyhome.me-ssl_log-Aug-2026.gz",
    "$home/access-logs/makemyhome.me-ssl_log.processed",
    "$home/logs/makemyhome.me",
    "/usr/local/apache/domlogs/mmhdecor/makemyhome.me",
    "/usr/local/apache/domlogs/mmhdecor/makemyhome.me-ssl_log",
    "/var/log/apache2/domlogs/mmhdecor/makemyhome.me",
];

echo "== Trazim access log ==\n";
$log = null;
foreach ($kandidati as $f) {
    $ok = @is_readable($f);
    echo ($ok ? "[OK] " : "[--] ") . $f . "\n";
    if ($ok && !$log) $log = $f;
}
echo "\n-- Sadrzaj access-logs/ --\n";
$logovi = [];
foreach (glob("$home/access-logs/*") ?: [] as $f) {
    $cit = is_readable($f);
    echo "  " . $f . ($cit ? "  (citljiv, " . @filesize($f) . " B)" : "  (nije citljiv)") . "\n";
    // Sve citljive makemyhome logove uzimamo — HTTPS saobracaj (Googlebot) je u
    // -ssl_log fajlu, koji je veci; obicni GET je u ne-ssl fajlu.
    if ($cit && stripos($f, 'makemyhome') !== false) $logovi[] = $f;
}
foreach ($kandidati as $f) if (@is_readable($f)) $logovi[] = $f;
$logovi = array_values(array_unique($logovi));

if (!$logovi) { echo "\nNijedan access log nije citljiv iz PHP-a.\n"; exit; }

echo "\n== Citam logove: " . implode(', ', array_map('basename', $logovi)) . " ==\n";
$linije = [];
foreach ($logovi as $log) {
    $sz   = filesize($log);
    $fp   = fopen($log, 'rb');
    $read = 4000000;
    if ($sz > $read) fseek($fp, -$read, SEEK_END);
    $data = fread($fp, $read);
    fclose($fp);
    foreach (explode("\n", $data) as $l) $linije[] = $l;
}
$sm = [];
foreach ($linije as $l) {
    if (stripos($l, 'sitemap') !== false) $sm[] = $l;
}
echo "Redova koji spominju 'sitemap' (u zadnjih ~1.5 MB loga): " . count($sm) . "\n\n";

$statG = []; $statO = [];
foreach ($sm as $l) {
    if (!preg_match('#"\s*(?:GET|HEAD)\s+/sitemap\.[a-z]+[^"]*"\s+(\d{3})#i', $l, $m)) continue;
    $code = $m[1];
    $isG  = (stripos($l, 'Googlebot') !== false || stripos($l, 'Google-InspectionTool') !== false || stripos($l, 'Google-Site') !== false);
    if ($isG) $statG[$code] = ($statG[$code] ?? 0) + 1;
    else      $statO[$code] = ($statO[$code] ?? 0) + 1;
}
echo "Googlebot -> /sitemap.*  statusi: " . (json_encode($statG) ?: '{}') . "\n";
echo "Ostali    -> /sitemap.*  statusi: " . (json_encode($statO) ?: '{}') . "\n\n";

// Sveukupni Googlebot saobracaj (svi zahtjevi) — da vidimo da li ga server
// uopste posluzuje normalno ili ga odbija (403/406/429/5xx).
$gAll = []; $gLast = '';
foreach ($linije as $l) {
    if (stripos($l, 'Googlebot') === false && stripos($l, 'Google-InspectionTool') === false) continue;
    if (preg_match('#"\s+(\d{3})\s#', $l, $m)) $gAll[$m[1]] = ($gAll[$m[1]] ?? 0) + 1;
    $gLast = $l;
}
echo "Googlebot -> SVI zahtjevi, statusi: " . (json_encode($gAll) ?: '{}') . "\n";
echo "Zadnji Googlebot red: " . ($gLast ?: '(nema Googlebot u logu)') . "\n\n";

echo "== Zadnjih 30 redova sa 'sitemap' ==\n";
foreach (array_slice($sm, -30) as $l) echo $l . "\n";
