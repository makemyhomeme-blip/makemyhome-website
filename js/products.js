/* ===================================================
   MAKE MY HOME - Products JavaScript
   =================================================== */

let currentFilter = 'all';

// ===== PODACI UGRAĐENI DIREKTNO (rade i bez servera) =====
const PRODUCTS_DATA = [
  {"id":1,"name":"Bambus Panel Drveni","category":"bambus-drveni","price":"0.00","unit":"m²","description":"Dekorativni bambus panel sa drvenom strukturom. Prirodan i elegantan izgled za svaki prostor.","features":["Prirodni bambus","Jednostavna montaža","Različite nijanse"],"image":"","badge":null,"inStock":true,"featured":true},
  {"id":2,"name":"Bambus Panel Tekstilni","category":"bambus-tekstilni","price":"0.00","unit":"m²","description":"Bambus panel sa tekstilnom površinom. Mekana tekstura koja unosi toplinu u prostor.","features":["Tekstilna površina","Zvučna apsorpcija","Elegantni dizajn"],"image":"","badge":null,"inStock":true,"featured":true},
  {"id":3,"name":"Bambus Panel Mermerni","category":"bambus-mermerni","price":"0.00","unit":"m²","description":"Bambus panel sa mermernim uzorkom. Luksuzni izgled bez visoke cijene pravog mermera.","features":["Mermerni uzorak","Vodootporan","Laka montaža"],"image":"","badge":null,"inStock":true,"featured":true},
  {"id":4,"name":"Bambus Panel Metalni","category":"bambus-metalni","price":"0.00","unit":"m²","description":"Bambus panel sa metalnim završetkom. Moderan industrijski stil za savremene enterijer projekte.","features":["Metalni završetak","Moderan dizajn","Otporan na ogrebotine"],"image":"","badge":null,"inStock":true,"featured":false},
  {"id":5,"name":"Bambus Panel Kožni","category":"bambus-kozni","price":"0.00","unit":"m²","description":"Bambus panel sa kožnom površinom. Luksuzno rješenje za ekskluzivne prostore.","features":["Kožna površina","Luksuzni izgled","Premium kvalitet"],"image":"","badge":"Novo","inStock":true,"featured":false},
  {"id":6,"name":"3D Letvice","category":"3d-letvice","price":"0.00","unit":"m²","description":"Elegantne 3D letvice za moderne i luksuzne enterijer projekte.","features":["Prirodno drvo","Jednostavna montaža","Razne boje"],"image":"","badge":null,"inStock":true,"featured":true},
  {"id":7,"name":"Akustični Panel","category":"akusticni-paneli","price":"0.00","unit":"m²","description":"Vrhunska zvučna izolacija uz dekorativan izgled.","features":["Zvučna izolacija","Ekološki materijal","Različite boje"],"image":"","badge":null,"inStock":true,"featured":true},
  {"id":8,"name":"Aluminijum Lajsne","category":"aluminijum-lajsne","price":"0.00","unit":"kom","description":"Aluminijumske lajsne za završnu obradu i elegantne detalje.","features":["Aluminijum","Razne dimenzije","Laka montaža"],"image":"","badge":null,"inStock":true,"featured":false}
];

const CATEGORIES_DATA = [
  {"id":"bambus-drveni","name":"Drveni","icon":"fas fa-leaf","description":"Bambus paneli sa drvenom strukturom","color":"#7a9e6e"},
  {"id":"bambus-tekstilni","name":"Tekstilni","icon":"fas fa-leaf","description":"Bambus paneli sa tekstilnom površinom","color":"#7a9e6e"},
  {"id":"bambus-mermerni","name":"Mermerni","icon":"fas fa-leaf","description":"Bambus paneli sa mermernim uzorkom","color":"#7a9e6e"},
  {"id":"bambus-metalni","name":"Metalni","icon":"fas fa-leaf","description":"Bambus paneli sa metalnim završetkom","color":"#7a9e6e"},
  {"id":"bambus-kozni","name":"Kožni","icon":"fas fa-leaf","description":"Bambus paneli sa kožnom površinom","color":"#7a9e6e"},
  {"id":"3d-letvice","name":"3D Letvice","icon":"fas fa-grip-lines","description":"Elegantne 3D letvice za moderne enterijer projekte","color":"#8B6914"},
  {"id":"akusticni-paneli","name":"Akustični Paneli","icon":"fas fa-volume-mute","description":"Vrhunska zvučna izolacija uz dekorativan izgled","color":"#2c2c2c"},
  {"id":"aluminijum-lajsne","name":"Aluminijum Lajsne","icon":"fas fa-minus","description":"Aluminijumske lajsne za završnu obradu","color":"#888888"}
];

let allProducts = PRODUCTS_DATA;
let allCategories = CATEGORIES_DATA;

// ===== UČITAJ PODATKE (pokušaj fetch, fallback na ugrađene) =====
async function loadData() {
  if (allProducts.length > 0 && allCategories.length > 0) return; // već učitano
  try {
    const [prodRes, catRes] = await Promise.all([
      fetch('data/products.json'),
      fetch('data/categories.json')
    ]);
    allProducts = await prodRes.json();
    allCategories = await catRes.json();
  } catch (e) {
    // Koristimo ugrađene podatke (fallback)
    allProducts = PRODUCTS_DATA;
    allCategories = CATEGORIES_DATA;
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

// ===== RENDERUJ SVE PROIZVODE (Products page) =====
async function renderAllProducts(containerId) {
  const container = document.getElementById(containerId);
  if (!container) return;

  await loadData();
  renderFilters();
  filterAndRender(container);
}

// ===== FILTERI =====
function renderFilters() {
  const filterBar = document.getElementById('filter-bar');
  if (!filterBar) return;

  const allBtn = `<button class="filter-btn active" data-filter="all" onclick="setFilter('all', this)">
    Sve Kategorije
  </button>`;

  const catBtns = allCategories.map(cat =>
    `<button class="filter-btn" data-filter="${cat.id}" onclick="setFilter('${cat.id}', this)">
      ${cat.name}
    </button>`
  ).join('');

  filterBar.innerHTML = allBtn + catBtns + `<span class="products-count" id="products-count"></span>`;
}

function setFilter(filter, btn) {
  currentFilter = filter;
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const container = document.getElementById('products-container');
  if (container) filterAndRender(container);
}

function filterAndRender(container) {
  const filtered = currentFilter === 'all'
    ? allProducts
    : allProducts.filter(p => p.category === currentFilter);

  const countEl = document.getElementById('products-count');
  if (countEl) countEl.textContent = `${filtered.length} proizvod${filtered.length !== 1 ? 'a' : ''}`;

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
  if (galleryMain) {
    galleryMain.innerHTML = `<img src="${product.image}" alt="${product.name}"
      onerror="this.innerHTML='<span class=\'gallery-placeholder\'><i class=\'fas fa-image\'></i></span>'">`;
  }

  // Info
  const info = document.getElementById('product-info-content');
  if (info) {
    info.innerHTML = `
      <div class="product-category">${categoryName}</div>
      <h1 class="product-name">${product.name}</h1>
      <div class="product-rating">
        <span class="rating-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i></span>
        <span class="rating-count">(4.8) · Odlično</span>
      </div>
      <div class="product-price-lg">${product.price} € <span>/ ${product.unit}</span></div>
      <div class="price-note"><i class="fas fa-info-circle"></i> Cijena iskazana po kvadratnom metru. PDV uključen.</div>
      <div class="product-short-desc">${product.description}</div>
      <ul class="product-features-list">
        ${product.features.map(f => `<li><i class="fas fa-check"></i>${f}</li>`).join('')}
      </ul>
      <div class="product-qty">
        <span class="qty-label">Količina:</span>
        <div class="qty-input">
          <button class="qty-btn" onclick="changeQty(-1)">−</button>
          <input type="number" id="qty" class="qty-num" value="1" min="1" max="999">
          <button class="qty-btn" onclick="changeQty(1)">+</button>
        </div>
        <span class="qty-unit">m²</span>
      </div>
      <div class="product-detail-actions">
        <button class="btn btn-primary btn-lg" onclick="inquireProduct('${product.name}')">
          <i class="fas fa-envelope"></i> Pošalji Upit
        </button>
        <a href="contact.html" class="btn btn-dark btn-lg">
          <i class="fas fa-phone"></i> Pozovite Nas
        </a>
      </div>
    `;
  }

  // Related products
  const related = allProducts.filter(p => p.category === product.category && p.id !== id).slice(0, 4);
  const relContainer = document.getElementById('related-products');
  if (relContainer && related.length > 0) {
    relContainer.innerHTML = related.map(p => renderProductCard(p)).join('');
    initAnimations();
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
