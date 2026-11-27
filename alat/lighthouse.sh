#!/bin/bash
# Lighthouse — isti alat koji pokrece Google PageSpeed, ali na SVIM tipovima
# stranica, a ne samo na pocetnoj.
#
# Zasto postoji:
# alat/provjera.py provjerava ono sto smo znali da treba provjeriti. Lighthouse
# gleda drugim ocima — kontrast boja, velicinu dugmadi, zaglavlja tabela,
# oznake formi. Kad je prvi put pusten, nasao je 4 stranice sa nedovoljnim
# kontrastom koje moja provjera nije mogla naci jer kontrast nikad nije mjerila.
#
# Zasto posrednik:
# Stranice sa <base href="https://makemyhome.me/"> testni pregledac pokusa da
# ucita sa pravog sajta, do kojeg iz ovog okruzenja nema pristupa — pa ostanu
# BEZ stilova i Lighthouse prijavi gomilu izmisljenih gresaka (navigacija
# "prevelika", kontrast "los"). alat/posrednik.mjs zamijeni adresu sajta
# lokalnom, pa stranica dobije svoje stilove i nalazi su vjerodostojni.
#
# Pokretanje:  bash alat/lighthouse.sh
set -e
KOR="$(cd "$(dirname "$0")/.." && pwd)"
LH="${LH:-$KOR/../lighthouse/node_modules/.bin/lighthouse}"
[ -x "$LH" ] || LH="$(command -v lighthouse || true)"
if [ ! -x "$LH" ]; then echo "Lighthouse nije nadjen. Instaliraj: npm i lighthouse"; exit 1; fi
export CHROME_PATH="${CHROME_PATH:-$(ls /opt/pw-browsers/chromium*/chrome-linux/chrome 2>/dev/null | head -1)}"

pgrep -f "php -S 127.0.0.1:8899" >/dev/null || { php -S 127.0.0.1:8899 -t "$KOR" >/dev/null 2>&1 & sleep 2; }
pgrep -f "posrednik.mjs" >/dev/null || { node "$KOR/alat/posrednik.mjs" >/dev/null 2>&1 & sleep 2; }

IZL=$(mktemp -d)
for par in "pocetna:/pocetna.php" "katalog:/products.php" "kategorija:/products.php?k=3d-letvice" \
           "proizvod:/product.php?slug=drveni-panel-mocha-oak" "cjenovnik:/cjenovnik.php" \
           "inspiracija:/inspiracija.php" "kontakt:/contact.html" "faq:/faq.html" \
           "korpa:/korpa.html" "placanje:/checkout.html" "decorbox:/decor-box.php" \
           "montaza:/montaza.html" "tvzid:/tv-zid.html" "onama:/about.html"; do
  ime="${par%%:*}"; put="${par#*:}"
  "$LH" "http://127.0.0.1:8898${put}" --only-categories=seo,accessibility,best-practices \
    --form-factor=mobile --screenEmulation.mobile \
    --chrome-flags="--headless=new --no-sandbox --disable-dev-shm-usage" \
    --output=json --output-path="$IZL/$ime.json" --quiet >/dev/null 2>&1 || true
done
python3 "$KOR/alat/lighthouse-izvjestaj.py" "$IZL"
