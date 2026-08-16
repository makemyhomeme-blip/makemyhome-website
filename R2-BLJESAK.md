# R2 — bljesak, skok rasporeda i mobilni prikaz

Napravljeno: 2026-08-16 19:40 UTC

Svaka kategorija je snimljena na 200, 600, 1500 i 3000 ms od pocetka ucitavanja,
na mobilnom ekranu 412×915, sa Googlebot user-agentom. Snimci su u `alat/snimci/`.

**Bljesak** = broj VIDLJIVIH kartica proizvoda poraste pa padne. Tacno to se
desavalo na bambusu prije popravke: 39 kartica se iscrta, pa nestane.

- kategorija sa bljeskom: **0**
- kategorija sa CLS iznad 0,1 (Googleov prag): **1**
- kategorija sa LCP iznad 2,5 s: **0**

| kategorija | kartice 200/600/1500/3000 ms | tekst 200→3000 ms | CLS | LCP | ocjena |
|---|---|---|---|---|---|
| `/kategorija/bambus-paneli` | 39 / 39 / 39 / 39 | 7388 → 7828 | 0.517 | 308 ms | [!] skace raspored |
| `/kategorija/bambus-drveni` | 9 / 9 / 9 / 9 | 3667 → 3667 | 0.054 | 248 ms | [i] uredu |
| `/kategorija/bambus-tekstilni` | 9 / 9 / 9 / 9 | 4235 → 4235 | 0.051 | 236 ms | [i] uredu |
| `/kategorija/bambus-mermerni` | 13 / 13 / 13 / 13 | 4248 → 4248 | 0.050 | 212 ms | [i] uredu |
| `/kategorija/bambus-metalni` | 4 / 4 / 4 / 4 | 3244 → 3244 | 0.050 | 200 ms | [i] uredu |
| `/kategorija/bambus-kozni` | 4 / 4 / 4 / 4 | 3183 → 3183 | 0.050 | 216 ms | [i] uredu |
| `/kategorija/3d-letvice` | 28 / 28 / 28 / 28 | 6505 → 6505 | 0.001 | 208 ms | [i] uredu |
| `/kategorija/akusticni-paneli` | 9 / 9 / 9 | 4825 → 4825 | 0.049 | 1228 ms | [i] uredu |
| `/kategorija/aluminijum-lajsne` | 8 / 8 / 8 / 8 | 4128 → 4128 | 0.001 | 952 ms | [i] uredu |
| `/kategorija/spc-pod` | 5 / 5 / 5 | 4527 → 4527 | 0.050 | 2452 ms | [i] uredu |
| `/kategorija/pu-kamen` | 9 / 9 / 9 / 9 | 4693 → 4693 | 0.050 | 188 ms | [i] uredu |
| `/kategorija/classic` | 4 / 4 / 4 / 4 | 3302 → 3302 | 0.050 | 176 ms | [i] uredu |
| `/kategorija/mdf` | 5 / 5 / 5 / 5 | 3926 → 3926 | 0.073 | 220 ms | [i] uredu |
| `/kategorija/flex-stone` | 10 / 10 / 10 / 10 | 4998 → 4998 | 0.053 | 200 ms | [i] uredu |

## Lazy loading

Googlebot ne skroluje. `loading="lazy"` na slici ne krije samu karticu — kartica
je u HTML-u — ali slika koja se nikad ne ucita ne moze uci u Google Slike.

| kategorija | slika sa lazy | slika bez izvora poslije 3 s |
|---|---|---|
| `/kategorija/bambus-paneli` | 45 | 0 |
| `/kategorija/bambus-drveni` | 17 | 0 |
| `/kategorija/bambus-tekstilni` | 17 | 0 |
| `/kategorija/bambus-mermerni` | 21 | 0 |
| `/kategorija/bambus-metalni` | 12 | 0 |
| `/kategorija/bambus-kozni` | 12 | 0 |
| `/kategorija/3d-letvice` | 36 | 0 |
| `/kategorija/akusticni-paneli` | 17 | 0 |
| `/kategorija/aluminijum-lajsne` | 16 | 0 |
| `/kategorija/spc-pod` | 13 | 0 |
| `/kategorija/pu-kamen` | 17 | 0 |
| `/kategorija/classic` | 12 | 0 |
| `/kategorija/mdf` | 13 | 0 |
| `/kategorija/flex-stone` | 18 | 0 |
