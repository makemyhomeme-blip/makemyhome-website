#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
DOK NE BUDE — pokrece provjeru iznova dok sajt ne bude onakav kakav pise u
alat/ETALON.md, i ne vjeruje nijednoj gresci dok se ne ponovi.

Zasto ovo postoji
-----------------
Provjera je znala da javi gresku koja nije greska. E2 je jednom prijavio
pokvarenu adresu; ponovno pokretanje je dalo 397 adresa i nula gresaka.
Uzrok je bio jedan ispad shared hostinga pod naletom od 400 zahtjeva.

Takva lazna greska je gora od nikakve: salje covjeka da popravlja ono sto
nije pokvareno, a pravu gresku sakrije u sumu. Zato ovdje vazi jedno pravilo:

    KVAR JE KVAR SAMO AKO SE PONOVI.

Kako radi
---------
1. Pusti se cijela provjera.
2. Ako nesto padne, NE prijavljuje se odmah — pusti se jos jednom, samo te
   grupe. Sto prezivi drugi prolaz, prezivi i treci — to je prava greska.
   Sto nestane, bio je ispad mreze i nikad se ne prikaze kao greska.
3. Prave greske se ispisu i izlazi se sa kodom 1 — ima se sta popravljati.
4. Kad prodje bez ijedne prave greske, izlazi se sa 0: sajt je po etalonu.

Upotreba
--------
    python3 alat/dok-ne-bude.py          # brza pravila (~3 min)
    python3 alat/dok-ne-bude.py sve      # sva pravila, ukljucujuci spora
    python3 alat/dok-ne-bude.py sve 5    # najvise 5 krugova umjesto 3
"""
import re
import subprocess
import sys
import os

KORIJEN = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PROVJERA = os.path.join(KORIJEN, 'alat', 'provjera.py')

# Slovo grupe -> pravila koja ta grupa nosi. Kad padne G4, ponovo se pusti
# samo grupa G, ne cijela provjera — potvrda kosta minut umjesto deset.
GRUPA_OD = lambda sifra: sifra[0]


def pusti(izbor):
    """Pokrene provjeru i vrati (cio ispis, {sifra: opis} palih pravila)."""
    r = subprocess.run([sys.executable, PROVJERA, izbor],
                       capture_output=True, text=True, errors='replace')
    ispis = r.stdout + r.stderr
    pali = {}
    for m in re.finditer(r'^\s*PAD\s+(\S+)\s+(.*?)\s+\(\d+\)\s*$', ispis, re.M):
        pali[m.group(1)] = m.group(2)
    return ispis, pali


def detalji(ispis, sifre):
    """Izvuce redove sa detaljima (· ...) za data pravila."""
    out = []
    for s in sifre:
        m = re.search(r'^PAD %s\s+(.*)$((?:\n\s+·.*)*)' % re.escape(s), ispis, re.M)
        if m:
            out.append('PAD %-5s %s%s' % (s, m.group(1).strip(), m.group(2)))
    return out


def main():
    izbor = sys.argv[1] if len(sys.argv) > 1 else 'brzo'
    maks = int(sys.argv[2]) if len(sys.argv) > 2 else 3

    for krug in range(1, maks + 1):
        print('\n' + '=' * 66)
        print('KRUG %d — pustam provjeru (%s)' % (krug, izbor))
        print('=' * 66)
        ispis, pali = pusti(izbor)
        print(ispis)

        if not pali:
            print('\n' + '=' * 66)
            print('SAJT JE PO ETALONU — nijedno pravilo nije palo.')
            print('=' * 66)
            return 0

        # Nista se ne prijavljuje dok se ne potvrdi drugim prolazom.
        print('\n--- palo %d, provjeravam da nije bio ispad mreze: %s'
              % (len(pali), ', '.join(sorted(pali))))
        grupe = ''.join(sorted({GRUPA_OD(s) for s in pali}))
        ispis2, pali2 = pusti(grupe)

        stvarne = {s: o for s, o in pali2.items() if s in pali}
        nestale = sorted(set(pali) - set(pali2))
        if nestale:
            print('--- bio ispad, ne greska (prezivjelo nije): %s' % ', '.join(nestale))

        if not stvarne:
            print('\n' + '=' * 66)
            print('SAJT JE PO ETALONU — sve sto je palo bio je trenutni ispad,')
            print('nijedna greska nije prezivjela ponovni prolaz.')
            print('=' * 66)
            return 0

        print('\n' + '=' * 66)
        print('PRAVE GRESKE (prezivjele dva prolaza): %d' % len(stvarne))
        print('=' * 66)
        for red in detalji(ispis2, sorted(stvarne)):
            print(red)
        print('\nOve se moraju popraviti u kodu — ponavljanje ih nece skloniti.')
        return 1

    return 1


if __name__ == '__main__':
    sys.exit(main())
