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
    session_start();
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

$files = [
    // HTML stranice
    $root . '/404.html'         => $base . '/404.html',
    $root . '/index.html'       => $base . '/index.html',
    $root . '/product.html'     => $base . '/product.html',
    $root . '/products.html'    => $base . '/products.html',
    $root . '/about.html'       => $base . '/about.html',
    $root . '/contact.html'     => $base . '/contact.html',
    $root . '/korpa.html'       => $base . '/korpa.html',
    $root . '/checkout.html'    => $base . '/checkout.html',
    $root . '/hvala.html'       => $base . '/hvala.html',
    $root . '/faq.html'         => $base . '/faq.html',
    $root . '/montaza.html'     => $base . '/montaza.html',
    $root . '/decor-box.html'   => $base . '/decor-box.html',
    $root . '/privatnost.html'  => $base . '/privatnost.html',
    $root . '/uslovi.html'      => $base . '/uslovi.html',
    $root . '/reklamacije.html' => $base . '/reklamacije.html',
    $root . '/paneli-za-kupatilo.html' => $base . '/paneli-za-kupatilo.html',
    $root . '/tv-zid.html' => $base . '/tv-zid.html',
    $root . '/paneli-ili-lamperija.html' => $base . '/paneli-ili-lamperija.html',
    $root . '/akusticni-paneli-kancelarija.html' => $base . '/akusticni-paneli-kancelarija.html',
    $root . '/spc-ili-laminat.html' => $base . '/spc-ili-laminat.html',
    $root . '/dostava-crna-gora.html' => $base . '/dostava-crna-gora.html',
    // PHP
    $root . '/product.php'      => $base . '/product.php',
    $root . '/products.php'     => $base . '/products.php',
    $root . '/cjenovnik.php'    => $base . '/cjenovnik.php',
    $root . '/inspiracija.php'  => $base . '/inspiracija.php',
    $root . '/php/slug.php'       => $base . '/php/slug.php',
    $root . '/php/dimenzije.php'  => $base . '/php/dimenzije.php',
    $root . '/php/slug-match.php' => $base . '/php/slug-match.php',
    $root . '/php/contact.php'    => $base . '/php/contact.php',
    // JS
    $root . '/js/cart.js'       => $base . '/js/cart.js',
    $root . '/js/products.js'   => $base . '/js/products.js',
    $root . '/js/main-v4.js'    => $base . '/js/main-v4.js',
    $root . '/js/analytics-events.js' => $base . '/js/analytics-events.js',
    // CSS
    $root . '/css/style-v5.css' => $base . '/css/style-v5.css',
    $root . '/css/fonts.css'    => $base . '/css/fonts.css',
    // Images / favicon
    $root . '/images/favicon.ico'     => $base . '/images/favicon.ico',
    $root . '/images/favicon-512.png' => $base . '/images/favicon-512.png',
    // SEO
    $root . '/404.php'          => $base . '/404.php',
    $root . '/robots.txt'       => $base . '/robots.txt',
    $root . '/llms.txt'         => $base . '/llms.txt',
    $root . '/sitemap.xml'      => $base . '/sitemap.xml',
    // Server config
    $root . '/.htaccess'           => $base . '/.htaccess',
    // Admin
    __DIR__ . '/dashboard.php'  => $base . '/admin/dashboard.php',
    __DIR__ . '/actions.php'    => $base . '/admin/actions.php',
    __DIR__ . '/sync.php'       => $base . '/admin/sync.php',
    __DIR__ . '/index.php'      => $base . '/admin/index.php',
    __DIR__ . '/logout.php'     => $base . '/admin/logout.php',
    __DIR__ . '/oporavak.php'   => $base . '/admin/oporavak.php',
    __DIR__ . '/sifre.php'      => $base . '/admin/sifre.php',
    __DIR__ . '/optimize-gallery-images.php' => $base . '/admin/optimize-gallery-images.php',
    __DIR__ . '/optimize-main-images.php'    => $base . '/admin/optimize-main-images.php',
    __DIR__ . '/apply-discount.php'          => $base . '/admin/apply-discount.php',
    __DIR__ . '/server-status.php'           => $base . '/admin/server-status.php',
];

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
