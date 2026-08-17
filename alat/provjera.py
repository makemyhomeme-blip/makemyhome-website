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
import json, re, subprocess, sys, os, collections, hashlib, time, datetime
import xml.etree.ElementTree as ET
from urllib.parse import urljoin, urlparse
from html.parser import HTMLParser

BAZA = 'https://makemyhome.me'
KORIJEN = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CURL = ['curl', '-sk', '--cacert', '/root/.ccr/ca-bundle.crt']
GOOGLEBOT = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'
rezultati = []

# Spora pravila (ona koja povlace stotine adresa) rade se samo uz 'sve'.
SPORO = len(sys.argv) > 1 and sys.argv[1].upper() in ('SVE', 'S', 'E')


# Odgovori koji NE znace gresku na sajtu nego trenutni ispad.
#
# 000 = veza nije ni uspostavljena. 429/500/502/503/504 = server je za taj
# jedan zahtjev odustao. Provjera povuce preko 400 adresa u nizu i deelnog
# shared hostinga to zna nakratko da obori — pa bi ispravna stranica bila
# prijavljena kao pokvarena. Desilo se: E2 je jednom javio gresku, a na
# ponovnom pokretanju 397 adresa 0 gresaka.
#
# Pravilo: kvar je kvar samo ako se ponovi. Pokusava se tri puta, sa pauzom
# koja raste, da server stigne da se oporavi. Prava greska (404 na obrisanu
# stranicu, 500 zbog greske u kodu) preziva sva tri pokusaja i bice prijavljena.
PROLAZNO = {'000', '429', '500', '502', '503', '504'}


def dohvati(u, prati=False, timeout='20'):
    cmd = CURL + ['--max-time', timeout, '-w', '\n@@%{http_code}|%{num_redirects}|%{url_effective}']
    if prati:
        cmd += ['-L', '--max-redirs', '5']
    zadnji = ('', '000', '0', '')
    for pokusaj in range(3):
        r = subprocess.run(cmd + [u], capture_output=True, text=True, errors='replace').stdout
        i = r.rfind('\n@@')
        if i < 0:
            time.sleep(1.5 * (pokusaj + 1))
            continue
        kod, sk, kraj = r[i + 3:].split('|', 2)
        zadnji = (r[:i], kod, sk, kraj.strip())
        if kod not in PROLAZNO:
            return zadnji
        time.sleep(1.5 * (pokusaj + 1))
    return zadnji


def zabiljezi(sifra, opis, greske, provjereno):
    rezultati.append((sifra, opis, list(greske), provjereno))
    znak = 'OK ' if not greske else 'PAD'
    print('%s %-5s %-52s (%d provjereno, %d gresaka)' % (znak, sifra, opis[:52], provjereno, len(greske)))
    for g in greske[:6]:
        print('        · %s' % str(g)[:112])
    if len(greske) > 6:
        print('        · …i jos %d' % (len(greske) - 6))


def bajtovi(u, timeout='20'):
    """Skini fajl kao BAJTOVE. dohvati() vraca tekst, pa se binarni fajl (woff2)
    pri dekodiranju izmijeni i hash mu ispadne drugaciji — pravilo G24 je zbog
    toga prijavilo da se font na serveru razlikuje, a bio je bajt po bajt isti."""
    r = subprocess.run(CURL + ['--max-time', timeout, u], capture_output=True)
    return r.stdout if r.returncode == 0 else b''


def urlparse_put(u):
    """Samo putanja iz adrese: https://makemyhome.me/paneli/x -> /paneli/x"""
    return re.sub(r'^https?://[^/]+', '', u)


def mmhCvorovi(d):
    """Svi JSON-LD cvorovi, ukljucujuci one unutar @graph.

    Bez ovoga se @graph preskace u tisini. Kad je schema na kategorijama
    prebacena u @graph — da se politika povrata i uslovi dostave definisu
    jednom pa pozivaju oznakom — pravila D2, D3 i D4 bi prestala da vide
    proizvode na tih 14 stranica, i javljala bi da je sve u redu jer nemaju
    sta da provjere. Provjera koja nema sta da gleda uvijek prolazi.
    """
    for o in (d if isinstance(d, list) else [d]):
        if not isinstance(o, dict):
            continue
        if '@graph' in o:
            for g in (o['@graph'] if isinstance(o['@graph'], list) else [o['@graph']]):
                if isinstance(g, dict):
                    yield g
        else:
            yield o


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

    # ---- Ikona mora imati i GLIF u fontu, ne samo pravilo u CSS-u ----------
    #
    # C6 gleda samo CSS i zato je propustio pravu gresku. Fontovi su sazeti na
    # ikone koje su se koristile u tom trenutku (fa-solid-900.woff2: 156 kB ->
    # 12 kB). Kad je na Decor Box dodato sest ikona, CSS je regenerisan i C6 je
    # rekao da je sve u redu — ali font te glifove nije imao i cetiri ikone su
    # se iscrtale kao prazan prostor. Vidjelo se samo okom, na slici stranice.
    #
    # Ovdje se cita pravi font sa sajta i provjerava da za svaku koriscenu
    # ikonu postoji znak u njemu. Lijek je `python3 alat/fontovi.py upisi`.
    g = []
    provjereno_gl = 0
    try:
        from fontTools.ttLib import TTFont
        import io
        # ime ikone -> kod znaka, iz punog Font Awesome CSS-a
        puni = open(os.path.join(KORIJEN, 'fa/css/all.min.css'), encoding='utf-8').read()
        kodovi = {}
        for sel, kod in re.findall(r'([^{}]+)\{content:"\\([0-9a-f]+)"\}', puni):
            for ime in re.findall(r'\.fa-([a-z0-9-]+):+before', sel):
                kodovi[ime] = int(kod, 16)

        znakovi = set()
        for rel in ('fa/webfonts/fa-solid-900.woff2', 'fa/webfonts/fa-brands-400.woff2'):
            r = subprocess.run(CURL + ['--max-time', '25', '%s/%s' % (BAZA, rel)],
                               capture_output=True).stdout
            if len(r) < 500:
                g.append('%s se ne moze skinuti sa sajta (%d B)' % (rel, len(r)))
                continue
            znakovi |= set(TTFont(io.BytesIO(r)).getBestCmap())

        if znakovi:
            for ime in sorted(ikone):
                if ime not in kodovi:
                    continue
                provjereno_gl += 1
                if kodovi[ime] not in znakovi:
                    g.append('fa-%s ima pravilo u CSS-u ali font nema taj znak — iscrtace se prazno '
                             '(pokreni: python3 alat/fontovi.py upisi)' % ime)
    except Exception as e:
        g.append('provjera glifova nije mogla da se izvrsi: %s' % e)
    zabiljezi('C9', 'Svaka ikona ima i stvarni znak u fontu', g, provjereno_gl)

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

    # ---- Kod ne smije iscuriti u vidljivi tekst ---------------------------
    #
    # Na Decor Boxu je ispod fotografije fabrike stajalo golo `">`. Uzrok:
    # mmhDimAtributi() je bio ubacen USRED onerror="..." teksta, a ispisuje
    # prave navodnike — pa je zatvorio atribut prije kraja i ostatak markupa
    # je zavrsio na stranici kao tekst koji posjetilac vidi.
    #
    # Nijedno dotadasnje pravilo to nije moglo uhvatiti: stranica je vracala
    # 200, HTML se ucitavao, slika se prikazivala, alt je bio tu. Greska se
    # vidjela samo okom. Zato se sada gleda ono sto posjetilac stvarno cita:
    # ako se u tekstu nadje komad koda, nesto je puklo u sastavljanju.
    # Ovdje se NE smije koristiti tekst_bez_skripti(): ono cisti tagove regexom
    # <[^>]+>, koji stane na prvom `>` — a `>` sasvim legitimno stoji unutar
    # navodnika u onerror="…&quot;></i>…". Regex tu presijece tag na pola i
    # ostatak proglasi tekstom, pa je pravilo u prvom pokusaju prijavilo 22
    # ispravne stranice. Zato ovdje ide pravi parser, koji navodnike postuje.
    class _Tekst(HTMLParser):
        def __init__(self):
            super().__init__(convert_charrefs=True)
            self.dijelovi, self.preskoci = [], 0

        def handle_starttag(self, tag, attrs):
            if tag in ('script', 'style'):
                self.preskoci += 1

        def handle_endtag(self, tag):
            if tag in ('script', 'style') and self.preskoci:
                self.preskoci -= 1

        def handle_data(self, d):
            if not self.preskoci:
                self.dijelovi.append(d)

    OSTACI = ['">', "'>", '/>', '<div', '<img', '<span', '<i ', '<?php', '<?=',
              'onerror=', 'srcset=', 'loading="lazy"']
    g = []
    for u, (h, kod, _, _) in STRANICE.items():
        if kod != '200':
            continue
        p = _Tekst()
        try:
            p.feed(h)
        except Exception as e:
            g.append('%s → HTML se ne moze isparsirati: %s' % (u, e))
            continue
        vidljivo = ' '.join(''.join(p.dijelovi).split())
        nadjeno = sorted({o for o in OSTACI if o in vidljivo})
        if nadjeno:
            i = vidljivo.find(nadjeno[0])
            g.append('%s → u vidljivom tekstu stoji %s  …%s…'
                     % (u, ', '.join(nadjeno), vidljivo[max(0, i - 45):i + 30]))
    zabiljezi('C8', 'Nijedan komad koda nije iscurio u vidljivi tekst', g, len(STRANICE))


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
            for o in mmhCvorovi(d):
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
    # D4 nije opreznost nego pravilo koje se NE SMIJE ukloniti.
    #
    # Search Console javlja "Missing field aggregateRating (optional)" na
    # stranicama proizvoda. To NIJE greska — GSC uz to pise "valid items
    # detected, eligible for rich results", a polje je oznaceno kao neobavezno.
    # Zvuci kao da fali nesto sto treba dodati, i lako se doda.
    #
    # Ne smije se dodati: vlasnik je potvrdio da recenzije na sajtu nisu prave.
    # Slanje izmisljenih ocjena Googleu je krsenje pravila o strukturiranim
    # podacima i povlaci rucnu sankciju, koja obara cijeli sajt u rezultatima —
    # dakle tacno suprotno od onoga zbog cega se ovo i radi.
    #
    # Ako recenzije jednom postanu prave (kupci ih sami ostave), tek tada se
    # smiju slati, i tek tada se ovo pravilo mijenja.
    zabiljezi('D4', 'Nigdje se ne salju ocjene Google-u', g4, len(SITEMAP))
    zabiljezi('D5', 'Breadcrumb pozicije idu 1,2,3…', g5, len(SITEMAP))

    # ---- Firma mora biti JEDNA, ne dvije ----------------------------------
    #
    # Grupa D je do sada provjeravala samo proizvode. Podatke o firmi nije
    # gledao niko — a bas njih Google cita kad neko ukuca ime firme i kad
    # odlucuje da li sajt i profil na Mapama pripadaju istom subjektu.
    #
    # Naslo se ovako: pocetna je opisivala firmu kao HomeGoodsStore /
    # LocalBusiness / Organization sa @id "…/#organization", a contact.html
    # kao LocalBusiness BEZ ijednog @id-a i bez addressRegion. Dva cvora bez
    # zajednickog @id-a Google moze citati kao dvije razlicite firme, pa se
    # snaga jedne dijeli na dvije — a firma se pet mjeseci nije nalazila ni
    # po svom punom imenu.
    g = []
    cvorovi = []
    for u, (h, kod, _, _) in STRANICE.items():
        if kod != '200':
            continue
        for blok in re.findall(r'<script type="application/ld\+json">(.*?)</script>', h, re.S):
            try:
                d = json.loads(blok)
            except Exception:
                continue                      # D1 to vec prijavljuje
            for n in mmhCvorovi(d):
                t = n.get('@type')
                tt = t if isinstance(t, list) else [t]
                if not any(x in ('Organization', 'LocalBusiness', 'HomeGoodsStore', 'Store') for x in tt):
                    continue
                cvorovi.append((u, n))
                if not n.get('@id'):
                    g.append('%s → podaci o firmi bez @id — Google to moze citati '
                             'kao drugu firmu' % u)
    # Sve sto ima @id mora imati ISTE podatke: ime, telefon, adresu
    poId = collections.defaultdict(list)
    for u, n in cvorovi:
        if n.get('@id'):
            poId[n['@id']].append((u, n))
    if len(poId) > 1:
        g.append('firma je opisana pod %d razlicitih @id: %s'
                 % (len(poId), ', '.join(sorted(poId))))
    for oid, spisak in poId.items():
        osnovni = None
        for u, n in spisak:
            kljuc = (n.get('name'),
                     re.sub(r'\D', '', str(n.get('telephone') or ''))[-8:],
                     (n.get('address') or {}).get('streetAddress'))
            if osnovni is None:
                osnovni = (u, kljuc)
            elif kljuc != osnovni[1]:
                g.append('%s opisuje firmu drugacije nego %s: %s vs %s'
                         % (u, osnovni[0], kljuc, osnovni[1]))
    zabiljezi('D6', 'Firma je svuda opisana kao JEDNA te ista', g, len(cvorovi))


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
              'data/upload_debug.log', 'admin/listall.php', 'admin/listgallery.php',
              # Recenzije su uklonjene sa sajta; ova dva fajla nista ne cita, a
              # nosila su 585 izmisljenih recenzija javno citljivih na adresi.
              # Sklonjeni su sa servera — ako se ikad vrate, ovo pravilo pada.
              'data/reviews.json', 'data/reviews-extra.json']:
        _, kod, _, _ = dohvati('%s/%s' % (BAZA, f), timeout='10')
        if kod not in ('403', '404', '410'):
            g.append('%s → %s (podaci kupaca / mrtvi fajl, mora biti zatvoren)' % (f, kod))
    for f in ['data/products.json', 'data/categories.json']:
        _, kod, _, _ = dohvati('%s/%s' % (BAZA, f), timeout='10')
        if kod != '200':
            g.append('%s → %s (sajtu treba, ne smije biti zatvoren)' % (f, kod))
    zabiljezi('G6', 'Podaci kupaca zatvoreni, podaci sajta otvoreni', g, 11)

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
                  'gallery-specs',
                  # Kutija za rezultat kalkulatora: server je ispisuje praznu i
                  # rezervise joj visinu, a broj u nju upise JavaScript kad se
                  # promijene mjere. Nije sadrzaj koji Google treba.
                  'calc-result'}
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
            # Prazno znaci PRAZNO, ne "krace od 40 znakova".
            #
            # Ranije je ovdje stajao prag duzine, kao gruba zamjena za pravu
            # provjeru. Cim je server poceo da ispisuje kratke ali stvarne
            # blokove — recimo "1 kom = 3,42 m²" u oznaci pokrivenosti — pravilo
            # ih je prijavilo kao prazne na 109 stranica. Nijedan nije bio
            # prazan. Prag je bio pretpostavka, a pretpostavka u pravilu daje
            # lazne nalaze i tjera da se pravima prestane vjerovati.
            unut = re.sub(r'<!--.*?-->', '', m.group(3), flags=re.S)
            # Prazno = nema ni teksta ni ijednog elementa. Blok sa poljem za
            # pretragu nema teksta, ali nije prazan — prva verzija ove izmjene
            # je bas njega prijavila na svakoj stranici.
            imaElement = re.search(r'<[a-z]', unut, re.I) is not None
            tekst = re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', ' ', unut)).strip()
            if not tekst and not imaElement:
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
            # Desnu kolonu proizvoda (sifra, cijena, KALKULATOR, dugmad) i
            # harmoniku ispod slike ispisuje product.php. Ranije ih je JS u
            # cjelini prepisivao, tek posto skine data/products.json — pa je
            # kupac pri osvjezavanju vidio prvo jedan raspored pa drugi, a
            # kalkulator se pojavljivao zadnji. Na svakoj stranici proizvoda.
            ('renderProductDetail',  "info.dataset.ssr"),
            ('renderProductDetail',  "gallerySpecs.dataset.ssr"),
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
    zabiljezi('G11', 'JavaScript ne prepisuje ono sto je server ispisao', g, len(ograde))

    # ---- Googlebot mora dobiti ISTO sto i posjetilac ----------------------
    #
    # Dijeljeni hosting i zastitni dodaci znaju tiho blokirati ili usporavati
    # botove — po imenu pregledaca. Kad se to desi, sve ostale provjere i dalje
    # prolaze jer mi dolazimo kao obican posjetilac, a Google ne vidi nista.
    # Kvar koji bi objasnio mjesece nevidljivosti, a nijedno pravilo ga nije
    # moglo primijetiti.
    #
    # Poredi se i velicina odgovora, ne samo kod: podmetanje drugog sadrzaja
    # botu vratilo bi 200 kao i nama.
    GBOT = ('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')
    GBOT_MOB = ('Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 '
                '(KHTML, like Gecko) Chrome/125.0.0.0 Mobile Safari/537.36 '
                '(compatible; Googlebot/2.1; +http://www.google.com/bot.html)')
    g = []
    meta = [BAZA + '/', BAZA + '/sitemap.xml', BAZA + '/robots.txt',
            BAZA + '/kategorija/3d-letvice', BAZA + '/paneli/drveni-panel-golden-teak']
    for u in meta:
        obican = subprocess.run(CURL + ['--max-time', '25', u], capture_output=True).stdout
        for ime, ua in (('Googlebot', GBOT), ('Googlebot za telefon', GBOT_MOB)):
            bot = subprocess.run(CURL + ['--max-time', '25', '-A', ua, u], capture_output=True).stdout
            # Bez praga po velicini: robots.txt je 179 bajtova i prvi prag od
            # 200 ga je proglasio nedostupnim iako je stizao ispravno. Poredjenje
            # sa obicnim odgovorom je dovoljno i ne zavisi od velicine fajla.
            if not bot:
                g.append('%s → %s ne dobija nista' % (u, ime))
            elif bot != obican:
                g.append('%s → %s dobija drugaciji sadrzaj (%d B umjesto %d B)'
                         % (u, ime, len(bot), len(obican)))
    zabiljezi('G12', 'Googlebot dobija isto sto i posjetilac', g, len(meta) * 2)

    # ---- Server crta, JavaScript samo reaguje -----------------------------
    #
    # Ovo je pravilo o tome KAKO je sajt slozen, a ne o jednoj gresci.
    #
    # Ista greska se vracala vise puta: server ispise sadrzaj, pa ga JavaScript
    # — posto skine podatke — prepise svojom verzijom. Posjetilac vidi prvo
    # jedan pa drugi raspored, sadrzaj skace, a Google ponekad procita ono sto
    # ce za sekundu nestati. Desilo se na pocetnoj, na katalogu, na
    # kategorijama, na galeriji, pa na desnoj koloni proizvoda i harmonici.
    #
    # G11 provjerava sedam poimenicno nabrojanih mjesta. To ne pomaze kad neko
    # doda osmo. Ovo pravilo zato gleda SVAKO mjesto gdje JavaScript upisuje
    # sadrzaj i trazi da svako od njih bude ili na spisku onoga sto JavaScript
    # smije da posjeduje, ili zasticeno ogradom.
    #
    # Sta JavaScript SMIJE da crta sam: ono cega u HTML-u nema i ne treba da
    # ga bude — rezultat pretrage, poruka forme, uvecana slika, obavjestenje,
    # izracunata vrijednost. Sve ostalo pise server.
    NJEGOVO = {
        'res', 'resBox', 'resultsBox',      # rezultati pretrage i kalkulatora
        'msgDiv', 't', 'lb', 'btn', 'btnBack',   # poruka, obavjestenje, uvecana slika, dugmad
    }
    OGRADE = ('dataset.ssr', 'vecIspisana', '_srvGlavna', '_srvSlicice',
              'querySelector', 'querySelectorAll', 'length === 0')
    g = []
    ukupno = 0
    for fajl in ('js/products.js', 'js/main-v4.js', 'js/cart.js'):
        put = os.path.join(KORIJEN, fajl)
        if not os.path.exists(put):
            continue
        redovi = open(put, encoding='utf-8').read().splitlines()
        for i, red in enumerate(redovi):
            # Preskacu se redovi gdje "innerHTML" stoji UNUTAR teksta, a ne u
            # kodu — npr. u onerror="…this.parentElement.innerHTML='…'". To se
            # izvrsi tek kad slika ne uspije da se ucita i ne prepisuje nista
            # sto je server nacrtao. Prva verzija pravila je bas to prijavila.
            if 'onerror=' in red or 'onerror="' in red:
                continue
            m = re.search(r'(\b[A-Za-z_$][\w$]*)\.innerHTML\s*=[^=]', red)
            if not m:
                continue
            meta_ime = m.group(1)
            ukupno += 1
            if meta_ime in NJEGOVO:
                continue
            # ograda smije stajati u istom redu ili u petnaest redova iznad
            okolina = '\n'.join(redovi[max(0, i - 15): i + 1])
            if not any(o in okolina for o in OGRADE):
                g.append('%s red %d: %s.innerHTML se upisuje bez provjere da li je '
                         'server vec ispisao sadrzaj' % (fajl, i + 1, meta_ime))
    zabiljezi('G13', 'Server crta, JavaScript samo reaguje', g, ukupno)

    # ---- Zavisnost se mora deployovati PRIJE stranice koja je trazi --------
    #
    # Sync upisuje fajlove redom iz spiska. Ako stranica dodje prije fajla koji
    # trazi preko require_once, postoji prozor u kom stranica stoji na serveru
    # a fajl jos nije — i server tada vraca 500. Prozor traje dok se ostatak
    # spiska ne preuzme, jer svaki fajl ide posebnim zahtjevom sa GitHuba.
    #
    # Nije teorija. U dnevniku servera stoji: 4. avgusta fatalna greska zbog
    # inc/slug-match.php, 11. avgusta zbog php/dimenzije.php, 14. avgusta zbog
    # php/kalkulator.php. Svaki put kad je dodat nov zavisni fajl. Google sajtu
    # koji vraca 500 smanjuje citanje i povjerenje, a to se ne vidi ni na jednoj
    # stranici — vidi se samo u dnevniku, koji niko nije gledao sest mjeseci.
    g = []
    parova = 0
    try:
        izl = subprocess.run(['php', '-r',
            '$f = require "%s"; foreach ($f("", "", "admin") as $lok => $_) echo $lok . "\n";'
            % os.path.join(KORIJEN, 'admin', 'sync-lista.php')],
            capture_output=True, text=True).stdout
        redom = [x.strip().lstrip('/') for x in izl.splitlines() if x.strip()]
        mjesto = {f: i for i, f in enumerate(redom)}
        for f in redom:
            if not f.endswith('.php'):
                continue
            put = os.path.join(KORIJEN, f)
            if not os.path.exists(put):
                continue
            for zav in re.findall(r"require(?:_once)?\s+__DIR__\s*\.\s*'/([^']+)'",
                                  open(put, encoding='utf-8').read()):
                if zav not in mjesto:
                    continue                      # G5 prijavljuje ono cega nema u spisku
                parova += 1
                if mjesto[zav] > mjesto[f]:
                    g.append('%s se deployuje prije %s koji trazi — prozor u kom '
                             'server vraca 500' % (f, zav))
    except Exception as e:
        g.append('provjera redosljeda nije mogla da se izvrsi: %s' % e)
    zabiljezi('G14', 'Zavisnost se deployuje prije stranice koja je trazi', g, parova)

    # ---- Zabrana indeksiranja se moze poslati i ZAGLAVLJEM ----------------
    #
    # X-Robots-Tag: noindex u odgovoru servera radi isto sto i meta oznaka u
    # HTML-u, ali se u izvornom kodu stranice NE VIDI. Jedan red u .htaccess
    # ili u podesavanju hostinga moze tiho iskljuciti cio sajt iz Googla, a
    # svaka druga provjera bi i dalje prolazila: stranica vraca 200, sadrzaj
    # je tu, canonical je tacan.
    #
    # Provjerava se kao Googlebot, jer se takvo pravilo ponekad postavlja bas
    # po imenu pregledaca.
    g = []
    GBOT_UA = ('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')
    mete = [BAZA + '/', BAZA + '/products.html', BAZA + '/sitemap.xml',
            BAZA + '/kategorija/bambus-paneli', BAZA + '/paneli/drveni-panel-golden-teak']
    for u in mete:
        for ime, ua in (('posjetilac', None), ('Googlebot', GBOT_UA)):
            cmd = CURL + ['--max-time', '25', '-o', '/dev/null', '-D', '-']
            if ua:
                cmd += ['-A', ua]
            zag = subprocess.run(cmd + [u], capture_output=True, text=True).stdout.lower()
            for red in zag.splitlines():
                if red.startswith('x-robots-tag') and ('noindex' in red or 'none' in red):
                    g.append('%s → %s dobija zabranu u zaglavlju: %s'
                             % (u, ime, red.strip()[:70]))
    zabiljezi('G15', 'Nijedna stranica ne salje zabranu indeksiranja zaglavljem',
              g, len(mete) * 2)

    # ---- G16 i G17: IZMJENA MORA STICI DO POSJETIOCA ---------------------
    #
    # Zasto:
    # Recenzije su obrisane sa servera i provjera je javila "0 tragova na svih
    # 149 stranica" — a vlasnik ih je i dalje gledao na telefonu. Provjera je
    # gledala STA SERVER POSALJE, a ne STA PREGLEDAC POKAZE. js/products.js se
    # servira sa immutable na godinu, a broj u ?v= je ostao 51, pa je svaki
    # posjetilac koji je sajt vec otvarao dobijao stari fajl iz svog kesa.
    #
    # G16: fajl koji se servira dugorocno MORA imati ?v= i istu vrijednost svuda.
    # G17: ta vrijednost MORA biti hash sadrzaja fajla (alat/verzije.py).
    #      Nema pamcenja sa strane — tacna vrijednost se racuna iz samog fajla,
    #      pa pravilo ne moze zakazati zato sto je neko zaboravio nesto upisati.
    import hashlib as _h
    ref = {}          # sredstvo -> {verzija: [fajlovi]}
    for koren, dirs, fajlovi in os.walk(KORIJEN):
        dirs[:] = [d for d in dirs if d not in
                   ('.git', 'alat', 'node_modules', '.seo-audit-kes', 'images', 'data', 'admin')]
        for f in fajlovi:
            if not f.endswith(('.html', '.php')):
                continue
            try:
                with open(os.path.join(koren, f), encoding='utf-8', errors='replace') as fh:
                    t = fh.read()
            except OSError:
                continue
            for m in re.finditer(
                    r'(?:src|href)="((?:js|css|fa)/[^"?]+\.(?:js|css|woff2?|ttf))(?:\?v=([A-Za-z0-9.]+))?"', t):
                ref.setdefault(m.group(1), {}).setdefault(m.group(2) or '', []).append(
                    os.path.relpath(os.path.join(koren, f), KORIJEN))

    g16, g17, dugorocni = [], [], []
    for sred in sorted(ref):
        zag = subprocess.run(CURL + ['-I', '--max-time', '15', BAZA + '/' + sred],
                             capture_output=True, text=True, errors='replace').stdout
        if 'immutable' not in zag and 'max-age=31536000' not in zag:
            continue
        dugorocni.append(sred)
        verzije = ref[sred]
        if '' in verzije:
            g16.append('%s se kesira godinu, a %d fajl(ova) ga poziva BEZ ?v= (%s)'
                       % (sred, len(verzije['']), verzije[''][0]))
        drugi = sorted(v for v in verzije if v)
        if len(drugi) > 1:
            g16.append('%s se poziva sa vise razlicitih verzija: %s' % (sred, ', '.join(drugi)))
        put = os.path.join(KORIJEN, sred)
        if not os.path.exists(put):
            continue
        with open(put, 'rb') as fh:
            hesh = _h.sha256(fh.read()).hexdigest()[:8]
        for v in drugi:
            if v != hesh:
                g17.append('%s?v=%s ne odgovara sadrzaju (treba %s) — pokreni '
                           'python3 alat/verzije.py upisi' % (sred, v, hesh))
    zabiljezi('G16', 'Dugorocno kesiran fajl ima verziju, istu svuda', g16, max(len(dugorocni), 1))
    zabiljezi('G17', 'Verzija je hash sadrzaja fajla', g17, max(len(dugorocni), 1))

    # ---- G18-G24: ZAVRSNA ZASTITA -----------------------------------------
    #
    # Sedam pravila koja cuvaju ono sto je danas dovedeno u red. Neka se
    # preklapaju sa ranijim pravilima (A2 canonical, S1-S7 sitemap, G15
    # noindex, I1-I4 linkovi) — namjerno: ranija gledaju jednu stranu, ova
    # gledaju krajnji ishod, na zivom sajtu i u pregledacu.

    # G18 — nigdje na javnoj stranici lazna recenzija ili ocjena
    tragovi = ['rv-card', 'rv-wrap', 'testimonial-card', 'aggregateRating',
               'ratingValue', 'reviewCount', 'ratingCount', 'Ocjene korisnika',
               'Šta kažu kupci', 'Šta Kažu Naši Kupci', 'Google ocjena']
    # Stranice su vec skinute na pocetku (STRANICE) — ne skidaju se po drugi put.
    g = []
    for u in SITEMAP:
        h, kod, _, _ = STRANICE[u]
        if kod != '200':
            g.append('%s → %s' % (u.replace(BAZA, ''), kod))
            continue
        bez = re.sub(r'<!--.*?-->', '', h, flags=re.S)     # komentari smiju objasniti
        nasao = [t for t in tragovi if t in bez]
        if nasao:
            g.append('%s → %s' % (u.replace(BAZA, ''), ', '.join(nasao[:3])))
    # I javni podaci, ne samo stranice.
    for f in ('data/products.json', 'data/categories.json'):
        tijelo, kod, _, _ = dohvati('%s/%s' % (BAZA, f), timeout='15')
        if kod == '200' and re.search(r'"(reviews|rating|aggregateRating)"\s*:', tijelo):
            g.append('%s sadrzi polje sa recenzijama/ocjenama' % f)
    for f in ('data/reviews.json', 'data/reviews-extra.json'):
        _, kod, _, _ = dohvati('%s/%s' % (BAZA, f), timeout='10')
        if kod == '200':
            g.append('%s je ponovo dostupan (mora biti obrisan)' % f)
    zabiljezi('G18', 'Nigdje javno nema lazne recenzije ni ocjene', g, len(SITEMAP) + 4)

    # G19 — Product schema bez review/aggregateRating dok nema stvarnih podataka
    g = []
    prov = [u for u in SITEMAP if '/paneli/' in u]
    for u in prov:
        h, kod, _, _ = STRANICE[u]
        if kod != '200':
            g.append('%s → %s' % (u.replace(BAZA, ''), kod))
            continue
        for blok in re.findall(r'<script[^>]*application/ld\+json[^>]*>(.*?)</script>', h, re.S):
            try:
                d = json.loads(blok)
            except Exception:
                g.append('%s → JSON-LD se ne parsira' % u.replace(BAZA, ''))
                continue
            for c in mmhCvorovi(d):
                if c.get('@type') == 'Product' and ('review' in c or 'aggregateRating' in c):
                    g.append('%s → Product ima review/aggregateRating' % u.replace(BAZA, ''))
    zabiljezi('G19', 'Product schema bez izmisljene ocjene', g, len(prov))

    # G20 — canonical mora vracati 200 i pokazivati na samu stranicu
    g = []
    for u in SITEMAP:
        h, kod, _, _ = STRANICE[u]
        if kod != '200':
            g.append('%s → %s' % (u.replace(BAZA, ''), kod))
            continue
        m = re.search(r'<link[^>]+rel=["\']canonical["\'][^>]*href=["\']([^"\']+)', h, re.I)
        if not m:
            g.append('%s → nema canonical' % u.replace(BAZA, ''))
            continue
        c = m.group(1)
        if c != u:
            g.append('%s → canonical pokazuje na %s' % (u.replace(BAZA, ''), c))
            continue
        if not c.startswith('https://makemyhome.me/'):
            g.append('%s → canonical nije https bez www' % u.replace(BAZA, ''))
    zabiljezi('G20', 'Canonical vraca 200 i pokazuje na samu stranicu', g, len(SITEMAP))

    # G21 — svaka adresa iz sitemapa vraca 200 bez skoka (S1-S3 gledaju sadrzaj
    #       sitemapa; ovo gleda ishod svake adrese)
    # A1 provjerava status svih adresa. Ovdje se gleda ono sto A1 ne gleda:
    # da adresa iz sitemapa bude ISTA kao canonical te stranice — inace sitemap
    # salje Google na jednu adresu, a stranica ga upucuje na drugu.
    g = []
    for u in SITEMAP:
        h, kod, sk, _ = STRANICE[u]
        if kod != '200' or sk != '0':
            g.append('%s → %s, skokova %s' % (u.replace(BAZA, ''), kod, sk))
            continue
        m = re.search(r'<link[^>]+rel=["\']canonical["\'][^>]*href=["\']([^"\']+)', h, re.I)
        if m and m.group(1) != u:
            g.append('sitemap ima %s, a canonical je %s' % (u.replace(BAZA, ''), m.group(1)))
    zabiljezi('G21', 'Adresa iz sitemapa je 200 i jednaka canonical-u', g, len(SITEMAP))

    # G22 — nijedna adresa iz sitemapa ne smije imati noindex (ni u HTML-u ni u
    #       zaglavlju), ni kao posjetilac ni kao Googlebot
    # HTML se gleda na SVIH 149 (stranice su vec skinute), a zaglavlje na uzorku
    # od 15 kao Googlebot — jedno zaglavlje je jedan zahtjev, a G15 vec pokriva
    # deset stranica u oba oblika. Broj provjerenog je zato 149 + 15.
    g = []
    for u in SITEMAP:
        h, kod, _, _ = STRANICE[u]
        if kod == '200' and re.search(r'<meta[^>]+name=["\']robots["\'][^>]*noindex', h, re.I):
            g.append('%s → noindex u HTML-u' % u.replace(BAZA, ''))
    uzorak_zag = [BAZA + '/'] + [u for u in SITEMAP if '/kategorija/' in u][:7] \
                 + [u for u in SITEMAP if '/paneli/' in u][:7]
    for u in uzorak_zag:
        zag = subprocess.run(CURL + ['-I', '--max-time', '15', '-A', GOOGLEBOT, u],
                             capture_output=True, text=True, errors='replace').stdout
        if re.search(r'(?i)x-robots-tag:[^\r\n]*noindex', zag):
            g.append('%s → X-Robots-Tag: noindex' % u.replace(BAZA, ''))
    zabiljezi('G22', 'Nijedna adresa iz sitemapa nema noindex', g,
              len(SITEMAP) + len(uzorak_zag))

    # G23 — do svakog proizvoda se dolazi internim linkovima (I2 broji dolazne
    #       linkove; ovo trazi da ih ima bar jedan sa stranice koja nije sitemap)
    g = []
    dolazni = {}
    for u in SITEMAP:
        h, kod, _, _ = STRANICE[u]
        if kod != '200':
            continue
        bez = re.sub(r'<script[\s\S]*?</script>', '', h, flags=re.I)
        for cilj in set(re.findall(r'href="[^"]*?(/paneli/[a-z0-9-]+)"', bez)):
            if cilj != urlparse_put(u):
                dolazni[cilj] = dolazni.get(cilj, 0) + 1
    for u in prov:
        put = u.replace(BAZA, '')
        if dolazni.get(put, 0) == 0:
            g.append('%s → nijedan interni link ne vodi do njega' % put)
    zabiljezi('G23', 'Do svakog proizvoda vodi bar jedan interni link', g, len(prov))

    # G24 — pregledac ne smije dobiti stari sadrzaj iz kesa
    #
    # Ovo je pravilo zbog kojeg su i nastala G16/G17. Ako je verzija u adresi
    # hash sadrzaja, stari kes je nemoguc: druga verzija = druga adresa. Ovdje
    # se to provjerava na zivom sajtu — hash u adresi naspram sadrzaja koji
    # server stvarno vrati na toj adresi.
    g = []
    uzorak = [BAZA + '/'] + [u for u in SITEMAP if '/kategorija/' in u][:2] + \
             [u for u in SITEMAP if '/paneli/' in u][:2]
    prov_sred = 0
    for u in uzorak:
        h, kod, _, _ = dohvati(u, timeout='15')
        if kod != '200':
            g.append('%s → %s' % (u.replace(BAZA, ''), kod))
            continue
        for m in re.finditer(r'(?:src|href)="((?:js|css|fa)/[^"?]+)\?v=([A-Za-z0-9.]+)"', h):
            sred, v = m.group(1), m.group(2)
            tijelo = bajtovi('%s/%s?v=%s' % (BAZA, sred, v), timeout='15')
            if not tijelo:
                g.append('%s?v=%s → nije se skinuo' % (sred, v))
                continue
            prov_sred += 1
            stvarni = _h.sha256(tijelo).hexdigest()[:8]
            if len(v) == 8 and v != stvarni:
                g.append('%s: u adresi stoji ?v=%s, a sadrzaj na serveru daje %s '
                         '— pregledaci mogu drzati stari fajl' % (sred, v, stvarni))
    zabiljezi('G24', 'Verzija u adresi odgovara onome sto server vrati', g, max(prov_sred, 1))


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
    """Recenzija na sajtu NEMA i ne smije ih biti dok ne budu od stvarnih kupaca.

    Ranije su ovdje bila dva pravila koja su provjeravala da se svaka recenzija
    iz podataka vidi na stranici. Recenzije su uklonjene sa cijelog sajta jer su
    bile izmisljene (585 zapisa, 117 proizvoda, nijedan ispod 4,6), pa ta dva
    pravila vise nemaju smisla. Zamijenjena su obrnutim: alat sada cuva da se
    ne vrate slucajno — ni na stranicu, ni u strukturirane podatke.
    """
    print('\n=== R · RECENZIJE ===')
    tragovi = ['rv-card', 'rv-wrap', 'testimonial-card', 'aggregateRating',
               'ratingValue', 'reviewCount', '"review"', 'Ocjene korisnika',
               'Šta kažu kupci', 'Šta Kažu Naši Kupci']
    g = []
    for u in SITEMAP:
        h, kod, _, _ = dohvati(u, timeout='15')
        if kod != '200':
            g.append('%s → %s' % (u, kod))
            continue
        # Komentari u kodu smiju objasniti zasto su uklonjene — ne broje se.
        bez = re.sub(r'<!--.*?-->', '', h, flags=re.S)
        nasao = [t for t in tragovi if t in bez]
        if nasao:
            g.append('%s → %s' % (u.replace(BAZA, ''), ', '.join(nasao[:3])))
    zabiljezi('R1', 'Nigdje na sajtu nema recenzija ni ocjena', g, len(SITEMAP))

    # Isto i u kodu koji se deployuje — da se ne vrati tihо kroz JavaScript.
    g = []
    for rel in ('js/products.js', 'js/main-v4.js', 'product.php', 'products.php', 'index.html'):
        put = os.path.join(KORIJEN, rel)
        if not os.path.exists(put):
            continue
        with open(put, encoding='utf-8', errors='replace') as fh:
            kod_t = fh.read()
        # PHP i JS komentari se izbacuju — u njima stoji zapis zasto su uklonjene.
        kod_t = re.sub(r'/\*.*?\*/', '', kod_t, flags=re.S)
        kod_t = re.sub(r'^\s*//.*$', '', kod_t, flags=re.M)
        kod_t = re.sub(r'<!--.*?-->', '', kod_t, flags=re.S)
        for t in ('rv-card', 'rv-wrap', 'testimonial-card', 'aggregateRating', 'reviewCount'):
            if t in kod_t:
                g.append('%s sadrzi %s' % (rel, t))
    zabiljezi('R2', 'Ni u kodu nema ostatka koji bi ih vratio', g, 5)


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

    # ---- Do svake stranice se mora doci sa pocetne, pracenjem linkova ------
    #
    # I1 provjerava da do stranice vodi bar jedan link. To nije isto: stranica
    # moze imati link sa druge stranice do koje se takodje ne moze doci. Ovdje
    # se pusta pauk koji krece sa pocetne i prati linkove do tri klika dubine,
    # i gleda dosegne li svih 149 adresa iz sitemapa.
    #
    # Zasto je vazno: u Search Console-u je na svakoj stranici pisalo
    # "No referring sitemaps detected" — Google nijednu adresu nije pripisivao
    # sitemapu. Da sajt zavisi od sitemapa, to bi bio ozbiljan problem. Ovako
    # nije: i da ga Google potpuno ignorise, do svake stranice dodje linkovima.
    from urllib.parse import urljoin as _spoji
    g = []
    cilj = {a for a in SITEMAP if '/images/' not in a}
    posjeceno, red, nadjeno = set(), [(BAZA + '/', 0)], set()
    while red and len(posjeceno) < 80:
        u, dub = red.pop(0)
        if u in posjeceno or dub > 3:
            continue
        posjeceno.add(u)
        h = STRANICE.get(u, (None,))[0]
        if h is None:
            h, kodP, _, _ = dohvati(u, timeout='20')
            if kodP != '200':
                continue
        if not h or len(h) < 500:
            continue
        b = re.search(r'<base href="([^"]*)"', h)
        baza = b.group(1) if b else u
        bez = re.sub(r'<script[^>]*>.*?</script>', '', h, flags=re.S)
        for m in re.findall(r'href="([^"#][^"]*)"', bez):
            if m.startswith(('mailto:', 'tel:', 'javascript:', 'viber:', 'data:')):
                continue
            if m.startswith('http') and not m.startswith(BAZA):
                continue
            a = _spoji(baza, m).split('#')[0].split('?')[0]
            if not a.startswith(BAZA):
                continue
            if a in cilj:
                nadjeno.add(a)
            if a not in posjeceno:
                red.append((a, dub + 1))
    for u in sorted(cilj - nadjeno):
        g.append('%s → do nje se ne moze doci sa pocetne u tri klika' % u)
    zabiljezi('I4', 'Do svake stranice se dolazi sa pocetne, bez sitemapa', g, len(cilj))


# ============================================================
# S — SITEMAP
#
# Sitemap je jedini dokument koji Google cita da bi znao STA sajt uopste ima
# i KAD se sta promijenilo. Do sada se provjeravao samo posredno — kroz A1
# (svaka adresa iz njega vraca 200) — pa su dvije greske u njemu prosle
# neprimijeceno:
#
#   * sync.php je prepisivao svaki fajl pri svakom pokretanju, pa je vrijeme
#     izmjene mjerilo deploy a ne izmjenu sadrzaja, i svih 149 adresa je
#     javljalo isti datum. Kad sve stranice svaki put kazu da su nove, Google
#     prestane da vjeruje tom podatku;
#   * kes sitemapa nije zavisio od stranica iz kojih se ti datumi racunaju,
#     pa izmjena teksta na stranici ne bi ni stigla do Googlea.
# ============================================================
def grupa_S():
    print('\n=== S · SITEMAP ===')

    sm, kod, sk, _ = dohvati(BAZA + '/sitemap.xml', timeout='30')
    g = []
    if kod != '200':
        g.append('sitemap.xml → %s' % kod)
    if sk != '0':
        g.append('sitemap.xml ide kroz %s preusmjerenja' % sk)
    # GET a ne HEAD: Apache na HEAD zahtjev ne primjenjuje mod_deflate, pa bi
    # provjera javila da kompresije nema iako radi. Prva verzija ovog pravila
    # je bas tako lagala.
    zag = subprocess.run(CURL + ['--max-time', '30', '-D', '-', '-o', '/dev/null',
                                 '-H', 'Accept-Encoding: gzip',
                                 BAZA + '/sitemap.xml'], capture_output=True, text=True).stdout.lower()
    if 'content-type: application/xml' not in zag:
        g.append('sitemap se ne servira kao application/xml')
    if 'content-encoding: gzip' not in zag:
        g.append('sitemap se ne salje sazet (gzip) — 123 kB umjesto 9 kB')
    rob, _, _, _ = dohvati(BAZA + '/robots.txt', timeout='10')
    if 'Sitemap: %s/sitemap.xml' % BAZA not in rob:
        g.append('robots.txt ne navodi sitemap')
    zabiljezi('S1', 'Sitemap se servira ispravno i sazeto', g, 5)

    # ---- Struktura ----------------------------------------------------------
    g = []
    korijen = None
    try:
        korijen = ET.fromstring(sm)
    except Exception as e:
        g.append('sitemap nije validan XML: %s' % e)
    NSM = {'s': 'http://www.sitemaps.org/schemas/sitemap/0.9',
           'i': 'http://www.google.com/schemas/sitemap-image/1.1'}
    adrese, slikeSM = [], []
    if korijen is not None:
        if not korijen.tag.endswith('}urlset'):
            g.append('korijenski element nije <urlset>')
        stavke = korijen.findall('s:url', NSM)
        adrese = [x.find('s:loc', NSM).text for x in stavke if x.find('s:loc', NSM) is not None]
        slikeSM = [x.text for x in korijen.findall('.//i:loc', NSM)]
        for a, n in collections.Counter(adrese).items():
            if n > 1:
                g.append('adresa se ponavlja %d puta: %s' % (n, a))
        for a in adrese:
            if not a.startswith(BAZA + '/'):
                g.append('adresa nije apsolutna na nas domen: %s' % a)
        for s in set(slikeSM):
            if not s.startswith(BAZA + '/'):
                g.append('slika nije apsolutna na nas domen: %s' % s)
        VALID = {'always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'}
        for e in korijen.findall('.//s:changefreq', NSM):
            if e.text not in VALID:
                g.append('nevalidan changefreq: %s' % e.text)
        for e in korijen.findall('.//s:priority', NSM):
            try:
                if not 0.0 <= float(e.text) <= 1.0:
                    raise ValueError
            except (TypeError, ValueError):
                g.append('nevalidan priority: %s' % e.text)
        danas = datetime.date.today()
        for x in stavke:
            d = x.find('s:lastmod', NSM)
            loc = x.find('s:loc', NSM)
            if d is None or not d.text:
                g.append('%s → nema lastmod' % (loc.text if loc is not None else '?'))
                continue
            try:
                if datetime.date.fromisoformat(d.text[:10]) > danas:
                    g.append('%s → lastmod u buducnosti: %s' % (loc.text, d.text))
            except ValueError:
                g.append('%s → lastmod nije Y-m-d: %s' % (loc.text, d.text))
        # Sitemap smije nositi najvise 50 000 adresa i 50 MB nesazeto
        if len(adrese) > 50000:
            g.append('vise od 50 000 adresa (%d) — mora se podijeliti' % len(adrese))
        if len(sm) > 50 * 1024 * 1024:
            g.append('veci od 50 MB nesazeto')
        # Naslovi slika
        for x in korijen.findall('.//i:image', NSM):
            t = x.find('i:title', NSM)
            if t is None or not (t.text or '').strip():
                lo = x.find('i:loc', NSM)
                g.append('slika bez naslova: %s' % (lo.text if lo is not None else '?'))
    zabiljezi('S2', 'Sitemap je strukturno ispravan', g, len(adrese))

    # ---- Nijedna stranica ne smije faliti -----------------------------------
    #
    # Stranica koje nema u sitemapu Google mozda nikad i ne otkrije. Ako je
    # namjerno izostavljena (korpa, naplata, hvala), mora nositi noindex —
    # inace se ne zna da li je izostavljena namjerno ili greskom.
    g = []
    usm = set(adrese)
    html = sorted(f for f in os.listdir(KORIJEN) if f.endswith('.html'))
    for f in html:
        if f == 'index.html' or '%s/%s' % (BAZA, f) in usm:
            continue
        h, kodF, _, _ = dohvati('%s/%s' % (BAZA, f), timeout='12')
        if kodF == '404':
            continue                      # ne postoji kao adresa — u redu
        if 'noindex' not in h.lower():
            g.append('%s → nije u sitemapu, a nema noindex (%s)' % (f, kodF))
    zabiljezi('S3', 'Nijedna stranica ne fali u sitemapu bez razloga', g, len(html))

    # ---- Kes mora zavisiti od svega iz cega se racunaju datumi --------------
    #
    # Ovo je staticka provjera samog sitemap.php, ne sajta. Datum svake adrese
    # racuna se iz odredjenih fajlova; ako kes ne zavisi bas od tih fajlova,
    # izmjena stranice se nikad ne pojavi u sitemapu. Bas se to i desilo.
    g = []
    koristi = set()
    try:
        izv = open(os.path.join(KORIJEN, 'sitemap.php'), encoding='utf-8').read()
        # Vodeca kosa crta se skida: __DIR__ . '/data/products.json' i
        # 'data/products.json' su isti fajl, a bez normalizacije bi se
        # poredili kao dva razlicita i pravilo bi javljalo greske kojih nema.
        imena = lambda t: {x.lstrip('/') for x in
                           re.findall(r"'([^']+\.(?:php|json|html))'", t)}

        # $mmhStatika — spisak statickih stranica; ulazi u kes preko array_column
        st = re.search(r'\$mmhStatika\s*=\s*\[(.*?)\n\];', izv, re.S)
        statika = imena(st.group(1)) if st else set()

        # $mmhPodaci i $mmhOkvir — promjenljive koje se prosljedjuju mmhVrijeme()
        def var(ime):
            m = re.search(r'\$%s\s*=\s*\[(.*?)\];' % ime, izv, re.S)
            return imena(m.group(1)) if m else set()
        podaci, okvir = var('mmhPodaci'), var('mmhOkvir')

        # Od cega kes zavisi — dodjela $izvori koja stoji prije $najnoviji.
        # Hvata se bilo koji oblik (obican niz ili array_merge), jer je stari
        # kod bio obican niz; regex vezan samo za array_merge bi na njemu
        # prijavio da nista nije pokriveno, sto je tacno po ishodu ali
        # pogresno po obrazlozenju.
        prije = izv.split('$najnoviji = 0;')[0]
        d = re.findall(r'\$izvori\s*=\s*(.*?);', prije, re.S)
        kes = imena(d[-1]) if d else set()
        if d and 'array_column($mmhStatika' in d[-1]:
            kes |= statika

        # Iz cega se datumi stvarno racunaju
        for m in re.finditer(r'mmhVrijeme\((.*?)\)\)?[,;]', izv, re.S):
            t = m.group(1)
            koristi |= imena(t)
            if '$mmhPodaci' in t:
                koristi |= podaci
            if '$mmhOkvir' in t:
                koristi |= okvir
            if re.search(r'\[\s*\$f\b', t) or ', [$f]' in t:
                koristi |= statika          # petlja po $mmhStatika
        # $izvori se u petlji po statici gradi posebno
        for m in re.finditer(r'\$izvori\s*=\s*array_merge\(\$mmh(Okvir|Podaci),\s*\[(.*?)\]\)', izv):
            koristi |= (okvir if m.group(1) == 'Okvir' else podaci) | imena('[' + m.group(2) + ']')
        koristi |= statika                  # svaka staticka stranica nosi svoj datum

        for f in sorted(koristi - kes):
            g.append('datum se racuna iz %s, a kes od njega ne zavisi — izmjena te '
                     'stranice ne bi stigla do Googlea' % f)
    except Exception as e:
        g.append('provjera kesa nije mogla da se izvrsi: %s' % e)
    zabiljezi('S4', 'Kes sitemapa zavisi od svega iz cega se racunaju datumi', g, len(koristi))

    # ---- Kategorija koja na stranici ima proizvode mora imati i slike -------
    #
    # /kategorija/bambus-paneli prikazuje 39 proizvoda sa 39 fotografija, a u
    # sitemapu je stajala bez ijedne slike. Uzrok: bambus-paneli je nadredjena
    # kategorija — nijedan proizvod je ne nosi u polju "category", nego se na
    # njoj prikazuju svi bambus podtipovi. products.php to zna, sitemap.php
    # nije znao. Google slike otkriva prvenstveno preko sitemapa, pa je cijela
    # ta kategorija bila nevidljiva za pretragu slika.
    #
    # Nijedno pravilo to nije moglo vidjeti: adresa vraca 200, stranica je
    # puna, slike postoje. Greska je bila u tome sto sitemap i stranica ne
    # racunaju isto.
    g = []
    parovi = 0
    if korijen is not None:
        for x in korijen.findall('s:url', NSM):
            loc = x.find('s:loc', NSM)
            if loc is None or '/kategorija/' not in loc.text:
                continue
            parovi += 1
            uSM = len(x.findall('.//i:loc', NSM))
            h, kodK, _, _ = dohvati(loc.text, timeout='20')
            if kodK != '200':
                g.append('%s → %s' % (loc.text, kodK))
                continue
            naStr = h.count('class="product-card')
            if naStr and not uSM:
                g.append('%s → stranica pokazuje %d proizvoda, a sitemap nema nijednu sliku'
                         % (loc.text, naStr))
            elif naStr and uSM < naStr:
                g.append('%s → %d proizvoda na stranici, samo %d slika u sitemapu'
                         % (loc.text, naStr, uSM))
    zabiljezi('S6', 'Kategorija sa proizvodima ima i slike u sitemapu', g, parovi)

    # ---- Svaki proizvod i kategorija MORAJU biti u sitemapu ---------------
    #
    # Do sada se provjeravalo samo ono sto u sitemapu JESTE — da svaka adresa
    # vraca 200. Ono cega u njemu NEMA nije provjeravao niko. Proizvod koji
    # ispadne iz sitemapa Google mozda nikad ne otkrije, a nista to ne bi
    # javilo: sve ostale provjere bi i dalje prolazile.
    #
    # Provjerava se u oba smjera: da nijedan proizvod iz podataka ne fali, i
    # da u sitemapu nema adrese proizvoda kojeg vise nema u podacima.
    g = []
    php = subprocess.run(['php', '-r',
        'require "%s/php/slug.php"; $d=json_decode(file_get_contents("php://stdin"),true); '
        '$P=$d["products"]??$d; $o=[]; foreach($P as $p) $o[$p["id"]]=mmhSlugProizvoda($p); '
        'echo json_encode($o);' % KORIJEN],
        input=json.dumps({'products': PROIZVODI}), capture_output=True, text=True)
    try:
        slugovi = json.loads(php.stdout)
    except Exception as e:
        slugovi = {}
        g.append('ne mogu izracunati adrese proizvoda: %s' % e)
    uSitemapu = set(adrese)
    for pid, sl in slugovi.items():
        if BAZA + '/' + sl not in uSitemapu:
            g.append('proizvod %s (%s) nije u sitemapu — Google ga mozda nikad ne otkrije' % (pid, sl))
    nasi = {BAZA + '/' + sl for sl in slugovi.values()}
    for u in sorted(uSitemapu):
        if '/paneli/' in u and u not in nasi:
            g.append('%s je u sitemapu, a tog proizvoda nema u podacima' % u)
    KATEGORIJE = ['bambus-paneli', 'bambus-drveni', 'bambus-tekstilni', 'bambus-mermerni',
                  'bambus-metalni', 'bambus-kozni', '3d-letvice', 'akusticni-paneli',
                  'aluminijum-lajsne', 'spc-pod', 'pu-kamen', 'classic', 'mdf', 'flex-stone']
    for k in KATEGORIJE:
        if BAZA + '/kategorija/' + k not in uSitemapu:
            g.append('kategorija %s nije u sitemapu' % k)
    # Uz to: svaki proizvod mora u sitemapu imati SVE svoje slike — glavnu i
    # sve iz galerije. Google slike otkriva prvenstveno preko sitemapa; ako
    # fotografija enterijera nije u njemu, za pretragu slika ne postoji.
    # S6 ovo provjerava za kategorije, ali stranice proizvoda nije gledao niko.
    if korijen is not None:
        slikaPo = {}
        for x in korijen.findall('s:url', NSM):
            lo = x.find('s:loc', NSM)
            if lo is not None and '/paneli/' in lo.text:
                slikaPo[lo.text] = len(x.findall('.//i:loc', NSM))
        for p in PROIZVODI:
            sl = slugovi.get(str(p.get('id')))
            if not sl:
                continue
            treba = (1 if p.get('image') else 0) + len(p.get('gallery') or [])
            ima = slikaPo.get(BAZA + '/' + sl, -1)
            if ima != treba:
                g.append('%s → u podacima %d slika, u sitemapu %d'
                         % (p.get('name', '?'), treba, ima))
    zabiljezi('S7', 'Svaki proizvod i kategorija su u sitemapu, sa svim slikama',
              g, len(slugovi) + len(KATEGORIJE))

    # ---- Slike (sporo) ------------------------------------------------------
    if SPORO:
        g = []
        for s in sorted(set(slikeSM)):
            _, kodS, _, _ = dohvati(s, timeout='15')
            if kodS != '200':
                g.append('%s → %s' % (s.replace(BAZA + '/', ''), kodS))
        zabiljezi('S5', 'Svaka slika iz sitemapa postoji', g, len(set(slikeSM)))


# ============================================================
GRUPE = {'A': grupa_A, 'B': grupa_B, 'C': grupa_C, 'D': grupa_D,
         'E': grupa_E, 'F': grupa_F, 'G': grupa_G, 'H': grupa_H,
         'I': grupa_I, 'R': grupa_R, 'S': grupa_S}

if __name__ == '__main__':
    arg = (sys.argv[1] if len(sys.argv) > 1 else 'brzo').upper()
    if arg == 'SVE':
        red = 'ABCDEFGHIRS'
    elif arg == 'BRZO':
        red = 'ACDFGIRS'
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
