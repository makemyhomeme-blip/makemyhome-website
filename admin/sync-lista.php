<?php
/**
 * Spisak fajlova koje sync povlaci sa GitHuba.
 *
 * Zasto je izdvojen iz sync.php:
 * sync.php gradi spisak na pocetku izvrsavanja, a sebe prepisuje tek u toku
 * rada. Kad se doda nov fajl, prvi sync jos uvijek radi po STAROM spisku —
 * ne povuce novi fajl, a povuce product.php koji ga trazi. Rezultat je 500
 * na cijelom sajtu dok se sync ne pokrene drugi put. Tako se 11.08.2026
 * desilo kad je dodat php/dimenzije.php.
 *
 * Sada sync.php prvo svjeze skine OVAJ fajl pa tek onda cita spisak, pa je
 * jedan prolaz uvijek dovoljan.
 */
return function (string $base, string $root, string $adminDir): array {
    return [
    // HTML stranice
    $root . '/404.html'         => $base . '/404.html',
    $root . '/index.html'       => $base . '/index.html',
        $root . '/pocetna.php'      => $base . '/pocetna.php',
    $root . '/product.html'     => $base . '/product.html',
    $root . '/products.html'    => $base . '/products.html',
    $root . '/about.html'       => $base . '/about.html',
    $root . '/contact.html'     => $base . '/contact.html',
    $root . '/korpa.html'       => $base . '/korpa.html',
    $root . '/checkout.html'    => $base . '/checkout.html',
    $root . '/hvala.html'       => $base . '/hvala.html',
    $root . '/faq.html'         => $base . '/faq.html',
    $root . '/montaza.html'     => $base . '/montaza.html',
    $root . '/decor-box.php'    => $base . '/decor-box.php',
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
    // Ikone: fontovi su sazeti sa 267 kB na 14 kB (samo 134 ikone koje sajt
        // stvarno koristi). Isto vazi i za CSS — all.min.css opisuje 1870 ikona
        // i tezak je 100 kB, a mmh-ikone.css samo onih 134, 22 kB. Pravi ga
        // alat/ikone.py iz koda, pa ne moze zastarjeti. Sync ih prenosi bajt
        // po bajt, kao i favicon.
        $root . '/fa/webfonts/fa-solid-900.woff2'  => $base . '/fa/webfonts/fa-solid-900.woff2',
        $root . '/fa/webfonts/fa-brands-400.woff2' => $base . '/fa/webfonts/fa-brands-400.woff2',
        $root . '/fa/css/mmh-ikone.css'              => $base . '/fa/css/mmh-ikone.css',
        // Tekstualni fontovi: "latin-ext" fajlovi su nosili ogroman opseg znakova
        // (fonetski, dodatni latinicni...) a sajtu trebaju samo ć č đ š ž.
        // Sazeti su na cio blok Latin Extended-A i B, da rade i buduca imena.
        $root . '/fonts/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa25L7SUc.woff2' => $base . '/fonts/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa25L7SUc.woff2',
        $root . '/fonts/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa1ZL7.woff2'    => $base . '/fonts/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa1ZL7.woff2',
        $root . '/fonts/nuFiD-vYSZviVYUb_rj3ij__anPXDTLYgFE_.woff2'    => $base . '/fonts/nuFiD-vYSZviVYUb_rj3ij__anPXDTLYgFE_.woff2',
        $root . '/fonts/nuFiD-vYSZviVYUb_rj3ij__anPXDTzYgA.woff2'      => $base . '/fonts/nuFiD-vYSZviVYUb_rj3ij__anPXDTzYgA.woff2',
        $root . '/images/favicon.ico'     => $base . '/images/favicon.ico',
    $root . '/images/favicon-512.png' => $base . '/images/favicon-512.png',
    // SEO
    $root . '/404.php'          => $base . '/404.php',
    $root . '/robots.txt'       => $base . '/robots.txt',
    $root . '/llms.txt'         => $base . '/llms.txt',
    $root . '/sitemap.php'      => $base . '/sitemap.php',
    // Server config
    $root . '/.htaccess'           => $base . '/.htaccess',
    // Admin
    $adminDir . '/dashboard.php'  => $base . '/admin/dashboard.php',
    $adminDir . '/actions.php'    => $base . '/admin/actions.php',
    $adminDir . '/sync.php'       => $base . '/admin/sync.php',
        $adminDir . '/sync-lista.php'  => $base . '/admin/sync-lista.php',
        $adminDir . '/sesija.php'      => $base . '/admin/sesija.php',
    $adminDir . '/index.php'      => $base . '/admin/index.php',
    $adminDir . '/logout.php'     => $base . '/admin/logout.php',
    $adminDir . '/oporavak.php'   => $base . '/admin/oporavak.php',
    $adminDir . '/sifre.php'      => $base . '/admin/sifre.php',
    $adminDir . '/optimize-gallery-images.php' => $base . '/admin/optimize-gallery-images.php',
    $adminDir . '/optimize-main-images.php'    => $base . '/admin/optimize-main-images.php',
    $adminDir . '/apply-discount.php'          => $base . '/admin/apply-discount.php',
    $adminDir . '/webp.php'                    => $base . '/admin/webp.php',
    $adminDir . '/server-status.php'           => $base . '/admin/server-status.php',
];
};
