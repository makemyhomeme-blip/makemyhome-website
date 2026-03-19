/* ===================================================
   MAKE MY HOME - Products JavaScript
   =================================================== */

let currentFilter = 'all';

let allProducts = [];
let allCategories = [];

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

  return `
    <article class="product-card animate-on-scroll" data-category="${product.category}" data-id="${product.id}">
      <div class="product-img">
        ${imgContent}
        ${badge}
        <div class="product-actions">
          <button class="btn-action" title="Brzi pregled" onclick="openProductModal(${product.id})">
            <i class="fas fa-eye"></i>
          </button>
          <button class="btn-action" title="Pošalji upit" onclick="inquireProduct('${product.name}')">
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
            ${product.price} € <span>/ ${product.unit}</span>
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

function buildCatMap() {
  const catMap = {};
  allProducts.forEach(p => {
    if (!catMap[p.category]) {
      const catData = allCategories.find(c => c.id === p.category);
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
  const cats = Object.values(catMap);

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

  container.innerHTML = allCategories.map(cat => `
    <a href="products.html?cat=${cat.id}" class="category-card animate-on-scroll">
      <div class="category-img">
        <img src="${cat.image}" alt="${cat.name}"
          onerror="this.parentElement.innerHTML='<span class=\'category-img-placeholder\'><i class=\'${cat.icon}\'></i></span>'"
          loading="lazy">
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
  `).join('');

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

  // Compute m² per unit from features string
  function getCoveragePerUnit() {
    if (product.unit === 'm²') return 1;
    for (const f of (product.features || [])) {
      const m = f.match(/\((\d+[.,]\d+)\s*m²/);
      if (m) return parseFloat(m[1].replace(',', '.'));
    }
    return 3.416;
  }
  const coveragePerUnit = getCoveragePerUnit();

  // Reviews data – per product ID, with total count and 2 visible comments
  const reviewsData = {
    4:  { total: 7,  fiveS: 6, fourS: 1, comments: [
      { name: 'Aleksandar M.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Postavio u kućni studio – razlika u zvuku je odmah primjetna. Panel je sivi, diskretnog dizajna, odlično se uklapa.' },
      { name: 'Tijana R.', city: 'Nikšić', date: 'Februar 2026', stars: 5, text: 'Kupila za dnevnu sobu. Siva boja je neutralna, pristaje uz sve. Montaža je bila super jednostavna.' }
    ]},
    18: { total: 15, fiveS: 13, fourS: 2, comments: [
      { name: 'Marko T.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Topli Tik daje savršenu toplinu prostoriji. Postavio iza TV-a u dnevnoj sobi i svi gosti pitaju odakle mi ideja – izgleda skupo a cijena je odlična.' },
      { name: 'Jovana M.', city: 'Bar', date: 'Februar 2026', stars: 5, text: 'Boja tika je tačno kao na slikama, topla i prirodna. Postavljanje sa silikonom trajalo svega sat vremena. Presretna sam!' }
    ]},
    19: { total: 10, fiveS: 8, fourS: 2, comments: [
      { name: 'Stefan K.', city: 'Budva', date: 'Mart 2026', stars: 5, text: 'Bijeli Jasen je pravi izbor za moderan, svetao stan. Osvjetljava prostor i čini sobu vizualno većom. Preporučujem svima!' },
      { name: 'Ana P.', city: 'Podgorica', date: 'Januar 2026', stars: 4, text: 'Jako lijepa bijela nijansa, ne previše bijela nego prirodna. Jedino bih voljela malo veće dimenzije panela.' }
    ]},
    20: { total: 12, fiveS: 11, fourS: 1, comments: [
      { name: 'Nikola V.', city: 'Podgorica', date: 'Februar 2026', stars: 5, text: 'Crni Antracit je brutalno lijep. Cijela zida u spavaćoj sobi – izgleda kao luksuzni hotel. Montaža perfektna.' },
      { name: 'Milica Đ.', city: 'Herceg Novi', date: 'Januar 2026', stars: 5, text: 'Tamna boja daje dramatičnost prostoru. Kombinovala sa zlatnim detaljima i rezultat je fenomenalan!' }
    ]},
    21: { total: 6,  fiveS: 5, fourS: 1, comments: [
      { name: 'Petar S.', city: 'Cetinje', date: 'Mart 2026', stars: 5, text: 'Srebrno siva je savršena nijansa – ni pretopla ni prehladna. Ide uz sve stilove namještaja. Jako zadovoljan.' },
      { name: 'Jelena B.', city: 'Podgorica', date: 'Februar 2026', stars: 5, text: 'Odabrala za ured i savršeno odgovara poslovnom prostoru. Daje ozbiljnost i eleganciju.' }
    ]},
    22: { total: 9,  fiveS: 8, fourS: 1, comments: [
      { name: 'Dragan L.', city: 'Bar', date: 'Mart 2026', stars: 5, text: 'Pješčani Hrast je toplih tonova – savršen za stan koji želi prirodan dodir. Postavio u hodniku i odmah promjena.' },
      { name: 'Sanja N.', city: 'Tivat', date: 'Februar 2026', stars: 5, text: 'Boja hrasta je autentična, ne plastičan izgled. Kvalitet je iznenađujuće dobar za ovu cijenu!' }
    ]},
    23: { total: 11, fiveS: 10, fourS: 1, comments: [
      { name: 'Igor M.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Dimljeni Orah je moja omiljena nijansa. Daje prostoriji karakter i toplinu istovremeno. Postavio u trpezariji.' },
      { name: 'Vesna K.', city: 'Kotor', date: 'Februar 2026', stars: 5, text: 'Tamni orah je elegantan i moderan. Kombinuje se lijepo i sa svjetlim i sa tamnim namještajem.' }
    ]},
    24: { total: 8,  fiveS: 7, fourS: 1, comments: [
      { name: 'Luka P.', city: 'Podgorica', date: 'Februar 2026', stars: 5, text: 'Prirodni Hrast izgleda kao pravo drvo. Postavio u dnevnoj sobi iza sofe i efekt je nevjerovatan – kao da je sobi duša vraćena.' },
      { name: 'Ivana C.', city: 'Budva', date: 'Januar 2026', stars: 5, text: 'Boja je autentično drvenasta, miris je čak i kao pravo drvo. Izuzetno zadovoljna kvalitetom!' }
    ]},
    25: { total: 5,  fiveS: 4, fourS: 1, comments: [
      { name: 'Radovan T.', city: 'Nikšić', date: 'Mart 2026', stars: 5, text: 'Divlji Orah ima specifičnu teksturu koja privlači pogled. Jedinstven izgled, svaka daska drugačija – baš kao pravo drvo.' },
      { name: 'Nataša B.', city: 'Podgorica', date: 'Februar 2026', stars: 4, text: 'Lijepa tamno-smeđa nijansa, odlično ide uz rustičan enterijer. Montaža jednostavna, preporučujem.' }
    ]},
    26: { total: 13, fiveS: 12, fourS: 1, comments: [
      { name: 'Bojan S.', city: 'Podgorica', date: 'Mart 2026', stars: 5, text: 'Espresso Orah je savršen spoj tamnog i toplog tona. Postavio u kancelariji i kolege svakodnevno komentarišu koliko je prostorija lijepa.' },
      { name: 'Maja F.', city: 'Bar', date: 'Januar 2026', stars: 5, text: 'Tamna espresso boja daje luksuz bez prevelike cijene. Panel je čvrst i lako se čisti. Apsolutno preporučujem!' }
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
    <div class="product-rating">
      <span class="rating-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i></span>
      <span class="rating-count">(4.8) · Odlično</span>
    </div>

    <div class="product-price-lg">${product.price} € <span>/ ${product.unit}</span></div>

    <div class="product-short-desc">${product.description}</div>

    <!-- Tab switcher -->
    <div class="pq-tabs">
      <button class="pq-tab active" onclick="switchPqTab('qty', this)">
        <i class="fas fa-list-ol"></i> Unesi Količinu
      </button>
      <button class="pq-tab" onclick="switchPqTab('calc', this)">
        <i class="fas fa-calculator"></i> Kalkulator m²
      </button>
    </div>

    <!-- Tab: Količina -->
    <div class="pq-panel" id="pq-qty">
      <div class="pq-stepper">
        <button type="button" class="pq-step-btn" onclick="stepPqQty(-1)">−</button>
        <span class="pq-step-val" id="pq-qty-val">1</span>
        <button type="button" class="pq-step-btn" onclick="stepPqQty(1)">+</button>
      </div>
      <div class="pq-m2-badge" id="pq-m2-badge">
        1 ${product.unit === 'm²' ? 'm²' : 'kom'} = ${coveragePerUnit.toFixed(2).replace('.', ',')} m²
      </div>
    </div>

    <!-- Tab: Kalkulator -->
    <div class="pq-panel" id="pq-calc" style="display:none;">
      <div class="pq-calc-inner">
        <div class="pq-calc-field">
          <label>Širina zida</label>
          <div class="pq-calc-stepper">
            <button type="button" onclick="stepCalc('wall-w',-0.5)">−</button>
            <input type="number" id="wall-w" value="4" min="0.5" max="50" step="0.5" oninput="calcPanels()">
            <span class="pq-calc-unit">m</span>
            <button type="button" onclick="stepCalc('wall-w',0.5)">+</button>
          </div>
        </div>
        <div class="pq-calc-field">
          <label>Visina zida</label>
          <div class="pq-calc-stepper">
            <button type="button" onclick="stepCalc('wall-h',-0.1)">−</button>
            <input type="number" id="wall-h" value="2.8" min="0.5" max="10" step="0.1" oninput="calcPanels()">
            <span class="pq-calc-unit">m</span>
            <button type="button" onclick="stepCalc('wall-h',0.1)">+</button>
          </div>
        </div>
      </div>
      <div class="pq-calc-result" id="calc-result">
        Za zid 4 × 2,8 m = <strong>11,2 m²</strong> → trebaš <strong>4 komada</strong>
      </div>
    </div>

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
    const count = Math.ceil(area / coveragePerUnit);
    const res = document.getElementById('calc-result');
    if (res && area > 0) {
      const label = product.unit === 'm²' ? 'm²' : count === 1 ? 'komad' : 'komada';
      res.innerHTML = `Za zid ${w} × ${h} m = <strong>${area.toFixed(1).replace('.',',')} m²</strong> → trebaš <strong>${count} ${label}</strong>`;
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
