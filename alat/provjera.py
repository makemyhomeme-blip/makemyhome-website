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
    # Kod 000 znaci da veza uopste nije uspostavljena — to je najcesce trenutni
    # prekid u mrezi, a ne greska na sajtu. Zato se pokusava jos dva puta;
    # bez toga je provjera znala da prijavi ispravnu adresu kao pokvarenu.
    cmd = CURL + ['--max-time', timeout, '-w', '\n@@%{http_code}|%{num_redirects}|%{url_effective}']
    if prati:
        cmd += ['-L', '--max-redirs', '5']
    for pokusaj in range(3):
        r = subprocess.run(cmd + [u], capture_output=True, text=True, errors='replace').stdout
        i = r.rfind('\n@@')
        if i < 0:
            continue
        kod, sk, kraj = r[i + 3:].split('|', 2)
        if kod != '000':
            return r[:i], kod, sk, kraj.strip()
    return '', '000', '0', ''


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
            # 140-160 je opseg koji Google prikaze cijel. Ispod 140 cesto odbaci
            # nas tekst i sam sastavi opis iz stranice, iznad 160 odsijece kraj.
            if not (140 <= len(dd) <= 160):
                g3.append('%s → opis %d znakova' % (u, len(dd)))
        v = tekst_bez_skripti(h)
        for obr, ime in [(r'[А-Яа-яЁё]', 'cirilica'), (r'Ã[\x80-\xbf]|â€', 'mojibake'),
                         (r'\bundefined\b', 'undefined'), (r'\bNaN\b', 'NaN'),
                         (r'\$\{', 'nerazrijesen JS sablon'), (r'<\?php', 'neizvrsen PHP')]:
            if re.search(obr, v):
                g4.append('%s → %s' % (u, ime))
        # Traziti <img u sirovom HTML-u znaci naci ga i u komentaru unutar
        # <style> ili <script>. Tako je jedan CSS komentar oborio ovu provjeru
        # iako na stranici nije bilo nijedne slike bez alt. Blokovi sa kodom
        # se izbacuju prije trazenja.
        bezKoda = re.sub(r'<(style|script)\b[^>]*>.*?</\1>', '', h, flags=re.S | re.I)
        bezalt = [m for m in re.findall(r'<img\b[^>]*>', bezKoda) if 'alt=' not in m]
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
    zabiljezi('C3', 'Opis jedinstven, 140-160 znakova', g3, len(SITEMAP))
    zabiljezi('C4', 'Nema cirilice, mojibakea, undefined, NaN', g4, len(SITEMAP))
    zabiljezi('C5', 'Svaka slika ima alt', g5, len(SITEMAP))

    # fa/css/mmh-ikone.css nosi samo ikone koje sajt koristi (100 kB -> 22 kB).
    # Kad neko doda novu ikonu na stranicu a zaboravi da pokrene alat/ikone.py,
    # ikona se prikaze kao prazan kvadratic. To se lako previdi jer sve ostalo
    # radi. Zato se ovdje uporedjuje sta stranice traze sa onim sto CSS ima.
    g = []
    ikone = set()
    try:
        sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
        import ikone as _ik
        ikone = _ik.koriscene_ikone()
        # Ikone kategorija bira vlasnik kroz admin i cuvaju se u
        # data/categories.json, koji se NE deployuje sa lokalnog. Zato se
        # spisak dopunjuje onim sto na serveru stvarno stoji — inace bi izbor
        # nove ikone u adminu dao prazan kvadratic, a provjera bi rekla da je
        # sve u redu jer lokalni fajl o toj ikoni ne zna nista.
        kat, kodK, _, _ = dohvati('%s/data/categories.json' % BAZA)
        if kodK == '200':
            ikone |= {m for m in re.findall(r'"icon"\s*:\s*"[^"]*\bfa-([a-z0-9-]+)', kat)}
        css, kod, _, _ = dohvati('%s/fa/css/mmh-ikone.css' % BAZA)
        if kod != '200':
            g.append('mmh-ikone.css nije dostupan na sajtu (kod %s)' % kod)
        else:
            ima = set()
            for p in re.findall(r'\.fa-[a-z0-9-]+(?:[:,][^{]*)?\{content:"\\[0-9a-f]+"[^}]*\}', css):
                ima |= set(re.findall(r'\.fa-([a-z0-9-]+)(?=:|,|\{)', p))
            for f in sorted(ikone - ima):
                g.append('fa-%s se koristi na sajtu ali je nema u CSS-u — prikazace se prazan kvadratic '
                         '(pokreni: python3 alat/ikone.py)' % f)
    except Exception as e:
        g.append('provjera ikona nije mogla da se izvrsi: %s' % e)
    zabiljezi('C6', 'Svaka ikona koju stranice traze postoji u CSS-u', g, len(ikone))

    # ---- Ime, adresa i telefon moraju biti ISTI svuda ----------------------
    #
    # Google uporedjuje ove tri stvari sa sajta sa onim sto stoji na profilu
    # firme. Kad se ne poklapaju, slabije povezuje profil i sajt — a bas ta
    # veza izbacuje firmu u mapu i u lokalne rezultate. Ovo se stvarno desilo:
    # sajt je pisao broj 41, a profil 43, i to je stajalo nezapazeno jer se
    # adresa pojavljuje na 48 mjesta u 23 fajla i niko ih ne poredi rucno.
    ADRESA  = 'Vojvode Maša Đurovića 43'
    TELEFON = '069 105 222'
    g = []
    brojevi, bezAdrese = collections.Counter(), []
    for u, (h, kod, _, _) in STRANICE.items():
        if kod != '200':
            continue
        for m in re.findall(r'Vojvode Maša Đurovića\s*([0-9][0-9-]*)', h):
            brojevi[m] += 1
        if 'Vojvode Maša Đurovića' in h and ADRESA not in h:
            bezAdrese.append(u)
    for b in sorted(brojevi):
        if b != '43':
            g.append('negdje pise kucni broj %s umjesto 43 (%d puta)' % (b, brojevi[b]))
    for u in bezAdrese[:5]:
        g.append('%s → adresa nije u tacnom obliku' % u)

    # Telefon i adresa u strukturiranim podacima — to Google zaista cita
    for u in (BAZA + '/', BAZA + '/contact.html', BAZA + '/about.html'):
        h = STRANICE.get(u, ('', '', '', ''))[0]
        if not h:
            continue
        for blok in re.findall(r'<script type="application/ld\+json">(.*?)</script>', h, re.S):
            if '"streetAddress"' not in blok:
                continue
            sa = re.search(r'"streetAddress"\s*:\s*"([^"]*)"', blok)
            if sa and ADRESA not in sa.group(1):
                g.append('%s → streetAddress u strukturiranim podacima: %s' % (u, sa.group(1)))
            tel = re.search(r'"telephone"\s*:\s*"([^"]*)"', blok)
            # Poredi se zadnjih 8 cifara: isti broj se pise i kao +382 69 105 222
            # i kao 069 105 222, pa vodeca nula odnosno pozivni ne smiju da smetaju.
            if tel and re.sub(r'\D', '', tel.group(1))[-8:] != re.sub(r'\D', '', TELEFON)[-8:]:
                g.append('%s → telefon u strukturiranim podacima: %s' % (u, tel.group(1)))
    zabiljezi('C7', 'Adresa i telefon isti na cijelom sajtu', g, len(STRANICE))


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

    # data/inquiries.json cuva ime, email, telefon i poruku svakog kupca, a
    # /data/ je javan direktorijum. Bio je otvoren svakome ko zna adresu.
    # Ovo pravilo pazi da se to ne vrati, i da se ne zatvore fajlovi koji
    # sajtu trebaju.
    g = []
    for f in ['data/inquiries.json', 'data/inquiries.json.tmp',
              'data/products.bak.20260807-174841.json', 'data/actions_debug.log',
              'data/upload_debug.log', 'admin/listall.php', 'admin/listgallery.php']:
        _, kod, _, _ = dohvati('%s/%s' % (BAZA, f), timeout='10')
        if kod not in ('403', '404', '410'):
            g.append('%s → %s (podaci kupaca / mrtvi fajl, mora biti zatvoren)' % (f, kod))
    for f in ['data/products.json', 'data/categories.json', 'data/reviews.json']:
        _, kod, _, _ = dohvati('%s/%s' % (BAZA, f), timeout='10')
        if kod != '200':
            g.append('%s → %s (sajtu treba, ne smije biti zatvoren)' % (f, kod))
    zabiljezi('G6', 'Podaci kupaca zatvoreni, podaci sajta otvoreni', g, 10)

    # Sudar admina i sinhronizacije.
    # Admin je CSS za velicinu slika na Decor Box stranici upisivao pravo u
    # decor-box.html — a taj fajl je bio u sync listi. Vlasnik bi namjestio
    # visinu, sve bi radilo, i onda bi prvi sljedeci sync vratio staro, bez
    # ijedne poruke. Ovo pravilo trazi svaki fajl u koji admin pise i provjerava
    # da nijedan nije u spisku koji sync prepisuje.
    g = []
    lista = ''
    for put in ('admin/sync-lista.php', 'admin/sync.php'):
        pp = os.path.join(KORIJEN, put)
        if os.path.exists(pp):
            lista += open(pp, encoding='utf-8').read()
    usync = set(re.findall(r"\$base \. '/([^']+)'", lista))
    pise = set()
    for f in os.listdir(os.path.join(KORIJEN, 'admin')):
        if not f.endswith('.php') or f in ('sync.php', 'sync-lista.php'):
            continue
        t = open(os.path.join(KORIJEN, 'admin', f), encoding='utf-8').read()
        # putanje oblika __DIR__ . '/../nesto'
        for m in re.findall(r"__DIR__ \. '/\.\./([^']+)'", t):
            pise.add(m.strip('/'))
    for m in sorted(pise):
        if m in usync:
            g.append('admin pise u %s, a sync ga prepisuje — izmjena bi se gubila' % m)
    zabiljezi('G7', 'Nijedan fajl koji admin mijenja nije u sync listi', g, len(pise))

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

    # ---- Git, cPanel i zivi sajt moraju nositi ISTE fajlove ----------------
    #
    # Ranije je ovdje provjeravano samo sedam fajlova, pa se ostalo moglo
    # razici a da niko ne primijeti: u gitu jedno, na serveru drugo, a na
    # sajtu se vidi trece. Sada se provjerava SVAKI fajl iz sync liste.
    #
    # Dva nacina, jer se ne servira sve isto:
    #   * fajl koji se salje takav kakav jeste (css, js, txt, woff2, slike i
    #     one .html stranice koje .htaccess ne prepisuje na PHP) — skida se
    #     preko HTTPS-a i uporedjuje bajt po bajt;
    #   * fajl koji se ne moze skinuti u izvornom obliku (svi .php, .htaccess,
    #     i .html koje .htaccess prepisuje na PHP) — poredi se VELICINA preko
    #     cPanel API-ja. Sadrzaj se preko tog API-ja ne moze porediti jer ga
    #     on normalizuje (npr. spoji <head> i <meta charset>), pa je znao da
    #     prijavi razliku koje nema. Velicina dolazi sa diska i ne laze.
    g = []

    # Prvo: da li je LOKALNI git uopste na onome sto je pushovano?
    #
    # Bez ovoga pravilo zna da slaze. Radni direktorijum se zna vratiti unazad
    # (kontejner se resetuje), pa lokalno stoji stara verzija dok su server i
    # GitHub na novoj. G4 tada prijavi trideset razlika i za svaku kaze "na
    # serveru toliko, u gitu toliko" — a nijedna nije prava: server je u redu,
    # lokalna kopija je zastarjela. Zato se prvo provjeri to, i ako lokalno
    # kaska, kaze se TO umjesto trideset lazi. Lijek je `git reset --hard`,
    # ne ponovni sync.
    def _git(*a):
        return subprocess.run(['git', '-C', KORIJEN, *a],
                              capture_output=True, text=True, timeout=60).stdout.strip()
    try:
        grana = _git('rev-parse', '--abbrev-ref', 'HEAD')
        subprocess.run(['git', '-C', KORIJEN, 'fetch', '--quiet', 'origin', grana],
                       capture_output=True, timeout=120)
        lok_h, dalj_h = _git('rev-parse', 'HEAD'), _git('rev-parse', 'origin/' + grana)
        if lok_h and dalj_h and lok_h != dalj_h:
            iza = _git('rev-list', '--count', 'HEAD..origin/' + grana)
            ispred = _git('rev-list', '--count', 'origin/%s..HEAD' % grana)
            g.append('LOKALNI GIT NIJE NA POSLJEDNJEM STANJU: %s iza, %s ispred origin/%s'
                     ' — sve razlike ispod su zato lazne; uradi `git reset --hard origin/%s`'
                     % (iza or '?', ispred or '?', grana, grana))
        prljavo = [x for x in _git('status', '--porcelain').splitlines() if x.strip()]
        if prljavo:
            g.append('lokalno ima %d neupisanih izmjena (nisu ni commitovane ni pushovane): %s'
                     % (len(prljavo), ', '.join(x.split(maxsplit=1)[-1] for x in prljavo[:4])))
    except Exception as e:
        g.append('ne mogu provjeriti stanje gita: %s' % e)

    lista_php = os.path.join(KORIJEN, 'admin', 'sync-lista.php')
    parovi = []
    if os.path.exists(lista_php):
        izl = subprocess.run(['php', '-r',
            '$f = require "%s"; foreach ($f("", "", "admin") as $lok => $_) echo $lok . "\\n";' % lista_php],
            capture_output=True, text=True).stdout
        parovi = [x.strip().lstrip('/') for x in izl.splitlines() if x.strip()]

    # .html adrese koje .htaccess prepisuje na PHP — njih se ne moze skinuti sirove
    PREKO_PHP = {'index.html', 'products.html', 'product.html', 'cjenovnik.html',
                 'inspiracija.html', 'decor-box.html'}
    PREKO_HTTP = ('.css', '.js', '.txt', '.woff2', '.ico', '.png', '.jpg', '.webp')

    srv_vel = {}
    for folder in ('', 'css', 'js', 'fa/css', 'fa/webfonts', 'fonts', 'images', 'php', 'admin'):
        dir_srv = '/home/mmhdecor/public_html/makemyhome.me' + ('/' + folder if folder else '')
        r = subprocess.run(CURL + ['--max-time', '30', '-u', 'mmhdecor:fhgkwqjd0F6K',
            'https://cpanel.mmhdecor.mycpanel.rs/execute/Fileman/list_files?dir=%s&include_mime=0'
            '&show_hidden=1' % dir_srv.replace('/', '%2F')], capture_output=True, text=True).stdout
        try:
            for x in (json.loads(r).get('data') or []):
                kljuc = (folder + '/' if folder else '') + x.get('file', '')
                srv_vel[kljuc] = int(x.get('size') or 0)
        except Exception:
            pass

    provjereno = 0
    for rel in parovi:
        put = os.path.join(KORIJEN, rel)
        if not os.path.exists(put):
            g.append('%s je u sync listi a nema ga lokalno' % rel)
            continue
        provjereno += 1
        sirovo = rel.endswith(PREKO_HTTP) or (rel.endswith('.html') and rel not in PREKO_PHP)
        if sirovo:
            lok = hashlib.md5(open(put, 'rb').read()).hexdigest()
            r = subprocess.run(CURL + ['--max-time', '25', '-L', '-H', 'Accept-Encoding: identity',
                                       '%s/%s' % (BAZA, rel)], capture_output=True).stdout
            if hashlib.md5(r).hexdigest() != lok:
                g.append('%s: sadrzaj na sajtu nije isti kao u gitu' % rel)
        else:
            if rel not in srv_vel:
                g.append('%s: nema ga na serveru' % rel)
            elif srv_vel[rel] != os.path.getsize(put):
                g.append('%s: na serveru %d B, u gitu %d B' % (rel, srv_vel[rel], os.path.getsize(put)))
    zabiljezi('G4', 'Git, cPanel i sajt nose iste fajlove', g, provjereno)

    # Stranice koje server sastavlja: mora da se vidi ono sto Google treba da
    # procita, i to BEZ JavaScripta. Ranije su ovi blokovi bili prazni.
    g = []
    h, kod, _, _ = dohvati(BAZA + '/', timeout='20')
    if kod != '200':
        g.append('pocetna → %s' % kod)
    else:
        if h.count('product-card') < 3:
            g.append('pocetna: manje od 3 kartice proizvoda u HTML-u (puni ih JavaScript?)')
        if 'loading-placeholder' in h:
            g.append('pocetna: ostao prazan blok koji ceka JavaScript')
    h, kod, _, _ = dohvati(BAZA + '/products.html', timeout='20')
    if kod != '200':
        g.append('katalog → %s' % kod)
    elif h.count('cat-card"') < 6:
        g.append('katalog: manje od 6 kartica kategorija u HTML-u')
    h, kod, _, _ = dohvati(BAZA + '/sitemap.xml', timeout='25')
    if kod != '200':
        g.append('sitemap → %s' % kod)
    else:
        if '<urlset' not in h:
            g.append('sitemap nije urlset')
        br_slika = h.count('<image:image>')
        if br_slika < 300:
            g.append('sitemap ima samo %d slika (ocekivano preko 300)' % br_slika)
        # <loc> su adrese stranica; slike koriste <image:loc>, pa se ne mijesaju.
        # Prva verzija ovog pravila ih je oduzimala i uvijek javljala gresku.
        br_adresa = h.count('<loc>')
        if br_adresa < 140:
            g.append('sitemap ima samo %d adresa (ocekivano preko 140)' % br_adresa)
    zabiljezi('G8', 'Server sastavlja pocetnu, katalog i sitemap kako treba', g, 3)

    # Sve sto Googlebot NE vidi u sirovom HTML-u.
    # Ovako su otkriveni: prazna galerija na svih 117 stranica proizvoda,
    # prazan spisak kategorija u podnozju na 15 stranica, prazan blok
    # izdvojenih proizvoda na pocetnoj i prazna mreza kategorija na katalogu.
    # Nista od toga nije bilo vidljivo ni u jednoj drugoj provjeri.
    DOZVOLJENO = {'products-container', 'cout', 'form-message', 'mob-search-results',
                  'desk-search-results', 'back-bar', 'insp-prazno', 'toast', 'img-lightbox',
                  'gallery-specs'}
    g = []
    for u, (h, kod, _, _) in STRANICE.items():
        if kod != '200':
            continue
        vidljivo = re.sub(r'<script[^>]*>.*?</script>', '', h, flags=re.S)
        if 'loading-placeholder' in vidljivo:
            g.append('%s → ostao prazan blok koji ceka JavaScript' % u)
            continue
        for m in re.finditer(r'<(div|section|ul|tbody)\b[^>]*id="([a-z0-9-]+)"[^>]*>(.*?)</\1>', vidljivo, re.S):
            if m.group(2) in DOZVOLJENO:
                continue
            unut = re.sub(r'\s+', ' ', re.sub(r'<!--.*?-->', '', m.group(3), flags=re.S)).strip()
            if len(unut) < 40:
                g.append('%s → prazan <%s id="%s">' % (u, m.group(1), m.group(2)))
                break
    zabiljezi('G9', 'Nista sto Google treba ne ceka JavaScript', g, len(STRANICE))

    # Spisak je od 11.08.2026 u admin/sync-lista.php, ne vise u sync.php.
    # Fajl koji nije u spisku nikad ne stigne na server — a ako ga product.php
    # trazi preko require, cijeli sajt vrati 500.
    g = []
    lista = ''
    for put in ('admin/sync-lista.php', 'admin/sync.php'):
        p = os.path.join(KORIJEN, put)
        if os.path.exists(p):
            lista += open(p, encoding='utf-8').read()
    vazni = ['php/slug.php', 'php/dimenzije.php', 'php/slug-match.php', 'php/contact.php',
             'product.php', 'products.php', 'cjenovnik.php', 'inspiracija.php', '.htaccess',
             'sitemap.php', 'pocetna.php', 'robots.txt', 'css/style-v5.css', 'js/products.js',
             'admin/sync.php', 'admin/sync-lista.php']
    for f in vazni:
        if "'/%s'" % f not in lista and "'%s'" % f not in lista:
            g.append('%s NIJE u sync listi' % f)
    # Svaki fajl koji neki PHP trazi preko require MORA biti u spisku
    for izvor in ('product.php', 'products.php', 'inspiracija.php', 'cjenovnik.php'):
        p = os.path.join(KORIJEN, izvor)
        if not os.path.exists(p):
            continue
        for m in re.findall(r"require(?:_once)?\s+__DIR__\s*\.\s*'/([^']+)'", open(p, encoding='utf-8').read()):
            if "'/%s'" % m not in lista:
                g.append('%s trazi %s, a njega nema u sync listi (sajt bi vratio 500)' % (izvor, m))
    zabiljezi('G5', 'Svi vazni fajlovi su u listi sinhronizacije', g, len(vazni))

    # WebP se servira na istoj adresi kao JPG, preko Accept zaglavlja. Tri
    # stvari mogu tiho da se pokvare i niko ne bi primijetio:
    #   1. pravilo u .htaccess nestane pri nekoj izmjeni — sajt radi, samo je
    #      opet dvostruko tezi;
    #   2. webp ostane stariji od originala — vlasnik promijeni fotografiju,
    #      a posjetioci mjesecima gledaju staru;
    #   3. pregledac bez WebP-a dobije webp i vidi pokvarenu sliku.
    # Zato se sve troje mjeri na stvarnim slikama sa sajta.
    g = []
    slike = []
    h, kod, _, _ = dohvati('%s/kategorija/3d-letvice' % BAZA)
    if kod == '200':
        slike = [s for s in re.findall(r'<img[^>]+src="([^"]+)"', h) if '/products/' in s][:8]
    for rel in slike:
        u = BAZA + '/' + rel.lstrip('/')
        zag = subprocess.run(
            CURL + ['-sI', '--max-time', '25', '-H', 'Accept: image/webp,image/*,*/*', u],
            capture_output=True, text=True, errors='replace').stdout.lower()
        zagJpg = subprocess.run(
            CURL + ['-sI', '--max-time', '25', '-H', 'Accept: image/jpeg,image/*,*/*', u],
            capture_output=True, text=True, errors='replace').stdout.lower()
        ime = rel.split('/')[-1]
        if 'image/webp' not in zag:
            g.append('%s ne vraca webp pregledacu koji ga trazi' % ime)
        if 'image/webp' in zagJpg:
            g.append('%s vraca webp i pregledacu koji ga NE trazi' % ime)
        if 'vary: accept' not in zag:
            g.append('%s nema Vary: Accept — posrednicki kes moze pomijesati verzije' % ime)
        try:
            duz = int(re.search(r'content-length:\s*(\d+)', zag).group(1))
            duzJ = int(re.search(r'content-length:\s*(\d+)', zagJpg).group(1))
            if duz >= duzJ:
                g.append('%s: webp (%d B) nije manji od originala (%d B)' % (ime, duz, duzJ))
        except (AttributeError, ValueError):
            pass
    if not slike:
        g.append('nijedna slika proizvoda nije nadjena na kategoriji — provjera nije mogla da se izvrsi')
    zabiljezi('G10', 'Slike se serviraju kao WebP samo onome ko ga cita', g, len(slike))

    # ---- JavaScript ne smije da prepisuje ono sto je server ispisao --------
    #
    # Ovo je bio izvor najskupljih gresaka na sajtu. Funkcije koje crtaju
    # kartice zvale su se pri ucitavanju i BEZUSLOVNO brisale serverski
    # sadrzaj pa ga crtale iznova. Posljedice koje su stvarno izmjerene:
    #   * renderCategories je slike kategorija vracao na CSS pozadine, a one
    #     ne poznaju loading="lazy" — svih 640 kB se skidalo odmah;
    #   * galerija proizvoda se crtala drugi put, pa se prva slika ucitavala
    #     dvaput;
    #   * svaki takav ispis nosi rizik da se razidje od onoga sto je server
    #     poslao Googleu.
    #
    # Zato svaka takva funkcija mora imati "ogradu" — provjeru da je server
    # vec odradio posao. Ako neko ogradu ukloni, ovo pravilo pada.
    g = []
    put_js = os.path.join(KORIJEN, 'js', 'products.js')
    if not os.path.exists(put_js):
        g.append('js/products.js ne postoji')
    else:
        kod_js = open(put_js, encoding='utf-8').read()
        ograde = [
            ('renderFeatured',       "querySelectorAll('.product-card')"),
            ('renderCategories',     "container.querySelector('.category-card')"),
            ('showCategoryGrid',     "grid.querySelector('.cat-card')"),
            ('showCategoryProducts', "container.querySelector('.product-card')"),
            ('renderProductDetail',  'vecIspisana'),
        ]
        for ime, ograda in ograde:
            m = re.search(r'function %s\s*\(' % re.escape(ime), kod_js)
            if not m:
                g.append('funkcija %s vise ne postoji — provjeri da li je ograda prenesena' % ime)
                continue
            # tijelo do sljedece deklaracije funkcije na pocetku reda
            k = re.search(r'\n(?:async )?function ', kod_js[m.end():])
            tijelo = kod_js[m.end(): m.end() + (k.start() if k else 4000)]
            if ograda not in tijelo:
                g.append('%s vise ne provjerava da li je server vec ispisao sadrzaj '
                         '(nedostaje: %s)' % (ime, ograda))
    zabiljezi('G11', 'JavaScript ne prepisuje ono sto je server ispisao', g, 5)


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

    # ---- Adresa bez zamjene mora reci "obrisano", ne preusmjeravati --------
    #
    # Preusmjerenje Googleu znaci "sadrzaj je premjesten ovdje". RSS feed i
    # WordPress-ova fiktivna kategorija "uncategorized" nemaju zamjenu na novom
    # sajtu — slati ih na pocetnu ili katalog je neistina, a Google to tretira
    # kao meku 404 i drzi adresu u redu za obilazak unedogled. Bas to nas je
    # mjesecima drzalo zakacene za stari WordPress sajt.
    # 410 znaci "trajno obrisano": Google je izbaci i vise ne dolazi po nju.
    bez_zamjene = ['/feed/', '/comments/feed/', '/product-category/aku-paneli/feed/',
                   '/category/uncategorized/', '/hello-world/']
    g = []
    for p in bez_zamjene:
        _, kod, _, _ = dohvati(BAZA + p, timeout='12')
        if kod != '410':
            g.append('%s → %s (mora 410, nema zamjenu na novom sajtu)' % (p, kod))
    # a ono STO ima zamjenu mora i dalje voditi na nju
    _, kod, sk, kraj = dohvati(BAZA + '/product/mocha-oak/feed/', prati=True, timeout='12')
    if kod != '200' or 'mocha-oak' not in kraj:
        g.append('/product/mocha-oak/feed/ → %s %s (mora voditi na taj proizvod)' % (kod, kraj))
    zabiljezi('H3', 'Stara adresa bez zamjene vraca 410, sa zamjenom vodi na nju',
              g, len(bez_zamjene) + 1)


def grupa_R():
    print('\n=== R · RECENZIJE ===')
    # Recenzije se traze po ID-u proizvoda u data/reviews.json. Kad su adrese
    # promijenjene u /paneli/..., iz zahtjeva je nestao ?id=, pa je ID ostajao
    # nula i svaka stranica je pokazivala STARE tri recenzije iz products.json
    # umjesto pet novijih. Nista drugo na stranici nije odavalo gresku.
    import urllib.request as _u
    rev, _, _, _ = dohvati(BAZA + '/data/reviews.json')
    try:
        REV = json.loads(rev)
    except Exception:
        zabiljezi('R1', 'Svaka recenzija iz podataka se vidi na stranici', ['ne mogu procitati reviews.json'], 1)
        return
    php = subprocess.run(['php', '-r',
        'require "%s/php/slug.php"; $d=json_decode(file_get_contents("php://stdin"),true); '
        '$o=[]; foreach($d as $p) $o[$p["id"]]=mmhSlugProizvoda($p); echo json_encode($o);' % KORIJEN],
        input=json.dumps(PROIZVODI), capture_output=True, text=True)
    slug = json.loads(php.stdout)
    g = []
    uk_ocek = uk_prik = 0
    for pid, blok in REV.items():
        if pid not in slug:
            continue
        ocek = [i.get('name') for i in (blok.get('items') or [])]
        h, kod, _, _ = dohvati('%s/%s' % (BAZA, slug[pid]), timeout='15')
        if kod != '200':
            g.append('%s → %s' % (slug[pid], kod))
            continue
        imena = [re.sub(r'<.*', '', x).strip() for x in re.findall(r'class="rv-card-name">([^<]*)', h)]
        uk_ocek += len(ocek); uk_prik += len(imena)
        fale = [n for n in ocek if n not in imena]
        if fale:
            g.append('%s → fali %d recenzija (%s)' % (slug[pid], len(fale), ', '.join(fale[:2])))
    zabiljezi('R1', 'Svaka recenzija iz podataka se vidi na stranici', g, len(REV))
    g = []
    if uk_prik != uk_ocek:
        g.append('prikazano %d od %d recenzija' % (uk_prik, uk_ocek))
    zabiljezi('R2', 'Ukupan broj prikazanih recenzija = broj u podacima', g, 1)


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
         'I': grupa_I, 'R': grupa_R}

if __name__ == '__main__':
    arg = (sys.argv[1] if len(sys.argv) > 1 else 'brzo').upper()
    if arg == 'SVE':
        red = 'ABCDEFGHIR'
    elif arg == 'BRZO':
        red = 'ACDFGIR'
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
