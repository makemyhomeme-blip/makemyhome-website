#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
SNIMAK SAJTA — da se vidi sta je izmjena promijenila, a sta nije.

Zasto postoji:
Svaka popravka moze da pokvari nesto drugo, a to se cesto ne primijeti. Danas
se to desilo vise puta: ispravka opisa kategorija zamalo prepisala imena
kategorija, dodavanje jednog fajla oborilo sajt na pola minuta, ispis mreze
kategorija unio gresku u nivoe naslova.

Ovaj alat snimi stanje SVIH stranica prije izmjene, pa poslije, i pokaze
tacno sta se promijenilo. Ako se promijenilo samo ono sto si htio — u redu.
Ako se promijenilo jos nesto — vidis odmah, prije nego sto Google vidi.

Pokretanje:
    python3 alat/snimak.py snimi prije      # prije izmjene
    ... radis izmjenu i deployujes ...
    python3 alat/snimak.py snimi poslije    # poslije izmjene
    python3 alat/snimak.py uporedi prije poslije

Snimci se cuvaju u alat/snimci/ i NE deployuju se na server.
"""
import json, os, re, subprocess, sys, gzip
from concurrent.futures import ThreadPoolExecutor

BAZA = 'https://makemyhome.me'
KORIJEN = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
FOLDER = os.path.join(KORIJEN, 'alat', 'snimci')
CURL = ['curl', '-sk', '--cacert', '/root/.ccr/ca-bundle.crt', '--max-time', '30',
        '-A', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)']


def dohvati(u):
    r = subprocess.run(CURL + ['-w', '\n@@%{http_code}', u], capture_output=True, text=True, errors='replace').stdout
    i = r.rfind('\n@@')
    return (r[:i], r[i + 3:]) if i >= 0 else ('', '000')


def mjere(u):
    """Sve sto nas zanima na jednoj stranici — brojevi, ne cio HTML."""
    h, kod = dohvati(u)
    if kod != '200':
        return u, {'kod': kod}
    bez = re.sub(r'<(script|style)[^>]*>.*?</\1>', '', h, flags=re.S)
    tekst = re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', ' ', bez)).strip()

    def prvi(p, d=''):
        m = re.search(p, h, re.S)
        return re.sub(r'\s+', ' ', m.group(1)).strip() if m else d

    tipovi = []
    for blok in re.findall(r'<script type="application/ld\+json">(.*?)</script>', h, re.S):
        try:
            j = json.loads(blok)
        except Exception:
            tipovi.append('NEISPRAVAN-JSON')
            continue
        for x in (j if isinstance(j, list) else [j]):
            t = x.get('@type')
            tipovi.append(','.join(t) if isinstance(t, list) else str(t))

    return u, {
        'kod': kod,
        'naslov': prvi(r'<title>(.*?)</title>'),
        'opis': prvi(r'<meta name="description" content="([^"]*)"'),
        'canonical': prvi(r'<link rel="canonical" href="([^"]*)"'),
        'og_slika': prvi(r'<meta property="og:image" content="([^"]*)"'),
        'rijeci': len(tekst.split()),
        'h1': len(re.findall(r'<h1[\s>]', h)),
        'h2': len(re.findall(r'<h2[\s>]', h)),
        'slika': len(re.findall(r'<img[\s>]', h)),
        'kartica_proizvoda': h.count('class="product-card'),
        'kartica_kategorija': h.count('class="cat-card"'),
        'linkova_proizvod': len(set(re.findall(r'href="[^"]*/paneli/[^"]*"', h))),
        'linkova_kategorija': len(set(re.findall(r'href="[^"]*/kategorija/[^"]*"', h))),
        'prazni_blokovi': len(re.findall(r'loading-placeholder', re.sub(r'<script[^>]*>.*?</script>', '', h, flags=re.S))),
        'schema': sorted(set(tipovi)),
        'noindex': 'noindex' in prvi(r'<meta name="robots" content="([^"]*)"').lower(),
    }


def snimi(ime):
    os.makedirs(FOLDER, exist_ok=True)
    h, _ = dohvati(BAZA + '/sitemap.xml')
    urls = re.findall(r'<loc>(.*?)</loc>', h)
    print('… snimam %d stranica' % len(urls))
    with ThreadPoolExecutor(10) as ex:
        podaci = dict(ex.map(mjere, urls))
    # i sam sitemap
    podaci['@sitemap'] = {'adresa': h.count('<loc>'), 'slika': h.count('<image:image>')}
    put = os.path.join(FOLDER, ime + '.json')
    with gzip.open(put + '.gz', 'wt', encoding='utf-8') as f:
        json.dump(podaci, f, ensure_ascii=False)
    print('snimljeno: %s.gz  (%d stranica)' % (put, len(urls)))


def ucitaj(ime):
    put = os.path.join(FOLDER, ime + '.json.gz')
    if not os.path.exists(put):
        sys.exit('nema snimka "%s" — prvo: python3 alat/snimak.py snimi %s' % (ime, ime))
    with gzip.open(put, 'rt', encoding='utf-8') as f:
        return json.load(f)


def uporedi(a_ime, b_ime):
    A, B = ucitaj(a_ime), ucitaj(b_ime)
    nestale = [u for u in A if u not in B]
    nove = [u for u in B if u not in A]
    if nestale:
        print('NESTALO STRANICA: %d' % len(nestale))
        for u in nestale[:10]:
            print('   ' + u)
    if nove:
        print('NOVE STRANICE: %d' % len(nove))
        for u in nove[:10]:
            print('   ' + u)

    promjene = {}
    for u in A:
        if u not in B:
            continue
        for k in A[u]:
            if A[u].get(k) != B[u].get(k):
                promjene.setdefault(k, []).append((u, A[u].get(k), B[u].get(k)))

    if not promjene and not nestale and not nove:
        print('\nNISTA SE NIJE PROMIJENILO.')
        return 0

    print('\n=== STA SE PROMIJENILO ===')
    for k, l in sorted(promjene.items(), key=lambda x: -len(x[1])):
        print('\n%-22s %d stranica' % (k, len(l)))
        for u, a, b in l[:4]:
            sa, sb = str(a)[:60], str(b)[:60]
            print('   %s' % u.replace(BAZA, ''))
            print('      prije : %s' % sa)
            print('      poslije: %s' % sb)
        if len(l) > 4:
            print('   …i jos %d' % (len(l) - 4))
    return len(promjene)


if __name__ == '__main__':
    if len(sys.argv) >= 3 and sys.argv[1] == 'snimi':
        snimi(sys.argv[2])
    elif len(sys.argv) >= 4 and sys.argv[1] == 'uporedi':
        uporedi(sys.argv[2], sys.argv[3])
    else:
        print(__doc__)
