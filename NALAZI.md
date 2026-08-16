# NALAZI — 16. avgust 2026.

Izvori: `SEO-AUDIT-RAPORT.md` (149 URL-ova, čist bash/curl, sa servera)
i `JS-RAPORT.md` (15 stranica, Chromium sa Googlebot UA, 412×915).

Provjeravane su tačno dvije hipoteze koje su ostale otvorene:

| hipoteza | odgovor | dokaz |
|---|---|---|
| Kategorije `/kategorija/*` nisu vidljive Googleu | **NE** | svih 14 vraća 200, bez preusmjerenja, sa canonicalom na sebe, sa 1646–2252 riječi i 17–81 cijenom **u sirovom HTML-u** (SEO-AUDIT-RAPORT.md, sekcija 8) |
| Proizvodi se iscrtavaju JavaScriptom | **NE** | na svih 15 mjerenih stranica broj cijena u sirovom HTML-u je ≥ broja poslije JS-a; 0 JS grešaka (JS-RAPORT.md, zbirna tabela) |

---

## 1. BLOKATORI [!!] — sve što sprječava Google da vidi ili indeksira stranice

**Nema nijednog.**

Jedina `[!!]` oznaka u oba izvještaja je ova:

**B1 (nije kvar) — `noindex` na korpi**
`korpa.html:6` → `<meta name="robots" content="noindex, follow">`
JS-RAPORT.md, linija 460.

Isto imaju i `checkout.html:6`, `hvala.html:18`, `404.html:5`. Sve četiri su
stranice koje ne smiju u indeks, nijedna nije u sitemapu (provjereno: 0 pogodaka
u `sitemap.xml`), i `follow` propušta link-sok dalje. Ovo je tako namjerno i
ostaje.

Za ostalo, mjereno na svih 149 URL-ova sa servera:

- 0 stranica sa `noindex` u HTML-u ili u zaglavlju `X-Robots-Tag`
- 0 preusmjerenja, 0 grešaka — svih 149 vraća 200
- svaka stranica ima canonical koji pokazuje na samu sebe, nijedan se ne ponavlja
- `robots.txt` ne blokira ništa, sitemap je u njemu prijavljen
- sitemap: 200, 149 apsolutnih URL-ova, bez duplikata, 492 slike
- svaka stranica ima JSON-LD; svih 117 `/paneli/` ima Product šemu
- sve varijante domena (www, http, IP) završe na `https://makemyhome.me/`
- TTFB 0,65–0,78 s

**Zaključak: tehnički, ništa ne sprječava Google. Ono što je sprječavalo
(sadržaj iz JavaScripta i HTTP 500 tokom deployova) popravljeno je 11. i 14.
avgusta.**

---

## 2. OZBILJNO [!] — sve što šteti rangiranju

**O1 — na `/kategorija/bambus-paneli` JavaScript sakrije sadržaj koji je server
ispisao**

| fajl | linija | šta radi |
|---|---|---|
| `products.php` | 284 | za `bambus-paneli` skuplja proizvode iz svih 5 podkategorija i ispisuje **39 kartica** (81 cijena, 2252 riječi) u `#products-container`, koji je otvoren sa `style="display:grid"` |
| `js/products.js` | 220–222 | prepoznaje da je `bambus-paneli` roditelj i zove `showSubcategoryGrid()` |
| `js/products.js` | 273–274 | `showSubcategoryGrid()` **bezuslovno** postavi `#products-container` na `display:none` i umjesto njega prikaže 5 pločica podkategorija |

Izmjereno (JS-RAPORT.md, zbirna tabela, red `/kategorija/bambus-paneli`):

```
                sirovi HTML   poslije JS
znak €          81            0
duzina teksta   8585          3249
```

Dvije posljedice:

1. **Google u prvom prolazu vidi jednu stranicu, a u drugom drugu.** Sirovi HTML
   je katalog od 39 proizvoda sa cijenama; poslije iscrtavanja to je raskrsnica
   sa 5 pločica i 62 % manje teksta. Ovo nije cloaking (Googlebot i posjetilac
   dobiju isti HTML), ali jeste tačno onaj obrazac koji vodi u
   „Crawled – currently not indexed".
2. **Posjetilac vidi bljesak.** Prvo se iscrtaju 39 kartica, pa nestanu. To je
   ujedno i skok rasporeda na najvećoj kategoriji na sajtu.

Ovo je jedina stranica na sajtu sa tim obrascem. Lisnate kategorije imaju
zaštitu — `js/products.js:404–406` i `js/products.js:407`: „products.php je
kartice već ispisao… mreža se ne crta po drugi put". `showSubcategoryGrid()` tu
zaštitu nema.

**Drugih `[!]` nalaza koji štete rangiranju nema.** Sve ostalo što je označeno
`[!]` je ili greška mjerenja ili posljedica ovog okruženja — razloženo u
sekciji 3.

---

## 3. SITNICE

**S1 — `seo-audit.sh` nikad nije stvarno uporedio meta opise**
`seo-audit.sh:182` → `echo "$u|${t:-NEMA}|${d:-NEMA}"`
Razdvajač je `|`, a **title sadrži `|`** („Proizvodi | Make My Home Decor").
Zato u `awk -F'|'` polje `$3` nije opis nego rep naslova — „ Make My Home Decor"
— isti za 148 stranica. Odatle `[!] 1 dupliranih meta opisa`
(SEO-AUDIT-RAPORT.md, linija 68) i odatle provjera naslova na
`seo-audit.sh:188–191` poredi samo dio naslova prije `|`.
Stvarno stanje, provjereno Pythonom nad istim keširanim fajlovima:
**0 dupliranih opisa, svi 70–165 znakova, 0 dupliranih naslova.**
Kvar je u alatu, ne na sajtu.

**S2 — pad dužine teksta na svim stranicama je asimetrija mjerenja**
`js-check.js:73–75` sirovi HTML mjeri tako što skine tagove sa **cijelog**
dokumenta; `js-check.js:183` poslije JS-a mjeri `document.body.innerText`, dakle
samo **vidljivi** tekst. Zato svaka stranica pokaže pad (npr. `/` 8128 → 7597).
To nije gubitak sadržaja. Jedini stvarni pad je O1.

**S3 — 15 × `ERR_CONNECTION_RESET` u konzoli**
`index.html:363` → `googletagmanager.com/gtag/js?id=G-4LLQCZ8CV4` (i isto u
ostalim stranicama). Ovaj kontejner nema izlaz na te domene, pa svaki učitani
dokument prijavi jednu grešku. Na živom sajtu radi. Nije nalaz.

**S4 — 1 neuspio zahtjev na `/contact.html`**
`contact.html:409` → Google Maps embed, isti razlog kao S3. Iframe već ima
`loading="lazy"` i `title`, tako da tu nema šta da se popravlja.

**S5 — `[!] Nema nijedan JSON-LD blok` za `/products.html`**
JS-RAPORT.md linija 68. Lokalno je posluženo iz statičkog fajla `products.html`;
živi sajt na toj adresi pokreće `products.php` i vraća **2 JSON-LD bloka**
(provjereno curlom). Nije nalaz. Za `/korpa.html` (linija 461) JSON-LD nije ni
potreban — stranica je `noindex`.

---

## 4. PLAN POPRAVKI

### U kodu

**Korak 1 — O1, `/kategorija/bambus-paneli` (~30 min + provjera)**

Odluka koju treba donijeti: koja slika te stranice je prava.

Preporuka: **zadržati ono što server ispisuje** (39 proizvoda sa cijenama), a
pločice podkategorija prikazati **iznad** njih kao filter, umjesto da zamijene
mrežu. Time stranica ostaje jednako jaka i za Google i poslije iscrtavanja,
bljesak nestaje, a posjetilac i dalje može da suzi izbor.

Izmjena je na jednom mjestu: `js/products.js:273–274`, po uzoru na zaštitu koja
već postoji na `js/products.js:404–407` — ako `#products-container` već sadrži
`.product-card`, ne dirati ga.

Provjera poslije izmjene: `alat/sve.sh`, pa ponovo `node js-check.js` — red
`/kategorija/bambus-paneli` mora imati €81 i poslije JS-a.
Commit: samo `js/products.js`.

**Korak 2 — S1, `seo-audit.sh:182` (~10 min)**

Zamijeniti razdvajač `|` znakom koji se ne pojavljuje u naslovima (tab), i
uskladiti `awk -F` na linijama 184–191. Bez toga alat i dalje ne provjerava
opise. Commit: samo `seo-audit.sh`.

Redoslijed je bitan: prvo Korak 1 (jedini stvarni nalaz), pa Korak 2 (alat).
Ukupno ~40 minuta rada, dva commita.

### Ne rješava se u kodu

Tehnička provjera je iscrpljena. Ništa na sajtu ne stoji između Googlea i
stranica — 149/149 dostupno, čitljivo bez JavaScripta, ispravno označeno.
Ono što je ostalo nije kod:

- **Vanjski linkovi.** Sajt ima praktično jedan (oglas na radnik.me). Ovo je
  jedina veličina u kojoj konkurencija koja izlazi ispred nas ima prednost, i
  jedina koju kod ne može promijeniti.
- **Google Business Profile** — polje „Website" mora pokazivati na
  `https://makemyhome.me/`.
- **Instagram bio i Facebook „Website"** — isti link.
- **Request Indexing** u Search Consoleu, za početnu i za 3–4 najvažnije
  kategorije.

Indeks se u međuvremenu popravlja u vašim podacima: indeksirano 73 → 280,
neindeksirano 1540 → 657, a svih 10 redova u „zašto nije indeksirano" su stare
WordPress adrese koje vraćaju 410 — nijedna nije naša stranica.

---

**Kod nije diran.** Čeka se odobrenje plana; onda se popravlja stavka po
stavci, commit po stavci.
