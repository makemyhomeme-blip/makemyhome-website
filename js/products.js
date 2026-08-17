/* ===== ADRESE PROIZVODA I KATEGORIJA =====
   Mora davati ISTI rezultat kao php/slug.php — ako se razidju, linkovi pucaju.
   Svaka izmjena ovdje ide i tamo. */
const MMH_TIP = {
  'bambus-paneli': 'bambus-panel', 'bambus-drveni': 'drveni-panel',
  'bambus-tekstilni': 'tekstilni-panel', 'bambus-mermerni': 'mermerni-panel',
  'bambus-metalni': 'metalni-panel', 'bambus-kozni': 'kozni-panel',
  'classic': 'classic-panel', '3d-letvice': '3d-letvica',
  'akusticni-paneli': 'akusticni-panel', 'aluminijum-lajsne': 'alu-lajsna',
  'spc-pod': 'spc-pod', 'pu-kamen': 'pu-kamen', 'mdf': 'mdf-panel',
  'flex-stone': 'flex-stone'
};
function mmhSlugify(s) {
  s = String(s == null ? '' : s).toLowerCase();
  const mapa = { 'č':'c','ć':'c','š':'s','ž':'z','đ':'dj','–':'-','—':'-','×':'x' };
  s = s.replace(/[čćšžđ–—×]/g, function (m) { return mapa[m]; });
  s = s.normalize('NFKD').replace(/[\u0300-\u036f]/g, '');
  s = s.replace(/[^a-z0-9]+/g, '-');
  return s.replace(/-+/g, '-').replace(/^-|-$/g, '');
}
function mmhSlugProizvoda(p) {
  let ime = mmhSlugify(p && p.name);
  const tip = MMH_TIP[p && p.category] || 'panel';
  const dijelovi = ime === '' ? [] : ime.split('-');
  const prva = tip.split('-')[0];
  if (ime !== '' && dijelovi.indexOf(prva) === -1) ime = tip + '-' + ime;
  if (ime === '') ime = 'proizvod-' + ((p && p.id) || 0);
  if (ime.length > 60) {
    const skraceno = ime.slice(0, 60);
    const zadnja = skraceno.lastIndexOf('-');
    ime = (zadnja > 20 ? skraceno.slice(0, zadnja) : skraceno).replace(/-+$/, '');
  }
  return 'paneli/' + ime;
}
function mmhUrlProizvoda(p) { return '/' + mmhSlugProizvoda(p); }
function mmhUrlKategorije(k) { return '/kategorija/' + k; }
window.mmhUrlProizvoda = mmhUrlProizvoda;
window.mmhUrlKategorije = mmhUrlKategorije;

/* ===================================================
   MAKE MY HOME - Products JavaScript
   =================================================== */

let currentFilter = 'all';

let allProducts = [];
let allCategories = [];

/**
 * products.json je 73 kB (sazeto) i skidao se ODMAH na svakoj stranici koja
 * ucita ovu skriptu — i tamo gdje ne treba. Na pocetnoj su proizvodi i
 * kategorije vec ispisani na serveru, pa se taj fajl tu koristi samo za
 * pretragu i traku sa sobama. A dok se skida, otima propusni opseg hero
 * slici, po kojoj se mjeri LCP.
 *
 * Sada se skida na prvi zahtjev. Na katalogu i kategorijama initProductsPage()
 * ga trazi odmah, pa se tamo nista ne mijenja. Ako niko ne zatrazi, krene sam
 * kad se stranica smiri, da pretraga bude spremna prije nego korisnik klikne.
 *
 * Adresa je stabilna (bez Date.now()) da bi radio kes pregledaca; svjezinu
 * pokriva Cache-Control: max-age=0, must-revalidate iz .htaccess.
 */
let _dataPromise = null;
function mmhPodaci() {
  if (!_dataPromise) {
    _dataPromise = Promise.all([
      fetch('data/products.json?v=5').then(r => r.json()),
      fetch('data/categories.json?v=5').then(r => r.json())
    ]).catch(() => [[], []]);
  }
  return _dataPromise;
}
window.mmhPodaci = mmhPodaci;
if (document.readyState === 'complete') setTimeout(mmhPodaci, 300);
else window.addEventListener('load', function () { setTimeout(mmhPodaci, 300); });

// Željeni redoslijed kategorija na stranici "Svi proizvodi"
const CATEGORY_ORDER = [
  'bambus-drveni',
  'bambus-tekstilni',
  'bambus-mermerni',
  'classic',
  'bambus-kozni',
  'bambus-metalni',
  'mdf',
  '3d-letvice',
  'akusticni-paneli',
  'aluminijum-lajsne',
  'pu-kamen',
  'flex-stone',
  'spc-pod'
];

// ===== UČITAJ PODATKE SA SERVERA =====
async function loadData() {
  if (allProducts.length > 0) return;
  try {
    const [products, categories] = await mmhPodaci();
    allProducts = products;
    allCategories = categories;
  } catch (e) {
    console.error('Greška pri učitavanju proizvoda:', e);
    allProducts = [];
    allCategories = [];
  }
}

// ===== GENERIŠI BADGE HTML =====
function getBadgeClass(badge) {
  if (!badge) return '';
  const lower = badge.toLowerCase();
  if (lower.includes('novo') || lower.includes('new')) return 'new';
  if (lower.includes('akcija') || lower.includes('sale')) return 'sale';
  if (lower.includes('limitirano')) return 'limited';
  if (lower.includes('preporučujemo')) return 'recommended';
  if (lower.includes('poručivanje') || lower.includes('porucivanje')) return 'preorder';
  return '';
}

// ===== GENERIŠI KARTICU PROIZVODA =====
function renderProductCard(product, lazy = true) {
  const isPreorder = product.badge && (product.badge.toLowerCase().includes('poručivanje') || product.badge.toLowerCase().includes('porucivanje'));

  const badge = product.badge && !isPreorder
    ? `<div class="product-badge ${getBadgeClass(product.badge)}">${product.badge}</div>`
    : '';

  const preorderOverlay = isPreorder
    ? `<div style="position:absolute;inset:0;background:rgba(0,0,0,0.45);display:flex;align-items:center;justify-content:center;z-index:3;border-radius:inherit;">
        <div style="background:#e67e22;color:#fff;font-weight:800;font-size:13px;letter-spacing:1px;text-transform:uppercase;padding:10px 20px;border-radius:8px;text-align:center;line-height:1.4;box-shadow:0 4px 16px rgba(0,0,0,0.3);">
          <i class="fas fa-clock" style="display:block;font-size:22px;margin-bottom:6px;"></i>
          Samo za<br>poručivanje
        </div>
      </div>`
    : '';

  const categoryName = allCategories.find(c => c.id === product.category)?.name || product.category;

  const imgContent = `
    <img src="${product.image}" alt="${product.name} – ${categoryName} | Make My Home Decor Podgorica"
      onerror="this.onerror=null;this.parentElement.innerHTML='<span class=&quot;product-img-placeholder&quot;><i class=&quot;fas fa-image&quot;></i></span>'"
      ${lazy ? 'loading="lazy"' : ''}>
  `;

  const outOfStock = product.inStock === false;

  const discountRibbon = (product.discount > 0 && !isPreorder && !outOfStock)
    ? `<div style="position:absolute;top:10px;right:10px;background:#c0392b;color:#fff;font-weight:800;font-size:13px;line-height:1;padding:6px 11px;border-radius:8px;z-index:4;box-shadow:0 3px 10px rgba(192,57,43,0.45);letter-spacing:0.3px;">−${product.discount}%</div>`
    : '';

  return `
    <article class="product-card${outOfStock ? ' out-of-stock' : ''}" data-category="${product.category}" data-id="${product.id}" onclick="window.location='${mmhUrlProizvoda(product)}'" style="cursor:pointer;">
      <div class="product-img">
        ${imgContent}
        ${badge}
        ${discountRibbon}
        ${preorderOverlay}
        ${outOfStock ? `<div class="oos-tag">Rasprodato</div>` : ''}
      </div>
      <div class="product-body">
        <div class="product-category">${categoryName}</div>
        <h3 class="product-name">${product.name}</h3>
        ${product.sku ? `<div class="product-sku">Šifra: <strong>${product.sku}</strong></div>` : ''}
        <p class="product-desc">${product.description}</p>
        <div class="product-footer">
          <div class="product-price">
            ${product.discount > 0
              ? `<span style="text-decoration:line-through;color:#767676;font-size:13px;display:block;">${product.price} €</span>
                 <span style="color:#c0392b;font-weight:700;">${(product.price*(1-product.discount/100)).toFixed(2)} €</span>
                 <span style="background:#c0392b;color:#fff;border-radius:12px;padding:2px 8px;font-size:11px;font-weight:700;margin-left:4px;">-${product.discount}%</span>
                 <span style="color:#666e7a;font-size:12px;"> / ${product.unit}</span>`
              : `${product.price} € <span>/ ${product.unit}</span>`
            }
          </div>
          <a href="${mmhUrlProizvoda(product)}" class="btn-card-detail">
            Detaljnije <i class="fas fa-arrow-right"></i>
          </a>
        </div>
      </div>
    </article>
  `;
}

// ===== RENDERUJ FEATURED PROIZVODE (Home page) =====
async function renderFeatured(containerId, limit = 6) {
  const container = document.getElementById(containerId);
  if (!container) return;

  // Pocetnu servira pocetna.php, koja kartice vec ispise na serveru — da ih
  // Google vidi i bez JavaScripta. Ako su vec tu, ne diramo ih.
  if (container.querySelectorAll('.product-card').length > 0) { initAnimations(); return; }

  await loadData();
  const featured = allProducts.filter(p => p.featured).slice(0, limit);

  if (featured.length === 0) {
    container.innerHTML = '<p style="color:var(--gray);text-align:center;grid-column:1/-1;">Nema proizvoda.</p>';
    return;
  }

  container.innerHTML = featured.map(p => renderProductCard(p)).join('');
  initAnimations();
}

// ===== PRODUCTS PAGE – category-first navigation =====
async function initProductsPage() {
  const params = new URLSearchParams(window.location.search);
  // Nova adresa je /kategorija/<kljuc> i nema ?category= — upisuje ga products.php.
  const cat = (typeof window.__mmhCategory === 'string' && window.__mmhCategory) ||
              params.get('cat') || params.get('category');

  // Title/subtitle/grid already handled by inline script in HTML — don't clear

  await loadData();

  if (!cat) {
    showCategoryGrid();
  } else {
    // Check if this cat is a parent with subcategories
    const parentCat = allCategories.find(c => c.id === cat && c.subcategories?.length > 0);
    if (parentCat) {
      showSubcategoryGrid(parentCat);
    } else {
      showCategoryProducts(cat);
    }
  }

  // Fill footer categories
  const footerCats = document.getElementById('footer-cats');
  if (footerCats && footerCats.children.length === 0) {
    footerCats.innerHTML = allCategories.map(c =>
      `<li><a href="/kategorija/${c.id}"><i class="fas fa-chevron-right"></i> ${c.name}</a></li>`
    ).join('');
  }
}

function findCatData(catId) {
  const top = allCategories.find(c => c.id === catId);
  if (top) return top;
  for (const cat of allCategories) {
    if (cat.subcategories) {
      const sub = cat.subcategories.find(s => s.id === catId);
      if (sub) return sub;
    }
  }
  return null;
}

function buildCatMap() {
  const catMap = {};
  allProducts.forEach(p => {
    if (!catMap[p.category]) {
      const catData = findCatData(p.category);
      catMap[p.category] = {
        id: p.category,
        name: catData?.name || p.category,
        icon: catData?.icon || 'fas fa-box',
        color: catData?.color || '#c9a86c',
        description: catData?.description || '',
        count: 0,
        firstImage: null
      };
    }
    catMap[p.category].count++;
    if (!catMap[p.category].firstImage && p.image) {
      catMap[p.category].firstImage = p.image;
    }
  });
  return catMap;
}

function showSubcategoryGrid(parentCat) {
  /* Server (products.php) za roditeljsku kategoriju ispisuje i plocice
     podkategorija i kartice proizvoda, obje odmah vidljive i tim redom.
     Ovdje se zato vise nista ne ispisuje i nista ne gasi — samo se popune
     natpisi. Pravilo je: JavaScript smije filtrirati ono sto je server dao,
     ali ne smije ni ispisivati ni gasiti mreze.

     Kratka istorija, da se ne vrati:
       · prvo su se kartice proizvoda bezuslovno gasile — Google je u sirovom
         HTML-u vidio katalog, a poslije iscrtavanja raskrsnicu sa 62% manje
         teksta, a posjetilac bljesak kartica koje nestanu;
       · zatim su ostale, ali su se plocice i dalje ubacivale iznad njih —
         sve ispod se pomjeralo nadolje, CLS 0,517 (prag je 0,1). */
  const grid = document.getElementById('category-grid');
  const pc = document.getElementById('products-container');
  if (grid && !grid.querySelector('.cat-card')) grid.style.display = 'grid';
  if (pc && !pc.querySelector('.product-card')) pc.style.display = 'none';

  // Back bar
  const backBar = document.getElementById('back-bar');
  if (backBar && backBar.style.display === 'none') backBar.style.display = 'flex';
  /* Sve sto nosi data-seo ispisao je server i ne dira se. Bez toga je Google u
     sirovom HTML-u citao "Proizvodi" u mrvicama i dugme "Sve Kategorije" prema
     katalogu, a posjetilac je poslije JavaScripta vidio ime kategorije i dugme
     prema roditelju — dva razlicita sadrzaja na istoj adresi. */
  const catTitle = document.getElementById('cat-title');
  if (catTitle && !catTitle.dataset.seo) catTitle.textContent = parentCat.name;
  const catCount = document.getElementById('cat-count');
  if (catCount && !catCount.textContent.trim()) catCount.textContent = `${parentCat.subcategories.length} podkategorija`;

  // Breadcrumb
  const breadLabel = document.getElementById('breadcrumb-label');
  if (breadLabel && !breadLabel.dataset.seo) breadLabel.textContent = parentCat.name;
  const pageTitle = document.getElementById('page-title');
  if (pageTitle && !pageTitle.dataset.seo) pageTitle.textContent = parentCat.name;
  const _catSubs = {'bambus-paneli':'Odaberite tip panela','bambus-drveni':'Topla drvena tekstura bambusa – prirodan izgled koji unosi toplinu u svaki prostor','bambus-tekstilni':'Mekana tekstilna površina na bambus osnovi za sofisticiran i elegantan zid','bambus-mermerni':'Mermerni uzorak na bambus panelu – luksuz bez težine i cijene pravog mermera','bambus-metalni':'Metalni sjaj na bambus osnovi za moderan industrijski ili luksuzni enterijer','bambus-kozni':'Kožna površinska obrada za ekskluzivan i taktilno bogat zid','classic':'Klasični paneli s vremenski provjerenim uzorcima prilagođenim svakom stilu','3d-letvice':'Vertikalni rebrasti paneli koji igrom svjetla i sjene transformišu svaki ravni zid','akusticni-paneli':'Poboljšavaju akustiku i smanjuju buku, a pritom izgledaju kao pravi dekorativni element','aluminijum-lajsne':'Profili za završne detalje, ivice i prelaze – savršena finalna tačka svakog enterijera','spc-pod':'Vodootporni laminatni pod koji izdrži kupatilo, kuhinju i svakodnevnu upotrebu','pu-kamen':'Laki poliuretanski paneli koji izgledaju kao pravi kamen, a teže mnogo manje','mdf':'Kaneliran medijapan koji zidovima daje arhitektonski karakter i trodimenzionalnu dubinu','flex-stone':'Savitljivi kameni furnir koji se primjenjuje na ravne, zakrivljene i neravne površine'};
  const pageSub = document.getElementById('page-subtitle');
  // Podnaslov ispisuje server i nosi data-seo — tada se ne dira.
  if (pageSub && !pageSub.dataset.seo) pageSub.textContent = _catSubs[parentCat.id] || 'Pogledajte našu kolekciju';

  // Dugme "nazad" ispisuje server (data-seo) — ne dira se.
  const btnBack = document.querySelector('.btn-back');
  if (btnBack && !btnBack.dataset.seo) { btnBack.href = 'products.html'; btnBack.innerHTML = '<i class="fas fa-arrow-left"></i> Sve Kategorije'; }

  // Plocice je ispisao server. Ako su tu, ne diraju se.
  if (grid && grid.querySelector('.cat-card')) { initAnimations(); return; }
  grid.innerHTML = parentCat.subcategories.map(sub => {
    const subProducts = allProducts.filter(p => p.category === sub.id);
    const firstImg = subProducts.find(p => p.image)?.image || '';
    return `
      <a href="/kategorija/${sub.id}" class="cat-card">
        <div class="cat-card-img">
          ${firstImg
            ? `<img src="${firstImg}" alt="${sub.name}" loading="lazy">`
            : `<i class="${sub.icon || parentCat.icon}"></i>`}
        </div>
        <div class="cat-card-body">
          <div class="cat-card-icon" style="background:${sub.color || parentCat.color}">
            <i class="${sub.icon || parentCat.icon}"></i>
          </div>
          <div class="cat-card-info">
            <h2>${sub.name}</h2>
            <p>${sub.description || ''}</p>
            <span class="cat-card-count">${subProducts.length} proizvoda</span>
          </div>
        </div>
      </a>
    `;
  }).join('');

  initAnimations();
}

function showCategoryGrid() {
  document.getElementById('category-grid').style.display = 'grid';
  document.getElementById('products-container').style.display = 'none';
  document.getElementById('back-bar').style.display = 'none';

  const grid = document.getElementById('category-grid');
  // Katalog sada ispisuje server (products.php) da bi Google vidio kategorije
  // i bez JavaScripta. Ako su kartice vec tu, ne diramo ih.
  if (grid.querySelectorAll('.cat-card').length > 0) { initAnimations(); return; }
  const catMap = buildCatMap();
  const cats = Object.values(catMap).sort((a, b) => {
    const ai = CATEGORY_ORDER.indexOf(a.id);
    const bi = CATEGORY_ORDER.indexOf(b.id);
    if (ai === -1 && bi === -1) return 0;
    if (ai === -1) return 1;
    if (bi === -1) return -1;
    return ai - bi;
  });

  /* Server je ovu mrezu vec ispisao (products.php). Ova funkcija se zove samo
     jednom, pri ucitavanju — nema sortiranja koje bi je ponovo pozvalo — pa bi
     ponovno ispisivanje bilo cist gubitak: isti sadrzaj se izbrise i nacrta
     iznova, uz treptaj i rizik da se ispis iz JavaScripta razidje od onog sa
     servera. Tako je vec bilo sa slikama kategorija na pocetnoj. */
  if (grid.querySelector('.cat-card')) { initAnimations(); return; }

  grid.innerHTML = cats.map(cat => `
    <a href="/kategorija/${cat.id}" class="cat-card">
      <div class="cat-card-img">
        ${cat.firstImage
          ? `<img src="${cat.firstImage}" alt="${cat.name}" loading="lazy">`
          : `<i class="${cat.icon}"></i>`}
      </div>
      <div class="cat-card-body">
        <div class="cat-card-icon" style="background:${cat.color}">
          <i class="${cat.icon}"></i>
        </div>
        <div class="cat-card-info">
          <h2>${cat.name}</h2>
          <p>${cat.description}</p>
          <span class="cat-card-count">${cat.count} proizvoda</span>
        </div>
      </div>
    </a>
  `).join('');

  initAnimations();
}

function showCategoryProducts(catId) {
  document.getElementById('category-grid').style.display = 'none';
  document.getElementById('products-container').style.display = 'grid';

  const catMap = buildCatMap();
  const cat = catMap[catId] || { name: catId, count: 0 };

  // Update breadcrumb & title (subtitle already set by inline script — don't change height)
  const breadLabel = document.getElementById('breadcrumb-label');
  if (breadLabel && !breadLabel.dataset.seo) breadLabel.textContent = cat.name;
  const pageTitle = document.getElementById('page-title');
  if (pageTitle && !pageTitle.dataset.seo) pageTitle.textContent = cat.name;

  // Find parent category if this is a subcategory
  const parentCat = allCategories.find(c => c.subcategories?.some(s => s.id === catId));

  /* Traku iznad server na kategoriji vec ispisuje vidljivom. Paljenje po drugi
     put je pomjeralo sve ispod nje — odatle je dolazio CLS oko 0,05 na svih 13
     lisnatih kategorija. Dira se samo ako je zaista ugasena. */
  const backBar = document.getElementById('back-bar');
  if (backBar && backBar.style.display === 'none') backBar.style.display = 'flex';
  const catTitle = document.getElementById('cat-title');
  if (catTitle && !catTitle.dataset.seo) catTitle.textContent = cat.name;
  const catCount = document.getElementById('cat-count');
  if (catCount && !catCount.textContent.trim()) catCount.textContent = `${cat.count} proizvoda`;

  // Back button: go to parent if subcategory, else go to all categories
  const btnBack = document.querySelector('.btn-back');
  if (btnBack && !btnBack.dataset.seo && parentCat) {
    btnBack.href = `/kategorija/${parentCat.id}`;
    btnBack.innerHTML = `<i class="fas fa-arrow-left"></i> ${parentCat.name}`;
  }

  // Render products
  const container = document.getElementById('products-container');
  const filtered = allProducts.filter(p => p.category === catId);

  if (filtered.length === 0) {
    container.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:var(--gray);padding:60px 0;">Nema proizvoda u ovoj kategoriji.</p>';
    return;
  }

  /* Isto kao gore: products.php je kartice vec ispisao, a ova funkcija se zove
     samo pri ucitavanju. Naslov, brojac i dugme "nazad" iznad se svejedno
     postave, mijenja se samo to da se mreza ne crta po drugi put. */
  if (container.querySelector('.product-card')) { initAnimations(); return; }

  container.innerHTML = filtered.map(p => renderProductCard(p)).join('');
  initAnimations();
}

// ===== RENDERUJ KATEGORIJE (Home page) =====
async function renderCategories(containerId) {
  const container = document.getElementById(containerId);
  if (!container) return;

  /* Ako je server vec ispisao kartice (pocetna.php ih pravi iz categories.json),
     ovdje se ne dira nista. Ranije je ova funkcija bezuslovno prepisivala mrezu
     i vracala slike na CSS pozadine — a CSS pozadina ne poznaje loading="lazy",
     pa se svih 640 kB slika kategorija skidalo odmah, dok se jos ucitava hero.
     Isto je i Googleu pokazivalo praznu mrezu do trenutka dok se JavaScript ne
     izvrsi. Server to radi bolje i ranije; ovo ostaje samo kao rezerva. */
  if (container.querySelector('.category-card')) return;

  await loadData();

  container.innerHTML = allCategories.map(cat => {
    const pos  = cat.imagePosition || {};
    const zoom = pos.zoom || 1.0;
    const posX = pos.posX !== undefined ? pos.posX : 50;
    const posY = pos.posY !== undefined ? pos.posY : 50;
    const imgInner = cat.image
      ? `<div class="category-bg-img"
              style="position:absolute;inset:0;
                background-image:url('${cat.image}');
                background-position:${posX}% ${posY}%;
                background-size:cover;
                background-repeat:no-repeat;
                transform:scale(${zoom});
                transform-origin:${posX}% ${posY}%;
                transition:transform 0.5s ease;
                --zoom:${zoom};"></div>`
      : `<span class="category-img-placeholder"><i class="${cat.icon}"></i></span>`;
    return `
    <a href="/kategorija/${cat.id}" class="category-card">
      <div class="category-img" style="overflow:hidden;position:relative;">
        ${imgInner}
      </div>
      <div class="category-body">
        <div class="category-icon" style="background:${cat.color}">
          <i class="${cat.icon}"></i>
        </div>
        <h2>${cat.name}</h2>
        <p>${cat.description}</p>
        <span class="category-link">Pogledaj <i class="fas fa-arrow-right"></i></span>
      </div>
    </a>
  `; }).join('');

  initAnimations();
}

// ===== PRODUCT DETAIL PAGE =====
async function renderProductDetail() {
  const params = new URLSearchParams(window.location.search);
  // Nova adresa je /paneli/<ime> i nema ?id= — koji je proizvod otvoren upisuje
  // product.php u window.__mmhProduct. ?id= ostaje kao rezerva za stare linkove.
  const id = (window.__mmhProduct && window.__mmhProduct.id) || parseInt(params.get('id'));
  if (!id) { window.location.href = '/products.html'; return; }

  await loadData();
  const product = allProducts.find(p => p.id === id);
  if (!product) { window.location.href = '/products.html'; return; }

  const categoryName = allCategories.find(c => c.id === product.category)?.name || product.category;

  document.title = `${product.name} | Make My Home`;

  const breadcrumb = document.getElementById('breadcrumb-product');
  if (breadcrumb) breadcrumb.textContent = product.name;

  // Gallery
  const galleryMain = document.getElementById('gallery-main');
  const galleryThumbs = document.getElementById('gallery-thumbs');
  // product.php ove dvije kutije vec popuni na serveru, da ih Google vidi.
  //
  // Sta se ovdje desavalo: kutije su se praznile ODMAH, a tek nize se
  // provjeravalo "je li server vec ispisao" — nad vec ispraznjenom kutijom.
  // Provjera je zato uvijek bila netacna i galerija se pri svakom otvaranju
  // brisala pa iscrtavala iznova. Kupac je vidio kako slika i slicice nestanu
  // pa se vrate. Ograda je postojala u kodu, ali nije radila nista.
  //
  // Sada se stanje sa servera zapamti PRIJE bilo kakvog diranja, i prazni se
  // samo ono cega na serveru nije bilo.
  const _srvGlavna  = !!(galleryMain && galleryMain.querySelector('#gallery-main-img'));
  const _srvSlicice = !!(galleryThumbs && galleryThumbs.querySelector('.gallery-thumb'));
  if (galleryThumbs && !_srvSlicice) galleryThumbs.innerHTML = '';
  if (galleryMain && !_srvGlavna) galleryMain.innerHTML = '';

  // Build image list — expose globally so lightbox can navigate
  const _galleryImages = [{ src: product.image, label: `${product.name} – ${categoryName} | Make My Home Decor` }];
  if (product.roomImage) _galleryImages.push({ src: product.roomImage, label: `${product.name} u enterijeru – ${categoryName} | Make My Home Decor` });
  (product.gallery || []).forEach((src, i) => _galleryImages.push({ src, label: `${product.name} detalj ${i + 1} – ${categoryName}` }));
  window._lbImages = _galleryImages;
  let _galleryIndex = 0;
  const multi = _galleryImages.length > 1;

  const _goToGallery = window._goToGallery = function(idx) {
    _galleryIndex = (idx + _galleryImages.length) % _galleryImages.length;
    const img = document.getElementById('gallery-main-img');
    if (img) {
      img.style.opacity = '0';
      setTimeout(() => {
        img.src = _galleryImages[_galleryIndex].src;
        img.alt = _galleryImages[_galleryIndex].label;
        img.onclick = () => openImageLightbox(img.src, product.name);
        img.style.opacity = '1';
      }, 120);
    }
    // Update thumbs
    document.querySelectorAll('.gallery-thumb').forEach((t, i) => {
      t.classList.toggle('active', i === _galleryIndex);
    });
    // Update dots
    document.querySelectorAll('.gallery-dot').forEach((d, i) => {
      d.style.background = i === _galleryIndex ? '#c9a86c' : 'rgba(255,255,255,0.35)';
      d.style.transform = i === _galleryIndex ? 'scale(1.25)' : 'scale(1)';
    });
  }

  /* product.php je glavnu sliku i slicice vec ispisao — Googlebot ih tako vidi
     i bez JavaScripta. Ako su tu, ne diramo ih: samo se zakace dogadjaji za
     klik i listanje. Ranije se cijela galerija crtala iznova, pa se prva slika
     ucitavala DVA puta (jednom sa servera, jednom iz JavaScripta). */
  const vecIspisana = _srvGlavna;   // zapamceno prije praznjenja, vidi gore

  if (galleryMain) {
    const dotWrap = (multi && !vecIspisana) ? `
      <div style="position:absolute;bottom:12px;left:50%;transform:translateX(-50%);
        display:flex;gap:7px;z-index:10;pointer-events:none;">
        ${_galleryImages.map((_, i) => `<span class="gallery-dot" style="
          display:block;width:7px;height:7px;border-radius:50%;transition:all .2s;
          background:${i === 0 ? '#c9a86c' : 'rgba(255,255,255,0.35)'};
          transform:${i === 0 ? 'scale(1.25)' : 'scale(1)'};"
        ></span>`).join('')}
      </div>` : '';
    const _noviHtml = `
      <div style="position:relative;width:100%;height:100%;">
        <img id="gallery-main-img" src="${_galleryImages[0].src}" alt="${_galleryImages[0].label}"
          onclick="openImageLightbox(this.src, '${product.name}')"
          style="cursor:zoom-in;transition:opacity .12s ease;width:100%;height:100%;object-fit:cover;border-radius:16px;"
          onerror="this.style.display='none'">
        ${dotWrap}
      </div>`;
    /* Ispis se preskace ako je server vec nacrtao sliku; dogadjaji za listanje
       se kace u svakom slucaju, da swipe i strelice rade i tada. */
    if (!vecIspisana) galleryMain.innerHTML = _noviHtml;

    // Swipe support (mobile)
    let _tx = 0;
    galleryMain.addEventListener('touchstart', e => { _tx = e.touches[0].clientX; }, { passive: true });
    galleryMain.addEventListener('touchend', e => {
      const dx = e.changedTouches[0].clientX - _tx;
      if (Math.abs(dx) > 40) _goToGallery(_galleryIndex + (dx < 0 ? 1 : -1));
    }, { passive: true });

    // Keyboard arrow keys (desktop)
    if (multi) {
      document.addEventListener('keydown', e => {
        if (e.key === 'ArrowLeft')  _goToGallery(_galleryIndex - 1);
        if (e.key === 'ArrowRight') _goToGallery(_galleryIndex + 1);
      });
    }
  }

  if (galleryThumbs) {
    if (multi && _srvSlicice) {
      /* slicice je vec ispisao product.php — ne diraju se */
      galleryThumbs.style.display = 'flex';
    } else if (multi) {
      galleryThumbs.innerHTML = _galleryImages.map((img, i) => `
        <div class="gallery-thumb ${i === 0 ? 'active' : ''}" onclick="_goToGallery(${i})">
          <img src="${img.src}" alt="${img.label}"
            onerror="this.onerror=null;this.closest(&quot;.gallery-thumb&quot;).style.display='none'">
        </div>`).join('');
      galleryThumbs.style.display = 'flex';
    } else {
      galleryThumbs.style.display = 'none';
    }
  }

  // Sirina letvice iz featuresa (Širina: Xmm). Ako je nema, vraca null —
  // ranije se vracalo 16cm, pa je kalkulator za profile bez upisane sirine
  // (3D02, 3D05, 3D09X, 3D07X) racunao sa izmisljenom mjerom.
  function getLetvicaWidthCm() {
    for (const f of (product.features || [])) {
      const m = f.match(/Širina:\s*(\d+)\s*mm/i);
      if (m) return parseInt(m[1]) / 10;
    }
    return null;
  }
  // Koliko m² pokriva jedan komad. Cita se iz featuresa isto kao u product.php:
  // prvo "(3,42 m²", pa "275×60cm". Bez podatka vraca null — bez izmisljanja.
  function getCoveragePerUnit() {
    if (product.unit === 'm²') return 1;
    if (product.category === '3d-letvice') {
      const w = getLetvicaWidthCm();
      return w ? 2.80 * (w / 100) : null;
    }
    for (const f of (product.features || [])) {
      const m1 = f.match(/\((\d+\s*[.,]\s*\d+)\s*m²/);
      if (m1) {
        const v = parseFloat(m1[1].replace(/\s+/g, '').replace(',', '.'));
        if (v > 0.05) return v;
      }
      const m2 = f.match(/(\d{2,3})\s*[×x]\s*(\d{2,3})\s*cm/i);
      if (m2) {
        const v = (parseInt(m2[1]) / 100) * (parseInt(m2[2]) / 100);
        if (v > 0.05) return v;
      }
    }
    return null;
  }
  // Dimensions info for letvice (shown in calculator)
  const nemaNaStanju = product.inStock === false;
  /* Bez upisane sirine nema ni prikaza dimenzija ni racunanja komada */
  const _letvW = product.category === '3d-letvice' ? getLetvicaWidthCm() : null;
  const letvicaDims = _letvW ? { w: _letvW, h: 280 } : null;  // cm
  const coveragePerUnit = getCoveragePerUnit();

  // PU Kamen panel dimensions from features
  const puDims = product.category === 'pu-kamen' ? (() => {
    for (const f of (product.features || [])) {
      const m = f.match(/(\d+)\s*[×x]\s*(\d+)\s*cm/i);
      if (m) return { w: parseInt(m[1]), h: parseInt(m[2]) };
    }
    return null;
  })() : null;

  // MDF panel dimensions from features (e.g. "Dimenzije: 290×120cm")
  const mdfDims = product.category === 'mdf' ? (() => {
    for (const f of (product.features || [])) {
      const m = f.match(/(\d+)\s*[×x]\s*(\d+)\s*cm/i);
      if (m) return { w: parseInt(m[1]), h: parseInt(m[2]) };
    }
    return null;
  })() : null;

  // Flex Stone panel dimensions from features (e.g. "Dimenzije: 120×60cm")
  const flexDims = product.category === 'flex-stone' ? (() => {
    for (const f of (product.features || [])) {
      const m = f.match(/(\d+)\s*[×x]\s*(\d+)\s*cm/i);
      if (m) return { w: parseInt(m[1]), h: parseInt(m[2]) };
    }
    return { w: 120, h: 60 };
  })() : null;

  // SPC floor plank/tile dimensions from features (e.g. "Dimenzije: 122 × 18 cm")
  const spcDims = product.category === 'spc-pod' ? (() => {
    for (const f of (product.features || [])) {
      const m = f.match(/(\d+[.,]?\d*)\s*[×x]\s*(\d+[.,]?\d*)\s*cm/i);
      if (m) return { w: parseFloat(m[1].replace(',','.')), h: parseFloat(m[2].replace(',','.')) };
    }
    return null;
  })() : null;

  /* Ovdje je bila tabela sa stotinama izmisljenih recenzija (ime, grad, datum,
     ocjena, tekst) i kod koji ih je crtao u blok "Ocjene korisnika". Uklonjeno
     je zajedno sa recenzijama na serveru — nijedna nije bila od stvarnog kupca.
     Kad recenzije budu dolazile iz forme, dolazice sa servera, ne odavde. */

  // Info section
  const info = document.getElementById('product-info-content');
  if (!info) return;

  const roomIcons = {
    'Dnevna soba': 'fas fa-couch', 'Spavaća soba': 'fas fa-bed',
    'Kuhinja': 'fas fa-utensils', 'Kupaonica': 'fas fa-bath',
    'Hodnik': 'fas fa-door-open', 'Ured': 'fas fa-briefcase',
    'Restoran': 'fas fa-concierge-bell', 'Bar/kafić': 'fas fa-coffee',
    'Kućni bioskop': 'fas fa-film', 'Hotel': 'fas fa-hotel',
    'VIP lounge': 'fas fa-glass-cheers', 'Biblioteka': 'fas fa-book'
  };

  const idealForHtml = (product.idealFor || []).map(room => `
    <div class="ideal-room">
      <i class="${roomIcons[room] || 'fas fa-home'}"></i>
      <span>${room}</span>
    </div>`).join('');

  const styleMatchHtml = (product.styleMatch || []).map(s =>
    `<span class="style-badge">${s}</span>`).join('');

  const waIdentifier = product.sku ? `šifra: ${product.sku}` : product.name;
  const waLink = `https://wa.me/38269105222?text=Zdravo%2C%20zanima%20me%20panel%20${encodeURIComponent(waIdentifier)}`;

  const karakteristikeHtml = `
      <div class="spec-item">
        <button class="spec-header" onclick="toggleSpec(this)">
          <span><i class="fas fa-list-check"></i> Karakteristike</span>
          <i class="fas fa-chevron-down spec-arrow"></i>
        </button>
        <div class="spec-body open">
          <ul class="spec-feature-list">
            ${(() => {
              const protMap = [
                // Boje su potamnjene: na svojoj svijetloj podlozi stare su davale
                // kontrast 3,1-4,7 sto je ispod 4,5 koliko trazi WCAG za obican
                // tekst. Nove daju 5,4-7,2, a ostaju iste boje po karakteru.
                { k: 'Vodootporan',            icon: 'fa-droplet',           color: '#155f95' },
                { k: 'Otporan na buđ',         icon: 'fa-shield-halved',     color: '#116343' },
                { k: 'Vatrootporan',           icon: 'fa-fire-flame-curved', color: '#a34a06' },
                { k: 'Otporan na prljavštinu', icon: 'fa-hand-sparkles',     color: '#5c4680' },
              ];
              // Group: lines starting with lowercase are continuations of the line above
              const groups = [];
              for (const f of product.features) {
                if (f.startsWith('Šifra:')) continue;
                const isContd = /^[a-zšđčćžа-я]/.test(f);
                if (isContd && groups.length > 0) {
                  groups[groups.length - 1].cont.push(f);
                } else {
                  groups.push({ main: f, cont: [] });
                }
              }
              return groups.map(({ main, cont }) => {
                // Skip "Pogodan za" — covered by "Idealno za" section
                if (/^Pogodan za/i.test(main)) return '';
                const prot = protMap.find(p => main.startsWith(p.k));
                if (prot) {
                  return `<li style="background:${prot.color}14;border:1px solid ${prot.color}33;border-radius:8px;padding:8px 12px;margin-bottom:4px;">
                    <i class="fas ${prot.icon}" style="color:${prot.color};"></i>
                    <strong style="color:${prot.color};">${main}</strong>
                  </li>`;
                }
                // Join all continuations inline — no chips, no separate lines
                const full = cont.length > 0 ? main + ', ' + cont.join(', ') : main;
                return `<li><i class="fas fa-check"></i>${full}</li>`;
              }).join('');
            })()}
            ${(() => {
              // Cijena po m² — racuna se iz ZIVE cijene, isto kao u product.php.
              // Bez ovoga bi je vidio samo Google (PHP je ispisuje), a kupac ne bi,
              // jer JS ovdje prepisuje cijeli blok karakteristika.
              // Proizvod koji se prodaje po m² vec ima cijenu po m² — dijeljenje sa
              // povrsinom jedne daske davalo je 79,51 €/m² umjesto 17,49 €/m².
              if ((product.unit || '') === 'm²') return '';
              let pov = null;
              for (const f of product.features || []) {
                let m = f.match(/\(([\d.,]+)\s*m²\s*po\s+\S+\)/);
                if (m) { pov = parseFloat(m[1].replace(',', '.')); break; }
                m = f.match(/Dimenzije[^:]*:\s*(\d+(?:[.,]\d+)?)\s*[×x]\s*(\d+(?:[.,]\d+)?)\s*cm/);
                if (m) {
                  pov = (parseFloat(m[1].replace(',', '.')) / 100) * (parseFloat(m[2].replace(',', '.')) / 100);
                  break;
                }
              }
              if (!pov || pov <= 0.05 || !product.price) return '';
              pov = Math.round(pov * 100) / 100;
              const puna  = parseFloat(String(product.price).replace(',', '.'));
              const placa = puna * (1 - (parseFloat(product.discount) || 0) / 100);
              const fmt = n => n.toFixed(2).replace('.', ',');
              return `<li><i class="fas fa-check"></i>Cijena po m²: ${fmt(placa / pov)} €/m² (1 komad pokriva ${fmt(pov)} m²)</li>`;
            })()}
          </ul>
        </div>
      </div>`;

  const accordionHtml = `
    <div class="spec-accordion">

      ${karakteristikeHtml}

      ${idealForHtml || styleMatchHtml ? `
      <div class="spec-item">
        <button class="spec-header" onclick="toggleSpec(this)">
          <span><i class="fas fa-heart"></i> Idealno za & Stil</span>
          <i class="fas fa-chevron-down spec-arrow"></i>
        </button>
        <div class="spec-body">
          ${idealForHtml ? `<div class="ideal-for-grid" style="margin-bottom:12px;">${idealForHtml}</div>` : ''}
          ${styleMatchHtml ? `<div class="style-match-row">${styleMatchHtml}</div>` : ''}
        </div>
      </div>` : ''}

      ${product.highlight ? `
      <div class="spec-item">
        <button class="spec-header" onclick="toggleSpec(this)">
          <span><i class="fas fa-quote-left"></i> O Proizvodu</span>
          <i class="fas fa-chevron-down spec-arrow"></i>
        </button>
        <div class="spec-body">
          <div class="product-highlight">${product.highlight}</div>
        </div>
      </div>` : ''}

    </div>

    <!-- Trust row -->
    <div class="product-trust-row">
      <div class="trust-item"><i class="fas fa-truck"></i><span>Dostava kurirskom službom — okvirno 20 €</span></div>
      <div class="trust-item"><i class="fas fa-tools"></i><a href="montaza.html" style="color:inherit;text-decoration:underline;">Savjeti za montažu</a></div>
      <div class="trust-item"><i class="fas fa-money-bill-wave"></i><span>Plaćanje pouzećem</span></div>
    </div>`;

  // Desnu kolonu ispisuje SERVER (product.php), ne vise ovaj fajl.
  //
  // Ranije je ovdje stajao predlozak koji je u cjelini prepisivao ono sto je
  // server vec ispisao — i to tek posto se skine data/products.json. Kupac je
  // pri osvjezavanju vidio prvo jedan raspored pa drugi, a kalkulator se
  // pojavljivao zadnji. Predlozak je obrisan, a ne samo zaobidjen, da ne
  // ostanu dvije kopije istog HTML-a koje se vremenom raziđu.
  //
  // Ako oznake nema (stara verzija stranice iz kesa), nista se ne dira —
  // bolje je da fali dugme nego da se stranica prepise pred kupcem.
  if (info.dataset.ssr !== '1') {
    console.warn('product-info-content bez data-ssr — server nije ispisao kolonu');
  }

  // Harmoniku ispod glavne slike ispisuje SERVER (mmhHarmonikaHTML u
  // php/kalkulator.php). Ranije je JavaScript prepisivao ono sto je server vec
  // ispisao, pa se pri svakom osvjezavanju vidjela promjena. Ako oznake nema,
  // znaci da je stranica stara verzija iz kesa — tada se ispise kao i prije,
  // da harmonika ne bi nedostajala.
  const gallerySpecs = document.getElementById('gallery-specs');
  if (gallerySpecs && gallerySpecs.dataset.ssr !== '1') gallerySpecs.innerHTML = accordionHtml;

  /* Matching pairs (panel ↔ 3D letvica sa istom nijansom).
     Ovaj odjeljak sada ispisuje SERVER (product.php, blok "PANEL ↔ 3D LETVICA
     ISTE NIJANSE"). Ako je tu, ne crta se ponovo — inace bi Google i posjetilac
     vidjeli dvije iste sekcije jednu pod drugom.
     Tabela ispod je kopija one u product.php. Ako se mijenja jedna, mora i
     druga — inace se sadrzaj razlikuje prije i poslije JavaScripta. */
  const matchingPairs = {
    18: [60], 60: [18],          // CQ006
    19: [64], 64: [19],          // MW010
    23: [61], 61: [23],          // MW300
    24: [63], 63: [24],          // MW321
    25: [67], 67: [25],          // MW682
    26: [62], 62: [26],          // MW312
    37: [82], 82: [37],          // BW229
    39: [80], 80: [39],          // BW224
    43: [81], 81: [43],          // BW809
    45: [79], 79: [45],          // BW008
    110: [77], 77: [110],        // Classic CS029 ↔ 3D Letvica 029 Topli Mahagonija
    112: [72], 72: [112],        // Classic CS013 ↔ 3D Letvica CS013 Hladno Siva
    113: [71], 71: [113],        // Classic CS022 ↔ 3D Letvica CS022 Betonski Sivi
  };

  const partnerIds = matchingPairs[id];
  const _parNaStranici = document.querySelector('.matching-pair-section[data-ssr="1"]');
  if (partnerIds && partnerIds.length > 0 && !_parNaStranici) {
    const partners = partnerIds.map(pid => allProducts.find(p => p.id === pid)).filter(Boolean);
    if (partners.length > 0) {
      const isPanel = partners[0].category === '3d-letvice';
      const letvicaVariants = isPanel && product.category === '3d-letvice';
      const sectionTitle = letvicaVariants
        ? '<i class="fas fa-link"></i> Ostale varijante iste nijanse'
        : isPanel
          ? '<i class="fas fa-link"></i> Ove 3D letvice postoje u istoj nijansi'
          : '<i class="fas fa-link"></i> Ovaj panel postoji u istoj nijansi';
      const sectionSubtitle = letvicaVariants
        ? 'Ista nijansa dostupna je i u ovim završnicama'
        : isPanel
          ? 'Kombiniraj panel sa 3D letvicama iste boje za savršen enterijer'
          : 'Kombiniraj 3D letvice sa panelom iste boje za savršen enterijer';

      const partnerCards = partners.map(p => `
        <a href="${mmhUrlProizvoda(p)}" class="pair-card">
          <div class="pair-card-img">
            <img src="${p.image}" alt="${p.name}" loading="lazy">
            ${p.badge ? `<span class="pair-badge">${p.badge}</span>` : ''}
          </div>
          <div class="pair-card-info">
            <div class="pair-card-name">${p.name}</div>
            <div class="pair-card-price">${(parseFloat(p.price) * (1 - (parseFloat(p.discount) || 0) / 100)).toFixed(2).replace('.', ',')} €<span class="pair-card-unit"> / ${p.unit}</span></div>
            <div class="pair-card-cta">Pogledaj <i class="fas fa-arrow-right"></i></div>
          </div>
        </a>
      `).join('');

      info.insertAdjacentHTML('beforeend', `
        <div class="matching-pair-section">
          <div class="matching-pair-header">
            <div class="matching-pair-title">${sectionTitle}</div>
            <div class="matching-pair-subtitle">${sectionSubtitle}</div>
          </div>
          <div class="pair-cards-row">${partnerCards}</div>
        </div>
      `);
    }
  }


  // Tab switch
  window.switchPqTab = function(tab, btn) {
    document.querySelectorAll('.pq-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('pq-qty').style.display = tab === 'qty' ? '' : 'none';
    document.getElementById('pq-calc').style.display = tab === 'calc' ? '' : 'none';
  };

  // Qty stepper
  window.stepPqQty = function(delta) {
    const el = document.getElementById('pq-qty-val');
    const badge = document.getElementById('pq-m2-badge');
    if (!el) return;
    let val = Math.max(1, parseInt(el.textContent) + delta);
    el.textContent = val;
    if (!badge || !coveragePerUnit) return;
    const total = (val * coveragePerUnit).toFixed(2).replace('.', ',');
    badge.textContent = `${val} ${product.unit === 'm²' ? 'm²' : 'kom'} = ${total} m²`;
  };

  // Calc stepper
  window.stepCalc = function(id, delta) {
    const input = document.getElementById(id);
    if (!input) return;
    let val = Math.round((parseFloat(input.value) + delta) * 10) / 10;
    val = Math.max(parseFloat(input.min), Math.min(parseFloat(input.max), val));
    input.value = val;
    calcPanels();
  };

  window.calcPanels = function() {
    const w = parseFloat(document.getElementById('wall-w')?.value) || 0;
    const h = parseFloat(document.getElementById('wall-h')?.value) || 0;
    const area = w * h;
    const res = document.getElementById('calc-result');
    if (!res || area <= 0) return;

    if (product.category === 'spc-pod') {
      const areaWithWaste = area * 1.10;
      const m2Needed = Math.ceil(areaWithWaste * 10) / 10; // round up to 0.1
      const price = parseFloat(product.price) * (1 - (product.discount || 0) / 100);
      const totalCost = (m2Needed * price).toFixed(2).replace('.', ',');
      res.innerHTML = `
        <div style="line-height:1.7;">
          Prostorija <strong>${w} × ${h} m</strong> = <strong>${area.toFixed(2).replace('.',',')} m²</strong><br>
          <span style="color:#9b7d56;">+10% za rezove</span> → trebaš <strong>${m2Needed.toFixed(1).replace('.',',')} m²</strong><br>
          <span style="font-size:15px;">Okvirna cijena: <strong>~${totalCost} €</strong></span>
        </div>`;
      return;
    }

    const unitPrice = parseFloat(product.price) * (1 - (product.discount || 0) / 100);
    if (!coveragePerUnit) {
      /* Za ovaj profil u podacima nema dimenzija — bolje bez broja nego sa pogresnim */
      res.innerHTML = `Zid <strong>${w} × ${h} m</strong> = <strong>${area.toFixed(1).replace('.', ',')} m²</strong><br>` +
        `<span style="color:#9b7d56;">Za ovaj profil nemamo upisane dimenzije. Pošaljite nam mjere i ` +
        `izračunamo tačan broj komada isti dan — <a href="tel:+38269105222" style="color:#795f32;font-weight:700;">069 105 222</a>.</span>`;
      return;
    }
    const count = Math.ceil(area / coveragePerUnit);
    const totalPrice = (count * unitPrice).toFixed(2).replace('.', ',');

    if (puDims) {
      const areaWithBuffer = area * 1.05;
      const total = Math.ceil(areaWithBuffer / coveragePerUnit);
      const totalCost = (total * unitPrice).toFixed(2).replace('.', ',');
      const label = total === 1 ? 'komad' : total < 5 ? 'komada' : 'komada';
      res.innerHTML = `
        <div style="line-height:1.7;">
          Zid <strong>${w} × ${h} m</strong> = <strong>${area.toFixed(2).replace('.',',')} m²</strong><br>
          <span style="color:#c9a86c;">+5% rezerva</span> → trebaš <strong>${total} ${label}</strong> (${puDims.w}×${puDims.h}cm)<br>
          <span style="font-size:15px;">Okvirna cijena: <strong>~${totalCost} €</strong></span>
        </div>`;
    } else if (mdfDims) {
      const areaWithBuffer = area * 1.05;
      const total = Math.ceil(areaWithBuffer / coveragePerUnit);
      const totalCost = (total * unitPrice).toFixed(2).replace('.', ',');
      const label = total === 1 ? 'komad' : total < 5 ? 'komada' : 'komada';
      res.innerHTML = `
        <div style="line-height:1.7;">
          Zid <strong>${w} × ${h} m</strong> = <strong>${area.toFixed(2).replace('.',',')} m²</strong><br>
          <span style="color:#c9a86c;">+5% rezerva</span> → trebaš <strong>${total} ${label}</strong> (${mdfDims.w}×${mdfDims.h}cm)<br>
          <span style="font-size:15px;">Okvirna cijena: <strong>~${totalCost} €</strong></span>
        </div>`;
    } else if (flexDims) {
      const areaWithBuffer = area * 1.05;
      const total = Math.ceil(areaWithBuffer / coveragePerUnit);
      const totalCost = (total * unitPrice).toFixed(2).replace('.', ',');
      const label = total === 1 ? 'komad' : total < 5 ? 'komada' : 'komada';
      res.innerHTML = `
        <div style="line-height:1.7;">
          Zid <strong>${w} × ${h} m</strong> = <strong>${area.toFixed(2).replace('.',',')} m²</strong><br>
          <span style="color:#c9a86c;">+5% rezerva</span> → trebaš <strong>${total} ${label}</strong> (${flexDims.w}×${flexDims.h}cm)<br>
          <span style="font-size:15px;">Okvirna cijena: <strong>~${totalCost} €</strong></span>
        </div>`;
    } else if (letvicaDims) {
      const total = Math.ceil(area / coveragePerUnit);
      const totalCost = (total * unitPrice).toFixed(2).replace('.', ',');
      const label = total === 1 ? 'letvica' : total < 5 ? 'letvice' : 'letvica';
      res.innerHTML = `Za zid ${w} × ${h} m = <strong>${area.toFixed(1).replace('.',',')} m²</strong> → trebaš <strong>${total} ${label}</strong> (~${totalCost} €)`;
    } else {
      const label = product.unit === 'm²' ? 'm²' : count === 1 ? 'komad' : 'komada';
      res.innerHTML = `Za zid ${w} × ${h} m = <strong>${area.toFixed(1).replace('.',',')} m²</strong> → trebaš <strong>${count} ${label}</strong> (~${totalPrice} €)`;
    }
  };

  // Accordion toggle
  window.toggleSpec = function(btn) {
    const body = btn.nextElementSibling;
    const arrow = btn.querySelector('.spec-arrow');
    const isOpen = body.classList.contains('open');
    body.classList.toggle('open', !isOpen);
    arrow.style.transform = isOpen ? '' : 'rotate(180deg)';
  };

  // Init arrows for open panels
  document.querySelectorAll('.spec-body.open').forEach(b => {
    const arrow = b.previousElementSibling.querySelector('.spec-arrow');
    if (arrow) arrow.style.transform = 'rotate(180deg)';
  });

  // Immediately compute initial calculator result
  calcPanels();

  // Srodni proizvodi — product.php ih vec ispise na serveru.
  // JS popunjava samo ako je kutija prazna (product.html bez PHP-a).
  const relContainer = document.getElementById('related-products');
  if (relContainer && relContainer.querySelectorAll('.product-card').length === 0) {
    const related = allProducts.filter(p => p.category === product.category && p.id !== id).slice(0, 4);
    if (related.length > 0) {
      relContainer.innerHTML = related.map(p => renderProductCard(p)).join('');
      initAnimations();
    }
  }
}

function switchGalleryImg(thumb, src) {
  // Legacy fallback — thumbs now use _goToGallery(index) directly
  const img = document.getElementById('gallery-main-img');
  if (img) { img.src = src; img.onclick = () => openImageLightbox(src, img.alt); }
  document.querySelectorAll('.gallery-thumb').forEach(t => t.classList.remove('active'));
  thumb.classList.add('active');
}

function openImageLightbox(src, name) {
  const images = (window._lbImages && window._lbImages.length > 0)
    ? window._lbImages : [{ src, label: name }];
  let lbIdx = Math.max(0, images.findIndex(i => i.src === src));
  const multi = images.length > 1;
  const isMobile = /iPhone|iPad|Android/i.test(navigator.userAgent);
  const canShare = isMobile && navigator.canShare;

  const lb = document.createElement('div');
  lb.id = 'img-lightbox';
  lb.style.cssText = `position:fixed;inset:0;background:rgba(0,0,0,0.93);z-index:99999;
    display:flex;align-items:center;justify-content:center;`;

  const btnS = `display:inline-flex;align-items:center;gap:8px;padding:9px 18px;
    border-radius:8px;font-size:13px;font-weight:600;border:none;cursor:pointer;`;

  lb.innerHTML = `
    <div style="position:relative;display:flex;flex-direction:column;align-items:center;
        padding:20px;box-sizing:border-box;max-width:100vw;">
      <img id="lb-img" src="${images[lbIdx].src}" alt="${images[lbIdx].label}"
        style="max-width:min(95vw,900px);max-height:75vh;object-fit:contain;border-radius:8px;
        box-shadow:0 0 60px rgba(0,0,0,0.6);display:block;transition:opacity .1s ease;">
      ${multi ? `
        <button id="lb-prev" style="position:fixed;top:50%;left:16px;transform:translateY(-50%);
          width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,0.12);
          border:1.5px solid rgba(255,255,255,0.25);color:#fff;font-size:20px;cursor:pointer;
          display:flex;align-items:center;justify-content:center;z-index:2;">
          <i class="fas fa-chevron-left"></i></button>
        <button id="lb-next" style="position:fixed;top:50%;right:16px;transform:translateY(-50%);
          width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,0.12);
          border:1.5px solid rgba(255,255,255,0.25);color:#fff;font-size:20px;cursor:pointer;
          display:flex;align-items:center;justify-content:center;z-index:2;">
          <i class="fas fa-chevron-right"></i></button>
        <div id="lb-dots" style="display:flex;gap:7px;margin-top:10px;">
          ${images.map((_, i) => `<span class="lb-dot" style="display:block;width:7px;height:7px;
            border-radius:50%;transition:all .2s;
            background:${i===lbIdx?'#c9a86c':'rgba(255,255,255,0.3)'};
            transform:${i===lbIdx?'scale(1.3)':'scale(1)'};"></span>`).join('')}
        </div>
        <span id="lb-counter" style="font-size:11px;color:rgba(255,255,255,0.3);margin-top:4px;">
          ${lbIdx+1} / ${images.length}</span>` : ''}
      <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center;margin-top:14px;">
        ${canShare
          ? `<button id="lb-save-btn" style="${btnS}background:#c9a86c;color:#fff;">
               <i class="fas fa-image"></i> Sačuvaj</button>`
          : `<a id="lb-dl" href="${images[lbIdx].src}" download="${name.replace(/\s+/g,'-')}.jpg"
               style="${btnS}background:#c9a86c;color:#fff;text-decoration:none;">
               <i class="fas fa-download"></i> Preuzmi</a>`}
        <button id="lb-close" style="${btnS}background:rgba(255,255,255,0.15);color:#fff;">
          <i class="fas fa-times"></i> Zatvori</button>
      </div>
      <span style="font-size:11px;color:rgba(255,255,255,0.3);margin-top:8px;">ESC za zatvaranje</span>
    </div>`;

  // Wire buttons using lb.querySelector — works before lb is in the document
  function updateState() {
    const img = lb.querySelector('#lb-img');
    if (img) { img.style.opacity = '0'; setTimeout(() => { img.src = images[lbIdx].src; img.alt = images[lbIdx].label; img.style.opacity = '1'; }, 100); }
    lb.querySelectorAll('.lb-dot').forEach((d, i) => {
      d.style.background = i === lbIdx ? '#c9a86c' : 'rgba(255,255,255,0.3)';
      d.style.transform   = i === lbIdx ? 'scale(1.3)' : 'scale(1)';
    });
    const ctr = lb.querySelector('#lb-counter');
    if (ctr) ctr.textContent = `${lbIdx+1} / ${images.length}`;
    const dl = lb.querySelector('#lb-dl');
    if (dl) dl.href = images[lbIdx].src;
  }

  function goLb(delta) {
    lbIdx = (lbIdx + delta + images.length) % images.length;
    updateState();
  }

  lb.querySelector('#lb-close').addEventListener('click', () => lb.remove());
  lb.querySelector('#lb-prev')?.addEventListener('click', () => goLb(-1));
  lb.querySelector('#lb-next')?.addEventListener('click', () => goLb(1));
  lb.addEventListener('click', e => { if (e.target === lb) lb.remove(); });

  document.body.appendChild(lb);

  // Keyboard — attached after append so it's live immediately
  function onKey(e) {
    if (!lb.isConnected) { document.removeEventListener('keydown', onKey); return; }
    if (e.key === 'Escape')     { lb.remove(); document.removeEventListener('keydown', onKey); }
    if (e.key === 'ArrowLeft')  goLb(-1);
    if (e.key === 'ArrowRight') goLb(1);
  }
  document.addEventListener('keydown', onKey);

  // Swipe
  let _lbTx = 0;
  lb.addEventListener('touchstart', e => { _lbTx = e.touches[0].clientX; }, { passive: true });
  lb.addEventListener('touchend', e => {
    const dx = e.changedTouches[0].clientX - _lbTx;
    if (Math.abs(dx) > 40) goLb(dx < 0 ? 1 : -1);
  }, { passive: true });

  if (canShare) {
    lb.querySelector('#lb-save-btn')?.addEventListener('click', async () => {
      const cur = images[lbIdx];
      try {
        const res = await fetch(cur.src);
        const blob = await res.blob();
        const file = new File([blob], `${name.replace(/\s+/g,'-')}.jpg`, { type: blob.type });
        if (navigator.canShare({ files: [file] })) await navigator.share({ files: [file], title: name });
        else window.open(cur.src, '_blank');
      } catch { window.open(cur.src, '_blank'); }
    });
  }
}

function changeQty(delta) {
  const input = document.getElementById('qty');
  if (!input) return;
  let val = parseInt(input.value) + delta;
  if (val < 1) val = 1;
  input.value = val;
}

// ===== UPIT ZA PROIZVOD =====
function inquireProduct(productName) {
  window.location.href = `contact.html?product=${encodeURIComponent(productName)}`;
}

// ===== ANIMACIJE =====
function initAnimations() {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.style.opacity = '1';
        entry.target.style.transform = 'translateY(0)';
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });

  document.querySelectorAll('.animate-on-scroll').forEach(el => {
    const rect = el.getBoundingClientRect();
    if (rect.top < window.innerHeight && rect.bottom > 0) {
      // Already visible — show instantly, no animation
      el.style.opacity = '1';
      el.style.transform = 'translateY(0)';
    } else {
      el.style.opacity = '0';
      el.style.transform = 'translateY(30px)';
      el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
      observer.observe(el);
    }
  });
}

// ===== URL FILTER =====
function checkUrlFilter() {
  const params = new URLSearchParams(window.location.search);
  const cat = params.get('cat');
  if (cat) {
    currentFilter = cat;
    setTimeout(() => {
      const btn = document.querySelector(`[data-filter="${cat}"]`);
      if (btn) {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const container = document.getElementById('products-container');
        if (container) filterAndRender(container);
      }
    }, 100);
  }
}
