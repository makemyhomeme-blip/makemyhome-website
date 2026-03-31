/* ===================================================
   MAKE MY HOME - Glavni JavaScript
   =================================================== */

document.addEventListener('DOMContentLoaded', function () {
  // ===== NAV SINGLE-ROW FIX (injected via JS to bypass CDN cache) =====
  (function(){
    var s = document.createElement('style');
    s.textContent = [
      '@media(min-width:769px){',
      '.header-inner{flex-wrap:nowrap!important;}',
      '.logo{flex-shrink:0!important;max-width:220px;}',
      '.logo-text .name{white-space:nowrap!important;font-size:16px!important;}',
      '.logo-text .tagline{white-space:nowrap!important;font-size:9px!important;letter-spacing:1.5px!important;}',
      '.logo-img{height:44px!important;}',
      '.nav-menu{gap:0!important;flex-wrap:nowrap!important;flex-shrink:1!important;}',
      '.nav-link{white-space:nowrap!important;font-size:12px!important;padding:8px 5px!important;}',
      '.nav-cta{padding:7px 14px!important;font-size:12px!important;}',
      '#desk-search-wrap{flex-shrink:0!important;margin-right:4px!important;}',
      '}'
    ].join('');
    document.head.appendChild(s);
  })();


  // ===== HEADER SCROLL =====
  const header = document.getElementById('header');
  window.addEventListener('scroll', () => {
    if (window.scrollY > 50) header.classList.add('scrolled');
    else header.classList.remove('scrolled');
  });

  // ===== HAMBURGER MENU + REBUILD NAV (uvijek svjež, bez obzira na keš) =====
  const hamburger = document.getElementById('hamburger');
  const navMenu = document.getElementById('nav-menu');
  if (navMenu) {
    navMenu.innerHTML = `
      <div id="mob-search-box" style="padding:4px 0 14px;">
        <div style="position:relative;">
          <i class="fas fa-search" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#c9a86c;font-size:14px;pointer-events:none;z-index:1;"></i>
          <input id="mob-search-input" type="text" placeholder="Traži po imenu ili šifri…" autocomplete="off"
            style="width:100%;box-sizing:border-box;padding:12px 14px 12px 40px;border-radius:10px;border:1.5px solid rgba(201,168,108,0.35);background:rgba(255,255,255,0.07);color:#fff;font-size:15px;font-family:inherit;outline:none;-webkit-appearance:none;">
        </div>
        <div id="mob-search-results" style="display:none;margin-top:6px;border-radius:10px;overflow:hidden;max-height:52vh;overflow-y:auto;background:rgba(20,18,15,0.97);border:1px solid rgba(201,168,108,0.2);"></div>
      </div>
      <a href="index.html" class="nav-link">Početna</a>
      <a href="products.html?category=bambus-paneli" class="nav-link">Bambus Paneli</a>
      <a href="products.html?category=3d-letvice" class="nav-link">3D Letvice</a>
      <a href="products.html?category=akusticni-paneli" class="nav-link">Akustični Paneli</a>
      <a href="products.html?category=aluminijum-lajsne" class="nav-link">Aluminijum Lajsne</a>
      <a href="products.html?category=spc-pod" class="nav-link">SPC Pod</a>
      <a href="products.html?category=pu-kamen" class="nav-link">PU Kamen</a>
      <a href="about.html" class="nav-link">O Nama</a>
      <a href="contact.html" class="nav-link nav-cta">Kontakt</a>
    `;

    // ── Mobile search logic ──
    let _allProducts = null;
    async function loadProductsOnce() {
      if (_allProducts) return _allProducts;
      try {
        const r = await fetch('data/products.json?v=' + Date.now());
        _allProducts = await r.json();
      } catch(e) { _allProducts = []; }
      return _allProducts;
    }

    const catLabels = {
      'bambus-paneli':'Bambus Paneli','bambus-drveni':'Drveni','bambus-tekstilni':'Tekstilni',
      'bambus-mermerni':'Mermerni','bambus-metalni':'Metalni','bambus-kozni':'Kožni',
      '3d-letvice':'3D Letvice','akusticni-paneli':'Akustični','aluminijum-lajsne':'Lajsne',
      'spc-pod':'SPC Pod','pu-kamen':'PU Kamen','classic':'Classic'
    };

    const searchInput = navMenu.querySelector('#mob-search-input');
    const resultsBox  = navMenu.querySelector('#mob-search-results');

    searchInput.addEventListener('focus', () => loadProductsOnce());
    searchInput.addEventListener('input', async () => {
      const q = searchInput.value.trim().toLowerCase();
      if (q.length < 2) { resultsBox.style.display = 'none'; resultsBox.innerHTML = ''; return; }
      const products = await loadProductsOnce();
      const hits = products.filter(p =>
        (p.name || '').toLowerCase().includes(q) ||
        (p.sku  || '').toLowerCase().includes(q)
      ).slice(0, 12);

      if (!hits.length) {
        resultsBox.style.display = 'block';
        resultsBox.innerHTML = `<div style="padding:14px 16px;color:rgba(255,255,255,0.45);font-size:14px;">Nema rezultata za „${searchInput.value}"</div>`;
        return;
      }

      resultsBox.innerHTML = hits.map(p => {
        const cat = p.category || '';
        const baseCat = cat.replace('bambus-','');
        const catPage = ['drveni','tekstilni','mermerni','metalni','kozni'].includes(baseCat)
          ? 'bambus-paneli' : cat;
        const label = catLabels[cat] || cat;
        const thumb = p.image ? `<img src="${p.image}" style="width:40px;height:40px;object-fit:cover;border-radius:6px;flex-shrink:0;" onerror="this.style.display='none'">` : '';
        return `<a href="products.html?category=${encodeURIComponent(catPage)}"
          style="display:flex;align-items:center;gap:12px;padding:11px 14px;text-decoration:none;border-bottom:1px solid rgba(255,255,255,0.06);transition:background 0.15s;"
          onmouseenter="this.style.background='rgba(201,168,108,0.12)'" onmouseleave="this.style.background=''"
          onclick="document.getElementById('mob-search-input').value='';document.getElementById('mob-search-results').style.display='none';">
          ${thumb}
          <div style="flex:1;min-width:0;">
            <div style="color:#fff;font-size:14px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${p.name}</div>
            <div style="margin-top:2px;display:flex;gap:6px;flex-wrap:wrap;">
              ${p.sku ? `<span style="font-size:11px;color:#c9a86c;font-family:monospace;">${p.sku}</span>` : ''}
              <span style="font-size:11px;color:rgba(255,255,255,0.4);">${label}</span>
            </div>
          </div>
          <i class="fas fa-chevron-right" style="color:rgba(201,168,108,0.5);font-size:11px;flex-shrink:0;"></i>
        </a>`;
      }).join('');
      resultsBox.style.display = 'block';
    });

    if (hamburger) {
      hamburger.addEventListener('click', () => {
        hamburger.classList.toggle('active');
        navMenu.classList.toggle('open');
        if (navMenu.classList.contains('open')) {
          setTimeout(() => searchInput.focus(), 120);
        } else {
          resultsBox.style.display = 'none';
          searchInput.value = '';
        }
      });
    }
    navMenu.querySelectorAll('.nav-link').forEach(link => {
      link.addEventListener('click', () => {
        if (hamburger) hamburger.classList.remove('active');
        navMenu.classList.remove('open');
        resultsBox.style.display = 'none';
        searchInput.value = '';
      });
    });
  }

  // ===== DESKTOP SEARCH =====
  (function() {
    const wrap = document.getElementById('desk-search-wrap');
    if (!wrap) return;

    // Sakrij desk-search na mobilnom, mob-search-box na desktopu
    const navResponsive = document.createElement('style');
    navResponsive.textContent = '@media(max-width:768px){#desk-search-wrap{display:none!important;}}@media(min-width:769px){#mob-search-box{display:none!important;}}';
    document.head.appendChild(navResponsive);

    const input  = document.getElementById('desk-search-input');
    const resBox = document.getElementById('desk-search-results');
    if (!input || !resBox) return;

    let _prods = null;
    async function loadProds() {
      if (_prods) return _prods;
      try { const r = await fetch('data/products.json?v=' + Date.now()); _prods = await r.json(); }
      catch(e) { _prods = []; }
      return _prods;
    }

    const dCatLabels = {
      'bambus-paneli':'Bambus Paneli','bambus-drveni':'Drveni','bambus-tekstilni':'Tekstilni',
      'bambus-mermerni':'Mermerni','bambus-metalni':'Metalni','bambus-kozni':'Kožni',
      '3d-letvice':'3D Letvice','akusticni-paneli':'Akustični','aluminijum-lajsne':'Lajsne',
      'spc-pod':'SPC Pod','pu-kamen':'PU Kamen','classic':'Classic'
    };

    function closeResults() {
      resBox.style.display = 'none';
      resBox.innerHTML = '';
      input.value = '';
    }

    input.addEventListener('focus', () => loadProds());
    input.addEventListener('keydown', e => { if (e.key === 'Escape') closeResults(); });

    input.addEventListener('input', async () => {
      const q = input.value.trim().toLowerCase();
      if (q.length < 2) { resBox.style.display = 'none'; resBox.innerHTML = ''; return; }
      const products = await loadProds();
      const hits = products.filter(p =>
        (p.name || '').toLowerCase().includes(q) ||
        (p.sku  || '').toLowerCase().includes(q)
      ).slice(0, 10);

      if (!hits.length) {
        resBox.innerHTML = `<div style="padding:14px 16px;color:rgba(255,255,255,0.45);font-size:14px;">Nema rezultata za „${input.value}"</div>`;
        resBox.style.display = 'block';
        return;
      }
      resBox.innerHTML = hits.map(p => {
        const cat = p.category || '';
        const baseCat = cat.replace('bambus-','');
        const catPage = ['drveni','tekstilni','mermerni','metalni','kozni'].includes(baseCat)
          ? 'bambus-paneli' : cat;
        const label = dCatLabels[cat] || cat;
        const thumb = p.image ? `<img src="${p.image}" style="width:36px;height:36px;object-fit:cover;border-radius:6px;flex-shrink:0;" onerror="this.style.display='none'">` : '';
        return `<a href="products.html?category=${encodeURIComponent(catPage)}"
          style="display:flex;align-items:center;gap:10px;padding:10px 14px;text-decoration:none;border-bottom:1px solid rgba(255,255,255,0.06);transition:background .15s;"
          onmouseenter="this.style.background='rgba(201,168,108,0.1)'" onmouseleave="this.style.background=''">
          ${thumb}
          <div style="flex:1;min-width:0;">
            <div style="color:#fff;font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${p.name}</div>
            <div style="margin-top:2px;display:flex;gap:6px;">
              ${p.sku ? `<span style="font-size:11px;color:#c9a86c;font-family:monospace;">${p.sku}</span>` : ''}
              <span style="font-size:11px;color:rgba(255,255,255,0.4);">${label}</span>
            </div>
          </div>
        </a>`;
      }).join('');
      resBox.style.display = 'block';
    });

    // Zatvori kad klikneš van
    document.addEventListener('click', e => {
      if (!wrap.contains(e.target)) { resBox.style.display = 'none'; }
    });
  })();

  // ===== ACTIVE NAV =====
  const currentPage = window.location.pathname.split('/').pop() || 'index.html';
  const currentSearch = window.location.search;
  document.querySelectorAll('.nav-link').forEach(link => {
    const href = link.getAttribute('href');
    const [hPage, hSearch] = href.split('?');
    if (
      href === currentPage ||
      (currentPage === '' && href === 'index.html') ||
      (hPage === currentPage && hSearch && currentSearch.includes(hSearch.split('=')[1]))
    ) {
      link.classList.add('active');
    }
  });

  // ===== SCROLL TO TOP =====
  const scrollTopBtn = document.getElementById('scroll-top');
  if (scrollTopBtn) {
    window.addEventListener('scroll', () => {
      if (window.scrollY > 400) scrollTopBtn.classList.add('show');
      else scrollTopBtn.classList.remove('show');
    });
    scrollTopBtn.addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  // ===== KONTAKT FORMA =====
  const contactForm = document.getElementById('contact-form');
  if (contactForm) {
    contactForm.addEventListener('submit', function (e) {
      e.preventDefault();
      const btn = this.querySelector('[type="submit"]');
      const msgDiv = document.getElementById('form-message');
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Slanje...';

      const formData = new FormData(this);

      fetch('php/contact.php', {
        method: 'POST',
        body: formData
      })
        .then(r => r.json())
        .then(data => {
          msgDiv.className = 'form-msg ' + (data.success ? 'success' : 'error');
          msgDiv.textContent = data.message;
          if (data.success) contactForm.reset();
        })
        .catch(() => {
          msgDiv.className = 'form-msg success';
          msgDiv.textContent = 'Poruka je primljena! Kontaktiraćemo vas uskoro.';
          contactForm.reset();
        })
        .finally(() => {
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-paper-plane"></i> Pošalji Poruku';
        });
    });
  }

  // ===== ANIMACIJA NA SCROLL =====
  const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
  };

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.style.opacity = '1';
        entry.target.style.transform = 'translateY(0)';
        observer.unobserve(entry.target);
      }
    });
  }, observerOptions);

  document.querySelectorAll('.animate-on-scroll').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(30px)';
    el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
    observer.observe(el);
  });

});

// ===== LIGHTBOX ZA SLIKE =====
function openLightbox(src) {
  const overlay = document.createElement('div');
  overlay.style.cssText = `
    position:fixed;inset:0;background:rgba(0,0,0,0.92);z-index:9999;
    display:flex;align-items:center;justify-content:center;cursor:pointer;
  `;
  const img = document.createElement('img');
  img.src = src;
  img.style.cssText = 'max-width:90vw;max-height:90vh;border-radius:8px;box-shadow:0 20px 60px rgba(0,0,0,0.5);';
  overlay.appendChild(img);
  overlay.addEventListener('click', () => overlay.remove());
  document.body.appendChild(overlay);
}


