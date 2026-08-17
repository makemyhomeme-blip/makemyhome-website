# R2 — bljesak, skok rasporeda i mobilni prikaz

Napravljeno: 2026-08-17 18:43 UTC

Svaka kategorija je snimljena na 200, 600, 1500 i 3000 ms od pocetka ucitavanja,
na mobilnom ekranu 412×915, sa Googlebot user-agentom. Snimci su u `alat/snimci/`.

**Bljesak** = broj VIDLJIVIH kartica proizvoda poraste pa padne. Tacno to se
desavalo na bambusu prije popravke: 39 kartica se iscrta, pa nestane.

- kategorija sa bljeskom: **0**
- kategorija sa CLS iznad 0,1 (Googleov prag): **0**
- kategorija sa LCP iznad 2,5 s: **1**

| kategorija | kartice 200/600/1500/3000 ms | tekst 200→3000 ms | CLS | LCP | ocjena |
|---|---|---|---|---|---|
| `/kategorija/bambus-paneli` | 39 / 39 / 39 / 39 | 7815 → 7815 | 0.001 | 288 ms | [i] uredu |
| `/kategorija/bambus-drveni` | 9 / 9 / 9 / 9 | 3691 → 3691 | 0.004 | 212 ms | [i] uredu |
| `/kategorija/bambus-tekstilni` | 9 / 9 / 9 / 9 | 4249 → 4249 | 0.001 | 188 ms | [i] uredu |
| `/kategorija/bambus-mermerni` | 13 / 13 / 13 / 13 | 4242 → 4242 | 0.001 | 188 ms | [i] uredu |
| `/kategorija/bambus-metalni` | 4 / 4 / 4 / 4 | 3258 → 3258 | 0.001 | 196 ms | [i] uredu |
| `/kategorija/bambus-kozni` | 4 / 4 / 4 / 4 | 3194 → 3194 | 0.001 | 192 ms | [i] uredu |
| `/kategorija/3d-letvice` | 28 / 28 / 28 / 28 | 6522 → 6522 | 0.001 | 176 ms | [i] uredu |
| `/kategorija/akusticni-paneli` | 9 / 9 / 9 | 4817 → 4817 | 0.001 | 660 ms | [i] uredu |
| `/kategorija/aluminijum-lajsne` | 8 / 8 / 8 / 8 | 4143 → 4143 | 0.001 | 1000 ms | [i] uredu |
| `/kategorija/spc-pod` | 5 / 5 / 5 | 4080 → 4080 | 0.001 | 3344 ms | [i] uredu |
| `/kategorija/pu-kamen` | 9 / 9 / 9 / 9 | 4687 → 4687 | 0.002 | 192 ms | [i] uredu |
| `/kategorija/classic` | 4 / 4 / 4 / 4 | 3316 → 3316 | 0.001 | 148 ms | [i] uredu |
| `/kategorija/mdf` | 5 / 5 / 5 / 5 | 3926 → 3926 | 0.025 | 180 ms | [i] uredu |
| `/kategorija/flex-stone` | 10 / 10 / 10 / 10 | 4998 → 4998 | 0.005 | 184 ms | [i] uredu |

## Lazy loading

Googlebot ne skroluje. `loading="lazy"` na slici ne krije samu karticu — kartica
je u HTML-u — ali slika koja se nikad ne ucita ne moze uci u Google Slike.

| kategorija | slika sa lazy | slika bez izvora poslije 3 s |
|---|---|---|
| `/kategorija/bambus-paneli` | 45 | 0 |
| `/kategorija/bambus-drveni` | 9 | 0 |
| `/kategorija/bambus-tekstilni` | 9 | 0 |
| `/kategorija/bambus-mermerni` | 13 | 0 |
| `/kategorija/bambus-metalni` | 4 | 0 |
| `/kategorija/bambus-kozni` | 4 | 0 |
| `/kategorija/3d-letvice` | 28 | 0 |
| `/kategorija/akusticni-paneli` | 9 | 0 |
| `/kategorija/aluminijum-lajsne` | 8 | 0 |
| `/kategorija/spc-pod` | 5 | 0 |
| `/kategorija/pu-kamen` | 9 | 0 |
| `/kategorija/classic` | 4 | 0 |
| `/kategorija/mdf` | 5 | 0 |
| `/kategorija/flex-stone` | 10 | 0 |
