#!/usr/bin/env python3
"""Sredi Lighthouse rezultate u citljiv izvjestaj i reci sta je stvarna greska."""
import json, glob, os, sys, collections

# Ovo nisu greske na sajtu nego posljedica testnog okruzenja:
#   korpa i placanje su NAMJERNO noindex — to i treba da budu
#   canonical prijavljuje posrednik jer prepise adresu na 127.0.0.1
#   "browser errors" su slike kojih nema lokalno (images/ se ne drzi u gitu)
OCEKIVANO = {
    ('is-crawlable', 'korpa'), ('is-crawlable', 'placanje'),
    ('canonical', 'pocetna'),
}
ZANEMARI = {'errors-in-console', 'inspector-issues'}

mapa = sys.argv[1] if len(sys.argv) > 1 else '.'
rez, pale = {}, collections.defaultdict(list)
for f in sorted(glob.glob(os.path.join(mapa, '*.json'))):
    ime = os.path.basename(f).replace('.json', '')
    d = json.load(open(f))
    rez[ime] = {k: (round(v['score'] * 100) if v.get('score') is not None else None)
                for k, v in d['categories'].items()}
    for kljuc, a in d['audits'].items():
        if kljuc in ZANEMARI:
            continue
        if a.get('scoreDisplayMode') in ('notApplicable', 'manual', 'informative'):
            continue
        if a.get('score') is not None and a['score'] < 1 and (kljuc, ime) not in OCEKIVANO:
            pale[(kljuc, a['title'])].append(ime)

print('%-12s %5s %9s %8s' % ('stranica', 'SEO', 'pristup', 'praksa'))
for k, v in rez.items():
    print('%-12s %5s %9s %8s' % (k, v.get('seo'), v.get('accessibility'), v.get('best-practices')))

if pale:
    print('\nSTVARNE GRESKE:')
    for (kljuc, naslov), gdje in sorted(pale.items(), key=lambda x: -len(x[1])):
        print('  PAD  %-46s %d/%d — %s' % (naslov[:46], len(gdje), len(rez), ', '.join(gdje)))
    sys.exit(1)
print('\nZAVRSNO: nijedna stvarna greska na %d tipova stranica' % len(rez))
