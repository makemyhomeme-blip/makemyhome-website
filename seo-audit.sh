#!/usr/bin/env bash
# =============================================================================
# seo-audit.sh — potpuna tehnicka provjera sajta. Samo curl/grep/sed/awk.
#
# Skine sve adrese iz sitemap.xml (podrzava i sitemap index), povuce svaku
# stranicu kao Googlebot, snimi je lokalno, pa analizira offline.
#
#   bash seo-audit.sh        →  SEO-AUDIT-RAPORT.md
#
# Oznake:  [!!] blokator · [!] ozbiljno · [i] informacija
# =============================================================================
set -uo pipefail
cd "$(dirname "$0")" || exit 1

# Adresa sajta i putanje se mogu preusmjeriti kroz okruzenje — tako
# alat/r2-meta-test.sh pusti ovaj isti skript preko laznog sajta u koji su
# namjerno ubacene greske, da se vidi hvata li ih uopste.
BAZA="${MMH_BAZA:-https://makemyhome.me}"
GBOT="Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)"
CURL=(curl -sk --cacert /root/.ccr/ca-bundle.crt --max-time 30)
KES="${MMH_KES:-.seo-audit-kes}"
IZV="${MMH_IZVJESTAJ:-SEO-AUDIT-RAPORT.md}"

mkdir -p "$KES"; : > "$IZV"
r()  { printf '%s\n' "$*" >> "$IZV"; }
h2() { r ""; r "## $*"; r ""; }
h3() { r ""; r "### $*"; r ""; }
ime(){ echo "$1" | sed 's|^https\?://||; s|[/?=&]|_|g'; }

r "# SEO audit — makemyhome.me"
r ""
r "Napravljeno: $(date -u '+%Y-%m-%d %H:%M UTC') · Googlebot user-agent"
r ""
r "**[!!]** blokator · **[!]** ozbiljno · **[i]** informacija"

# ---------------------------------------------------------------- 1. DOMEN --
h2 "1. Verzije domena"
r '```'
for u in "http://makemyhome.me/" "https://makemyhome.me/" "http://www.makemyhome.me/" \
         "https://www.makemyhome.me/" "https://makemyhome.me/index.html"; do
  printf '%-40s %s\n' "$u" \
    "$("${CURL[@]}" -A "$GBOT" -o /dev/null -w '%{http_code} skokova:%{num_redirects} -> %{url_effective}' -L "$u")" >> "$IZV"
done
r '```'
KANON=$("${CURL[@]}" -A "$GBOT" -o /dev/null -w '%{url_effective}' -L "$BAZA/")
RAZ=0
for u in "http://makemyhome.me/" "http://www.makemyhome.me/" "https://www.makemyhome.me/" "https://makemyhome.me/index.html"; do
  [ "$("${CURL[@]}" -A "$GBOT" -o /dev/null -w '%{url_effective}' -L "$u")" != "$KANON" ] && RAZ=$((RAZ+1))
done
[ $RAZ -eq 0 ] && r "[i] Sve verzije zavrsavaju na \`$KANON\`. Nema duplikata domena." \
               || r "[!!] $RAZ verzija NE zavrsava na istoj adresi — Google vidi vise sajtova."

# ------------------------------------------------------------ 2. ROBOTS -----
h2 "2. robots.txt"
KODR=$("${CURL[@]}" -A "$GBOT" -o "$KES/robots.txt" -w '%{http_code}' "$BAZA/robots.txt")
r "Status: \`$KODR\`"
r '```'; cat "$KES/robots.txt" >> "$IZV"; r '```'
[ "$KODR" != "200" ] && r "[!!] robots.txt ne vraca 200."
grep -qiE '^[[:space:]]*Disallow:[[:space:]]*/[[:space:]]*$' "$KES/robots.txt" \
  && r "[!!] \`Disallow: /\` — cio sajt zabranjen." || r "[i] Nema \`Disallow: /\`."
for res in '\.js' '\.css' '/images' '\.png' '\.jpg' '\.webp'; do
  grep -qiE "^[[:space:]]*Disallow:.*$res" "$KES/robots.txt" \
    && r "[!!] robots.txt blokira \`$res\` — Google ne moze iscrtati stranicu."
done
grep -qi '^Sitemap:' "$KES/robots.txt" \
  && r "[i] Sitemap naveden." || r "[!] robots.txt ne navodi sitemap."

# ----------------------------------------------------------- 3. SITEMAP -----
h2 "3. Sitemap"
META=$("${CURL[@]}" -A "$GBOT" -o "$KES/sitemap.xml" -w '%{http_code}|%{num_redirects}|%{content_type}' "$BAZA/sitemap.xml")
IFS='|' read -r SKOD SSKOK STIP <<< "$META"
r "Status \`$SKOD\` · preusmjerenja \`$SSKOK\` · tip \`$STIP\`"
[ "$SKOD" != "200" ] && r "[!!] sitemap.xml ne vraca 200."
[ "$SSKOK" != "0" ]  && r "[!] sitemap.xml ide kroz preusmjerenje."

if grep -q '<sitemapindex' "$KES/sitemap.xml"; then
  r "[i] Ovo je sitemap **index** — skidam podsitemape."
  : > "$KES/spojeno.xml"
  grep -o '<loc>[^<]*</loc>' "$KES/sitemap.xml" | sed 's/<[^>]*>//g' | while read -r ps; do
    "${CURL[@]}" -A "$GBOT" "$ps" >> "$KES/spojeno.xml"
  done
  IZVOR="$KES/spojeno.xml"
else
  IZVOR="$KES/sitemap.xml"
fi
# <loc> koji nisu unutar <image:image>
sed 's|<image:image>|\n@@S@@|g' "$IZVOR" | grep -v '^@@S@@' \
  | grep -o '<loc>[^<]*</loc>' | sed 's/<[^>]*>//g' > "$KES/adrese.txt"
N=$(grep -c . "$KES/adrese.txt" || true)
r "Adresa u sitemapu: **$N**"

REL=$(grep -vc '^https\?://' "$KES/adrese.txt" || true)
[ "$REL" -gt 0 ] && r "[!!] $REL adresa nije apsolutna." || r "[i] Sve adrese apsolutne."
DUP=$(sort "$KES/adrese.txt" | uniq -d | grep -c . || true)
[ "$DUP" -gt 0 ] && r "[!] $DUP duplikata u sitemapu." || r "[i] Nema duplikata."
sed 's|\.html$||; s|/$||' "$KES/adrese.txt" | sort | uniq -d > "$KES/dvojne.txt"
DVO=$(grep -c . "$KES/dvojne.txt" || true)
[ "$DVO" -gt 0 ] && { r "[!] $DVO adresa postoji i sa i bez \`.html\`/kose crte:"; r '```'; head -10 "$KES/dvojne.txt" >> "$IZV"; r '```'; } \
                 || r "[i] Nema iste stranice u dva oblika."
DANAS=$(date -u +%Y-%m-%d)
BUD=$(grep -o '<lastmod>[^<]*' "$IZVOR" | sed 's/<lastmod>//' | awk -v d="$DANAS" '$1>d' | wc -l)
STAR=$(grep -o '<lastmod>[^<]*' "$IZVOR" | sed 's/<lastmod>//' | awk '$1<"2025-01-01"' | wc -l)
[ "$BUD" -gt 0 ]  && r "[!] $BUD datuma izmjene u buducnosti." || r "[i] Nijedan datum nije u buducnosti."
[ "$STAR" -gt 0 ] && r "[!] $STAR datuma starije od 2025."     || r "[i] Nijedan datum nije zastario."
SL=$(grep -c '<image:loc>' "$IZVOR" || true)
[ "$SL" -gt 0 ] && r "[i] Sitemap nosi **$SL** slika (image sitemap je ugradjen)." \
                || r "[!] Sitemap nema nijednu sliku."

# ------------------------------------------------- 4. SKIDANJE + STATUSI ----
h2 "4. Statusi svih stranica"
: > "$KES/statusi.txt"; BR=0
while read -r u; do
  [ -z "$u" ] && continue
  f="$KES/$(ime "$u").html"
  m=$("${CURL[@]}" -A "$GBOT" -o "$f" -w '%{http_code}|%{num_redirects}|%{time_starttransfer}' "$u")
  echo "$u|$m" >> "$KES/statusi.txt"; BR=$((BR+1))
done < "$KES/adrese.txt"
r "Skinuto: **$BR** stranica."
NE200=$(awk -F'|' '$2!="200"' "$KES/statusi.txt" | grep -c . || true)
SKOK=$(awk -F'|' '$3!="0"'   "$KES/statusi.txt" | grep -c . || true)
[ "$NE200" -gt 0 ] && { r "[!!] $NE200 adresa ne vraca 200:"; r '```'; awk -F'|' '$2!="200"{printf "%-58s %s\n",$1,$2}' "$KES/statusi.txt" | head -20 >> "$IZV"; r '```'; } \
                   || r "[i] Svih $BR adresa vraca 200."
[ "$SKOK" -gt 0 ]  && { r "[!] $SKOK adresa preusmjerava:"; r '```'; awk -F'|' '$3!="0"{printf "%-58s %s\n",$1,$3}' "$KES/statusi.txt" | head -20 >> "$IZV"; r '```'; } \
                   || r "[i] Nijedna adresa iz sitemapa ne preusmjerava."

# ----------------------------------------------------------- 5. NOINDEX -----
h2 "5. Zabrana indeksiranja"
: > "$KES/noindex.txt"
while IFS='|' read -r u kod _; do
  [ "$kod" != "200" ] && continue
  grep -qiE '<meta[^>]*name=["'"'"']robots["'"'"'][^>]*noindex' "$KES/$(ime "$u").html" 2>/dev/null \
    && echo "$u  (meta robots)" >> "$KES/noindex.txt"
done < "$KES/statusi.txt"
for u in "$BAZA/" "$BAZA/products.html" "$BAZA/kategorija/bambus-paneli" \
         "$BAZA/paneli/drveni-panel-golden-teak" "$BAZA/sitemap.xml"; do
  "${CURL[@]}" -A "$GBOT" -o /dev/null -D - "$u" 2>/dev/null | grep -qi 'x-robots-tag.*noindex' \
    && echo "$u  (X-Robots-Tag u zaglavlju)" >> "$KES/noindex.txt"
done
NI=$(grep -c . "$KES/noindex.txt" || true)
[ "$NI" -gt 0 ] && { r "[!!] Zabrana indeksiranja na $NI mjesta:"; r '```'; cat "$KES/noindex.txt" >> "$IZV"; r '```'; } \
                || r "[i] Nema \`noindex\` ni u HTML-u ni u HTTP zaglavlju."

# --------------------------------------------------------- 6. CANONICAL -----
h2 "6. Canonical"
: > "$KES/canon.txt"
while IFS='|' read -r u kod _; do
  [ "$kod" != "200" ] && continue
  c=$(tr '\n' ' ' < "$KES/$(ime "$u").html" 2>/dev/null \
      | grep -oiE '<link[^>]*rel="canonical"[^>]*>' | head -1 \
      | grep -oiE 'href="[^"]*' | sed 's/^href="//')
  echo "$u|${c:-NEMA}" >> "$KES/canon.txt"
done < "$KES/statusi.txt"
BEZ=$(awk -F'|' '$2=="NEMA"' "$KES/canon.txt" | grep -c . || true)
[ "$BEZ" -gt 0 ] && { r "[!!] $BEZ stranica bez canonical:"; r '```'; awk -F'|' '$2=="NEMA"{print $1}' "$KES/canon.txt" | head -10 >> "$IZV"; r '```'; } \
                 || r "[i] Svaka stranica ima canonical."
awk -F'|' '$2!="NEMA"{print $2}' "$KES/canon.txt" | sort | uniq -d > "$KES/canon-isti.txt"
ISTI=$(grep -c . "$KES/canon-isti.txt" || true)
if [ "$ISTI" -gt 0 ]; then
  r "[!!] $ISTI canonical adresa se ponavlja na vise stranica — te stranice ispadaju iz indeksa:"
  r '```'
  while read -r c; do
    printf '%s\n   <- ' "$c" >> "$IZV"
    awk -F'|' -v c="$c" '$2==c{printf "%s  ",$1}' "$KES/canon.txt" >> "$IZV"; echo >> "$IZV"
  done < "$KES/canon-isti.txt"
  r '```'
else r "[i] Nijedan canonical se ne ponavlja."; fi
RAZL=$(awk -F'|' '$2!="NEMA" && $2!=$1' "$KES/canon.txt" | grep -c . || true)
[ "$RAZL" -gt 0 ] && { r "[!] $RAZL stranica ima canonical koji nije ona sama:"; r '```'; awk -F'|' '$2!="NEMA" && $2!=$1{printf "%-52s -> %s\n",$1,$2}' "$KES/canon.txt" | head -10 >> "$IZV"; r '```'; } \
                  || r "[i] Svaki canonical pokazuje na samu stranicu."

# ------------------------------------------------------ 7. TITLE / OPIS -----
h2 "7. Title i meta opis"
: > "$KES/meta.txt"
while IFS='|' read -r u kod _; do
  [ "$kod" != "200" ] && continue
  f="$KES/$(ime "$u").html"
  # Fajl se prvo spljosti u jedan red. Meta opis se u 62 od 149 stranica
  # prelama preko dva reda, pa ga grep koji radi red po red ne moze uhvatiti —
  # prva verzija ovog skripta je zbog toga za 148 stranica vratila pogresnu
  # vrijednost i prijavila "148 dupliranih opisa" kojih nema.
  jedan=$(tr '\n' ' ' < "$f")
  t=$(printf '%s' "$jedan" | grep -oiE '<title>[^<]*</title>' | head -1 | sed 's/<[^>]*>//g; s/^ *//; s/ *$//')
  d=$(printf '%s' "$jedan" | grep -oiE '<meta[^>]*name="description"[^>]*>' | head -1 \
      | grep -oiE 'content="[^"]*' | sed 's/^content="//' | tr -s ' ')
  # Razdvajac je TABULATOR, ne "|". Naslovi na ovom sajtu izgledaju ovako:
  # "Proizvodi | Make My Home Decor" — sa "|" kao razdvajacem awk je za polje
  # opisa uzimao rep naslova (" Make My Home Decor"), isti za 148 stranica, pa
  # je skript prijavio duplirani opis kojeg nema, a opisi nikad nisu ni bili
  # uporedjeni. Tabulator se u naslovima i opisima ne pojavljuje.
  printf '%s\t%s\t%s\n' "$u" "${t:-NEMA}" "${d:-NEMA}" >> "$KES/meta.txt"
done < "$KES/statusi.txt"
BT=$(awk -F'\t' '$2=="NEMA"' "$KES/meta.txt" | grep -c . || true)
BD=$(awk -F'\t' '$3=="NEMA"' "$KES/meta.txt" | grep -c . || true)
[ "$BT" -gt 0 ] && r "[!!] $BT stranica bez title." || r "[i] Svaka stranica ima title."
[ "$BD" -gt 0 ] && r "[!] $BD stranica bez meta opisa." || r "[i] Svaka stranica ima meta opis."
DT=$(awk -F'\t' '$2!="NEMA"{print $2}' "$KES/meta.txt" | sort | uniq -d | grep -c . || true)
DD=$(awk -F'\t' '$3!="NEMA"{print $3}' "$KES/meta.txt" | sort | uniq -d | grep -c . || true)
[ "$DT" -gt 0 ] && { r "[!] $DT dupliranih title-ova:"; r '```'; awk -F'\t' '$2!="NEMA"{print $2}' "$KES/meta.txt" | sort | uniq -d | head -10 >> "$IZV"; r '```'; } || r "[i] Nijedan title se ne ponavlja."
[ "$DD" -gt 0 ] && { r "[!] $DD dupliranih meta opisa:"; r '```'; awk -F'\t' '$3!="NEMA"{print $3}' "$KES/meta.txt" | sort | uniq -d | cut -c1-100 | head -10 >> "$IZV"; r '```'; } || r "[i] Nijedan opis se ne ponavlja."
DUG=$(awk -F'\t' '$2!="NEMA" && length($2)>60' "$KES/meta.txt" | grep -c . || true)
[ "$DUG" -gt 0 ] && r "[i] $DUG title-ova duze od 60 znakova (Google ih skracuje)."

# ------------------------------------------------- 8. SADRZAJ BEZ JS --------
h2 "8. Sadrzaj u sirovom HTML-u (bez JavaScripta)"
r "Sve se mjeri u HTML-u koji server posalje, prije nego JavaScript isprva ista."
: > "$KES/sadrzaj.txt"
while IFS='|' read -r u kod _; do
  [ "$kod" != "200" ] && continue
  f="$KES/$(ime "$u").html"
  rij=$(sed -e 's/<script[^>]*>.*<\/script>//g' -e 's/<style[^>]*>.*<\/style>//g' -e 's/<[^>]*>/ /g' "$f" 2>/dev/null | tr -s ' \n' ' ' | wc -w)
  eur=$(grep -o '€' "$f" 2>/dev/null | wc -l)
  h1=$(grep -oiE '<h1[ >]' "$f" 2>/dev/null | wc -l)
  hh2=$(grep -oiE '<h2[ >]' "$f" 2>/dev/null | wc -l)
  hh3=$(grep -oiE '<h3[ >]' "$f" 2>/dev/null | wc -l)
  jl=$(grep -c 'application/ld+json' "$f" 2>/dev/null || echo 0)
  echo "$u|$rij|$eur|$h1|$hh2|$hh3|$jl" >> "$KES/sadrzaj.txt"
done < "$KES/statusi.txt"

h3 "Malo teksta"
TANKO=$(awk -F'|' '$2<250' "$KES/sadrzaj.txt" | grep -c . || true)
[ "$TANKO" -gt 0 ] && { r "[!] $TANKO stranica ima ispod 250 rijeci:"; r '```'; awk -F'|' '$2<250{printf "%-58s %s\n",$1,$2}' "$KES/sadrzaj.txt" | head -20 >> "$IZV"; r '```'; } \
                   || r "[i] Nijedna stranica nema ispod 250 rijeci."

h3 "Naslovi H1"
B1=$(awk -F'|' '$4==0' "$KES/sadrzaj.txt" | grep -c . || true)
V1=$(awk -F'|' '$4>1'  "$KES/sadrzaj.txt" | grep -c . || true)
[ "$B1" -gt 0 ] && { r "[!] $B1 stranica bez H1:"; r '```'; awk -F'|' '$4==0{print $1}' "$KES/sadrzaj.txt" | head -10 >> "$IZV"; r '```'; } || r "[i] Svaka stranica ima H1."
[ "$V1" -gt 0 ] && { r "[!] $V1 stranica ima vise od jednog H1."; } || r "[i] Nijedna nema vise H1."

h3 "KLJUCNI TEST — cijene i naslovi na kategorijama"
r "Ako u sirovom HTML-u kategorije nema cijena (€) ni naslova proizvoda (h2/h3),"
r "znaci da ih crta JavaScript i Google ih u prvom prolazu ne vidi."
r ""
r '```'
printf '%-40s %7s %5s %5s %5s\n' "kategorija" "rijeci" "€" "h2" "h3" >> "$IZV"
grep '/kategorija/' "$KES/sadrzaj.txt" | awk -F'|' '{n=$1; sub("https://makemyhome.me","",n);
  printf "%-40s %7s %5s %5s %5s\n", n, $2, $3, $5, $6}' >> "$IZV"
r '```'
KB=$(grep '/kategorija/' "$KES/sadrzaj.txt" | awk -F'|' '$3<3' | grep -c . || true)
[ "$KB" -gt 0 ] && r "[!!] $KB kategorija ima manje od 3 cijene u sirovom HTML-u — proizvode crta JavaScript." \
                || r "[i] Sve kategorije imaju cijene u sirovom HTML-u — server ih ispisuje."
PB=$(grep '/paneli/' "$KES/sadrzaj.txt" | awk -F'|' '$3<1' | grep -c . || true)
[ "$PB" -gt 0 ] && r "[!!] $PB stranica proizvoda nema cijenu u sirovom HTML-u." \
                || r "[i] Svaka stranica proizvoda ima cijenu u sirovom HTML-u."

# ------------------------------------------------------------- 9. JSON-LD ---
h2 "9. Strukturirani podaci"
BJ=$(awk -F'|' '$7==0' "$KES/sadrzaj.txt" | grep -c . || true)
[ "$BJ" -gt 0 ] && { r "[!] $BJ stranica bez ijednog JSON-LD bloka:"; r '```'; awk -F'|' '$7==0{print $1}' "$KES/sadrzaj.txt" | head -10 >> "$IZV"; r '```'; } \
                || r "[i] Svaka stranica ima bar jedan JSON-LD blok."
python3 - "$KES" >> "$IZV" <<'PYEOF'
import json, os, re, sys, collections
kes = sys.argv[1]
tip = collections.Counter(); lose = []; bezP = []
for red in open(os.path.join(kes, 'statusi.txt'), encoding='utf-8'):
    d = red.strip().split('|')
    if len(d) < 2 or d[1] != '200': continue
    u = d[0]
    f = os.path.join(kes, re.sub(r'[/?=&]', '_', re.sub(r'^https?://', '', u)) + '.html')
    if not os.path.exists(f): continue
    h = open(f, encoding='utf-8', errors='replace').read()
    imaP = False
    for b in re.findall(r'<script type="application/ld\+json">(.*?)</script>', h, re.S):
        try: dd = json.loads(b)
        except Exception as e: lose.append('%s → %s' % (u, str(e)[:55])); continue
        def cvor(x):
            for o in (x if isinstance(x, list) else [x]):
                if not isinstance(o, dict): continue
                if '@graph' in o:
                    for g in o['@graph']:
                        if isinstance(g, dict): yield g
                else: yield o
        for n in cvor(dd):
            t = n.get('@type')
            for x in (t if isinstance(t, list) else [t]):
                if x: tip[str(x)] += 1
                if x == 'Product': imaP = True
            if n.get('@type') == 'ItemList':
                for e in n.get('itemListElement', []):
                    if (e.get('item') or {}).get('@type') == 'Product':
                        tip['Product (unutar ItemList)'] += 1
    if '/paneli/' in u and not imaP: bezP.append(u)
print(''); print('Tipovi na sajtu:'); print('```')
for t, n in tip.most_common(): print('%-30s %d' % (t, n))
print('```'); print('')
if lose:
    print('[!!] JSON-LD koji se ne parsira: %d' % len(lose)); print('```')
    [print(' ', x) for x in lose[:10]]; print('```')
else: print('[i] Svaki JSON-LD blok se ispravno parsira.')
print('')
if bezP:
    print('[!!] /paneli/ stranica bez Product scheme: %d' % len(bezP)); print('```')
    [print(' ', x) for x in bezP[:10]]; print('```')
else: print('[i] Svaka /paneli/ stranica ima Product schemu.')
PYEOF

# ------------------------------------------------------- 10. OSNOVNE OZNAKE -
h2 "10. lang · og:image · viewport"
BL=0; BO=0; BV=0
while IFS='|' read -r u kod _; do
  [ "$kod" != "200" ] && continue
  f="$KES/$(ime "$u").html"
  grep -qiE '<html[^>]*lang=' "$f" || BL=$((BL+1))
  grep -qiE 'property=["'"'"']og:image["'"'"']' "$f" || BO=$((BO+1))
  grep -qiE 'name=["'"'"']viewport["'"'"']' "$f" || BV=$((BV+1))
done < "$KES/statusi.txt"
[ "$BL" -gt 0 ] && r "[!] $BL stranica bez \`lang\`."      || r "[i] Svaka stranica ima \`lang\`."
[ "$BO" -gt 0 ] && r "[!] $BO stranica bez \`og:image\`."   || r "[i] Svaka stranica ima \`og:image\`."
[ "$BV" -gt 0 ] && r "[!!] $BV stranica bez \`viewport\`."  || r "[i] Svaka stranica ima \`viewport\`."

# --------------------------------------------------------------- 11. SLIKE --
h2 "11. Slike"
BA=0
while IFS='|' read -r u kod _; do
  [ "$kod" != "200" ] && continue
  n=$(grep -oiE '<img[^>]*>' "$KES/$(ime "$u").html" 2>/dev/null | grep -vc 'alt=' || true)
  BA=$((BA+n))
done < "$KES/statusi.txt"
[ "$BA" -gt 0 ] && r "[!] $BA slika bez \`alt\` opisa." || r "[i] Svaka slika ima \`alt\`."
r ""
r "Slike preko 300 kB (uzorak 30):"
r '```'
grep -ohE 'src="[^"]*\.(jpg|jpeg|png|webp)"' "$KES"/*.html 2>/dev/null | sed 's/src="//; s/"//' \
 | sort -u | head -30 | while read -r sl; do
    case "$sl" in http*) a="$sl";; /*) a="$BAZA$sl";; *) a="$BAZA/$sl";; esac
    v=$("${CURL[@]}" -A "$GBOT" -o /dev/null -w '%{size_download}' "$a")
    [ "${v:-0}" -gt 307200 ] && printf '%-66s %s kB\n' "${a#$BAZA/}" "$((v/1024))"
  done >> "$IZV"
r '```'

# --------------------------------------------------- 12. LINKOVI I SIROCAD --
h2 "12. Interni linkovi i sirocad"
: > "$KES/linkovi.txt"
while IFS='|' read -r u kod _; do
  [ "$kod" != "200" ] && continue
  f="$KES/$(ime "$u").html"
  b=$(grep -oE '<base href="[^"]*"' "$f" 2>/dev/null | head -1 | sed 's/.*href="//; s/"//')
  sed 's|<script[^>]*>.*</script>||g' "$f" 2>/dev/null | grep -oE 'href="[^"#][^"]*"' \
    | sed 's/href="//; s/"//' | while read -r l; do
      case "$l" in
        http*) echo "$l" ;;
        /*)    echo "$BAZA$l" ;;
        mailto:*|tel:*|javascript:*|viber:*|data:*) ;;
        *)     echo "${b:-$BAZA/}$l" ;;
      esac
    done >> "$KES/linkovi.txt"
done < "$KES/statusi.txt"
grep "^$BAZA" "$KES/linkovi.txt" | sed 's/[?#].*//' | sort -u > "$KES/linkovi-puni.txt"
: > "$KES/sirocad.txt"
while read -r u; do
  grep -qxF "$u" "$KES/linkovi-puni.txt" || echo "$u" >> "$KES/sirocad.txt"
done < "$KES/adrese.txt"
SI=$(grep -c . "$KES/sirocad.txt" || true)
[ "$SI" -gt 0 ] && { r "[!] $SI stranica je u sitemapu ali do nje ne vodi nijedan interni link:"; r '```'; head -15 "$KES/sirocad.txt" >> "$IZV"; r '```'; } \
                || r "[i] Do svake stranice iz sitemapa vodi bar jedan interni link."
r ""
r "Interni linkovi koji vracaju gresku (uzorak 60):"
r '```'
head -60 "$KES/linkovi-puni.txt" | while read -r l; do
  k=$("${CURL[@]}" -A "$GBOT" -o /dev/null -w '%{http_code}' "$l")
  case "$k" in 200|301|302) ;; *) printf '%-66s %s\n' "${l#$BAZA}" "$k" ;; esac
done >> "$IZV"
r '```'

# ------------------------------------------------------------- 13. REPO -----
h2 "13. Lokalni repo"
h3 "Pojave rijeci noindex"
r '```'
{ grep -rn "noindex" --include=*.html --include=*.php --include=*.js --include=*.txt . 2>/dev/null \
  | grep -v "^\./$KES" | grep -v '^\./alat' | head -20; } >> "$IZV" || true
r '```'
h3 "Konfiguracija hostinga"
r '```'
for f in netlify.toml vercel.json .htaccess _redirects _headers CNAME web.config nginx.conf; do
  [ -f "$f" ] && printf '%-16s postoji (%s B)\n' "$f" "$(wc -c < "$f")" || printf '%-16s nema\n' "$f"
done >> "$IZV"
r '```'
h3 "Sumnjivi fajlovi u korijenu"
r '```'
{ ls -1 2>/dev/null | grep -iE 'test|backup|bak|old|copy|tmp|phpinfo|\.sql|\.zip' | head -20; } >> "$IZV" || true
r '```'
h3 "Lozinke ili kljucevi u JavaScriptu"
r '```'
{ grep -rniE "(password|passwd|api[_-]?key|secret|token)[[:space:]]*[:=][[:space:]]*['\"][^'\"]{6,}" js/ 2>/dev/null | head -10; } >> "$IZV" || true
r '```'
h3 "Istorija sitemapa i robots.txt"
r '```'
{ git log --oneline -8 -- sitemap.xml sitemap.php robots.txt 2>/dev/null; } >> "$IZV" || true
r '```'

# ------------------------------------------------------------ 14. BRZINA ----
h2 "14. Brzina"
r '```'
printf '%-46s %8s %8s\n' "stranica" "TTFB" "ukupno" >> "$IZV"
for u in "$BAZA/" "$BAZA/kategorija/bambus-paneli" "$BAZA/paneli/drveni-panel-golden-teak"; do
  m=$("${CURL[@]}" -A "$GBOT" -o /dev/null -w '%{time_starttransfer}|%{time_total}' "$u")
  IFS='|' read -r a b <<< "$m"
  printf '%-46s %7ss %7ss\n' "${u#$BAZA}" "$a" "$b" >> "$IZV"
done
r '```'
r "[i] Mjereno kroz posrednik ovog okruzenja — pravi server je brzi za oko 0,5 s."

r ""; r "---"; r ""
r "Kes skinutih stranica: \`$KES/\`"
echo "Gotovo → $IZV"
