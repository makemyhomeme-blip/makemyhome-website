# SEO / Search Console audit — makemyhome.me

**Datum:** 17. avgust 2026.
**STANJE POSLIJE POPRAVKI (isti dan):** svih pet nalaza je zatvoreno.
Recenzije su **uklonjene sa cijelog sajta** po odluci vlasnika (nijedna nije bila
od stvarnog kupca) — nema ih ni na stranicama proizvoda, ni na početnoj, ni u
kodu, ni u strukturiranim podacima; provjereno na svih 149 adresa. Uklonjena je i
tvrdnja „Google ocjena 4.8 · 22 recenzije" sa početne, `numberOfItems` sada
odgovara broju stavki, u svaku ponudu na kategoriji dodani su `url` i `seller`,
blok „u istoj nijansi" ispisuje server, a rezervni izvor recenzija više ne može
tiho ubaciti zastarjele podatke. `provjera.py` 50/50, sve 149 adresa 200.
**Metod:** čitanje izvornog koda + mjerenje živog sajta (149 adresa iz sitemapa,
Googlebot user-agent). **Nijedan postojeći fajl nije mijenjan** — ovo je novi
fajl sa izvještajem.

Šta je stvarno provjereno, a ne pretpostavljeno:

- svih 149 adresa: HTTP status, canonical, `X-Robots-Tag`, JSON-LD
- svih 117 proizvoda: schema polje po polju, cijena u schemi naspram cijene na
  stranici, slug izračunat iz `php/slug.php` naspram sitemapa
- 585 recenzija sa servera (`data/reviews.json`) i 351 ugniježđenih u
  `data/products.json`
- interno povezivanje mjereno iz sačuvanog HTML-a svih 149 stranica

---

## 🔴 KRITIČNI PROBLEMI

**Nema ni jednog kritičnog problema u ovim oblastima.**

To nije uljepšavanje — to je mjerenje. Konkretno:

| provjera | rezultat |
|---|---|
| stranica sa `noindex` u HTML-u ili zaglavlju (od 149) | **0** |
| stranica koje ne vraćaju 200 (od 149) | **0** |
| stranica bez canonicala | **0** |
| canonical koji pokazuje na drugu stranicu | **0** |
| canonical koji se ponavlja na dvije stranice | **0** |
| `aggregateRating` ili `review` u schemi (od 149) | **0** |
| proizvoda koji fale u sitemapu (od 117) | **0** |
| proizvoda bez ijednog dolaznog linka | **0** |
| nevalidan JSON-LD blok | **0** |

Jedina stvar koja bi mogla postati kritična opisana je kao **V1** ispod
(vidljiva „Google ocjena 4.8 · 22 recenzije" na početnoj) — nije kritična *danas*
jer ne ide u structured data, ali je jedina stavka na sajtu koja nosi rizik
ručne kazne ako podatak nije istinit.

---

## 🟠 VAŽNI PROBLEMI

### V1 — „4.8 ★ Google ocjena · 22 recenzije" na početnoj

**Fajl:** `index.html`
**Linija:** 975 (broj `4.8`), 976 (zvjezdice), **977** (tekst `Google ocjena · 22 recenzije`)

```html
975: <span style="...">4.8</span>
976: <span style="color:#f5a623;...">★★★★★</span>
977: <span style="...">Google ocjena · 22 recenzije</span>
```

**Zašto je problem:** ovo je tvrdo upisan tekst koji **imenom navodi Google** kao
izvor ocjene. Ne dolazi iz nikakvog API-ja, nije vezan ni za jedan podatak na
sajtu, i ne osvježava se. Dvije mogućnosti:

- ako Google Business Profile stvarno ima 4,8 sa 22 recenzije — tekst je istinit
  danas, ali će postati neistinit prvog dana kad se broj promijeni, a niko to
  neće primijetiti jer je upisan u HTML;
- ako nema — to je neistinita tvrdnja o ocjeni treće strane, na najvidljivijem
  mjestu na sajtu.

**NIJE MOGUĆE PROVJERITI IZ KODA** da li GBP ima tačno 4,8 i 22 recenzije. To se
vidi samo u Google Business Profile nalogu.

**Kako riješiti:** ili obrisati taj blok, ili ostaviti samo ako je istinit i uz
njega staviti link na stvarni GBP profil (da čitalac može provjeriti). Ne ide u
schemu ni u jednom slučaju — i ne ide.

### V2 — `numberOfItems` u ItemList ne odgovara broju stavki

**Fajl:** `products.php`
**Linije:** **317** (`foreach (array_slice($_listProds, 0, 20) ...)`) i **384**
(`'numberOfItems' => count($_listProds)`)

Izmjereno na živom sajtu:

| kategorija | `numberOfItems` | stavki u `itemListElement` | kartica na stranici |
|---|---|---|---|
| `/kategorija/bambus-paneli` | **39** | **20** | 39 |
| `/kategorija/3d-letvice` | **28** | **20** | 28 |
| `/kategorija/mdf` | 5 | 5 | 5 |

Google čita „lista ima 39 stavki", pa nabroji 20. To nije greška koja obara
validaciju (Rich Results Test ovo ne prijavljuje kao error), ali je netačan
podatak u structured data — a netačni podaci su tačno ono zbog čega Google
prestane vjerovati ostalim podacima na sajtu.

**Kako riješiti:** ili `numberOfItems` postaviti na broj stvarno ispisanih
stavki (`count($_items)`), ili ukinuti ograničenje od 20 i ispisati sve. Prva
opcija je jednostavnija i tačna; druga daje Googleu više podataka ali povećava
schemu na 39 stavki.

### V3 — dva izvora istine za recenzije

**Fajlovi:** `data/reviews.json` (585 recenzija, 117 proizvoda) i
`data/products.json` (polje `reviews`, 351 recenzija)
**Kod koji bira izvor:** `product.php:325–342`

```php
325:  // Reviews & aggregate rating — jedan izvor istine: data/reviews.json
327:  $allReviews = json_decode(@file_get_contents(__DIR__ . '/data/reviews.json'), true) ?: [];
338:    $reviews = $product['reviews'] ?? [];      // <- rezervni izvor
```

`products.json` i dalje nosi 351 staru recenziju koje se nigdje ne prikazuju dok
`reviews.json` postoji. Ako `reviews.json` ikad zafali ili se pokvari, sajt
tiho pređe na stari skup — druge recenzije, druga ocjena, ista adresa. Komentar
na `product.php:61–65` opisuje da se upravo takav tihi prelaz već jednom desio.

**Kako riješiti:** izbaciti polje `reviews` iz `products.json` kad se odluči šta
će biti sa recenzijama (vidi sekciju o recenzijama), da postoji samo jedan izvor.

---

## 🟡 PREPORUKE

| preporuka | fajl / linija | očekivani benefit |
|---|---|---|
| `offers.url` u stavkama ItemList-a (Product čvor ima `url`, ponuda ne) | `products.php:330–358` | Merchant listings preporučeno polje; Google sigurnije veže ponudu za adresu |
| `seller` u stavkama ItemList-a (stranica proizvoda ga ima, kategorija ne) | `products.php:330–358` | dosljednost između dva mjesta gdje isti proizvod ima schemu |
| `gtin` ili `mpn` ako proizvodi imaju barkod proizvođača | `product.php:358–367` (`sku` postoji, `gtin`/`mpn` nema) | jače uparivanje u Google Shopping / Merchant listings; **ne izmišljati** ako ih nema |
| cross-sell blok („Ove 3D letvice postoje u istoj nijansi") prebaciti na server | `js/products.js` — blok se crta JavaScriptom | ~200 znakova teksta i nekoliko internih linkova koje Google u prvom prolazu ne vidi |
| početna linkuje samo 4 proizvoda | `index.html` (blok „Izdvojeno") | više proizvoda na jedan klik od početne = brže indeksiranje novih |
| `priceValidUntil` je zadnji dan sljedećeg mjeseca (`product.php:290`) | `product.php:286–290` | radi ispravno, ali svaki proizvod ima isti datum; nije problem, samo da se zna zašto se mijenja svakog prvog |

---

## 🟢 DOBRO JE

- **Product schema je kompletna** — sva obavezna i skoro sva preporučena polja
  (detalji u sekciji 9 ispod).
- **Cijena u schemi = cijena na stranici**, provjereno na proizvodima sa
  popustom: `86,99 € − 20 % = 69,59 €`, schema `price: 69.59`. Nijedan proizvod
  ne šalje punu cijenu dok na stranici stoji snižena (`product.php:271–272`).
- **Ocjene se NE šalju Googleu** — `product.php:385` i `products.php:367` imaju
  `if (false && ...)`, provjereno na svih 149 stranica: 0 pojava
  `aggregateRating`, 0 pojava `review` u structured data.
- **Canonical je besprijekoran** — 149/149 ima canonical, svaki pokazuje na samu
  sebe, nijedan se ne ponavlja, svi `https://makemyhome.me/` bez `www` i bez
  kose crte na kraju.
- **Duplikata adresa nema** — provjereno 313 varijanti:
  `/Paneli/...` → 301, `/paneli/3D-Letvica-...` → 301, `/paneli/x/` → 301,
  `/product.html?id=69` → 301, `/products.html?category=mdf` → 301,
  `/kategorija/MDF` → 301. Svi u **jednom** skoku na tačnu adresu.
- **Parametri ne prave duplikate** — `?utm_source`, `?fbclid`, `?gclid`,
  `?page`, `?filter`, `?ref` vraćaju 200 sa canonicalom **bez** parametra.
- **`noindex` samo tamo gdje treba** — `korpa.html:6`, `checkout.html:6`,
  `hvala.html:18`, `404.html:5`; nijedna nije u sitemapu.
- **404 se ponaša ispravno** — nepostojeći proizvod/kategorija vraća 404 **i**
  `X-Robots-Tag: noindex` (`product.php:55–58`, `products.php:46`, `112`), pa
  nema soft-404 smeća tipa `?id=99999`.
- **Sitemap je tačan** — 149 `<loc>`, 492 `<image:loc>`, 0 duplikata, 0 bez
  HTTPS-a, 0 sa `www`, 0 sa kosom crtom, 0 sa parametrima, 0 `noindex` stranica.
- **Interno povezivanje** — početna → 14/14 kategorija; kategorija → svi svoji
  proizvodi (npr. bambus-paneli → 39); 117/117 proizvoda linkuje nazad na
  kategoriju; **0 siročadi**; najmanji broj dolaznih linkova na proizvod: 6,
  prosjek 8,8.

---

## 1. PRODUCT SCHEMA — polje po polju

**Gdje se generiše:** `product.php:358–411` (jedan `Product` blok po stranici),
`products.php:317–389` (`ItemList` sa do 20 `Product` stavki po kategoriji).

Izvučeno sa žive stranice `/paneli/3d-letvica-obsidian`:

| property | stanje | vrijednost / napomena |
|---|---|---|
| `@type` | ✅ | `Product` |
| `name` | ✅ | `3D Letvica – Obsidian` |
| `description` | ✅ | pravi opis proizvoda, ne šablon |
| `image` | ✅ | apsolutne adrese, više slika iz galerije |
| `url` | ✅ | u `offers.url` na stranici proizvoda |
| `sku` | ✅ | `I3D170MW018` |
| `brand` | ✅ | `{"@type":"Brand","name":"Make My Home Decor"}` |
| `offers` | ✅ | `Offer` |
| `price` | ✅ | `15.99` — **ista kao na stranici** |
| `priceCurrency` | ✅ | `EUR` |
| `availability` | ✅ | `https://schema.org/InStock` |
| `itemCondition` | ✅ | `https://schema.org/NewCondition` |
| `validFrom` | ✅ | `2026-08-01` (`product.php:288`) |
| `priceValidUntil` | ✅ | `2026-09-30` (`product.php:290`) |
| `hasMerchantReturnPolicy` | ✅ | `MerchantReturnFiniteReturnWindow`, 7 dana, `FreeReturn` (`product.php:296–302`) |
| `shippingDetails` | ✅ | `OfferShippingDetails`, 20 EUR (`product.php:302+`) |
| `seller` | ✅ | `Organization` |
| `category`, `color`, `material` | ✅ | `product.php:370–378`, čitaju se iz `features` |
| `priceSpecification` | ✅ | `UnitPriceSpecification` |
| `review` | ⛔ namjerno nema | `product.php:385` — `if (false && ...)` |
| `aggregateRating` | ⛔ namjerno nema | isto |
| `gtin` / `mpn` | ➖ nema | preporučeno, ne obavezno; ne izmišljati |

**Duplikati i konflikti:**

- Stranica proizvoda ima **tačno 2** JSON-LD bloka: `Product` + `BreadcrumbList`
  (`product.php:413`). Nema dva `Product` bloka.
- Kategorija ima **tačno 2**: `ItemList` + `BreadcrumbList`. Isti proizvod se
  pojavi u `ItemList`-u kategorije i kao `Product` na svojoj stranici — to su
  različite adrese, Google to očekuje, nije konflikt.
- **Konflikta između blokova nema** — provjereno parsiranjem svih blokova na
  svih 149 stranica, 0 nevalidnih, 0 protivrječnih vrijednosti.

**Poklapanje sa vidljivim sadržajem:** provjereno na proizvodima sa popustom
(109 od 117 ih ima) — u schemi i na stranici stoji ista snižena cijena.

---

## 2. RECENZIJE I OCJENE

### Gdje su definisane

| šta | gdje | koliko |
|---|---|---|
| glavni izvor | `data/reviews.json` (na serveru) | **585 recenzija**, svih 117 proizvoda |
| rezervni izvor | `data/products.json`, polje `reviews` | 351 recenzija |
| kod koji ih čita | `product.php:325–342` | |
| prosjek i raspodjela | `product.php:340–342` — računa se iz podataka | |

Struktura jedne: `{"avg":4.8,"count":5,"dist":{...},"items":[{name, city, stars, text, date}]}`

**Raspodjela prosjeka po proizvodu:** 4,6 → 10 proizvoda · 4,8 → 74 · 5,0 → 33.
**Nijedan proizvod nije ispod 4,6.** Ukupan prosjek 4,84. Nijedna recenzija nema
polje koje bi vezalo za stvarnu kupovinu (nema `verified`, nema broja
narudžbe). Ocjena se **ne** unosi ručno po proizvodu — računa se iz tih zapisa,
ali su sami zapisi unaprijed napisani, ne prikupljeni.

### Gdje se prikazuju

- **Stranica proizvoda:** `product.php:908–935+` — blok „Šta kažu kupci", veliki
  broj, zvjezdice, trake po ocjenama, kartice recenzija (prve 3 vidljive,
  ostale skrivene). Server ih ispisuje (`data-ssr="1"`).
- **Uz naslov proizvoda:** `product.php:705–720` — zvjezdice + prosjek. Ovdje je
  ranije bilo tvrdo upisano `(4.8) · Odlično` za svaki proizvod, pa je Nordic Oak
  na istoj stranici imao 4,8 gore i 4,6 dolje; sada čita stvarni prosjek.
- **Početna:** `index.html:975–977` — vidi V1.
- **Početna, testimonijali:** `index.html:967+` — „Šta Kažu Naši Kupci", tekstovi
  komentara tvrdo upisani u HTML.

### Odgovori na tražena pitanja

**A) Šta Google trenutno vidi**
Ništa od ocjena. Provjereno na svih 149 stranica sa Googlebot user-agentom:
**0 pojava `aggregateRating`, 0 pojava `review`, 0 pojava `ratingValue`** u
structured data. Blokovi koji bi ih slali stoje u kodu ali su isključeni:
`product.php:385` i `products.php:367` počinju sa `if (false && ...)`.
Google **vidi** zvjezdice kao običan tekst i slike na stranici — kao što vidi
svaki drugi tekst — ali ih ne čita kao ocjenu i ne može ih prikazati u
rezultatima.

**B) Šta bi trebalo ukloniti**
Jedna stvar: `index.html:977` — tvrdnja `Google ocjena · 22 recenzije`. To je
jedino mjesto na sajtu koje **imenom pripisuje ocjenu Googleu**, a ne dolazi iz
Googlea. Sve ostalo su vaše recenzije na vašem sajtu, bez pripisivanja trećoj
strani.

**C) Da li treba potpuno ukloniti review/aggregateRating schema**
Već je uklonjena iz izlaza. Preporuka: **ostaviti `if (false && ...)` kako jeste**
i ne brisati kod. Razlog: kad se uvede forma za kupce, blok se vraća uklanjanjem
dvije riječi, umjesto pisanja ispočetka. Komentari na `product.php:379–384` i
`products.php:360–366` objašnjavaju zašto je isključen — to je zapis odluke i
štiti od toga da ga neko slučajno vrati.

**D) Da li same vizuelne recenzije predstavljaju problem**
Za Googleove smjernice o structured data — **ne**, jer nisu označene. Google
Review snippet guidelines govore o označenim ocjenama.
Ali dva stvarna problema ostaju, i nisu SEO nego povjerenje i zakon:
1. 585 recenzija, nijedna ispod 4,6, sa imenima i gradovima izmišljenih ljudi —
   čitalac koji to prepozna izgubi povjerenje u cijeli sajg, uključujući cijene.
2. Predstavljanje izmišljenih recenzija kao pravih je nelojalna trgovačka praksa
   po propisima o zaštiti potrošača u većini evropskih zemalja, Crnu Goru
   uključujući. **NIJE MOGUĆE PROVJERITI IZ KODA** kakva je tačno crnogorska
   praksa u primjeni — to je pitanje za pravnika, ne za ovaj audit.

**E) Rizik od structured-data ili ručne kazne**
| rizik | stanje |
|---|---|
| ručna kazna za lažne označene ocjene (spammy structured markup) | **nizak** — ocjene se ne označavaju |
| gubitak zvjezdica na cijelom sajtu | **nema šta da se izgubi** — zvjezdica u rezultatima nema |
| kazna zbog tvrdnje „Google ocjena 4.8" | **postoji, ali nije kroz structured data** — kroz prijavu korisnika ili konkurencije |
| Manual Actions u Search Consoleu danas | **prazno** (vi ste pokazali ekran: „No issues detected") |

Kad se uvede prava forma za recenzije: obavezno `datePublished`, ime iz forme,
bez uređivanja teksta, i prikazati i loše ocjene. Do tada — bez schema.

---

## 3. CANONICAL

| gdje | fajl | linija | oblik |
|---|---|---|---|
| stranica proizvoda | `product.php` | **267** | `<link rel="canonical" href="<?= htmlspecialchars($ogUrl, ENT_QUOTES) ?>">` |
| kategorija i katalog | `products.php` | **267** | `<link rel="canonical" href="<?= htmlspecialchars($ogUrl) ?>">` |
| adresa se računa | `product.php:102` → `mmhUrlProizvoda()` u `php/slug.php:77` | | `https://makemyhome.me/paneli/<ime>` |
| adresa se računa | `products.php:189` → `mmhUrlKategorije()` u `php/slug.php:87` | | `https://makemyhome.me/kategorija/<kljuc>` |
| statične stranice | 19 `.html` fajlova, svaki po jedan | npr. `index.html`, `about.html` | tvrdo upisan, tačan |

Mjereno na živom sajtu, svih 149:

- bez canonicala: **0**
- canonical pokazuje na drugu stranicu: **0**
- isti canonical na dvije stranice: **0**
- svi su `https://`, svi bez `www`, svi bez kose crte na kraju
- **paginacije nema** — kategorije ispisuju sve proizvode na jednoj stranici, pa
  nema `?page=` adresa za koje bi canonical trebao biti drugačiji
- filteri/parametri: `?utm_*`, `?fbclid`, `?gclid`, `?page`, `?filter`, `?ref`
  → 200 sa **čistim** canonicalom; `?cat=` i `?category=` → 301

---

## 4. ROBOTS.TXT

**Statični fajl** `robots.txt` (179 B), deployuje se kroz `admin/sync-lista.php:98`.
Ne generiše se kodom.

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

| provjera | rezultat |
|---|---|
| Googlebot blokiran? | **ne** — `User-agent: *` + `Allow: /` |
| `/kategorija/` blokirana? | **ne** |
| `/paneli/` blokirana? | **ne** |
| `/products.html` blokiran? | **ne** |
| `/js`, `/css`, `/images`, `/fa`, `/data` blokirani? | **ne** — sve vraća 200, Google može iscrtati stranicu |
| sitemap prijavljen? | **da**, apsolutnom adresom |
| nepotrebni `Disallow`? | `Disallow: /php/` — vrijedi pogledati, ali **nije problem**: jedini fajl odatle koji pregledač traži je `php/contact.php` (POST forme, `js/main-v4.js:228`), a on nije sredstvo za iscrtavanje. `php/slug.php` i ostali su `require` sa servera i nikad se ne traže preko weba. |
| `wp-login.php` | bezopasno; ionako vraća 410 |

---

## 5. SITEMAP

**Generiše ga `sitemap.php`** (nije statični fajl), `.htaccess` preslikava
`/sitemap.xml` na njega. Sitemap index ne postoji i nije potreban — 149 adresa
je daleko ispod granice od 50.000.

| dio | gdje u kodu |
|---|---|
| zaglavlje `urlset` + `xmlns:image` | `sitemap.php:216–219` |
| jedna `<url>` stavka, `<loc>` + `<lastmod>` | `sitemap.php:138–139` |
| `<image:image>` sa `<image:loc>` i `<image:title>` | `sitemap.php:129–132` |
| statične stranice | `sitemap.php:46` (`$mmhStatika`), petlja `151` |
| kategorije | petlja `193`, grupisanje roditelja `190` |
| proizvodi | petlja `203`, galerija `208` |
| keš i od čega zavisi `lastmod` | `sitemap.php:40–41`, `64`, `98`, `114` |

Izmjereno na živom sitemapu:

| stavka | broj |
|---|---|
| proizvoda u podacima (`data/products.json`) | **117** |
| `/paneli/` adresa u sitemapu | **117** |
| adresa izračunatih iz `php/slug.php` | **117** |
| **proizvodi koji fale u sitemapu** | **nijedan** |
| **u sitemapu a nema ih u podacima** | **nijedan** |
| `/kategorija/` adresa | 14 |
| statične i ostale | 18 |
| **ukupno `<loc>`** | **149** |
| `<image:loc>` | 492 |
| duplikata | 0 |
| bez HTTPS-a | 0 |
| sa `www` | 0 |
| sa kosom crtom na kraju | 0 |
| sa `?` ili `&` | 0 |
| `noindex` stranica u sitemapu | **nijedna** |
| preusmjerenja u sitemapu | 0 (svih 149 vraća 200 bez skoka) |
| nevalidan XML | ne — parsiran bez greške |

Sitemap ima **tačno onoliko adresa koliko sajt ima indeksirajućih stranica**:
117 proizvoda + 14 kategorija + 18 statičnih = 149.

---

## 6. INDEXING / NOINDEX

| pojam | gdje se pojavljuje | ocjena |
|---|---|---|
| `noindex` u meta | `korpa.html:6`, `checkout.html:6`, `hvala.html:18`, `404.html:5` | ✅ tako treba, nijedna nije u sitemapu |
| `X-Robots-Tag: noindex` | `product.php:57`, `products.php:46`, `products.php:112` | ✅ **samo** na putevima koji vraćaju 404 |
| `nofollow` | nigdje na internim linkovima | ✅ |
| 301 | `.htaccess` (blok „LIJEPE ADRESE"), `product.php:47`, `products.php:105`, `114` | ✅ jedan skok |
| 302 | nigdje | ✅ |
| 410 | `.htaccess` + `404.php` za stare WordPress adrese | ✅ |
| 404 | `product.php:55–58`, `products.php:46`, `112`, `404.php` | ✅ |

**Nijedan template proizvoda ili kategorije ne šalje `noindex` na ispravnoj
adresi.** Provjereno i kao posjetilac i kao Googlebot na svih 149.

---

## 7. INTERNO POVEZIVANJE

Mjereno iz sačuvanog HTML-a svih 149 živih stranica, uz izbacivanje `<script>`
blokova (da se JavaScript koji sklapa adrese ne broji kao link):

| provjera | rezultat |
|---|---|
| početna → kategorije | **14 / 14** |
| početna → proizvodi | 4 (izdvojeni) |
| `/kategorija/bambus-paneli` → proizvodi | 39 |
| `/kategorija/3d-letvice` → proizvodi | 28 |
| `/kategorija/mdf` → proizvodi | 5 |
| proizvod → nazad na kategoriju | **117 / 117** |
| **siročadi (proizvod bez dolaznog linka)** | **0** |
| najmanji broj dolaznih linkova na proizvod | 6 |
| prosjek dolaznih linkova | 8,8 |

Do svakog proizvoda se dolazi sa početne u najviše 3 klika (mjereno simulacijom
crawlera, bez sitemapa).

---

## 8. STRUKTURA ADRESA PROIZVODA

Oblik: `/paneli/<tip>-<ime>` (npr. `/paneli/3d-letvica-honey-oak`).
Računa ga `php/slug.php:77` (`mmhUrlProizvoda`), a **ista funkcija postoji i u
JavaScriptu** na vrhu `js/products.js` — obje moraju ostati usklađene.

| mogući problem | stanje |
|---|---|
| duplicate content | ❌ nema |
| više adresa za isti proizvod | ❌ nema — sve varijante 301 |
| ID + slug u adresi | ❌ nema; stari `?id=` → 301 na slug |
| query parametri | ✅ canonical ih čisti |
| razlika velika/mala slova | ✅ `/Paneli/...` i `/paneli/3D-Letvica-...` → 301 |
| kosa crta na kraju | ✅ `/paneli/x/` → 301 na `/paneli/x` |
| slug problemi | ❌ nijedan — 117/117 izračunatih adresa se poklapa sa sitemapom |

---

## 9. GOOGLE PRODUCT / MERCHANT LISTINGS — šta je šta

**Obavezno za validan `Product` (rich result):**
`name`, `image`, i u `offers`: `price` + `priceCurrency` (ili
`priceSpecification`), `availability` → **sve postoji, 117/117.**

**Obavezno/traženo za Merchant listings (Google Shopping izgled):**
`offers.price`, `offers.priceCurrency`, `offers.availability`,
`offers.itemCondition`, `hasMerchantReturnPolicy`, `shippingDetails`
→ **sve postoji, 117/117.**

**Samo preporučeno:** `sku` (✅ ima), `brand` (✅ ima), `description` (✅ ima),
`gtin`/`mpn` (➖ nema — ne izmišljati), `offers.url` (✅ na stranici proizvoda,
➖ nema u ItemList-u), `priceValidUntil` (✅ ima), `validFrom` (✅ ima).

**Potpuno opcionalno:** `color`, `material`, `category` (✅ sve tri ima).

**`review` i `aggregateRating` NISU obavezni** ni za `Product` ni za Merchant
listings. Ono što je Search Console prijavljivao kao „Missing field review /
aggregateRating" je bilo **optional**, i tako je i pisalo na vašem ekranu.
Njihovo odsustvo **ne** može biti razlog što se proizvodi ne prikazuju.

---

## 10. ZAŠTO PROIZVODI I KATEGORIJE NEMAJU PRIKAZ — procjena iz koda

Ovo je najvažniji dio, pa idem redom kroz sve što bi moglo biti uzrok, sa
odgovorom da/ne i dokazom.

| moguć uzrok | odgovor | dokaz |
|---|---|---|
| Google ne može doći do stranica | **ne** | 149/149 vraća 200, 0 siročadi, sve u ≤3 klika od početne |
| stranice šalju `noindex` | **ne** | 0 od 149, provjereno i u HTML-u i u zaglavlju |
| `robots.txt` blokira | **ne** | `Allow: /`, ništa od `/js`, `/css`, `/images`, `/data` nije blokirano |
| sadržaj se crta JavaScriptom | **ne** (više) | 149/149: raw HTML = iscrtani DOM po proizvodima, cijenama, naslovima i JSON-LD-u |
| sitemap je pogrešan ili nepotpun | **ne** | 149 adresa, 0 duplikata, 0 preusmjerenja, 117/117 proizvoda unutra |
| canonical šalje snagu drugdje | **ne** | 149/149 self-referencing, 0 ponavljanja |
| duplikati adresa dijele snagu | **ne** | 313 varijanti provjereno, sve 301 |
| structured data je neispravna | **ne** | 0 nevalidnih blokova, 0 nedostajućih obaveznih polja |
| nedostaju `review`/`aggregateRating` | **nije uzrok** | opciona polja (sekcija 9) |
| ručna kazna | **ne** | Manual Actions prazno |
| brzina | **ne** | TTFB 0,65–0,78 s; CLS 0,001–0,025 na svim kategorijama |
| tanak sadržaj | **ne** | kategorije 1646–2252 riječi, proizvodi ~900 |

**Ono što iz koda ostaje kao objašnjenje:**

1. **Sajt nema linkova sa drugih sajtova.** Ovo se ne vidi u kodu i nije greška
   u kodu — ali je jedina veličina u kojoj konkurencija koja izlazi ispred vas
   ima prednost. Bez vanjskih linkova Google ima tačne, čitljive stranice bez
   ijednog signala da su vrijedne pokazivanja. **NIJE MOGUĆE PROVJERITI IZ
   KODA** koliko ih tačno ima — to je Search Console → Links.
2. **Vrijeme od popravke.** Sadržaj je počeo da se servira sa servera 11.
   avgusta, a fatalne greške tokom deployova su prestale 14. avgusta. Google
   mora ponovo obići i ponovo ocijeniti 149 stranica; vaš vlastiti Search
   Console to i pokazuje — indeksirano 73 → 280, neindeksirano 1540 → 657.
3. **Sve što je do koda — jeste ispravno.** Ovaj audit nije našao ni jedan
   tehnički razlog. To je i dobra i teška vijest: dobra jer se ne traži više
   kvarova, teška jer sljedeći korak nije u kodu.

---

## TABELA

| Stavka | Status | Fajl | Linija | Problem | Preporučena izmjena |
|---|---|---|---|---|---|
| Product schema | 🟢 ispravno | `product.php` | 358–411 | nema | ostaviti; eventualno `gtin`/`mpn` ako postoje |
| Reviews | 🟠 rizik van schema | `data/reviews.json`, `product.php` | 325–342, 908+ | 585 izmišljenih recenzija, nijedna pod 4,6; nisu u schemi | odluka vlasnika: obrisati ili zamijeniti pravim iz forme; **ne** vraćati u schemu |
| Reviews — tvrdnja o Googleu | 🟠 važno | `index.html` | **975–977** | „4.8 ★ Google ocjena · 22 recenzije" tvrdo upisano, pripisano Googleu | obrisati ili dokazati i linkovati GBP |
| aggregateRating | 🟢 isključeno | `product.php` / `products.php` | 385 / 367 | nema — `if (false && ...)` | ostaviti isključeno dok recenzije ne budu od kupaca |
| Canonical | 🟢 ispravno | `product.php`, `products.php` + 19 `.html` | 267 / 267 | nema | bez izmjene |
| robots.txt | 🟢 ispravno | `robots.txt` | 1–9 | nema; `Disallow: /php/` bezopasan | bez izmjene |
| Sitemap | 🟢 ispravno | `sitemap.php` | 129–219 | nema; 117/117 proizvoda unutra | bez izmjene |
| noindex | 🟢 ispravno | `korpa/checkout/hvala/404.html`, `product.php`, `products.php` | 6/6/18/5, 57, 46, 112 | nema; samo na 404 i na stranicama koje ne smiju u indeks | bez izmjene |
| Internal links | 🟢 ispravno | `index.html`, `products.php`, `product.php` | — | 0 siročadi, 117/117 nazad na kategoriju | više proizvoda linkovati sa početne |
| Product URLs | 🟢 ispravno | `php/slug.php`, `.htaccess` | 77, 87 | nema duplikata | održavati JS kopiju funkcije u `js/products.js` usklađenom |
| ItemList `numberOfItems` | 🟠 važno | `products.php` | **317 + 384** | piše 39, nabroji 20 | `count($_items)` ili ukinuti granicu od 20 |
| Dva izvora recenzija | 🟠 važno | `data/products.json` + `data/reviews.json` | `product.php:325–342` | tihi prelaz na stari skup ako glavni zafali | ostaviti jedan izvor |
| Cross-sell blok | 🟡 preporuka | `js/products.js` | crta se JS-om | Google ga u prvom prolazu ne vidi | prebaciti na server |

---

## REDOSLIJED — šta mijenjati, od 1 do 10

1. **Odluka o recenzijama** (vlasnik, ne kod). Tri puta: (a) obrisati sve i
   uvesti formu, (b) obrisati sve bez zamjene, (c) ostaviti privremeno uz jasnu
   oznaku da su ilustracija. Sve dalje zavisi od ovoga — zato je prvo.
2. **`index.html:975–977`** — obrisati ili dokazati „Google ocjena 4.8 · 22
   recenzije". Jedina stavka na sajtu koja nosi pravni i reputacijski rizik.
   ~5 minuta.
3. **`products.php:384`** — `numberOfItems` da odgovara broju stavki.
   ~10 minuta. Tačnost structured data.
4. **Vanjski linkovi** (vlasnik, ne kod): Google Business Profile → polje
   „Website"; Instagram bio; Facebook „Website". Ovo je jedina stvar koja realno
   mijenja pozicije. Danas.
5. **Request Indexing** u Search Consoleu za početnu i 4–5 najvažnijih
   kategorija, pa pratiti Crawl Stats.
6. **Cross-sell blok na server** (`js/products.js`) — ~200 znakova i nekoliko
   internih linkova više koje Google vidi odmah. ~40 minuta.
7. **`offers.url` i `seller` u ItemList stavkama** (`products.php:330–358`).
   ~15 minuta. Preporučeno polje za Merchant listings.
8. **Više proizvoda sa početne** — sada 4. Npr. 12 izdvojenih. ~20 minuta.
9. **Izbaciti `reviews` iz `products.json`** kad se 1. riješi, da ostane jedan
   izvor istine. ~10 minuta.
10. **`gtin`/`mpn`** — samo ako proizvodi imaju barkod proizvođača. Ako nemaju,
    preskočiti; ne izmišljati vrijednosti.

Stavke 1, 2, 4 i 5 nisu SEO-tehnika nego odluke i radnje van koda — i one su
jedine koje mogu promijeniti ono što vas muči. Sve od 3 do 10 su poboljšanja
tačnosti i količine podataka, ne popravke kvarova, jer kvarova u ovim oblastima
nema.

---

**Nijedan fajl sajta nije mijenjan. Nijedna recenzija nije obrisana, dodana ni
izmijenjena. Nijedna ocjena nije izmišljena.**
