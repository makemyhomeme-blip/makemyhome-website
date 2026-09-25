# R3 — nezavisna provjera indeksabilnosti i uskladjenosti

Mjereno na zivom sajtu. Ne oslanja se ni na jedan raniji izvjestaj.

Sitemap: **149** adresa.

## 1. Indeksabilnost — presuda po adresi

- **INDEXABLE: 149/149**
- NOT INDEXABLE: 0/149

[i] Svaka adresa iz sitemapa je indeksabilna: 200 bez skoka, dozvoljena u
robots.txt, bez noindex u HTML-u i u zaglavlju, sa canonical-om na samu sebe.

## 2. Lanac: SITEMAP → CANONICAL → HTTP 200 → INDEXABLE

**149/149** adresa je potpuno uskladjeno.

[i] Nijedna adresa iz sitemapa ne zavrsava preusmjerenjem, ne pokazuje
canonical na drugu stranicu, nema noindex i nije zabranjena u robots.txt.

## 3. Preusmjerenja

| proba | prvi status | skokova | zavrsava na | konacni status |
|---|---|---|---|---|
| http → https | 301 | 1 | `/` | 200 |
| http + www | 301 | 1 | `/` | 200 |
| www → non-www | 301 | 1 | `/` | 200 |
| index.html → / | 301 | 1 | `/` | 200 |
| kosa crta na kraju | 301 | 1 | `/paneli/3d-letvica-obsidian` | 200 |
| velika slova | 301 | 1 | `/paneli/3d-letvica-obsidian` | 200 |
| stari ?id= | 301 | 1 | `/paneli/3d-letvica-obsidian` | 200 |
| stari ?category= | 301 | 1 | `/kategorija/mdf` | 200 |
| stari WordPress /shop/ | 301 | 1 | `/products.html` | 200 |
| stari WordPress /product/ | 301 | 1 | `/paneli/akusticni-panel-aku053-zlatni-bambus-linear` | 200 |

[i] petlji u preusmjerenjima: 0

## 5. robots.txt

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

| putanja | Googlebot | Bingbot | ostali |
|---|---|---|---|
| `/paneli/3d-letvica-obsidian` | da | da | da |
| `/kategorija/mdf` | da | da | da |
| `/products.html` | da | da | da |
| `/js/products.js` | da | da | da |
| `/css/style-v5.css` | da | da | da |
| `/images/products/cq006.jpg` | da | da | da |
| `/fa/css/mmh-ikone.css` | da | da | da |
| `/data/products.json` | da | da | da |
| `/admin/` | **NE** | **NE** | **NE** |
| `/php/slug.php` | **NE** | **NE** | **NE** |

[i] sitemap prijavljen u robots.txt: da

## 6. Pokrivenost sitemapa

| vrsta | postoji | u sitemapu | fali |
|---|---|---|---|
| proizvodi | 117 | 117 | 0 |
| kategorije | 14 | 14 | 0 |
| ostale stranice | – | 18 | – |
| **ukupno** | – | **149** | – |

## 16. Zaglavlja odgovora (zivi sajt)

| sredstvo | Content-Type | Cache-Control | ETag | Last-Modified | Content-Encoding |
|---|---|---|---|---|---|
| `/` | text/html; charset=utf-8 | public, max-age=0, must-revalidate | – | – | – |
| `/kategorija/mdf` | text/html; charset=UTF-8 | public, max-age=0, must-revalidate | – | – | – |
| `/paneli/3d-letvica-obsidian` | text/html; charset=UTF-8 | public, max-age=0, must-revalidate | – | – | – |
| `/js/products.js` | text/javascript | public, max-age=31536000, immutable | – | da | gzip |
| `/css/style-v5.css` | text/css | public, max-age=31536000, immutable | – | da | gzip |
| `/data/products.json` | application/json | public, max-age=0, must-revalidate | – | da | gzip |
| `/images/products/cq006.jpg` | image/jpeg | public, max-age=2592000 | – | da | – |
| `/sitemap.xml` | application/xml; charset=utf-8 | public, max-age=0, must-revalidate | – | da | – |
| `/robots.txt` | text/plain | – | – | da | – |

## 18. Title, opis, H1 na svih 149

| provjera | rezultat |
|---|---|
| bez title | 0 |
| duplirani title | 0 |
| bez meta opisa | 0 |
| duplirani meta opis | 0 |
| bez H1 | 0 |
| vise od jednog H1 | 0 |

## 19. Deset najvecih slika (onako kako ih pregledac dobije)

Razlicitih slika na sajtu: **245**

| slika | kB (sa Accept: webp) | tip |
|---|---|---|
| `images/products/product-1774008688-517.jpg` | 534 | image/webp |
| `images/products/product-1774006410-646.jpg` | 496 | image/webp |
| `images/products/product-1774006317-836.jpg` | 496 | image/webp |
| `images/products/product-1774006266-913.jpg` | 377 | image/webp |
| `images/products/product-1774006512-598.jpg` | 313 | image/webp |
| `images/products/product-1774006203-686.jpg` | 283 | image/webp |
| `images/products/product-1775308294-360.jpg` | 224 | image/webp |
| `images/products/mw321.jpg` | 219 | image/webp |
| `images/products/product-1774011402-320.jpg` | 207 | image/webp |
| `images/products/product-1774009566-250.jpg` | 205 | image/webp |

## 15. LOKALNO / GITHUB / ZIVO — kriticni fajlovi

Fajl koji se servira takav kakav jeste (js, css, txt) poredi se po hasu
sadrzaja. PHP se preko weba ne moze skinuti u izvornom obliku, pa se cita
sa servera kroz cPanel i poredi po VELICINI — cPanel sadrzaj normalizuje
(spoji neke redove), pa hash ne bi bio uporediv, a velicina dolazi sa diska.
Alati u `alat/` se ne deployuju, za njih vazi samo LOKALNO = GITHUB.

| fajl | lokalno | GitHub | zivo | poklapa se |
|---|---|---|---|---|
| `js/products.js` | a373a25d | a373a25d | a373a25d | da |
| `product.php` | d37e12b1 (67014 B) | d37e12b1 | 67014 B | da |
| `products.php` | 5294f86d (88325 B) | 5294f86d | 88325 B | da |
| `robots.txt` | 94503e43 | 94503e43 | 94503e43 | da |
| `sitemap.php` | c1e91a29 (10381 B) | c1e91a29 | 10381 B | da |
| `php/slug.php` | d083c85d (4229 B) | d083c85d | 4229 B | da |
| `alat/verzije.py` | 6fd4d4e8 | 6fd4d4e8 | (ne deployuje se) | da |
| `css/style-v5.css` | 86d9ada3 | 86d9ada3 | 86d9ada3 | da |

## 12 i 13. Googlebot naspram obicnog korisnika naspram mobilnog

Isti zahtjev, tri user-agenta. Ako se sadrzaj razlikuje, to je cloaking —
bilo namjeran ili slucajan, Google ga tretira isto.

| adresa | title | canonical | robots | h1 | LD | Product | cijene | slike | linkovi | isto? |
|---|---|---|---|---|---|---|---|---|---|---|
| `/` | da | da | – | 1 | 4 | – | 1 | 18 | 45 | da |
| `/kategorija/bambus-paneli` | da | da | – | 1 | 2 | da | 5 | 47 | 81 | da |
| `/kategorija/bambus-drveni` | da | da | – | 1 | 2 | da | 2 | 11 | 50 | da |
| `/paneli/drveni-panel-golden-teak` | da | da | – | 1 | 2 | da | 4 | 13 | 51 | da |
| `/paneli/drveni-panel-mocha-oak` | da | da | – | 1 | 2 | da | 4 | 14 | 51 | da |
| `/paneli/drveni-panel-havana-oak` | da | da | – | 1 | 2 | da | 4 | 12 | 51 | da |
| `/paneli/drveni-panel-honey-oak` | da | da | – | 1 | 2 | da | 4 | 12 | 51 | da |
| `/paneli/drveni-panel-espresso-teak` | da | da | – | 1 | 2 | da | 4 | 15 | 51 | da |
| `/paneli/drveni-panel-nordic-oak` | da | da | – | 1 | 2 | da | 4 | 13 | 51 | da |
| `/paneli/drveni-panel-amber-oak` | da | da | – | 1 | 2 | da | 3 | 12 | 50 | da |
| `/paneli/drveni-panel-smoke-oak` | da | da | – | 1 | 2 | da | 3 | 12 | 50 | da |
| `/paneli/drveni-panel-dark-ash` | da | da | – | 1 | 2 | da | 3 | 11 | 50 | da |
| `/paneli/tekstilni-panel-perla` | da | da | – | 1 | 2 | da | 4 | 13 | 51 | da |

[i] Svih 13 stranica daje **identican** sadrzaj Googlebotu, obicnom
pregledacu i mobilnom Googlebotu — u naslovu, canonical-u, robots-u, broju H1,
JSON-LD blokova, cijena, slika i linkova, i u duzini HTML-a.

## 17. Zbirna tabela signala

| adresa | u sitemapu | canonical = adresa | HTTP | robots.txt | noindex | H1 | konacno |
|---|---|---|---|---|---|---|---|
| `/` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/products.html` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/cjenovnik.html` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/inspiracija.html` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/montaza.html` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/faq.html` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/about.html` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/contact.html` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/decor-box.html` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli-za-kupatilo.html` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/tv-zid.html` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli-ili-lamperija.html` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/spc-ili-laminat.html` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/akusticni-paneli-kancelarija.html` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/dostava-crna-gora.html` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/uslovi.html` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/reklamacije.html` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/privatnost.html` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/kategorija/bambus-paneli` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/kategorija/bambus-drveni` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/kategorija/bambus-tekstilni` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/kategorija/bambus-mermerni` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/kategorija/bambus-metalni` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/kategorija/bambus-kozni` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/kategorija/3d-letvice` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/kategorija/akusticni-paneli` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/kategorija/aluminijum-lajsne` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/kategorija/spc-pod` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/kategorija/pu-kamen` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/kategorija/classic` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/kategorija/mdf` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/kategorija/flex-stone` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/drveni-panel-golden-teak` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/drveni-panel-mocha-oak` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/drveni-panel-havana-oak` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/drveni-panel-honey-oak` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/drveni-panel-espresso-teak` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/drveni-panel-nordic-oak` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/drveni-panel-amber-oak` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/drveni-panel-smoke-oak` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/drveni-panel-dark-ash` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/tekstilni-panel-perla` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/tekstilni-panel-calla` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/tekstilni-panel-grigio` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/tekstilni-panel-glacier` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/tekstilni-panel-slate` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/tekstilni-panel-blanc` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/tekstilni-panel-siena` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/tekstilni-panel-pura` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/tekstilni-panel-deva` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/mermerni-panel-mystic-marble` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/mermerni-panel-desert-stone` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/mermerni-panel-travertino` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/mermerni-panel-mercury-marble` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/mermerni-panel-sahara` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/mermerni-panel-beige-marble` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/mermerni-panel-lava-stone` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/mermerni-panel-urban-concrete` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/mermerni-panel-nordic-concrete` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/mermerni-panel-noir-stone` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/mermerni-panel-dark-luxe` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/mermerni-panel-white-marble` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/mermerni-panel-sw002` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/classic-panel-terrazzo` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/classic-panel-midnight-black` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/classic-panel-soft-beige` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/classic-panel-pure-white` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/kozni-panel-pw007-hermes-narandzasta` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/kozni-panel-pw001-bordo-crvena` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/kozni-panel-pw005-ledena-siva` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/kozni-panel-pw003-tamni-antracit` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/metalni-panel-brushed-gold` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/metalni-panel-raw-steel` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/metalni-panel-champagne-metal` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/metalni-panel-js0014` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/3d-letvica-golden-teak` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/3d-letvica-mocha-oak` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/3d-letvica-espresso-teak` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/3d-letvica-honey-oak` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/3d-letvica-nordic-oak` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/3d-letvica-topli-tik-mat` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/3d-letvica-tamni-orah-gloss` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/3d-letvica-havana-oak` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/3d-letvica-topli-orah` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/3d-letvica-obsidian` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/3d-letvica-prirodni-javor` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/3d-letvica-midnight-black` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/3d-letvica-pure-white` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/3d-letvica-hladno-siva-teksturisana` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/3d-letvica-krem-bijela` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/3d-letvica-topla-bez` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/3d-letvica-topla-bez-uzi-profil` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/3d-letvica-terrazzo` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/3d-letvica-hladno-siva` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/3d-letvica-deva` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/3d-letvica-grigio` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/3d-letvica-glacier` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/3d-letvica-perla` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/3d-letvica-zlatna-zuta` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/3d-letvica-prirodni-hrast` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/3d-letvica-tamna-siva` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/3d-letvica-topli-mahagonij` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/3d-letvica-bijela-premium-profil` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/akusticni-panel-aku063-prirodni-hrast` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/akusticni-panel-aku064-orasasti-hrast` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/akusticni-panel-aku060-topli-orah` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/akusticni-panel-aku041-tamni-antracit` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/akusticni-panel-aku005-crni-talas` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/akusticni-panel-aku051-zlatni-hrast-geometrik` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/akusticni-panel-aku050-bijeli-pepeo-geometrik` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/akusticni-panel-aku054-medeni-hrast-linear` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/akusticni-panel-aku053-zlatni-bambus-linear` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/alu-lajsna-l1-crna-srednja-lajsna` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/alu-lajsna-l2-crna-pocetna-zavrsna-lajsna` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/alu-lajsna-l3-crna-ugaona-lajsna-spoljni-ugao` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/alu-lajsna-l4-crna-led-lajsna` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/alu-lajsna-l5-bronzana-srednja-lajsna` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/alu-lajsna-l6-bronzana-pocetna-zavrsna-lajsna` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/alu-lajsna-l7-bronzana-ugaona-lajsna-spoljni-ugao` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/alu-lajsna-l8-bronzana-led-lajsna` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/spc04-spc-laminat` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/spc05-spc-laminat` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/spc06-spc-laminat` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/spc07-spc-laminat-tile-format` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/spc08-spc-laminat-tile-format` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/pu-kamen-poliuretanski-kamen-bijeli` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/pu-stone-talas-beli-xl` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/pu-stone-talas-bez-xl` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/pu-stone-talas-khaki-xl` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/pu-stone-talas-siva-xl` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/pu-stone-mushroom-beli` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/pu-stone-mushroom-bez` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/pu-stone-mushroom-braon` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/pu-stone-mushroom-crni` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/mdf-panel-mdf004-deblji` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/mdf-panel-mdf005-tanji` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/mdf-panel-mdf001` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/mdf-panel-mdf002` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/mdf-panel-mdf003` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/flex-stone-linear-travertine-1-2x0-6m-fleksibilni-kamen` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/flex-stone-weaving-beige-1-2x0-6m-fleksibilni-kamen` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/flex-stone-weaving-khaki-1-2x0-6m-fleksibilni-kamen` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/flex-stone-rouge-granite-beige-1-2x0-6m-fleksibilni-kamen` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/flex-stone-white-linear-travertine-1-2x0-6m-fleksibilni` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/flex-stone-luna-travertine-1-2x0-6m-fleksibilni-kamen` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/flex-stone-dolomitic-travertine-1-2x0-6m-fleksibilni-kamen` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/flex-stone-romantine-white-1-2x0-6m-fleksibilni-kamen` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/flex-stone-romantine-yellow-1-2x0-6m-fleksibilni-kamen` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
| `/paneli/flex-stone-white-milan-travertine-1-2x0-6m-fleksibilni-kamen` | da | da | 200 | dozvoljeno | ne | 1 | INDEXABLE |
