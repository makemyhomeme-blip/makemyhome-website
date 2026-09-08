/**
 * Make My Home Decor — GA4 događaji (konverzije).
 * Samo osluškuje klikove i slanja formi — ne dira postojeću logiku korpe.
 * Ako gtag nije učitan (blokiran adblockom), sve tiho pada u prazno.
 */
(function () {
  'use strict';

  function ev(name, params) {
    try {
      if (typeof window.gtag === 'function') window.gtag('event', name, params || {});
    } catch (e) { /* nikad ne ruši stranicu zbog analitike */ }
  }
  window.mmhTrack = ev;

  // ---- 1. Telefon, WhatsApp, Viber, email ----
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a) return;
    var href = a.getAttribute('href') || '';

    if (href.indexOf('tel:') === 0) {
      ev('poziv_telefonom', { method: 'telefon', broj: href.replace('tel:', ''), stranica: location.pathname });
      ev('generate_lead', { method: 'telefon' });
    } else if (href.indexOf('wa.me') > -1 || href.indexOf('whatsapp') > -1) {
      ev('whatsapp_klik', { method: 'whatsapp', stranica: location.pathname });
      ev('generate_lead', { method: 'whatsapp' });
    } else if (href.indexOf('viber:') === 0) {
      ev('viber_klik', { method: 'viber', stranica: location.pathname });
      ev('generate_lead', { method: 'viber' });
    } else if (href.indexOf('mailto:') === 0) {
      ev('email_klik', { method: 'email', stranica: location.pathname });
      ev('generate_lead', { method: 'email' });
    } else if (/instagram\.com|facebook\.com|tiktok\.com|threads\./.test(href)) {
      ev('drustvena_mreza', { mreza: href.replace(/^https?:\/\/(www\.)?/, '').split('/')[0] });
    }
  }, true);

  // ---- 2. Dodavanje u korpu ----
  // Dugmad koriste inline onclick="addProductToCartById(...)" i nemaju klasu,
  // pa presrećemo same funkcije — radi i sa kartica i sa stranice proizvoda.
  function wrap(fnName, handler) {
    var tries = 0;
    (function attach() {
      if (typeof window[fnName] === 'function' && !window[fnName].__mmhWrapped) {
        var orig = window[fnName];
        var w = function () {
          try { handler.apply(null, arguments); } catch (e) {}
          return orig.apply(this, arguments);
        };
        w.__mmhWrapped = true;
        window[fnName] = w;
        return;
      }
      if (++tries < 40) setTimeout(attach, 150);
    })();
  }

  // Namjerno NE presrećemo addProductToCartById — ona interno zove addToCart,
  // pa bi se add_to_cart brojao dva puta.
  wrap('addToCart', function (product, qty) {
    var p = product || {};
    var cijena = p.discount > 0 ? +(p.price * (1 - p.discount / 100)).toFixed(2) : p.price;
    ev('add_to_cart', {
      currency: 'EUR',
      value: cijena,
      items: [{ item_id: String(p.id || ''), item_name: p.name || '', price: cijena, quantity: qty || 1 }]
    });
  });

  // ---- 3. Početak narudžbe + poslata narudžba ----
  var orderForm = document.getElementById('order-form');
  if (orderForm) {
    ev('begin_checkout', { currency: 'EUR' });
    orderForm.addEventListener('submit', function () {
      var uk = document.querySelector('#order-total, .order-total, [data-order-total]');
      var v = uk ? parseFloat(String(uk.innerText).replace(/[^\d.,]/g, '').replace(',', '.')) : undefined;
      ev('purchase_intent', { currency: 'EUR', value: isNaN(v) ? undefined : v });
      ev('generate_lead', { method: 'narudzba_forma' });
    });
  }

  // ---- 4. Kontakt forma ----
  var cf = document.getElementById('contact-form');
  if (cf) {
    cf.addEventListener('submit', function () {
      ev('kontakt_forma', { stranica: location.pathname });
      ev('generate_lead', { method: 'kontakt_forma' });
    });
  }

  // ---- 5. Uspješna narudžba (stranica zahvalnice) ----
  if (/hvala\.html$/.test(location.pathname)) {
    ev('purchase_confirmed', { currency: 'EUR' });
  }

  // ---- 6. Pregled proizvoda ----
  if (/product\.(html|php)$/.test(location.pathname) && location.search.indexOf('id=') > -1
      && document.querySelector('.product-detail-grid')) {
    var h1 = document.querySelector('h1');
    ev('view_item', {
      currency: 'EUR',
      items: [{ item_name: h1 ? h1.innerText.trim().slice(0, 90) : document.title }]
    });
  }

  // ---- 7. Klik na "Prikaži još komentara" (mjeri interes za recenzije) ----
  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('.rv-more-btn')) ev('recenzije_prosireno', {});
  }, true);
})();

/**
 * Cookie consent banner (kolačići).
 * Sajt koristi Google Consent Mode: analitika je po defaultu ODBIJENA
 * (index.html: gtag consent default analytics_storage:'denied'), pa GA ne
 * postavlja kolačiće dok posjetilac ne prihvati. Ovaj banner daje izbor i
 * pamti ga u localStorage. Prihvatanje odobri analitiku, odbijanje je ostavlja
 * ugašenu. Prikazuje se na svim stranicama (ovaj fajl je svuda uključen).
 */
(function () {
  'use strict';
  var KEY = 'mmh_cookie_consent';
  function grantAnalytics() {
    try { if (typeof window.gtag === 'function') window.gtag('consent', 'update', { analytics_storage: 'granted' }); } catch (e) {}
  }
  var choice = null;
  try { choice = localStorage.getItem(KEY); } catch (e) {}
  if (choice === 'granted') { grantAnalytics(); return; }
  if (choice === 'denied')  { return; }

  function build() {
    if (document.getElementById('mmh-cookie')) return;
    var box = document.createElement('div');
    box.id = 'mmh-cookie';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-label', 'Obavještenje o kolačićima');
    box.innerHTML =
      '<div class="mmh-cookie-txt">Koristimo kolačiće za <strong>analitiku posjeta</strong> kako bismo poboljšali sajt. ' +
      'Možeš prihvatiti ili odbiti. Više u <a href="/privatnost.html">Politici privatnosti</a>.</div>' +
      '<div class="mmh-cookie-btns">' +
        '<button type="button" class="mmh-cookie-no">Odbijam</button>' +
        '<button type="button" class="mmh-cookie-yes">Prihvatam</button>' +
      '</div>';
    var css = document.createElement('style');
    css.textContent =
      '#mmh-cookie{position:fixed;left:16px;bottom:16px;z-index:9998;max-width:min(440px,calc(100% - 32px));' +
      'background:#0d0d0d;color:#fff;border:1px solid rgba(201,168,108,0.35);border-radius:14px;' +
      'box-shadow:0 12px 40px rgba(0,0,0,0.45);padding:16px 18px;font-size:13.5px;line-height:1.55;' +
      'font-family:inherit;animation:mmhCk .35s ease}' +
      '@keyframes mmhCk{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}' +
      '#mmh-cookie a{color:#d8b877;font-weight:600;text-decoration:underline}' +
      '#mmh-cookie .mmh-cookie-btns{display:flex;gap:10px;margin-top:14px;justify-content:flex-end}' +
      '#mmh-cookie button{cursor:pointer;border-radius:9px;padding:9px 18px;font-size:13px;font-weight:700;border:1px solid rgba(255,255,255,0.28);font-family:inherit}' +
      '#mmh-cookie .mmh-cookie-no{background:transparent;color:#cfcfcf}' +
      '#mmh-cookie .mmh-cookie-no:hover{background:rgba(255,255,255,0.08)}' +
      '#mmh-cookie .mmh-cookie-yes{background:#c9a86c;color:#1a1a1a;border-color:#c9a86c}' +
      '#mmh-cookie .mmh-cookie-yes:hover{background:#d8b877}' +
      '@media(max-width:520px){#mmh-cookie{left:12px;right:12px;bottom:12px;max-width:none}' +
      '#mmh-cookie .mmh-cookie-btns{margin-top:12px}' +
      '#mmh-cookie .mmh-cookie-btns button{flex:1}}' +
      // dok je banner otvoren, sakrij plutajuce dugmad da se ne preklapaju
      'html.mmh-ck-on #whatsapp-float,html.mmh-ck-on #scroll-top,html.mmh-ck-on .scroll-top{display:none!important}';
    document.head.appendChild(css);
    function close(val) {
      try { localStorage.setItem(KEY, val); } catch (e) {}
      if (val === 'granted') grantAnalytics();
      document.documentElement.classList.remove('mmh-ck-on');
      box.style.opacity = '0';
      setTimeout(function () { box.parentNode && box.parentNode.removeChild(box); }, 250);
    }
    box.querySelector('.mmh-cookie-yes').addEventListener('click', function () { close('granted'); });
    box.querySelector('.mmh-cookie-no').addEventListener('click', function () { close('denied'); });
    document.documentElement.classList.add('mmh-ck-on');
    document.body.appendChild(box);
  }
  if (document.body) build();
  else document.addEventListener('DOMContentLoaded', build);
})();
