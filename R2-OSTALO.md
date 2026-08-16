# R2 — ostalo

Provjereno 149 adresa iz sitemapa.

## 1. lang i hreflang

[i] Svaka stranica ima `lang`.
[i] `lang="sr-ME"` na 149 stranica
[i] hreflang: 0 stranica. Sajt je jednojezican, pa hreflang nije potreban.

## 2. Kosa crta na kraju adrese

- interni linkovi BEZ kose crte na kraju: **6269**
- interni linkovi SA kosom crtom na kraju: **0**

[i] Nijedan interni link nema kosu crtu na kraju — dosljedno.

## 3. Schema na /kategorija/*

| kategorija | BreadcrumbList | ItemList | CollectionPage | broj Offer |
|---|---|---|---|---|
| `/kategorija/3d-letvice` | da | da | – | 20 |
| `/kategorija/akusticni-paneli` | da | da | – | 9 |
| `/kategorija/aluminijum-lajsne` | da | da | – | 8 |
| `/kategorija/bambus-drveni` | da | da | – | 9 |
| `/kategorija/bambus-kozni` | da | da | – | 4 |
| `/kategorija/bambus-mermerni` | da | da | – | 13 |
| `/kategorija/bambus-metalni` | da | da | – | 4 |
| `/kategorija/bambus-paneli` | da | da | – | 20 |
| `/kategorija/bambus-tekstilni` | da | da | – | 9 |
| `/kategorija/classic` | da | da | – | 4 |
| `/kategorija/flex-stone` | da | da | – | 10 |
| `/kategorija/mdf` | da | da | – | 5 |
| `/kategorija/pu-kamen` | da | da | – | 9 |
| `/kategorija/spc-pod` | da | da | – | 5 |

[i] Sve kategorije imaju i BreadcrumbList i ItemList.

## 4. Schema na /paneli/*

- proizvoda: **117**
- [i] bez `Product`: 0
- [i] bez `Offer`: 0
- [i] bez cijene u ponudi: 0
- [i] bez dostupnosti u ponudi: 0

## 5. robots.txt i sredstva za iscrtavanje

```
User-agent: *
Allow: /
Disallow: /admin/
Disallow: /php/
Disallow: /wp-login.php

Sitemap: https://makemyhome.me/sitemap.xml

# AI/LLM opis sajta
# https://makemyhome.me/llms.txt
```
[i] Nista od `/js`, `/css`, `/images`, `/fa`, `/assets`, `/data` nije blokirano.
- `/js/products.js` → 200
- `/css/style-v5.css` → 200
- `/data/products.json` → 200

## 6. Rich Results Test i Search Console

- **Rich Results Test** nema javni API. Google nudi samo web alat
  (`search.google.com/test/rich-results`) i on trazi prijavu, pa se ne moze
  pozvati iz skripte. Zamjena koju ovaj alat radi: parsira svaki JSON-LD blok,
  provjerava tipove i obavezna polja (sekcije 3 i 4 iznad). To hvata sve sto
  Rich Results Test prijavljuje kao gresku, osim Googleovih internih pravila.
- **Search Console API** trazi OAuth pristup vlasnika naloga. U ovom okruzenju
  ga nema, pa se Crawl Stats ne moze povuci. To ostaje na vama u pregledacu:
  Settings → Crawl stats.
