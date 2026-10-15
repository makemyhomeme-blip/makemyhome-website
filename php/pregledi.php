<?php
/**
 * Brojač pregleda — SERVER-SIDE, broji SVAKOG posjetioca
 * (ne zavisi od Google Analyticsa ni od pristanka na kolačiće).
 *
 * Šta se broji (server-side može samo PHP stranice):
 *   - proizvod   /paneli/<ime>     → ključ "p:<id>"   (product.php)
 *   - kategorija /kategorija/<k>    → ključ "k:<kljuc>" (products.php)
 * Statične HTML stranice (početna, blog, products.html) se ne vide odavde.
 *
 * Šta se pamti (data/pregledi.json):
 *   { "ukupno":  {"<kljuc>": N},                         // sve vrijeme, po proizvodu/kategoriji
 *     "dani":    {"YYYY-MM-DD": {"<kljuc>": N}},          // po danu, po proizvodu/kategoriji
 *     "posjete": {"YYYY-MM-DD": {"uk":N,"jed":N,"izvori":{...}}}, // pregledi, JEDINSTVENI posjetioci, odakle
 *     "_fp":     {"YYYY-MM-DD": {"<otisak>":1}} }         // otisci za brojanje jedinstvenih (čuva se 3 dana)
 * Fajl pravi server sam i NE deployuje se sa GitHuba (nije u sync listi).
 *
 * KO SE NE BROJI:
 *   - botovi / alati (po User-Agentu),
 *   - vlasnik koji se sam isključi (kolačić mmh_noview=1; postavlja se iz admina
 *     ili posjetom bilo koje stranice sa ?mmhnoview=1, a ?mmhnoview=0 vraća).
 *
 * Sigurnost: nikad ne ruši stranicu (try/catch, @), upis pod zaključavanjem (flock).
 */

if (!function_exists('mmhZabiljeziPregledKljuc')) {

/** Bot/alat? Bote NE brojimo (Googlebot bi obišao sve i izravnao listu). */
function mmhPregledBot(): bool {
    $ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ua === '') return true;
    $z = ['bot','spider','crawl','slurp','bing','google','yandex','baidu','duckduck',
        'facebook','embed','pinterest','headless','lighthouse','gtmetrix','pingdom',
        'uptime','curl','wget','python','http-client','ahrefs','semrush','mj12',
        'dotbot','petal','bytespider','applebot','whatsapp','telegram','viber','preview'];
    foreach ($z as $s) if (strpos($ua, $s) !== false) return true;
    return false;
}

/** Grubi otisak posjetioca (IP+UA+dan), heširan — za brojanje jedinstvenih istog dana. */
function mmhPregledOtisak(): string {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    if (strpos($ip, ',') !== false) $ip = trim(explode(',', $ip)[0]);
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return substr(md5($ip . '|' . $ua . '|' . date('Y-m-d') . '|mmh'), 0, 12);
}

/** Odakle je posjetilac došao (iz Referer zaglavlja). */
function mmhPregledIzvor(): string {
    $r = strtolower((string)($_SERVER['HTTP_REFERER'] ?? ''));
    if ($r === '')                              return 'direktno';
    if (strpos($r, 'instagram') !== false)      return 'instagram';
    if (strpos($r, 'google') !== false)         return 'google';
    if (strpos($r, 'bing') !== false || strpos($r, 'yandex') !== false || strpos($r, 'duckduck') !== false) return 'pretraga';
    if (strpos($r, 'facebook') !== false || strpos($r, 'tiktok') !== false || strpos($r, 't.co') !== false || strpos($r, 'youtube') !== false) return 'drustvene';
    if (strpos($r, 'chatgpt') !== false || strpos($r, 'openai') !== false || strpos($r, 'perplexity') !== false) return 'ai';
    if (strpos($r, 'makemyhome.me') !== false)  return 'sa-sajta';
    return 'ostalo';
}

/** Glavna funkcija: zabilježi pregled pod ključem ("p:63", "k:bambus-paneli"). */
function mmhZabiljeziPregledKljuc(string $kljuc): void {
    $kljuc = trim($kljuc);
    if ($kljuc === '' || strlen($kljuc) > 80) return;
    try {
        // --- samoisključenje vlasnika (kolačić) ---
        if (array_key_exists('mmhnoview', $_GET) && !headers_sent()) {
            $off = ($_GET['mmhnoview'] !== '0');
            @setcookie('mmh_noview', $off ? '1' : '', [
                'expires'  => $off ? time() + 31536000 : time() - 3600,
                'path'     => '/', 'samesite' => 'Lax',
            ]);
            $_COOKIE['mmh_noview'] = $off ? '1' : '';
            if ($off) return;
        }
        if (($_COOKIE['mmh_noview'] ?? '') === '1') return;
        if (mmhPregledBot()) return;

        $put = dirname(__DIR__) . '/data/pregledi.json';
        $fh  = @fopen($put, 'c+');
        if (!$fh) return;
        if (!flock($fh, LOCK_EX)) { fclose($fh); return; }

        $sirovo = stream_get_contents($fh);
        $D = json_decode($sirovo ?: '{}', true);
        if (!is_array($D)) $D = [];
        foreach (['ukupno','dani','posjete','_fp'] as $g) if (!isset($D[$g]) || !is_array($D[$g])) $D[$g] = [];

        $dan = date('Y-m-d');

        // 1) pregledi po proizvodu/kategoriji
        $D['ukupno'][$kljuc] = (int)($D['ukupno'][$kljuc] ?? 0) + 1;
        if (!isset($D['dani'][$dan]) || !is_array($D['dani'][$dan])) $D['dani'][$dan] = [];
        $D['dani'][$dan][$kljuc] = (int)($D['dani'][$dan][$kljuc] ?? 0) + 1;

        // 2) posjetioci + jedinstveni + izvori
        if (!isset($D['posjete'][$dan]) || !is_array($D['posjete'][$dan]))
            $D['posjete'][$dan] = ['uk' => 0, 'jed' => 0, 'izvori' => []];
        $D['posjete'][$dan]['uk'] = (int)($D['posjete'][$dan]['uk'] ?? 0) + 1;

        $fp = mmhPregledOtisak();
        if (!isset($D['_fp'][$dan]) || !is_array($D['_fp'][$dan])) $D['_fp'][$dan] = [];
        if (!isset($D['_fp'][$dan][$fp])) {
            if (count($D['_fp'][$dan]) < 8000) $D['_fp'][$dan][$fp] = 1; // novi jedinstveni posjetilac danas
            $D['posjete'][$dan]['jed'] = (int)($D['posjete'][$dan]['jed'] ?? 0) + 1;
            $izv = mmhPregledIzvor();
            if (!isset($D['posjete'][$dan]['izvori']) || !is_array($D['posjete'][$dan]['izvori'])) $D['posjete'][$dan]['izvori'] = [];
            $D['posjete'][$dan]['izvori'][$izv] = (int)($D['posjete'][$dan]['izvori'][$izv] ?? 0) + 1;
        }

        // 3) čišćenje da fajl ne raste bez granice
        if (count($D['dani'])    > 120) { ksort($D['dani']);    $D['dani']    = array_slice($D['dani'],    -120, null, true); }
        if (count($D['posjete']) > 120) { ksort($D['posjete']); $D['posjete'] = array_slice($D['posjete'], -120, null, true); }
        if (count($D['_fp'])     > 3)   { ksort($D['_fp']);     $D['_fp']     = array_slice($D['_fp'],     -3,   null, true); }

        $novo = json_encode($D, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($novo !== false) { rewind($fh); ftruncate($fh, 0); fwrite($fh, $novo); fflush($fh); }
        flock($fh, LOCK_UN);
        fclose($fh);
    } catch (\Throwable $e) {
        // Analitika NIKAD ne smije srušiti stranicu.
    }
}

/** Kratica za proizvod po ID-u. */
function mmhZabiljeziPregled(int $id): void {
    if ($id <= 0) return;
    mmhZabiljeziPregledKljuc('p:' . $id);
}

}
