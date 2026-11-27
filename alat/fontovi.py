#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
SAZIMANJE FONTOVA — zadrzi samo ono sto sajt stvarno koristi.

Zasto:
Font Awesome nosi 2465 ikona (267 kB), a sajt koristi 134. Tekstualni
"latin-ext" fajlovi nose fonetske i prosirene latinicne znakove, a nama iz
njih trebaju samo c c d s z sa kvacicama. Prva posjeta je zbog toga skidala
455 kB fontova. Poslije sazimanja: 150 kB.

Pokretanje:
    python3 alat/fontovi.py            # samo pokaze sta bi uradio
    python3 alat/fontovi.py upisi      # stvarno prepise fajlove

Poslije upisa OBAVEZNO: git commit, push, pa sync (fontovi su u sync listi).

KADA SE POKRECE:
  - kad se na sajt doda ikona koja se do sada nije koristila
    (inace se nece prikazati — font je nema)
  - kad se u tekst uvede slovo van opsega U+0000-024F

Trazi fonttools i brotli:  pip install fonttools brotli
"""
import os, re, sys, glob, json, shutil, subprocess

KORIJEN = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
UPISI = len(sys.argv) > 1 and sys.argv[1].lower() in ('upisi', 'upiši', 'write')

# Sluzbene klase Font Awesome-a — nisu ikone, ne treba ih trazi u fontu
SLUZBENE = {
    'fa-solid', 'fa-brands', 'fa-regular', 'fa-light', 'fa-thin', 'fa-duotone',
    'fa-spin', 'fa-fw', 'fa-xs', 'fa-sm', 'fa-lg', 'fa-2x', 'fa-3x', 'fa-4x',
    'fa-5x', 'fa-6x', 'fa-7x', 'fa-8x', 'fa-9x', 'fa-10x', 'fa-stack',
    'fa-stack-1x', 'fa-stack-2x', 'fa-pull-left', 'fa-pull-right', 'fa-border',
    'fa-inverse', 'fa-rotate-90', 'fa-rotate-180', 'fa-rotate-270',
    'fa-flip-horizontal', 'fa-flip-vertical', 'fa-flip-both', 'fa-spin-pulse',
    'fa-spin-reverse', 'fa-beat', 'fa-beat-fade', 'fa-fade', 'fa-bounce',
    'fa-shake', 'fa-pulse', 'fa-solid-900', 'fa-brands-400', 'fa-regular-400',
}


def sve_datoteke():
    """Svaki fajl u kom moze da stoji ime ikone — ukljucujuci admin."""
    obrasci = ['*.html', '*.php', 'js/*.js', 'admin/*.php', 'css/*.css']
    out = []
    for o in obrasci:
        out += glob.glob(os.path.join(KORIJEN, o))
    return out


def mapa_ikona():
    """Ime ikone -> kod znaka, procitano iz all.min.css (aliasi su grupisani)."""
    css = open(os.path.join(KORIJEN, 'fa/css/all.min.css'), encoding='utf-8').read()
    m = {}
    for sel, kod in re.findall(r'([^{}]+)\{content:"\\([0-9a-f]+)"\}', css):
        for ime in re.findall(r'\.(fa-[a-z0-9-]+):+before', sel):
            m[ime] = kod
    return m


def ikone_sa_servera():
    """Ikone koje vlasnik bira u adminu — one stoje samo na serveru.

    data/categories.json se NE deployuje sa lokalnog (vlasnik ga mijenja kroz
    admin), pa lokalni fajlovi o tim ikonama ne znaju nista. Zbog toga su
    fa-cube i fa-table-columns ispali iz fonta pri sazimanju i dvije ikone
    kategorija su se na sajtu iscrtavale prazno — mjesecima, a da niko ne
    primijeti. Zato se spisak dopunjuje onim sto na serveru stvarno stoji.
    """
    try:
        r = subprocess.run(['curl', '-sk', '--cacert', '/root/.ccr/ca-bundle.crt',
                            '--max-time', '25',
                            'https://makemyhome.me/data/categories.json'],
                           capture_output=True, text=True, timeout=40).stdout
        if len(r) < 50:
            print('   PAZI: categories.json sa servera nije procitan — ikone iz admina nisu uzete')
            return set()
        return set(re.findall(r'"icon"\s*:\s*"[^"]*\b(fa-[a-z0-9-]+)', r))
    except Exception as e:
        print('   PAZI: categories.json sa servera nije procitan (%s)' % e)
        return set()


def koriscene(mapa):
    pom = set()
    for f in sve_datoteke():
        s = open(f, encoding='utf-8', errors='replace').read()
        pom |= set(re.findall(r'\b(fa-[a-z0-9-]+)', s))
    pom |= ikone_sa_servera()
    koris = {i for i in pom if i in mapa}
    nepoznate = {i for i in pom if i not in mapa and i not in SLUZBENE}
    return koris, nepoznate


def sazmi(rel, unicodes, opis):
    put = os.path.join(KORIJEN, rel)
    if not os.path.exists(put):
        print('   nema fajla: ' + rel)
        return

    # Sazima se UVIJEK iz punog originala u fa/webfonts-izvor/, nikad preko
    # vec sazetog fajla.
    #
    # Ranije je alat citao isti fajl koji i prepisuje. Prvo pokretanje je
    # izbacilo sve glifove osim tada koriscenih — i ti glifovi su nestali
    # zauvijek. Kad je kasnije na Decor Box dodato sest novih ikona, cetiri
    # se nisu iscrtale: CSS ih je imao, font vise nije, a ponovno pokretanje
    # alata ih nije moglo vratiti jer ih ni u izvoru nema. Originali su
    # vraceni iz git istorije (commit 117a8d4) i stoje u fa/webfonts-izvor/.
    # Taj folder se NE deployuje — sluzi samo kao izvor pri sazimanju.
    izvor = os.path.join(KORIJEN, 'fa/webfonts-izvor', os.path.basename(rel))
    if not os.path.exists(izvor):
        print('   PAZI: nema originala %s — preskacem %s da ne izgubim jos glifova'
              % (os.path.relpath(izvor, KORIJEN), rel))
        return

    prije = os.path.getsize(put)
    izlaz = put + '.novi'
    r = subprocess.run(['python3', '-m', 'fontTools.subset', izvor,
                        '--unicodes=' + unicodes, '--flavor=woff2',
                        '--layout-features=*', '--no-hinting',
                        '--output-file=' + izlaz],
                       capture_output=True, text=True)
    if r.returncode != 0:
        print('   GRESKA na ' + rel)
        print(r.stderr[-400:])
        return
    poslije = os.path.getsize(izlaz)
    znak = '->' if UPISI else '(samo prikaz)'
    print('   %-46s %6.0f kB %s %5.1f kB   %s'
          % (os.path.basename(rel), prije / 1024, znak, poslije / 1024, opis))
    if UPISI:
        shutil.move(izlaz, put)
    else:
        os.remove(izlaz)


def main():
    mapa = mapa_ikona()
    koris, nepoznate = koriscene(mapa)
    print('ikona definisano u CSS-u: %d' % len(mapa))
    print('ikona koje sajt koristi:  %d' % len(koris))
    if nepoznate:
        print('PAZI — ove klase lice na ikone ali ih nema u CSS-u:')
        for i in sorted(nepoznate):
            print('   ' + i)
    kodovi = sorted({mapa[i] for i in koris})
    uni = ','.join('U+' + k.upper() for k in kodovi)

    print('\nIKONE (Font Awesome):')
    sazmi('fa/webfonts/fa-solid-900.woff2', uni, '%d ikona' % len(kodovi))
    sazmi('fa/webfonts/fa-brands-400.woff2', uni, '%d ikona' % len(kodovi))

    print('\nTEKST (latin-ext varijante — trebaju nam samo c c d s z sa kvacicama):')
    for rel in ('fonts/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa25L7SUc.woff2',
                'fonts/nuFiD-vYSZviVYUb_rj3ij__anPXDTLYgFE_.woff2'):
        sazmi(rel, 'U+0100-024F', 'Latin Extended-A i B')

    if not UPISI:
        print('\nNista nije promijenjeno. Za stvarni upis:  python3 alat/fontovi.py upisi')
    else:
        print('\nUpisano. Sada: git commit, push, pa sync.')


if __name__ == '__main__':
    main()
