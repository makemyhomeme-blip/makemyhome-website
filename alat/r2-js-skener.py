#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
r2-js-skener.py — trazi u JavaScriptu obrasce koji brisu ili kriju sadrzaj
koji je server vec ispisao.

Zasto postoji:
Na /kategorija/bambus-paneli je showSubcategoryGrid() bezuslovno gasio
#products-container sa 39 kartica koje je products.php ispisao. Nijedno
pravilo to nije uhvatilo jer sve gledaju gotov HTML, a ne sta kod radi s njim.

Skenira se svaki .js fajl i svaki <script> unutar .php i .html, i trazi:
  · innerHTML = ...          nad kontejnerom sadrzaja
  · replaceChildren, removeChild, .remove()
  · style.display = 'none', visibility, classList.add('hidden'/'d-none')
  · textContent = ...        nad kontejnerom
  · render/init funkcije zakacene na DOMContentLoaded

Za svaki nalaz se gleda ima li ZASTITU u istoj funkciji — provjeru da li je
server vec nesto ispisao (data-ssr, querySelector('.product-card'), .children
.length, dataset.seo ...). Ako je ima, nalaz je bezopasan.

  python3 alat/r2-js-skener.py   →  R2-JS.md
"""
import json
import os
import re
import sys

KOR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# Kontejneri u koje server pise sadrzaj. Diranje ovih je vrijedno paznje;
# diranje nekog <span> sa brojacem nije.
SADRZAJ = re.compile(
    r"products-container|category-grid|product-info-content|gallery-|"
    r"product-reviews|specs|cat-grid|#products|footer-cats|related|"
    r"breadcrumb|page-title|page-subtitle|cat-title|main-content|results",
    re.I)

# Znaci da kod pazi na ono sto je server vec ispisao.
ZASTITA = re.compile(
    r"dataset\.ssr|data-ssr|dataset\.seo|querySelector\([^)]*product-card|"
    r"querySelectorAll\([^)]*(cat-card|product-card)[^)]*\)\.length|"
    r"children\.length\s*===?\s*0|children\.length\s*>|"
    r"\.length\s*>\s*0\s*\)\s*\{?\s*(initAnimations|return)|"
    r"!\w+\.querySelector|innerHTML\.trim\(\)\s*===?\s*''", re.I)

OBRASCI = [
    ('innerHTML =',      re.compile(r"\.innerHTML\s*=(?!=)")),
    ('textContent =',    re.compile(r"\.textContent\s*=(?!=)")),
    ('replaceChildren',  re.compile(r"\.replaceChildren\s*\(")),
    ('removeChild',      re.compile(r"\.removeChild\s*\(")),
    ('.remove()',        re.compile(r"\.remove\s*\(\s*\)")),
    ("display='none'",   re.compile(r"style\.display\s*=\s*['\"]none['\"]")),
    ('visibility',       re.compile(r"style\.visibility\s*=\s*['\"]hidden['\"]")),
    ('classList hidden', re.compile(r"classList\.add\s*\(\s*['\"](hidden|d-none|is-hidden)['\"]")),
    ('hidden = true',    re.compile(r"\.hidden\s*=\s*true")),
]

FILTER = re.compile(r"\b(allProducts|products|proizvodi)\b\s*\.\s*(filter|slice)\s*\(")
NA_UCITAVANJU = re.compile(r"DOMContentLoaded|window\.onload|\bdefer\b")


def granice_funkcija(linije):
    """Za svaku liniju vrati ime funkcije u kojoj je (grubo, po indentaciji)."""
    ime, mapa = '(vrh fajla)', []
    poc = re.compile(r"^\s*(?:async\s+)?function\s+(\w+)|^\s*(?:const|let|var)\s+(\w+)\s*=\s*(?:async\s*)?\(")
    for ln in linije:
        m = poc.match(ln)
        if m:
            ime = m.group(1) or m.group(2)
        mapa.append(ime)
    return mapa


def tijelo_funkcije(linije, i, mapa):
    """Vrati tekst cijele funkcije u kojoj je linija i — da se u njoj trazi zastita."""
    ime = mapa[i]
    poc = next((j for j in range(i, -1, -1) if mapa[j] != ime), -1) + 1
    kraj = next((j for j in range(i, len(mapa)) if mapa[j] != ime), len(mapa))
    return '\n'.join(linije[poc:kraj]), ime


def skripte_iz(putanja, tekst):
    """Za .js cijeli fajl; za .php/.html samo sadrzaj <script> blokova, sa
       tacnim brojem linije u originalnom fajlu."""
    if putanja.endswith('.js'):
        return [(1, tekst)]
    out = []
    for m in re.finditer(r"<script\b[^>]*>(.*?)</script>", tekst, re.S | re.I):
        if re.search(r'type\s*=\s*["\']application/ld\+json', m.group(0), re.I):
            continue
        if re.search(r'\bsrc\s*=', m.group(0)[:m.group(0).find('>')], re.I):
            continue
        out.append((tekst[:m.start(1)].count('\n') + 1, m.group(1)))
    return out


def koje_stranice(putanja):
    """Gdje se ovaj fajl ucitava — grubo, po tome ko ga ukljucuje."""
    if not putanja.endswith('.js'):
        return [os.path.relpath(putanja, KOR)]
    ime = os.path.relpath(putanja, KOR)
    pog = []
    for koren, _, fajlovi in os.walk(KOR):
        if any(p in koren for p in ('/.git', '/alat', '/admin', '/fa', '/.seo-audit')):
            continue
        for f in fajlovi:
            if not f.endswith(('.html', '.php')):
                continue
            try:
                with open(os.path.join(koren, f), encoding='utf-8', errors='replace') as fh:
                    if ime in fh.read():
                        pog.append(os.path.relpath(os.path.join(koren, f), KOR))
            except OSError:
                pass
    return sorted(pog)


def main():
    mete = []
    for koren, dirs, fajlovi in os.walk(KOR):
        dirs[:] = [d for d in dirs if d not in
                   ('.git', 'alat', 'fa', 'images', 'data', 'node_modules', '.seo-audit-kes')]
        for f in sorted(fajlovi):
            if f.endswith(('.js', '.php', '.html')):
                mete.append(os.path.join(koren, f))

    nalazi = []
    for putanja in mete:
        try:
            with open(putanja, encoding='utf-8', errors='replace') as fh:
                tekst = fh.read()
        except OSError:
            continue
        rel = os.path.relpath(putanja, KOR)
        for pomak, kod in skripte_iz(putanja, tekst):
            linije = kod.split('\n')
            mapa = granice_funkcija(linije)
            for i, ln in enumerate(linije):
                if ln.lstrip().startswith(('//', '*', '/*')):
                    continue
                for naziv, rx in OBRASCI:
                    if not rx.search(ln):
                        continue
                    if not SADRZAJ.search(ln):
                        continue
                    tijelo, fime = tijelo_funkcije(linije, i, mapa)
                    nalazi.append({
                        'fajl': rel,
                        'linija': pomak + i,
                        'funkcija': fime,
                        'obrazac': naziv,
                        'kod': ln.strip()[:150],
                        'zasticeno': bool(ZASTITA.search(tijelo)),
                        'na_ucitavanju': bool(NA_UCITAVANJU.search(kod)),
                    })
            for i, ln in enumerate(linije):
                if FILTER.search(ln) and SADRZAJ.search('\n'.join(linije[max(0, i - 6):i + 6])):
                    tijelo, fime = tijelo_funkcije(linije, i, mapa)
                    nalazi.append({
                        'fajl': rel, 'linija': pomak + i, 'funkcija': fime,
                        'obrazac': 'filter/slice nad listom',
                        'kod': ln.strip()[:150],
                        'zasticeno': bool(ZASTITA.search(tijelo)),
                        'na_ucitavanju': bool(NA_UCITAVANJU.search(kod)),
                    })

    opasni = [n for n in nalazi if not n['zasticeno']]
    bezopasni = [n for n in nalazi if n['zasticeno']]

    L = ['# R2 — obrasci u JavaScriptu koji brisu ili kriju sadrzaj', '']
    L.append('Skenirano: svi `.js` fajlovi i svaki ugradjeni `<script>` u `.php` i `.html`.')
    L.append('')
    L.append('Prijavljuje se samo diranje **kontejnera sadrzaja** (mreza proizvoda, mreza')
    L.append('kategorija, kolona proizvoda, galerija, recenzije, specifikacije, mrvice).')
    L.append('Mijenjanje brojaca, dugmadi i natpisa se ne broji.')
    L.append('')
    L.append(f'- ukupno pogodaka: **{len(nalazi)}**')
    L.append(f'- **bez zastite** (moze pregaziti ono sto je server ispisao): **{len(opasni)}**')
    L.append(f'- sa zastitom: {len(bezopasni)}')
    L.append('')
    L.append('„Zastita" znaci da funkcija u kojoj je linija provjerava da li je server')
    L.append('vec nesto ispisao — `data-ssr`, `querySelector(\'.product-card\')`,')
    L.append('`children.length`, `dataset.seo`.')

    if opasni:
        L += ['', '## Bez zastite — pregledati', '']
        L.append('| fajl | linija | funkcija | obrazac | kod |')
        L.append('|---|---|---|---|---|')
        for n in opasni:
            kod = n['kod'].replace('|', '\\|')
            L.append(f"| `{n['fajl']}` | {n['linija']} | `{n['funkcija']}` | {n['obrazac']} | `{kod}` |")

    if bezopasni:
        L += ['', '## Sa zastitom — bezopasno', '']
        L.append('| fajl | linija | funkcija | obrazac |')
        L.append('|---|---|---|---|')
        for n in bezopasni:
            L.append(f"| `{n['fajl']}` | {n['linija']} | `{n['funkcija']}` | {n['obrazac']} |")

    with open(os.path.join(KOR, 'R2-JS.md'), 'w', encoding='utf-8') as fh:
        fh.write('\n'.join(L) + '\n')
    with open(os.path.join(KOR, 'R2-JS.json'), 'w', encoding='utf-8') as fh:
        json.dump(nalazi, fh, ensure_ascii=False, indent=1)
    print(f'Gotovo → R2-JS.md  (bez zastite {len(opasni)}, sa zastitom {len(bezopasni)})')
    return 0


if __name__ == '__main__':
    sys.exit(main())
