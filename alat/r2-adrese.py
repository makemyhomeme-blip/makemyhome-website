#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
r2-adrese.py — koje sve adrese na sajtu vracaju 200, a nisu u sitemapu.

Zasto postoji:
Search Console prijavljuje oko 280 indeksiranih adresa, a sitemap ima 149.
Razlika su ili duplikati (ista stranica na vise adresa), ili ostaci starog
sajta, ili adrese sa parametrima koje neko linkuje. Duplikati dijele snagu
izmedju sebe, pa ista stranica na dvije adrese rangira slabije nego na jednoj.

Sta radi:
  1. crawla sajt od pocetne, prati sve interne linkove do dubine 3
  2. za svaku nadjenu adresu: status, broj skokova, gdje zavrsi, canonical
  3. probava varijante svake adrese iz sitemapa: /x/, /x.html, /x bez .html
  4. probava parametre: ?utm_source, ?page, ?filter, ?id, ?category, ?fbclid
  5. probava ostatke starog WordPress sajta
  6. spisak: sve sto vraca 200 a nije u sitemapu, sa prijedlogom

  python3 alat/r2-adrese.py   →  R2-ADRESE.md
"""
import concurrent.futures as fut
import os
import re
import subprocess
import sys
from urllib.parse import urljoin, urlparse

SAJT = 'https://makemyhome.me'
KOR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
GBOT = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'
CURL = ['curl', '-sk', '--cacert', '/root/.ccr/ca-bundle.crt', '--max-time', '25', '-A', GBOT]

# Adrese koje NAMJERNO nisu u sitemapu i to je ispravno.
NAMJERNO = {'/korpa.html', '/checkout.html', '/hvala.html', '/404.html', '/admin/',
            '/robots.txt', '/sitemap.xml'}


def glava(url):
    """Vrati (status_bez_pracenja, skokovi, konacna_adresa, konacni_status)."""
    p = subprocess.run(CURL + ['-o', '/dev/null', '-w', '%{http_code}', url],
                       capture_output=True, text=True)
    kod = p.stdout.strip()
    q = subprocess.run(CURL + ['-L', '-o', '/dev/null', '-w', '%{num_redirects}|%{url_effective}|%{http_code}', url],
                       capture_output=True, text=True)
    dio = q.stdout.strip().split('|')
    if len(dio) < 3:
        return kod, 0, url, kod
    return kod, int(dio[0] or 0), dio[1], dio[2]


def tijelo(url):
    p = subprocess.run(CURL + [url], capture_output=True, text=True, errors='replace')
    return p.stdout


def kanon(html):
    m = re.search(r'<link[^>]+rel=["\']canonical["\'][^>]*>', html, re.I)
    if not m:
        return ''
    h = re.search(r'href=["\']([^"\']+)', m.group(0), re.I)
    return h.group(1) if h else ''


def sitemap_adrese():
    x = tijelo(SAJT + '/sitemap.xml')
    return [m.group(1) for m in re.finditer(r'<loc>([^<]+)</loc>', x)]


def crawl(pocetak, dubina=3, granica=600):
    """Prati interne linkove sa stranica. Vraca {adresa: [odakle je nadjena]}."""
    vidjeno = {pocetak: ['(pocetna)']}
    sloj = [pocetak]
    for _ in range(dubina):
        novi = []
        with fut.ThreadPoolExecutor(max_workers=8) as ex:
            tekstovi = list(ex.map(tijelo, sloj))
        for izvor, html in zip(sloj, tekstovi):
            # <base href> se MORA postovati. Stranice na lijepim adresama
            # (/kategorija/x, /paneli/x) nose <base href="https://makemyhome.me/">,
            # pa se relativni link "about.html" rjesava na /about.html, a ne na
            # /kategorija/about.html. Prva verzija ovog crawlera to nije gledala i
            # prijavila je 36 nepostojecih 404 adresa kojih na sajtu nema.
            mb = re.search(r'<base\b[^>]+href=["\']([^"\']+)', html, re.I)
            osnova = urljoin(izvor, mb.group(1)) if mb else izvor
            # Bez skripti — inace se JavaScript koji sklapa adresu
            # ("/kategorija/' + slug + '") cita kao pravi link.
            bez = re.sub(r'<script[\s\S]*?</script>', '', html, flags=re.I)
            for m in re.finditer(r'<a\b[^>]+href=["\']([^"\'#]+)', bez, re.I):
                cilj = urljoin(osnova, m.group(1))
                if not cilj.startswith(SAJT):
                    continue
                cilj = cilj.split('#')[0]
                if re.search(r'\.(jpg|jpeg|png|webp|svg|css|js|pdf|ico|woff2?)$', cilj, re.I):
                    continue
                if cilj in vidjeno:
                    continue
                vidjeno[cilj] = [urlparse(izvor).path or '/']
                novi.append(cilj)
                if len(vidjeno) >= granica:
                    return vidjeno
        sloj = novi
        if not sloj:
            break
    return vidjeno


def main():
    L = []
    r = L.append
    r('# R2 — sve adrese koje vracaju 200')
    r('')
    r('Cilj: naci razliku izmedju ~280 indeksiranih adresa i 149 iz sitemapa.')
    r('')

    smap = sitemap_adrese()
    smap_set = set(smap)
    smap_put = {urlparse(u).path for u in smap}
    r(f'Sitemap: **{len(smap)}** adresa.')

    # ---------------------------------------------------- 1. crawl od pocetne
    nadjeno = crawl(SAJT + '/')
    r(f'Crawl od pocetne (dubina 3): **{len(nadjeno)}** razlicitih adresa u linkovima.')

    van = sorted(u for u in nadjeno if u not in smap_set)
    r('')
    r('## 1. Nadjeno crawlom, a nije u sitemapu')
    r('')
    if not van:
        r('[i] Nijedna. Svaki interni link vodi na adresu koja je u sitemapu.')
    else:
        r('| adresa | status | skokova | zavrsi na | u sitemapu? |')
        r('|---|---|---|---|---|')
        with fut.ThreadPoolExecutor(max_workers=8) as ex:
            for u, (kod, sk, kraj, kkod) in zip(van, ex.map(glava, van)):
                put = urlparse(u).path
                bilj = 'namjerno' if put in NAMJERNO else ('ista stranica' if kraj in smap_set else '**NE**')
                r(f'| `{put}` | {kod} | {sk} | `{urlparse(kraj).path}` | {bilj} |')

    # ------------------------------------------------------- 2. varijante ----
    r('')
    r('## 2. Varijante iste adrese (/x, /x/, /x.html)')
    r('')
    varijante = []
    for u in smap:
        put = urlparse(u).path
        if put == '/':
            continue
        kand = set()
        if put.endswith('.html'):
            kand.add(put[:-5])
            kand.add(put[:-5] + '/')
            kand.add(put + '/')
        else:
            kand.add(put.rstrip('/') + '/')
            kand.add(put.rstrip('/') + '.html')
        for k in kand:
            if k not in smap_put:
                varijante.append(SAJT + k)
    varijante = sorted(set(varijante))
    r(f'Provjereno {len(varijante)} varijanti od {len(smap)} adresa iz sitemapa.')
    r('')
    lose = []
    with fut.ThreadPoolExecutor(max_workers=12) as ex:
        for u, (kod, sk, kraj, kkod) in zip(varijante, ex.map(glava, varijante)):
            if kod == '200':
                lose.append((u, kod, sk, kraj))
    if not lose:
        r('[i] Nijedna varijanta ne vraca 200 direktno — sve su 301 ili 404.')
    else:
        r(f'[!!] **{len(lose)}** varijanti vraca 200 bez preusmjerenja — ista stranica na dvije adrese.')
        r('')
        r('| adresa | status | canonical pokazuje na |')
        r('|---|---|---|')
        with fut.ThreadPoolExecutor(max_workers=8) as ex:
            for (u, kod, sk, kraj), html in zip(lose[:40], ex.map(tijelo, [x[0] for x in lose[:40]])):
                r(f'| `{urlparse(u).path}` | {kod} | `{kanon(html)}` |')

    # ------------------------------------------------------- 3. parametri ----
    r('')
    r('## 3. Adrese sa parametrima')
    r('')
    uzorak = ['/', '/kategorija/3d-letvice', '/paneli/3d-letvica-obsidian', '/products.html']
    param = ['?utm_source=facebook&utm_medium=social', '?fbclid=IwAR123', '?page=2',
             '?filter=cijena', '?cat=3d-letvice', '?category=3d-letvice', '?id=5',
             '?gclid=abc123', '?ref=instagram']
    r('| adresa | status | skokova | zavrsi na | canonical |')
    r('|---|---|---|---|---|')
    zadaci = [SAJT + p + q for p in uzorak for q in param]
    with fut.ThreadPoolExecutor(max_workers=12) as ex:
        glave = list(ex.map(glava, zadaci))
        tijela = list(ex.map(tijelo, zadaci))
    saParam200 = 0
    for u, (kod, sk, kraj, kkod), html in zip(zadaci, glave, tijela):
        k = kanon(html)
        pu = urlparse(u)
        prikaz = (pu.path + '?' + pu.query)[:52]
        if kod == '200' and k and k.split('?')[0] == k:
            oc = 'canonical cisti'
        elif kod == '200':
            oc = '**canonical NE cisti**'
            saParam200 += 1
        else:
            oc = f'{kod}'
        r(f'| `{prikaz}` | {kod} | {sk} | `{urlparse(kraj).path}` | {oc} |')

    # ------------------------------------------------- 4. ostaci starog sajta
    r('')
    r('## 4. Ostaci starog sajta')
    r('')
    stari = ['/wp-admin/', '/wp-login.php', '/wp-content/uploads/', '/?p=1', '/feed/',
             '/shop/', '/product/bambus-panel/', '/product-category/paneli/', '/cart/',
             '/my-account/', '/checkout/', '/wp-json/wp/v2/posts', '/xmlrpc.php',
             '/index.php', '/home', '/about', '/kontakt', '/blog/', '/category/uncategorized/']
    r('| adresa | status | zavrsi na | ocjena |')
    r('|---|---|---|---|')
    with fut.ThreadPoolExecutor(max_workers=10) as ex:
        for u, (kod, sk, kraj, kkod) in zip([SAJT + s for s in stari],
                                            ex.map(glava, [SAJT + s for s in stari])):
            put = urlparse(u).path + (('?' + urlparse(u).query) if urlparse(u).query else '')
            if kod in ('404', '410'):
                oc = 'uredu'
            elif kod.startswith('3') and kraj in smap_set:
                oc = 'uredu (301 na pravu)'
            elif kod == '200':
                oc = '**[!!] vraca 200**'
            else:
                oc = kod
            r(f'| `{put}` | {kod} | `{urlparse(kraj).path}` | {oc} |')

    # ------------------------------------------------------ 5. fajlovi na disku
    r('')
    r('## 5. Staticni fajlovi koje je zamijenio PHP')
    r('')
    r('Ako `.htaccess` prepisuje `/x.html` na `x.php`, staticni `x.html` na disku je')
    r('mrtav teret — ali ostaje dostupan ako pravilo ikad zakaze.')
    r('')
    parovi = [('products.html', 'products.php'), ('product.html', 'product.php'),
              ('cjenovnik.html', 'cjenovnik.php'), ('inspiracija.html', 'inspiracija.php'),
              ('decor-box.html', 'decor-box.php'), ('index.html', 'pocetna.php')]
    r('| staticni fajl | PHP verzija | fajl postoji lokalno | sajt vraca |')
    r('|---|---|---|---|')
    for st, php in parovi:
        ima = os.path.isfile(os.path.join(KOR, st))
        html = tijelo(f'{SAJT}/{st}')
        lok = ''
        if ima:
            with open(os.path.join(KOR, st), encoding='utf-8', errors='replace') as fh:
                lok = fh.read()
        isti = 'isto kao staticni' if (lok and html and len(lok) == len(html)) else 'PHP verziju'
        r(f'| `{st}` | `{php}` | {"da" if ima else "ne"} | {isti} |')

    with open(os.path.join(KOR, 'R2-ADRESE.md'), 'w', encoding='utf-8') as fh:
        fh.write('\n'.join(L) + '\n')
    print('Gotovo → R2-ADRESE.md')
    return 0


if __name__ == '__main__':
    sys.exit(main())
