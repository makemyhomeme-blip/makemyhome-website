#!/usr/bin/env bash
# =============================================================================
# r2-meta-test.sh — provjera samog alata.
#
# Zasto postoji:
# seo-audit.sh je mjesec dana prijavljivao "1 duplirani meta opis" kojeg nema,
# a prave opise nikad nije ni uporedio — razdvajac je bio "|", a naslovi ga
# sadrze. Alat koji ne hvata gresku je gori od nikakvog alata, jer daje mir.
#
# Ovdje se pravi mali lazni sajt od pet stvarnih stranica u koje se NAMJERNO
# ubace greske, pa se preko njega pusti seo-audit.sh. Sve sto alat ne prijavi
# je slijepo pravilo.
#
# Ubacene greske:
#   1. noindex u meta robots
#   2. dvije stranice sa istim canonical-om
#   3. obrisan <title>
#   4. obrisan meta description
#   5. dva <h1> na istoj stranici
#   6. neispravan JSON-LD (pokvaren JSON)
#   7. <img> bez alt
#
#   bash alat/r2-meta-test.sh   →  R2-ALAT.md
# =============================================================================
set -uo pipefail
cd "$(dirname "$0")/.." || exit 1

SAJT="https://makemyhome.me"
PORT=8897
TEST="${TMPDIR:-/tmp}/mmh-meta-test"
IZV="R2-ALAT.md"
CURL=(curl -sk --cacert /root/.ccr/ca-bundle.crt --max-time 25)

rm -rf "$TEST"; mkdir -p "$TEST"
: > "$IZV"
r() { printf '%s\n' "$*" >> "$IZV"; }

r "# R2 — provjera samog alata"
r ""
r "Napravljeno: $(date -u '+%Y-%m-%d %H:%M UTC')"
r ""
r "Pet stvarnih stranica je prekopirano u lazni sajt i u njih su ubacene greske."
r "Alat je pusten preko tog sajta. Sve sto nije prijavljeno je **slijepo pravilo**."
r ""

# --------------------------------------------------------- lazni sajt -------
"${CURL[@]}" "$SAJT/faq.html"                > "$TEST/t1.html"
"${CURL[@]}" "$SAJT/montaza.html"            > "$TEST/t2.html"
"${CURL[@]}" "$SAJT/about.html"              > "$TEST/t3.html"
"${CURL[@]}" "$SAJT/uslovi.html"             > "$TEST/t4.html"
"${CURL[@]}" "$SAJT/dostava-crna-gora.html"  > "$TEST/t5.html"

# 1. noindex
sed -i 's|<head>|<head>\n<meta name="robots" content="noindex, nofollow">|' "$TEST/t1.html"
# canonical na lokalni sajt (da ne bi svi bili "tudji")
for n in 1 2 3 4 5; do
  sed -i "s|<link rel=\"canonical\" href=\"[^\"]*\"|<link rel=\"canonical\" href=\"http://127.0.0.1:$PORT/t$n.html\"|" "$TEST/t$n.html"
done
# 2. duplirani canonical — t2 pokazuje na t1
sed -i "s|href=\"http://127.0.0.1:$PORT/t2.html\"|href=\"http://127.0.0.1:$PORT/t1.html\"|" "$TEST/t2.html"
# 3. i 4. obrisan title i opis
perl -0pi -e 's|<title>.*?</title>||s'                     "$TEST/t3.html"
perl -0pi -e 's|<meta[^>]*name="description"[^>]*>||s'     "$TEST/t3.html"
# 5. drugi h1
perl -0pi -e 's|</h1>|</h1><h1>Drugi naslov koji ne bi smio postojati</h1>|' "$TEST/t4.html"
# 6. pokvaren JSON-LD
perl -0pi -e 's|(<script type="application/ld\+json">)|$1 {"@context": "https://schema.org", "@type": "Thing", "name": ,,, }</script><script type="application/ld+json">|' "$TEST/t4.html"
# 7. slika bez alt
perl -0pi -e 's|(<img[^>]*?)\s+alt="[^"]*"|$1|' "$TEST/t5.html"

cat > "$TEST/robots.txt" <<EOF
User-agent: *
Allow: /
Sitemap: http://127.0.0.1:$PORT/sitemap.xml
EOF

{
  echo '<?xml version="1.0" encoding="UTF-8"?>'
  echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
  for n in 1 2 3 4 5; do
    echo "<url><loc>http://127.0.0.1:$PORT/t$n.html</loc><lastmod>2026-08-16</lastmod></url>"
  done
  echo '</urlset>'
} > "$TEST/sitemap.xml"

php -S "127.0.0.1:$PORT" -t "$TEST" > /dev/null 2>&1 &
SRV=$!
trap 'kill $SRV 2>/dev/null' EXIT
sleep 2

# ------------------------------------------------------- pusti alat ---------
MMH_BAZA="http://127.0.0.1:$PORT" MMH_IZVJESTAJ="$TEST/raport.md" MMH_KES="$TEST/kes" \
  bash seo-audit.sh > "$TEST/izlaz.txt" 2>&1
RAP="$TEST/raport.md"

if [ ! -s "$RAP" ]; then
  r "[!!] seo-audit.sh nije napravio izvjestaj. Izlaz:"
  r '```'; tail -20 "$TEST/izlaz.txt" >> "$IZV"; r '```'
  exit 1
fi

# --------------------------------------------------------- ocjena -----------
r "## Rezultat"
r ""
r "| # | ubacena greska | gdje | alat prijavio? |"
r "|---|---|---|---|"

PALO=0
ocijeni() {  # $1 = broj, $2 = opis, $3 = gdje, $4 = obrazac koji mora biti u izvjestaju
  if grep -qiE "$4" "$RAP"; then
    r "| $1 | $2 | \`$3\` | **da** |"
  else
    r "| $1 | $2 | \`$3\` | **NE — slijepo** |"
    PALO=$((PALO+1))
  fi
}

ocijeni 1 "noindex u meta robots"        "t1.html" '\[!!\][^|]*noindex|noindex.*t1\.html'
ocijeni 2 "dvije stranice isti canonical" "t2.html" '\[!!?\][^|]*canonical[^|]*ponavlja'
ocijeni 3 "obrisan title"                 "t3.html" '\[!!?\][^|]*bez title'
ocijeni 4 "obrisan meta opis"             "t3.html" '\[!!?\][^|]*bez meta opisa'
ocijeni 5 "dva h1"                        "t4.html" '\[!!?\][^|]*(vise od jednog H1|dva H1|H1)'
ocijeni 6 "neispravan JSON-LD"            "t4.html" '\[!!?\][^|]*(JSON-LD|strukturiran)'
ocijeni 7 "slika bez alt"                 "t5.html" '\[!!?\][^|]*bez .?alt'

r ""
if [ "$PALO" -eq 0 ]; then
  r "[i] Alat je uhvatio svih 7 ubacenih gresaka."
else
  r "[!!] Alat NIJE uhvatio **$PALO** od 7 ubacenih gresaka. Ta pravila su slijepa."
fi

r ""
r "## Izvjestaj koji je alat napravio nad laznim sajtom"
r ""
r '```'
grep -E '^\[!' "$RAP" >> "$IZV" || echo "(nijedna oznaka [!] ni [!!])" >> "$IZV"
r '```'

echo "Gotovo → $IZV  (neuhvacenih: $PALO/7)"
