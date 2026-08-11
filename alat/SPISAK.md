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
- **G1** gzip, HSTS, X-Content-Type-Options, X-Frame-Options, Referrer-Policy
- **G2** `.htaccess`, `error_log`, arhive, `.git`, listanje foldera — sve zabranjeno
- **G3** `robots.txt` navodi sitemap, blokira `/admin/`, ne blokira `/paneli/` ni `/kategorija/`
- **G4** Lokalni fajlovi identicni onima na serveru
- **G5** Svi vazni fajlovi su u `admin/sync.php` listi (bez `php/slug.php` sajt puca)

## H · Adrese koje Google stvarno ima
Izvor: `alat/gsc-adrese.txt` — izvezeno iz Search Console-a (Pages + Coverage).
- **H1** Svaka od tih adresa vraca 200 ili 410, nikad lanac preusmjerenja
- **H2** Ugasen proizvod ide na **svoju kategoriju**, ne na spisak svih kategorija

## Sta se NE provjerava automatski
- Izgled na telefonu (radi se kroz Playwright, posebno)
- Pretraga, korpa, naplata (isto Playwright)
- Da li je tekst tacan po sadrzaju — to moze samo covjek
