# R2 — provjera samog alata

Napravljeno: 2026-08-16 19:23 UTC

Pet stvarnih stranica je prekopirano u lazni sajt i u njih su ubacene greske.
Alat je pusten preko tog sajta. Sve sto nije prijavljeno je **slijepo pravilo**.

## Rezultat

| # | ubacena greska | gdje | alat prijavio? |
|---|---|---|---|
| 1 | noindex u meta robots | `t1.html` | **da** |
| 2 | dvije stranice isti canonical | `t2.html` | **da** |
| 3 | obrisan title | `t3.html` | **da** |
| 4 | obrisan meta opis | `t3.html` | **da** |
| 5 | dva h1 | `t4.html` | **da** |
| 6 | neispravan JSON-LD | `t4.html` | **da** |
| 7 | slika bez alt | `t5.html` | **da** |

[i] Alat je uhvatio svih 7 ubacenih gresaka.

## Izvjestaj koji je alat napravio nad laznim sajtom

```
[!!] 4 verzija NE zavrsava na istoj adresi — Google vidi vise sajtova.
[!] Sitemap nema nijednu sliku.
[!!] Zabrana indeksiranja na 1 mjesta:
[!!] 1 canonical adresa se ponavlja na vise stranica — te stranice ispadaju iz indeksa:
[!] 1 stranica ima canonical koji nije ona sama:
[!!] 1 stranica bez title.
[!] 1 stranica bez meta opisa.
[!] 1 stranica ima vise od jednog H1.
[!!] JSON-LD koji se ne parsira: 1
[!] 1 slika bez `alt` opisa.
[!] 1 stranica je u sitemapu ali do nje ne vodi nijedan interni link:
```
