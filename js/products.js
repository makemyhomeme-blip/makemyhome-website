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

  // Update page title
  document.title = `${product.name} | Make My Home`;

  // Breadcrumb
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

  // Info
  const info = document.getElementById('product-info-content');
  if (info) {
    // Ideal for icons map
    const roomIcons = {
      'Dnevna soba': 'fas fa-couch',
      'Spavaća soba': 'fas fa-bed',
      'Kuhinja': 'fas fa-utensils',
      'Kupaonica': 'fas fa-bath',
      'Hodnik': 'fas fa-door-open',
      'Ured': 'fas fa-briefcase',
      'Restoran': 'fas fa-concierge-bell',
      'Bar/kafić': 'fas fa-coffee',
      'Kućni bioskop': 'fas fa-film',
      'Hotel': 'fas fa-hotel',
      'VIP lounge': 'fas fa-glass-cheers',
      'Biblioteka': 'fas fa-book'
    };

    const idealForHtml = (product.idealFor || []).map(room => `
      <div class="ideal-room">
        <i class="${roomIcons[room] || 'fas fa-home'}"></i>
        <span>${room}</span>
      </div>`).join('');

    const styleMatchHtml = (product.styleMatch || []).map(s =>
      `<span class="style-badge">${s}</span>`).join('');

    const highlightHtml = product.highlight
      ? `<div class="product-highlight"><i class="fas fa-quote-left"></i> ${product.highlight}</div>`
      : '';

    const calcHtml = `
      <div class="coverage-calc">
        <div class="calc-title"><i class="fas fa-ruler-combined"></i> Kalkulator – koliko panela trebaš?</div>
        <div class="calc-fields">
          <div class="calc-field">
            <label>Širina zida</label>
            <div class="calc-stepper">
              <button type="button" onclick="stepCalc('wall-w',-0.5)">−</button>
              <input type="number" id="wall-w" value="4" min="0.5" max="50" step="0.5" oninput="calcPanels()">
              <span class="calc-unit">m</span>
              <button type="button" onclick="stepCalc('wall-w',0.5)">+</button>
            </div>
          </div>
          <div class="calc-field">
            <label>Visina zida</label>
            <div class="calc-stepper">
              <button type="button" onclick="stepCalc('wall-h',-0.1)">−</button>
              <input type="number" id="wall-h" value="2.8" min="0.5" max="10" step="0.1" oninput="calcPanels()">
              <span class="calc-unit">m</span>
              <button type="button" onclick="stepCalc('wall-h',0.1)">+</button>
            </div>
          </div>
        </div>
        <div class="calc-result" id="calc-result">
          Za zid od <strong>11.2 m²</strong> trebaš <strong>4 panela</strong>
        </div>
      </div>`;

    info.innerHTML = `
      <div class="product-category">${categoryName}</div>
      <h1 class="product-name">${product.name}</h1>
      <div class="product-rating">
        <span class="rating-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i></span>
        <span class="rating-count">(4.8) · Odlično</span>
      </div>

      ${highlightHtml}

      <div class="product-price-lg">${product.price} € <span>/ ${product.unit}</span></div>
      <div class="price-note"><i class="fas fa-info-circle"></i> Cijena po komadu (panel 280×122cm = 3,42 m²). PDV uključen.</div>

      <div class="product-short-desc">${product.description}</div>

      ${idealForHtml ? `<div class="ideal-for-label">Idealno za:</div><div class="ideal-for-grid">${idealForHtml}</div>` : ''}

      ${styleMatchHtml ? `<div class="style-match-row"><span class="style-match-label">Stil:</span>${styleMatchHtml}</div>` : ''}

      <ul class="product-features-list">
        ${product.features.map(f => `<li><i class="fas fa-check"></i>${f}</li>`).join('')}
      </ul>

      ${calcHtml}

      <div class="product-detail-actions">
        <button class="btn btn-primary btn-lg" onclick="inquireProduct('${product.name}')">
          <i class="fas fa-envelope"></i> Pošalji Upit
        </button>
        <a href="https://wa.me/38269105222?text=Zdravo%2C%20zanima%20me%20panel%20${encodeURIComponent(product.name)}" target="_blank" class="btn btn-dark btn-lg">
          <i class="fab fa-whatsapp"></i> WhatsApp
        </a>
      </div>

      <div class="product-trust-row">
        <div class="trust-item"><i class="fas fa-truck"></i><span>Dostava Crna Gora</span></div>
        <div class="trust-item"><i class="fas fa-tools"></i><span>Savjeti za montažu</span></div>
        <div class="trust-item"><i class="fas fa-undo"></i><span>Zamjena u 7 dana</span></div>
      </div>
    `;
  }

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
    const panels = Math.ceil(area / 3.416);
    const res = document.getElementById('calc-result');
    if (res && area > 0) {
      res.innerHTML = `Za zid ${w} × ${h} m = <strong>${area.toFixed(1)} m²</strong> → trebaš <strong>${panels} ${panels === 1 ? 'panel' : panels < 5 ? 'panela' : 'panela'}</strong>`;
    }
  };

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
