/* ===================================================
   MAKE MY HOME - Products JavaScript
   =================================================== */

let currentFilter = 'all';

let allProducts = [];
let allCategories = [];

// Željeni redoslijed kategorija na stranici "Svi proizvodi"
const CATEGORY_ORDER = [
  'bambus-drveni',
  'bambus-tekstilni',
  'bambus-mermerni',
  'classic',
  'bambus-kozni',
  'bambus-metalni',
  '3d-letvice',
  'akusticni-paneli',
  'aluminijum-lajsne',
  'spc-pod'
];

// ===== UČITAJ PODATKE SA SERVERA =====
async function loadData() {
  try {
    const [prodRes, catRes] = await Promise.all([
      fetch('data/products.json?v=' + Date.now()),
      fetch('data/categories.json?v=' + Date.now())
    ]);
    allProducts = await prodRes.json();
    allCategories = await catRes.json();
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
    <article class="product-card animate-on-scroll${outOfStock ? ' out-of-stock' : ''}" data-category="${product.category}" data-id="${product.id}" onclick="window.location='product.html?id=${product.id}'" style="cursor:pointer;">
      <div class="product-img">
        ${imgContent}
        ${badge}
        ${outOfStock ? `<div class="oos-tag">Nema na stanju</div>` : ''}
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
  await loadData();

  const params = new URLSearchParams(window.location.search);
  const cat = params.get('cat') || params.get('category');

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
  const pageSub = document.getElementById('page-subtitle');
  if (pageSub) pageSub.textContent = 'Odaberite tip panela';

  // Back button goes to all categories
  const btnBack = document.querySelector('.btn-back');
  if (btnBack) { btnBack.href = 'products.html'; btnBack.innerHTML = '<i class="fas fa-arrow-left"></i> Sve Kategorije'; }

  // Count products per subcategory
  const grid = document.getElementById('category-grid');
  grid.innerHTML = parentCat.subcategories.map(sub => {
    const subProducts = allProducts.filter(p => p.category === sub.id);
    const firstImg = subProducts.find(p => p.image)?.image || '';
    return `
      <a href="products.html?cat=${sub.id}" class="cat-card animate-on-scroll">
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
    <a href="products.html?cat=${cat.id}" class="cat-card animate-on-scroll">
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

  // Update breadcrumb & title
  const breadLabel = document.getElementById('breadcrumb-label');
  if (breadLabel) breadLabel.textContent = cat.name;
  const pageTitle = document.getElementById('page-title');
  if (pageTitle) pageTitle.textContent = cat.name;
  const pageSub = document.getElementById('page-subtitle');
  if (pageSub) pageSub.textContent = `Pogledajte sve ${cat.count} proizvoda u kategoriji ${cat.name}`;

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
    <a href="products.html?cat=${cat.id}" class="category-card animate-on-scroll">
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
  if (galleryMain) {
    galleryMain.innerHTML = `
      <img id="gallery-main-img" src="${product.image}" alt="${product.name}"
        onclick="openImageLightbox(this.src, '${product.name}')"
        style="cursor:zoom-in;"
        onerror="this.style.display='none'">
    `;
  }
  if (galleryThumbs) {
    const images = [{ src: product.image, label: 'Proizvod' }];
    if (product.roomImage) images.push({ src: product.roomImage, label: 'U prostoru' });
    if (images.length > 1) {
      galleryThumbs.innerHTML = images.map((img, i) => `
        <div class="gallery-thumb ${i === 0 ? 'active' : ''}" onclick="switchGalleryImg(this, '${img.src}')">
          <img src="${img.src}" alt="${img.label}">
        </div>`).join('');
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
      { name: 'Marko T.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Golden Teak je savršen izbor za dnevnu sobu – ta topla zlatno-smeđa nijansa tika odmah grije prostor. Postavio iza TV-a i svi gosti pitaju odakle mi ideja. Izgleda skupo, a montaža je bila gotova za sat.' },
      { name: 'Jovana M.', city: 'Bar', date: 'Februar 2026', stars: 5, text: 'Renovirali smo kafić i odabrali Golden Teak za cijeli jedan zid. Gosti stalno komentarišu koliko je prostorija udobna i topla. Boja je tačno kao na slikama, silikon drži savršeno.' }
    ]},
    19: { total: 10, fiveS: 8, fourS: 2, comments: [
      { name: 'Stefan K.', city: 'Budva', date: 'Mart 2026', stars: 5, text: 'Nordic Oak je pravi izbor za moderan, svijetao stan. Ta hladna, nordijska nijansa hrasta čini sobu vizualno većom i čišćom. Postavio u spavaćoj sobi i efekt je kao da sam renovirao cijeli stan.' },
      { name: 'Ana P.', city: 'Podgorica', date: 'Januar 2026', stars: 4, text: 'Otvorili smo novi wellness centar i Nordic Oak je bio savršen izbor za recepciju. Daje onaj skandinavski mir i uređenost koji gosti odmah osjete. Montaža brza i bez problema.' }
    ]},
    20: { total: 12, fiveS: 11, fourS: 1, comments: [
      { name: 'Nikola V.', city: 'Podgorica', date: 'Februar 2026', stars: 5, text: 'Dark Ash je brutalno lijep. Postavio cijeli zid u spavaćoj sobi – izgleda kao suite u petospratnom hotelu. Tamna pepeljasta nijansa daje dramatičnost bez agresivnosti.' },
      { name: 'Milica Đ.', city: 'Herceg Novi', date: 'Januar 2026', stars: 5, text: 'Renovirali smo restoran i Dark Ash je bio naš prvi izbor. Tamna boja uz toplu rasvjetu stvara atmosferu kakvu gosti ne mogu zaboraviti. Preporučujem svima koji žele premium izgled.' }
    ]},
    21: { total: 6, fiveS: 5, fourS: 1, comments: [
      { name: 'Petar S.', city: 'Cetinje', date: 'Mart 2026', stars: 5, text: 'Smoke Oak je boja koja ne zastarijeva – ta dimljeno-siva nijansa hrasta ide uz svaki stil nameštaja. Postavio u uredu i prostor je dobio ozbiljnost i modernost istovremeno.' },
      { name: 'Jelena B.', city: 'Podgorica', date: 'Februar 2026', stars: 5, text: 'Odabrala za hodnik stana i presretan sam s izborom. Smoke Oak je neutralan ali nikako dosadan – tekstura drveta daje mu život. Montaža silikonom bila je laka i brza.' }
    ]},
    22: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Dragan L.', city: 'Bar', date: 'Mart 2026', stars: 5, text: 'Amber Oak ima tu jantarnu toplinu koja odmah grije svaki prostor. Postavio u dnevnoj sobi i prijatelji stalno pitaju da li je pravo drvo. Kvalitet je iznenađujuće dobar za ovu cijenu.' },
      { name: 'Sanja N.', city: 'Tivat', date: 'Februar 2026', stars: 5, text: 'Za kafić koji smo otvarali tražili smo nešto toplo i prirodno. Amber Oak je bio savršen – med-žuta nijansa hrasta uz tople lampe stvara ambijent koji gosti obožavaju. Svaka preporuka!' }
    ]},
    23: { total: 11, fiveS: 10, fourS: 1, comments: [
      { name: 'Igor M.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Mocha Oak je moja omiljena nijansa. Ta srednje-smeđa topla boja daje prostoriji karakter bez pretjerivanja. Postavio u trpezariji i svakom gostu odmah pade pogled na taj zid.' },
      { name: 'Vesna K.', city: 'Kotor', date: 'Februar 2026', stars: 5, text: 'U hotelu smo renovirali sobe i Mocha Oak je bio naš izbor za zid iza kreveta. Gosti pišu u recenzijama da su sobe posebno udobne i tople – panel je definitivno doprinosi tome.' }
    ]},
    24: { total: 8, fiveS: 7, fourS: 1, comments: [
      { name: 'Luka P.', city: 'Podgorica', date: 'Februar 2026', stars: 5, text: 'Honey Oak izgleda kao pravo drvo – ta medeno-topla nijansa je autentična i živa. Postavio iza sofe i efekt je nevjerovatan. Dnevna soba je dobila dušu kakvu nije imala s bijelim zidovima.' },
      { name: 'Ivana C.', city: 'Budva', date: 'Januar 2026', stars: 5, text: 'Odabrala Honey Oak za salon za uljepšavanje i klijentice stalno pitaju odakle ta ideja. Topla nijansa hrasta stvara opuštenu, ugodnu atmosferu. Paneli su čvrsti i lako se čiste.' }
    ]},
    25: { total: 5, fiveS: 4, fourS: 1, comments: [
      { name: 'Radovan T.', city: 'Nikšić', date: 'Mart 2026', stars: 5, text: 'Havana Oak ima specifičnu tamnu toplinu koja privlači pogled. Tamno-smeđa nijansa s izraženim drvenim šarama daje zidu karakter kao u ekskluzivnom baru na Havani. Jedinstven izbor.' },
      { name: 'Nataša B.', city: 'Podgorica', date: 'Februar 2026', stars: 4, text: 'Postavio u kućnoj biblioteci i kombinacija Havana Oak panela s drvenim policama je fenomenalna. Prostor djeluje kao da je dizajner radio na projektu. Svaka preporuka!' }
    ]},
    26: { total: 13, fiveS: 12, fourS: 1, comments: [
      { name: 'Bojan S.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Espresso Teak je savršen spoj tamnog i toplog. Postavio u kancelariji i kolege svakodnevno komentarišu koliko prostor djeluje ozbiljno i elegantno. Espresso nijansa tika je timeless.' },
      { name: 'Maja F.', city: 'Bar', date: 'Januar 2026', stars: 5, text: 'U restoranu smo stavili Espresso Teak na zid iza šanka – tamna nijansa uz zlatnu rasvjetu daje luksuz koji gosti odmah primjete. Panel je čvrst, lak za čišćenje i izgleda skuplje nego što košta.' }
    ]},
    // ── Tekstilni paneli ──
    37: { total: 12, fiveS: 11, fourS: 1, comments: [
      { name: 'Tijana K.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Perla je savršena za spavaću sobu – kremasto-biserna nijansa s lanenom teksturom daje nevjerovatnu toplinu. Kao da si obavio zid u skupocjenu tkaninu. Svaki gost ostane bez teksta kad uđe u sobu.' },
      { name: 'Srđan P.', city: 'Nikšić', date: 'Februar 2026', stars: 5, text: 'Postavio Perla panel u recepciju butik hotela koji smo otvarali. Tekstura lana je nevjerovatno autentična i upija svjetlo na poseban način. Gosti fotografišu recepciju i pitaju koji je materijal na zidu.' }
    ]},
    38: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Jovana M.', city: 'Tivat', date: 'Mart 2026', stars: 5, text: 'Calla je savršena za minimalistički enterijer – čista bijela s finom tekstilnom teksturom nije ravna kao farba nego živa i dinamična. Zid diše. Kombinovala sa drvenim nameštajem i rezultat je moderan i čist.' },
      { name: 'Lazar Đ.', city: 'Bar', date: 'Januar 2026', stars: 4, text: 'U wellness centru smo koristili Calla za sve zidove tretman soba. Bijela tekstilna površina daje čistoću i mir koji je idealan za takav prostor. Klijenti kažu da se odmah osjete opuštenije.' }
    ]},
    39: { total: 8, fiveS: 7, fourS: 1, comments: [
      { name: 'Maja S.', city: 'Kotor', date: 'Mart 2026', stars: 5, text: 'Grigio je idealan za kancelariju – neutralno siva tekstilna površina daje prostor ozbiljnost i eleganciju bez pretjerivanja. Klijenti uvijek komentarišu koliko ured djeluje profesionalno i uređeno.' },
      { name: 'Nikola B.', city: 'Podgorica', date: 'Februar 2026', stars: 5, text: 'Siva nijansa Grigio je tačno onakva kakva se vidi na slikama – ni pretopla ni prehladna. Tekstilna površina upija svjetlo i daje sobi miran, staložen karakter. Savršen za spavaću sobu.' }
    ]},
    40: { total: 11, fiveS: 10, fourS: 1, comments: [
      { name: 'Ana R.', city: 'Herceg Novi', date: 'Mart 2026', stars: 5, text: 'Slate je moj apsolutni favorit. Ta sivo-bež nijansa s prirodnom lanenom teksturom ide uz sve – drvo, metal, beton. Postavio u dnevnoj sobi i prijatelji pitaju da li sam uzeo dizajnera enterijerа.' },
      { name: 'Dejan V.', city: 'Budva', date: 'Februar 2026', stars: 5, text: 'Koristili smo Slate za renovaciju restorana – cijeli jedan zid prekriven ovim panelom. Tekstura lana daje toplinu i prirodnost kakvu tvrdi materijali ne mogu dati. Gosti su oduševljeni.' }
    ]},
    41: { total: 7, fiveS: 6, fourS: 1, comments: [
      { name: 'Jelena F.', city: 'Cetinje', date: 'Mart 2026', stars: 5, text: 'Blanc je čista, topla bijela s delikatnom teksturom – nije hladna bijela nego živa i prijatna. Postavio u spavaćoj sobi uz zlatne lampe i efekt je kao iz luksuznog boutique hotela.' },
      { name: 'Marija T.', city: 'Podgorica', date: 'Januar 2026', stars: 5, text: 'Odabrala Blanc za salon za vjenčanja koji uređujemo. Čista bijela tekstilna površina je savršena pozadina za fotografisanje. Klijenti su presretni, prostorija izgleda kao iz magazina.' }
    ]},
    42: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Petar L.', city: 'Tivat', date: 'Februar 2026', stars: 5, text: 'Siena je delikatan i sofisticiran panel – tople sivo-smeđe nijanse tekstilne površine daju prostoru karakter kakav ne možeš postići bojom. Postavio u trpezariji i prostorija je dobila dušu.' },
      { name: 'Sandra N.', city: 'Bar', date: 'Januar 2026', stars: 5, text: 'Renovirali smo kafić i Siena je bio savršen izbor za fokalni zid iza šanka. Topli tonovi s tekstilnom teksturom uz vintage rasvjetu stvaraju ambijent koji gosti obožavaju. Preporučujem svima!' }
    ]},
    43: { total: 6, fiveS: 5, fourS: 1, comments: [
      { name: 'Bojan K.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Glacier je jedinstven – ta ledeno-bijela nijansa s hladnim podtonom daje prostoru svježinu i modernitet. Postavio u spa centru i klijenti kažu da se odmah osjete kao da su na odmoru.' },
      { name: 'Vesna M.', city: 'Budva', date: 'Februar 2026', stars: 4, text: 'Koristila Glacier u kupatilu umjesto keramike – ni bela ni plava nego nešto između što daje savremeni spa izgled. Uz mat metalne slavine i betонski pod je kombinacija koja osvaja na prvi pogled.' }
    ]},
    44: { total: 10, fiveS: 9, fourS: 1, comments: [
      { name: 'Ivana S.', city: 'Nikšić', date: 'Mart 2026', stars: 5, text: 'Pura s buklé teksturom je nešto posebno – taj meki, trodimenzionalni izgled tkanine na zidu daje spavaćoj sobi izgled iz modnog magazina. Svaki gost ostane zatečen kad vidi taj zid.' },
      { name: 'Aleksandar P.', city: 'Podgorica', date: 'Januar 2026', stars: 5, text: 'Postavio Pura panel u recepciju hotela koji renoviramo. Buklé površina je toliko upečatljiva da odmah postaje fokalna tačka prostorije. Gosti fotografišu recepciju i dijele na društvenim mrežama.' }
    ]},
    45: { total: 13, fiveS: 12, fourS: 1, comments: [
      { name: 'Milena D.', city: 'Kotor', date: 'Mart 2026', stars: 5, text: 'Deva šampanjac je pravi luksuz. Topla zlatno-šampanjac nijansa s tekstilnom površinom daje dnevnoj sobi glamur kakav ne možeš postići nikakvom bojom. Svaki gost odmah primjeti taj zid.' },
      { name: 'Stefan J.', city: 'Herceg Novi', date: 'Februar 2026', stars: 5, text: 'Renovirali smo banketnu salu restorana i Deva je bio naš izbor za sve zidove. Šampanjac s tekstilnom teksturom uz kristalne lustere je kombinacija koja oduševljava goste na svakom događaju.' }
    ]},
    // ── Mermerni paneli ──
    46: { total: 11, fiveS: 10, fourS: 1, comments: [
      { name: 'Dragana M.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Mystic Marble izgleda kao pravi mermerni zid. Gosti uvijek pitaju da li sam ugradila pravi mermer – ne mogu da vjeruju da je panel. Postavljeno u dnevnoj sobi i prostor djeluje dvostruko skuplje.' },
      { name: 'Zoran K.', city: 'Budva', date: 'Februar 2026', stars: 5, text: 'Postavio Mystic Marble u kupatilu iza lavaboa. Efekat je nevjerovatan – luksuz za malu cijenu. Bijelo-sive žilice izgledaju autentično, montaža sa silikonom prošla bez ikakvih problema.' }
    ]},
    47: { total: 8, fiveS: 7, fourS: 1, comments: [
      { name: 'Kristina P.', city: 'Kotor', date: 'Mart 2026', stars: 5, text: 'Desert Stone ima tople pješčano-sive tonove koji su potpuno drugačiji od klasičnog mermera. Postavljeno u holu kuće i svaki gost ostane zatečen – toplo i prirodno, kao da si unijela komad pustime u dom.' },
      { name: 'Nemanja R.', city: 'Nikšić', date: 'Januar 2026', stars: 4, text: 'Jako lijepa imitacija kamena – topli pješčani tonovi daju prostoru organsku prirodnost. Postavljeno u kafić koji smo renovirali i gosti stalno komentarišu koliko je prostorija unikatna.' }
    ]},
    48: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Ivana B.', city: 'Herceg Novi', date: 'Februar 2026', stars: 5, text: 'White Marble je klasik koji ne može da pogreši. Čisti bijeli mermer sa suptilnim sivim žilicama je savršen za kupatilo ili dnevnu sobu. Izgleda skupo i elegantno, a montaža je trajala svega sat.' },
      { name: 'Rade S.', city: 'Tivat', date: 'Januar 2026', stars: 5, text: 'Renovirali smo recepciju hotela i White Marble je bio naš izbor. Fotografije ne mogu prenijeti koliko lijepo izgleda uživo – gosti odmah pitaju koji materijal je na zidu. Čisto luksuz.' }
    ]},
    49: { total: 7, fiveS: 6, fourS: 1, comments: [
      { name: 'Snežana J.', city: 'Bar', date: 'Mart 2026', stars: 5, text: 'Lava Stone je dramatičan i moćan. Tamna vulkanska tekstura na fokalnom zidu u dnevnoj sobi potpuno je promijenila karakter prostorije. Gosti pitaju odakle ta ideja – niko ne može pogoditi da je panel.' },
      { name: 'Miloš V.', city: 'Cetinje', date: 'Februar 2026', stars: 5, text: 'Koristio Lava Stone u baru koji smo otvarali – tamna kamena tekstura uz industrijsku rasvjetu je kombinacija koja oduševljava goste. Panel je čvrst, lako se čisti i izgleda kao pravi kamen.' }
    ]},
    50: { total: 13, fiveS: 12, fourS: 1, comments: [
      { name: 'Tatjana Đ.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Urban Concrete ima savršenu betonsku teksturu – hladan, urbani izgled koji je savremena alternativa klasičnom mermeru. Postavio u kancelariji i prostor odmah dobio industrijski-luksuzni karakter.' },
      { name: 'Goran N.', city: 'Bijelo Polje', date: 'Februar 2026', stars: 5, text: 'Renovirali smo moderan restoran i Urban Concrete je bio savršen za zidove. Beton tekstura uz drvene stolove i metalne detalje je kombinacija kojom su gosti oduševljeni od prvog dana.' }
    ]},
    51: { total: 10, fiveS: 9, fourS: 1, comments: [
      { name: 'Bojana L.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Nordic Concrete ima nešto posebno – topliji, svjetliji ton betona s blagim kremastim podtonom. Daje prostoru nordijsku čistoću bez hladnoće. Trpezarija je potpuno transformisana, jako sam zadovoljna.' },
      { name: 'Danilo F.', city: 'Budva', date: 'Januar 2026', stars: 5, text: 'Koristio Nordic Concrete za renovaciju spa centra – ta topla betonska nijansa uz drvene detalje i zeleno bilje je kombinacija koja opušta i liječi. Klijenti obožavaju atmosferu tretman soba.' }
    ]},
    52: { total: 6, fiveS: 5, fourS: 1, comments: [
      { name: 'Marina T.', city: 'Kotor', date: 'Februar 2026', stars: 5, text: 'Noir Stone je hrabar izbor koji se isplati – tamna crno-antracit kamena tekstura daje prostoru snagu i ekskluzivnost. Postavljeno u kućnom bioskopu i prostorija izgleda kao profesionalni kino.' },
      { name: 'Predrag M.', city: 'Podgorica', date: 'Januar 2026', stars: 4, text: 'U premium baru koji smo otvarali Noir Stone je bio obavezan izbor. Tamna kamena tekstura uz zlatnu rasvjetu je kombinacija koja komunicira luksuz i ekskluzivnost. Gosti su odmah primijetili razliku.' }
    ]},
    53: { total: 12, fiveS: 11, fourS: 1, comments: [
      { name: 'Jelena S.', city: 'Nikšić', date: 'Mart 2026', stars: 5, text: 'Beige Marble je savremena i sofisticirana – topli bež mermer s tamnim žilicama je moderniji izbor od klasičnog bijelog. Kupatilo izgleda kao suite u luksuznom hotelu, svi gosti su impresionisani.' },
      { name: 'Stefan B.', city: 'Bar', date: 'Februar 2026', stars: 5, text: 'Renovirali smo boutique hotel i Beige Marble smo koristili za kupaonice. Gosti pišu u recenzijama da se kupaonice osjećaju kao spa. Panel je čvrst, vodootporan i nevjerovatno lijepo izgleda.' }
    ]},
    54: { total: 8, fiveS: 7, fourS: 1, comments: [
      { name: 'Aleksandra C.', city: 'Tivat', date: 'Mart 2026', stars: 5, text: 'Dark Luxe je jedinstven – tamna, duboka nijansa s bogatim žilicama daje prostoru ekskluzivnost kakvu teško postižeš s drugim materijalima. Postavljeno u VIP sobi restorana i efekt je spektakularan.' },
      { name: 'Vuk J.', city: 'Podgorica', date: 'Januar 2026', stars: 5, text: 'U privatnoj kancelariji sam htio nešto posebno i Dark Luxe je bio savršen izbor. Tamna kamena tekstura s bogatim uzorkom daje prostor ozbiljnost i prestiž. Svaki poslovni partner komentariše taj zid.' }
    ]},
    55: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Milica R.', city: 'Herceg Novi', date: 'Mart 2026', stars: 5, text: 'Sahara je topla i elegantna – pješčano-bež nijansa s blagim žilicama daje prostoru mediteransku toplinu. Postavljeno u dnevnoj sobi i u kombinaciji sa prirodnim materijalima izgleda kao luksuzni resort.' },
      { name: 'Andrija K.', city: 'Budva', date: 'Februar 2026', stars: 5, text: 'Odabrali Sahara za hotelski lobi koji renoviramo. Ta topla sahara nijansa uz drvo i zelenilo stvara ambijent luksuznog odmarališta. Gosti odmah pitaju koji materijal je na zidu – niko ne pogađa da je panel.' }
    ]},

    // ── Metalni paneli ──
    56: { total: 8, fiveS: 7, fourS: 1, comments: [
      { name: 'Radovan T.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Brushed Gold je nešto što nisam vidio kod konkurencije. Četkano zlato pod usmjerenom rasvjetom stvara igru svjetla i sjenke koja menja izgled prostorije tokom dana. Postavio u trpezariji i efekat je luksuzno-dramatičan.' },
      { name: 'Katarina M.', city: 'Budva', date: 'Februar 2026', stars: 5, text: 'Koristili smo Brushed Gold za recepciju premium salona – zlatna površina s četkanom teksturom komunicira ekskluzivnost i kvalitet čim neko uđe na vrata. Klijenti odmah primjete i komentarišu.' }
    ]},
    57: { total: 11, fiveS: 10, fourS: 1, comments: [
      { name: 'Dragan S.', city: 'Bar', date: 'Mart 2026', stars: 5, text: 'Raw Steel je prava bomba za industrijski enterijer. Mat čelična površina bez refleksija daje prostoru autentičan loft karakter kakav ne možeš postići drugačije. Postavljeno u novom baru i gosti su oduševljeni.' },
      { name: 'Nataša V.', city: 'Tivat', date: 'Januar 2026', stars: 5, text: 'Raw Steel panel u kombinaciji s drvenim elementima i betonskim podom je savršen industrijski trio. Panel je čvrst, ne deformiše se i izgleda kao pravi čelik. Ekipa u uredu ga obožava.' }
    ]},
    58: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Marko Đ.', city: 'Nikšić', date: 'Februar 2026', stars: 5, text: 'Champagne Metal je suptilan i elegantan – topla šampanjac-metalna nijansa nije previše zlatna ni previše srebrna. Postavljeno u spavaćoj sobi i daje onaj soft-luksuz koji obožavam. Fenomenalan izbor.' },
      { name: 'Jovana K.', city: 'Podgorica', date: 'Januar 2026', stars: 4, text: 'U boutique hotelu koji renoviramo koristili smo Champagne Metal za hodnik soba. Topla metalna nijansa uz dimovano ogledalo i mesingane lampe je kombinacija kojom su gosti oduševljeni od prvog dana.' }
    ]},
    59: { total: 7, fiveS: 6, fourS: 1, comments: [
      { name: 'Aleksandar B.', city: 'Kotor', date: 'Mart 2026', stars: 5, text: 'Bronzani metalni panel je jedinstven izbor – ni zlatni ni smeđi nego nešto između što daje prostoru toplinu i luksuz istovremeno. Postavljeno u restoranskoj VIP sali i efekt je impresivan.' },
      { name: 'Mirjana F.', city: 'Herceg Novi', date: 'Februar 2026', stars: 5, text: 'Bronza u kombinaciji s tamnim drvom je savršen spoj. Trpezarija izgleda kao iz ekskluzivnog restorana. Panel je čvrst, lako se čisti i tokom vremena dobija patinu koja mu daje još više karaktera.' }
    ]},

    60: { total: 10, fiveS: 9, fourS: 1, comments: [
      { name: 'Igor S.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'CQ006 Topli Tik letvice su transformisale moju dnevnu sobu. 3D efekat pod lampama je nevjerovatna – senke i svjetlo igraju cijeli dan.' },
      { name: 'Tijana R.', city: 'Bar', date: 'Februar 2026', stars: 5, text: 'Tik tonovi su topli i prirodni. Montaža silikonom je bila jednostavna, a PVC materijal je lagan i lako se siječe skalpelom.' }
    ]},
    61: { total: 8, fiveS: 7, fourS: 1, comments: [
      { name: 'Stefan M.', city: 'Budva', date: 'Mart 2026', stars: 5, text: 'MW300 Prirodna Bela letvice daju zidu skulpturalni izgled. Čiste, moderne, savršene za svetle minimalistike enterijere.' },
      { name: 'Maja L.', city: 'Tivat', date: 'Januar 2026', stars: 5, text: 'Bela letvica uz LED traku iza je savršena kombinacija. Prostor dobija dubinu i ambijentalnost kakvu nisam mogla ni zamisliti.' }
    ]},
    62: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Nikola V.', city: 'Nikšić', date: 'Februar 2026', stars: 5, text: 'MW312 Pepeljasti Hrast je delikatan i sofisticiran. Siva sa drvenim uzorkom daje zidu prirodan karakter bez pretjerivanja.' },
      { name: 'Sanja B.', city: 'Podgorica', date: 'Januar 2026', stars: 4, text: 'Lepa nijansa pepela i hrasta – moderna i topla istovremeno. 3D senke pod spotovima su fenomenalne.' }
    ]},
    63: { total: 7, fiveS: 6, fourS: 1, comments: [
      { name: 'Petar D.', city: 'Kotor', date: 'Mart 2026', stars: 5, text: 'MW321 Zlatni Kesten ima bogatu, toplu teksturu koja osvaja na prvi pogled. Hodnik je potpuno promijenjen – topliji i prijatniji.' },
      { name: 'Jelena N.', city: 'Bar', date: 'Februar 2026', stars: 5, text: 'Kesten tonovi su savršeni za trpezariju. 3D reljef stvara igru senki koja menja izgled tokom dana zavisno od osvjetljenja.' }
    ]},
    64: { total: 12, fiveS: 11, fourS: 1, comments: [
      { name: 'Milena P.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'MW010 Snježna Bijela letvice su klasika koja nikad ne stari. Oštre, definisane forme uz belu boju daju prostoru arhitektonski izgled.' },
      { name: 'Vuk J.', city: 'Budva', date: 'Januar 2026', stars: 5, text: 'Postavio u spavaćoj sobi uz LED traku i efekat je kao u luksuznom hotelu. Montaža brza, PVC materijal lagan i praktičan.' }
    ]},
    65: { total: 8, fiveS: 7, fourS: 1, comments: [
      { name: 'Dragana K.', city: 'Herceg Novi', date: 'Februar 2026', stars: 5, text: 'Mat verzija M51-01 je diskretna i elegantna. Nema refleksije svjetlosti što je savršeno za dnevne sobe sa puno prirodnog svetla.' },
      { name: 'Bojan R.', city: 'Cetinje', date: 'Januar 2026', stars: 5, text: 'Mat bela letvica je sofisticiranija od gloss verzije. Prijatna za oči, moderna i čista. Odlična za minimalistički enterijer.' }
    ]},
    66: { total: 7, fiveS: 6, fourS: 1, comments: [
      { name: 'Ivana S.', city: 'Tivat', date: 'Mart 2026', stars: 5, text: 'Gloss MW319 letvice su spektakularne pod spotovima – sjaj bele u kombinaciji sa 3D senkom pravi dramatičan efekat koji oduševljava.' },
      { name: 'Luka M.', city: 'Podgorica', date: 'Februar 2026', stars: 5, text: 'Sjajne letvice menjaju karakter prostora zavisno od izvora svetla. Dnevni prostor je potpuno drugačiji noću i danju.' }
    ]},
    67: { total: 11, fiveS: 10, fourS: 1, comments: [
      { name: 'Goran T.', city: 'Nikšić', date: 'Mart 2026', stars: 5, text: 'MW682 Tamni Orah letvice su dramatične i moćne. Iza kauča u dnevnoj sobi izgledaju kao skulptura – nevjerovatna dubina i karakter.' },
      { name: 'Ana Đ.', city: 'Bar', date: 'Januar 2026', stars: 5, text: 'Tamni orah u 3D formatu je drugačiji od svakog drugog materijala. Senke između letvica stvaraju iluziju mnogo dubljeg zida.' }
    ]},
    68: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Danilo V.', city: 'Budva', date: 'Mart 2026', stars: 5, text: 'SP054 Hladni Sivi širi profil 170mm daje arhitektonsku snagu zidu. Efekat je moćan i moderan – idealno za fokalni zid u dnevnoj sobi.' },
      { name: 'Kristina B.', city: 'Podgorica', date: 'Februar 2026', stars: 5, text: 'Širi profil letvice pravi duže i dublje senke. Siva boja uz betonski pod je savršena industrijska kombinacija.' }
    ]},
    69: { total: 8, fiveS: 7, fourS: 1, comments: [
      { name: 'Nemanja S.', city: 'Kotor', date: 'Februar 2026', stars: 5, text: 'MW018 Bež Lanen letvice su tople i harmonične. Ritam letvica stvara smirujući efekat – soba djeluje uređeno i opuštajuće.' },
      { name: 'Vesna P.', city: 'Herceg Novi', date: 'Januar 2026', stars: 5, text: 'Bež u 3D formatu je nevjerovatno toplo i prijatno. Kombinovala sa drvenim detaljima i biljkama – rezultat je kao iz Instagram profila.' }
    ]},
    70: { total: 7, fiveS: 6, fourS: 1, comments: [
      { name: 'Srđan K.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'SP052 Topla Bela sa drvenim efektom je savršena kombinacija – ni čisto drvo ni čista bela. Daje prostoru toplinu i modernost istovremeno.' },
      { name: 'Milica T.', city: 'Bar', date: 'Januar 2026', stars: 4, text: 'Zanimljiva tekstura bele s drvenim šarama. Širi profil 170mm pravi jače senke i efekat je impresivan uz bočno osvjetljenje.' }
    ]},
    71: { total: 10, fiveS: 9, fourS: 1, comments: [
      { name: 'Zoran M.', city: 'Nikšić', date: 'Mart 2026', stars: 5, text: 'CS022 Betonski Sivi je savršen za industrijski enterijer. Betonska tekstura na letvici je toliko autentična da moraš prstom provjeriti da li je beton.' },
      { name: 'Aleksandra J.', city: 'Budva', date: 'Februar 2026', stars: 5, text: 'Beton + 3D reljef je ubojita kombinacija. Dnevna soba je dobila karakter industrijske loft arhitekture za mali novac.' }
    ]},
    72: { total: 8, fiveS: 7, fourS: 1, comments: [
      { name: 'Rade B.', city: 'Tivat', date: 'Februar 2026', stars: 5, text: 'CS013 Hladno Siva je precizna i oštra – idealna za moderne kancelarije i poslovne prostore. Daje ozbiljnost svakom enterijeru.' },
      { name: 'Bojana S.', city: 'Podgorica', date: 'Januar 2026', stars: 5, text: 'Hladna siva letvica je neutralna i elegantna. Lako se kombinuje sa svim bojama nameštaja. Jako zadovoljna izborom.' }
    ]},
    73: { total: 6, fiveS: 5, fourS: 1, comments: [
      { name: 'Marija V.', city: 'Bar', date: 'Mart 2026', stars: 5, text: 'JM001 Teksturisana verzija je jača od obične – naglašeni reljef pravi dublje senke i zid ima pravu trodimenzionalnu dubinu.' },
      { name: 'Stefan Đ.', city: 'Cetinje', date: 'Februar 2026', stars: 4, text: 'Razlika između obične i teksturisane verzije je primetna. Ova teksturisana je za one koji žele maksimalan 3D efekat.' }
    ]},
    74: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Predrag N.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Tamna Espresso letvica je dramatična i luksuzna. Pod usmjerenim svjetlom senke između letvica su nevjerovatno duboke i upečatljive.' },
      { name: 'Tatjana K.', city: 'Kotor', date: 'Januar 2026', stars: 5, text: 'Espresso tamna nijansa uz zlatne detalje je klasična luksuzna kombinacija. Trpezarija je dobila novi identitet.' }
    ]},
    75: { total: 11, fiveS: 10, fourS: 1, comments: [
      { name: 'Dejan M.', city: 'Budva', date: 'Mart 2026', stars: 5, text: 'Srednji Hrast 021 letvice su najprirodnija drvenasta opcija u ponudi. Autentična tekstura hrasta u 3D formatu je prosto prelepa.' },
      { name: 'Gordana R.', city: 'Herceg Novi', date: 'Februar 2026', stars: 5, text: 'Klasičan hrast u modernom 3D formatu – savršena kombinacija prirodnog i savremenog. Dnevna soba je topla i prijatna.' }
    ]},
    76: { total: 7, fiveS: 6, fourS: 1, comments: [
      { name: 'Miloš J.', city: 'Nikšić', date: 'Februar 2026', stars: 5, text: 'Uski 140mm profil hrasta pravi finiji, gušći ritam letvica. Efekat je delikatan i elegantan – savršeno za manje prostore.' },
      { name: 'Sandra L.', city: 'Podgorica', date: 'Januar 2026', stars: 5, text: 'Uži profil mi je drao prednost jer daje više senki po kvadratu. Zid izgleda bogato i detaljno. Odlično rešenje.' }
    ]},
    77: { total: 8, fiveS: 7, fourS: 1, comments: [
      { name: 'Jovan P.', city: 'Bar', date: 'Mart 2026', stars: 5, text: 'Topli Mahagonija letvice su raskošne i svečane. Duboka smeđe-crvena nijansa uz 3D senke daje prostoru gotovo kraljevski karakter.' },
      { name: 'Nevena T.', city: 'Tivat', date: 'Februar 2026', stars: 5, text: 'Mahagonija u 3D letvicama je kombinacija kakvu nisam videla. Postavljeno u biblioteci – efekat je kao u starom engleskom domu.' }
    ]},
    78: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Lazar B.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Svetli Javor 016 letvice su lagane i prozračne. Svetla nijansa osvaja prostor i čini ga vizualno većim – idealno za manje sobe.' },
      { name: 'Ivana M.', city: 'Cetinje', date: 'Januar 2026', stars: 5, text: 'Javor je svetao i energičan. Soba dobija skandinavski karakter koji volim – prirodno, čisto, moderno. Jako zadovoljna.' }
    ]},
    79: { total: 10, fiveS: 9, fourS: 1, comments: [
      { name: 'Nikola F.', city: 'Budva', date: 'Mart 2026', stars: 5, text: 'BW008 Crna Ebonija letvice su najdramatičniji izbor u kolekciji. Pod usmjerenim svjetlom efekat je kao skulptura – apsolutan statement.' },
      { name: 'Milena K.', city: 'Kotor', date: 'Februar 2026', stars: 5, text: 'Crna ebonija na fokalnom zidu je hrabar potez koji se apsolutno isplatilo. Svaki gost ostane bez reči kad uđe u sobu.' }
    ]},
    80: { total: 8, fiveS: 7, fourS: 1, comments: [
      { name: 'Aleksandar V.', city: 'Podgorica', date: 'Februar 2026', stars: 5, text: 'BW224 Antracit letvice su između crne i sive – savršena nijansa koja daje snagu bez agresivnosti. Moderna i timeless kombinacija.' },
      { name: 'Jelena Đ.', city: 'Nikšić', date: 'Januar 2026', stars: 5, text: 'Antracit uz beli nameštaj i zlatne detalje je kombinacija koja ne može da promaši. 3D efekat daje zidu pravi karakter.' }
    ]},
    81: { total: 7, fiveS: 6, fourS: 1, comments: [
      { name: 'Bojan N.', city: 'Herceg Novi', date: 'Mart 2026', stars: 5, text: 'BW809 Tamni Grafit sa metalnim podtonom je jedinstven – blago metalično blistanje uz 3D senke pravi futuristički efekat.' },
      { name: 'Sanja M.', city: 'Bar', date: 'Februar 2026', stars: 4, text: 'Metalni podton grafita je nešto posebno. Izgleda drugačije pod različitim svjetlom – uvijek nov i zanimljiv. Pravi dizajnerski komad.' }
    ]},
    82: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Dragan J.', city: 'Tivat', date: 'Mart 2026', stars: 5, text: 'BW229 Tamni Škriljevac ima hladnu kamenu teksturu koja djeluje veoma skupo. Kupatilo uz ovu letvicu izgleda kao visokoklasni hotel.' },
      { name: 'Katarina S.', city: 'Podgorica', date: 'Januar 2026', stars: 5, text: 'Škriljevac tekstura u 3D formatu je kombinacija dva materijala u jednom. Zid djeluje kao pravi kamen a montaža je bila trivijalna.' }
    ]},
    83: { total: 12, fiveS: 11, fourS: 1, comments: [
      { name: 'Marko B.', city: 'Budva', date: 'Mart 2026', stars: 5, text: 'Topli Hrast 003 letvice su klasika koja ne može da pogreši. Topla smeđa uz 3D reljef daje prostoru prirodnu energiju i karakter.' },
      { name: 'Vesna R.', city: 'Bar', date: 'Februar 2026', stars: 5, text: 'Hrast letvice su moj prvi izbor za dnevnu sobu. Kombinovala sa kožnim nameštajem i biljkama – savršen prirodni enterijer.' }
    ]},
    84: { total: 8, fiveS: 7, fourS: 1, comments: [
      { name: 'Zoran P.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: '3D02 Siva Mineralna ima gušći 3D efekat od standardnih letvica. Mineralna tekstura je neobična i upečatljiva – pravi razgovor starter.' },
      { name: 'Ana B.', city: 'Nikšić', date: 'Februar 2026', stars: 5, text: 'Mineralni 3D profil je za one koji žele više od klasičnih letvica. Efekat na fokalnom zidu je fantastičan pod bilo kojim osvjetljenjem.' }
    ]},
    85: { total: 7, fiveS: 6, fourS: 1, comments: [
      { name: 'Miloš T.', city: 'Kotor', date: 'Februar 2026', stars: 5, text: '3D05 Bela Struktura je skulpturalni komad na zidu. Duboke senke između reljefa pod usmjerenim svjetlom prave pravi umetnički efekat.' },
      { name: 'Dragana V.', city: 'Budva', date: 'Januar 2026', stars: 5, text: 'Izrazitiji reljef 3D05 profila je za hrabre – efekat je snažan i dramatičan. Fotke ne mogu da prenesu koliko lepo izgleda uživo.' }
    ]},
    86: { total: 6, fiveS: 5, fourS: 1, comments: [
      { name: 'Nemanja B.', city: 'Herceg Novi', date: 'Mart 2026', stars: 5, text: '3D09X Siva Struktura je urbana i moćna. Isti profil kao bela verzija ali siva nijansa daje industrijskiji i agresivniji karakter zidu.' },
      { name: 'Tijana S.', city: 'Tivat', date: 'Februar 2026', stars: 4, text: 'Siva sa dubokim reljefom je odlična za moderne kancelarije. Klijenti su odmah primijetili promjenu – prostor djeluje ozbiljno i savremeno.' }
    ]},
    87: { total: 10, fiveS: 10, fourS: 0, comments: [
      { name: 'Luka Đ.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: '3D07X Premium Crna je apsolutni vrhunac kolekcije. Najdublji 3D reljef uz crnu boju je nešto što se mora videti uživo da bi se razumelo.' },
      { name: 'Kristina N.', city: 'Budva', date: 'Mart 2026', stars: 5, text: 'Premium crna letvica uz dramatično usmjereno svjetlo pravi efekat koji ostavlja bez daha. Investicija koja se vraća svakog dana.' }
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

    // ── Kožni paneli ──
    106: { total: 10, fiveS: 9, fourS: 1, comments: [
      { name: 'Ivana Č.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Bordo Crvena je dublja i toplija od onoga što sam očekivala – bogata cigla-crvena s kožnim reljefom mijenja ton na različitom svjetlu. Postavljeno u restoranu iza šanka i gosti uvijek primijete i fotografišu.' },
      { name: 'Ognjen M.', city: 'Tivat', date: 'Mart 2026', stars: 5, text: 'Postavio u privatnom kabinetу – topla bordo nijansa s kožnom texturom daje prostoriji karakter i toplinu. Ni previše agresivna ni tiha. Klijenti odmah komentarišu koliko je prostor autentičan i jedinstven.' }
    ]},
    107: { total: 7, fiveS: 6, fourS: 1, comments: [
      { name: 'Lena V.', city: 'Kotor', date: 'Mart 2026', stars: 5, text: 'Hermès Narandžasta je hrabar izbor koji se stoprocentno isplati. Puna saturirana narandžasta s kožnim reljefom daje prostoru energiju i karakter. Kolege u uredu odmah su promijenile stav prema prostoru.' },
      { name: 'Dejan T.', city: 'Bar', date: 'Februar 2026', stars: 4, text: 'Za bar koji smo otvarali – savršena. Narandžasta s kožnom granulacijom izgleda skupo i retro-chic. Uz crne stolice i drvene površine je kombinacija koja gosti fotografišu i dijele na mrežama.' }
    ]},
    108: { total: 7, fiveS: 6, fourS: 1, comments: [
      { name: 'Maja S.', city: 'Herceg Novi', date: 'Mart 2026', stars: 5, text: 'Ledena Siva u određenom svjetlu dobiva blag sedefni sjaj – fini kožni reljef je izrazito vidljiv i daje zidu gotovo kristalnu dubinu. Uz bijeli nameštaj i zlatne detalje izgleda prefinijeno i luksuzno.' },
      { name: 'Petar A.', city: 'Podgorica', date: 'Mart 2026', stars: 4, text: 'Recepcija firme s Ledenom Sivom odmah je dobila premium izgled. Hladna siva s kožnom teksturom komunicira profesionalnost bez pretjerivanja. Posjetioci uvijek pitaju koji materijal je na zidu.' }
    ]},
    109: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Stefan L.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Tamni Antracit nije crna – to je tamna ugljeno-siva koja se mijenja s kutom svjetlosti. Kožni reljef pod direktnim svjetlom postaje izrazito vidljiv i panel dobija gotovo granitnu dubinu. Fascinantno.' },
      { name: 'Milica R.', city: 'Budva', date: 'Februar 2026', stars: 5, text: 'Antracit kožni panel u kućnom uredu – tačno ono za fokus i ozbiljnost. Tamna ali ne depresivna, granulacija kože daje živost zidu. Savršena kombinacija s drvenim stolom i zelenim biljkama.' }
    ]},

    // ── Classic paneli ──
    110: { total: 11, fiveS: 10, fourS: 1, comments: [
      { name: 'Tamara J.', city: 'Budva', date: 'Mart 2026', stars: 5, text: 'Terrazzo panel je nešto što nisam vidio ni u jednom salonu – šareni terrazzo uzorak daje prostoru radost i originalnost. Postavljeno u kafiću i gosti ga fotografišu i dijele na Instagramu. Pravi magnet za pažnju.' },
      { name: 'Kristina R.', city: 'Podgorica', date: 'Februar 2026', stars: 5, text: 'U dječijoj sobi sam tražila nešto veselo ali ne djetinjasto. Terrazzo s raznolikm granulama je savršen – šaren, moderan, jedinstven. Djeca ga obožavaju a i ja sam presretna izborom.' }
    ]},
    111: { total: 9, fiveS: 8, fourS: 1, comments: [
      { name: 'Miloš D.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Soft Beige je panel koji ne griješi – topla, meka bež nijansa ide uz sve stilove i materijale. Postavljeno u dnevnoj sobi i prostorija je dobila onaj neutralan ali ugodan karakter koji sam uvijek tražio.' },
      { name: 'Jelena N.', city: 'Nikšić', date: 'Februar 2026', stars: 5, text: 'Renovirali smo boutique hotel i Soft Beige je bio naš izbor za sve sobe. Ta topla bež nijansa opušta i grije prostor bez pretjerivanja. Gosti pišu u recenzijama da se osjete kao kod kuće – panel sigurno doprinosi.' }
    ]},
    112: { total: 12, fiveS: 11, fourS: 1, comments: [
      { name: 'Ana V.', city: 'Kotor', date: 'Mart 2026', stars: 5, text: 'Pure White je savršen za minimaliste – čista bijela koja nije hladna nego živa i dinamična zahvaljujući finom reljefu površine. Postavljeno u studiju za fotografisanje i postalo je omiljena pozadina za sve snimke.' },
      { name: 'Bojan S.', city: 'Tivat', date: 'Januar 2026', stars: 5, text: 'U wellness centru smo koristili Pure White za sve tretman sobe. Bijela čistoća s blagom teksturom daje mir i sterilnost kakvu klijenti traže. Kombinacija s drvenim detaljima i zelenilom je savršena.' }
    ]},
    113: { total: 13, fiveS: 12, fourS: 1, comments: [
      { name: 'Marko Đ.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Midnight Black je apsolutni statement komad – mat crna bez ikakvog sjaja apsorbira svjetlo i daje prostoru dramatičnost i snagu. Postavljeno iza kreveta i spavaća soba izgleda kao suite u boutique hotelu.' },
      { name: 'Sonja K.', city: 'Budva', date: 'Februar 2026', stars: 5, text: 'U kućnom bioskopu sam tražila mat crnu bez refleksija projektora – Midnight Black je bio savršen. Ni traga od sjaja, čisto duboko crno. Kino atmosfera kakvu nisam mogla postići nijednom drugom bojom.' }
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

  const waLink = `https://wa.me/38269105222?text=Zdravo%2C%20zanima%20me%20panel%20${encodeURIComponent(product.name)}`;

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
            ${product.features.map(f => `<li><i class="fas fa-check"></i>${f}</li>`).join('')}
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

    if (letvicaDims) {
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
  const img = document.getElementById('gallery-main-img');
  img.src = src;
  img.onclick = () => openImageLightbox(src, img.alt);
  document.querySelectorAll('.gallery-thumb').forEach(t => t.classList.remove('active'));
  thumb.classList.add('active');
}

function openImageLightbox(src, name) {
  const lb = document.createElement('div');
  lb.id = 'img-lightbox';
  lb.style.cssText = `position:fixed;inset:0;background:rgba(0,0,0,0.92);z-index:99999;
    display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px;`;

  const btnClose = `<button onclick="document.getElementById('img-lightbox').remove()"
    style="display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,0.15);
    color:#fff;padding:10px 20px;border-radius:8px;font-size:14px;font-weight:600;border:none;cursor:pointer;">
    <i class="fas fa-times"></i> Zatvori
  </button>`;

  // Try Web Share API (native share sheet → user picks "Save to Photos")
  const isMobile = /iPhone|iPad|Android/i.test(navigator.userAgent);
  const canShare = isMobile && navigator.canShare;

  const saveBtn = canShare
    ? `<button id="lb-save-btn"
        style="display:inline-flex;align-items:center;gap:8px;background:#c9a86c;color:#fff;
        padding:10px 20px;border-radius:8px;font-size:14px;font-weight:600;border:none;cursor:pointer;">
        <i class="fas fa-image"></i> Sačuvaj u Galeriju
      </button>`
    : `<a href="${src}" download="${name.replace(/\s+/g,'-')}.jpg"
        style="display:inline-flex;align-items:center;gap:8px;background:#c9a86c;color:#fff;
        padding:10px 20px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;">
        <i class="fas fa-download"></i> Preuzmi sliku
      </a>`;

  lb.innerHTML = `
    <div style="max-width:95vw;max-height:90vh;display:flex;flex-direction:column;align-items:center;gap:14px;">
      <img src="${src}" alt="${name}"
        style="max-width:100%;max-height:78vh;object-fit:contain;border-radius:8px;box-shadow:0 0 60px rgba(0,0,0,0.6);">
      <div style="display:flex;gap:12px;flex-wrap:wrap;justify-content:center;">
        ${saveBtn}
        ${btnClose}
      </div>
      <span style="font-size:12px;color:rgba(255,255,255,0.35);">Tapni van slike ili pritisni ESC za zatvaranje</span>
    </div>
  `;

  lb.addEventListener('click', e => { if (e.target === lb) lb.remove(); });
  document.addEventListener('keydown', function esc(e) {
    if (e.key === 'Escape') { lb.remove(); document.removeEventListener('keydown', esc); }
  });
  document.body.appendChild(lb);

  // Attach Web Share handler after DOM insert
  if (canShare) {
    document.getElementById('lb-save-btn').addEventListener('click', async () => {
      try {
        const res = await fetch(src);
        const blob = await res.blob();
        const file = new File([blob], `${name.replace(/\s+/g,'-')}.jpg`, { type: blob.type });
        if (navigator.canShare({ files: [file] })) {
          await navigator.share({ files: [file], title: name });
        } else {
          // Fallback: open in new tab so user can long-press → Save
          window.open(src, '_blank');
        }
      } catch {
        window.open(src, '_blank');
      }
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
    el.style.opacity = '0';
    el.style.transform = 'translateY(30px)';
    el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
    observer.observe(el);
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
