<?php
/**
 * Beacon za brojanje posjeta STRANICAMA koje server ne vidi sam
 * (početna index.html, blog članci, products.html — statični HTML).
 *
 * js/analytics-events.js šalje navigator.sendBeacon() na svakoj stranici OSIM
 * /paneli/ i /kategorija/ (njih product.php i products.php već broje server-side,
 * pa bi beacon tu duplirao). Ovdje se posjeta upisuje pod ključem "page:<putanja>".
 *
 * Prima (GET ili POST): p = putanja stranice, r = document.referrer (odakle je došao).
 * Vraća 204 (bez sadržaja). Nikad ne ruši ništa.
 */
require __DIR__ . '/pregledi.php';

try {
    $p = (string)($_GET['pg'] ?? $_POST['pg'] ?? '');
    $p = strtok($p, '?');                 // bez query stringa
    $p = strtok((string)$p, '#');         // bez fragmenta
    if ($p === '' || $p === false) $p = '/';
    if ($p[0] !== '/') $p = '/' . $p;
    // samo bezbjedni znakovi; ograniči dužinu da ključ ostane kratak
    $p = preg_replace('~[^a-zA-Z0-9/_.\-]~', '', $p);
    if ($p === '' ) $p = '/';
    if (strlen($p) > 70) $p = substr($p, 0, 70);

    $ref = (string)($_GET['ref'] ?? $_POST['ref'] ?? '');
    if (strlen($ref) > 300) $ref = substr($ref, 0, 300);

    if (function_exists('mmhZabiljeziPregledKljuc')) {
        mmhZabiljeziPregledKljuc('page:' . $p, $ref);
    }
} catch (\Throwable $e) {
    // tišina — beacon nikad ne smije da javi grešku
}

http_response_code(204);
