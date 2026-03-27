/* ===================================================
   MAKE MY HOME - Glavni JavaScript
   =================================================== */

document.addEventListener('DOMContentLoaded', function () {

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
    if (hamburger) {
      hamburger.addEventListener('click', () => {
        hamburger.classList.toggle('active');
        navMenu.classList.toggle('open');
      });
    }
    navMenu.querySelectorAll('.nav-link').forEach(link => {
      link.addEventListener('click', () => {
        if (hamburger) hamburger.classList.remove('active');
        navMenu.classList.remove('open');
      });
    });
  }

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
