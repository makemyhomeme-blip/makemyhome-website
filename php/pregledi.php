<?php
/**
 * Brojač pregleda proizvoda — SERVER-SIDE, broji SVAKOG posjetioca
 * (ne zavisi od Google Analyticsa ni od pristanka na kolačiće).
 *
 * Zašto postoji:
 * GA4 broji samo posjetioce koji prihvate kolačiće, a kod nas "view_item" nije
 * ni okidao na lijepim adresama /paneli/..., pa je admin ostajao bez prave slike
 * o tome koji se paneli najviše gledaju. Ovaj brojač je nezavisan i uvijek radi —
 * rezultat se gleda u admin/pregledi.php.
 *
 * Podaci: data/pregledi.json
 *   { "ukupno": {"<id>": N, ...},
 *     "dani":   {"YYYY-MM-DD": {"<id>": N, ...}, ...} }
 * Taj fajl pravi server sam i NE deployuje se sa GitHuba (nije u sync listi),
 * isto kao products.json — da ga svaki sync ne vrati na prazno.
 *
 * Sigurnost rada: nikad ne ruši stranicu (sve je u try/catch i @), upis je pod
 * zaključavanjem (flock) da se paralelni pregledi ne izgube, a stari dani se
 * čiste (čuva se zadnjih ~120 dana) da fajl ne raste bez granice.
 */

if (!function_exists('mmhZabiljeziPregled')) {

/** Je li posjetilac bot/alat? Bote NE brojimo da rang-lista ostane stvarna
 *  (Googlebot i sl. obiđu svih 117 proizvoda i izravnali bi listu). */
function mmhPregledBot(): bool {
    $ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ua === '') return true; // prazan User-Agent = skoro uvijek alat
    $zabranjeni = ['bot', 'spider', 'crawl', 'slurp', 'bing', 'google', 'yandex',
        'baidu', 'duckduck', 'facebook', 'embed', 'pinterest', 'headless',
        'lighthouse', 'gtmetrix', 'pingdom', 'uptime', 'curl', 'wget', 'python',
        'http-client', 'ahrefs', 'semrush', 'mj12', 'dotbot', 'petal', 'bytespider',
        'applebot', 'whatsapp', 'telegram', 'viber', 'preview'];
    foreach ($zabranjeni as $z) {
        if (strpos($ua, $z) !== false) return true;
    }
    return false;
}

/** Zabilježi jedan pregled proizvoda sa datim ID-om. */
function mmhZabiljeziPregled(int $id): void {
    if ($id <= 0) return;
    try {
        if (mmhPregledBot()) return;

        $put = dirname(__DIR__) . '/data/pregledi.json';
        $fh  = @fopen($put, 'c+');          // c+: napravi ako ne postoji, ne briše sadržaj
        if (!$fh) return;
        if (!flock($fh, LOCK_EX)) { fclose($fh); return; }

        $sirovo = stream_get_contents($fh);
        $D = json_decode($sirovo ?: '{}', true);
        if (!is_array($D)) $D = [];
        if (!isset($D['ukupno']) || !is_array($D['ukupno'])) $D['ukupno'] = [];
        if (!isset($D['dani'])   || !is_array($D['dani']))   $D['dani']   = [];

        $k   = (string)$id;
        $dan = date('Y-m-d');

        $D['ukupno'][$k] = (int)($D['ukupno'][$k] ?? 0) + 1;
        if (!isset($D['dani'][$dan]) || !is_array($D['dani'][$dan])) $D['dani'][$dan] = [];
        $D['dani'][$dan][$k] = (int)($D['dani'][$dan][$k] ?? 0) + 1;

        // Čišćenje starih dana — čuvamo zadnjih 120.
        if (count($D['dani']) > 120) {
            ksort($D['dani']);
            $D['dani'] = array_slice($D['dani'], -120, null, true);
        }

        $novo = json_encode($D, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($novo !== false) {
            rewind($fh);
            ftruncate($fh, 0);
            fwrite($fh, $novo);
            fflush($fh);
        }
        flock($fh, LOCK_UN);
        fclose($fh);
    } catch (\Throwable $e) {
        // Analitika NIKAD ne smije srušiti stranicu proizvoda.
    }
}

}
