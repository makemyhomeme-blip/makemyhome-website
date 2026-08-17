#!/usr/bin/env bash
# =============================================================================
# SVE — jedna komanda koja provjeri sve i da JEDAN odgovor:
#       smije li se sajt slati Googleu na indeksiranje ili ne.
#
# Zasto postoji
# -------------
# Do sada je postojalo vise alata, svaki sa svojim izlazom. Kad bi jedan
# rekao "0 gresaka", to je znacilo "0 od onoga sto TAJ alat provjerava" — a
# ne "0 gresaka na sajtu". Ta razlika je bila presudna, a nije se vidjela iz
# izvjestaja. Zato je izgledalo da se greske pojavljuju iz cista mira, iako
# su cijelo vrijeme bile tu, samo ih niko nije trazio.
#
# Ovdje se pokrece SVE sto postoji, redom, i na kraju stoji jedan zakljucak.
# Ako pise da nije spremno, ne salje se na indeksiranje.
#
# Sta se pokrece
# --------------
#    1. GIT        lokalno, GitHub i server nose isto; nista necommitovano
#    2. PRAVILA    52 pravila iz alat/ETALON.md (alat/dok-ne-bude.py)
#    3. OKO        pravi pregledac, 10 stranica x racunar i telefon
#    4. KORPA      22 provjere korpe i narudzbe u pravom pregledacu
#    5. PREGLEDAC  149 stranica: sto server posalje naspram sto pregledac pokaze
#    6. SCHEMA     strukturirani podaci, sirovo naspram iscrtanog + obavezna polja
#    7. BLJESAK    skok rasporeda i sadrzaj koji se pojavi pa nestane
#    8. ADRESE     duplikati, varijante adresa, ostaci starog sajta
#    9. OSTALO     lang, kosa crta, schema po tipu, robots.txt
#   10. SEO        149 adresa sa Googlebot user-agentom
#   11. IKONE      svaka ikona ima pravilo u CSS-u I znak u fontu
#   12. PROMJENE   sta se promijenilo od zadnje ciste provjere
#   + LIGHTHOUSE   Googleov alat na 14 tipova stranica (dodatno)
#
# Koraci 5, 6 i 7 su tu zato sto svi ostali gledaju samo jednu stranu — sta
# server posalje. Recenzije su jednom bile obrisane sa servera i provjera je
# javila "0 tragova", a vlasnik ih je i dalje gledao na telefonu, jer je
# pregledac cuvao stari js/products.js iz svog kesa.
#
# Pokretanje
# ----------
#     bash alat/sve.sh          # sve, svih 149 stranica (oko 60 min)
#     bash alat/sve.sh brzo     # uzorak od 12 stranica, bez sporih (oko 12 min)
#
# PAZI pri rucnom ciscenju prije pokretanja: "pkill -f" poredi CIJELU komandnu
# liniju, pa obrazac koji se poklapa sa imenom ovog fajla ubije i sam poziv.
# Desilo se dvaput. Alat sam cisti za sobom — ne treba ga pokretati uz pkill.
# =============================================================================
set -uo pipefail
cd "$(dirname "$0")/.." || exit 1

BRZO="${1:-}"
ISPIS=$(mktemp -d)
PALO=0
declare -a REZ

# PAD i PRESK se broje odvojeno. Ranije je i preskoceno racunato kao pad, pa je
# zakljucak tvrdio "palo 6 koraka" kad su stvarno pala dva, a cetiri su namjerno
# preskocena zbog 'brzo'. Broj koji nije tacan se prestane citati.
# Ali preskoceno se NE precutkuje: ako ista nije provjereno, to pise u zakljucku.
PRESKOCENO=0
zapisi() {           # zapisi <ime> <status> <detalj>
  REZ+=("$1|$2|$3")
  case "$2" in
    OK)    ;;
    PRESK) PRESKOCENO=$((PRESKOCENO+1)) ;;
    *)     PALO=$((PALO+1)) ;;
  esac
  printf '\n>>> %-10s %s  %s\n' "$1" "$2" "$3"
}

ocisti() {
  # Uglaste zagrade oko prvog slova: bez njih "pkill -f" pogodi i sopstvenu
  # komandnu liniju, jer i ona sadrzi taj isti tekst — pa alat ubije sam sebe.
  # Bas se to i desilo: cijeli prolaz je prekinut na pocetku, bez ijedne
  # poruke o razlogu.
  pkill -f '[p]osrednik.mjs' 2>/dev/null
  pkill -f '[p]hp -S 127.0.0.1:8899' 2>/dev/null
  return 0
}
trap ocisti EXIT

echo "==============================================================="
echo " PROVJERA SVEGA — Make My Home Decor"
echo " $(date '+%Y-%m-%d %H:%M')"
echo "==============================================================="

# ---------------------------------------------------------------- 1. GIT ----
echo; echo "--- 1/12  GIT · lokalno, GitHub i server ---"
GRANA=$(git rev-parse --abbrev-ref HEAD)
git fetch --quiet origin "$GRANA" 2>/dev/null
NEUPISANO=$(git status --porcelain | grep -c . || true)
IZA=$(git rev-list --count "HEAD..origin/$GRANA" 2>/dev/null || echo '?')
ISPRED=$(git rev-list --count "origin/$GRANA..HEAD" 2>/dev/null || echo '?')
if [ "$NEUPISANO" != "0" ]; then
  zapisi GIT PAD "$NEUPISANO izmjena nije commitovano — commituj i pushuj prije indeksiranja"
elif [ "$IZA" != "0" ] || [ "$ISPRED" != "0" ]; then
  zapisi GIT PAD "lokalno $IZA iza / $ISPRED ispred GitHuba — nije isto stanje"
else
  zapisi GIT OK "lokalno = GitHub ($GRANA), nista necommitovano"
fi
echo "    (poredjenje sa serverom radi pravilo G4 u sljedecem koraku)"

# ------------------------------------------------------------ 2. PRAVILA ----
echo; echo "--- 2/12  PRAVILA · 54 pravila iz ETALON.md ---"
if [ "$BRZO" = "brzo" ]; then
  python3 alat/dok-ne-bude.py > "$ISPIS/pravila.txt" 2>&1
else
  python3 alat/dok-ne-bude.py sve > "$ISPIS/pravila.txt" 2>&1
fi
KOD=$?
BROJ=$(grep -oE 'ZAVRSNO: [0-9]+ pravila' "$ISPIS/pravila.txt" | tail -1 | grep -oE '[0-9]+' || echo '?')
if [ $KOD -eq 0 ]; then
  zapisi PRAVILA OK "$BROJ pravila, nijedno nije palo"
else
  # Pravilo se u ispisu pojavi dva puta: u svojoj grupi i u zavrsnom sazetku.
  # Brojanje svih "^PAD" je zato davalo dvostruko vise nego sto je stvarno palo.
  zapisi PRAVILA PAD "$(grep -oE 'ZAVRSNO: [0-9]+ pravila provjereno, [0-9]+ palo' "$ISPIS/pravila.txt" | tail -1 | grep -oE '[0-9]+ palo' || echo '?') — detalji ispod"
  grep -A3 'PRAVE GRESKE' "$ISPIS/pravila.txt" | head -30
fi

# --------------------------------------------------------------- 3. OKO ----
echo; echo "--- 3/12  OKO · pravi pregledac, 10 stranica x 2 uredjaja ---"
ocisti; sleep 1
# Sa ruterom: bez njega lokalna kopija ne zna za lijepe adrese
# (/kategorija/x, /paneli/x) pa svaka pada na 404 stranicu, a alat to
# izmjeri kao da je stranica prazna. Vec se jednom desilo: osam stranica je
# prijavljeno kao "cijene se pojavljuju tek poslije JavaScripta", a nijedna
# od njih nije ni bila ucitana.
php -S 127.0.0.1:8899 alat/ruter.php > "$ISPIS/php.log" 2>&1 &
node alat/posrednik.mjs > "$ISPIS/posrednik.log" 2>&1 &
for _ in $(seq 40); do
  curl -s -o /dev/null http://127.0.0.1:8898/products.php && break
  sleep 1
done
if curl -s -o /dev/null http://127.0.0.1:8898/products.php; then
  node alat/oko.mjs > "$ISPIS/oko.txt" 2>&1
  if [ $? -eq 0 ]; then
    zapisi OKO OK "$(grep -c '^  OK' "$ISPIS/oko.txt") provjera, sve stranice izgledaju kako treba"
  else
    zapisi OKO PAD "$(grep -c '^  PAD' "$ISPIS/oko.txt") nalaza"
    grep '^  PAD' "$ISPIS/oko.txt" | head -12
  fi
else
  zapisi OKO PAD "lokalni server se nije podigao — provjera izgleda NIJE uradjena"
fi

# ------------------------------------------------------------- 4. KORPA ----
echo; echo "--- 4/12  KORPA · narudzba od pocetka do kraja ---"
if curl -s -o /dev/null http://127.0.0.1:8899/korpa.html; then
  node alat/korpa.mjs > "$ISPIS/korpa.txt" 2>&1
  if [ $? -eq 0 ]; then
    zapisi KORPA OK "$(grep -ciE '^\s*(OK|✓)' "$ISPIS/korpa.txt" || echo '?') provjera prolazi"
  else
    zapisi KORPA PAD "korpa ne radi kako treba"
    tail -15 "$ISPIS/korpa.txt"
  fi
else
  zapisi KORPA PAD "lokalni server nedostupan — korpa NIJE provjerena"
fi
# Lokalni server OSTAJE upaljen — trebaju ga koraci 5, 6 i 7 (pregledac,
# schema, bljesak). Gasi se poslije njih.

# ------------------------------------------------- 5. PREGLEDAC ------------
#
# Ovo je korak zbog kojeg je sve ostalo dobilo smisao.
#
# Svaki drugi alat gleda STA SERVER POSALJE. Kad je vlasnik obrisao recenzije,
# provjera je javila "0 tragova na svih 149 stranica" — a on ih je i dalje
# gledao na svom telefonu. Fajl js/products.js se servira sa "immutable" na
# godinu dana, a broj u ?v= nije bio podignut, pa je pregledac posluzio stari
# fajl iz svog kesa. Server cist, pregledac star, i nijedno pravilo to nije
# moglo vidjeti jer su sva gledala samo jednu stranu.
#
# Ovdje se svaka stranica ucitava DVA PUTA u istom Chromiumu — jednom sa
# iskljucenim, jednom sa ukljucenim JavaScriptom — i broji se isto: cijene,
# kartice proizvoda, plocice kategorija, JSON-LD blokovi, h1, h2, h3 i duzina
# vidljivog teksta. Ako se brojevi razlikuju, Google i covjek ne vide isto.
#
# Verziju kesiranih fajlova cuvaju pravila G16 i G17 u koraku 2.
echo; echo "--- 5/12  PREGLEDAC · server naspram onoga sto pregledac pokaze ---"
curl -s "https://makemyhome.me/sitemap.xml" 2>/dev/null | grep -o '<loc>[^<]*' | sed 's/<loc>//' > "$ISPIS/adrese.txt"
UKUPNO=$(grep -c . "$ISPIS/adrese.txt" || echo 0)
if [ "$BRZO" = "brzo" ]; then
  { head -3 "$ISPIS/adrese.txt"; grep '/kategorija/' "$ISPIS/adrese.txt" | head -4; grep '/paneli/' "$ISPIS/adrese.txt" | head -5; } > "$ISPIS/uzorak.txt"
  META="$ISPIS/uzorak.txt"
else
  META="$ISPIS/adrese.txt"
fi
BROJ_META=$(grep -c . "$META" || echo 0)
if [ "$BROJ_META" -gt 0 ] && curl -s -o /dev/null http://127.0.0.1:8898/; then
  MMH_IZLAZ="$ISPIS/render" node alat/r2-render.mjs "$META" > "$ISPIS/render.txt" 2>&1
  KOD_R=$?
  LOSE=$(grep -oE '\[!!\] [0-9]+' "$ISPIS/render.txt" | tail -1 | grep -oE '[0-9]+' || echo '?')
  PUKLE=$(grep -oE 'pukle [0-9]+' "$ISPIS/render.txt" | tail -1 | grep -oE '[0-9]+' || echo '?')
  # I izlazni kod I broj nalaza I broj stranica koje se nisu ucitale. Alat je
  # jednom javio "0 problema" dok nijedna stranica nije bila ucitana.
  if [ "$LOSE" = "0" ] && [ "$PUKLE" = "0" ] && [ $KOD_R -eq 0 ]; then
    zapisi PREGLEDAC OK "$BROJ_META stranica: server i pregledac pokazuju isto"
  else
    zapisi PREGLEDAC PAD "$LOSE stranica gdje JS gasi sadrzaj, $PUKLE se nije ucitalo"
    grep -A 6 'JavaScript gasi' "$ISPIS/render.md" 2>/dev/null | head -20
  fi
else
  zapisi PREGLEDAC PAD "lokalni server nedostupan ili nema adresa — NIJE provjereno"
fi

# ---------------------------------------------------- 6. SCHEMA -----------
#
# Strukturirani podaci moraju biti isti u sirovom HTML-u i poslije JavaScripta.
# Blok koji ubaci JavaScript Google u prvom prolazu cesto ne pokupi, a blok koji
# JavaScript obrise Google je vec procitao — i jedno i drugo je problem.
# Uz to se provjeravaju obavezna polja: Product (name, apsolutna slika, cijena,
# valuta, dostupnost), LocalBusiness (adresa, telefon, radno vrijeme) i
# BreadcrumbList (redoslijed 1..N).
echo; echo "--- 6/12  SCHEMA · strukturirani podaci, sirovo naspram iscrtanog ---"
if [ "$BROJ_META" -gt 0 ] && curl -s -o /dev/null http://127.0.0.1:8898/; then
  MMH_IZLAZ="$ISPIS/schema" node alat/r2-jsonld.mjs "$META" > "$ISPIS/schema.txt" 2>&1
  KOD_S=$?
  GR=$(grep -oE 'greske [0-9]+' "$ISPIS/schema.txt" | tail -1 | grep -oE '[0-9]+' || echo '?')
  PUK_S=$(grep -oE 'pukle [0-9]+' "$ISPIS/schema.txt" | tail -1 | grep -oE '[0-9]+' || echo 0)
  UP=$(grep -oE 'upozorenja [0-9]+' "$ISPIS/schema.txt" | tail -1 | grep -oE '[0-9]+' || echo '?')
  if [ "$GR" = "0" ] && [ "$PUK_S" = "0" ] && [ $KOD_S -eq 0 ]; then
    zapisi SCHEMA OK "$BROJ_META stranica: 0 gresaka, $UP upozorenja"
  else
    zapisi SCHEMA PAD "$GR stranica sa greskom u strukturiranim podacima"
    sed -n '/## Greske/,/## Tabela/p' "$ISPIS/schema.md" 2>/dev/null | head -20
  fi
else
  zapisi SCHEMA PAD "lokalni server nedostupan — NIJE provjereno"
fi

# ---------------------------------------------------- 7. BLJESAK ----------
#
# Sadrzaj koji se pojavi pa nestane, i raspored koji skoci pod prstom.
# Snima se na 200, 600, 1500 i 3000 ms od pocetka ucitavanja, na telefonu.
# Googleov prag za skok rasporeda (CLS) je 0,1.
echo; echo "--- 7/12  BLJESAK · skok rasporeda i sadrzaj koji nestane ---"
if curl -s -o /dev/null http://127.0.0.1:8898/; then
  MMH_IZLAZ="$ISPIS/bljesak" MMH_SNIMCI="$ISPIS/snimci" node alat/r2-bljesak.mjs > "$ISPIS/bljesak.txt" 2>&1
  KOD_B=$?
  BLJ=$(grep -oE 'bljesak [0-9]+' "$ISPIS/bljesak.txt" | tail -1 | grep -oE '[0-9]+' || echo '?')
  PUK_B=$(grep -oE 'pukle [0-9]+' "$ISPIS/bljesak.txt" | tail -1 | grep -oE '[0-9]+' || echo '?')
  CLS=$(grep -oE 'CLS>0,1 [0-9]+' "$ISPIS/bljesak.txt" | tail -1 | grep -oE '[0-9]+$' || echo '?')
  if [ "$BLJ" = "0" ] && [ "$CLS" = "0" ] && [ "$PUK_B" = "0" ] && [ $KOD_B -eq 0 ]; then
    zapisi BLJESAK OK "14 kategorija: bez bljeska, sve ispod praga 0,1"
  else
    zapisi BLJESAK PAD "bljesak na $BLJ, CLS iznad praga na $CLS, nije se ucitalo $PUK_B"
    grep -E '\[!' "$ISPIS/bljesak.txt" | head -8
  fi
else
  zapisi BLJESAK PAD "lokalni server nedostupan — NIJE provjereno"
fi
ocisti

# ---------------------------------------------------- 8. ADRESE -----------
echo; echo "--- 8/12  ADRESE · duplikati, varijante, ostaci starog sajta ---"
if [ "$BRZO" = "brzo" ]; then
  zapisi ADRESE PRESK "preskoceno jer je pokrenuto 'brzo'"
else
  python3 alat/r2-adrese.py > "$ISPIS/adrese-log.txt" 2>&1
  if grep -qE '^\[!' R2-ADRESE.md 2>/dev/null; then
    zapisi ADRESE PAD "$(grep -cE '^\[!' R2-ADRESE.md) nalaza — ista stranica na vise adresa"
    grep -E '^\[!' R2-ADRESE.md | head -6
  else
    zapisi ADRESE OK "nijedna varijanta adrese ne vraca 200 mimo sitemapa"
  fi
fi

# ---------------------------------------------------- 9. OSTALO -----------
echo; echo "--- 9/12  OSTALO · lang, kosa crta, schema po tipu, robots.txt ---"
if [ "$BRZO" = "brzo" ]; then
  zapisi OSTALO PRESK "preskoceno jer je pokrenuto 'brzo'"
else
  python3 alat/r2-ostalo.py > "$ISPIS/ostalo-log.txt" 2>&1
  if grep -qE '^\[!' R2-OSTALO.md 2>/dev/null; then
    zapisi OSTALO PAD "$(grep -cE '^\[!' R2-OSTALO.md) nalaza"
    grep -E '^\[!' R2-OSTALO.md | head -6
  else
    zapisi OSTALO OK "lang, kose crte, schema po tipu i robots.txt — sve uredu"
  fi
fi

# ------------------------------------------------------- 10. SEO ----------
echo; echo "--- 10/12  SEO · 149 adresa sa Googlebot user-agentom ---"
if [ "$BRZO" = "brzo" ]; then
  zapisi SEO PRESK "preskoceno jer je pokrenuto 'brzo'"
else
  bash seo-audit.sh > "$ISPIS/seo.txt" 2>&1
  OZN=$(grep -cE '^\[!' SEO-AUDIT-RAPORT.md 2>/dev/null || echo '?')
  if [ "$OZN" = "0" ]; then
    zapisi SEO OK "149 adresa, nijedna oznaka"
  else
    zapisi SEO PAD "$OZN oznaka u izvjestaju"
    grep -E '^\[!' SEO-AUDIT-RAPORT.md | head -8
  fi
fi

# ------------------------------------------------------------ 11. IKONE ----
echo; echo "--- 11/12  IKONE · pravilo u CSS-u i znak u fontu ---"
python3 alat/ikone.py provjeri > "$ISPIS/ikone.txt" 2>&1
python3 alat/fontovi.py >> "$ISPIS/ikone.txt" 2>&1
if grep -qiE 'PAZI|nema u CSS|GRESKA' "$ISPIS/ikone.txt"; then
  zapisi IKONE PAD "nesto fali — pokreni: python3 alat/ikone.py && python3 alat/fontovi.py upisi"
  grep -iE 'PAZI|nema u CSS|GRESKA' "$ISPIS/ikone.txt" | head -8
else
  zapisi IKONE OK "$(grep -oE 'koristi:?\s+[0-9]+' "$ISPIS/ikone.txt" | tail -1 | grep -oE '[0-9]+') ikona, sve imaju i pravilo i znak"
fi

# -------------------------------------------------------- 6. LIGHTHOUSE ----
#
# Googleov alat, isti onaj iza PageSpeed-a. Gleda drugim ocima od nasih
# pravila: kontrast boja, velicinu dugmadi, oznake formi, zaglavlja tabela.
# Kad je prvi put pusten, nasao je cetiri stranice sa nedovoljnim kontrastom
# koje nase provjere nisu mogle naci jer kontrast nikad nisu mjerile.
#
# Ako nije instaliran, TO SE KAZE i broji se kao pad. Tiho preskakanje bi
# znacilo da izvjestaj tvrdi vise nego sto je provjereno — a bas to je i
# bio problem: "sve prolazi" je znacilo "sve od onoga sto sam pogledao".
echo; echo "--- LIGHTHOUSE (dodatno) · Googleov alat na 14 tipova stranica ---"
if [ "$BRZO" = "brzo" ]; then
  zapisi LIGHTHOUSE PRESK "preskoceno jer je pokrenuto 'brzo' — pokreni bez 'brzo' prije indeksiranja"
elif ! ls /home/user/lighthouse/node_modules/.bin/lighthouse >/dev/null 2>&1 \
     && ! command -v lighthouse >/dev/null 2>&1 \
     && { echo "    Lighthouse nije nadjen — instaliram (nestane pri resetu okruzenja)…"
          mkdir -p /home/user/lighthouse \
            && (cd /home/user/lighthouse && npm init -y >/dev/null 2>&1 \
                && npm i lighthouse --no-audit --no-fund >/dev/null 2>&1)
          ! ls /home/user/lighthouse/node_modules/.bin/lighthouse >/dev/null 2>&1; }; then
  zapisi LIGHTHOUSE PAD "nije instaliran i instalacija nije uspjela — provjeri mrezu, pa: cd /home/user/lighthouse && npm i lighthouse"
else
  ocisti; sleep 1
  LH="${LH:-/home/user/lighthouse/node_modules/.bin/lighthouse}" \
    bash alat/lighthouse.sh > "$ISPIS/lighthouse.txt" 2>&1
  LHKOD=$?
  # Sudi se po izlaznom kodu i po tome da je izvjestaj stvarno ispisan.
  #
  # Prva verzija je trazila rijec "GRESKA" bez obzira na velika slova — a bas
  # ta rijec stoji i u uspjesnoj poruci "nijedna stvarna greska na 14 tipova
  # stranica". Lighthouse je prolazio, a alat je javljao pad. Tacno ona vrsta
  # laznog alarma zbog koje se pravim nalazima prestane vjerovati.
  if [ $LHKOD -eq 0 ] && grep -q 'nijedna stvarna greska' "$ISPIS/lighthouse.txt"; then
    zapisi LIGHTHOUSE OK "14 tipova stranica, nijedna stvarna greska"
  elif [ $LHKOD -ne 0 ] && ! grep -q 'ZAVRSNO\|STVARNE GRESKE' "$ISPIS/lighthouse.txt"; then
    zapisi LIGHTHOUSE PAD "alat se nije izvrsio do kraja — provjera NIJE uradjena"
    tail -12 "$ISPIS/lighthouse.txt"
  else
    zapisi LIGHTHOUSE PAD "nalazi ispod"
    sed -n '/STVARNE GRESKE/,$p' "$ISPIS/lighthouse.txt" | head -14
  fi
  ocisti
fi

# ------------------------------------------------------ 7. STA SE PROMIJENILO ----
#
# Ovo je odgovor na najcescu prituzbu: popravi se jedno, pokvari drugo, i to
# se primijeti tek sljedeci put. Pravila hvataju samo ono sto neko unaprijed
# zna da treba provjeriti. Ona NE mogu primijetiti da je sa 117 stranica nestao
# naslov, jer nijedno pravilo ne kaze koliko naslova stranica treba da ima.
#
# Zato se poslije svake ciste provjere snimi stanje svih 149 stranica. Pri
# sljedecem pokretanju se novo stanje uporedi sa tim snimkom i ispise se STA
# se promijenilo. Ako se promijenilo samo ono sto si mijenjao — u redu. Ako je
# usput nestalo nesto drugo — vidi se odmah, a ne za nedjelju dana.
#
# Tako je i nadjeno da je "Karakteristike – <ime>" nestalo sa 117 stranica.
echo; echo "--- 12/12  PROMJENE · sta se promijenilo od zadnje ciste provjere ---"
SNIMCI="$(dirname "$0")/snimci"
if [ -f "$SNIMCI/zadnji-ok.json.gz" ]; then
  python3 alat/snimak.py snimi sada > /dev/null 2>&1
  python3 alat/snimak.py uporedi zadnji-ok sada > "$ISPIS/promjene.txt" 2>&1
  if grep -q "NEMA RAZLIKE\|Nema razlike" "$ISPIS/promjene.txt"; then
    zapisi PROMJENE OK "nista se nije promijenilo od zadnje ciste provjere"
  else
    # Promjena NIJE greska — samo se mora vidjeti i potvrditi.
    # grep -c vec ispise 0 kad nema pogodaka; dodatni "|| echo 0" je ispisivao
    # jos jednu nulu, pa je poruka pucala na prelom reda i vidjela se samo "0".
    BROJ=$(grep -cE "^[a-z_0-9]+ +[0-9]+ stranica" "$ISPIS/promjene.txt" || true)
    zapisi PROMJENE OK "$BROJ vrsta izmjena — spisak ispod, provjeri da je samo ono sto si mijenjao"
    sed -n "/STA SE PROMIJENILO/,\$p" "$ISPIS/promjene.txt" | head -40
  fi
else
  echo "    (nema ranijeg snimka — pravim prvi, poredjenje krece od sljedeceg puta)"
  zapisi PROMJENE OK "prvi snimak, poredjenje krece od sljedeceg puta"
fi

# ------------------------------------------------------------- ZAKLJUCAK ----
echo
echo "==============================================================="
printf ' %-10s %s\n' "KORAK" "REZULTAT"
echo "---------------------------------------------------------------"
for r in "${REZ[@]}"; do
  IFS='|' read -r ime st det <<< "$r"
  printf ' %-10s %-4s %s\n' "$ime" "$st" "$det"
done
echo "==============================================================="
if [ $PALO -eq 0 ] && [ $PRESKOCENO -gt 0 ]; then
  echo
  echo "  PROLAZI SVE STO JE PROVJERENO — ali $PRESKOCENO koraka nije uradjeno."
  echo "  Pokrenuto je 'brzo'. Prije slanja na indeksiranje pokreni punu provjeru:"
  echo "      bash alat/sve.sh"
  echo "==============================================================="
  exit 2
elif [ $PALO -eq 0 ]; then
  # Cisto stanje postaje osnova za sljedecu uporedbu.
  python3 alat/snimak.py snimi zadnji-ok > /dev/null 2>&1
  echo
  echo "  SPREMNO ZA INDEKSIRANJE."
  echo
  echo "  Sve sto se provjerava — prolazi. U Search Console-u:"
  echo "    1. Sitemaps  → posalji https://makemyhome.me/sitemap.xml"
  echo "    2. URL Inspection → pocetna + 2-3 kategorije → Request Indexing"
  echo
  echo "  Ovo NE znaci da je sajt bez ijedne greske. Znaci da nema"
  echo "  nijedne od onih koje se provjeravaju. Sta se NE provjerava"
  echo "  pise u alat/ETALON.md, odjeljak 'Sta se ne provjerava'."
  echo "==============================================================="
  exit 0
else
  echo
  echo "  NIJE SPREMNO — palo $PALO korak(a), preskoceno $PRESKOCENO."
  echo "  Ne salji na indeksiranje dok ovo ne bude cisto."
  echo "  Puni izlaz: $ISPIS"
  echo "==============================================================="
  exit 1
fi
