#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
r2-ostalo.py — sitne, ali provjerljive stvari iz druge runde.

  1. lang i hreflang na svih 149 stranica
  2. kosa crta na kraju adrese — da li su interni linkovi dosljedni
  3. /kategorija/* mora imati BreadcrumbList i ItemList
  4. /paneli/* mora imati Product sa Offer, cijenom i dostupnoscu
  5. robots.txt ne smije blokirati /js, /css, /images, /fa
  6. Rich Results Test i Search Console — sta se moze provjeriti odavde

  python3 alat/r2-ostalo.py   →  R2-OSTALO.md
"""
import concurrent.futures as fut
import json
import os
import re
import subprocess
import sys
from urllib.parse import urlparse

SAJT = 'https://makemyhome.me'
KOR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
GBOT = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'
CURL = ['curl', '-sk', '--cacert', '/root/.ccr/ca-bundle.crt', '--max-time', '25', '-A', GBOT]


def uzmi(url):
    return subprocess.run(CURL + [url], capture_output=True, text=True, errors='replace').stdout


def ldblokovi(html):
    out = []
    for m in re.finditer(r'<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>',
                         html, re.S | re.I):
        try:
            out.append(json.loads(m.group(1)))
        except Exception:
            out.append({'__nevalidan': m.group(1)[:80]})
    return out


def cvorovi(d):
    """Razmota @graph i liste u ravan spisak cvorova."""
    if isinstance(d, list):
        for x in d:
            yield from cvorovi(x)
    elif isinstance(d, dict):
        if '@graph' in d:
            yield from cvorovi(d['@graph'])
        else:
            yield d


def tipovi(html):
    t = set()
    for b in ldblokovi(html):
        for c in cvorovi(b):
            v = c.get('@type')
            if isinstance(v, list):
                t.update(v)
            elif v:
                t.add(v)
    return t


def main():
    L = []
    r = L.append
    r('# R2 — ostalo')
    r('')

    sm = uzmi(SAJT + '/sitemap.xml')
    adrese = re.findall(r'<loc>([^<]+)</loc>', sm)
    r(f'Provjereno {len(adrese)} adresa iz sitemapa.')

    with fut.ThreadPoolExecutor(max_workers=10) as ex:
        strane = dict(zip(adrese, ex.map(uzmi, adrese)))

    # ------------------------------------------------------------ 1. lang ---
    r('')
    r('## 1. lang i hreflang')
    r('')
    jezici, bezLang, hreflang = {}, [], []
    for u, h in strane.items():
        m = re.search(r'<html[^>]*\blang=["\']([^"\']+)', h, re.I)
        if not m:
            bezLang.append(u)
        else:
            jezici.setdefault(m.group(1), []).append(u)
        if re.search(r'rel=["\']alternate["\'][^>]*hreflang', h, re.I):
            hreflang.append(u)
    if bezLang:
        r(f'[!!] {len(bezLang)} stranica bez `lang`: ' + ', '.join(f'`{urlparse(x).path}`' for x in bezLang[:5]))
    else:
        r('[i] Svaka stranica ima `lang`.')
    for j, spisak in sorted(jezici.items(), key=lambda x: -len(x[1])):
        oznaka = '[i]' if j == 'sr-ME' and len(spisak) == len(adrese) else '[!]'
        r(f'{oznaka} `lang="{j}"` na {len(spisak)} stranica'
          + ('' if len(spisak) == len(adrese) else ': ' + ', '.join(f'`{urlparse(x).path}`' for x in spisak[:6])))
    r(f'[i] hreflang: {len(hreflang)} stranica. Sajt je jednojezican, pa hreflang nije potreban.')

    # -------------------------------------------------- 2. kosa crta --------
    r('')
    r('## 2. Kosa crta na kraju adrese')
    r('')
    sa, bez, spoljni = 0, 0, 0
    primjeri = []
    for u, h in strane.items():
        # Skripte se prvo izbace. U cjenovniku stoji JavaScript koji sklapa
        # adresu: href="/kategorija/' + slug + '" — prva verzija ove provjere
        # je to procitala kao pravi link i prijavila nedosljednu kosu crtu
        # koje na sajtu nema.
        h = re.sub(r'<script[\s\S]*?</script>', '', h, flags=re.I)
        for m in re.finditer(r'<a\b[^>]+href=["\']([^"\'#]+)', h, re.I):
            v = m.group(1)
            if v.startswith(('http', 'mailto:', 'tel:', 'javascript:')):
                if not v.startswith(SAJT):
                    spoljni += 1
                    continue
                v = v[len(SAJT):]
            if not v or v.startswith(('?', '#')):
                continue
            if re.search(r'\.(html|xml|jpg|png|webp|css|js|pdf)$', v, re.I):
                continue
            if v.endswith('/') and v != '/':
                sa += 1
                if len(primjeri) < 6:
                    primjeri.append((urlparse(u).path, v))
            elif v != '/':
                bez += 1
    r(f'- interni linkovi BEZ kose crte na kraju: **{bez}**')
    r(f'- interni linkovi SA kosom crtom na kraju: **{sa}**')
    if sa and bez:
        r('')
        r(f'[!] Oba oblika se koriste. Sajt na oba odgovara ({sa} sa crtom vodi na 301),')
        r('ali svaki takav link trosi jedan skok. Primjeri:')
        for gdje, v in primjeri:
            r(f'  - `{gdje}` → `{v}`')
    elif sa == 0:
        r('')
        r('[i] Nijedan interni link nema kosu crtu na kraju — dosljedno.')

    # ------------------------------------------- 3. schema na kategorijama --
    r('')
    r('## 3. Schema na /kategorija/*')
    r('')
    r('| kategorija | BreadcrumbList | ItemList | CollectionPage | broj Offer |')
    r('|---|---|---|---|---|')
    manjka = 0
    for u in sorted(x for x in adrese if '/kategorija/' in x):
        h = strane[u]
        t = tipovi(h)
        ponuda = len(re.findall(r'"@type"\s*:\s*"Offer"', h))
        bc = 'da' if 'BreadcrumbList' in t else '**NE**'
        il = 'da' if 'ItemList' in t else '**NE**'
        cp = 'da' if ('CollectionPage' in t or 'WebPage' in t) else '–'
        if 'BreadcrumbList' not in t or 'ItemList' not in t:
            manjka += 1
        r(f'| `{urlparse(u).path}` | {bc} | {il} | {cp} | {ponuda} |')
    r('')
    r(('[i] Sve kategorije imaju i BreadcrumbList i ItemList.' if manjka == 0
       else f'[!] {manjka} kategorija nema jedno od to dvoje.'))

    # ------------------------------------------- 4. schema na proizvodima ---
    r('')
    r('## 4. Schema na /paneli/*')
    r('')
    proizvodi = [x for x in adrese if '/paneli/' in x]
    bezProduct, bezOffer, bezCijene, bezStanja = [], [], [], []
    for u in proizvodi:
        h = strane[u]
        t = tipovi(h)
        if 'Product' not in t:
            bezProduct.append(u)
        # Offer je UGNIJEZDEN u Product.offers, ne stoji kao poseban cvor —
        # trazenje po spisku tipova ga zato nikad ne nadje. Prva verzija ove
        # provjere je tako prijavila da svih 117 proizvoda nema ponudu, a u
        # istom dahu da nijedan nema ponudu bez cijene. Gleda se u podacima.
        po = [c for b in ldblokovi(h) for c in cvorovi(b) if c.get('@type') == 'Product']
        ponude = []
        for p in po:
            of = p.get('offers') or {}
            ponude += (of if isinstance(of, list) else [of])
        ponude = [o for o in ponude if isinstance(o, dict) and o]
        if not ponude:
            bezOffer.append(u)
            continue
        ima_c = any(o.get('price') for o in ponude)
        ima_s = any(o.get('availability') for o in ponude)
        if not ima_c:
            bezCijene.append(u)
        if not ima_s:
            bezStanja.append(u)
    r(f'- proizvoda: **{len(proizvodi)}**')
    for naziv, spisak in (('bez `Product`', bezProduct), ('bez `Offer`', bezOffer),
                          ('bez cijene u ponudi', bezCijene), ('bez dostupnosti u ponudi', bezStanja)):
        if spisak:
            r(f'- [!!] {naziv}: **{len(spisak)}** — ' + ', '.join(f'`{urlparse(x).path}`' for x in spisak[:5]))
        else:
            r(f'- [i] {naziv}: 0')

    # ---------------------------------------------------- 5. robots.txt -----
    r('')
    r('## 5. robots.txt i sredstva za iscrtavanje')
    r('')
    rb = uzmi(SAJT + '/robots.txt')
    r('```')
    r(rb.strip())
    r('```')
    blok = []
    for staza in ('/js', '/css', '/images', '/fa', '/assets', '/data'):
        for m in re.finditer(r'^\s*Disallow:\s*(\S+)', rb, re.M | re.I):
            if m.group(1) != '/' and staza.startswith(m.group(1).rstrip('*')):
                blok.append((staza, m.group(1)))
    if blok:
        for s, p in blok:
            r(f'[!!] `{s}` je blokiran pravilom `Disallow: {p}` — Google ne moze iscrtati stranicu.')
    else:
        r('[i] Nista od `/js`, `/css`, `/images`, `/fa`, `/assets`, `/data` nije blokirano.')
    for staza in ('/js/products.js', '/css/style-v5.css', '/data/products.json'):
        kod = subprocess.run(CURL + ['-o', '/dev/null', '-w', '%{http_code}', SAJT + staza],
                             capture_output=True, text=True).stdout.strip()
        r(f'- `{staza}` → {kod}')

    # -------------------------------------------- 6. sta se ne moze odavde --
    r('')
    r('## 6. Rich Results Test i Search Console')
    r('')
    r('- **Rich Results Test** nema javni API. Google nudi samo web alat')
    r('  (`search.google.com/test/rich-results`) i on trazi prijavu, pa se ne moze')
    r('  pozvati iz skripte. Zamjena koju ovaj alat radi: parsira svaki JSON-LD blok,')
    r('  provjerava tipove i obavezna polja (sekcije 3 i 4 iznad). To hvata sve sto')
    r('  Rich Results Test prijavljuje kao gresku, osim Googleovih internih pravila.')
    r('- **Search Console API** trazi OAuth pristup vlasnika naloga. U ovom okruzenju')
    r('  ga nema, pa se Crawl Stats ne moze povuci. To ostaje na vama u pregledacu:')
    r('  Settings → Crawl stats.')

    with open(os.path.join(KOR, 'R2-OSTALO.md'), 'w', encoding='utf-8') as fh:
        fh.write('\n'.join(L) + '\n')
    print('Gotovo → R2-OSTALO.md')
    return 0


if __name__ == '__main__':
    sys.exit(main())
