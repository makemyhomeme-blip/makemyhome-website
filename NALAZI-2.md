# NALAZI-2 — druga runda, 16. avgust 2026.

> **STANJE: O1 i O2 popravljeni i pušteni na sajt** (commit `d807a3a`).
> CLS na `/kategorija/bambus-paneli` 0,517 → **0,001**; ostalih 13 kategorija
> 0,05 → 0,001–0,025. Sirovi HTML i iscrtani DOM su na svih 149 adresa isti po
> proizvodima, cijenama, pločicama, naslovima i JSON-LD blokovima.
> Poslije deploya: `provjera.py` 50/50, `seo-audit.sh` 0 oznaka, `r2-jsonld`
> 0 grešaka. Detalji ispod ostaju kao zapis šta je bilo i zašto.

Prva runda je gledala **statični HTML** i prošla čisto, a kvar je bio u
**ponašanju** — JavaScript je gasio 39 proizvoda na `/kategorija/bambus-paneli`.
Ova runda zato mjeri šta ostane **poslije** izvršavanja JavaScripta.

Kako je mjereno: svaka od 149 stranica je učitana **dva puta u istom Chromiumu**
— jednom sa isključenim, jednom sa uključenim JavaScriptom — i u oba slučaja se
broji isto, u živom DOM-u. Tako se poredi jabuka sa jabukom.

| alat | šta gleda | izvještaj |
|---|---|---|
| `alat/r2-render.mjs` | 149 stranica, sa i bez JS-a | `R2-RENDER.md` |
| `alat/r2-js-skener.py` | uzrok u kodu, ne posljedica | `R2-JS.md` |
| `alat/r2-adrese.py` | sve adrese koje vraćaju 200 | `R2-ADRESE.md` |
| `alat/r2-meta-test.sh` | da li alat uopšte hvata greške | `R2-ALAT.md` |
| `alat/r2-bljesak.mjs` | bljesak, CLS, LCP, snimci kroz vrijeme | `R2-BLJESAK.md` |
| `alat/r2-ostalo.py` | lang, kosa crta, schema, robots | `R2-OSTALO.md` |

---

## 1. BLOKATORI [!!]

**Nema nijednog.**

Ista klasa greške kao na bambusu je tražena na svih 149 adresa:

- stranica gdje JavaScript **ukloni** kartice proizvoda: **0**
- stranica gdje JavaScript **ukloni** cijene: **0**
- stranica gdje JavaScript **ukloni** više od 5 % vidljivog teksta: **0**
- stranica koje se nisu učitale: **0**

Svih 14 kategorija, kartice prije → poslije JavaScripta (`R2-RENDER.md`):

```
bambus-paneli 39→39   bambus-drveni 9→9    bambus-tekstilni 9→9
bambus-mermerni 13→13 bambus-metalni 4→4   bambus-kozni 4→4
3d-letvice 28→28      akusticni-paneli 9→9 aluminijum-lajsne 8→8
spc-pod 5→5           pu-kamen 9→9         classic 4→4
mdf 5→5               flex-stone 10→10
```

Nijedna kategorija ne gubi nijednu karticu. Bug nije bio u dijeljenom kodu —
`showSubcategoryGrid()` se poziva **samo** za roditeljsku kategoriju, a to je
jedino `bambus-paneli` (`js/products.js:220–222`).

---

## 2. OZBILJNO [!]

### O1 — `/kategorija/bambus-paneli` skače raspored: CLS **0,517** — POPRAVLJENO

Googleov prag je 0,1. Ova stranica je **pet puta iznad njega**, jedina na sajtu.

| kategorija | CLS |
|---|---|
| `/kategorija/bambus-paneli` | **0,517** |
| sve ostale (13) | 0,001 – 0,073 |

**Uzrok, tačno:**

| fajl | linija | šta radi |
|---|---|---|
| `products.php` | 688 | za kategoriju ispisuje `<div id="category-grid" style="display:none;">` sa 8 kartica **glavnih** kategorija |
| `products.php` | 284 | za `bambus-paneli` ispisuje 39 kartica proizvoda u `#products-container`, vidljivo |
| `js/products.js` | 306–307 | JavaScript prepiše `#category-grid` sa 6 pločica podkategorija |
| `js/products.js` | 273 | i onda ga upali (`display:grid`) — **iznad** 39 već iscrtanih proizvoda |

Pločice se ubace iznad sadržaja koji je već na ekranu, pa se sve ispod pomjeri
nadolje. To je taj skok.

**Ovo je posljedica jučerašnje popravke i to treba reći otvoreno.** Izmjereno je
i jedno i drugo stanje, na istoj stranici, u istom pregledaču — stara verzija
`js/products.js` je ubačena kroz presretanje zahtjeva, bez diranja fajlova:

```
prije popravke:  CLS 0,050   vidljivih kartica  0
sada:            CLS 0,517   vidljivih kartica 39
```

Ranije skoka nije bilo jer nije bilo ni sadržaja — JavaScript je 39 kartica
ugasio prije nego se raspored stigne pomjeriti. Sadržaj je sada tu, ali stiže u
dva koraka.

**Rješenje** (jedna izmjena rješava i O1 i O2): neka **server** ispiše tih 6
pločica podkategorija u `#category-grid` i ostavi ga vidljivim, isto kao što već
ispisuje 39 proizvoda. Tada JavaScript zatiče gotovu mrežu, ništa ne ubacuje,
ništa se ne pomjera. Mjesto: `products.php:688` (grana kada je `$cat` roditelj)
plus zaštita u `js/products.js:306–307` — ne prepisuj mrežu ako su pločice već
tu, isto kao što `showCategoryProducts()` već radi za proizvode.

### O2 — Google na toj stranici u prvom prolazu vidi pogrešnih 8 naslova — POPRAVLJENO

Na istoj stranici, `h2` prije i poslije JavaScripta:

```
bez JS-a (ono što Google čita prvo):  51 h2  — među njima 8 glavnih kategorija:
    Bambus Paneli, 3D Letvice, Akustični Paneli, MDF Paneli,
    PU Kamen, Flex Stone, Alu Lajsne, SPC Pod
poslije JS-a (ono što vidi čovjek):   49 h2  — među njima 6 podkategorija:
    Drveni, Tekstilni, Mermerni, Metalni, Kožni, Classic
```

Znači: Googleu u sirovom HTML-u stiže osam naslova **drugih kategorija**, a
šest naslova podkategorija bambusa — ono što stranica zapravo nudi — ne stigne
nikad, jer postoje samo poslije JavaScripta. Isti uzrok kao O1, isti popravak.

---

## 3. SITNICE

**S1 — skrivena mreža kategorija na svih 14 kategorija** — POPRAVLJENO (mreža se na lisnatim kategorijama više ne ispisuje)
`products.php:688`. Svaka kategorija nosi 8 kartica glavnih kategorija sa
`display:none`. Google ih čita u HTML-u, posjetilac ih ne vidi nikad. Nije kazna
i nije cloaking — svi dobijaju isti HTML — ali je 8 nepotrebnih naslova i 8
linkova na svakoj kategoriji. Na `bambus-paneli` je to i uzrok O2.

**S2 — mrtvi statični fajlovi koje je zamijenio PHP**
`products.html`, `product.html`, `index.html` stoje na disku, a `.htaccess` te
adrese prepisuje na `products.php`, `product.php` i `pocetna.php`. Sajt uvijek
vraća PHP verziju (provjereno), ali `products.html:279–319` još nosi **stari**
inline skript koji gasi `#category-grid` — sadržaj star nekoliko mjeseci. Dok
pravilo u `.htaccess` radi, to niko ne vidi; ako ikad zakaže, servira se ta
zastarjela stranica.

**S3 — 18 „nezaštićenih" mjesta iz skenera koda**
`R2-JS.md` ih izlistava. Pregledani su svi:
- `js/main-v4.js:47–102` — kutija za pretragu, prazni svoje rezultate. U redu.
- `js/products.js:604` — `onerror` gasi sličicu koja se nije učitala. U redu.
- `js/products.js:500` — upisuje ime proizvoda u mrvice; ista vrijednost koju je
  server već ispisao. U redu.
- `products.php:834–839` — radi samo kad adresa ima `?cat=`, a lijepe adrese ga
  nemaju; funkcija odmah izađe. U redu.
- `admin/dashboard.php` — admin panel, Google ga ne vidi (`robots.txt`).
- `products.html:279–319` — mrtav fajl, vidi S2.

Nijedno od njih ne gazi ono što server ispiše. Skener ostaje — sada postoji i
hvatalo bi novi slučaj kao bambus.

**S4 — dvije kategorije su izmjerene u 3 umjesto 4 tačke**
`akusticni-paneli` i `spc-pod` u `R2-BLJESAK.md`. Snimak na 3000 ms nije stigao.
Ne mijenja nalaz (kartice se ne mijenjaju ni u jednoj tački), ali mjerenje nije
potpuno.

---

## 4. Provjera samog alata (meta-provjera)

Pet stvarnih stranica je prekopirano u lažni sajt i u njih je namjerno ubačeno
7 grešaka, pa je `seo-audit.sh` pušten preko toga (`alat/r2-meta-test.sh`).

| ubačena greška | alat prijavio? |
|---|---|
| `noindex` u meta robots | da |
| dvije stranice isti canonical | da |
| obrisan `<title>` | da |
| obrisan meta opis | da |
| dva `<h1>` | da |
| neispravan JSON-LD | da |
| slika bez `alt` | da |

**7 od 7.** Nijedno pravilo nije slijepo.

### Ali — pet grešaka je nađeno u samim alatima ove runde

Ovo je važnije od tabele iznad, jer su sve ove greške **prijavljivale kvarove
kojih na sajtu nema**:

| alat | šta je bilo | popravljeno |
|---|---|---|
| `alat/ruter.php` | prvo je gledao postoji li fajl, pa je za `/products.html` servirao zastarjeli statični fajl umjesto `products.php`; mjerenje je zbog toga prijavilo „JS dodaje 66 % teksta" | preslikavanja idu prva |
| `alat/r2-adrese.py` | crawler nije poštovao `<base href>`, pa je prijavio **36 nepostojećih 404 adresa** (`/kategorija/about.html`…) | `<base>` se čita |
| `alat/r2-adrese.py`, `alat/r2-ostalo.py` | čitali su `href` iz `<script>` blokova, pa je `href="/kategorija/' + slug + '"` (JavaScript) prijavljen kao nedosljedna kosa crta | skripte se izbace prije čitanja |
| `alat/r2-ostalo.py` | `Offer` je ugniježđen u `Product.offers`, a tražen je među tipovima čvorova — prijavio je da **svih 117** proizvoda nema ponudu, i u istom dahu da nijedan nema ponudu bez cijene | gleda se u podacima |
| `alat/r2-meta-test.sh` | dva obrasca za poređenje nisu odgovarala tekstu izvještaja („canonical **adresa** se ponavlja", „bez \`alt\`"), pa je prijavio 2 slijepa pravila kojih nema | obrasci ispravljeni |

Pouka je ista kao i sa razdvajačem `|` u `seo-audit.sh`: **svaki alarm se prvo
provjeri ručno na jednom slučaju, pa tek onda upiše u izvještaj.**

---

## 5. 280 indeksiranih naspram 149 u sitemapu

Traženo je gdje je razlika. Odgovor: **na sajtu duplikata nema.**

| provjera | rezultat |
|---|---|
| crawl od početne, dubina 3 | **151** adresa u linkovima |
| od toga u sitemapu | 149 |
| ostatak | `/korpa.html` i `/checkout.html` — namjerno `noindex`, nisu u sitemapu |
| varijante `/x`, `/x/`, `/x.html` — **313 provjerenih** | nijedna ne vraća 200; sve su 301 ili 404 |
| parametri `?utm_source`, `?fbclid`, `?gclid`, `?page`, `?filter`, `?id`, `?ref` | svi vraćaju 200 sa canonicalom **bez** parametra — Google ih spaja u jednu adresu |
| `?cat=` i `?category=` | 301 na `/kategorija/<ime>` |
| ostaci WordPressa (`/wp-admin/`, `/feed/`, `/xmlrpc.php`, `/wp-json/…`, `/index.php`, `/category/…`) | **410** |
| stare adrese (`/shop/`, `/product/…`, `/product-category/…`, `/about`, `/kontakt`, `/blog/`) | **301** na tačnu novu adresu |

Znači: 280 nije 280 živih stranica. To su adrese koje je Google **ikad**
indeksirao — uključujući stare WordPress adrese koje sada vraćaju 410 i 301.
Taj broj pada sam od sebe kako Google ponovo obiđe te adrese; ništa se u kodu ne
može ubrzati. Ovo je i ranije viđeno u vašem Search Consoleu: svih 10 redova u
„zašto nije indeksirano" su bile stare WordPress adrese, nijedna naša stranica.

---

## 6. Ostalo — sve prolazi

| provjera | rezultat |
|---|---|
| `lang` | `sr-ME` na svih 149, nijedna bez |
| `hreflang` | nema ga i ne treba — sajt je jednojezičan |
| kosa crta na kraju | 6269 internih linkova, **nijedan** sa kosom crtom — dosljedno |
| `BreadcrumbList` + `ItemList` na `/kategorija/*` | svih 14 ima oboje |
| `Product` + `Offer` + cijena + dostupnost na `/paneli/*` | svih 117 ima sve četiri |
| `robots.txt` blokira `/js`, `/css`, `/images`, `/fa`, `/data`? | ne blokira ništa od toga; sve vraća 200 |
| LCP na kategorijama | 176 ms – 2452 ms, sve ispod praga od 2,5 s |
| bljesak (sadržaj se pojavi pa nestane) | **0** kategorija |
| lazy loading krije proizvode od Googlebota? | ne — kartice su u HTML-u, `loading="lazy"` je samo na slikama |

**Rich Results Test** nema javni API — Googleov alat traži prijavu, pa se ne
može pozvati iz skripte. Zamjena: `alat/r2-ostalo.py` parsira svaki JSON-LD blok
i provjerava tipove i obavezna polja (tabela iznad).
**Search Console API** traži OAuth pristup vlasnika naloga, kojeg ovo okruženje
nema — Crawl Stats ostaje na vama: Settings → Crawl stats.

---

## 7. PLAN POPRAVKI

### Korak 1 — O1 + O2 zajedno (~40 min)

Server ispisuje 6 pločica podkategorija za `bambus-paneli`, vidljivo, umjesto 8
skrivenih glavnih kategorija.

- `products.php:688` — kada je `$cat` roditeljska kategorija, u `#category-grid`
  ide 6 podkategorija i **bez** `display:none`
- `js/products.js:306–307` — ne prepisuj mrežu ako pločice već postoje
  (`grid.querySelector('.cat-card')`), isto kao zaštita u
  `showCategoryProducts()`

Time se rješava: CLS 0,517 → očekivano ispod 0,1; Google u prvom prolazu dobija
6 pravih naslova podkategorija umjesto 8 tuđih; skrivena mreža na toj stranici
nestaje.

Provjera poslije: `node alat/r2-bljesak.mjs` (CLS mora pasti ispod 0,1) i
`node alat/r2-render.mjs` (h2 bez JS-a = h2 sa JS-om). Commit: dva fajla.

### Korak 2 — S1, skrivena mreža na ostalih 13 kategorija (~20 min)

`products.php:688` — na lisnatoj kategoriji `#category-grid` uopšte ne ispisivati
(sada se ispiše pa sakrije). Manje HTML-a, 8 nepotrebnih naslova i linkova manje
po stranici. Nije hitno i nije kazna — ali je čišće i besplatno.

### Korak 3 — S2, mrtvi statični fajlovi (~10 min, odluka je vaša)

`products.html`, `product.html`, `index.html` na serveru. Dvije mogućnosti:
obrisati ih (čisto, ali ako `.htaccess` pravilo ikad nestane, te adrese daju
404 umjesto zastarjele stranice), ili ih ostaviti i osvježiti. **Preporuka:
ostaviti kako jeste** — dok pravilo radi, niko ih ne vidi, a brisanje fajlova sa
servera nosi rizik koji nije vrijedan dobitka.

### Ne rješava se u kodu

Ništa novo u odnosu na jučerašnji nalaz. Tehnički je sajt čist: 149/149 čitljivo
bez JavaScripta, bez duplikata, bez blokiranja, sa ispravnom schemom. Ostaje
vanjski dio — linkovi ka sajtu, Google Business Profile, Instagram i Facebook.

---

**Kod nije diran.** Čeka se odobrenje plana; onda stavka po stavci, commit po
stavci.
