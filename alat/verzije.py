#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
verzije.py — cache-busting po sadrzaju fajla, bez rucnog brojanja.

Zasto postoji
-------------
js/products.js, css/style-v5.css i ostali se serviraju sa
"Cache-Control: max-age=31536000, immutable" — pregledac ih drzi godinu dana i
ne pita server. Jedini nacin da izmjena stigne do posjetioca je da se promijeni
ADRESA fajla, a to je radio rucno upisan broj: ?v=51, ?v=52...

Taj broj je jednom zaboravljen. Recenzije su bile obrisane sa servera, svaka
provjera je javljala "0 tragova", a vlasnik ih je i dalje gledao na telefonu —
jer je njegov pregledac imao stari js/products.js pod istom adresom ?v=51.

Ovdje broj vise ne postoji. Verzija je prvih 8 znakova SHA-256 sadrzaja fajla:

    js/products.js?v=a373a25d

Ako se fajl promijeni, hash se promijeni sam. Ako se ne promijeni, adresa ostaje
ista i pregledac s pravom koristi ono sto vec ima. Nista se ne pamti sa strane —
tacna vrijednost se uvijek moze izracunati iz samog fajla, pa provjera ne zavisi
od toga da li je neko nesto zapisao.

Upotreba
--------
    python3 alat/verzije.py            # samo prijavi sta nije uskladjeno
    python3 alat/verzije.py upisi      # prepise adrese u svim HTML/PHP fajlovima

Poslije "upisi" izmijenjeni fajlovi se moraju deployovati kao i svaki drugi.
"""
import hashlib
import os
import re
import sys

KOR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# Direktorijumi koji se ne pretrazuju za pozive.
PRESKOCI = {'.git', 'alat', 'node_modules', '.seo-audit-kes', 'images', 'data',
            'admin', 'fa/webfonts-izvor'}

# Sta se verzionise: sve iz js/, css/ i fa/ sto se dugo kesira.
SREDSTVO = re.compile(r'(?:src|href)="((?:js|css|fa)/[^"?]+\.(?:js|css|woff2?|ttf))(\?v=[^"]*)?"')


def hash_fajla(rel):
    put = os.path.join(KOR, rel)
    if not os.path.isfile(put):
        return None
    with open(put, 'rb') as fh:
        return hashlib.sha256(fh.read()).hexdigest()[:8]


def stranice():
    for koren, dirs, fajlovi in os.walk(KOR):
        dirs[:] = [d for d in dirs if d not in PRESKOCI and not d.startswith('.')]
        for f in sorted(fajlovi):
            if f.endswith(('.html', '.php')):
                yield os.path.join(koren, f)
    # Ikonski CSS iznutra poziva font — i to mora nositi verziju, inace se novi
    # font nikad ne skine iako je CSS osvjezen.
    ikone = os.path.join(KOR, 'fa', 'css', 'mmh-ikone.css')
    if os.path.isfile(ikone):
        yield ikone


CSS_FONT = re.compile(r'url\((\.\./webfonts/[^)?]+\.(?:woff2|ttf))(\?v=[^)]*)?\)')


def prolaz(upisi):
    """Jedan prolaz kroz sve stranice. Vraca (nesklad, izmijenjeni)."""
    nesklad = []
    izmijenjeni = []
    hashevi = {}

    for put in stranice():
        rel_str = os.path.relpath(put, KOR)
        with open(put, encoding='utf-8', errors='replace') as fh:
            tekst = fh.read()
        novi = tekst

        def sredi(m):
            """Vrati isti poziv, ali sa ?v=<hash sadrzaja>."""
            sred = m.group(1)
            h = hashevi.setdefault(sred, hash_fajla(sred))
            if h is None:                    # fajla nema lokalno — ne dira se
                return m.group(0)
            atribut = m.group(0).split('"')[0]     # 'src=' ili 'href='
            return '%s"%s?v=%s"' % (atribut, sred, h)

        novi = SREDSTVO.sub(sredi, novi)

        if rel_str.endswith('mmh-ikone.css'):
            def font(m):
                rel = os.path.normpath(os.path.join('fa/css', m.group(1)))
                h = hashevi.setdefault(rel, hash_fajla(rel))
                if h is None:
                    return m.group(0)
                trenutna = (m.group(2) or '')[3:]
                if trenutna != h:
                    nesklad.append((rel_str, rel, trenutna or '(bez verzije)', h))
                return 'url(%s?v=%s)' % (m.group(1), h)
            novi = CSS_FONT.sub(font, novi)

        # Nesklad za obicne pozive (SREDSTVO) se biljezi ovdje, poredjenjem.
        for m in SREDSTVO.finditer(tekst):
            sred = m.group(1)
            h = hashevi.setdefault(sred, hash_fajla(sred))
            if h is None:
                continue
            trenutna = (m.group(2) or '')[3:]
            if trenutna != h:
                nesklad.append((rel_str, sred, trenutna or '(bez verzije)', h))

        if upisi and novi != tekst:
            with open(put, 'w', encoding='utf-8') as fh:
                fh.write(novi)
            izmijenjeni.append(rel_str)

    return nesklad, izmijenjeni


def main():
    upisi = len(sys.argv) > 1 and sys.argv[1] == 'upisi'
    # Vise prolaza: fa/css/mmh-ikone.css je i sam sredstvo koje se poziva sa
    # ?v=, a njegov hash se promijeni cim se u njemu upisu verzije fontova.
    # Poslije toga su svi pozivi tog CSS-a zastarjeli za jedan korak. Zato se
    # ponavlja dok se stanje ne umiri (najvise 5 puta, da nema vrtnje u krug).
    for _ in range(5):
        nesklad, izmijenjeni = prolaz(upisi)
        if not upisi or not izmijenjeni:
            break

    # Sazetak
    po_sredstvu = {}
    for gdje, sred, staro, novo in nesklad:
        po_sredstvu.setdefault((sred, staro, novo), []).append(gdje)

    if not po_sredstvu:
        print('Sve adrese nose tacan hash sadrzaja. Nema sta da se mijenja.')
        return 0

    print('Adrese koje ne odgovaraju sadrzaju fajla:\n')
    for (sred, staro, novo), gdje in sorted(po_sredstvu.items()):
        print('  %-32s %s -> %s   (%d fajl%s)' % (sred, staro, novo, len(gdje),
                                                  'a' if len(gdje) != 1 else ''))
        for g in sorted(set(gdje))[:3]:
            print('      %s' % g)
        if len(set(gdje)) > 3:
            print('      … i jos %d' % (len(set(gdje)) - 3))
    print()
    if upisi:
        print('Upisano u %d fajl(ova). Deployuj ih.' % len(izmijenjeni))
        return 0
    print('Nista nije promijenjeno. Za upis:  python3 alat/verzije.py upisi')
    return 1


if __name__ == '__main__':
    sys.exit(main())
