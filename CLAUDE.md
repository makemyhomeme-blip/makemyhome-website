# Make My Home – Project Configuration

## Server & Hosting

- **Live site:** https://makemyhome.me
- **Hosting:** cPanel shared hosting, server `cp71.cpanelhosting.rs`
- **Document root:** `/home/mmhdecor/public_html/makemyhome.me/`
- **cPanel username:** `mmhdecor`
- **cPanel password:** `fhgkwqjd0F6K`
- **cPanel URL:** `https://cpanel.mmhdecor.mycpanel.rs` (use this — port 2083 SSL fails through proxy)
- **cPanel UAPI base:** `https://cpanel.mmhdecor.mycpanel.rs/execute/`

### cPanel API usage (curl)
```bash
# List files
curl -sk --cacert /root/.ccr/ca-bundle.crt -u "mmhdecor:fhgkwqjd0F6K" \
  "https://cpanel.mmhdecor.mycpanel.rs/execute/Fileman/list_files?dir=%2Fhome%2Fmmhdecor%2Fpublic_html%2Fmakemyhome.me&include_mime=0"

# Save a file
curl -sk --cacert /root/.ccr/ca-bundle.crt -u "mmhdecor:fhgkwqjd0F6K" -X POST \
  "https://cpanel.mmhdecor.mycpanel.rs/execute/Fileman/save_file_content" \
  --data-urlencode "dir=/home/mmhdecor/public_html/makemyhome.me" \
  --data-urlencode "file=FILENAME" \
  --data-urlencode "content=CONTENT"
```

## Admin Panel

- **URL:** https://makemyhome.me/admin/
- **Username:** `admin`
- **Password:** NIJE u repou. Cita se sa servera iz `/home/mmhdecor/.mmh-admin-pass`
  (izvan `public_html`, nedostupno preko weba). Procitati preko cPanel API-ja kad zatreba.
- **Sync:** `https://makemyhome.me/admin/sync.php?key=mkhsync2025`

### Admin sync via curl
```bash
# 1. Login (SIFRA_SA_SERVERA se cita iz /home/mmhdecor/.mmh-admin-pass preko cPanel API-ja)
curl -sk --cacert /root/.ccr/ca-bundle.crt -c /tmp/mkh_cookies.txt \
  -X POST "https://makemyhome.me/admin/index.php" \
  -d "username=admin&password=SIFRA_SA_SERVERA" -L -o /dev/null

# 2. Run sync
curl -sk --cacert /root/.ccr/ca-bundle.crt -b /tmp/mkh_cookies.txt \
  "https://makemyhome.me/admin/sync.php?key=mkhsync2025"
```

## Git Branch

- **Active branch:** `claude/build-product-website-6CvHG`
- **Repo:** `makemyhomeme-blip/makemyhome-website`
- **Always push to this branch, never to main/master**

## Struktura hostinga — VAZNO

- `mmhdecor.mycpanel.rs` je **glavni domen naloga**, docroot `/home/mmhdecor/public_html`
- `makemyhome.me` je **addon domen**, docroot `/home/mmhdecor/public_html/makemyhome.me`
- U korijenu naloga stoji zastarjela kopija sajta iz maja 2026 (index.html, about.html,
  contact.html, sitemap.xml sa 5 URL-ova...). Ta kopija je bila javno dostupna na
  `mmhdecor.mycpanel.rs` i `cp71.cpanelhosting.rs/~mmhdecor` — drugi sajt sa istim
  sadrzajem, sto Google tretira kao duplikat.
- Rijeseno: `/home/mmhdecor/public_html/.htaccess` sada 301-uje sve sto ne dolazi
  na `makemyhome.me` na pravi domen. **Taj blok se ne smije brisati.**
- Taj `.htaccess` je u RODITELJSKOM direktorijumu addon domena, pa se njegova pravila
  nasljedjuju i na makemyhome.me. Svaka izmjena tamo se mora odmah testirati na
  pravom sajtu (svih 149 URL-ova iz sitemapa).

## Adrese (URL struktura)

- Proizvod: `/paneli/<tip>-<ime>`  npr. `/paneli/3d-letvica-honey-oak`
- Kategorija: `/kategorija/<kljuc>`  npr. `/kategorija/3d-letvice`
- Adresu pravi **`php/slug.php`** (PHP) i **ista funkcija na vrhu `js/products.js`** (JS).
  **Ako se mijenja jedna, mora i druga** — inace linkovi na sajtu vode na 404.
  Provjera: izracunaj slug za svih 117 proizvoda u oba jezika i uporedi.
- Stari oblici `?id=` i `?category=` rade i salju 301 na novu adresu (product.php / products.php).
- Stare WordPress adrese idu **pravo** na novu adresu, jednim skokom (`php/slug-match.php`).
- Pravila su u `.htaccess`, blok `# ===== LIJEPE ADRESE =====`.
- `php/slug.php` **mora biti u `admin/sync.php` listi** — bez njega product.php i
  products.php pucaju sa fatalnom greskom.

## Deploy Workflow

**REDOSLIJED JE OBAVEZAN — sync povlaci sa GitHuba i prepisuje server.
Ako se fajl deployuje preko cPanela prije push-a, prvi sljedeci sync ga vrati na staro.**

1. Edit files locally
2. `git add <files> && git commit && git push -u origin claude/build-product-website-6CvHG`
3. Wait ~5 min for GitHub CDN cache (raw.githubusercontent.com)
4. Run admin sync (curl login → sync)
5. If sync.php itself changed: run sync **twice** (first run deploys new sync.php, second run uses it)
6. If site returns 500 or sync fails: use cPanel API to write files directly

## Important Rules

- **NEVER deploy** `data/products.json` from local to the server. Vlasnik ga mijenja preko
  admina (slike, galerije, cijene) i svaki deploy sa lokalnog brise te izmjene.
  Ako se sadrzaj mijenja: **prvo povuci sa servera**, izmijeni tu verziju, pa vrati.
  Isto vazi za `images/products/*`.
- **NEVER touch** main/first product images without explicit permission
- **Jedinica mjere:** jedino **SPC pod** se prodaje po m² (`"unit": "m²"`). Sve ostalo —
  bambus paneli, 3D letvice, akusticni, MDF, PU kamen, Flex Stone, alu lajsne — ide
  **po komadu** (`"unit": "kom"`). Kod koji racuna cijenu po m² se oslanja na ovo polje:
  za robu po m² cijena JESTE cijena po m² i ne smije se dijeliti povrsinom komada.
- **Always test** `.htaccess` changes: `Options` directive is NOT allowed on this hosting (causes 500 for all pages). Use `<IfModule>` blocks only.
- Respond in **Latin script** (latinica), never Cyrillic
- CSS file is versioned: `style-v5.css` — when incrementing version, update all HTML references too

## Tech Stack

- Apache + PHP 8.x + OPcache (shared hosting)
- Static HTML pages + PHP for SSR (product.php, products.php)
- Products data: `data/products.json`
- Admin panel: `admin/` directory
- No framework, no build step — pure HTML/CSS/JS/PHP

## SEO Notes

- Sitemap: `sitemap.xml` (129 URLs, update `lastmod` when content changes)
- Canonical: `https://makemyhome.me/` (non-www, HTTPS)
- Google Search Console connected
- Old WordPress URLs `/product/slug/` → 301 via `404.php` → `product.html?id=X`
