# R2 — obrasci u JavaScriptu koji brisu ili kriju sadrzaj

Skenirano: svi `.js` fajlovi i svaki ugradjeni `<script>` u `.php` i `.html`.

Prijavljuje se samo diranje **kontejnera sadrzaja** (mreza proizvoda, mreza
kategorija, kolona proizvoda, galerija, recenzije, specifikacije, mrvice).
Mijenjanje brojaca, dugmadi i natpisa se ne broji.

- ukupno pogodaka: **25**
- **bez zastite** (moze pregaziti ono sto je server ispisao): **16**
- sa zastitom: 9

„Zastita" znaci da funkcija u kojoj je linija provjerava da li je server
vec nesto ispisao — `data-ssr`, `querySelector('.product-card')`,
`children.length`, `dataset.seo`.

## Bez zastite — pregledati

| fajl | linija | funkcija | obrazac | kod |
|---|---|---|---|---|
| `products.html` | 279 | `(vrh fajla)` | textContent = | `(function(){var p=new URLSearchParams(location.search),cat=p.get('cat')\|\|p.get('category');if(!cat)return;var names={'bambus-paneli':'Bambus Paneli','` |
| `products.html` | 314 | `(vrh fajla)` | textContent = | `var ct=document.getElementById('cat-title');if(ct)ct.textContent=n;` |
| `products.html` | 319 | `(vrh fajla)` | display='none' | `var cg=document.getElementById('category-grid');if(cg)cg.style.display='none';` |
| `admin/dashboard.php` | 1387 | `showSection` | textContent = | `document.getElementById('page-title').textContent = titles[name] \|\| '';` |
| `admin/dashboard.php` | 1425 | `editProduct` | textContent = | `document.getElementById('gallery-upload-status').textContent = '';` |
| `js/main-v4.js` | 47 | `loadProductsOnce` | innerHTML = | `if (q.length < 2) { resultsBox.style.display = 'none'; resultsBox.innerHTML = ''; return; }` |
| `js/main-v4.js` | 47 | `loadProductsOnce` | display='none' | `if (q.length < 2) { resultsBox.style.display = 'none'; resultsBox.innerHTML = ''; return; }` |
| `js/main-v4.js` | 56 | `loadProductsOnce` | innerHTML = | `resultsBox.innerHTML = `<div style="padding:14px 16px;color:rgba(255,255,255,0.45);font-size:14px;">Nema rezultata za „${searchInput.value}"</div>`;` |
| `js/main-v4.js` | 60 | `loadProductsOnce` | innerHTML = | `resultsBox.innerHTML = hits.map(p => {` |
| `js/main-v4.js` | 70 | `loadProductsOnce` | display='none' | `onclick="document.getElementById('mob-search-input').value='';document.getElementById('mob-search-results').style.display='none';">` |
| `js/main-v4.js` | 93 | `loadProductsOnce` | display='none' | `if (resultsBox) resultsBox.style.display = 'none';` |
| `js/main-v4.js` | 102 | `loadProductsOnce` | display='none' | `if (resultsBox) resultsBox.style.display = 'none';` |
| `js/main-v4.js` | 49 | `loadProductsOnce` | filter/slice nad listom | `const hits = products.filter(p =>` |
| `js/main-v4.js` | 147 | `closeResults` | filter/slice nad listom | `const hits = products.filter(p =>` |
| `js/products.js` | 513 | `id` | textContent = | `if (breadcrumb) breadcrumb.textContent = product.name;` |
| `js/products.js` | 617 | `dotWrap` | display='none' | `onerror="this.onerror=null;this.closest(&quot;.gallery-thumb&quot;).style.display='none'">` |

## Sa zastitom — bezopasno

| fajl | linija | funkcija | obrazac |
|---|---|---|---|
| `products.php` | 692 | `(vrh fajla)` | textContent = |
| `products.php` | 909 | `(vrh fajla)` | textContent = |
| `products.php` | 918 | `(vrh fajla)` | display='none' |
| `js/products.js` | 347 | `showCategoryGrid` | display='none' |
| `js/products.js` | 395 | `showCategoryProducts` | display='none' |
| `js/products.js` | 859 | `styleMatchHtml` | innerHTML = |
| `js/products.js` | 1058 | `totalCost` | innerHTML = |
| `js/products.js` | 429 | `showCategoryProducts` | filter/slice nad listom |
| `js/products.js` | 1056 | `totalCost` | filter/slice nad listom |
