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
#   1. GIT      lokalno, GitHub i server nose isto; nista necommitovano
#   2. PRAVILA  54 pravila iz alat/ETALON.md (alat/dok-ne-bude.py)
#   3. OKO      pravi pregledac, 10 stranica x racunar i telefon
#   4. KORPA    22 provjere korpe i narudzbe u pravom pregledacu
#   5. IKONE    svaka ikona ima pravilo u CSS-u I znak u fontu
#   6. LIGHTHOUSE  Googleov alat na 14 tipova stranica
#   7. PROMJENE   sta se promijenilo od zadnje ciste provjere
#
# Pokretanje
# ----------
#     bash alat/sve.sh          # sve (oko 40 min)
#     bash alat/sve.sh brzo     # bez sporih pravila (oko 10 min)
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

zapisi() {           # zapisi <ime> <status> <detalj>
  REZ+=("$1|$2|$3")
  if [ "$2" != "OK" ]; then PALO=$((PALO+1)); fi
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
echo; echo "--- 1/7  GIT · lokalno, GitHub i server ---"
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
echo; echo "--- 2/7  PRAVILA · 54 pravila iz ETALON.md ---"
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
  zapisi PRAVILA PAD "$(grep -c '^PAD' "$ISPIS/pravila.txt" || echo '?') pravila palo — detalji ispod"
  grep -A3 'PRAVE GRESKE' "$ISPIS/pravila.txt" | head -30
fi

# --------------------------------------------------------------- 3. OKO ----
echo; echo "--- 3/7  OKO · pravi pregledac, 10 stranica x 2 uredjaja ---"
ocisti; sleep 1
php -S 127.0.0.1:8899 -t . > "$ISPIS/php.log" 2>&1 &
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
echo; echo "--- 4/7  KORPA · narudzba od pocetka do kraja ---"
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
ocisti

# ------------------------------------------------------------- 5. IKONE ----
echo; echo "--- 5/7  IKONE · pravilo u CSS-u i znak u fontu ---"
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
echo; echo "--- 6/7  LIGHTHOUSE · Googleov alat na 14 tipova stranica ---"
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
echo; echo "--- 7/7  PROMJENE · sta se promijenilo od zadnje ciste provjere ---"
SNIMCI="$(dirname "$0")/snimci"
if [ -f "$SNIMCI/zadnji-ok.json.gz" ]; then
  python3 alat/snimak.py snimi sada > /dev/null 2>&1
  python3 alat/snimak.py uporedi zadnji-ok sada > "$ISPIS/promjene.txt" 2>&1
  if grep -q "NEMA RAZLIKE\|Nema razlike" "$ISPIS/promjene.txt"; then
    zapisi PROMJENE OK "nista se nije promijenilo od zadnje ciste provjere"
  else
    # Promjena NIJE greska — samo se mora vidjeti i potvrditi.
    BROJ=$(grep -cE "^[a-z_]+ +[0-9]+ stranica" "$ISPIS/promjene.txt" || echo 0)
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
if [ $PALO -eq 0 ]; then
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
  echo "  NIJE SPREMNO — palo $PALO od 7 koraka."
  echo "  Ne salji na indeksiranje dok ovo ne bude cisto."
  echo "  Puni izlaz: $ISPIS"
  echo "==============================================================="
  exit 1
fi
