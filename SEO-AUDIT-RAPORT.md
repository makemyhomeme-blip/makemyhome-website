# SEO audit — makemyhome.me

Napravljeno: 2026-08-16 18:28 UTC · Googlebot user-agent

**[!!]** blokator · **[!]** ozbiljno · **[i]** informacija

## 1. Verzije domena

```
http://makemyhome.me/                    200 skokova:1 -> https://makemyhome.me/
https://makemyhome.me/                   200 skokova:0 -> https://makemyhome.me/
http://www.makemyhome.me/                200 skokova:1 -> https://makemyhome.me/
https://www.makemyhome.me/               200 skokova:1 -> https://makemyhome.me/
https://makemyhome.me/index.html         200 skokova:1 -> https://makemyhome.me/
```
[i] Sve verzije zavrsavaju na `https://makemyhome.me/`. Nema duplikata domena.

## 2. robots.txt

Status: `200`
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
[i] Nema `Disallow: /`.
[i] Sitemap naveden.

## 3. Sitemap

Status `200` · preusmjerenja `0` · tip `application/xml; charset=utf-8`
Adresa u sitemapu: **149**
[i] Sve adrese apsolutne.
[i] Nema duplikata.
[i] Nema iste stranice u dva oblika.
[i] Nijedan datum nije u buducnosti.
[i] Nijedan datum nije zastario.
[i] Sitemap nosi **492** slika (image sitemap je ugradjen).

## 4. Statusi svih stranica

Skinuto: **149** stranica.
[i] Svih 149 adresa vraca 200.
[i] Nijedna adresa iz sitemapa ne preusmjerava.

## 5. Zabrana indeksiranja

[i] Nema `noindex` ni u HTML-u ni u HTTP zaglavlju.

## 6. Canonical

[i] Svaka stranica ima canonical.
[i] Nijedan canonical se ne ponavlja.
[i] Svaki canonical pokazuje na samu stranicu.

## 7. Title i meta opis

[i] Svaka stranica ima title.
[i] Svaka stranica ima meta opis.
[i] Nijedan title se ne ponavlja.
[i] Nijedan opis se ne ponavlja.
[i] 48 title-ova duze od 60 znakova (Google ih skracuje).

## 8. Sadrzaj u sirovom HTML-u (bez JavaScripta)

Sve se mjeri u HTML-u koji server posalje, prije nego JavaScript isprva ista.

### Malo teksta

[i] Nijedna stranica nema ispod 250 rijeci.

### Naslovi H1

[i] Svaka stranica ima H1.
[i] Nijedna nema vise H1.

### KLJUCNI TEST — cijene i naslovi na kategorijama

Ako u sirovom HTML-u kategorije nema cijena (€) ni naslova proizvoda (h2/h3),
znaci da ih crta JavaScript i Google ih u prvom prolazu ne vidi.

```
kategorija                                rijeci   €    h2    h3
/kategorija/bambus-paneli                   2252    81    51     3
/kategorija/bambus-drveni                   1624    21    21     3
/kategorija/bambus-tekstilni                1701    23    21     3
/kategorija/bambus-mermerni                 1739    29    25     3
/kategorija/bambus-metalni                  1504    11    16     3
/kategorija/bambus-kozni                    1514    12    16     3
/kategorija/3d-letvice                      2213    59    40     3
/kategorija/akusticni-paneli                1827    24    21     3
/kategorija/aluminijum-lajsne               1731    13    20     3
/kategorija/spc-pod                         1702    15    17     3
/kategorija/pu-kamen                        1862    21    21     3
/kategorija/classic                         1519    11    16     3
/kategorija/mdf                             1646    17    17     3
/kategorija/flex-stone                      1897    25    22     3
```
[i] Sve kategorije imaju cijene u sirovom HTML-u — server ih ispisuje.
[i] Svaka stranica proizvoda ima cijenu u sirovom HTML-u.

## 9. Strukturirani podaci

[i] Svaka stranica ima bar jedan JSON-LD blok.

Tipovi na sajtu:
```
Product (unutar ItemList)      149
BreadcrumbList                 148
Product                        117
ItemList                       15
FAQPage                        10
HomeGoodsStore                 2
LocalBusiness                  2
Organization                   2
WebSite                        1
SiteNavigationElement          1
AboutPage                      1
Service                        1
```

[i] Svaki JSON-LD blok se ispravno parsira.

[i] Svaka /paneli/ stranica ima Product schemu.

## 10. lang · og:image · viewport

[i] Svaka stranica ima `lang`.
[i] Svaka stranica ima `og:image`.
[i] Svaka stranica ima `viewport`.

## 11. Slike

[i] Svaka slika ima `alt`.

Slike preko 300 kB (uzorak 30):
```
```

## 12. Interni linkovi i sirocad

[i] Do svake stranice iz sitemapa vodi bar jedan interni link.

Interni linkovi koji vracaju gresku (uzorak 60):
```
/kategorija/' + slug + '                                           000
```

## 13. Lokalni repo


### Pojave rijeci noindex

```
./korpa.html:6:  <meta name="robots" content="noindex, follow">
./hvala.html:18:  <meta name="robots" content="noindex, follow">
./404.html:5:  <meta name="robots" content="noindex, follow">
./product.php:57:    header('X-Robots-Tag: noindex', true);
./js-check.js:234:    if (/noindex/i.test(d.robots)) dodaj(`[!!] meta robots sadrzi noindex: \`${d.robots}\``);
./checkout.html:6:  <meta name="robots" content="noindex, follow">
./products.php:46:    header('X-Robots-Tag: noindex', true);
./products.php:112:    header('X-Robots-Tag: noindex', true);
```

### Konfiguracija hostinga

```
netlify.toml     nema
vercel.json      nema
.htaccess        postoji (16029 B)
_redirects       nema
_headers         nema
CNAME            nema
web.config       nema
nginx.conf       nema
```

### Sumnjivi fajlovi u korijenu

```
makemyhome-website.zip
upload.zip
```

### Lozinke ili kljucevi u JavaScriptu

```
```

### Istorija sitemapa i robots.txt

```
632f212 Kategorija sa 39 fotografija stajala je u sitemapu bez ijedne slike
f7b207e Sitemap je Googleu govorio da se svih 149 stranica mijenja svaki dan
423337b Sitemap se pravio iznova pri svakom zahtjevu — Google javljao gresku
20180ae Sitemap je svim stranicama javljao isti datum izmjene
6f7cf1c Alt tekstovi: 35 grupa slika imalo je identican opis
cc07ecc Provjera: pravila osvjezena poslije prelaska na sastavljanje na serveru
f4c642a Sitemap nije imao nijednu sliku — Google nije mogao ni da ih otkrije
4b7ec8a Sitemap: lastmod na 11.08.2026 — sve stranice su dobile nove linkove
```

## 14. Brzina

```
stranica                                           TTFB   ukupno
/                                              0.655820s 0.805832s
/kategorija/bambus-paneli                      0.753611s 1.005375s
/paneli/drveni-panel-golden-teak               0.676137s 0.821536s
```
[i] Mjereno kroz posrednik ovog okruzenja — pravi server je brzi za oko 0,5 s.

---

Kes skinutih stranica: `.seo-audit-kes/`
