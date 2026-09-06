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
