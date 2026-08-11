<?php
/**
 * Make My Home – File sync from GitHub
 * Web:  /admin/sync.php?key=mkhsync2025  (requires admin session)
 * CLI:  php sync.php
 */
// OPcache reset — mora biti na vrhu da bi novi fajlovi odmah bili aktivni
if (function_exists('opcache_reset')) opcache_reset();

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    require_once __DIR__ . '/sesija.php';
    if (empty($_SESSION['admin_logged'])) {
        http_response_code(403);
        die('Pristup odbijen – mora si prijavljen kao admin.');
    }
    if (($_GET['key'] ?? '') !== 'mkhsync2025') {
        die('Pogre&scaron;an klju&ccaron;. Dodaj ?key=mkhsync2025 u URL.');
    }
}

$branch = 'claude/build-product-website-6CvHG';
$repo   = 'makemyhomeme-blip/makemyhome-website';
$base   = "https://raw.githubusercontent.com/{$repo}/{$branch}";
$root   = dirname(__DIR__);

// --- Preuzimanje po SHA-u commita ---------------------------------------
// raw.githubusercontent.com se posluzuje preko CDN-a koji kesira po cvorovima.
// Adresa sa imenom grane je uvijek ista, pa je cvor koji opsluzuje ovaj server
// umio da vrati PRETHODNU verziju fajla — sync bi ispisao OK, a na disk upisao
// staro. Tako je product.php dva puta ostao star iako je push prosao.
// Adresa sa SHA-om commita je jedinstvena za svaku izmjenu, pa CDN nema sta da
// vrati iz kesa. Ako API ne odgovori, vracamo se na ime grane (staro ponasanje).
$sha = null;
$apiOdgovor = fetchUrl("https://api.github.com/repos/{$repo}/commits/"
                       . rawurlencode($branch) . '?per_page=1');
if ($apiOdgovor !== false) {
    $j = json_decode($apiOdgovor, true);
    if (!empty($j['sha']) && preg_match('/^[0-9a-f]{40}$/', $j['sha'])) $sha = $j['sha'];
}
if ($sha) $base = "https://raw.githubusercontent.com/{$repo}/{$sha}";
$sync_izvor = $sha ? ('commit ' . substr($sha, 0, 7)) : ('grana ' . $branch . ' (API nedostupan)');

// --- Spisak fajlova ------------------------------------------------------
// Spisak stoji u admin/sync-lista.php i skida se PRVI, prije svega ostalog.
// Ranije je stajao ovdje, u sync.php, pa je prvi sync poslije dodavanja novog
// fajla radio po starom spisku: povukao bi product.php koji trazi novi fajl,
// a sam novi fajl ne bi. Sajt bi vratio 500 dok se sync ne pokrene drugi put.
$listaPut = __DIR__ . '/sync-lista.php';
$svjezaLista = fetchUrl($base . '/admin/sync-lista.php');
if ($svjezaLista !== false && strpos($svjezaLista, 'return function') !== false) {
    file_put_contents($listaPut, $svjezaLista);
    if (function_exists('opcache_invalidate')) opcache_invalidate($listaPut, true);
}
if (!is_file($listaPut)) {
    die('Nema admin/sync-lista.php, a ni sa GitHuba se ne moze skinuti. Sync prekinut.');
}
$dajListu = require $listaPut;
$files = $dajListu($base, $root, __DIR__);


/** Fetch a URL using best available method */
function fetchUrl(string $url): string|false {
    $zaglavlja = ['Cache-Control: no-cache', 'Pragma: no-cache'];

    // Method 1: PHP curl extension
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => $zaglavlja,
            CURLOPT_USERAGENT      => 'MakeMyHome-Sync/1.0',
        ]);
        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($data !== false && $code === 200 && strlen($data) > 5) return $data;
    }
    // Method 2: exec curl binary (-f = fail on HTTP >= 400, ne snimaj error stranice)
    if (function_exists('exec')) {
        $out = []; $ret = 0;
        exec("curl -fsSL --max-time 30 -A 'MakeMyHome-Sync/1.0' -H 'Cache-Control: no-cache' " . escapeshellarg($url) . " 2>/dev/null", $out, $ret);
        if ($ret === 0 && !empty($out)) {
            $data = implode("\n", $out);
            if (strlen($data) > 5 && trim($data) !== '404: Not Found') return $data;
        }
    }
    // Method 3: file_get_contents (needs allow_url_fopen=On)
    if (ini_get('allow_url_fopen')) {
        $ctx  = stream_context_create(['http' => ['timeout' => 30, 'header' => "Cache-Control: no-cache\r\nUser-Agent: MakeMyHome-Sync/1.0\r\n"]]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data !== false && strlen($data) > 5) return $data;
    }
    return false;
}

if ($isCli) {
    echo "=== Make My Home – Sync fajlova (CLI) ===\n";
    echo "Izvor: {$sync_izvor}\n\n";
} else {
    echo '<pre style="font-family:monospace;font-size:14px;padding:20px;background:#111;color:#eee;min-height:100vh;">';
    echo "=== Make My Home &ndash; Sync fajlova ===\n";
    echo "Izvor: " . htmlspecialchars($sync_izvor) . "\n\n";
}

$allOk = true;
foreach ($files as $dest => $url) {
    $label   = str_replace($root . '/', '', $dest);
    $padded  = str_pad("Preuzimam: $label", 45);
    if ($isCli) echo $padded . " ... ";
    else        echo $padded . " ... ";
    flush();

    $content = fetchUrl($url);
    if ($content === false) {
        $msg = "GREŠKA – nije moguće preuzeti fajl.";
        echo $isCli ? "$msg\n" : "<span style='color:#e74c3c;'>$msg</span>\n";
        $allOk = false;
    } else {
        @mkdir(dirname($dest), 0755, true);
        $bytes = file_put_contents($dest, $content);
        if ($bytes !== false && function_exists('opcache_invalidate')) opcache_invalidate($dest, true);
        if ($bytes === false) {
            $msg = "GREŠKA – nije moguće pisati na disk.";
            echo $isCli ? "$msg\n" : "<span style='color:#e74c3c;'>$msg</span>\n";
            $allOk = false;
        } else {
            $msg = "OK (" . round($bytes / 1024, 1) . " KB)";
            echo $isCli ? "$msg\n" : "<span style='color:#2ecc71;'>$msg</span>\n";
        }
    }
    if (!$isCli) ob_flush();
}

echo "\n";
if ($allOk) {
    $msg = "Sve ažurirano! Svi fajlovi su sinhronizovani.";
    echo $isCli ? "$msg\n" : "<span style='color:#c9a86c;font-weight:bold;'>$msg</span>\n";
} else {
    $msg = "Neke stavke NISU ažurirane. Provjeri greške iznad.";
    echo $isCli ? "$msg\n" : "<span style='color:#e74c3c;'>$msg</span>\n";
}

if (!$isCli) {
    echo "\n<a href='../' style='color:#c9a86c;'>&rarr; Otvori sajt</a>  ";
    echo "<a href='dashboard.php' style='color:#c9a86c;margin-left:20px;'>&rarr; Admin panel</a>\n";
    echo '</pre>';
}
