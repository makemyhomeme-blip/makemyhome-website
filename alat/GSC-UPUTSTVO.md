# Google Search Console — šta uraditi, tačno ovim redom

Stanje na dan 12.08.2026. Sve provjereno na živom sajtu, ne lokalno.

---

## Korak 1 — Sitemap (5 minuta, uradi prvo)

GSC → **Sitemaps** (lijevi meni).

1. Ako u spisku već stoji `sitemap.xml`, klikni na njega → tri tačkice → **Remove sitemap**.
   Ne briše ništa sa sajta, samo tjera Google da ga pročita ispočetka.
2. U polje "Add a new sitemap" upiši:

   ```
   sitemap.xml
   ```

3. Submit. Status treba da pređe u **Success** i da pokaže **149 discovered URLs**.
   Ako pokaže manje od 149 — javi mi, znači da nešto nije preuzeto.

**Šta je novo:** sitemap sada nosi i **447 fotografija** (`<image:image>`). Ranije nije nosio
nijednu. To je jedini pouzdan način da Google slike uopšte sazna da postoje — a mi prodajemo
nešto što se kupuje očima. Ovo je najveća pojedinačna promjena u ovom krugu.

---

## Korak 2 — Provjeri da Google vidi sadržaj (10 minuta)

GSC → **URL Inspection** (traka na vrhu). Zalijepi adresu → Enter → **TEST LIVE URL** →
kad završi, **VIEW TESTED PAGE** → tab **HTML**.

Provjeri ove tri adrese i u HTML-u potraži (Ctrl+F) tekst iz kolone "traži":

| Adresa | Traži u HTML-u | Zašto |
|---|---|---|
| `https://makemyhome.me/` | `Mocha Oak` | izdvojeni proizvodi na početnoj |
| `https://makemyhome.me/products.html` | `Bambus Paneli` | mreža kategorija u katalogu |
| `https://makemyhome.me/paneli/drveni-panel-mocha-oak` | `product-gallery` | galerija na stranici proizvoda |

Ako se tekst nađe — Google ga vidi. **Ovo je bio glavni problem:** do ovih izmjena taj sadržaj
je crtao JavaScript, a Googlebot u prvom prolazu ne pokreće JavaScript. Vidio je praznu stranicu
tamo gdje mi vidimo proizvode. To je sada riješeno na **131 od 134 stranice**.

---

## Korak 3 — Zatraži indeksiranje (10 adresa dnevno, 3 dana)

Google dozvoljava oko **10 zahtjeva dnevno**. Zato ovim redom — najvažnije prvo.
Postupak za svaku: URL Inspection → zalijepi → Enter → **REQUEST INDEXING** → sačekaj poruku
"URL added to a priority crawl queue".

### Dan 1 — nosive stranice

```
https://makemyhome.me/
https://makemyhome.me/products.html
https://makemyhome.me/cjenovnik.html
https://makemyhome.me/kategorija/bambus-paneli
https://makemyhome.me/kategorija/3d-letvice
https://makemyhome.me/kategorija/akusticni-paneli
https://makemyhome.me/kategorija/spc-pod
https://makemyhome.me/kategorija/pu-kamen
https://makemyhome.me/contact.html
https://makemyhome.me/inspiracija.html
```

### Dan 2 — vodiči (ovi hvataju pretrage tipa "paneli za kupatilo")

```
https://makemyhome.me/paneli-za-kupatilo.html
https://makemyhome.me/tv-zid.html
https://makemyhome.me/paneli-ili-lamperija.html
https://makemyhome.me/spc-ili-laminat.html
https://makemyhome.me/akusticni-paneli-kancelarija.html
https://makemyhome.me/dostava-crna-gora.html
https://makemyhome.me/montaza.html
https://makemyhome.me/faq.html
https://makemyhome.me/about.html
https://makemyhome.me/kategorija/flex-stone
```

### Dan 3 — po jedan predstavnik svake kategorije proizvoda

Kad Google indeksira jednog iz kategorije, ostale nađe sam preko sitemapa i unutrašnjih linkova.

```
https://makemyhome.me/paneli/drveni-panel-mocha-oak
https://makemyhome.me/paneli/mermerni-panel-mystic-marble
https://makemyhome.me/paneli/tekstilni-panel-perla
https://makemyhome.me/paneli/3d-letvica-espresso-teak
https://makemyhome.me/paneli/akusticni-panel-aku051-zlatni-hrast-geometrik
https://makemyhome.me/paneli/pu-kamen-poliuretanski-kamen-bijeli
https://makemyhome.me/paneli/classic-panel-terrazzo
https://makemyhome.me/paneli/spc04-spc-laminat
https://makemyhome.me/paneli/mdf-panel-mdf004-deblji
https://makemyhome.me/paneli/flex-stone-linear-travertine-1-2x0-6m-fleksibilni-kamen
```

---

## Korak 4 — Removals: NE diraj

U GSC postoji **Removals**. **Nemoj ga koristiti.**

Stare WordPress adrese su provjerene — sve 332 iz GSC izvoza:

- **142** šalju 301 na novu adresu, **jednim skokom**, i sve završavaju na stranici koja vraća 200
- **182** vraćaju 410 (smeće tipa `?add-to-cart=941` — te stranice nikad nisu ni trebale postojati)
- **8** su žive stranice
- **0** grešaka, 0 lanaca preusmjeravanja, 0 petlji

301 i 410 su tačno ono što Google treba da vidi da bi sam očistio indeks. Removals bi samo
sakrio adrese na 6 mjeseci a onda bi se vratile — pogoršao bi stvar, ne popravio.

---

## Korak 5 — Šta gledati narednih 14 dana

**Pages** (ranije "Coverage"):

- broj u **Indexed** treba da raste ka 149
- ako se pojavi *"Crawled – currently not indexed"* → normalno prvih 7-10 dana, Google je vidio
  stranicu i čeka. Ne radi ništa.
- ako se pojavi *"Discovered – currently not indexed"* na više od 30 adresa poslije 14 dana →
  javi mi, to znači da Google ne smatra sadržaj dovoljno vrijednim i treba drugačiji potez
- ako se pojavi *"Duplicate without user-selected canonical"* → **javi mi odmah**, to je greška
  u kodu i ja je popravljam

**Performance** → prebaci na **Last 28 days** i uporedi sa prethodnim periodom. Prvo raste
**Impressions** (koliko puta se pojavimo), tek poslije **Clicks**. Ako impressions rastu a
klikovi ne — to je posao za naslove i opise, i to onda ja doradim.

**Core Web Vitals** → ovo mi treba od tebe. Ja brzinu ne mogu izmjeriti odavde (nemam pristup
Google alatima za mjerenje). Kad se u GSC-u napuni izvještaj, pošalji mi šta piše pod
**Mobile** — koliko URL-ova je "Poor" i "Need improvement". Bez toga radim naslijepo.

---

## Korak 6 — Google Business Profile (ovo je za lokalnu pretragu najvažnije)

Ovo nije GSC nego **business.google.com**, ali za pretragu "zidni paneli Podgorica" vrijedi
više od svega ostalog.

Provjeri da je **identično** ovome, znak po znak:

- Naziv: **Make My Home Decor**
- Adresa: **Vojvode Maša Đurovića 41, City Kvart, 81000 Podgorica**
- Telefon: **069 105 222**
- Sajt: **https://makemyhome.me**
- Radno vrijeme: pon–pet 09:00–20:00, subota 10:00–17:00, nedjelja zatvoreno

Ako se i jedan znak razlikuje od onoga što stoji na sajtu, Google ne poveže sa sigurnošću
profil i sajt, a to je upravo veza koja izbacuje firmu u mapu i u lokalne rezultate.
(Adresa je sad svuda na sajtu **41** — u `llms.txt` je pisalo 41-43, ispravio sam.)

---

## Odgovor na pitanje: hoće li nas Google sad izbaciti

Iskreno, bez uljepšavanja.

**Šta je sigurno riješeno.** Google do sada bukvalno nije mogao pročitati najveći dio sajta:
izdvojene proizvode na početnoj, mrežu kategorija u katalogu, galerije na svih 117 stranica
proizvoda, spisak kategorija u podnožju na 15 stranica, i nijednu sliku u sitemapu. Sve je to
crtao JavaScript poslije učitavanja, a Googlebot u prvom prolazu JavaScript ne pokreće. Broj
stranica koje su Google-u izgledale prazno pao je sa **134 na 3** (a te tri su takve namjerno —
korpa, plaćanje, hvala, i one su `noindex`).

To nije bilo "sitno tehničko dotjerivanje". To je bila razlika između sajta koji za Google
postoji i sajta koji ne postoji. Flex Decor i Bau Decor su sve vrijeme imali obično serversko
renderovanje, i to je razlika koju si tražio — ne linkovi.

**Šta ne mogu obećati.** Ne mogu ti garantovati poziciju, i ne bi ti valjalo da ti neko to
obeća. Google ne radi po pravilu "popravljeno je, dakle prvi si". Ono što mogu reći sa sigurnošću:
prepreka koja je do sada onemogućavala rangiranje je uklonjena. Sada se takmičimo, a do sada
nismo ni bili u trci.

**Koliko traje.** Iz iskustva sa ovakvim izmjenama:
- 3–7 dana: Google ponovo obiđe stranice koje si zatražio
- 2–4 nedelje: indeks se napuni, impressions počnu da rastu
- 6–8 nedelja: pozicije se slegnu i tek tada se vidi prava slika

Nemoj suditi po prvih 10 dana. I nemoj ništa mijenjati u tom periodu — ako se u međuvremenu
mijenja sadržaj, ne zna se šta je dalo rezultat.

**Šta bi sad najviše pomoglo, a nije kod.** Fotografije. **71 od 117 proizvoda nema nijednu
fotografiju u enterijeru** — samo sliku uzorka. Cijele kategorije su bez ijedne: Flex Stone
(10/10), aluminijum lajsne (8/8), SPC pod (5/5), MDF (5/5), kožni (4/4), metalni (4/4), a 3D
letvice 21 od 28. Sitemap sada nosi slike do Google-a, ali može nositi samo ono što postoji.
Fotografija panela na stvarnom zidu kod kupca vrijedi više od bilo koje moje izmjene koda —
to je ono što se u pretrazi slika klikne i po čemu se kupuje. Ako mi pošalješ slike, ja ih
ubacim i one iste večeri idu u sitemap.
