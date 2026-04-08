/* ===================================================
   MAKE MY HOME - Products JavaScript
   =================================================== */

let currentFilter = 'all';

let allProducts = [];
let allCategories = [];

// Start fetching data immediately when script loads — don't wait for initProductsPage()
const _dataPromise = Promise.all([
  fetch('data/products.json?v=5').then(r => r.json()),
  fetch('data/categories.json?v=5').then(r => r.json())
]).catch(() => [[], []]);

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
    const [products, categories] = await _dataPromise;
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
  return '';
}

// ===== GENERIŠI KARTICU PROIZVODA =====
function renderProductCard(product, lazy = true) {
  const badge = product.badge
    ? `<div class="product-badge ${getBadgeClass(product.badge)}">${product.badge}</div>`
    : '';

  const categoryName = allCategories.find(c => c.id === product.category)?.name || product.category;

  const imgContent = `
    <img src="${product.image}" alt="${product.name}"
      onerror="this.parentElement.innerHTML='<span class=\'product-img-placeholder\'><i class=\'fas fa-image\'></i></span>'"
      ${lazy ? 'loading="lazy"' : ''}>
  `;

  const outOfStock = product.inStock === false;

  return `
    <article class="product-card${outOfStock ? ' out-of-stock' : ''}" data-category="${product.category}" data-id="${product.id}" onclick="window.location='product.html?id=${product.id}'" style="cursor:pointer;">
      <div class="product-img">
        ${imgContent}
        ${badge}
        ${outOfStock ? `<div class="oos-tag">Rasprodato</div>` : ''}
        <div class="product-actions">
          <button class="btn-action" title="Brzi pregled" onclick="event.stopPropagation(); openProductModal(${product.id})">
            <i class="fas fa-eye"></i>
          </button>
          <button class="btn-action" title="Pošalji upit" onclick="event.stopPropagation(); inquireProduct('${product.name}')">
            <i class="fas fa-envelope"></i>
          </button>
        </div>
      </div>
      <div class="product-body">
        <div class="product-category">${categoryName}</div>
        <h3 class="product-name">${product.name}</h3>
        <p class="product-desc">${product.description}</p>
        <div class="product-footer">
          <div class="product-price">
            ${product.discount > 0
              ? `<span style="text-decoration:line-through;color:#aaa;font-size:13px;display:block;">${product.price} €</span>
                 <span style="color:#e74c3c;font-weight:700;">${(product.price*(1-product.discount/100)).toFixed(2)} €</span>
                 <span style="background:#e74c3c;color:#fff;border-radius:12px;padding:2px 8px;font-size:11px;font-weight:700;margin-left:4px;">-${product.discount}%</span>
                 <span style="color:#888;font-size:12px;"> / ${product.unit}</span>`
              : `${product.price} € <span>/ ${product.unit}</span>`
            }
          </div>
          <a href="product.html?id=${product.id}" class="btn btn-dark btn-sm">
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
  const cat = params.get('cat') || params.get('category');

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
  if (footerCats) {
    footerCats.innerHTML = allCategories.map(c =>
      `<li><a href="products.html?cat=${c.id}"><i class="fas fa-chevron-right"></i> ${c.name}</a></li>`
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
  document.getElementById('category-grid').style.display = 'grid';
  document.getElementById('products-container').style.display = 'none';

  // Back bar
  const backBar = document.getElementById('back-bar');
  if (backBar) backBar.style.display = 'flex';
  const catTitle = document.getElementById('cat-title');
  if (catTitle) catTitle.textContent = parentCat.name;
  const catCount = document.getElementById('cat-count');
  if (catCount) catCount.textContent = `${parentCat.subcategories.length} podkategorija`;

  // Breadcrumb
  const breadLabel = document.getElementById('breadcrumb-label');
  if (breadLabel) breadLabel.textContent = parentCat.name;
  const pageTitle = document.getElementById('page-title');
  if (pageTitle) pageTitle.textContent = parentCat.name;
  const _catSubs = {'bambus-paneli':'Odaberite tip panela','bambus-drveni':'Topla drvena tekstura bambusa – prirodan izgled koji unosi toplinu u svaki prostor','bambus-tekstilni':'Mekana tekstilna površina na bambus osnovi za sofisticiran i elegantan zid','bambus-mermerni':'Mermerni uzorak na bambus panelu – luksuz bez težine i cijene pravog mermera','bambus-metalni':'Metalni sjaj na bambus osnovi za moderan industrijski ili luksuzni enterijer','bambus-kozni':'Kožna površinska obrada za ekskluzivan i taktilno bogat zid','classic':'Klasični paneli s vremenski provjerenim uzorcima prilagođenim svakom stilu','3d-letvice':'Vertikalni rebrasti paneli koji igrom svjetla i sjene transformišu svaki ravni zid','akusticni-paneli':'Poboljšavaju akustiku i smanjuju buku, a pritom izgledaju kao pravi dekorativni element','aluminijum-lajsne':'Profili za završne detalje, ivice i prelaze – savršena finalna tačka svakog enterijera','spc-pod':'Vodootporni laminatni pod koji izdrži kupatilo, kuhinju i svakodnevnu upotrebu','pu-kamen':'Laki poliuretanski paneli koji izgledaju kao pravi kamen, a teže mnogo manje','mdf':'Kaneliran medijapan koji zidovima daje arhitektonski karakter i trodimenzionalnu dubinu','flex-stone':'Savitljivi kameni furnir koji se primjenjuje na ravne, zakrivljene i neravne površine'};
  const pageSub = document.getElementById('page-subtitle');
  if (pageSub) pageSub.textContent = _catSubs[parentCat.id] || 'Pogledajte našu kolekciju';

  // Back button goes to all categories
  const btnBack = document.querySelector('.btn-back');
  if (btnBack) { btnBack.href = 'products.html'; btnBack.innerHTML = '<i class="fas fa-arrow-left"></i> Sve Kategorije'; }

  // Count products per subcategory
  const grid = document.getElementById('category-grid');
  grid.innerHTML = parentCat.subcategories.map(sub => {
    const subProducts = allProducts.filter(p => p.category === sub.id);
    const firstImg = subProducts.find(p => p.image)?.image || '';
    return `
      <a href="products.html?cat=${sub.id}" class="cat-card">
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
            <h3>${sub.name}</h3>
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
  const catMap = buildCatMap();
  const cats = Object.values(catMap).sort((a, b) => {
    const ai = CATEGORY_ORDER.indexOf(a.id);
    const bi = CATEGORY_ORDER.indexOf(b.id);
    if (ai === -1 && bi === -1) return 0;
    if (ai === -1) return 1;
    if (bi === -1) return -1;
    return ai - bi;
  });

  grid.innerHTML = cats.map(cat => `
    <a href="products.html?cat=${cat.id}" class="cat-card">
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
          <h3>${cat.name}</h3>
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
  if (breadLabel) breadLabel.textContent = cat.name;
  const pageTitle = document.getElementById('page-title');
  if (pageTitle) pageTitle.textContent = cat.name;

  // Find parent category if this is a subcategory
  const parentCat = allCategories.find(c => c.subcategories?.some(s => s.id === catId));

  // Show back bar
  const backBar = document.getElementById('back-bar');
  if (backBar) backBar.style.display = 'flex';
  const catTitle = document.getElementById('cat-title');
  if (catTitle) catTitle.textContent = cat.name;
  const catCount = document.getElementById('cat-count');
  if (catCount) catCount.textContent = `${cat.count} proizvoda`;

  // Back button: go to parent if subcategory, else go to all categories
  const btnBack = document.querySelector('.btn-back');
  if (btnBack && parentCat) {
    btnBack.href = `products.html?cat=${parentCat.id}`;
    btnBack.innerHTML = `<i class="fas fa-arrow-left"></i> ${parentCat.name}`;
  }

  // Render products
  const container = document.getElementById('products-container');
  const filtered = allProducts.filter(p => p.category === catId);

  if (filtered.length === 0) {
    container.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:var(--gray);padding:60px 0;">Nema proizvoda u ovoj kategoriji.</p>';
    return;
  }

  container.innerHTML = filtered.map(p => renderProductCard(p)).join('');
  initAnimations();
}

// ===== RENDERUJ KATEGORIJE (Home page) =====
async function renderCategories(containerId) {
  const container = document.getElementById(containerId);
  if (!container) return;

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
    <a href="products.html?cat=${cat.id}" class="category-card">
      <div class="category-img" style="overflow:hidden;position:relative;">
        ${imgInner}
      </div>
      <div class="category-body">
        <div class="category-icon" style="background:${cat.color}">
          <i class="${cat.icon}"></i>
        </div>
        <h3>${cat.name}</h3>
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
  const id = parseInt(params.get('id'));
  if (!id) { window.location.href = 'products.html'; return; }

  await loadData();
  const product = allProducts.find(p => p.id === id);
  if (!product) { window.location.href = 'products.html'; return; }

  const categoryName = allCategories.find(c => c.id === product.category)?.name || product.category;

  document.title = `${product.name} | Make My Home`;

  const breadcrumb = document.getElementById('breadcrumb-product');
  if (breadcrumb) breadcrumb.textContent = product.name;

  // Gallery
  const galleryMain = document.getElementById('gallery-main');
  const galleryThumbs = document.getElementById('gallery-thumbs');

  // Build image list — expose globally so lightbox can navigate
  const _galleryImages = [{ src: product.image, label: 'Proizvod' }];
  if (product.roomImage) _galleryImages.push({ src: product.roomImage, label: 'U prostoru' });
  (product.gallery || []).forEach((src, i) => _galleryImages.push({ src, label: 'Slika ' + (i + 1) }));
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

  if (galleryMain) {
    const dotWrap = multi ? `
      <div style="position:absolute;bottom:12px;left:50%;transform:translateX(-50%);
        display:flex;gap:7px;z-index:10;pointer-events:none;">
        ${_galleryImages.map((_, i) => `<span class="gallery-dot" style="
          display:block;width:7px;height:7px;border-radius:50%;transition:all .2s;
          background:${i === 0 ? '#c9a86c' : 'rgba(255,255,255,0.35)'};
          transform:${i === 0 ? 'scale(1.25)' : 'scale(1)'};"
        ></span>`).join('')}
      </div>` : '';
    galleryMain.innerHTML = `
      <div style="position:relative;width:100%;height:100%;">
        <img id="gallery-main-img" src="${_galleryImages[0].src}" alt="${product.name}"
          onclick="openImageLightbox(this.src, '${product.name}')"
          style="cursor:zoom-in;transition:opacity .12s ease;width:100%;height:100%;object-fit:cover;border-radius:16px;"
          onerror="this.style.display='none'">
        ${dotWrap}
      </div>`;

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
    if (multi) {
      galleryThumbs.innerHTML = _galleryImages.map((img, i) => `
        <div class="gallery-thumb ${i === 0 ? 'active' : ''}" onclick="_goToGallery(${i})">
          <img src="${img.src}" alt="${img.label}">
        </div>`).join('');
      galleryThumbs.style.display = 'flex';
    } else {
      galleryThumbs.style.display = 'none';
    }
  }

  // Čitaj širinu letvice iz featuresa (Širina: Xmm), fallback 160mm
  function getLetvicaWidthCm() {
    for (const f of (product.features || [])) {
      const m = f.match(/Širina:\s*(\d+)\s*mm/i);
      if (m) return parseInt(m[1]) / 10;
    }
    return 16; // default 160mm
  }
  // Compute m² per unit from features string
  function getCoveragePerUnit() {
    if (product.unit === 'm²') return 1;
    if (product.category === '3d-letvice') return 2.80 * (getLetvicaWidthCm() / 100);
    for (const f of (product.features || [])) {
      const m1 = f.match(/\((\d+[.,]\d+)\s*m²/);
      if (m1) return parseFloat(m1[1].replace(',', '.'));
    }
    return 3.416;
  }
  // Dimensions info for letvice (shown in calculator)
  const letvicaDims = product.category === '3d-letvice'
    ? { w: getLetvicaWidthCm(), h: 280 }  // cm
    : null;
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

  // Reviews data – per product ID, with total count and 2 visible comments
  const reviewsData = {
    // ── Drveni paneli ──
    18: { total: 15, fiveS: 13, fourS: 2, comments: [
      { name: 'Marko T.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Vidio sam ovo kod prijatelja na večeri i odmah znao da moram imati isto. Pitao ga odakle, sljedeće sedmice naručio. Montirao sam sam za 2 sata bez majstora. Svako ko sada dođe pita isto što i ja tada – odakle je to.' },
      { name: 'Jovana M.', city: 'Bar', date: 'Februar 2026', stars: 5, text: 'Kupila za spavaću sobu, muž je bio skeptičan. Sad mi govori da je to bila njegova omiljena promjena u stanu haha. Topla zlatna nijansa tika izgleda kao pravo drvo. Definitivno kupujem još za hodnik.' }
    ]},
    19: { total: 10, fiveS: 8, fourS: 2, comments: [
      { name: 'Tijana R.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Vidjela sam na Instagramu nečiji stan i dva dana tražila koji je panel. Kad sam pronašla – odmah naručila. Montirala sama uz YouTube video. Komšinica kad je vidjela nije mogla vjerovati da je panel. Naručila je isti za sebe.' },
      { name: 'Stefan K.', city: 'Budva', date: 'Januar 2026', stars: 4, text: 'Nordic Oak u spavaćoj je bio savršen izbor. Svaki put kad uđem u sobu malo se osmiješim, toliko je lijep nordijski hrast. Muž već traži da postavimo i u dnevnu sobu. Montaža je bila jednostavnija nego što sam očekivao.' }
    ]},
    20: { total: 12, fiveS: 11, fourS: 1, comments: [
      { name: 'Nikola V.', city: 'Podgorica', date: 'Februar 2026', stars: 5, text: 'Htio nešto tamno za spavaću sobu al me bilo strah. Naručio uzorak, vidio uživo i odmah naručio 10 paketa. Žena je nervozno čekala rezultat – kad je vidjela nije imala šta da kaže. Svi gosti pitaju da li smo uzeli dizajnera.' },
      { name: 'Milica Đ.', city: 'Herceg Novi', date: 'Januar 2026', stars: 5, text: 'Vidio kod komšije Dark Ash iza kreveta i odmah pitao gdje kupio. Postavio i kod sebe i kombinacija tamne pepeljaste boje s bijelom posteljinom je kao u skupim hotelima. Prijatelj koji je majstor za enterijer pitao me odakle materijal.' }
    ]},
    21: { total: 6, fiveS: 5, fourS: 1, comments: [
      { name: 'Petar S.', city: 'Cetinje', date: 'Mart 2026', stars: 5, text: 'Smoke Oak je tačno ta nijansa koju sam tražio – dimljeno siva s drvenim uzorkom, niti previše toplo niti hladno. Postavio u kućnom uredu i videopozivi su mi izgledali mnogo profesionalnije odmah haha. Svi komentarišu pozadinu.' },
      { name: 'Jelena B.', city: 'Podgorica', date: 'Februar 2026', stars: 5, text: 'Poslala sliku prijateljici kako izgleda u dnevnoj sobi i odmah je naručila isti. Neutralna boja s karakterom – ide uz sve. Montaža mi je trebala sat i po, sve sama bez pomoći.' }
    ]},
    22: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Dragan L.', city: 'Bar', date: 'Mart 2026', stars: 5, text: 'Vidjela sam u jednom kafiću u Budvi, pitala konobarica i evo me ovdje. Topla jantarna nijansa hrasta daje onaj kafić-ugođaj koji sam tražila za dnevnu sobu. Svako ko dođe pita – odakle ti ovo.' },
      { name: 'Sanja N.', city: 'Tivat', date: 'Februar 2026', stars: 5, text: 'Naručio za trpezariju. Kad su roditelji došli na ručak mama je mislila da sam postavio pravo drvo. Morala je prstom da pipa da povjeruje. Kupujem još za hodnik, ne mogu se zamisliti bez ovog panela.' }
    ]},
    23: { total: 11, fiveS: 10, fourS: 1, comments: [
      { name: 'Igor M.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Pratila sam stranicu dugo, napokon naručila i ne kajem se ni sekunde. Mocha Oak iza sofe je magičan – neutralno smeđi ali živ i topao. Drugarice su pitale odakle u isto popodne kad su vidjele. Naručujem još.' },
      { name: 'Vesna K.', city: 'Kotor', date: 'Februar 2026', stars: 5, text: 'Renovirali smo kafić s ovim panelom i gosti sjede duže – kažu da je toplije i ugodnije. Vlasnik tvrdi da su recenzije bolje od renovacije. Naručujemo i za drugi lokal koji otvaramo.' }
    ]},
    24: { total: 8, fiveS: 7, fourS: 1, comments: [
      { name: 'Luka P.', city: 'Podgorica', date: 'Februar 2026', stars: 5, text: 'Honey Oak izgleda kao pravo drvo, ta medeno-topla nijansa je autentična. Postavio iza sofe i efekt je nevjerovatan. Dnevna soba je dobila dušu kakvu nije imala s bijelim zidovima. Komšija koji je majstor pitao odakle materijal.' },
      { name: 'Ivana C.', city: 'Budva', date: 'Januar 2026', stars: 5, text: 'Kupila umjesto farbanja zidova. Za manje novca nego što bi platila majstora za farbanje imam zid koji izgleda deset puta bolje. Kad gledam natrag ne mogu vjerovati da nisam ranije to uradila.' }
    ]},
    25: { total: 5, fiveS: 4, fourS: 1, comments: [
      { name: 'Radovan T.', city: 'Nikšić', date: 'Mart 2026', stars: 5, text: 'Havana Oak za biblioteku – bila sam nesigurna ali prijateljica me uvjerila. Sada je to moja omiljena soba u stanu. Svako ko uđe sjedne i kaže "ma ovde je lijepo". Kupujem još za spavaću.' },
      { name: 'Nataša B.', city: 'Podgorica', date: 'Februar 2026', stars: 4, text: 'Vidio u jednom hotelu u Baru i tražio po internetu dok nisam pronašao ovo. Postavio iza kreveta. Žena je bila toliko oduševljena da je odmah pozvala mamu da vidi. Nevjerovatan panel.' }
    ]},
    26: { total: 13, fiveS: 12, fourS: 1, comments: [
      { name: 'Bojan S.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Espresso Teak je moja jedina žalba što nisam naručio ranije. Tamna elegancija bez pretjerivanja. Drugar koji radi enterijer pitao me gdje sam našao ovaj materijal – rekao mu i sada on preporučuje klijentima.' },
      { name: 'Maja F.', city: 'Bar', date: 'Januar 2026', stars: 5, text: 'Postavila u kancelariju pa pozvala klijente na sastanak. Komentarišu od ulaska. Jedna klijentica pitala gdje nabaviti pa sam joj preporučila odmah. Definitivno uzimam još za dom.' }
    ]},
    // ── Tekstilni paneli ──
    37: { total: 12, fiveS: 11, fourS: 1, comments: [
      { name: 'Tijana K.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Vidjela sam kod prijateljice na rođendanu i odmah pitala odakle. Naručila za spavaću sobu i ne mogu prestati gledati taj zid. Kremasto-biserna boja s lanennom teksturom – mama je mislila da sam kupila pravi tekstil. Naručujem još za dnevnu sobu.' },
      { name: 'Srđan P.', city: 'Nikšić', date: 'Februar 2026', stars: 5, text: 'Bila sam skeptična prema panelima generalno ali Perla me je uvjerila. Izgleda skuplje nego što košta. Svi koji dođu kažu "ma lijepo je kod tebe" i odmah znaju za taj zid. Preporučujem svima.' }
    ]},
    38: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Jovana M.', city: 'Tivat', date: 'Mart 2026', stars: 5, text: 'Čista bijela ali nije dosadna bijela – tekstura daje dubinu i zid živi. Muž je odmah primijetio razliku bez da je znao šta sam promijenila. Kaže soba izgleda veća i ljepša. Kupujem još za spavaću.' },
      { name: 'Lazar Đ.', city: 'Bar', date: 'Januar 2026', stars: 4, text: 'Vidjela u nekom interijer magazinu, tražila na internetu i pronašla ovo. Montirala sama, malo muke s uglovima ali rezultat je savršen. Komšija je pitao da li sam uzela majstora. Presretna s kupovinom.' }
    ]},
    39: { total: 8, fiveS: 7, fourS: 1, comments: [
      { name: 'Maja S.', city: 'Kotor', date: 'Mart 2026', stars: 5, text: 'Tražila nešto neutralno za ured ali da nije dosadna farbana siva. Grigio je bio odgovor. Klijenti stalno pitaju što sam uradila s prostorom – kažem panel na zidu i svi budu iznenađeni. Jedna me pitala da joj preporučim gdje kupiti.' },
      { name: 'Nikola B.', city: 'Podgorica', date: 'Februar 2026', stars: 5, text: 'Vidio kod kolege i odmah pitao odakle. Postavio isti i kažem ti razlika je ogromna. Sivi tekstilni panel niti je topao niti hladan – uvijek elegantno. Svako ko dođe primijeti i komentariše.' }
    ]},
    40: { total: 11, fiveS: 10, fourS: 1, comments: [
      { name: 'Ana R.', city: 'Herceg Novi', date: 'Mart 2026', stars: 5, text: 'Slate je taj panel koji ide uz doslovno sve. Naručila za dnevnu sobu pa naručila još za spavaću pa sad i hodnik haha. Sestra je vidjela i odmah bila ljubomorna i naručila za sebe.' },
      { name: 'Dejan V.', city: 'Budva', date: 'Februar 2026', stars: 5, text: 'Boja je tačno kao na slikama, što je rijetko za online kupovinu. Silikon drži odlično, montaža laka. Žena kaže da je Slate bila njena omiljena promjena u renovaciji. Definitivno naručujemo još.' }
    ]},
    41: { total: 7, fiveS: 6, fourS: 1, comments: [
      { name: 'Jelena F.', city: 'Cetinje', date: 'Mart 2026', stars: 5, text: 'Htjela bijelu zidnu oblogu koja nije sterilna kao farba. Blanc je tačno to – topla bijela s finom teksturom. Mama je pitala da li sam nabavila skupocjenu tapetu, nije mogla vjerovati kad sam joj rekla cijenu. Naručuje za sebe.' },
      { name: 'Marija T.', city: 'Podgorica', date: 'Januar 2026', stars: 5, text: 'Postavio u kupatilo – da nisam vidio kod prijatelja nikad ne bih smislio. Izgleda luksuzno, lako se čisti. Svi gosti koji dođu pitaju odakle. Već sam preporučio trojici i svi su naručili.' }
    ]},
    42: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Petar L.', city: 'Tivat', date: 'Februar 2026', stars: 5, text: 'Siena se ne može opisati riječima – ta topla sivo-smeđa s tekstilnom površinom. Vidjela na nekoj fotografiji interijera i dva dana tražila koji je panel. Kad sam pronašla naručila odmah. Uživo je još ljepše nego na slici.' },
      { name: 'Sandra N.', city: 'Bar', date: 'Januar 2026', stars: 5, text: 'Renovirali kafić i Siena je bio hit od prvog dana. Gosti sjede duže, kažu da je ugodnije. Vlasnik tvrdi da su mu se recenzije popravile od renovacije. Već naručujemo za drugi lokal.' }
    ]},
    43: { total: 6, fiveS: 5, fourS: 1, comments: [
      { name: 'Bojan K.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Glacier je nešto između bijele i ledeno plave i tačno to daje moderan, svjež izgled. Postavljeno u kupatilu i muž je bio skeptičan – sada kaže da je to bila nabolja promjena u stanu. Svaki gost primijeti.' },
      { name: 'Vesna M.', city: 'Budva', date: 'Februar 2026', stars: 4, text: 'Vidio u jednoj privatnoj klinici i odmah pitao recepciju odakle materijal. Naručio za ured i svi kažu da je prostor mnogo mirniji. Kupujem još za hodnik, jednostavno mi se sviđa ovaj panel.' }
    ]},
    44: { total: 10, fiveS: 9, fourS: 1, comments: [
      { name: 'Ivana S.', city: 'Nikšić', date: 'Mart 2026', stars: 5, text: 'Buklé tekstura na panelu – nisam znala da postoji a sad ne mogu zamisliti spavaću sobu bez nje. Vidjela kod drugarice i mislila da je pravi tekstil na zidu. Kad sam saznala da je panel naručila odmah. Sad i drugarice pitaju pa sam im dala kontakt.' },
      { name: 'Aleksandar P.', city: 'Podgorica', date: 'Januar 2026', stars: 5, text: 'Za recepciju smo tražili nešto upečatljivo, Pura buklé je bila savršen izbor. Svaki klijent koji dođe komentariše taj zid. Već naručili još za konferencijsku salu. Niko ne pogađa da je panel.' }
    ]},
    45: { total: 13, fiveS: 12, fourS: 1, comments: [
      { name: 'Milena D.', city: 'Kotor', date: 'Mart 2026', stars: 5, text: 'Šampanjac tekstilni panel sam vidjela u hotelu u Bečićima i pitala recepciju. Uputili me ovdje, naručila za dnevnu sobu. Sad dvije drugarice naručile isti nakon što su bile u posjeti. Niko ne može vjerovati da nije pravi tekstil.' },
      { name: 'Stefan J.', city: 'Herceg Novi', date: 'Februar 2026', stars: 5, text: 'Postavio u trpezariji uz lustere – svaki put kad imamo goste komentarišu taj zid. Jedna drugarica odmah uzela broj i naručila za sebe isti dan. Definitivno kupujem još, ne mogu se zamisliti bez Deve.' }
    ]},
    // ── Mermerni paneli ──
    46: { total: 11, fiveS: 10, fourS: 1, comments: [
      { name: 'Dragana M.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Htio pravi mermer ali cijena je bila nerealna. Našao ovo, naručio jedan paket za probu i ostao u šoku koliko izgleda realno. Naručio još 8 paketa. Komšija koji je majstor mislio da je pravi mermer dok nisam mu pokazao.' },
      { name: 'Zoran K.', city: 'Budva', date: 'Februar 2026', stars: 5, text: 'Vidjela sam kod prijateljice u Tivtu, nisu htjela reći odakle haha, ali sam pronašla sama. Postavljeno u dnevnoj sobi iza TV-a. Svi koji dođu pitaju je li to pravi mermer. Kupujem još za ulazni hol.' }
    ]},
    47: { total: 8, fiveS: 7, fourS: 1, comments: [
      { name: 'Kristina P.', city: 'Kotor', date: 'Mart 2026', stars: 5, text: 'Desert Stone ima tople pješčano-sive tonove, potpuno drugačije od klasičnog mermera. Brat je bio u posjeti i pitao "je li to pravi kamen?" – kad sam mu rekla da je panel odmah naručio za svoju kuću.' },
      { name: 'Nemanja R.', city: 'Nikšić', date: 'Januar 2026', stars: 4, text: 'Renovirali recepciju i Desert Stone je bio hit. Gosti često komentarišu koliko je prostor lijep. Vlasnik kaže da je ovo bila njegova omiljena promjena u renovaciji i već planira drugi prostor.' }
    ]},
    48: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Ivana B.', city: 'Herceg Novi', date: 'Februar 2026', stars: 5, text: 'Bijeli mermer zvuči skupo, a ovaj panel je rješenje. Moj frizer koji dolazi kući mislio da sam potrošila hiljade eura. Smijala sam se kad sam mu rekla pravu cijenu. Naručila još za kupatilo.' },
      { name: 'Rade S.', city: 'Tivat', date: 'Januar 2026', stars: 5, text: 'Vidio u jednom poznatom restoranu u Kotoru i pitao konobarica. Naručio za kuhinju i žena mi se bacila oko vrata kad je vidjela. Svi gosti pitaju je li to pravi mermer. Kupujem još za kupatilo.' }
    ]},
    49: { total: 7, fiveS: 6, fourS: 1, comments: [
      { name: 'Snežana J.', city: 'Bar', date: 'Mart 2026', stars: 5, text: 'Lava Stone je bio hrabar potez za dnevnu sobu – brat me odgovarao. Sada mi kaže da je to bio moj životni potez haha. Bar koji smo otvorili ima ga na zidu i gosti ga fotografišu svaki dan.' },
      { name: 'Miloš V.', city: 'Cetinje', date: 'Februar 2026', stars: 5, text: 'Htio nešto drugačije za dnevnu sobu, ne drvo ne mermer. Lava Stone je bio savršen odgovor. Žena je bila sumnjičava, sada mi zahvaljuje svaki dan haha. Kupujem još za hodnik.' }
    ]},
    50: { total: 13, fiveS: 12, fourS: 1, comments: [
      { name: 'Tatjana Đ.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Vidjela kod prijatelja i bila oduševljena. Mislila sam da je pravi beton i pitala koliko koštalo oblaganje. Kad su mi rekli da je panel nisam vjerovala. Odmah naručila i postavljeno za vikend. Presretna.' },
      { name: 'Goran N.', city: 'Bijelo Polje', date: 'Februar 2026', stars: 5, text: 'Beton izgled bez težine betona. Postavio u uredu i klijenti sjede opuštenije, duže se zadržavaju. Kolega iz firme je naručio za svoj ured po mome savjetu. Urban Concrete je savršen za moderan poslovni prostor.' }
    ]},
    51: { total: 10, fiveS: 9, fourS: 1, comments: [
      { name: 'Bojana L.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Tražila beton izgled ali da nije hladan. Nordic Concrete je tačno to – topliji kremasti beton. Postavljeno u dnevnoj sobi i sad je to moja omiljena soba u stanu. Mama je pitala što sam uradila i odmah htjela isto za sebe.' },
      { name: 'Danilo F.', city: 'Budva', date: 'Januar 2026', stars: 5, text: 'Renovirali spa centar s Nordic Concrete panelima. Klijenti kažu da se odmah osjete opuštenije čim uđu. Vlasnica prezadovoljna, kaže da planira i drugu prostoriju s istim panelom.' }
    ]},
    52: { total: 6, fiveS: 5, fourS: 1, comments: [
      { name: 'Marina T.', city: 'Kotor', date: 'Februar 2026', stars: 5, text: 'Noir Stone za kućni bioskop – bila ideja prijatelja i u početku skeptičan. Sada mu zahvaljujem svaki put kad gledam film. Tamna kamena tekstura apsorbira sve. Svi koji dođu žele isti.' },
      { name: 'Predrag M.', city: 'Podgorica', date: 'Januar 2026', stars: 4, text: 'Premium bar smo otvarali i Noir Stone na zidovima je bio hit od prvog dana. Gosti fotografišu prostor i dijele na mrežama. Od renovacije nam se povećao broj rezervacija. Naručujemo više.' }
    ]},
    53: { total: 12, fiveS: 11, fourS: 1, comments: [
      { name: 'Jelena S.', city: 'Nikšić', date: 'Mart 2026', stars: 5, text: 'Bež mermer je moderan izbor koji ne zastarijeva. Postavljeno u kupaonici i kuma koja je bila skeptična prema panelima rekla mi da je ovo promijenilo njeno mišljenje. Odmah naručuje za sebe.' },
      { name: 'Stefan B.', city: 'Bar', date: 'Februar 2026', stars: 5, text: 'Vidio u jednom hotelu u Herceg Novom i pitao recepciju. Pronašao ovo i naručio za kupatilo. Žena je bila toliko oduševljena da smo naručili još za spavaću sobu. Svi gosti pitaju odakle.' }
    ]},
    54: { total: 8, fiveS: 7, fourS: 1, comments: [
      { name: 'Aleksandra C.', city: 'Tivat', date: 'Mart 2026', stars: 5, text: 'Dark Luxe je za one koji žele nešto posebno. U VIP sobi restorana izgleda spektakularno – gosti posebno traže tu sobu od kako smo renovirali. Vlasnik se hvali svima i preporučuje panel.' },
      { name: 'Vuk J.', city: 'Podgorica', date: 'Januar 2026', stars: 5, text: 'Htio kancelariju koja odiše prestižem. Dark Luxe je bio savršen. Svaki poslovni partner komentariše zid. Jedan je pitao kontakt za majstora – rekao mu da sam sam postavljao, pa mu rekao odakle panel.' }
    ]},
    55: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Milica R.', city: 'Herceg Novi', date: 'Mart 2026', stars: 5, text: 'Sahara sam vidjela u jednom resortu i tražila dok nisam pronašla. Postavljeno u dnevnoj sobi i osjećam se kao na odmoru svaki dan. Naručujem još za trpezariju, ne mogu bez ove boje.' },
      { name: 'Andrija K.', city: 'Budva', date: 'Februar 2026', stars: 5, text: 'Za hotelski lobi – Sahara je bio savršen. Topla mediteranska nijansa uz zelenilo i drvo, gosti odmah oduševljeni. Recenzije hotela su se popravile od renovacije. Naručujemo i za restoran.' }
    ]},

    // ── Metalni paneli ──
    56: { total: 8, fiveS: 7, fourS: 1, comments: [
      { name: 'Radovan T.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Četkano zlato zvuči kičasto ali u realnosti je elegantno i suzdržano. Vidio kod drugarice u stanu, odmah naručio isti. Postavljeno u trpezariji i svaki gost ostane šutke kad ga prvi put vidi. Svi pitaju odakle.' },
      { name: 'Katarina M.', city: 'Budva', date: 'Februar 2026', stars: 5, text: 'Premium frizerski salon smo otvarali i Brushed Gold je bio pravi hit. Od dana otvaranja gosti fotografišu zidove i dijele na Instagramu. Vlasnica kaže da je panel bila njena omiljena investicija.' }
    ]},
    57: { total: 11, fiveS: 10, fourS: 1, comments: [
      { name: 'Dragan S.', city: 'Bar', date: 'Mart 2026', stars: 5, text: 'Raw Steel u novom baru je bio hit od prvog dana. Čelična tekstura uz drvene stolove i industrijske lampe – gosti sjede i kažu da je prostorija posebna. Vlasnik je odmah rekao da naručujemo još. Pravo rješenje.' },
      { name: 'Nataša V.', city: 'Tivat', date: 'Januar 2026', stars: 5, text: 'Htio loft izgled u stanu bez rušenja zidova. Raw Steel panel na jednom zidu je pretvorio stan u dizajnerski loft. Drugar koji je arhitekt pitao koji panel je – i sam ga sada preporučuje klijentima.' }
    ]},
    58: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Marko Đ.', city: 'Nikšić', date: 'Februar 2026', stars: 5, text: 'Šampanjac metalna nijansa je suptilna elegancija – nije zlatna ni srebrna nego nešto između. Postavljeno u spavaćoj i svaki put kad uđem osjećam se kao u luksuznom hotelu. Kupujem još za hodnik.' },
      { name: 'Jovana K.', city: 'Podgorica', date: 'Januar 2026', stars: 4, text: 'Vidio u butik hotelu i pitao recepciju koji materijal. Pronašao ovaj panel, naručio za dnevnu sobu. Žena bila oduševljena, odmah pozvala sestru da vidi. Sestra naručila isti dan haha.' }
    ]},
    59: { total: 7, fiveS: 6, fourS: 1, comments: [
      { name: 'Aleksandar B.', city: 'Kotor', date: 'Mart 2026', stars: 5, text: 'Bronzani metalni panel je jedinstven izbor – ni zlatni ni smeđi nego nešto između što daje prostoru toplinu i luksuz istovremeno. Postavljeno u restoranskoj VIP sali i efekt je impresivan.' },
      { name: 'Mirjana F.', city: 'Herceg Novi', date: 'Februar 2026', stars: 5, text: 'Bronza u kombinaciji s tamnim drvom je savršen spoj. Trpezarija izgleda kao iz ekskluzivnog restorana. Panel je čvrst, lako se čisti i tokom vremena dobija patinu koja mu daje još više karaktera.' }
    ]},

    60: { total: 14, fiveS: 13, fourS: 1, comments: [
      { name: 'Igor S.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Vidio sam ove letvice kod prijatelja u dnevnoj sobi i nisam mogao vjerovati da je to zid. Pitao sam ga šta je to, objasnio mi je – sljedeće sedmice sam naručio. Postavio sam ih sam za jedan popodne. Žena je kad je vidjela odmah rekla da radimo i spavaću sobu isto. Sjene i svjetlo pod lampama se mijenjaju tokom dana – nikad nije dosadna.' },
      { name: 'Tijana R.', city: 'Bar', date: 'Februar 2026', stars: 5, text: 'Tražila nešto toplo za hodnik, vidjela na Instagramu jednoj djevojci. Pisala joj, uputila me ovdje. Montaža silikonom bila odlična, PVC se lako reže skalpelom pa sam sama krojila oko utičnica. Svako ko uđe stane i gleda taj hodnik. Već sam naručila još za kuhinju.' }
    ]},
    61: { total: 11, fiveS: 10, fourS: 1, comments: [
      { name: 'Stefan M.', city: 'Budva', date: 'Mart 2026', stars: 5, text: 'Renovirao sam dnevnu sobu i tražio nešto moderno ali ne pretjerano. Bijele letvice su izgledali jednostavno na slici – uživo su nevjerovatne. Ta igra sjenki između letvica pod lampama je ono zbog čega svaki gost ustane i pita odakle je to. Muž koji je bio skeptičan sad traži da ih stavimo i u ured.' },
      { name: 'Maja L.', city: 'Tivat', date: 'Januar 2026', stars: 5, text: 'Postavila bijele letvice i iza stavila LED traku. Nisam očekivala da će izgledati ovako – ambijentalnost kakvu sam viđala samo u hotelima. Mama je bila u posjeti i odmah rekla da joj treba isto za njenu dnevnu sobu. Naručujem još za spavaću.' }
    ]},
    62: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Nikola V.', city: 'Nikšić', date: 'Februar 2026', stars: 5, text: 'Pepeljasti hrast je ta nijansa između sive i drveta koja ide uz sve. Prijatelj koji radi enterijer rekao mi je da izabere ovaj model i bio je u pravu. Postavljeno u kućnom uredu i klijenti uvijek komentarišu. Jedan je pitao koji majstor je radio – rekao sam da sam sam montirao pa se čudio.' },
      { name: 'Sanja B.', city: 'Podgorica', date: 'Januar 2026', stars: 4, text: 'Vidjela u jednom kozmetičkom salonu i pitala vlasnice odakle. Dobile su od ovdje. Naručila za svoju kuću za spavaću sobu. Muž je bio nevjerica al kad je vidio gotov zid nije imao komentar – samo se smijao i kazao "okej, super je". Kupujem još za hodnik.' }
    ]},
    63: { total: 10, fiveS: 9, fourS: 1, comments: [
      { name: 'Petar D.', city: 'Kotor', date: 'Mart 2026', stars: 5, text: 'Tražio nešto toplo za hodnik jer je bio previše sterilno bijel. Zlatni kesten je bio savršen izbor – bogata, topla boja i 3D reljef koji izgleda potpuno drugačije ujutro i uveče. Svaki gost kad uđe stane i pipa rukom da provjeri je li pravo drvo. Naručujem još za dnevnu sobu.' },
      { name: 'Jelena N.', city: 'Bar', date: 'Februar 2026', stars: 5, text: 'Imala sam trpezariju s bijelim zidovima i nije živjela. Zlatni kesten na jednom zidu je promijenio sve. Sada kad sijemo za stol svi komentarišu koliko je ugodnije. Dvije komšinice su naručile iste nakon što su bile na ručku. Sjene pod spotovima su fenomenalne.' }
    ]},
    64: { total: 13, fiveS: 12, fourS: 1, comments: [
      { name: 'Milena P.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Ovo su bijele letvice ali nisu "obične bijele" – daju zidu treću dimenziju kakvu ne može dati nikakva farba. Vidjela u jednom butik hotelu u Bečićima i pitala recepciju. Pronašla Make My Home, naručila za spavaću sobu. Muž je mislio da sam potrošila puno novca – kad je saznao cijenu nije mogao vjerovati.' },
      { name: 'Vuk J.', city: 'Budva', date: 'Januar 2026', stars: 5, text: 'Postavio u spavaćoj sobi, stavio LED traku iza letvica i to je to. Sad svaki gost pita ko je radio enterijer. Reknem da sam sam postavio i ne vjeruju. Montaža je stvarno jednostavna – PVC se lako reže, silikon drži perfektno. Naručujem još za dnevnu sobu.' }
    ]},
    65: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Dragana K.', city: 'Herceg Novi', date: 'Februar 2026', stars: 5, text: 'Tražila bijelu opciju za dnevnu sobu ali nešto što ne blješti – mat verzija je bila tačan odgovor. Puno prirodnog svjetla u prostoru i sjajne letvice bi se vidjele previše. Ove mat su diskretne, elegantne i svako ko dođe komentariše koliko je sobaljepa. Preporučila sam ih troje prijatelja.' },
      { name: 'Bojan R.', city: 'Cetinje', date: 'Januar 2026', stars: 5, text: 'Brat mi je postavio mat bijele letvice u dnevnoj sobi i nisam mu vjerovao da to može izgledati ovako dobro. Kad sam vidio uživo odmah sam naručio za svoju. Razlika između mat i gloss je ogromna – mat daje neku ozbiljnost i klasu. Komšija je naručio iste po mome savjetu.' }
    ]},
    66: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Ivana S.', city: 'Tivat', date: 'Mart 2026', stars: 5, text: 'Sjajne bijele letvice su za one koji žele dramatičan efekat. Uveče kad se upale spotovi zid se pretvori u nešto nevjerovatno – sjaj i sjenka naizmjenično. Svaki gost ko dođe prvi put stane i gleda. Jedna drugarica mi je rekla da nikad nije vidjela nešto ovako u normalnom stanu. Kupujem još.' },
      { name: 'Luka M.', city: 'Podgorica', date: 'Februar 2026', stars: 5, text: 'Sjajne letvice izgledaju potpuno drugačije danju i noću. Danju su čiste i moderne, noću uz lampe dobijaju neku dubinu i luksuz. Žena je bila skeptična al sada svaki dan komentariše koliko je dnevna soba posebna. Jedina žalba je što nisam ranije znao za ove.' }
    ]},
    67: { total: 12, fiveS: 11, fourS: 1, comments: [
      { name: 'Goran T.', city: 'Nikšić', date: 'Mart 2026', stars: 5, text: 'Tamni orah u 3D formatu iza sofe je moj životni potez. Drugar koji radi enterijer design pitao me koji materijal sam koristio i nije mogao vjerovati da je PVC panel. Sjenka između letvica pod bočnim svjetlom izgleda kao skulptura. Svako ko sjedi u toj sobi primijeti. Naručujem još za spavaću.' },
      { name: 'Ana Đ.', city: 'Bar', date: 'Januar 2026', stars: 5, text: 'Vidjela u jednom restoranu, pitala konobara koji materijal. Naručila za dnevnu sobu kući. Muž je mislio da je pravo drvo dok nije prstom pritisnuo. Sada je totalno oduševljen. Zid izgleda trodimenzionalno – ne može to da se fotografiše onako kako izgleda uživo.' }
    ]},
    68: { total: 10, fiveS: 9, fourS: 1, comments: [
      { name: 'Danilo V.', city: 'Budva', date: 'Mart 2026', stars: 5, text: 'Širi 170mm profil daje zidu jednu arhitektonsku ozbiljnost. Vidjela u jednoj kancelariji gdje sam išla na sastanak i odmah pitala vlasnika. Naručila za svoju dnevnu sobu i efekat je totalno drugačiji od tanjih letvica – dublje sjene, snažniji utisak. Sestra je naručila iste čim je vidjela kod mene.' },
      { name: 'Kristina B.', city: 'Podgorica', date: 'Februar 2026', stars: 5, text: 'Širi profil i hladna siva boja je kombinacija koja diše industrijskim dizajnom. Imamo betonski pod i sad ta dva zajedno izgledaju kao loft stan. Drugar arhitekt rekao mi da sam napravila savršen enterijer spoj. Preporučujem svima koji žele moderan i jak zid.' }
    ]},
    69: { total: 11, fiveS: 10, fourS: 1, comments: [
      { name: 'Nemanja S.', city: 'Kotor', date: 'Februar 2026', stars: 5, text: 'Bež lanen je ta boja koja smiruje sobu. Postavljeno u spavaćoj i odmah je prostorija dobila onaj zen karakter koji sam tražio. Žena koja je tražila nešto toplije od bijele je bila oduševljena. Sada kaže da je spavaća soba njena omiljena soba u stanu. Kupujem još za hodnik.' },
      { name: 'Vesna P.', city: 'Herceg Novi', date: 'Januar 2026', stars: 5, text: 'Kombinovala bež lanen letvice s drvenim detaljima nameštaja i zelenim biljkama – rezultat je kao s Instagrama ali u svom stanu. Drugarica koja je bila u posjeti rekla mi da izgleda skuplje nego što vjeruje da je koštalo. Tačno – i lepo i pristupačno. Naručujem još.' }
    ]},
    70: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Srđan K.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Ova bijela sa drvenim uzorkom je nešto između – nije ni čisto bijela ni drvo, nego nešto posebno. Prijatelj koji je vidio u mom stanu odmah pitao odakle i naručio za sebe. Širi profil 170mm pravi duboke sjene pod bočnim lampama. Soba je totalno promijenjena i svako primijeti razliku.' },
      { name: 'Milica T.', city: 'Bar', date: 'Januar 2026', stars: 4, text: 'Tražila bijelo ali ne sterilno – ovo je savršen kompromis. Drveni uzorak na bijeloj podlozi daje toplinu bez tamnih boja. Montirala sama za jedno poslijepodne. Mama je pitala da li sam uzela majstora pa je bila iznenađena kad joj rekoh da sam sama. Preporučujem.' }
    ]},
    71: { total: 12, fiveS: 11, fourS: 1, comments: [
      { name: 'Zoran M.', city: 'Nikšić', date: 'Mart 2026', stars: 5, text: 'Vidio u jednom kafiću betonske sive letvice i mislio su pravi beton. Pitao konobarica i rekla mi odakle. Naručio za kućni ured – sad je to moja omiljena prostorija. Klijenti koji dolaze na sastanke komentarišu izgled ureda. Jedan je pitao koji dizajner je radio – nasmijao sam se i rekao da sam sam.' },
      { name: 'Aleksandra J.', city: 'Budva', date: 'Februar 2026', stars: 5, text: 'Htjela loft izgled bez rušenja zidova. Betonska letvica + 3D efekat je bila tačno to. Dnevna soba sad izgleda kao industrijski stan za dvostruko manje novca nego prava preuređenje. Drugarice su bile na koktelu i sve su pitale odakle, sve su naručile u roku od sedmice haha.' }
    ]},
    72: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Rade B.', city: 'Tivat', date: 'Februar 2026', stars: 5, text: 'Postavio u poslovnom prostoru jer sam tražio nešto ozbiljno i moderno. Hladna siva letvica je tačno to – nijansa koja govori o ozbiljnosti i klasi. Poslovni partneri koji dolaze uvijek komentarišu uredsku atmosferu. Jedna firma je pitala gdje smo uzeli pa smo im dali kontakt.' },
      { name: 'Bojana S.', city: 'Podgorica', date: 'Januar 2026', stars: 5, text: 'Tražila neutralnu boju za dnevnu sobu koja ide uz sve. Hladna siva je odgovor – kombiniram je s bijelim, crnim i drvenim detaljima i svaki put izgleda elegantno. Muž je bio skeptičan ali sada kaže da je to bila nabolja promjena u renovaciji. Kupujem više za sobu djece.' }
    ]},
    73: { total: 8, fiveS: 7, fourS: 1, comments: [
      { name: 'Marija V.', city: 'Bar', date: 'Mart 2026', stars: 5, text: 'Nisam znala razliku između glatke i teksturisane verzije dok nisam naručila uzorak. Pa kad sam vidjela – odmah naručila teksturisanu. Reljef je mnogo naglašeniji i sjenke su dublje. Postavljeno iza kreveta i sad ta soba izgleda kao iz časopisa. Sestra je naručila iste.' },
      { name: 'Stefan Đ.', city: 'Cetinje', date: 'Februar 2026', stars: 4, text: 'Teksturisana verzija je za one koji žele pun 3D efekat. Vidio kod komšije glatku pa mu rekao da uzme teksturisanu – sada mi zahvaljuje. Sjene pod usmjerenim lampama su gotovo trodimenzionalne. Jedino što bih preporučio je da naručite uzorak da vidite uživo jer slike ne govore dovoljno.' }
    ]},
    74: { total: 11, fiveS: 10, fourS: 1, comments: [
      { name: 'Predrag N.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Espresso tamna letvica iza kauča je bila hrabar potez – žena me odgovarala. Sada mi zahvaljuje svaki dan. Pod usmjerenim lampama senke između letvica su nevjerovatno duboke i cio zid izgleda živo. Svaki gost ko dođe pita šta je to. Naručujem još za trpezariju.' },
      { name: 'Tatjana K.', city: 'Kotor', date: 'Januar 2026', stars: 5, text: 'Kombinacija tamnog expresso sa zlatnim detaljima i lampama je nešto ekskluzivno. Vidjela u jednom restoranu tu kombinaciju i dva dana tražila materijal. Kad sam pronašla Make My Home odmah naručila. Trpezarija sad izgleda kao fine dining restoran. Nijedna drugarica ne vjeruje da je to panel a ne pravo drvo.' }
    ]},
    75: { total: 13, fiveS: 12, fourS: 1, comments: [
      { name: 'Dejan M.', city: 'Budva', date: 'Mart 2026', stars: 5, text: 'Hrast u 3D formatu je nešto posebno. Prijatelj je imao bambus panel pa sam tražio nešto slično ali s letvicama. Ovo je savršen izbor. Topla srednja nijansa hrasta se odlično uklapa u dnevnu sobu. Svako ko sjedne na kauč počne gledati taj zid i pitati šta je to. Naručujem još.' },
      { name: 'Gordana R.', city: 'Herceg Novi', date: 'Februar 2026', stars: 5, text: 'Renovirala sam dnevnu sobu i tražila toplo ali moderno. Srednji hrast u 3D je bila tačna kombinacija. Djeca su kad vidjela rekla "mama je ovo pravo drvo?" haha. Nije pravo drvo ali izgleda kao da jeste. Postavljala sama, bez majstora, za jedan dan. Sve drugarice pitaju odakle.' }
    ]},
    76: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Miloš J.', city: 'Nikšić', date: 'Februar 2026', stars: 5, text: 'Imao sam manju spavaću sobu i bojao se da će širi profil letvica izgledati masivno. Uzeo uži 140mm i bila to prava odluka – gušći ritam daje zidu teksturu koja je delikatna i elegantna. Sobaizgleda veća i bogatija. Žena kaže da je to bila nabolja promjena od renovacije.' },
      { name: 'Sandra L.', city: 'Podgorica', date: 'Januar 2026', stars: 5, text: 'Uži profil sam odabrala za dječiju sobu jer je djevojčici soba mala. Hrast letvice daju neku prirodnost i toplinu savršenu za djecu. Svaki put kad dođe kod kuće trči u svoju sobu i kaže "moja soba je najljepša". Naručujem isto i za svoju spavaću.' }
    ]},
    77: { total: 10, fiveS: 9, fourS: 1, comments: [
      { name: 'Jovan P.', city: 'Bar', date: 'Mart 2026', stars: 5, text: 'Mahagonija je bila moj san za kućnu biblioteku. Smeđe-crvena nijansa s 3D sjenama daje prostoru onaj englesko-elegantni karakter koji sam uvijek tražio. Drugar koji je bio skeptičan je sad jedini koji mi govori da mu treba isto haha. Polovi police sa knjigama uz mahagonija letvice – savršeno.' },
      { name: 'Nevena T.', city: 'Tivat', date: 'Februar 2026', stars: 5, text: 'Vidjela u jednom luksuznom restoranu toplu mahagonija boju na zidovima. Pitala konobarica, uputila me ovdje. Naručila za trpezariju kući i efekat je isti onaj restoranski luksuz. Svaki put kad imamo goste na večeri, komentarišu zid više nego hranu haha.' }
    ]},
    78: { total: 11, fiveS: 10, fourS: 1, comments: [
      { name: 'Lazar B.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Tražio nešto svijetlo za malu sobu jer ne dobija puno prirodnog svjetla. Javor je bio savršen – svjetla nijansa vizualno povećava prostor a 3D tekstura daje dubinu bez tamnih boja. Mama kad je vidjela odmah htjela isto za svoju sobu. Preporučio joj i naručila je.' },
      { name: 'Ivana M.', city: 'Cetinje', date: 'Januar 2026', stars: 5, text: 'Htjela skandinavski izgled u stanu. Bijela posteljina, bijeli namještaj, javor letvice na zidu – totalni hit. Prijateljice koje su na Pinterestu uvijek gledale takve enterijer slike sada dolaze kod mene i kažu "ti imaš taj Pinterest stan". Presretna. Naručujem još za hodnik.' }
    ]},
    79: { total: 12, fiveS: 11, fourS: 1, comments: [
      { name: 'Nikola F.', city: 'Budva', date: 'Mart 2026', stars: 5, text: 'Crna ebonija iza kreveta je bila moja ideja, žena me odgovarala tjednima. Sada mi svako jutro kaže da je bila u krivu. Pod lampama sjene između letvica su duboke i dramatične – spavaća soba djeluje kao luksuzni hotel. Svaki gost ko uđe u tu sobu ostane bez teksta.' },
      { name: 'Milena K.', city: 'Kotor', date: 'Februar 2026', stars: 5, text: 'Vidjela na TikToku trend crnih letvica i bila sam fascilnirana. Pronašla Make My Home, naručila za kućni bioskop. Zid sad upija pažnju i ni jedna refleksija nema. Brat je posjetio i odmah naručio iste za svoju sobu. Definitivno kupujem još za hodnik.' }
    ]},
    80: { total: 10, fiveS: 9, fourS: 1, comments: [
      { name: 'Aleksandar V.', city: 'Podgorica', date: 'Februar 2026', stars: 5, text: 'Antracit je ta nijansa između crne i sive koja ide uz sve i ne zastarijeva. Postavio iza sofe u dnevnoj sobi i sad ta soba ima karakter. Bijeli namještaj i zlatne lampe uz antracit letvice – svaki dizajner kaže da je to savršena kombinacija. Drugar koji je vidio naručio odmah za svoj stan.' },
      { name: 'Jelena Đ.', city: 'Nikšić', date: 'Januar 2026', stars: 5, text: 'Vidjela sliku na nekom dizajnerskom blogu i tražila materijal. Pronašla ovde. Antracit letvice uz bijeli namještaj i zlatne detalje je kombinacija koja ne može da pogreši. Muž je bio suzdržan – sada svaki dan komentariše koliko je dnevna soba posebna. Kupujem još.' }
    ]},
    81: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Bojan N.', city: 'Herceg Novi', date: 'Mart 2026', stars: 5, text: 'Tamni grafit s metalnim podtonom je jedinstven – nije antracit ni crna nego nešto između što blago blišti pod lampama. Vidio u jednom modernom baru i pitao vlasniku. Naručio za dnevnu sobu. Žena je odmah rekla "to je to što sam htjela godinama". Presretni smo oboje.' },
      { name: 'Sanja M.', city: 'Bar', date: 'Februar 2026', stars: 4, text: 'Metalni podton grafita se mijenja pod različitim kutom svjetla – što mi se posebno sviđa. Soba je uvijek nova i zanimljiva zavisno od osvjetljenja. Kuma koja je bila u posjeti sjela je i satima gledala zid haha. Preporučujem svima koji žele nešto unikatno.' }
    ]},
    82: { total: 11, fiveS: 10, fourS: 1, comments: [
      { name: 'Dragan J.', city: 'Tivat', date: 'Mart 2026', stars: 5, text: 'Postavio tamni škriljevac u kupatilu i gosti misle da sam postavio pravi kamen. Hladna kamena tekstura uz crne armature izgleda savršeno skupo. Majstor koji je vidio nije mogao vjerovati da je PVC panel. Naručio je kontakt za sebe i kaže da preporučuje klijentima. Jako zadovoljan.' },
      { name: 'Katarina S.', city: 'Podgorica', date: 'Januar 2026', stars: 5, text: 'Tražila kameni izgled za hodnik bez težine pravog kamena. Škriljevac letvice su bile savršeno rješenje – montaža laka, PVC je lagan, rezultat premijum. Svako ko uđe u hodnik pipa zid prstom da provjeri je li pravi kamen. Naručujem još za kupatilo.' }
    ]},
    83: { total: 14, fiveS: 13, fourS: 1, comments: [
      { name: 'Marko B.', city: 'Budva', date: 'Mart 2026', stars: 5, text: 'Topli hrast letvice su moj favorit od sve kolekcije. Topla smeđa koja ni ne pretjeruje ni nije blijeda – savršen prirodni ton. Postavio u dnevnoj sobi i sad svi prijatelji kad dođu kažu "ma lijepo je kod tebe". Renoviramo i drugu prostoriju s istim panelom jer ne mogu smisliti ništa bolje.' },
      { name: 'Vesna R.', city: 'Bar', date: 'Februar 2026', stars: 5, text: 'Kombinovala topli hrast sa kožnom sofom i biljkama – soba izgleda kao iz Airbnb fotografije u Toskani. Drugarice su bile na kafi i sve su odmah slikale i pitate odakle. Dvije su naručile iste. Jedino što mi je žao je što nisam ranije naručila – satima sam pretraživala internet.' }
    ]},
    84: { total: 10, fiveS: 9, fourS: 1, comments: [
      { name: 'Zoran P.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Siva mineralna letvica je nešto između kamena i betona u 3D formatu – neobično i upečatljivo. Vidio na nekom YouTube videu o enterijer dizajnu i tražio materijal. Pronašao ovde, naručio. Sad svako ko posjeti pitaju odakle taj zid. To je pravi razgovor starter.' },
      { name: 'Ana B.', city: 'Nikšić', date: 'Februar 2026', stars: 5, text: 'Htjela nešto više od klasičnih drvenih letvica. Mineralni 3D profil je bio odgovor. Efekat pod osvjetljenjem je potpuno drugačiji od standarda – izgleda skuplje i posebnije. Mama je pitala da li sam uzela nekog arhitektu da odabere materijal. Naručujem još za spavaću.' }
    ]},
    85: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Miloš T.', city: 'Kotor', date: 'Februar 2026', stars: 5, text: 'Bijela struktura 3D05 je skulptura na zidu – to je jedina prava definicija. Reljef je dubok i pod usmjerenim lampama sjene su kao na nekom arhitektonskom modelu. Vidjela kod nekog na Pinterestu, tražila dugo dok nisam pronašla. Postavljeno u dnevnoj sobi i to je postala fokalna tačka čitavog enterijera.' },
      { name: 'Dragana V.', city: 'Budva', date: 'Januar 2026', stars: 5, text: 'Izrazitiji reljef je za one koji ne žele nešto prosječno. Bio je hrabar potez i isplatilo se sto posto. Muž je bio skeptičan al kad je vidio gotov zid rekao je samo "wow". Slike zaista ne mogu prenijeti koliko lijepo izgleda uživo – mora se vidjeti lično.' }
    ]},
    86: { total: 8, fiveS: 7, fourS: 1, comments: [
      { name: 'Nemanja B.', city: 'Herceg Novi', date: 'Mart 2026', stars: 5, text: 'Siva struktura je industrijska i moćna – isti profil kao bijela verzija ali siva nijansa daje toj dubini još više karaktera. Postavio u novom baru koji smo otvarali i od prvog dana gosti fotografišu zidove. Vlasnik kaže da su mu se povećale recenzije od renovacije. Naručujemo još.' },
      { name: 'Tijana S.', city: 'Tivat', date: 'Februar 2026', stars: 4, text: 'Tražila ozbiljnu i modernu varijantu za poslovni prostor. Siva struktura je bila tačno to – klijenti koji dolaze odmah primijete i komentarišu. Jedna klijentica pitala odakle materijal pa sam joj preporučila. Postavljano samo za dan, bez majstora. Oduševljena.' }
    ]},
    87: { total: 12, fiveS: 12, fourS: 0, comments: [
      { name: 'Luka Đ.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Premium crna letvica je vrh kolekcije i to se osjeti – najdublji 3D reljef i crna boja koja apsorbira sve. Postavio u kućnom bioskopu i efekt je neverovatan. Brat koji je bio skeptičan pitao me je li uzeo arhitekta da projektuje. Rekao sam da sam sam postavljao panel i nije mogao vjerovati. Preporučujem svima koji žele maksimalni efekt.' },
      { name: 'Kristina N.', city: 'Budva', date: 'Mart 2026', stars: 5, text: 'Vidjela u jednom dizajnerskom studiju i odmah znala da to hoću. Pitala vlasnika, uputio me ovdje. Naručila za spavaću sobu – uz bijelu posteljinu i zlatne lampe efekt je nevjerovatno luksuzni. Muž je rekao da je to nabolja odluka u čitavoj renovaciji. Svaki gost ostaje bez teksta.' }
    ]},

    // ── Akustični paneli ──
    88: { total: 11, fiveS: 10, fourS: 1, comments: [
      { name: 'Miloš V.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Imao sam otvoren plan kuhinja-dnevna soba i eho je bio problem – svaki razgovor za stolom se čuo po čitavom stanu. Postavio akustični panel i razlika je nevjerovatna. Žena koja se žalila na zvuk sad kaže da ne može vjerovati koliko je tišije. A panel izgleda savršeno uz ostale drvene detalje.' },
      { name: 'Sandra K.', city: 'Budva', date: 'Februar 2026', stars: 5, text: 'Radim od kuće i imala sam problema s ehom u video pozivima – soba je bila bučna. Naručila akustični panel, montirala sama iza radnog stola. Od tada kolege na pozivima komentarišu koliko dobar zvuk imam. Plus zid izgleda lijepo – drvo u pozadini na pozivima izgleda profesionalno.' }
    ]},
    89: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Danilo R.', city: 'Bar', date: 'Mart 2026', stars: 5, text: 'Napravio sam kućni bioskop i slika je bila savršena ali zvuk je odjekivao. Postavio akustični panel na tri zida i sad je zvuk kao u pravom bioskopu. Ujak koji je u posjeti rekao da je to bolje od kina u gradu. Panel uz to izgleda kao pravi enterijer – drvo i materijal su vrhunski.' },
      { name: 'Maja B.', city: 'Kotor', date: 'Januar 2026', stars: 4, text: 'Imala baby sobu za bebu i htjela smanjiti zvuk jer je muž radio od kuće. Akustični panel na jednom zidu je bio rješenje. Soba je tišija, baby spava bolje, a panel izgleda lijepo – nije sterilna dječija soba više. Preporučila sam troje mamicama u grupi.' }
    ]},
    90: { total: 10, fiveS: 9, fourS: 1, comments: [
      { name: 'Stefan N.', city: 'Nikšić', date: 'Februar 2026', stars: 5, text: 'Imali smo konferencijsku salu gdje su sastanci bili glasni i odjekivali. Postavljeno po jednom zidu akustični paneli i promjena je dramatična – razgovor je čišći, manje napora za slušanje. Zaposleni su primijetili razliku odmah. Šef je pitao šta smo promijenili, a kad je saznao naručio za svoju kancelariju.' },
      { name: 'Tijana M.', city: 'Herceg Novi', date: 'Januar 2026', stars: 5, text: 'Otvorili smo kafić i muzika je odjekivala previše. Akustični paneli su rješili problem i sad gosti mogu razgovarati udobno čak i kad svira muzika. Uz to, paneli su dio dizajna – drvena tekstura uz industrijske lampe izgleda savršeno. Dobili smo komplimente na enterijer od prvog dana.' }
    ]},
    91: { total: 8, fiveS: 7, fourS: 1, comments: [
      { name: 'Nikola K.', city: 'Tivat', date: 'Mart 2026', stars: 5, text: 'Pravim muziku kod kuće i studije u stanu su uvijek imale problem sa odjekom. Ovaj premium akustični panel je rješio problem koji nisam znao kako da riješim. NRC koeficijent je viši i to se čuje – snimci su čišći, nema neželjenih refleksija. Plus zid izgleda savršeno na YouTube videjima u pozadini.' },
      { name: 'Jelena S.', city: 'Podgorica', date: 'Februar 2026', stars: 5, text: 'Učiteljica muzike sam i radim privatne lekcije kod kuće. Soba je imala loš akustiku. Premium panel je bio rješenje – i akustički i estetski. Učenici su primijetili da je zvuk čišći. Jedan roditelj je pitao odakle materijal pa sam preporučila. Definitivno vrijedna investicija.' }
    ]},
    92: { total: 7, fiveS: 6, fourS: 1, comments: [
      { name: 'Predrag V.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Postavio u poslovni prostor gdje su se odvijali klijentski razgovori – eho je bio problem i klijenti su se žalili. Tamni ton premium akustičnog panela uz kancelarijski namještaj izgleda luksuzno i ozbiljno. Klijenti su primijetili i poboljšanje zvuka i poboljšanje izgleda prostora. Dvostrana korist.' },
      { name: 'Ana Đ.', city: 'Budva', date: 'Januar 2026', stars: 5, text: 'Muž je napravio home theatre i tražio pravi akustični tretman ali i da izgleda lijepo. Tamni ton panela uz crni TV i tepihe je savršena kombinacija. Susjed koji se bavi audio tehniologijom rekao da je tretman dobar za privatni prostor. A estetika – nema šta reći, izgleda skupo.' }
    ]},

    // Aluminijum lajsne
    96: { total: 7, fiveS: 6, fourS: 1, comments: [
      { name: 'Miloš K.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Crna srednja lajsna između panela je detalj koji sve mijenja. Bez nje paneli izgledaju nedovršeno – sa njom zid dobija profesionalnu završnicu kakvu viđaš samo kod arhitekata.' },
      { name: 'Ana R.', city: 'Kotor', date: 'Februar 2026', stars: 5, text: 'Linija između panela odvaja i definiše svaki dio zida. Prostor bez nje nikada ne bi izgledao ovako čisto i dorađeno. Absolutno neophodna.' }
    ]},
    95: { total: 8, fiveS: 7, fourS: 1, comments: [
      { name: 'Stefan B.', city: 'Budva', date: 'Mart 2026', stars: 5, text: 'Početna lajsna daje zidu savršen ulaz – bez nje ivica panela je sirova i nedovršena. Sa njom sve izgleda kao da je rađeno po mjeri. Enterijer je znatno uljepšan.' },
      { name: 'Jelena M.', city: 'Tivat', date: 'Januar 2026', stars: 5, text: 'Postavljam je i na početak i na kraj obloge. Crna boja idealno prati tamne panele koje sam odabrala. Bez nje prostor zaista nije isti.' }
    ]},
    97: { total: 6, fiveS: 5, fourS: 1, comments: [
      { name: 'Nikola V.', city: 'Herceg Novi', date: 'Februar 2026', stars: 5, text: 'Ugaona lajsna rješava problem spoljnog ugla savršeno. Crna linija na uglu spaja oba zida u cjelinu – efekat je dramatičan i moderan. Prostor bez nje bio bi daleko siromašniji.' },
      { name: 'Dragana P.', city: 'Bar', date: 'Mart 2026', stars: 4, text: 'Ugao između dva oblozena zida je uvijek bio problem dok nisam otkrila ovu lajsnu. Čista crna linija koja razdvaja uglove i daje im arhitektonski karakter.' }
    ]},
    98: { total: 7, fiveS: 6, fourS: 1, comments: [
      { name: 'Lazar T.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'LED lajsna između panela je transformisala čitav zid. Svjetlost se pojavljuje kao tanka sjajna linija i prostor dobija ambijent koji ne možeš dobiti drugačije. Nevjerovatno uljepšava.' },
      { name: 'Marina Đ.', city: 'Nikšić', date: 'Februar 2026', stars: 5, text: 'Ugradila LED traku u crni kanal i rezultat je spektakularan. Zid živi noću – suptilna svjetlost između panela pravi dubinu i toplinu koju niko nije očekivao.' }
    ]},
    99: { total: 8, fiveS: 7, fourS: 1, comments: [
      { name: 'Vesna K.', city: 'Kotor', date: 'Mart 2026', stars: 5, text: 'Bronzana srednja lajsna između panela dodaje toplinu i luksuz koji crna ne može dati. Svaka linija na zidu govori o pažnji prema detaljima. Prostor bez nje nije isti.' },
      { name: 'Goran S.', city: 'Budva', date: 'Januar 2026', stars: 5, text: 'Bronzana boja savršeno prati tople tonove drvenih panela. Linija koja odvaja svaki panel daje zidu ritmičnost i dorađenost. Znatno uljepšava enterijer.' }
    ]},
    100: { total: 7, fiveS: 6, fourS: 1, comments: [
      { name: 'Ivana R.', city: 'Tivat', date: 'Februar 2026', stars: 5, text: 'Bronzana završna lajsna je luksuzni detalj koji zatvara oblogu. Bez nje ivica bi bila sirova – sa njom sve izgleda kao premium enterijer iz kataloga.' },
      { name: 'Bojan N.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Koristim je i za početak i za kraj. Topla bronzana nijansa uz bež panele je kombinacija koja diže enterijer na drugi nivo. Neophodna završnica.' }
    ]},
    101: { total: 6, fiveS: 5, fourS: 1, comments: [
      { name: 'Predrag L.', city: 'Bar', date: 'Mart 2026', stars: 5, text: 'Bronzana ugaona lajsna na spoljnom uglu je detalj koji oduševljava. Topla bronzana linija spaja dva zida elegantno – efekat je sofisticiran i znatno uljepšava prostor.' },
      { name: 'Tijana B.', city: 'Herceg Novi', date: 'Februar 2026', stars: 5, text: 'Ugao sa bronzanom lajsnom izgleda kao da je dizajner radio na projektu. Bez nje to bi bio samo sirov spoj dva zida – sa njom je arhitektonski detalj.' }
    ]},
    102: { total: 7, fiveS: 7, fourS: 0, comments: [
      { name: 'Aleksandar M.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Bronzana LED lajsna je vrhunac enterijera. Topla bronzana sa toplim LED svjetlom stvara ambijentalnost kakvu nisi mogao ni zamisliti. Zid živi i bez ijednog spotlighta.' },
      { name: 'Sandra V.', city: 'Kotor', date: 'Januar 2026', stars: 5, text: 'Kombinacija bronzanog kanala i tople bijele LED trake između panela je magična. Svaki večer kada se upali svjetlo, soba se pretvori u nešto posebno. Znatno uljepšava prostor.' }
    ]},

    // SPC04 – SPC Laminat 122×18cm
    103: { total: 12, fiveS: 11, fourS: 1, comments: [
      { name: 'Marko D.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Iskreno, spasio me ovaj pod. Imao sam klasični laminat u kuhinji i jednom mi je dijete prosulo čašu vode – za dva dana je nabreknuo i morao sam sve da mijenjam. Od kad sam postavio ovaj SPC, ni briga. Voda, sok, sve – obrišeš i gotovo. Ne nabrekne, ne mijenja oblik, ništa. Trebalo mi je ovo godinama ranije.' },
      { name: 'Jelena K.', city: 'Budva', date: 'Februar 2026', stars: 5, text: 'Nisam vjerovala dok nisam sama probala – postavljam ga sama, bez majstora, bez ljepila. Klikneš jedan u drugi i to je to. Za vikend sam završila cijelu dnevnu sobu. Pod izgleda kao pravo drvo, topao je na dodir i ne škripi ni korak. Preporučila sam već troje komšija.' }
    ]},

    // SPC05 – SPC Laminat 122×18cm
    104: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Stefan R.', city: 'Nikšić', date: 'Mart 2026', stars: 5, text: 'Živimo u stanu s podnim grijanjem i svaki pod koji smo probali je ili nabubreao ili se iskrivio od topline. Od kad smo stavili ovaj SPC – nema ništa. Pod ostaje ravan, grije se ravnomjerno, a hod je tih i ugodan. Komšije ispod nas su primijetile da ih više ne čujemo. To nešto govori.' },
      { name: 'Ana M.', city: 'Herceg Novi', date: 'Februar 2026', stars: 4, text: 'Imam troje djece i psa. Svaki prethodni pod je bio katastrofa za godinu-dvije. Ovaj SPC stoji već 14 mjeseci i nema ni jedne ogrebotine vidljive. Pas trči, djeca bacaju igračke, prosipaju – krpom obrišeš i kao nov. Jedino što mi malo smeta je što nisam ranije znala za SPC podove.' }
    ]},

    // SPC07 – SPC Laminat Tile Format 61.5×31cm
    105: { total: 10, fiveS: 9, fourS: 1, comments: [
      { name: 'Maja T.', city: 'Kotor', date: 'Mart 2026', stars: 5, text: 'Imala sam keramiku u kupatilu – hladna, klizava, a između fuga se skupljala prljavština pa sam ribala svake sedmice. Promijenila na ovaj SPC tile format i razlika je nebo i zemlja. Topao na bosim nogama, ne kliza, fuga nema, čisti se za minut. Nisam mogla ni zamisliti da postoji nešto ovako.' },
      { name: 'Dragan P.', city: 'Bar', date: 'Januar 2026', stars: 5, text: 'Renovirao sam cijeli stan i za hodnik i kuhinju sam uzeo ovaj tile SPC. Majstor mi je rekao da ga nikad nije postavljao ali da mu je najlakši pod u karijeri – klikne, gotovo. Izgleda skupo, osjećaj je mekan i tih, a cijena je bila pola od keramike kad uradiš račun sa postavljanjem. Definitivno ponovo.' }
    ]},

    // SPC06 – SPC Laminat 122×18cm
    115: { total: 11, fiveS: 10, fourS: 1, comments: [
      { name: 'Katarina M.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Imala sam klasični laminat u spavaćoj sobi i skinuo se od vlage u jednom uglu. Prešla na SPC06 i nema nikakvog straha. Postavila sama uz YouTube video – click-lock sistem je toliko intuitivan da nije trebao majstor. Mama je vidjela i odmah naručila za sebe. Izgleda kao pravo drvo i topao je na bosim nogama.' },
      { name: 'Aleksandar T.', city: 'Bar', date: 'Februar 2026', stars: 5, text: 'Renovirali smo cijeli stan i tražili pod koji može svugdje – dnevna, kuhinja, kupatilo, sve iste daske. SPC06 je bio jedini odgovor. Majstor je rekao da je ugradnja bila laka i brza. Sad imamo isti pod svuda i stan izgleda prostrano i ujednačeno. Komšija odmah pitao odakle daske.' }
    ]},

    // SPC08 – SPC Laminat (Tile Format)
    114: { total: 10, fiveS: 9, fourS: 1, comments: [
      { name: 'Nataša J.', city: 'Kotor', date: 'Mart 2026', stars: 5, text: 'Imala sam keramiku u kuhinji koja se stalno prljala po fugama i ribala sam je svake sedmice. Odlučila preći na SPC tile format i razlika je dramatična – nema fuga, nema prljanja, briše se za minut. Topao je na dodir za razliku od keramike. Mama je vidjela i odmah htjela isto za svoje kupatilo.' },
      { name: 'Bojan V.', city: 'Budva', date: 'Januar 2026', stars: 5, text: 'Renovirali smo restoran i za kuhinjski dio i sanitarije uzeli SPC tile format. Majstor je rekao da je to najbrža ugradnja poda u karijeri – click-lock direktno na stari pod, bez rušenja. Izgleda kao beton ili kamen, gosti uvijek pitaju koji materijal je to. Naručujemo još za terasu.' }
    ]},

    // ── Kožni paneli ──
    106: { total: 10, fiveS: 9, fourS: 1, comments: [
      { name: 'Ivana Č.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Bordo crvena koža na zidu zvuči previše ali kad to vidiš uživo sve postane jasno. Vidjela u jednom retro restoranu, pitala konobarica, pronašla ovo. Naručila za hodnik i sada svako ko uđe stane i gleda. Definitivno kupujem još.' },
      { name: 'Ognjen M.', city: 'Tivat', date: 'Mart 2026', stars: 5, text: 'Postavio u kabinetу i klijenti odmah komentarišu koliko je prostor autentičan. Jedna klijentica pitala odakle panel – preporučio sam bez razmišljanja. Bordo s kožnom teksturom je nešto što se mora videti uživo.' }
    ]},
    107: { total: 7, fiveS: 6, fourS: 1, comments: [
      { name: 'Lena V.', city: 'Kotor', date: 'Mart 2026', stars: 5, text: 'Narandžasta na zidu je bila ideja moje žene i ja sam se protivio. Sada joj zahvaljujem svaki dan. Hermès narandžasta s kožnom teksturom je magnetska – svaki gost stane i pita. Bar koji smo otvorili je hit.' },
      { name: 'Dejan T.', city: 'Bar', date: 'Februar 2026', stars: 4, text: 'Vidio sam na nekom Instagram stories i tražio dugo. Kad sam pronašao naručio odmah za studio za fotografisanje. Gosti posebno traže taj zid za pozadinu. Isplatilo se višestruko, naručujem još.' }
    ]},
    108: { total: 7, fiveS: 6, fourS: 1, comments: [
      { name: 'Maja S.', city: 'Herceg Novi', date: 'Mart 2026', stars: 5, text: 'Tražila nešto elegantno za recepciju firme. Ledena Siva s kožnom teksturom bila savršena. Posjetioci uvijek pitaju koji materijal je na zidu. Jedna klijentica pitala kontakt – preporučila odmah.' },
      { name: 'Petar A.', city: 'Podgorica', date: 'Mart 2026', stars: 4, text: 'Vidio u jednom uredu kod poslovnog partnera i odmah pitao odakle. Postavio kod sebe i razlika je drastična. Hladna siva s kožom daje prostor premium izgled. Svaki gost koji dođe primijeti.' }
    ]},
    109: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Stefan L.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Tamni Antracit nije crna – tamnija siva koja se mijenja s kutom svjetla. Drugar koji je dizajner nije mogao vjerovati da je panel. Postavljeno u kućnom uredu i sada je to moja omiljena prostorija.' },
      { name: 'Milica R.', city: 'Budva', date: 'Februar 2026', stars: 5, text: 'Vidio kod prijatelja i odmah pitao odakle. Naručio za spavaću i kombinacija tamnog antracita s bijelom posteljinom je presavršena. Žena bila skeptična, sada kaže da je to njena omiljena soba. Kupujem još.' }
    ]},

    // ── Classic paneli ──
    110: { total: 11, fiveS: 10, fourS: 1, comments: [
      { name: 'Tamara J.', city: 'Budva', date: 'Mart 2026', stars: 5, text: 'Terrazzo panel sam vidjela na nekom Pinterest profilu i tražila dugo. Kad sam pronašla odmah naručila za kafić. Gosti ga fotografišu svaki dan i pitaju vlasnika. Od renovacije smo dobili dosta novih mušterija.' },
      { name: 'Kristina R.', city: 'Podgorica', date: 'Februar 2026', stars: 5, text: 'Za dječiju sobu sam tražila nešto veselo ali moderno. Terrazzo je tačno to. Djeca ga obožavaju. Četiri mamice su naručile po mome preporuci nakon što su vidjele kako izgleda. Presretna sa izborom.' }
    ]},
    111: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Miloš D.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Soft Beige je ta boja koja ide uz doslovno sve. Muž je bio skeptičan, sada mi kaže da je to bila nabolja odluka u renovaciji. Neutralna ali udobna bež nijansa – svi gosti primijete i komentarišu.' },
      { name: 'Jelena N.', city: 'Nikšić', date: 'Februar 2026', stars: 5, text: 'Renovirali boutique hotel i Soft Beige za sve sobe. Gosti pišu u recenzijama da se osjete kao kod kuće. Mama je vidila jednom u sobi i odmah naručila za svoj stan. Preporučujem svima.' }
    ]},
    112: { total: 12, fiveS: 11, fourS: 1, comments: [
      { name: 'Ana V.', city: 'Kotor', date: 'Mart 2026', stars: 5, text: 'Bijela ali nije obična bijela – fina tekstura daje dubinu. Vidio na nekom dizajnerskom blogu i tražio dugo. Kad pronašao odmah naručio. Postavljeno u kupaonici, sad je to najljepša prostorija u kući. Komšija koji je bio u posjeti naručio isti.' },
      { name: 'Bojan S.', city: 'Tivat', date: 'Januar 2026', stars: 5, text: 'Pure White za studio za jogu – klijentice kažu da soba djeluje čišće i mirnije nego ikad. Tražile me odakle zidna obloga i više puta preporučila. Jednostavno ne bi isto izgledalo s bijelom farbom.' }
    ]},
    113: { total: 13, fiveS: 12, fourS: 1, comments: [
      { name: 'Marko Đ.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Midnight Black je bio hrabar potez za spavaću sobu i nabolja odluka u životu. Mat crna uz bijelu posteljinu i zlatne lampe – svaki gost ostane bez daha. Prijatelji mi se smiju što sam to uradio ali odmah pitaju odakle haha.' },
      { name: 'Sonja K.', city: 'Budva', date: 'Februar 2026', stars: 5, text: 'Kućni bioskop bez mat crnog zida nije pravi bioskop. Postavio Midnight Black i nema ni jedne refleksije. Brat bio toliko oduševljen da odmah naručio za svoju sobu. Preporučujem svima koji žele nešto dramatično.' }
    ]},
    // ── PU Stone Mushroom ──
    118: { total: 14, fiveS: 12, fourS: 2, comments: [
      { name: 'Maja S.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Dugo sam tražila nešto što izgleda kao pravi bijeli kamen a da nije teško za montažu. Pronašla ovaj panel, naručila, postavila sama za sat i pol. Svaka komšinica koja dođe misli da sam gazila pravi kameni zid. Naručujem još za hodnik.' },
      { name: 'Luka P.', city: 'Budva', date: 'Februar 2026', stars: 5, text: 'Postavio u recepciji apartmana i odmah su gosti počeli da fotografišu. Na Booking.com su počeli da pominju "predivan kameni zid" u recenzijama. Vlasnik je bio skeptičan ali sada naručuje za sve apartmane.' }
    ]},
    119: { total: 11, fiveS: 10, fourS: 1, comments: [
      { name: 'Ivana R.', city: 'Bar', date: 'Mart 2026', stars: 5, text: 'Bež mushroom kamen sam stavila iza kreveta umjesto tapete i rezultat je magičan. Svako jutro se probudim i ne mogu vjerovati koliko je lijepo. Muž koji je bio protiv sad kaže da je to nabolja odluka u renovaciji.' },
      { name: 'Nikola J.', city: 'Tivat', date: 'Februar 2026', stars: 5, text: 'Za kafić koji smo renovirali tražili smo nešto autentično ali praktično. Bež mushroom panel je bio tačno to. Gosti ga dodiruju i pitaju da li je pravi kamen. Preporučio sam Make My Home svim vlasnicima lokala koje znam.' }
    ]},
    120: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Tamara M.', city: 'Kotor', date: 'Mart 2026', stars: 5, text: 'Braon mushroom kamen u dnevnoj sobi – kombinacija sa zlatnim dodacima je kao iz nekog skupog hotela. Gosti pitaju da li smo angažovali dizajnera. Montirala sama, suprug nije mogao vjerovati kad je vidio rezultat.' },
      { name: 'Dragan N.', city: 'Herceg Novi', date: 'Januar 2026', stars: 5, text: 'Obložio stubove na terasi braon mushroom kamenom. Preživio je zimu i ljetnju kiše bez ijedne promjene. Svi koji prđu pitaju odakle materijal. Dugotrajan i lijep – preporučujem za spoljne zidove.' }
    ]},
    121: { total: 16, fiveS: 14, fourS: 2, comments: [
      { name: 'Stefan V.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Crni mushroom panel za kućni bar je bio rizik koji se stostruko isplatio. Mat crna kamena tekstura uz pozadinsko osvjetljenje izgleda kao nešto iz Dubaija. Svaki gost odmah uzme telefon. Naručujem još za studio.' },
      { name: 'Milena B.', city: 'Nikšić', date: 'Februar 2026', stars: 5, text: 'Vidjela sam na Instagramu nečiji hodnik s crnim kamenom i tražila sedmicu gdje naći. Kad pronašla – naručila odmah. Hodnik je sad najdramatičniji dio stana, svi koji uđu odmah komentarišu. Nabolji utrošen novac ove godine.' }
    ]},
    // ── PU Stone Talas XL ──
    122: { total: 12, fiveS: 11, fourS: 1, comments: [
      { name: 'Ana Đ.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Bijeli Talas XL je monumentalan. Jedan panel pokriva toliko da smo za cio zid dnevne sobe trebali samo 7 komada. Montaža brza, rezultat kao skupa kamena obloga. Svakom posjetiocu prvo pitanje je o tom zidu.' },
      { name: 'Petar L.', city: 'Budva', date: 'Februar 2026', stars: 5, text: 'Hotel koji renoviramo dobio je lobby koji izgleda kao petzvjezdičaski. Bijeli talas na zidu iza recepcije – gosti se fotografišu ispred. Investicija koja se odmah vidi i osjeća.' }
    ]},
    123: { total: 10, fiveS: 9, fourS: 1, comments: [
      { name: 'Jelena K.', city: 'Bar', date: 'Mart 2026', stars: 5, text: 'Bež Talas XL u trpezariji je pretvorio običan ručak u gastronomski doživljaj. Svi koji sjednu za sto automatski pogledaju zid. Muž koji je bio protiv sad poziva prijatelje da vide "naš kameni zid".' },
      { name: 'Miloš T.', city: 'Kotor', date: 'Januar 2026', stars: 5, text: 'Za restoran koji vodim bež talas panel je bio idealan – toplo, prirodno, upečatljivo. Od renovacije gosti češće naručuju fotografije hrane uz ovaj zid u pozadini. Instagram nas je popunio rezervacijama.' }
    ]},
    124: { total: 8, fiveS: 7, fourS: 1, comments: [
      { name: 'Bojan M.', city: 'Tivat', date: 'Mart 2026', stars: 5, text: 'Khaki Talas je ta boja kamena koju nisi vidio svuda – niti siva niti smeđa, nešto između što odgovara svemu. Dnevna soba sada izgleda kao da je projektovana. Prijatelj arhitekta pitao me koji materijal koristim.' },
      { name: 'Sandra P.', city: 'Podgorica', date: 'Februar 2026', stars: 5, text: 'Tražila sam nešto organsko za enterijer koji prati prirodu. Khaki Talas je idealan – uklapa se uz drvo, bambus i zelene biljke. Gosti uvijek komentarišu koliko je soba mirna i prirodna.' }
    ]},
    125: { total: 11, fiveS: 10, fourS: 1, comments: [
      { name: 'Igor S.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Sivi Talas XL u kućnom uredu – svaki video poziv izgleda profesionalno. Kolege pitaju da li sam uzeo novi ured. Hladan sivi kamen uz bijeli stol i crne detalje je savršena kombinacija za moderan radni prostor.' },
      { name: 'Vesna Đ.', city: 'Cetinje', date: 'Februar 2026', stars: 5, text: 'Renovirali smo spa centar sivim Talasom. Klijentice kažu da su zidovi koji "dišu hladnoćom" dio iskustva relaksacije. Recenzije su nam porasle od renovacije. Naručujem još za novu prostoriju.' }
    ]},
    // ── MDF Paneli ──
    126: { total: 14, fiveS: 12, fourS: 2, comments: [
      { name: 'Nikola R.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Iskreno nisam očekivao ovako dobar rezultat. Uzeo sam bijeli panel za zid iza TV-a, sam sam ga montirao vikendom i ispalo je odlično. Žena me pitala jesam li uzeo majstora – rekao sam da sam ga sam uradio i nije mi povjerovala haha. Medijapan se odlično sječe, ništa nije pucalo.' },
      { name: 'Tijana B.', city: 'Bar', date: 'Februar 2026', stars: 5, text: 'Stavila sam ga iza kreveta umjesto tapete i razlika je nevjerovatna. Bijela boja i oni žljebovi bacaju lijepe sjene kad upalim lampu sa strane. Soba izgleda dvostruko veća. Jedino što bih napomenula – izmjerite dobro prije nego narežete, ali to je normalno za svaki panel.' }
    ]},
    127: { total: 12, fiveS: 10, fourS: 2, comments: [
      { name: 'Marija K.', city: 'Budva', date: 'Mart 2026', stars: 5, text: 'Imala sam parket i drveni namještaj u dnevnoj sobi i tražila nešto što će se uklopiti, a ne izgledati jeftino. Topli kaneliran panel je bio tačno to. Sad svi koji dođu pitaju odakle ga nabavila. Ugradnja je super jednostavna, medijapan se lako obrađuje.' },
      { name: 'Dejan M.', city: 'Nikšić', date: 'Januar 2026', stars: 5, text: 'Uzeo sam za hodnik u stanu. Hodnik je uzan i taman i nikako nisam znao šta da radim s njim. Ovaj topli panel je bio pun pogodak – sad taj hodnik izgleda kao da je namjenski dizajniran. Paneli se lako sijeku i buše, sve sam sam uradio za nekoliko sati.' }
    ]},
    128: { total: 10, fiveS: 9, fourS: 1, comments: [
      { name: 'Stefan J.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Nisam tip za svijetle boje, htio sam nešto tamno i upečatljivo za spavaću sobu. Ovaj tamni panel je savršen. Uz bočnu lampu sjene u kanelurama izgledaju kao da je zid rađen po mjeri u nekom skupom hotelu. Materijal je dobar, reže se čisto.' },
      { name: 'Aleksandra P.', city: 'Kotor', date: 'Februar 2026', stars: 5, text: 'Otvaramo kafić i htjela sam tamnu atmosferu bez previše para. Tamni medijapan panel je riješio stvar. Ekipa koja je montirala kaže da je materijal lak za rad i da se fino reže i buši. Rezultat je fenomenalan, gosti već pitaju za detalje i još nismo ni otvorili.' }
    ]},
    140: { total: 11, fiveS: 10, fourS: 1, comments: [
      { name: 'Bojan T.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Uzeo sam deblji model za recepciju firme i odmah se vidi razlika u volumenu i dubini žljebova. Kad dodirnete zid osjetite da je to nešto solidno, nije tanko. Klijenti to primjete čim uđu. Za poslovne prostore bih uvijek preporučio deblji model.' },
      { name: 'Ivana S.', city: 'Tivat', date: 'Februar 2026', stars: 5, text: 'Iznajmljujem apartman i htjela sam nešto što će izgledati skupo a neće me koštati bogatstvo. Deblji medijapan panel je bio pravo rješenje. Već tri grupe gostiju su ga pohvalile u recenzijama na bookingu. Montaža je brza jer se materijal lako sječe i buši.' }
    ]},
    141: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Miroslav D.', city: 'Herceg Novi', date: 'Mart 2026', stars: 5, text: 'Trebao sam pokriti veći zid i odabrao tanji model zbog cijene i lakšeg rada. Isplatilo se. Materijal se super sječe, ništa se ne lomi, i montaža ide brzo. Kad završiš ne vidiš razliku od debljeg modela jer žljebovi izgledaju isto.' },
      { name: 'Katarina N.', city: 'Podgorica', date: 'Februar 2026', stars: 5, text: 'Radila sam renovaciju u dva stana i za oba sam uzela tanji panel jer sam trebala više komada. Medijapan je zahvalan materijal – sječeš ga običnom pilom, bušiš lako, prilagođava se svakom uglu. Izgled je odličan i susjedi su pitali odakle materijal.' }
    ]}
  };
  const rv = reviewsData[id] || { total: 8, fiveS: 7, fourS: 1, comments: [
    { name: 'Marko T.', city: 'Podgorica', date: 'Februar 2026', stars: 5, text: 'Odličan panel, montaža jednostavna, izgleda skupo. Preporučujem!' },
    { name: 'Ana K.', city: 'Bar', date: 'Januar 2026', stars: 5, text: 'Jako sam zadovoljna. Boja je tačna, kvalitet dobar. Svaka preporuka.' }
  ]};
  const productReviews = rv.comments;
  const totalRev = rv.total;
  const fiveStarsCount = rv.fiveS;
  const fourStarsCount = rv.fourS;
  const starFull = '<i class="fas fa-star rev-star-gold"></i>';
  const starEmpty = '<i class="fas fa-star rev-star-empty"></i>';
  const starIcons = n => Array.from({length: 5}, (_, i) => i < n ? starFull : starEmpty).join('');
  const pct = n => totalRev ? Math.round((n / totalRev) * 100) : 0;
  const reviewsHtml = `
    <div class="rv-wrap">
      <h3 class="rv-title">Ocjene korisnika</h3>
      <div class="rv-summary">
        <div class="rv-score-col">
          <div class="rv-big-num">4.8</div>
          <div class="rv-big-stars">${starIcons(5)}</div>
          <div class="rv-count">${totalRev} recenzija</div>
        </div>
        <div class="rv-bars-col">
          <div class="rv-bar-row"><span class="rv-bar-label">5</span><i class="fas fa-star rv-star-gold"></i><div class="rv-bar-track"><div class="rv-bar-fill" style="width:${pct(fiveStarsCount)}%"></div></div><span class="rv-bar-num">${fiveStarsCount}</span></div>
          <div class="rv-bar-row"><span class="rv-bar-label">4</span><i class="fas fa-star rv-star-gold"></i><div class="rv-bar-track"><div class="rv-bar-fill" style="width:${pct(fourStarsCount)}%"></div></div><span class="rv-bar-num">${fourStarsCount}</span></div>
          <div class="rv-bar-row"><span class="rv-bar-label">3</span><i class="fas fa-star rv-star-gold"></i><div class="rv-bar-track"><div class="rv-bar-fill rv-bar-fill--empty" style="width:0%"></div></div><span class="rv-bar-num">0</span></div>
          <div class="rv-bar-row"><span class="rv-bar-label">2</span><i class="fas fa-star rv-star-gold"></i><div class="rv-bar-track"><div class="rv-bar-fill rv-bar-fill--empty" style="width:0%"></div></div><span class="rv-bar-num">0</span></div>
          <div class="rv-bar-row"><span class="rv-bar-label">1</span><i class="fas fa-star rv-star-gold"></i><div class="rv-bar-track"><div class="rv-bar-fill rv-bar-fill--empty" style="width:0%"></div></div><span class="rv-bar-num">0</span></div>
        </div>
      </div>
      <div class="rv-list">
        ${productReviews.map(r => `
          <div class="rv-card">
            <div class="rv-card-top">
              <div class="rv-avatar">${r.name.charAt(0)}</div>
              <div class="rv-card-meta">
                <div class="rv-card-name">${r.name} <span class="rv-card-city">· ${r.city}</span></div>
                <div class="rv-card-stars">${starIcons(r.stars)}</div>
              </div>
              <span class="rv-card-date">${r.date}</span>
            </div>
            <p class="rv-card-text">${r.text}</p>
            <div class="rv-verified"><i class="fas fa-check-circle"></i> Verifikovana kupovina</div>
          </div>
        `).join('')}
      </div>
    </div>
  `;

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

  info.innerHTML = `
    <div class="product-category">${categoryName}</div>
    <h1 class="product-name">${product.name}</h1>
    ${product.sku ? `<div style="font-size:12px;color:#999;font-family:monospace;margin-top:-6px;margin-bottom:4px;">Šifra: ${product.sku}</div>` : ''}
    <div class="product-rating">
      <span class="rating-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i></span>
      <span class="rating-count">(4.8) · Odlično</span>
    </div>

    ${product.discount > 0
      ? `<div class="product-price-lg">
           <span style="text-decoration:line-through;color:#aaa;font-size:18px;font-weight:400;">${product.price} €</span>
           <span style="margin-left:8px;">${(product.price*(1-product.discount/100)).toFixed(2)} €</span>
           <span style="background:#e74c3c;color:#fff;border-radius:14px;padding:3px 12px;font-size:13px;font-weight:700;margin-left:8px;vertical-align:middle;">-${product.discount}% POPUST</span>
           <span style="color:#888;font-size:14px;"> / ${product.unit}</span>
         </div>`
      : `<div class="product-price-lg">${product.price} € <span>/ ${product.unit}</span></div>`
    }

    ${product.category === 'aluminijum-lajsne' ? `
    <!-- Količina za lajsne (bez kalkulatora) -->
    <div class="pq-panel" id="pq-qty" style="display:block;">
      <div class="pq-stepper">
        <button type="button" class="pq-step-btn" onclick="stepPqQty(-1)">−</button>
        <span class="pq-step-val" id="pq-qty-val">1</span>
        <button type="button" class="pq-step-btn" onclick="stepPqQty(1)">+</button>
      </div>
    </div>
    <div id="pq-calc" style="display:none;"><div class="pq-calc-result" id="calc-result"></div></div>
    ` : `
    <!-- Tab switcher -->
    <div class="pq-tabs">
      <button class="pq-tab active" onclick="switchPqTab('calc', this)">
        <i class="fas fa-calculator"></i> Kalkulator m²
      </button>
      <button class="pq-tab" onclick="switchPqTab('qty', this)">
        <i class="fas fa-list-ol"></i> Unesi Količinu
      </button>
    </div>

    <!-- Tab: Količina -->
    <div class="pq-panel" id="pq-qty" style="display:none;">
      <div class="pq-stepper">
        <button type="button" class="pq-step-btn" onclick="stepPqQty(-1)">−</button>
        <span class="pq-step-val" id="pq-qty-val">1</span>
        <button type="button" class="pq-step-btn" onclick="stepPqQty(1)">+</button>
      </div>
      ${product.category !== 'spc-pod' ? `<div class="pq-m2-badge" id="pq-m2-badge">
        1 ${product.unit === 'm²' ? 'm²' : 'kom'} = ${coveragePerUnit.toFixed(2).replace('.', ',')} m²
      </div>` : ''}
    </div>

    <!-- Tab: Kalkulator -->
    <div class="pq-panel" id="pq-calc">
      ${letvicaDims ? `
      <div style="background:rgba(201,168,108,0.12);border:1px solid rgba(201,168,108,0.35);border-radius:8px;padding:8px 12px;margin-bottom:10px;font-size:13px;color:#c9a86c;display:flex;align-items:center;gap:8px;">
        <i class="fas fa-ruler-horizontal"></i>
        <span>Svaka letvica: <strong>280cm visina × ${letvicaDims.w}cm širina</strong> → 1 letvica = ${coveragePerUnit.toFixed(2).replace('.', ',')} m²</span>
      </div>` : ''}
      ${puDims ? `
      <div style="background:rgba(201,168,108,0.12);border:1px solid rgba(201,168,108,0.35);border-radius:8px;padding:8px 12px;margin-bottom:10px;font-size:13px;color:#c9a86c;display:flex;align-items:center;gap:8px;">
        <i class="fas fa-ruler-combined"></i>
        <span>Svaki panel: <strong>${puDims.w} × ${puDims.h} cm</strong> &nbsp;·&nbsp; 1 kom = ${coveragePerUnit.toFixed(2).replace('.', ',')} m² &nbsp;·&nbsp; Uključuje <strong>+5% rezerva</strong></span>
      </div>` : ''}
      ${mdfDims ? `
      <div style="background:rgba(201,168,108,0.12);border:1px solid rgba(201,168,108,0.35);border-radius:8px;padding:8px 12px;margin-bottom:10px;font-size:13px;color:#c9a86c;display:flex;align-items:center;gap:8px;">
        <i class="fas fa-ruler-combined"></i>
        <span>Svaki panel: <strong>${mdfDims.w} × ${mdfDims.h} cm</strong> &nbsp;·&nbsp; 1 kom = ${coveragePerUnit.toFixed(2).replace('.', ',')} m² &nbsp;·&nbsp; Uključuje <strong>+5% rezerva</strong></span>
      </div>` : ''}
      ${flexDims ? `
      <div style="background:rgba(201,168,108,0.12);border:1px solid rgba(201,168,108,0.35);border-radius:8px;padding:8px 12px;margin-bottom:10px;font-size:13px;color:#c9a86c;display:flex;align-items:center;gap:8px;">
        <i class="fas fa-ruler-combined"></i>
        <span>Svaki panel: <strong>${flexDims.w} × ${flexDims.h} cm</strong> &nbsp;·&nbsp; 1 kom = ${coveragePerUnit.toFixed(2).replace('.', ',')} m² &nbsp;·&nbsp; Uključuje <strong>+5% rezerva</strong></span>
      </div>` : ''}
      ${spcDims ? `
      <div style="background:rgba(92,74,50,0.12);border:1px solid rgba(92,74,50,0.4);border-radius:8px;padding:8px 12px;margin-bottom:10px;font-size:13px;color:#9b7d56;display:flex;align-items:center;gap:8px;">
        <i class="fas fa-ruler-combined"></i>
        <span>Svaka daska: <strong>${spcDims.w} × ${spcDims.h} cm</strong> &nbsp;·&nbsp; Kalkulator uključuje <strong>+10% otpad</strong> za rezove</span>
      </div>` : (product.category === 'spc-pod' ? `
      <div style="background:rgba(92,74,50,0.12);border:1px solid rgba(92,74,50,0.4);border-radius:8px;padding:8px 12px;margin-bottom:10px;font-size:13px;color:#9b7d56;display:flex;align-items:center;gap:8px;">
        <i class="fas fa-info-circle"></i>
        <span>Kalkulator uključuje <strong>+10% otpad</strong> za rezove</span>
      </div>` : '')}
      <div class="pq-calc-inner">
        <div class="pq-calc-field">
          <label>${product.category === 'spc-pod' ? 'Dužina prostorije' : 'Širina zida'}</label>
          <div class="pq-calc-stepper">
            <button type="button" onclick="stepCalc('wall-w',-0.5)">−</button>
            <input type="number" id="wall-w" value="${product.category === 'spc-pod' ? '3' : '1'}" min="0.5" max="50" step="0.5" oninput="calcPanels()">
            <span class="pq-calc-unit">m</span>
            <button type="button" onclick="stepCalc('wall-w',0.5)">+</button>
          </div>
        </div>
        <div class="pq-calc-field">
          <label>${product.category === 'spc-pod' ? 'Širina prostorije' : 'Visina zida'}</label>
          <div class="pq-calc-stepper">
            <button type="button" onclick="stepCalc('wall-h',-0.1)">−</button>
            <input type="number" id="wall-h" value="${product.category === 'spc-pod' ? '3.5' : '2.8'}" min="0.5" max="50" step="0.1" oninput="calcPanels()">
            <span class="pq-calc-unit">m</span>
            <button type="button" onclick="stepCalc('wall-h',0.1)">+</button>
          </div>
        </div>
      </div>
      <div class="pq-calc-result" id="calc-result"></div>
    </div>
    `}

    <div class="product-short-desc">${product.description}</div>

    <!-- Akcijska dugmad -->
    <div class="pq-actions">
      <button class="pq-btn-primary" onclick="inquireProduct('${product.name}')">
        <i class="fas fa-envelope"></i> Pošalji Upit
      </button>
      <a href="${waLink}" target="_blank" rel="noopener" class="pq-btn-dark">
        <i class="fab fa-whatsapp"></i> WhatsApp
      </a>
    </div>
    <div class="pq-howto">
      <a href="contact.html"><i class="fas fa-circle-question"></i> Kako da kupim?</a>
    </div>

    <!-- Accordion sekcije -->
    <div class="spec-accordion">

      <div class="spec-item">
        <button class="spec-header" onclick="toggleSpec(this)">
          <span><i class="fas fa-list-check"></i> Karakteristike</span>
          <i class="fas fa-chevron-down spec-arrow"></i>
        </button>
        <div class="spec-body open">
          <ul class="spec-feature-list">
            ${(() => {
              const protMap = [
                { k: 'Vodootporan',            icon: 'fa-droplet',           color: '#1a7abf' },
                { k: 'Otporan na buđ',         icon: 'fa-shield-halved',     color: '#2e9e6b' },
                { k: 'Vatrootporan',           icon: 'fa-fire-flame-curved', color: '#d4620a' },
                { k: 'Otporan na prljavštinu', icon: 'fa-hand-sparkles',     color: '#7b5ea7' },
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
                    <strong style="color:${prot.color}dd;">${main}</strong>
                  </li>`;
                }
                // Join all continuations inline — no chips, no separate lines
                const full = cont.length > 0 ? main + ', ' + cont.join(', ') : main;
                return `<li><i class="fas fa-check"></i>${full}</li>`;
              }).join('');
            })()}
          </ul>
        </div>
      </div>

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
      <div class="trust-item"><i class="fas fa-truck"></i><span>Dostava Crna Gora</span></div>
      <div class="trust-item"><i class="fas fa-tools"></i><span>Savjeti za montažu</span></div>
      <div class="trust-item"><i class="fas fa-undo"></i><span>Zamjena u 7 dana</span></div>
    </div>

  `;

  // Matching pairs (panel ↔ 3D letvica sa istom nijansom)
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
  if (partnerIds && partnerIds.length > 0) {
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
        <a href="product.html?id=${p.id}" class="pair-card">
          <div class="pair-card-img">
            <img src="${p.image}" alt="${p.name}" loading="lazy">
            ${p.badge ? `<span class="pair-badge">${p.badge}</span>` : ''}
          </div>
          <div class="pair-card-info">
            <div class="pair-card-name">${p.name}</div>
            <div class="pair-card-price">${parseFloat(p.price).toFixed(2).replace('.', ',')} €<span class="pair-card-unit"> / ${p.unit}</span></div>
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

  // Append reviews directly to info
  info.insertAdjacentHTML('beforeend', reviewsHtml);

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

  // Related products
  const related = allProducts.filter(p => p.category === product.category && p.id !== id).slice(0, 4);
  const relContainer = document.getElementById('related-products');
  if (relContainer && related.length > 0) {
    relContainer.innerHTML = related.map(p => renderProductCard(p)).join('');
    initAnimations();
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
    if (img) { img.style.opacity = '0'; setTimeout(() => { img.src = images[lbIdx].src; img.style.opacity = '1'; }, 100); }
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

// ===== PRODUCT MODAL (Brzi pregled) =====
function openProductModal(id) {
  const product = allProducts.find(p => p.id === id);
  if (!product) return;
  const categoryName = allCategories.find(c => c.id === product.category)?.name || '';

  const overlay = document.createElement('div');
  overlay.id = 'product-modal';
  overlay.style.cssText = `position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:9999;
    display:flex;align-items:center;justify-content:center;padding:20px;`;
  overlay.innerHTML = `
    <div style="background:#fff;border-radius:20px;max-width:700px;width:100%;max-height:90vh;overflow-y:auto;position:relative;">
      <button onclick="document.getElementById('product-modal').remove()"
        style="position:absolute;top:16px;right:16px;border:none;background:rgba(0,0,0,0.08);
        width:36px;height:36px;border-radius:50%;cursor:pointer;font-size:18px;z-index:1;
        display:flex;align-items:center;justify-content:center;">✕</button>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;">
        <div style="height:300px;background:#f5f0eb;border-radius:20px 0 0 20px;overflow:hidden;display:flex;align-items:center;justify-content:center;">
          <img src="${product.image}" alt="${product.name}" style="width:100%;height:100%;object-fit:cover;"
            onerror="this.parentElement.innerHTML='<i class=\'fas fa-image\' style=\'font-size:64px;color:#ccc\'></i>'">
        </div>
        <div style="padding:32px 28px;">
          <div style="font-size:11px;text-transform:uppercase;letter-spacing:1.5px;color:var(--primary);font-weight:600;margin-bottom:8px;">${categoryName}</div>
          <h2 style="font-family:var(--font-heading);font-size:22px;margin-bottom:8px;">${product.name}</h2>
          <div style="font-size:28px;font-family:var(--font-heading);font-weight:700;color:var(--dark);margin-bottom:8px;">
            ${product.price} € <span style="font-size:14px;font-weight:400;color:#888;font-family:var(--font-body)">/ ${product.unit}</span>
          </div>
          <p style="font-size:13px;color:#666;line-height:1.6;margin-bottom:20px;">${product.description}</p>
          <ul style="list-style:none;padding:0;margin-bottom:24px;">
            ${product.features.slice(0,3).map(f => `<li style="font-size:13px;color:#444;display:flex;align-items:center;gap:8px;margin-bottom:6px;"><i class="fas fa-check" style="color:var(--primary)"></i>${f}</li>`).join('')}
          </ul>
          <div style="display:flex;flex-direction:column;gap:10px;">
            <a href="product.html?id=${product.id}" class="btn btn-primary" style="justify-content:center;">
              <i class="fas fa-eye"></i> Pogledaj Detalje
            </a>
            <button class="btn btn-dark" onclick="inquireProduct('${product.name}');document.getElementById('product-modal').remove();" style="justify-content:center;">
              <i class="fas fa-envelope"></i> Pošalji Upit
            </button>
          </div>
        </div>
      </div>
    </div>
  `;
  overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });
  document.body.appendChild(overlay);
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
