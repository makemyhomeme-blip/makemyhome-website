#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
r3-sitemap-signal.py — razdvaja DVA razlicita sitemap signala za jednu adresu.

Zasto postoji
-------------
Search Console pokazuje dvije stvari koje lako lice jedna na drugu:

  A) Sitemaps izvjestaj → sitemap.xml → Status: Success, Discovered pages: 149
     To je FAJL: Google ga je skinuo, procitao i prebrojao adrese.

  B) URL Inspection → Page indexing → Discovery → Sitemaps
     To je JEDNA ADRESA: kroz koji sitemap je Google dosao do bas te stranice.
     Taj podatak dolazi iz Googleovog indeksa, iz vremena kad je stranica
     zadnji put obidjena — nije uzivo.

Zato A moze pisati "Success" dok B za istu stranicu pise gresku: to nisu isti
podatak i ne racunaju se u istom trenutku.

Ovaj alat provjerava sve sto se sa NASE strane moze dokazati:
  · da li je adresa u aktuelnom sitemapu (i na kojoj liniji)
  · da li je sama adresa 200, self-canonical i bez zabrane indeksiranja
  · koliko sitemap adresa na domenu uopste vraca 200 (treba tacno jedna)
  · sta vracaju sve stare, istorijske sitemap adrese (treba 410 ili 404)
  · sta robots.txt prijavljuje kao sitemap

Ono sto se NE moze provjeriti odavde jasno se i kaze — poruka iz URL Inspection-a
nastaje unutar Search Console-a i ne moze se ni izazvati ni ponoviti sa sajta.

  python3 alat/r3-sitemap-signal.py [adresa]
"""
import re
import subprocess
import sys

SAJT = 'https://makemyhome.me'
CURL = ['curl', '-sk', '--cacert', '/root/.ccr/ca-bundle.crt', '--max-time', '25']
GBOT = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'

# Sve sitemap adrese koje su ikad postojale ili se mogu naslutiti.
# wp-sitemap* je stvarni spisak iz serverskog loga za februar 2026.
ISTORIJSKE = [
    'wp-sitemap.xml',
    'wp-sitemap-posts-post-1.xml', 'wp-sitemap-posts-page-1.xml',
    'wp-sitemap-posts-product-1.xml', 'wp-sitemap-posts-elementor-hf-1.xml',
    'wp-sitemap-posts-mailpoet_page-1.xml', 'wp-sitemap-users-1.xml',
    'wp-sitemap-taxonomies-category-1.xml', 'wp-sitemap-taxonomies-product_cat-1.xml',
    'wp-sitemap-taxonomies-product_tag-1.xml',
    'sitemap_index.xml', 'sitemap-index.xml',
    'post-sitemap.xml', 'page-sitemap.xml', 'product-sitemap.xml',
    'category-sitemap.xml', 'product_cat-sitemap.xml', 'product_tag-sitemap.xml',
    'author-sitemap.xml', 'sitemap1.xml', 'sitemaps.xml', 'sitemap/',
    'sitemap.php', 'data/sitemap-kes.xml',
]


def status(put):
    izl = subprocess.run(CURL + ['-A', GBOT, '-o', '/dev/null',
                                 '-w', '%{http_code}|%{num_redirects}|%{url_effective}|%{content_type}',
                                 SAJT + '/' + put.lstrip('/')],
                         capture_output=True, text=True).stdout.split('|')
    return (izl + ['', '', '', ''])[:4]


def tijelo(u):
    return subprocess.run(CURL + ['-A', GBOT, u], capture_output=True,
                          text=True, errors='replace').stdout


def main():
    meta = sys.argv[1] if len(sys.argv) > 1 else SAJT + '/kategorija/bambus-drveni'
    if not meta.startswith('http'):
        meta = SAJT + '/' + meta.lstrip('/')
    print('Adresa koja se ispituje: %s\n' % meta)

    # ---------------------------------------------- A) TRENUTNI SITEMAP -----
    print('=== A) TRENUTNI SITEMAP (ono sto Google danas skida) ===')
    sm = tijelo(SAJT + '/sitemap.xml')
    kod, sk, kraj, tip = status('sitemap.xml')
    redovi = sm.split('\n')
    linija = next((i + 1 for i, r in enumerate(redovi) if '<loc>%s</loc>' % meta in r), None)
    loc = re.findall(r'<loc>([^<]+)</loc>', sm)
    print('  sitemap.xml            : %s, %s skokova, %s' % (kod, sk, tip))
    print('  adresa u sitemapu      : %s' % ('DA, linija %d' % linija if linija else 'NE'))
    print('  ukupno adresa          : %d (jedinstvenih %d)' % (len(loc), len(set(loc))))

    h = tijelo(meta)
    k2, s2, kr2, t2 = status(meta.replace(SAJT, ''))
    can = re.search(r'<link[^>]+rel=["\']canonical["\'][^>]*href=["\']([^"\']+)', h, re.I)
    rob = re.search(r'<meta[^>]+name=["\']robots["\'][^>]*content=["\']([^"\']*)', h, re.I)
    zag = subprocess.run(CURL + ['-I', '-A', GBOT, meta], capture_output=True,
                         text=True, errors='replace').stdout
    xrob = re.search(r'(?i)x-robots-tag:\s*([^\r\n]+)', zag)
    print('  sama adresa            : %s, %s skokova' % (k2, s2))
    print('  canonical              : %s%s' % (can.group(1) if can else 'NEMA',
                                               '' if (can and can.group(1) == meta) else '  ← ne pokazuje na sebe!'))
    print('  meta robots            : %s' % (rob.group(1) if rob else 'nema (znaci index,follow)'))
    print('  X-Robots-Tag           : %s' % (xrob.group(1) if xrob else 'nema'))

    # -------------------------------------- B) ISTORIJSKI SITEMAP SIGNAL ----
    print('\n=== B) ISTORIJSKE SITEMAP ADRESE (sve sto je ikad postojalo) ===')
    zivi, mrtvi = [], []
    for p in ISTORIJSKE:
        kod, sk, kraj, tip = status(p)
        if kod == '200':
            zivi.append((p, kod, kraj, tip))
        else:
            mrtvi.append((p, kod, kraj.replace(SAJT, '')))
        print('  %-40s %s%s' % ('/' + p, kod,
                                '  → ' + kraj.replace(SAJT, '') if sk != '0' else ''))
    print('\n  sitemap adresa koje jos vracaju 200: %d' % len(zivi))
    for p, kod, kraj, tip in zivi:
        print('    /%s  (%s)' % (p, tip))

    # ---------------------------------------------- robots.txt --------------
    rb = tijelo(SAJT + '/robots.txt')
    prijavljeni = re.findall(r'(?im)^\s*sitemap:\s*(\S+)', rb)
    print('\n=== robots.txt prijavljuje ===')
    for x in prijavljeni:
        print('  %s' % x)
    if not prijavljeni:
        print('  (nijedan)')

    # ---------------------------------------------------- presuda -----------
    print('\n=== PRESUDA ===')
    ok_trenutni = (linija is not None and k2 == '200' and s2 == '0'
                   and can and can.group(1) == meta
                   and not (rob and 'noindex' in rob.group(1).lower())
                   and not (xrob and 'noindex' in xrob.group(1).lower()))
    ok_istorijski = (len(zivi) == 1 and zivi[0][0] == 'sitemap.php'
                     ) or len(zivi) == 0
    print('  TRENUTNI SIGNAL   : %s' % ('ISPRAVAN — adresa je u sitemapu, 200, self-canonical, indeksabilna'
                                        if ok_trenutni else 'PROBLEM — vidi iznad'))
    print('  ISTORIJSKI SIGNAL : %s' % ('CIST — nijedna stara sitemap adresa ne vraca 200'
                                        if len(zivi) == 0 else
                                        '%d stara sitemap adresa jos vraca 200' % len(zivi)))
    print('')
    print('  Poruka "Temporary processing error" u URL Inspection → Discovery →')
    print('  Sitemaps nastaje UNUTAR Search Console-a. Ne moze se izazvati ni')
    print('  ponoviti sa sajta, pa se odavde ne moze ni provjeriti ni popraviti.')
    print('  NIJE MOGUCE PROVJERITI IZ KODA.')
    return 0 if (ok_trenutni and len(zivi) == 0) else 1


if __name__ == '__main__':
    sys.exit(main())
