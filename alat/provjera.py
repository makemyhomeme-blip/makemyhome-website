#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
PROVJERA CIJELOG SAJTA — spisak svega sto mora da vazi.

Pokretanje:  python3 alat/provjera.py [grupa]
             grupe: A B C D E F G H  (bez argumenta = sve osim E i G, koje su spore)
             python3 alat/provjera.py sve   = bas sve

Svaka stavka je jedno pravilo. Ako pravilo padne, ispise se sta tacno i gdje.
Fajl se NE deployuje na server (nije u admin/sync.php listi).
"""
import json, re, subprocess, sys, os, collections, hashlib
from urllib.parse import urljoin, urlparse

BAZA = 'https://makemyhome.me'
KORIJEN = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CURL = ['curl', '-sk', '--cacert', '/root/.ccr/ca-bundle.crt']
rezultati = []


def dohvati(u, prati=False, timeout='20'):
    cmd = CURL + ['--max-time', timeout, '-w', '\n@@%{http_code}|%{num_redirects}|%{url_effective}']
    if prati:
        cmd += ['-L', '--max-redirs', '5']
    r = subprocess.run(cmd + [u], capture_output=True, text=True, errors='replace').stdout
    i = r.rfind('\n@@')
    if i < 0:
        return '', '000', '0', ''
    kod, sk, kraj = r[i + 3:].split('|', 2)
    return r[:i], kod, sk, kraj.strip()


def zabiljezi(sifra, opis, greske, provjereno):
    rezultati.append((sifra, opis, list(greske), provjereno))
    znak = 'OK ' if not greske else 'PAD'
    print('%s %-5s %-52s (%d provjereno, %d gresaka)' % (znak, sifra, opis[:52], provjereno, len(greske)))
    for g in greske[:6]:
        print('        · %s' % str(g)[:112])
    if len(greske) > 6:
        print('        · …i jos %d' % (len(greske) - 6))


def tekst_bez_skripti(h):
    v = re.sub(r'<(script|style)[^>]*>.*?</\1>', '', h, flags=re.S)
    v = re.sub(r'<[^>]+>', ' ', v)
    return re.sub(r'\s+', ' ', v)


# ---------- ucitavanje zajednickih podataka ----------
print('… ucitavam sitemap i podatke sa servera')
h, _, _, _ = dohvati(BAZA + '/sitemap.xml')
SITEMAP = re.findall(r'<loc>([^<]+)</loc>', h)
hp, _, _, _ = dohvati(BAZA + '/data/products.json?v=5')
PROIZVODI = json.loads(hp)
PROIZVODI = PROIZVODI['products'] if isinstance(PROIZVODI, dict) else PROIZVODI
STRANICE = {}
print('… skidam %d stranica' % len(SITEMAP))
for u in SITEMAP:
    STRANICE[u] = dohvati(u)
print('… spremno\n')

# ============================================================
# A — DOSTUPNOST
# ============================================================
def grupa_A():
    print('=== A · DOSTUPNOST ===')
    g = []
    for u, (h, kod, sk, _) in STRANICE.items():
        if kod != '200':
            g.append('%s → %s' % (u, kod))
        elif sk != '0':
            g.append('%s → %s preusmjerenja' % (u, sk))
    zabiljezi('A1', 'Svaka adresa iz sitemapa vraca 200 bez skoka', g, len(SITEMAP))

    g = []
    for u, (h, kod, _, _) in STRANICE.items():
        if kod != '200':
            continue
        m = re.search(r'<link rel="canonical" href="([^"]*)"', h)
        if not m:
            g.append('%s → nema canonical' % u)
        elif m.group(1) != u:
            g.append('%s → canonical %s' % (u, m.group(1)))
    zabiljezi('A2', 'Canonical pokazuje na samu sebe', g, len(SITEMAP))

    g = []
    for u in ['/product.html?id=99999', '/product.html?id=abc', '/paneli/nepostojeci-panel',
              '/kategorija/nepostojeca', '/nepostojeca-stranica']:
        _, kod, _, _ = dohvati(BAZA + u)
        if kod != '404':
            g.append('%s → %s (mora 404)' % (u, kod))
    zabiljezi('A3', 'Nepostojeca stranica vraca 404, ne lazni 200', g, 5)

    g = []
    _, kod, _, _ = dohvati(BAZA + '/admin/dashboard.php')
    if kod not in ('302', '403'):
        g.append('admin/dashboard.php bez prijave → %s' % kod)
    _, kod, _, _ = dohvati(BAZA + '/admin/sync.php?key=mkhsync2025')
    if kod == '200':
        hh, _, _, _ = dohvati(BAZA + '/admin/sync.php?key=mkhsync2025')
        if 'Pristup odbijen' not in hh:
            g.append('sync.php radi bez prijave!')
    zabiljezi('A4', 'Admin nije dostupan bez prijave', g, 2)


# ============================================================
# B — ADRESE
# ============================================================
def grupa_B():
    print('\n=== B · ADRESE ===')
    sys.path.insert(0, KORIJEN)
    slugovi = {}
    php = subprocess.run(['php', '-r',
        'require "%s/php/slug.php"; $d=json_decode(file_get_contents("php://stdin"),true); '
        '$P=$d["products"]??$d; $o=[]; foreach($P as $p) $o[$p["id"]]=mmhSlugProizvoda($p); echo json_encode($o);' % KORIJEN],
        input=json.dumps({'products': PROIZVODI}), capture_output=True, text=True)
    slugovi = json.loads(php.stdout)

    g = []
    for pid, s in slugovi.items():
        _, kod, sk, kraj = dohvati('%s/product.html?id=%s' % (BAZA, pid), prati=True, timeout='12')
        ocek = '%s/%s' % (BAZA, s)
        if kod != '200' or sk != '1' or kraj != ocek:
            g.append('?id=%s → %s (%s skoka) ocekivano %s' % (pid, kraj, sk, ocek))
    zabiljezi('B1', 'Stari ?id= ide jednim skokom na tacnu novu adresu', g, len(slugovi))

    kat = ['bambus-paneli', 'bambus-drveni', 'bambus-tekstilni', 'bambus-mermerni', 'bambus-metalni',
           'bambus-kozni', 'classic', '3d-letvice', 'akusticni-paneli', 'aluminijum-lajsne',
           'spc-pod', 'pu-kamen', 'mdf', 'flex-stone']
    g = []
    for c in kat:
        _, kod, sk, kraj = dohvati('%s/products.html?category=%s' % (BAZA, c), prati=True, timeout='12')
        if kod != '200' or sk != '1' or kraj != '%s/kategorija/%s' % (BAZA, c):
            g.append('?category=%s → %s (%s skoka)' % (c, kraj, sk))
    zabiljezi('B2', 'Stari ?category= ide jednim skokom na /kategorija/', g, len(kat))

    g = []
    varijante = [
        ('/paneli/3d-letvica-honey-oak/', '/paneli/3d-letvica-honey-oak'),
        ('/PANELI/3d-letvica-honey-oak', '/paneli/3d-letvica-honey-oak'),
        ('/paneli/3D-Letvica-Honey-Oak', '/paneli/3d-letvica-honey-oak'),
        ('/kategorija/3d-letvice/', '/kategorija/3d-letvice'),
        ('/KATEGORIJA/3d-letvice', '/kategorija/3d-letvice'),
        ('/index.html', '/'),
    ]
    for put, ocek in varijante:
        _, kod, sk, kraj = dohvati(BAZA + put, prati=True, timeout='12')
        if kod != '200' or kraj != BAZA + ocek:
            g.append('%s → %s (%s)' % (put, kraj, kod))
    _, kod, _, kraj = dohvati('https://www.makemyhome.me/', prati=True, timeout='12')
    if kraj != BAZA + '/':
        g.append('www → %s' % kraj)
    zabiljezi('B3', 'Sve varijante adrese vode na jednu kanonsku', g, len(varijante) + 1)

    g = []
    for u, (h, kod, _, _) in STRANICE.items():
        if kod != '200':
            continue
        bez = re.sub(r'<script[^>]*>.*?</script>', '', h, flags=re.S)
        if re.search(r'product\.html\?id=|products\.html\?category=', bez):
            g.append(u)
    zabiljezi('B4', 'Nijedna stranica ne sadrzi stari oblik adrese', g, len(SITEMAP))


# ============================================================
# C — SADRZAJ
# ============================================================
def grupa_C():
    print('\n=== C · SADRZAJ ===')
    naslovi, opisi = collections.defaultdict(list), collections.defaultdict(list)
    g1 = g2 = g3 = []
    g1, g2, g3, g4, g5 = [], [], [], [], []
    for u, (h, kod, _, _) in STRANICE.items():
        if kod != '200':
            continue
        n = len(re.findall(r'<h1[\s>]', h))
        if n != 1:
            g1.append('%s → %d H1' % (u, n))
        t = re.search(r'<title>(.*?)</title>', h, re.S)
        if not t:
            g2.append('%s → nema title' % u)
        else:
            tt = re.sub(r'\s+', ' ', t.group(1)).strip()
            naslovi[tt].append(u)
            if len(tt) > 65:
                g2.append('%s → title %d znakova' % (u, len(tt)))
        d = re.search(r'<meta name="description" content="([^"]*)"', h)
        if not d:
            g3.append('%s → nema description' % u)
        else:
            dd = d.group(1).strip()
            opisi[dd].append(u)
            if not (70 <= len(dd) <= 165):
                g3.append('%s → opis %d znakova' % (u, len(dd)))
        v = tekst_bez_skripti(h)
        for obr, ime in [(r'[А-Яа-яЁё]', 'cirilica'), (r'Ã[\x80-\xbf]|â€', 'mojibake'),
                         (r'\bundefined\b', 'undefined'), (r'\bNaN\b', 'NaN'),
                         (r'\$\{', 'nerazrijesen JS sablon'), (r'<\?php', 'neizvrsen PHP')]:
            if re.search(obr, v):
                g4.append('%s → %s' % (u, ime))
        bezalt = [m for m in re.findall(r'<img\b[^>]*>', h) if 'alt=' not in m]
        if bezalt:
            g5.append('%s → %d slika bez alt' % (u, len(bezalt)))
    for t, l in naslovi.items():
        if len(l) > 1:
            g2.append('isti title na %d stranica: %s' % (len(l), t[:40]))
    for d, l in opisi.items():
        if len(l) > 1:
            g3.append('isti opis na %d stranica' % len(l))
    zabiljezi('C1', 'Tacno jedan H1 po stranici', g1, len(SITEMAP))
    zabiljezi('C2', 'Title jedinstven i do 65 znakova', g2, len(SITEMAP))
    zabiljezi('C3', 'Opis jedinstven, 70-165 znakova', g3, len(SITEMAP))
    zabiljezi('C4', 'Nema cirilice, mojibakea, undefined, NaN', g4, len(SITEMAP))
    zabiljezi('C5', 'Svaka slika ima alt', g5, len(SITEMAP))


# ============================================================
# D — STRUKTURIRANI PODACI
# ============================================================
def grupa_D():
    print('\n=== D · STRUKTURIRANI PODACI ===')
    g1, g2, g3, g4, g5 = [], [], [], [], []
    ponuda = 0
    for u, (h, kod, _, _) in STRANICE.items():
        if kod != '200':
            continue
        blokovi = []
        for b in re.findall(r'application/ld\+json[^>]*>(.*?)</script>', h, re.S):
            try:
                blokovi.append(json.loads(b))
            except Exception as e:
                g1.append('%s → %s' % (u, str(e)[:40]))
        for d in blokovi:
            for o in (d if isinstance(d, list) else [d]):
                if not isinstance(o, dict):
                    continue
                t = o.get('@type')
                if t == 'BreadcrumbList':
                    poz = [e.get('position') for e in o.get('itemListElement', [])]
                    if poz != list(range(1, len(poz) + 1)):
                        g5.append('%s → pozicije %s' % (u, poz))
                proizvodi = []
                if t == 'Product':
                    proizvodi.append(o)
                if t == 'ItemList':
                    for e in o.get('itemListElement', []):
                        it = e.get('item') if isinstance(e, dict) else None
                        if isinstance(it, dict) and it.get('@type') == 'Product':
                            proizvodi.append(it)
                for p in proizvodi:
                    for k in ('name', 'image', 'description', 'offers', 'sku', 'brand'):
                        if not p.get(k):
                            g2.append('%s → Product bez %s' % (u, k))
                    if p.get('aggregateRating') or p.get('review'):
                        g4.append('%s → ima ocjene (namjerno iskljuceno)' % u)
                    of = p.get('offers') or {}
                    if isinstance(of, dict):
                        ponuda += 1
                        for k in ('price', 'priceCurrency', 'availability', 'itemCondition',
                                  'validFrom', 'priceValidUntil'):
                            if not of.get(k):
                                g3.append('%s → offers bez %s' % (u, k))
    zabiljezi('D1', 'Svi JSON-LD blokovi se parsiraju', g1, len(SITEMAP))
    zabiljezi('D2', 'Product ima name/image/description/offers/sku/brand', g2, len(SITEMAP))
    zabiljezi('D3', 'Ponuda ima cijenu, valutu, stanje, validFrom, priceValidUntil', g3, ponuda)
    zabiljezi('D4', 'Nigdje se ne salju ocjene Google-u', g4, len(SITEMAP))
    zabiljezi('D5', 'Breadcrumb pozicije idu 1,2,3…', g5, len(SITEMAP))


# ============================================================
# E — RESURSI I LINKOVI  (sporo)
# ============================================================
def grupa_E():
    print('\n=== E · RESURSI I LINKOVI (sporo) ===')
    g = []
    for u, (h, kod, _, _) in STRANICE.items():
        if kod != '200':
            continue
        if ('/paneli/' in u or '/kategorija/' in u) and '<base ' not in h:
            g.append('%s → nema <base>, relativne putanje ce pucati' % u)
    zabiljezi('E1', 'Ugnijezdene stranice imaju <base>', g, len(SITEMAP))

    mete = set()
    for u, (h, kod, _, _) in STRANICE.items():
        if kod != '200':
            continue
        b = re.search(r'<base href="([^"]*)"', h)
        baza = b.group(1) if b else u
        bez = re.sub(r'<script[^>]*>.*?</script>', '', h, flags=re.S)
        for m in re.findall(r'(?:href|src)="([^"#][^"]*)"', bez):
            if m.startswith(('mailto:', 'tel:', 'javascript:', 'data:', 'viber:')):
                continue
            a = urljoin(baza, m)
            if urlparse(a).netloc not in ('makemyhome.me', ''):
                continue
            mete.add(a.split('#')[0])
    g = []
    for a in sorted(mete):
        _, kod, sk, _ = dohvati(a, prati=True, timeout='10')
        if kod != '200':
            g.append('%s → %s' % (a, kod))
        elif sk not in ('0', '1'):
            g.append('%s → %s skokova' % (a, sk))
    zabiljezi('E2', 'Svaki link i resurs sa svake stranice radi', g, len(mete))

    slike = set()
    for x in PROIZVODI:
        if x.get('image'):
            slike.add(x['image'])
        for gg in (x.get('gallery') or []):
            slike.add(gg)
    g = []
    for s in sorted(slike):
        _, kod, _, _ = dohvati(BAZA + '/' + s.lstrip('/'), timeout='10')
        if kod != '200':
            g.append('%s → %s' % (s, kod))
    zabiljezi('E3', 'Svaka slika iz products.json postoji', g, len(slike))


# ============================================================
# F — PODACI
# ============================================================
def grupa_F():
    print('\n=== F · PODACI ===')
    g = []
    ids = collections.Counter(x.get('id') for x in PROIZVODI)
    for i, c in ids.items():
        if c > 1:
            g.append('dupliran id %s' % i)
    for x in PROIZVODI:
        for k in ('name', 'price', 'category', 'image', 'unit'):
            if not x.get(k):
                g.append('id %s bez %s' % (x.get('id'), k))
    zabiljezi('F1', 'Svaki proizvod ima id, ime, cijenu, kategoriju, sliku, jedinicu', g, len(PROIZVODI))

    g = [('id %s %s' % (x['id'], x['name'][:24])) for x in PROIZVODI
         if x.get('unit') == 'm²' and x.get('category') != 'spc-pod']
    zabiljezi('F2', 'Samo SPC pod se prodaje po m²', g, len(PROIZVODI))

    php = subprocess.run(['php', '-r',
        'require "%s/php/slug.php"; $d=json_decode(file_get_contents("php://stdin"),true); '
        '$P=$d["products"]??$d; $o=[]; foreach($P as $p) $o[$p["id"]]=mmhSlugProizvoda($p); echo json_encode($o);' % KORIJEN],
        input=json.dumps({'products': PROIZVODI}), capture_output=True, text=True)
    php_slug = json.loads(php.stdout)
    js = subprocess.run(['node', '-e', '''
const fs=require('fs');
const src=fs.readFileSync('%s/js/products.js','utf8');
const kraj=src.indexOf('window.mmhUrlKategorije = mmhUrlKategorije;')+45;
eval(src.slice(0,kraj).replace(/window\\./g,'globalThis.'));
let d=''; process.stdin.on('data',c=>d+=c).on('end',()=>{
  const P=JSON.parse(d).products; const o={};
  P.forEach(p=>o[p.id]=mmhSlugProizvoda(p));
  process.stdout.write(JSON.stringify(o));
});''' % KORIJEN], input=json.dumps({'products': PROIZVODI}), capture_output=True, text=True)
    js_slug = json.loads(js.stdout) if js.stdout.strip() else {}
    g = ['id %s: PHP %s vs JS %s' % (k, php_slug[k], js_slug.get(k))
         for k in php_slug if php_slug[k] != js_slug.get(k)]
    zabiljezi('F3', 'PHP i JavaScript prave ISTU adresu za svaki proizvod', g, len(php_slug))

    g = []
    vidjeni = {}
    for k, v in php_slug.items():
        if v in vidjeni:
            g.append('%s i %s dijele adresu %s' % (vidjeni[v], k, v))
        vidjeni[v] = k
    zabiljezi('F4', 'Nijedan proizvod ne dijeli adresu sa drugim', g, len(php_slug))


# ============================================================
# G — SERVER I BEZBJEDNOST
# ============================================================
def grupa_G():
    print('\n=== G · SERVER I BEZBJEDNOST ===')
    # Mjeri se na SVAKOM tipu stranice: statickoj, PHP proizvodu i PHP kategoriji.
    # Ranije se mjerilo samo na pocetnoj, pa se nije vidjelo da PHP stranice
    # nemaju Cache-Control (FilesMatch gleda ime fajla, ne adresu).
    g = []
    for put in ['/', '/faq.html', '/paneli/3d-letvica-honey-oak', '/kategorija/3d-letvice',
                '/cjenovnik.html', '/products.html']:
        r = subprocess.run(CURL + ['--max-time', '20', '-H', 'Accept-Encoding: gzip, br',
                                   '-D', '-', '-o', '/dev/null', BAZA + put],
                           capture_output=True, text=True).stdout.lower()
        for z in ['content-encoding: gzip', 'strict-transport-security', 'x-content-type-options',
                  'x-frame-options', 'referrer-policy', 'cache-control']:
            if z not in r:
                g.append('%s → nedostaje %s' % (put, z))
    zabiljezi('G1', 'Kompresija, zastita i Cache-Control na SVAKOM tipu stranice', g, 36)

    g = []
    for f in ['.htaccess', 'error_log', 'backup.zip', 'db.sql', 'wp-config.php.bak', '_test.php']:
        _, kod, _, _ = dohvati('%s/%s' % (BAZA, f), timeout='10')
        if kod not in ('403', '404', '410'):
            g.append('%s → %s (mora biti zabranjen)' % (f, kod))
    for d in ['images/', 'js/', 'css/', 'data/', 'php/']:
        _, kod, _, _ = dohvati('%s/%s' % (BAZA, d), timeout='10')
        if kod not in ('403', '404'):
            g.append('listanje %s → %s' % (d, kod))
    _, kod, _, _ = dohvati(BAZA + '/.git/config', timeout='10')
    if kod == '200':
        g.append('.git je javno dostupan!')
    zabiljezi('G2', 'Osjetljivi fajlovi i listanje foldera zabranjeni', g, 12)

    g = []
    hh, _, _, _ = dohvati(BAZA + '/robots.txt', timeout='10')
    if 'Sitemap: https://makemyhome.me/sitemap.xml' not in hh:
        g.append('robots.txt ne navodi sitemap')
    if 'Disallow: /admin/' not in hh:
        g.append('robots.txt ne blokira /admin/')
    for zab in ['/paneli/', '/kategorija/']:
        if 'Disallow: %s' % zab in hh:
            g.append('robots.txt blokira %s !' % zab)
    zabiljezi('G3', 'robots.txt ispravan', g, 4)

    g = []
    for f in ['css/style-v5.css', 'js/products.js', 'js/main-v4.js', 'js/cart.js',
              'llms.txt', 'robots.txt', 'sitemap.xml', '404.html', 'index.html']:
        put = os.path.join(KORIJEN, f)
        if not os.path.exists(put):
            g.append('%s ne postoji lokalno' % f)
            continue
        lok = hashlib.md5(open(put, 'rb').read()).hexdigest()
        r = subprocess.run(CURL + ['--max-time', '25', '-L', '%s/%s' % (BAZA, f)], capture_output=True).stdout
        if hashlib.md5(r).hexdigest() != lok:
            g.append('%s se razlikuje od servera' % f)
    zabiljezi('G4', 'Lokalni fajlovi identicni serveru', g, 9)

    g = []
    for f in ['php/slug.php', 'php/slug-match.php', 'product.php', 'products.php',
              'cjenovnik.php', 'inspiracija.php', '.htaccess']:
        if "'/%s'" % f not in open(os.path.join(KORIJEN, 'admin/sync.php'), encoding='utf-8').read():
            g.append('%s NIJE u sync listi' % f)
    zabiljezi('G5', 'Svi vazni fajlovi su u listi sinhronizacije', g, 7)


# ============================================================
# H — ADRESE KOJE GOOGLE STVARNO IMA
# ============================================================
def grupa_H():
    print('\n=== H · ADRESE KOJE GOOGLE IMA ===')
    put = os.path.join(KORIJEN, 'alat/gsc-adrese.txt')
    if not os.path.exists(put):
        zabiljezi('H1', 'Spisak Googlovih adresa (alat/gsc-adrese.txt)', ['fajl ne postoji'], 0)
        return
    adrese = [l.strip() for l in open(put) if l.strip().startswith('http')]
    g, opsti = [], []
    for u in adrese:
        _, kod, sk, kraj = dohvati(u, prati=True, timeout='10')
        if kod not in ('200', '410'):
            g.append('%s → %s' % (u, kod))
        elif sk.isdigit() and int(sk) > 1:
            g.append('%s → %s skokova (lanac)' % (u, sk))
        elif kod == '200' and kraj.rstrip('/') == BAZA + '/products.html' and '/product/' in u:
            opsti.append(u)
    zabiljezi('H1', 'Sve adrese koje Google ima → 200 ili 410, bez lanca', g, len(adrese))
    zabiljezi('H2', 'Ugasen proizvod ide na svoju kategoriju, ne na opsti katalog', opsti, len(adrese))


# ============================================================
# I — UNUTRASNJE POVEZIVANJE
# Stranica do koje vodi jedan jedini link Google sporo obilazi i slabo
# rangira. Ovo mjeri koliko linkova sa samog sajta vodi do svake stranice.
# ============================================================
VODICI = ['paneli-za-kupatilo.html', 'tv-zid.html', 'spc-ili-laminat.html',
          'paneli-ili-lamperija.html', 'akusticni-paneli-kancelarija.html',
          'dostava-crna-gora.html']


def grupa_I():
    print('\n=== I · UNUTRASNJE POVEZIVANJE ===')
    sve = {u.rstrip('/') for u in STRANICE}
    dolazni = collections.Counter()
    for u, (h, kod, _, _) in STRANICE.items():
        if kod != '200':
            continue
        b = re.search(r'<base href="([^"]*)"', h)
        baza = b.group(1) if b else u
        bez = re.sub(r'<script[^>]*>.*?</script>', '', h, flags=re.S)
        mete = set()
        for m in re.findall(r'<a\s[^>]*href="([^"#][^"]*)"', bez):
            if m.startswith(('mailto:', 'tel:', 'javascript:', 'data:', 'viber:')):
                continue
            a = urljoin(baza, m).split('#')[0].split('?')[0].rstrip('/')
            if a in sve and a != u.rstrip('/'):
                mete.add(a)
        for a in mete:
            dolazni[a] += 1

    g = [u for u in STRANICE if dolazni[u.rstrip('/')] == 0]
    zabiljezi('I1', 'Nijedna stranica nije siroce (bar jedan link vodi do nje)',
              g, len(STRANICE))

    proizvodi = [u for u in STRANICE if '/paneli/' in u]
    g = ['%s → samo %d' % (u, dolazni[u.rstrip('/')])
         for u in proizvodi if dolazni[u.rstrip('/')] < 3]
    zabiljezi('I2', 'Svaki proizvod ima bar 3 dolazna linka', g, len(proizvodi))

    g = ['/%s → samo %d' % (v, dolazni[(BAZA + '/' + v).rstrip('/')])
         for v in VODICI if dolazni[(BAZA + '/' + v).rstrip('/')] < 10]
    zabiljezi('I3', 'Svaki vodic je linkovan sa bar 10 stranica', g, len(VODICI))


# ============================================================
GRUPE = {'A': grupa_A, 'B': grupa_B, 'C': grupa_C, 'D': grupa_D,
         'E': grupa_E, 'F': grupa_F, 'G': grupa_G, 'H': grupa_H,
         'I': grupa_I}

if __name__ == '__main__':
    arg = (sys.argv[1] if len(sys.argv) > 1 else 'brzo').upper()
    if arg == 'SVE':
        red = 'ABCDEFGHI'
    elif arg == 'BRZO':
        red = 'ACDFGI'
    else:
        red = arg
    for k in red:
        if k in GRUPE:
            GRUPE[k]()
    pali = [r for r in rezultati if r[2]]
    print('\n' + '=' * 66)
    print('ZAVRSNO: %d pravila provjereno, %d palo' % (len(rezultati), len(pali)))
    for s, o, gr, _ in pali:
        print('  PAD %-5s %s  (%d)' % (s, o, len(gr)))
    sys.exit(1 if pali else 0)
