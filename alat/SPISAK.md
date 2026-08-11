# Spisak pravila — sta na sajtu mora da vazi

Pokretanje: `python3 alat/provjera.py brzo` (oko 3 min) ili `sve` (oko 25 min).
Svako pravilo ili prodje ili ispise tacno gdje je pao.

## A · Dostupnost
- **A1** Svaka adresa iz sitemapa vraca 200, bez ijednog preusmjerenja
- **A2** Canonical na svakoj stranici pokazuje na tu istu adresu
- **A3** Nepostojeci proizvod/kategorija vraca 404, ne lazni 200
- **A4** Admin nije dostupan bez prijave

## B · Adrese
- **B1** Stari `?id=` ide **jednim** skokom na tacnu novu adresu (svih 117)
- **B2** Stari `?category=` ide jednim skokom na `/kategorija/` (svih 14)
- **B3** Kosa crta, velika slova, `www`, `/index.html` — sve vodi na jednu kanonsku adresu
- **B4** Nijedna stranica ne sadrzi stari oblik adrese u HTML-u

## C · Sadrzaj
- **C1** Tacno jedan `<h1>` po stranici
- **C2** Title jedinstven i do 65 znakova
- **C3** Meta opis jedinstven, 70–165 znakova
- **C4** Nema cirilice, mojibakea, `undefined`, `NaN`, nerazrijesenih sablona, neizvrsenog PHP-a
- **C5** Svaka slika ima `alt`

## D · Strukturirani podaci
- **D1** Svaki JSON-LD blok se parsira
- **D2** Product ima name, image, description, offers, sku, brand
- **D3** Ponuda ima price, priceCurrency, availability, itemCondition, validFrom, priceValidUntil
- **D4** Nigdje se ne salju ocjene (namjerna odluka — recenzije nisu provjerene)
- **D5** Breadcrumb pozicije idu 1, 2, 3…

## E · Resursi i linkovi
- **E1** Stranice na `/paneli/` i `/kategorija/` imaju `<base>` (inace pucaju sve relativne putanje)
- **E2** Svaki link i resurs sa svake stranice vraca 200, bez lanca
- **E3** Svaka slika iz `products.json` postoji na serveru

## F · Podaci
- **F1** Svaki proizvod ima id, ime, cijenu, kategoriju, sliku, jedinicu
- **F2** Samo SPC pod se prodaje po m²
- **F3** PHP (`php/slug.php`) i JavaScript (vrh `js/products.js`) prave **istu** adresu za svaki proizvod
- **F4** Nijedan proizvod ne dijeli adresu sa drugim

## G · Server i bezbjednost
- **G1** gzip, HSTS, X-Content-Type-Options, X-Frame-Options, Referrer-Policy **i Cache-Control** —
  mjereno na svakom tipu stranice (staticka `.html`, PHP proizvod, PHP kategorija), jer
  `FilesMatch` u `.htaccess` gleda ime fajla na disku a ne adresu
- **G2** `.htaccess`, `error_log`, arhive, `.git`, listanje foldera — sve zabranjeno
- **G3** `robots.txt` navodi sitemap, blokira `/admin/`, ne blokira `/paneli/` ni `/kategorija/`
- **G4** Lokalni fajlovi identicni onima na serveru
- **G5** Svi vazni fajlovi su u `admin/sync.php` listi (bez `php/slug.php` sajt puca)
- **G6** `data/inquiries.json` (ime, email, telefon i poruka svakog kupca iz kontakt
  forme) mora biti **zatvoren** — `/data/` je javan direktorijum jer se odatle cita
  `products.json`. Isto vazi za `.tmp` varijantu, `data/*.bak.<datum>.json` i debug
  logove. Istovremeno `products.json`, `categories.json` i `reviews.json` moraju
  ostati **otvoreni** — bez njih sajt ne radi.

## H · Adrese koje Google stvarno ima
Izvor: `alat/gsc-adrese.txt` — izvezeno iz Search Console-a (Pages + Coverage).
- **H1** Svaka od tih adresa vraca 200 ili 410, nikad lanac preusmjerenja
- **H2** Ugasen proizvod ide na **svoju kategoriju**, ne na spisak svih kategorija

## I · Unutrasnje povezivanje
Stranica do koje sa sajta vodi jedan jedini link Google sporo obilazi i slabo rangira.
Zato se broji koliko linkova sa samog sajta vodi do svake stranice (racuna se `<base href>`,
a linkovi koje pravi JavaScript se **ne** broje — Google ih u prvom prolazu ne vidi).
- **I1** Nijedna stranica nije siroce — bar jedan link vodi do nje
- **I2** Svaki proizvod ima bar 3 dolazna linka
  (kutiju "Slicni proizvodi" ispisuje `product.php` na serveru; vrti se u krug kroz
  kategoriju, pa nijedan proizvod ne ostaje bez veza sa susjedima)
- **I3** Svaki od 6 vodica je linkovan sa bar 10 stranica
  (podnozje svih stranica + blok "Procitajte prije kupovine" na kategorijama)

## R · Recenzije
Recenzije se traze po ID-u proizvoda u `data/reviews.json`. ID se ranije citao samo
iz `?id=` u adresi — a nove adrese `/paneli/...` ga nemaju, pa je ostajao nula.
Pod kljucem `"0"` nema nicega, kod je padao na rezervnu granu i uzimao STARO polje
`reviews` iz `products.json`. Rezultat: svih 117 proizvoda je mjesecima prikazivalo
3 stare recenzije umjesto 5 novijih, a ocjena i zvjezdice su se racunale po starima.
Nista na stranici to nije odavalo.
- **R1** Svaka recenzija koja postoji u podacima se vidi na stranici proizvoda
- **R2** Ukupan broj prikazanih recenzija = broj u podacima (585)

## J · Vanjski alati koji sami kazu sta ne valja
Nase provjere (A–I) gledaju ono sto smo mi odlucili da gledamo. Ova tri alata
gledaju sve ostalo — pisu ih Google i W3C i ne znaju nista o nasem sajtu.
Vrijedi ih pustiti poslije svake vece izmjene.

**1. W3C provjeravac HTML-a** — ispravnost koda stranice
```
https://validator.w3.org/nu/?doc=https%3A%2F%2Fmakemyhome.me%2F&out=json
```
Zamijeni adresu na kraju. Trazi `"type":"error"`. Cilj: nula.
Prvi put pusten 11.08.2026 — nasao 109 gresaka na 13 stranica, sve popravljene.

**2. Google Lighthouse** — pristupacnost, SEO, dobra praksa
```
npm i lighthouse
CHROME_PATH=<putanja do chrome> node node_modules/lighthouse/cli/index.js \
  <adresa> --only-categories=accessibility,best-practices,seo \
  --chrome-flags="--headless=new --no-sandbox"
```
Cilj: pristupacnost i SEO 100. Korpa i naplata imaju SEO 69 namjerno — one su
noindex, Google ih ne treba.
Prvi put pusten 11.08.2026 — pristupacnost je bila 91, sada je 100.

**3. W3C provjeravac CSS-a**
```
https://jigsaw.w3.org/css-validator/validator?uri=https%3A%2F%2Fmakemyhome.me%2Fcss%2Fstyle-v5.css&output=json&profile=css3
```
Prijavljuje `pointer-events` kao gresku — nije greska, alat ne poznaje to
svojstvo. Sve ostalo mora biti cisto.

**4. Google Search Console** (ovo moze samo vlasnik, kroz nalog)
  Pages · Sitemaps · Core Web Vitals · Manual actions

## K · Fontovi — sazeti, ne originalni

**Ne zamjenjuj fajlove u `fa/webfonts/` i `fonts/` originalima sa interneta.**
Oni na serveru su namjerno sazeti na znakove koje sajt koristi:

| fajl | original | sada | sadrzi |
|---|---|---|---|
| `fa-solid-900.woff2` | 153 kB | 12 kB | 123 ikone |
| `fa-brands-400.woff2` | 114 kB | 2 kB | 6 ikona |
| Inter `...25L7SUc` (latin-ext) | 83 kB | 37 kB | U+0100-024F |
| Playfair `...LYgFE_` (latin-ext) | 21 kB | 15 kB | U+0100-024F |

Prva posjeta je time pala sa 455 kB fontova na 150 kB.
Imena fajlova, kodovi znakova i CSS su nepromijenjeni, pa stara kesirana
verzija i dalje radi.

**Ako se doda NOVA ikona** koja se do sada nije koristila, nece se prikazati —
font je nema. Tada treba ponovo sazeti:
```
python3 alat/fontovi.py      # popise ikone iz svih fajlova i sazme fontove
```
Isto vazi ako se u tekst uvede slovo van opsega U+0000-024F.

## Sta se NE provjerava automatski
- Izgled na telefonu (radi se kroz Playwright, posebno)
- Pretraga, korpa, naplata (isto Playwright)
- Da li je tekst tacan po sadrzaju — to moze samo covjek
