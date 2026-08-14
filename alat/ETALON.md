# ETALON — kakav sajt mora biti

Ovo je opis stanja u kojem sajt smije da bude pušten. Nije spisak želja nego
mjerilo: svaka stavka ima pravilo koje je provjerava, i pravilo je jedini sudija.
Ako stavka nema pravilo, nije u etalonu — jer ono što se ne mjeri, raspadne se
i niko ne primijeti.

Provjera:

    python3 alat/dok-ne-bude.py sve

Izlaz 0 znači da je sajt po etalonu. Izlaz 1 znači da nije i da ima šta da se
popravi. Ništa se ne pušta i ništa se ne prijavljuje kao gotovo dok ne bude 0.

---

## Pravilo iznad svih: kvar je kvar samo ako se ponovi

Provjera povlači preko 400 adresa u nizu i shared hosting to zna nakratko da
obori. Jedan takav ispad nije greška na sajtu. Zato:

* `dohvati()` ponavlja svaki zahtjev tri puta kad odgovor bude 000, 429 ili 5xx;
* `dok-ne-bude.py` nijednu palu provjeru ne prijavljuje dok je ne potvrdi drugim
  prolazom.

Lažna greška je gora od nikakve — pošalje čovjeka da popravlja ono što nije
pokvareno, a pravu grešku sakrije u šumu.

---

## 1. Svaka adresa radi · A, E

| Mora biti | Pravilo |
|---|---|
| Svaka od 149 adresa iz sitemapa vraća 200 bez preusmjeravanja | A1 |
| Canonical svake stranice pokazuje na tu istu stranicu | A2 |
| Nepostojeća adresa vraća 404, ne lažni 200 | A3 |
| Admin je zatvoren bez prijave | A4 |
| Svaki link i resurs sa svake stranice radi | E2 |
| Svaka slika iz `products.json` postoji | E3 |
| Ugniježdene adrese (`/paneli/…`, `/kategorija/…`) imaju `<base>` | E1 |

## 2. Stare adrese ne gube posjetioca · B, H

| Mora biti | Pravilo |
|---|---|
| Stari `?id=` ide **jednim** skokom na novu adresu | B1 |
| Stari `?category=` ide jednim skokom na `/kategorija/` | B2 |
| www, http, kosa crta na kraju — sve vodi na jednu kanonsku adresu | B3 |
| Nijedna stranica ne sadrži stari oblik adrese u svom kodu | B4 |
| Svaka adresa koju Google ima → 200 ili 410, bez lanca skokova | H1 |
| Ugašen proizvod ide na svoju kategoriju, ne na opšti katalog | H2 |
| Stara adresa bez zamjene vraća **410**, ne 404 i ne 301 na početnu | H3 |

410 a ne 404: 410 znači „nema više i neće se vratiti“, i Google takvu adresu
izbaci brže. Redirekcija na početnu je najgora — Google je čita kao lažni 200.

## 3. Google čita sadržaj bez JavaScripta · G, C

| Mora biti | Pravilo |
|---|---|
| Ništa što Google treba ne čeka JavaScript | G9 |
| JavaScript ne prepisuje ono što je server već ispisao | G11 |
| Server sam sastavlja početnu, katalog i sitemap | G8 |
| Tačno jedan `<h1>` po stranici | C1 |
| Title jedinstven, do 65 znakova | C2 |
| Opis jedinstven, 140–160 znakova | C3 |
| Nema ćirilice, mojibakea, `undefined`, `NaN` | C4 |
| Svaka slika ima `alt` | C5 |
| Svaka ikona koju stranica traži postoji u CSS-u | C6 |
| Adresa i telefon isti na cijelom sajtu i u strukturiranim podacima | C7 |
| Nijedan komad koda nije iscurio u vidljivi tekst | C8 |
| Svaka ikona ima i **stvarni znak u fontu**, ne samo pravilo u CSS-u | C9 |

C8 i C9 postoje zato što su dvije greške prošle kroz sve ostale provjere i
vidjele se **samo okom, na slici stranice**: golo `">` ispod fotografije
fabrike, i četiri ikone koje se iscrtavaju kao prazan prostor. Obje stranice
su vraćale 200, HTML se učitavao, sve ostalo je bilo tačno.

Pouka: provjera koja gleda samo kod ne vidi ono što posjetilac vidi.

## 3a. Stranica izgleda kako treba · alat/oko.mjs

Ovo se ne provjerava curl-om nego pravim pregledačem, na 10 tipova stranica ×
računar i telefon:

| Mora biti | |
|---|---|
| Nijedna JavaScript greška na stranici | |
| Nijedan resurs sa našeg domena ne vraća 404 | |
| Nijedna stranica ne izlazi van ekrana (bočni skrol) | |
| Nijedan komad koda u vidljivom tekstu | |
| Nijedna ikona bez stvarnog znaka u fontu | |

    php -S 127.0.0.1:8899 -t . &
    node alat/posrednik.mjs &
    node alat/oko.mjs

Posrednik je obavezan: stranice nose `<base href="https://makemyhome.me/">`, pa
bi pregledač bez njega učitao stranicu **bez stilova** i prijavio gomilu
izmišljenih grešaka. Posrednik i slike koje postoje samo na serveru
(`images/products/*`, `images/categories/*`) dovlači sa sajta — bez toga bi
alat prijavio 27 nepostojećih 404. Spoljni servisi (Analytics, Google Maps) se
ne broje: kroz ovo okruženje ionako ne prolaze.

Ovo je bio korijen pet mjeseci nevidljivosti: sadržaj je crtao JavaScript, a
Google je indeksirao 144 prazne stranice. Zato G9 i G11 nisu preporuka.

## 4. Strukturirani podaci su tačni · D

| Mora biti | Pravilo |
|---|---|
| Svi JSON-LD blokovi se parsiraju | D1 |
| Product ima name, image, description, offers, sku, brand | D2 |
| Ponuda ima cijenu, valutu, stanje, validFrom, priceValidUntil | D3 |
| **Nigdje se ne šalju ocjene Google-u** | D4 |
| Breadcrumb pozicije idu 1, 2, 3… | D5 |

D4 je namjerno: izmišljena `aggregateRating` je razlog za ručnu kaznu.

## 5. Podaci se slažu sami sa sobom · F, R

| Mora biti | Pravilo |
|---|---|
| Svaki proizvod ima id, ime, cijenu, kategoriju, sliku, jedinicu | F1 |
| **Samo SPC pod se prodaje po m²**, sve ostalo po komadu | F2 |
| PHP i JavaScript prave **istu** adresu za svaki proizvod | F3 |
| Nijedan proizvod ne dijeli adresu sa drugim | F4 |
| Svaka recenzija iz podataka se vidi na stranici | R1 |
| Broj prikazanih recenzija = broj u podacima | R2 |

F3 postoji jer slug računaju dva jezika na dva mjesta: `php/slug.php` i vrh
`js/products.js`. Ako se promijeni jedan a ne drugi, linkovi vode na 404.

## 6. Git, cPanel i živi sajt nose isto · G

| Mora biti | Pravilo |
|---|---|
| Lokalni git je na onome što je pushovano, bez neupisanih izmjena | G4 |
| Svih 63 fajla iz sync liste isti u gitu i na serveru | G4 |
| Svi važni fajlovi su u listi sinhronizacije | G5 |
| **Nijedan fajl koji admin mijenja nije u sync listi** | G7 |

G7 postoji zbog stvarnog gubitka: admin je pisao CSS u `decor-box.html`, a sync
je taj fajl povlačio sa GitHuba i prepisivao — podešavanje bi nestalo bez ijedne
poruke. Zato izbor sad stoji u `data/decor-box-style.json`.

Redoslijed deploya je obavezan i iz istog razloga: **prvo push, pa sync**. Sync
povlači sa GitHuba; fajl deployovan preko cPanela prije push-a prvi sljedeći
sync vrati na staro.

## 7. Server je podešen kako treba · G

| Mora biti | Pravilo |
|---|---|
| Kompresija, zaštita i Cache-Control na svakom tipu stranice | G1 |
| Osjetljivi fajlovi i listanje foldera zabranjeni | G2 |
| `robots.txt` navodi sitemap, blokira `/admin/`, **ne** blokira proizvode | G3 |
| Podaci kupaca zatvoreni, podaci sajta otvoreni | G6 |
| Slike se serviraju kao WebP samo pregledaču koji ga čita | G10 |

## 7a. Sitemap je tačan · S

Sitemap je jedini dokument koji Googleu kaže **šta sajt ima** i **kad se šta
promijenilo**. Do sada se provjeravao samo posredno — kroz A1, da svaka adresa
iz njega vraća 200 — pa su dvije greške u njemu prošle neprimijećeno.

| Mora biti | Pravilo |
|---|---|
| Servira se kao `application/xml`, gzip, bez skoka; `robots.txt` ga navodi | S1 |
| Validan XML, bez duplikata, apsolutne adrese, validni changefreq/priority, `lastmod` u obliku Y-m-d i ne u budućnosti, svaka slika ima naslov | S2 |
| Nijedna stranica ne fali u sitemapu — a ako fali, nosi `noindex` | S3 |
| **Keš zavisi od svega iz čega se računaju datumi** | S4 |
| Svaka slika iz sitemapa postoji | S5 |

Dvije greške koje su ovo iznudile:

1. **`sync.php` je prepisivao svaki fajl pri svakom pokretanju**, i kad se
   sadržaj nije promijenio ni za bajt. Vrijeme izmjene na disku je zato mjerilo
   *kad je zadnji put pokrenut deploy*, a ne kad se stranica promijenila — pa
   je svih 149 adresa javljalo isti datum. Kad sve stranice svaki put kažu da
   su nove, Google prestane da vjeruje tom podatku i ignoriše ga.
2. **Keš sitemapa nije zavisio od stranica** iz kojih se ti datumi računaju.
   Izmjena teksta na `about.html` promijenila bi `lastmod`, ali bi keš i dalje
   služio stari — i Google ne bi ni saznao da se nešto promijenilo.

S4 je statička provjera samog `sitemap.php`: upoređuje spisak fajlova od kojih
keš zavisi sa spiskom iz kog se datumi računaju. Provjereno na oba stanja koda —
0 nalaza na ispravnom, 25 na onom prije popravke.

**Pravilo za ubuduće:** kad se u `sitemap.php` doda nov izvor datuma, mora ući i
u `$izvori`. S4 ne pušta da se to zaboravi.

## 8. Nijedna stranica nije siroče · I

| Mora biti | Pravilo |
|---|---|
| Do svake stranice vodi bar jedan link | I1 |
| Svaki proizvod ima bar 3 dolazna linka | I2 |
| Svaki vodič je linkovan sa bar 10 stranica | I3 |

Stranica do koje ne vodi nijedan link za Google praktično ne postoji, čak i kad
je u sitemapu.

---

## Alati — koji šta radi

| Alat | Radi | Kad se pokreće |
|---|---|---|
| `dok-ne-bude.py` | pušta sve provjere dok ne bude čisto | prije svakog „gotovo je" |
| `provjera.py` | 48 pravila iz ovog dokumenta | zove ga runner |
| `oko.mjs` | pregled pravim pregledačem, 10 stranica × 2 uređaja | kad se mijenja izgled |
| `posrednik.mjs` | neutrališe `<base href>`, dovlači slike sa servera | uz `oko.mjs` i Lighthouse |
| `snimak.py` | snimi svih 149 stranica prije i poslije izmjene, pokaže razliku | prije rizične izmjene |
| `korpa.mjs` | 22 provjere korpe i narudžbe u pravom pregledaču | kad se dira korpa |
| `lighthouse.sh` | Googleov alat na 14 tipova stranica | kad se dira brzina ili pristupačnost |
| `ikone.py` | pravi skraćeni `mmh-ikone.css` | **kad se doda nova ikona** |
| `fontovi.py` | sažima fontove iz `fa/webfonts-izvor/` | **kad se doda nova ikona** |
| `webp.php` | pravi `.webp` parnjake i uže varijante slika | kad se dodaju slike |

Nova ikona traži **oba**: `ikone.py` (pravilo u CSS-u) i `fontovi.py upisi`
(znak u fontu). Ako se pokrene samo prvi, ikona se iscrta kao prazan prostor —
to se već desilo i zato postoji pravilo C9.

## Šta se ne provjerava automatski

Da se ne bi mislilo da je pokriveno više nego što jeste:

* da li je tekst **tačan po sadržaju** — cijena, dimenzija, tvrdnja o proizvodu.
  To može samo čovjek koji zna robu;
* da li stranica **lijepo izgleda**. `oko.mjs` hvata pokvareno (kod u tekstu,
  prazna ikona, bočni skrol), ne ružno;
* da li Google **stvarno prikazuje** sajt za neki upit — to se vidi samo u
  Search Console-u, poslije indeksiranja.

---

## Šta se NE dira

* `data/products.json` i `images/products/*` se **nikad** ne deployuju sa lokalnog
  na server. Vlasnik ih mijenja kroz admin i deploy bi te izmjene obrisao. Ako
  se sadržaj mijenja: povuci sa servera → izmijeni → vrati.
* Glavne slike proizvoda se ne diraju bez izričite dozvole.
* `Options` direktiva u `.htaccess` ruši cijeli sajt na ovom hostingu (500 na
  svakoj stranici). Samo `<IfModule>` blokovi.
* Blok u `/home/mmhdecor/public_html/.htaccess` koji 301-uje sve na `makemyhome.me`
  se ne briše — bez njega postoje dva sajta sa istim sadržajem.

## Kad se doda novo pravilo

Novo pravilo se dodaje **onda kad se greška desi**, ne unaprijed. Svako pravilo
u `provjera.py` nosi komentar koji kaže koja se greška stvarno dogodila i šta je
koštala. Pravilo bez te priče je pretpostavka; pravilo sa njom je ožiljak koji
više ne pušta istu grešku da prođe.
