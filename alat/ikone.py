#!/usr/bin/env python3
"""
Pravi fa/css/mmh-ikone.css — Font Awesome sa samo onim ikonama koje sajt koristi.

Zasto:
fa/css/all.min.css je 102 kB i opisuje 1870 ikona. Sajt koristi 133. Taj fajl
je u <head> i blokira iscrtavanje — pregledac ne prikaze nista dok ga ne
preuzme i obradi. Na telefonu, odakle dolazi najvise posjeta, to je cist gubitak.

Kako:
Zadrzava se SVE osim pravila oblika `.fa-ime:before{content:"\fxxx"}` za ikone
koje se nigdje ne pojavljuju. Osnovna pravila, @font-face blokovi, velicine i
animacije ostaju nedirnuti, pa se ponasanje ne mijenja.

Zasto skripta a ne rucno napravljen fajl:
Kad neko doda novu ikonu na stranicu, rucno skraceni CSS je ne bi imao i ikona
bi se prikazala kao prazan kvadratic — a niko ne bi znao zasto. Ovako se fajl
napravi ponovo iz koda, a `alat/provjera.py` (pravilo C6) prijavi ako je neka
koriscena ikona ostala van fajla.

Pokretanje:
    python3 alat/ikone.py            # napravi fajl i ispisi ustedu
    python3 alat/ikone.py provjeri   # samo provjeri, nista ne upisuje
"""
import os
import re
import sys
import gzip

KORIJEN = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
IZVOR = os.path.join(KORIJEN, 'fa', 'css', 'all.min.css')
CILJ = os.path.join(KORIJEN, 'fa', 'css', 'mmh-ikone.css')

# Folderi koji se ne pretrazuju: fa/ je sam izvor (u njemu su SVE ikone, pa bi
# se pronaslo 1870 imena), node_modules i git nisu dio sajta.
PRESKOCI = {'fa', 'node_modules', '.git', 'alat', 'images', 'fonts'}
# data/ se NE preskace: data/categories.json cuva ime ikone za svaku kategoriju
# i podkategoriju, a vlasnik ih bira kroz admin. Bez ovoga bi izbor nove ikone
# u adminu dao prazan kvadratic na sajtu.
NASTAVCI = ('.html', '.php', '.js', '.json')

# Nisu ikone nego modifikatori (velicina, rotacija, fiksna sirina)
# Imena fajlova sa fontovima (fa-solid-900.woff2) izgledaju kao imena ikona kad
# se trazi obrazac "fa-nesto", a stoje u <link rel=preload>. Nisu ikone.
NIJE_FAJL = re.compile(r'^fa-(solid-900|regular-400|brands-400|v4compatibility)$')

NIJE_IKONA = re.compile(
    r'^fa-(solid|regular|brands|light|thin|duotone|v4compatibility|fw|border|'
    r'pull-left|pull-right|spin|pulse|beat|fade|flip|shake|bounce|inverse|stack|'
    r'stack-1x|stack-2x|ul|li|rotate-\d+|flip-\w+|beat-fade|spin-reverse|spin-pulse|'
    r'2xs|xs|sm|lg|xl|2xl|1x|2x|3x|4x|5x|6x|7x|8x|9x|10x)$'
)


def koriscene_ikone():
    """Prodji kroz stranice i skripte i pokupi svako fa-ime koje se pojavi."""
    nadjene = set()
    for koren, folderi, fajlovi in os.walk(KORIJEN):
        folderi[:] = [f for f in folderi if f not in PRESKOCI and not f.startswith('.')]
        for ime in fajlovi:
            if not ime.endswith(NASTAVCI):
                continue
            put = os.path.join(koren, ime)
            try:
                sadrzaj = open(put, encoding='utf-8', errors='replace').read()
            except OSError:
                continue
            for m in re.findall(r'\bfa-([a-z0-9][a-z0-9-]*)', sadrzaj):
                if not NIJE_IKONA.match('fa-' + m) and not NIJE_FAJL.match('fa-' + m):
                    nadjene.add(m)
    return nadjene


def imena_pravila(pravilo):
    return set(re.findall(r'\.fa-([a-z0-9-]+)(?=:|,|\{)', pravilo))


def napravi():
    css = open(IZVOR, encoding='utf-8').read()
    used = koriscene_ikone()

    # Sva pravila koja samo dodjeljuju znak ikoni — jedino se ona izbacuju
    pravila = re.findall(r'\.fa-[a-z0-9-]+(?:[:,][^{]*)?\{content:"\\[0-9a-f]+"[^}]*\}', css)
    izbaci = [p for p in pravila if not (imena_pravila(p) & used)]

    novi = css
    for p in izbaci:
        novi = novi.replace(p, '', 1)

    # Koje od koriscenih ikona uopste ne postoje u Font Awesome-u
    ima = set()
    for p in pravila:
        ima |= imena_pravila(p)
    fale = sorted(used - ima)

    return novi, css, used, ima, fale, len(pravila) - len(izbaci)


def kb(n):
    return '%.1f kB' % (n / 1024)


def gz(tekst):
    return len(gzip.compress(tekst.encode('utf-8'), 9))


def main():
    samo_provjera = len(sys.argv) > 1 and sys.argv[1] == 'provjeri'
    novi, stari, used, ima, fale, zadrzano = napravi()

    print('koriscenih ikona na sajtu : %d' % len(used))
    print('zadrzanih pravila u CSS-u : %d (od %d)' % (zadrzano, len(re.findall(r'\{content:"\\', stari))))
    print('velicina: %s -> %s   (gzip %s -> %s)'
          % (kb(len(stari)), kb(len(novi)), kb(gz(stari)), kb(gz(novi))))

    if fale:
        print('\nUPOZORENJE: %d ikona se koristi na sajtu ali ne postoji u Font Awesome-u —' % len(fale))
        print('prikazace se kao prazan kvadratic:')
        for f in fale:
            print('   fa-%s' % f)

    if samo_provjera:
        return 1 if fale else 0

    with open(CILJ, 'w', encoding='utf-8') as f:
        f.write(novi)
    print('\nupisano: %s' % os.path.relpath(CILJ, KORIJEN))
    return 1 if fale else 0


if __name__ == '__main__':
    sys.exit(main())
