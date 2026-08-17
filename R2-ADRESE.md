# R2 — sve adrese koje vracaju 200

Cilj: naci razliku izmedju ~280 indeksiranih adresa i 149 iz sitemapa.

Sitemap: **149** adresa.
Crawl od pocetne (dubina 3): **151** razlicitih adresa u linkovima.

## 1. Nadjeno crawlom, a nije u sitemapu

| adresa | status | skokova | zavrsi na | u sitemapu? |
|---|---|---|---|---|
| `/checkout.html` | 200 | 0 | `/checkout.html` | namjerno |
| `/korpa.html` | 200 | 0 | `/korpa.html` | namjerno |

## 2. Varijante iste adrese (/x, /x/, /x.html)

Provjereno 313 varijanti od 149 adresa iz sitemapa.

[i] Nijedna varijanta ne vraca 200 direktno — sve su 301 ili 404.

## 3. Adrese sa parametrima

| adresa | status | skokova | zavrsi na | canonical |
|---|---|---|---|---|
| `/?utm_source=facebook&utm_medium=social` | 200 | 0 | `/` | canonical cisti |
| `/?fbclid=IwAR123` | 200 | 0 | `/` | canonical cisti |
| `/?page=2` | 200 | 0 | `/` | canonical cisti |
| `/?filter=cijena` | 200 | 0 | `/` | canonical cisti |
| `/?cat=3d-letvice` | 200 | 0 | `/` | canonical cisti |
| `/?category=3d-letvice` | 200 | 0 | `/` | canonical cisti |
| `/?id=5` | 200 | 0 | `/` | canonical cisti |
| `/?gclid=abc123` | 200 | 0 | `/` | canonical cisti |
| `/?ref=instagram` | 200 | 0 | `/` | canonical cisti |
| `/kategorija/3d-letvice?utm_source=facebook&utm_mediu` | 200 | 0 | `/kategorija/3d-letvice` | canonical cisti |
| `/kategorija/3d-letvice?fbclid=IwAR123` | 200 | 0 | `/kategorija/3d-letvice` | canonical cisti |
| `/kategorija/3d-letvice?page=2` | 200 | 0 | `/kategorija/3d-letvice` | canonical cisti |
| `/kategorija/3d-letvice?filter=cijena` | 200 | 0 | `/kategorija/3d-letvice` | canonical cisti |
| `/kategorija/3d-letvice?cat=3d-letvice` | 200 | 0 | `/kategorija/3d-letvice` | canonical cisti |
| `/kategorija/3d-letvice?category=3d-letvice` | 200 | 0 | `/kategorija/3d-letvice` | canonical cisti |
| `/kategorija/3d-letvice?id=5` | 200 | 0 | `/kategorija/3d-letvice` | canonical cisti |
| `/kategorija/3d-letvice?gclid=abc123` | 200 | 0 | `/kategorija/3d-letvice` | canonical cisti |
| `/kategorija/3d-letvice?ref=instagram` | 200 | 0 | `/kategorija/3d-letvice` | canonical cisti |
| `/paneli/3d-letvica-obsidian?utm_source=facebook&utm_` | 200 | 0 | `/paneli/3d-letvica-obsidian` | canonical cisti |
| `/paneli/3d-letvica-obsidian?fbclid=IwAR123` | 200 | 0 | `/paneli/3d-letvica-obsidian` | canonical cisti |
| `/paneli/3d-letvica-obsidian?page=2` | 200 | 0 | `/paneli/3d-letvica-obsidian` | canonical cisti |
| `/paneli/3d-letvica-obsidian?filter=cijena` | 200 | 0 | `/paneli/3d-letvica-obsidian` | canonical cisti |
| `/paneli/3d-letvica-obsidian?cat=3d-letvice` | 200 | 0 | `/paneli/3d-letvica-obsidian` | canonical cisti |
| `/paneli/3d-letvica-obsidian?category=3d-letvice` | 200 | 0 | `/paneli/3d-letvica-obsidian` | canonical cisti |
| `/paneli/3d-letvica-obsidian?id=5` | 200 | 0 | `/paneli/3d-letvica-obsidian` | canonical cisti |
| `/paneli/3d-letvica-obsidian?gclid=abc123` | 200 | 0 | `/paneli/3d-letvica-obsidian` | canonical cisti |
| `/paneli/3d-letvica-obsidian?ref=instagram` | 200 | 0 | `/paneli/3d-letvica-obsidian` | canonical cisti |
| `/products.html?utm_source=facebook&utm_medium=social` | 200 | 0 | `/products.html` | canonical cisti |
| `/products.html?fbclid=IwAR123` | 200 | 0 | `/products.html` | canonical cisti |
| `/products.html?page=2` | 200 | 0 | `/products.html` | canonical cisti |
| `/products.html?filter=cijena` | 200 | 0 | `/products.html` | canonical cisti |
| `/products.html?cat=3d-letvice` | 301 | 1 | `/kategorija/3d-letvice` | 301 |
| `/products.html?category=3d-letvice` | 301 | 1 | `/kategorija/3d-letvice` | 301 |
| `/products.html?id=5` | 200 | 0 | `/products.html` | canonical cisti |
| `/products.html?gclid=abc123` | 200 | 0 | `/products.html` | canonical cisti |
| `/products.html?ref=instagram` | 200 | 0 | `/products.html` | canonical cisti |

## 4. Ostaci starog sajta

| adresa | status | zavrsi na | ocjena |
|---|---|---|---|
| `/wp-admin/` | 410 | `/wp-admin/` | uredu |
| `/wp-login.php` | 410 | `/wp-login.php` | uredu |
| `/wp-content/uploads/` | 410 | `/wp-content/uploads/` | uredu |
| `/?p=1` | 301 | `/` | uredu (301 na pravu) |
| `/feed/` | 410 | `/feed/` | uredu |
| `/shop/` | 301 | `/products.html` | uredu (301 na pravu) |
| `/product/bambus-panel/` | 301 | `/paneli/akusticni-panel-aku053-zlatni-bambus-linear` | uredu (301 na pravu) |
| `/product-category/paneli/` | 301 | `/products.html` | uredu (301 na pravu) |
| `/cart/` | 502 | `/cart/` | 502 |
| `/my-account/` | 404 | `/my-account/` | uredu |
| `/checkout/` | 301 | `/checkout.html` | 301 |
| `/wp-json/wp/v2/posts` | 410 | `/wp-json/wp/v2/posts` | uredu |
| `/xmlrpc.php` | 410 | `/xmlrpc.php` | uredu |
| `/index.php` | 410 | `/index.php` | uredu |
| `/home` | 404 | `/home` | uredu |
| `/about` | 301 | `/about.html` | uredu (301 na pravu) |
| `/kontakt` | 301 | `/contact.html` | uredu (301 na pravu) |
| `/blog/` | 301 | `/` | uredu (301 na pravu) |
| `/category/uncategorized/` | 410 | `/category/uncategorized/` | uredu |

## 5. Staticni fajlovi koje je zamijenio PHP

Ako `.htaccess` prepisuje `/x.html` na `x.php`, staticni `x.html` na disku je
mrtav teret — ali ostaje dostupan ako pravilo ikad zakaze.

| staticni fajl | PHP verzija | fajl postoji lokalno | sajt vraca |
|---|---|---|---|
| `products.html` | `products.php` | da | PHP verziju |
| `product.html` | `product.php` | da | PHP verziju |
| `cjenovnik.html` | `cjenovnik.php` | ne | PHP verziju |
| `inspiracija.html` | `inspiracija.php` | ne | PHP verziju |
| `decor-box.html` | `decor-box.php` | ne | PHP verziju |
| `index.html` | `pocetna.php` | da | PHP verziju |
