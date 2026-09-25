#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
r3-indeksabilnost.py — za svaku adresu jedna presuda: INDEXABLE ili NE, i zasto.

Ovo je nezavisna provjera. Ne oslanja se ni na jedan raniji izvjestaj i ne cita
lokalne fajlove osim za poredjenje LOKALNO/GITHUB/ZIVO. Sve ostalo se mjeri na
zivom sajtu.

Sta radi
--------
Za svaku adresu iz sitemap.xml:
  1. HTTP status bez pracenja + lanac preusmjerenja do kraja
  2. robots.txt — da li je adresa dozvoljena Googlebotu
  3. <meta name="robots"> u HTML-u
  4. X-Robots-Tag u zaglavlju, i kao posjetilac i kao Googlebot
  5. canonical iz HTML-a
  6. da li je adresa u sitemapu (jeste, po definiciji) i da li canonical
     pokazuje bas na nju
  7. presuda: INDEXABLE / NOT INDEXABLE + razlog

Zatim lanac koji je trazen:
     SITEMAP  →  CANONICAL  →  KONACNI 200  →  INDEXABLE
i broj X/Y potpuno uskladjenih adresa.

Uz to:
  · zaglavlja (Cache-Control, ETag, Last-Modified, Content-Type, Content-Encoding)
    za HTML, JS, CSS, JSON i sliku
  · deset najvecih slika na sajtu, mjereno onako kako ih pregledac stvarno
    dobije (Accept: image/webp)
  · title, meta opis i H1 za svih 149 — prazni, duplirani, visestruki
  · poredjenje LOKALNO / GITHUB / ZIVO za kriticne fajlove

  python3 alat/r3-indeksabilnost.py            →  R3-INDEKSABILNOST.md
  MMH_IZLAZ=/put/ime python3 alat/r3-indeksabilnost.py
"""
import concurrent.futures as fut
import hashlib
import json
import os
import re
import subprocess
import sys
from urllib.parse import urlparse

SAJT = 'https://makemyhome.me'
KOR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
IZLAZ = os.environ.get('MMH_IZLAZ', os.path.join(KOR, 'R3-INDEKSABILNOST'))
GBOT = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'
GBOT_M = ('Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 '
          '(KHTML, like Gecko) Chrome/125.0.0.0 Mobile Safari/537.36 '
          '(compatible; Googlebot/2.1; +http://www.google.com/bot.html)')
COVJEK = ('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
          '(KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36')
CURL = ['curl', '-sk', '--cacert', '/root/.ccr/ca-bundle.crt', '--max-time', '25']

GRANA = 'claude/build-product-website-6CvHG'
KRITICNI = ['js/products.js', 'product.php', 'products.php', 'robots.txt',
            'sitemap.php', 'php/slug.php', 'alat/verzije.py', 'css/style-v5.css']


def tijelo(url, ua=GBOT):
    return subprocess.run(CURL + ['-A', ua, url], capture_output=True,
                          text=True, errors='replace').stdout


def bajtovi(url, ua=GBOT, dodatno=None):
    return subprocess.run(CURL + ['-A', ua] + (dodatno or []) + [url],
                          capture_output=True).stdout


def zaglavlja(url, ua=GBOT):
    return subprocess.run(CURL + ['-I', '-A', ua, url], capture_output=True,
                          text=True, errors='replace').stdout


def lanac(url, ua=GBOT):
    """(status_bez_pracenja, [koraci], konacna_adresa, konacni_status)"""
    izl = subprocess.run(
        CURL + ['-A', ua, '-o', '/dev/null', '-w', '%{http_code}'], capture_output=True,
        text=True).stdout if False else None
    prvi = subprocess.run(CURL + ['-A', ua, '-o', '/dev/null', '-w', '%{http_code}', url],
                          capture_output=True, text=True).stdout.strip()
    puni = subprocess.run(
        CURL + ['-A', ua, '-L', '--max-redirs', '10', '-o', '/dev/null',
                '-w', '%{num_redirects}|%{url_effective}|%{http_code}', url],
        capture_output=True, text=True).stdout.strip().split('|')
    if len(puni) < 3:
        return prvi, 0, url, prvi
    return prvi, int(puni[0] or 0), puni[1], puni[2]


# ---------------------------------------------------------------- robots.txt
class Robots:
    """Minimalno tumacenje robots.txt za jednog agenta, po Googleovom pravilu:
       vazi najduze pravilo koje se poklapa; kod istog broja znakova Allow
       pobjeduje Disallow."""

    def __init__(self, tekst, agent='googlebot'):
        self.pravila = []
        vazi = False
        opsti, moje = [], []
        tekuci = None
        for red in tekst.split('\n'):
            red = red.split('#')[0].strip()
            if not red:
                continue
            if ':' not in red:
                continue
            k, v = red.split(':', 1)
            k, v = k.strip().lower(), v.strip()
            if k == 'user-agent':
                tekuci = v.lower()
            elif k in ('allow', 'disallow') and tekuci is not None:
                meta = (k == 'allow', v)
                if tekuci == '*':
                    opsti.append(meta)
                elif agent in tekuci:
                    moje.append(meta)
        self.pravila = moje or opsti

    def dozvoljeno(self, put):
        najbolje = None
        for dozvola, obrazac in self.pravila:
            if not obrazac:
                continue
            o = obrazac.replace('*', '')
            if put.startswith(o.rstrip('$')):
                duz = len(o)
                if najbolje is None or duz > najbolje[0] or (duz == najbolje[0] and dozvola):
                    najbolje = (duz, dozvola)
        return True if najbolje is None else najbolje[1]


CP = 'https://cpanel.mmhdecor.mycpanel.rs/execute/Fileman/get_file_content'
CP_KOR = '/home/mmhdecor/public_html/makemyhome.me'


def cpanel_velicina(rel):
    """Velicina fajla na serveru, procitana kroz cPanel. Vraca None ako ne uspije."""
    direk = CP_KOR + ('/' + os.path.dirname(rel) if os.path.dirname(rel) else '')
    izl = subprocess.run(
        CURL + ['-u', 'mmhdecor:fhgkwqjd0F6K', '-G', CP,
                '--data-urlencode', 'dir=' + direk,
                '--data-urlencode', 'file=' + os.path.basename(rel)],
        capture_output=True, text=True, errors='replace').stdout
    try:
        return len(json.loads(izl)['data']['content'].encode('utf-8'))
    except Exception:
        return None


def main():
    L = []
    r = L.append
    r('# R3 — nezavisna provjera indeksabilnosti i uskladjenosti')
    r('')
    r('Mjereno na zivom sajtu. Ne oslanja se ni na jedan raniji izvjestaj.')
    r('')

    # ---- sitemap ----
    sm = tijelo(SAJT + '/sitemap.xml')
    adrese = re.findall(r'<loc>([^<]+)</loc>', sm)
    r(f'Sitemap: **{len(adrese)}** adresa.')

    rb_tekst = tijelo(SAJT + '/robots.txt')
    rb = Robots(rb_tekst, 'googlebot')

    def jedna(u):
        put = urlparse(u).path
        h = tijelo(u)
        prvi, skokova, kraj, kraj_status = lanac(u)
        zag_bot = zaglavlja(u, GBOT)
        zag_covjek = zaglavlja(u, COVJEK)
        mrob = re.search(r'<meta[^>]+name=["\']robots["\'][^>]*content=["\']([^"\']*)', h, re.I)
        mcan = re.search(r'<link[^>]+rel=["\']canonical["\'][^>]*href=["\']([^"\']*)', h, re.I)
        mtit = re.search(r'<title>([^<]*)</title>', h, re.I)
        mopis = re.search(r'<meta[^>]+name=["\']description["\'][^>]*content=["\']([^"\']*)', h, re.I)
        bez_sk = re.sub(r'<script[\s\S]*?</script>', '', h, flags=re.I)
        h1 = re.findall(r'<h1[\s>]', bez_sk, re.I)
        xrob_bot = re.search(r'(?i)x-robots-tag:\s*([^\r\n]+)', zag_bot)
        xrob_c = re.search(r'(?i)x-robots-tag:\s*([^\r\n]+)', zag_covjek)
        d = {
            'url': u, 'put': put, 'status': prvi, 'skokova': skokova,
            'kraj': kraj, 'kraj_status': kraj_status,
            'robots_txt_dozvoljeno': rb.dozvoljeno(put),
            'meta_robots': (mrob.group(1).strip() if mrob else None),
            'x_robots_bot': (xrob_bot.group(1).strip() if xrob_bot else None),
            'x_robots_covjek': (xrob_c.group(1).strip() if xrob_c else None),
            'canonical': (mcan.group(1).strip() if mcan else None),
            'title': (mtit.group(1).strip() if mtit else None),
            'opis': (mopis.group(1).strip() if mopis else None),
            'h1': len(h1),
        }
        razlozi = []
        if d['status'] != '200':
            razlozi.append('HTTP %s' % d['status'])
        if d['skokova']:
            razlozi.append('%d preusmjerenja → %s' % (d['skokova'], d['kraj']))
        if not d['robots_txt_dozvoljeno']:
            razlozi.append('robots.txt zabranjuje')
        if d['meta_robots'] and 'noindex' in d['meta_robots'].lower():
            razlozi.append('meta robots: %s' % d['meta_robots'])
        for ime, v in (('X-Robots-Tag (Googlebot)', d['x_robots_bot']),
                       ('X-Robots-Tag', d['x_robots_covjek'])):
            if v and 'noindex' in v.lower():
                razlozi.append('%s: %s' % (ime, v))
        if not d['canonical']:
            razlozi.append('nema canonical')
        elif d['canonical'] != u:
            razlozi.append('canonical → %s' % d['canonical'])
        d['indexable'] = not razlozi
        d['razlozi'] = razlozi
        return d

    with fut.ThreadPoolExecutor(max_workers=10) as ex:
        redovi = list(ex.map(jedna, adrese))

    ind = [x for x in redovi if x['indexable']]
    nije = [x for x in redovi if not x['indexable']]

    # ---------------------------------------------------- 1. indeksabilnost
    r('')
    r('## 1. Indeksabilnost — presuda po adresi')
    r('')
    r(f'- **INDEXABLE: {len(ind)}/{len(redovi)}**')
    r(f'- NOT INDEXABLE: {len(nije)}/{len(redovi)}')
    r('')
    if nije:
        r('| adresa | razlog |')
        r('|---|---|')
        for x in nije:
            r(f"| `{x['put']}` | {'; '.join(x['razlozi'])} |")
    else:
        r('[i] Svaka adresa iz sitemapa je indeksabilna: 200 bez skoka, dozvoljena u')
        r('robots.txt, bez noindex u HTML-u i u zaglavlju, sa canonical-om na samu sebe.')

    # ------------------------------------- 2. sitemap→canonical→200→indexable
    r('')
    r('## 2. Lanac: SITEMAP → CANONICAL → HTTP 200 → INDEXABLE')
    r('')
    uskladjeni = [x for x in redovi
                  if x['status'] == '200' and x['skokova'] == 0
                  and x['canonical'] == x['url'] and x['indexable']]
    r(f'**{len(uskladjeni)}/{len(redovi)}** adresa je potpuno uskladjeno.')
    r('')
    lose = [x for x in redovi if x not in uskladjeni]
    if lose:
        r('| adresa | status | skokova | canonical | indexable |')
        r('|---|---|---|---|---|')
        for x in lose:
            r(f"| `{x['put']}` | {x['status']} | {x['skokova']} | {x['canonical']} | "
              f"{'da' if x['indexable'] else 'NE'} |")
    else:
        r('[i] Nijedna adresa iz sitemapa ne zavrsava preusmjerenjem, ne pokazuje')
        r('canonical na drugu stranicu, nema noindex i nije zabranjena u robots.txt.')

    # ---------------------------------------------------- 3. preusmjerenja
    r('')
    r('## 3. Preusmjerenja')
    r('')
    probe = [('http://makemyhome.me/', 'http → https'),
             ('http://www.makemyhome.me/', 'http + www'),
             ('https://www.makemyhome.me/', 'www → non-www'),
             (SAJT + '/index.html', 'index.html → /'),
             (SAJT + '/paneli/3d-letvica-obsidian/', 'kosa crta na kraju'),
             (SAJT + '/Paneli/3d-letvica-obsidian', 'velika slova'),
             (SAJT + '/product.html?id=69', 'stari ?id='),
             (SAJT + '/products.html?category=mdf', 'stari ?category='),
             (SAJT + '/shop/', 'stari WordPress /shop/'),
             (SAJT + '/product/bambus-panel/', 'stari WordPress /product/')]
    r('| proba | prvi status | skokova | zavrsava na | konacni status |')
    r('|---|---|---|---|---|')
    petlje = 0
    for u, opis in probe:
        prvi, sk, kraj, ks = lanac(u)
        if sk >= 5:
            petlje += 1
        r(f'| {opis} | {prvi} | {sk} | `{kraj.replace(SAJT, "") or "/"}` | {ks} |')
    r('')
    r(f'[{"!!" if petlje else "i"}] petlji u preusmjerenjima: {petlje}')

    # ---------------------------------------------------- 5. robots.txt
    r('')
    r('## 5. robots.txt')
    r('')
    r('```')
    r(rb_tekst.strip())
    r('```')
    r('')
    r('| putanja | Googlebot | Bingbot | ostali |')
    r('|---|---|---|---|')
    rb_bing = Robots(rb_tekst, 'bingbot')
    rb_svi = Robots(rb_tekst, '*')
    for p in ['/paneli/3d-letvica-obsidian', '/kategorija/mdf', '/products.html',
              '/js/products.js', '/css/style-v5.css', '/images/products/cq006.jpg',
              '/fa/css/mmh-ikone.css', '/data/products.json', '/admin/', '/php/slug.php']:
        r(f'| `{p}` | {"da" if rb.dozvoljeno(p) else "**NE**"} '
          f'| {"da" if rb_bing.dozvoljeno(p) else "**NE**"} '
          f'| {"da" if rb_svi.dozvoljeno(p) else "**NE**"} |')
    r('')
    ima_sitemap = 'sitemap:' in rb_tekst.lower()
    r(f'[{"i" if ima_sitemap else "!!"}] sitemap prijavljen u robots.txt: '
      f'{"da" if ima_sitemap else "NE"}')

    # ---------------------------------------------------- 6. pokrivenost
    r('')
    r('## 6. Pokrivenost sitemapa')
    r('')
    pj = json.loads(tijelo(SAJT + '/data/products.json'))
    proizvodi = pj if isinstance(pj, list) else pj.get('products', pj)
    kj = json.loads(tijelo(SAJT + '/data/categories.json'))
    kat_id = set()
    for c in kj:
        kat_id.add(c.get('id'))
        for s in (c.get('subcategories') or []):
            kat_id.add(s.get('id'))
    sm_prod = [u for u in adrese if '/paneli/' in u]
    sm_kat = [u for u in adrese if '/kategorija/' in u]
    sm_ost = [u for u in adrese if '/paneli/' not in u and '/kategorija/' not in u]
    r('| vrsta | postoji | u sitemapu | fali |')
    r('|---|---|---|---|')
    r(f'| proizvodi | {len(proizvodi)} | {len(sm_prod)} | {len(proizvodi) - len(sm_prod)} |')
    r(f'| kategorije | {len(kat_id)} | {len(sm_kat)} | {len(kat_id) - len(sm_kat)} |')
    r(f'| ostale stranice | – | {len(sm_ost)} | – |')
    r(f'| **ukupno** | – | **{len(adrese)}** | – |')

    # ------------------------------------------------- 16. zaglavlja
    r('')
    r('## 16. Zaglavlja odgovora (zivi sajt)')
    r('')
    r('| sredstvo | Content-Type | Cache-Control | ETag | Last-Modified | Content-Encoding |')
    r('|---|---|---|---|---|---|')
    for u in [SAJT + '/', SAJT + '/kategorija/mdf', SAJT + '/paneli/3d-letvica-obsidian',
              SAJT + '/js/products.js', SAJT + '/css/style-v5.css',
              SAJT + '/data/products.json', SAJT + '/images/products/cq006.jpg',
              SAJT + '/sitemap.xml', SAJT + '/robots.txt']:
        z = subprocess.run(CURL + ['-I', '-A', GBOT, '-H', 'Accept-Encoding: gzip, br', u],
                           capture_output=True, text=True, errors='replace').stdout
        def polje(ime):
            m = re.search(r'(?i)^%s:\s*([^\r\n]+)' % ime, z, re.M)
            return (m.group(1).strip()[:38] if m else '–')
        r(f'| `{u.replace(SAJT, "") or "/"}` | {polje("content-type")} | {polje("cache-control")} '
          f'| {"da" if polje("etag") != "–" else "–"} | {"da" if polje("last-modified") != "–" else "–"} '
          f'| {polje("content-encoding")} |')

    # ------------------------------------------------- 18. meta podaci
    r('')
    r('## 18. Title, opis, H1 na svih %d' % len(redovi))
    r('')
    prazni_t = [x for x in redovi if not x['title']]
    prazni_o = [x for x in redovi if not x['opis']]
    bez_h1 = [x for x in redovi if x['h1'] == 0]
    vise_h1 = [x for x in redovi if x['h1'] > 1]
    from collections import Counter
    dup_t = [t for t, n in Counter(x['title'] for x in redovi if x['title']).items() if n > 1]
    dup_o = [o for o, n in Counter(x['opis'] for x in redovi if x['opis']).items() if n > 1]
    r('| provjera | rezultat |')
    r('|---|---|')
    r(f'| bez title | {len(prazni_t)} |')
    r(f'| duplirani title | {len(dup_t)} |')
    r(f'| bez meta opisa | {len(prazni_o)} |')
    r(f'| duplirani meta opis | {len(dup_o)} |')
    r(f'| bez H1 | {len(bez_h1)} |')
    r(f'| vise od jednog H1 | {len(vise_h1)} |')
    for ime, spisak in (('duplirani title', dup_t), ('duplirani opis', dup_o)):
        for v in spisak[:5]:
            gdje = [x['put'] for x in redovi if (x['title'] if ime.endswith('title') else x['opis']) == v]
            r(f'')
            r(f'**{ime}:** `{v[:70]}` → {", ".join(gdje[:4])}')

    # ------------------------------------------------- 19. najvece slike
    r('')
    r('## 19. Deset najvecih slika (onako kako ih pregledac dobije)')
    r('')
    slike = set()
    for x in redovi:
        h = tijelo(x['url'])
        for m in re.finditer(r'(?:src|href)="([^"]*images/[^"]+\.(?:jpg|jpeg|png|webp))"', h):
            s = m.group(1)
            slike.add(s if s.startswith('http') else SAJT + '/' + s.lstrip('/'))
    def velicina(u):
        izl = subprocess.run(CURL + ['-A', COVJEK, '-H', 'Accept: image/webp,image/*',
                                     '-o', '/dev/null', '-w', '%{size_download}|%{content_type}', u],
                             capture_output=True, text=True).stdout.split('|')
        return (int(izl[0] or 0), izl[1] if len(izl) > 1 else '')
    with fut.ThreadPoolExecutor(max_workers=10) as ex:
        mjere = list(ex.map(velicina, slike))
    poredak = sorted(zip(slike, mjere), key=lambda x: -x[1][0])[:10]
    r(f'Razlicitih slika na sajtu: **{len(slike)}**')
    r('')
    r('| slika | kB (sa Accept: webp) | tip |')
    r('|---|---|---|')
    for u, (v, t) in poredak:
        r(f'| `{u.replace(SAJT + "/", "")}` | {round(v / 1024)} | {t} |')

    # ------------------------------------------------- 15. lokalno/github/zivo
    r('')
    r('## 15. LOKALNO / GITHUB / ZIVO — kriticni fajlovi')
    r('')
    r('Fajl koji se servira takav kakav jeste (js, css, txt) poredi se po hasu')
    r('sadrzaja. PHP se preko weba ne moze skinuti u izvornom obliku, pa se cita')
    r('sa servera kroz cPanel i poredi po VELICINI — cPanel sadrzaj normalizuje')
    r('(spoji neke redove), pa hash ne bi bio uporediv, a velicina dolazi sa diska.')
    r('Alati u `alat/` se ne deployuju, za njih vazi samo LOKALNO = GITHUB.')
    r('')
    r('| fajl | lokalno | GitHub | zivo | poklapa se |')
    r('|---|---|---|---|---|')
    for rel in KRITICNI:
        put = os.path.join(KOR, rel)
        lok_b = open(put, 'rb').read() if os.path.exists(put) else b''
        lok = hashlib.sha256(lok_b).hexdigest()[:8] if lok_b else '–'
        gh = subprocess.run(['git', 'show', f'origin/{GRANA}:{rel}'], cwd=KOR,
                            capture_output=True)
        ghh = hashlib.sha256(gh.stdout).hexdigest()[:8] if gh.returncode == 0 else '–'
        if rel.startswith('alat/'):
            ziv, ok = '(ne deployuje se)', ('da' if lok == ghh else '**NE**')
        elif rel.endswith('.php'):
            v = cpanel_velicina(rel)
            ziv = f'{v} B' if v else '–'
            ok = 'da' if (lok == ghh and v == len(lok_b)) else '**NE**'
        else:
            bb = bajtovi(f'{SAJT}/{rel}', COVJEK)
            ziv = hashlib.sha256(bb).hexdigest()[:8] if bb else '–'
            ok = 'da' if lok == ghh == ziv else '**NE**'
        r(f'| `{rel}` | {lok}{"" if not rel.endswith(".php") else f" ({len(lok_b)} B)"} '
          f'| {ghh} | {ziv} | {ok} |')

    # ------------------------------------- 12/13. Googlebot vs covjek vs mobilni
    r('')
    r('## 12 i 13. Googlebot naspram obicnog korisnika naspram mobilnog')
    r('')
    r('Isti zahtjev, tri user-agenta. Ako se sadrzaj razlikuje, to je cloaking —')
    r('bilo namjeran ili slucajan, Google ga tretira isto.')
    r('')
    uzorak = ([SAJT + '/'] + [u for u in adrese if '/kategorija/' in u][:2]
              + [u for u in adrese if '/paneli/' in u][:10])

    def otisak(h):
        bez = re.sub(r'<script[\s\S]*?</script>', '', h, flags=re.I)
        mb = re.search(r'<base\b[^>]+href="([^"]+)"', h, re.I)
        return {
            'title': (re.search(r'<title>([^<]*)</title>', h, re.I) or [None, None])[1]
                     if re.search(r'<title>([^<]*)</title>', h, re.I) else None,
            'canonical': (re.search(r'rel=["\']canonical["\'][^>]*href=["\']([^"\']*)', h, re.I)
                          or [None, None])[1]
                         if re.search(r'rel=["\']canonical["\'][^>]*href=["\']([^"\']*)', h, re.I) else None,
            'robots': (re.search(r'name=["\']robots["\'][^>]*content=["\']([^"\']*)', h, re.I)
                       or [None, None])[1]
                      if re.search(r'name=["\']robots["\'][^>]*content=["\']([^"\']*)', h, re.I) else None,
            'h1': len(re.findall(r'<h1[\s>]', bez, re.I)),
            'ld': len(re.findall(r'application/ld\+json', h)),
            'product': ('"@type": "Product"' in h or '"@type":"Product"' in h),
            'cijene': len(set(re.findall(r'[0-9]+,[0-9]{2}\s*€', bez))),
            'slike': len(re.findall(r'<img[\s>]', bez, re.I)),
            'linkovi': len(set(re.findall(r'<a\b[^>]+href="([^"#]+)"', bez))),
            'duzina': len(bez),
        }

    razlike_ua = []
    r('| adresa | title | canonical | robots | h1 | LD | Product | cijene | slike | linkovi | isto? |')
    r('|---|---|---|---|---|---|---|---|---|---|---|')
    for u in uzorak:
        o = {ime: otisak(tijelo(u, ua))
             for ime, ua in (('bot', GBOT), ('covjek', COVJEK), ('mobilni', GBOT_M))}
        polja = ['title', 'canonical', 'robots', 'h1', 'ld', 'product', 'cijene', 'slike', 'linkovi']
        neslaganje = [p for p in polja if len({str(o[k][p]) for k in o}) > 1]
        # Duzina se poredi sa dopustanjem od 1% — dinamicki datum u schemi i
        # slicno smiju napraviti nekoliko bajtova razlike.
        duz = [o[k]['duzina'] for k in o]
        if max(duz) - min(duz) > max(duz) * 0.01:
            neslaganje.append('duzina %s' % duz)
        if neslaganje:
            razlike_ua.append((u, neslaganje))
        b_ = o['bot']
        r(f"| `{u.replace(SAJT, '') or '/'}` | {'da' if b_['title'] else 'NE'} "
          f"| {'da' if b_['canonical'] else 'NE'} | {b_['robots'] or '–'} | {b_['h1']} "
          f"| {b_['ld']} | {'da' if b_['product'] else '–'} | {b_['cijene']} | {b_['slike']} "
          f"| {b_['linkovi']} | {'**NE**' if neslaganje else 'da'} |")
    r('')
    if razlike_ua:
        r(f'[!!] **{len(razlike_ua)}** stranica daje razlicit sadrzaj po user-agentu:')
        for u, n in razlike_ua:
            r(f"- `{u.replace(SAJT, '')}` → {', '.join(str(x) for x in n)}")
    else:
        r(f'[i] Svih {len(uzorak)} stranica daje **identican** sadrzaj Googlebotu, obicnom')
        r('pregledacu i mobilnom Googlebotu — u naslovu, canonical-u, robots-u, broju H1,')
        r('JSON-LD blokova, cijena, slika i linkova, i u duzini HTML-a.')

    # ---------------------------------------------------- 17. zbirna tabela
    r('')
    r('## 17. Zbirna tabela signala')
    r('')
    r('| adresa | u sitemapu | canonical = adresa | HTTP | robots.txt | noindex | H1 | konacno |')
    r('|---|---|---|---|---|---|---|---|')
    for x in redovi:
        noindex = 'da' if ((x['meta_robots'] and 'noindex' in x['meta_robots'].lower())
                           or (x['x_robots_bot'] and 'noindex' in x['x_robots_bot'].lower())) else 'ne'
        r(f"| `{x['put']}` | da | {'da' if x['canonical'] == x['url'] else '**NE**'} "
          f"| {x['status']} | {'dozvoljeno' if x['robots_txt_dozvoljeno'] else '**ZABRANJENO**'} "
          f"| {noindex} | {x['h1']} | {'INDEXABLE' if x['indexable'] else '**NOT INDEXABLE**'} |")

    with open(IZLAZ + '.md', 'w', encoding='utf-8') as fh:
        fh.write('\n'.join(L) + '\n')
    with open(IZLAZ + '.json', 'w', encoding='utf-8') as fh:
        json.dump(redovi, fh, ensure_ascii=False, indent=1)
    print(f'Gotovo → {IZLAZ}.md  (indexable {len(ind)}/{len(redovi)}, '
          f'uskladjeno {len(uskladjeni)}/{len(redovi)})')
    return 0 if (len(ind) == len(redovi) and len(uskladjeni) == len(redovi)) else 1


if __name__ == '__main__':
    sys.exit(main())
