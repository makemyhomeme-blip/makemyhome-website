<?php
/**
 * Pokretanje admin sesije, na jednom mjestu.
 *
 * Zasto postoji:
 * Kolacic sesije je do 11.08.2026 isao bez ijedne zastitne oznake —
 * "PHPSESSID=...; path=/" i nista vise. To znaci troje:
 *   - bez HttpOnly ga moze procitati bilo koji JavaScript na sajtu, pa bi
 *     jedna ubacena skripta bila dovoljna da neko udje u admin;
 *   - bez Secure bi se poslao i preko obicnog HTTP-a;
 *   - bez SameSite ga pretrazivac salje i kad zahtjev dolazi sa tudjeg sajta.
 * Uz to se broj sesije nije mijenjao pri prijavi, pa je neko ko unaprijed
 * podmetne broj mogao da ga iskoristi posto se vlasnik prijavi.
 *
 * Ovo se mora ukljuciti PRIJE svakog session_start().
 */

if (session_status() === PHP_SESSION_NONE) {
    $preko_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/admin/',
        'secure'   => $preko_https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('MMHADMIN');
    session_start();
}

/** Pozvati odmah poslije uspjesne provjere lozinke. */
function mmhSesijaObnovi(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}
