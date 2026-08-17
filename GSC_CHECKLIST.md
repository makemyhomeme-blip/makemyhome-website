# Google Search Console — šta da otvoriš i šta da gledaš

Ovo je jedini dio koji ja **ne mogu** provjeriti: Search Console traži prijavu
na tvoj Google nalog. Sve ostalo na sajtu je izmjereno i zapisano u
`R3-INDEKSABILNOST.md`.

Otvori: **https://search.google.com/search-console** → izaberi `makemyhome.me`.

Ispod je devet mjesta, redom. Za svako piše: šta da vidiš, šta je normalno,
šta je problem, i šta da mi pošalješ ako je problem.

---

## 1. Indexing → Pages

**Gdje:** lijevi meni → *Indexing* → *Pages*

**Šta da vidiš:** dva broja gore — *Indexed* i *Not indexed*, i ispod spisak
razloga.

**Šta je normalno**
- *Indexed* raste iz sedmice u sedmicu i ide ka **149**
- pod *Not indexed* stoje razlozi tipa *Page with redirect*, *Not found (404)*,
  *Alternate page with proper canonical tag*, *Blocked by robots.txt* —
  **ali samo za stare WordPress adrese** (`/product/...`, `/shop/`, `/feed/`,
  `/wp-...`). Njih smo namjerno ugasili sa 410 i 301.

**Šta je problem**
- bilo koja adresa koja počinje sa `/paneli/` ili `/kategorija/` u spisku
  *Not indexed* — te moraju biti indeksirane
- razlog **„Discovered – currently not indexed"** na više od 20 naših adresa
  (znači da Google zna za njih ali ne stiže/ne želi da ih obiđe)
- razlog **„Crawled – currently not indexed"** na našim adresama koji stoji
  duže od 3–4 sedmice
- **„Blocked by robots.txt"** na bilo kojoj našoj adresi — to bi bila greška,
  jer robots.txt ne blokira ništa naše (provjereno: 149/149 dozvoljeno)

**Šta da mi pošalješ ako je problem**
Klikni na razlog → *Examples* → screenshot spiska adresa, ili kopiraj 5–10
adresa kao tekst. Uz to broj *Indexed* i *Not indexed*.

---

## 2. Indexing → Sitemaps

**Gdje:** lijevi meni → *Indexing* → *Sitemaps*

**Šta da vidiš:** red sa `sitemap.xml`, kolone *Status*, *Discovered URLs*,
*Last read*.

Ako sitemap nije poslan: u polje upiši `sitemap.xml` i klikni **Submit**.

**Šta je normalno**
- Status: **Success**
- *Discovered URLs*: **149** (tačno toliko ih ima; provjereno danas)
- *Last read*: datum od prije najviše nekoliko dana

**Šta je problem**
- Status *Couldn't fetch* ili *Has errors*
- *Discovered URLs* manji od 149 ili 0
- *Last read* stariji od mjesec dana

**Šta da mi pošalješ:** screenshot tog reda, i ako piše greška — klikni na
sitemap pa pošalji šta piše u detaljima.

---

## 3. URL Inspection

**Gdje:** polje za pretragu na samom vrhu, upiši punu adresu.

Spisak adresa koje treba provjeriti je u sekciji **„21. URL Inspection"** na
kraju ovog dokumenta.

**Šta da vidiš za svaku:** velika poruka gore — *URL is on Google* ili
*URL is not on Google*, pa raširi *Page indexing*.

**Šta je normalno**
- **URL is on Google**
- *Discovery → Sitemaps*: `https://makemyhome.me/sitemap.xml`
- *Crawl → Crawled as*: **Googlebot smartphone**
- *Crawl → Crawl allowed*: **Yes**
- *Indexing → Indexing allowed*: **Yes**
- *User-declared canonical* = *Google-selected canonical* = **ista adresa koju
  si upisao**

**Šta je problem**
- *URL is not on Google* za adresu koja je u sitemapu
- *Google-selected canonical* pokazuje na **drugu** adresu nego što si upisao
  → to znači da Google misli da su dvije naše stranice ista stvar
- *Crawl allowed: No* ili *Indexing allowed: No*
- *Page fetch: Failed*

**Šta da mi pošalješ:** screenshot cijelog *Page indexing* dijela, posebno
oba reda o canonical-u.

**Bonus koji vrijedi uraditi:** klikni **Test Live URL** → pa **View tested
page** → *Screenshot* i *HTML*. Ako na tom screenshotu vidiš proizvode i
cijene, znači da Google vidi isto što i kupac. (Ja sam to izmjerio i jeste —
ali ovo je potvrda iz njihovog alata.)

---

## 4. Shopping / Merchant listings i Enhancements

**Gdje:** lijevi meni → *Shopping* → *Merchant listings*, i *Enhancements* →
*Products* / *Breadcrumbs* / *FAQ* / *Sitelinks searchbox*, ako postoje.

**Šta je normalno**
- *Products* / *Merchant listings*: **Valid** za oko 117 stavki
- *Breadcrumbs*: Valid za 149
- Upozorenja tipa **„Missing field review"** ili **„Missing field
  aggregateRating"** → **to nije greška.** Ta polja su opciona i namjerno ih
  nemamo, jer nemamo prave recenzije kupaca. Ostavi ih.

**Šta je problem**
- bilo šta u koloni **Invalid** (crveno)
- „Missing field **price**", „**priceCurrency**", „**availability**",
  „**image**", „**name**" → to su obavezna polja i moraju biti tu
  (provjereno danas: 117/117 ih ima)

**Šta da mi pošalješ:** screenshot ekrana, i ako ima Invalid — klikni na
grešku pa pošalji spisak adresa.

---

## 5. Experience → Core Web Vitals

**Gdje:** lijevi meni → *Experience* → *Core Web Vitals* → *Mobile*

**Šta je normalno**
- zeleno (*Good*) ili poruka **„Not enough data"**

„Not enough data" je očekivano dok sajt ima malo posjeta — Google ovdje koristi
podatke stvarnih korisnika, ne test. To **nije** greška.

**Šta je problem**
- crveno (*Poor*) na grupi URL-ova, posebno zbog **CLS** ili **LCP**

**Šta da mi pošalješ:** screenshot mobilnog izvještaja i naziv grupe URL-ova
koja je crvena.

---

## 6. Security & Manual Actions → Manual actions

**Gdje:** lijevi meni → *Security & Manual Actions* → *Manual actions*

**Šta je normalno:** **No issues detected**

**Šta je problem:** bilo koja stavka, posebno *Spammy structured markup* ili
*Thin content*

**Šta da mi pošalješ:** screenshot i cijeli tekst kazne.

---

## 7. Security & Manual Actions → Security issues

**Šta je normalno:** **No issues detected**

**Šta je problem:** bilo šta (hakovan sadržaj, malware, obmanjujuće stranice)

**Šta da mi pošalješ:** screenshot i spisak pogođenih adresa.

---

## 8. Performance → Search results

**Gdje:** lijevi meni → *Performance* → *Search results*. Gore uključi **sve
četiri** kućice: *Total clicks*, *Total impressions*, *Average CTR*,
*Average position*. Period: **Last 3 months**.

**Šta da vidiš:** kartice *Queries*, *Pages*, *Countries*, *Devices*.

**Šta je normalno u našoj situaciji**
- impresije rastu; klikovi kreću sporije
- prosječna pozicija se popravlja (broj **pada** — od 50 ka 20 pa ka 10)
- pod *Pages* se pojavljuju i `/kategorija/` i `/paneli/` adrese, ne samo početna

**Šta je problem**
- impresije stoje na nuli duže od 3–4 sedmice poslije indeksiranja
- pod *Pages* se pojavljuje **samo** početna, a nijedna kategorija ni proizvod
- upiti su samo ime firme („make my home decor"), a nijedan opisni upit
  („bambus paneli", „3d letvice cijena", „zidni paneli Podgorica")

**Šta da mi pošalješ:** screenshot kartice *Queries* (top 20) i kartice *Pages*
(top 20), za zadnja 3 mjeseca.

---

## 9. Settings → Crawl stats

**Gdje:** lijevi meni → *Settings* → *Crawl stats* → *Open report*

**Šta je normalno**
- *Total crawl requests* raste
- *Average response time* ispod ~1000 ms (mjereno sa naše strane: 650–780 ms)
- *By response*: skoro sve **200**; nešto **301** i **410** je uredu (to su
  stare WordPress adrese)
- *By file type*: HTML, Image, JavaScript, CSS
- *By Googlebot type*: najviše **Smartphone**

**Šta je problem**
- *By response*: bilo koji **5xx**
- *Average response time* preko 2000 ms
- broj zahtjeva pada ka nuli

**Šta da mi pošalješ:** screenshot glavnog grafa i tabele *By response*.

---

# 21. URL Inspection — tačan spisak za ručnu provjeru

Otvori *URL Inspection* i provjeri **ovih osam adresa**, jednu po jednu.
Za svaku gledaj pet stvari: Discovery, Crawl, Indexing, Canonical, Mobile.

| # | adresa | zašto baš ova |
|---|---|---|
| 1 | `https://makemyhome.me/` | početna — najvažnija |
| 2 | `https://makemyhome.me/kategorija/bambus-paneli` | najveća kategorija, 39 proizvoda |
| 3 | `https://makemyhome.me/kategorija/3d-letvice` | druga po veličini, 28 proizvoda |
| 4 | `https://makemyhome.me/paneli/drveni-panel-golden-teak` | proizvod sa popustom |
| 5 | `https://makemyhome.me/paneli/3d-letvica-obsidian` | proizvod koji si nedavno preimenovao |
| 6 | `https://makemyhome.me/paneli/mdf-panel-mdf001` | mala kategorija |
| 7 | `https://makemyhome.me/paneli/spc04-spc-laminat` | jedini proizvod koji se prodaje po m² |
| 8 | `https://makemyhome.me/paneli/alu-lajsna-l1-crna-srednja-lajsna` | proizvod bez kalkulatora m² |

**Za svaku adresu zapiši:**

```
adresa:
URL is on Google?           da / ne
Discovery – Sitemaps:       da / ne
Crawl allowed:              Yes / No
Page fetch:                 Successful / …
Indexing allowed:           Yes / No
User-declared canonical:
Google-selected canonical:
Crawled as:                 Googlebot smartphone / desktop
Last crawl:
```

**Najvažniji red je pretposljednji.** Ako se *User-declared canonical* i
*Google-selected canonical* razlikuju, pošalji mi to odmah — to je jedini
signal koji iz koda ne mogu vidjeti, a može objasniti zašto se stranica ne
prikazuje.

---

# Šta uraditi odmah, ovim redom

1. **Sitemaps** → ako `sitemap.xml` nije poslan, pošalji ga.
2. **URL Inspection** za adrese 1, 2 i 3 iz tabele → klikni **Request Indexing**.
   (Više od 10–15 dnevno Google ionako ne prihvata.)
3. Sutradan provjeri **Indexing → Pages** i uporedi broj *Indexed* sa jučerašnjim.
4. Ako neka od osam adresa pokaže *URL is not on Google* poslije 7 dana od
   Request Indexing — pošalji mi screenshot.

# Šta nije u Search Console-u, a najviše nedostaje

Sajt praktično **nema linkova sa drugih sajtova**. To se vidi u
*Links → External links* — ako ta lista ima svega par redova, to je i dalje
najveća prepreka, i jedina koju kod ne može riješiti.

Tri stvari koje možeš uraditi danas, besplatno:
- **Google Business Profile** → polje *Website* → `https://makemyhome.me/`
- **Instagram** → bio → isti link
- **Facebook** → *Website* polje na stranici → isti link
