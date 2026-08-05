/* Traka za saglasnost o kolačićima.
   Google Analytics se NE pokreće dok posjetilac ne pristane — podrazumijevano stanje
   je "denied" i postavlja se u <head> prije gtag konfiguracije (Consent Mode v2). */
(function () {
  var KLJUC = 'mmh-kolacici';
  function stanje() { try { return localStorage.getItem(KLJUC); } catch (e) { return null; } }
  function upisi(v) { try { localStorage.setItem(KLJUC, v); } catch (e) {} }

  function primijeni(v) {
    if (typeof gtag !== 'function') return;
    var dozvoli = v === 'da' ? 'granted' : 'denied';
    gtag('consent', 'update', {
      ad_storage: dozvoli, ad_user_data: dozvoli,
      ad_personalization: dozvoli, analytics_storage: dozvoli
    });
  }

  function skloni() {
    var el = document.getElementById('kolacici');
    if (el) el.remove();
  }

  function prikazi() {
    if (document.getElementById('kolacici')) return;
    var d = document.createElement('div');
    d.id = 'kolacici';
    d.setAttribute('role', 'dialog');
    d.setAttribute('aria-label', 'Saglasnost za kolačiće');
    d.innerHTML =
      '<p>Koristimo kolačiće koji su neophodni da korpa radi, i statističke koji nam pokazuju ' +
      'kako se sajt koristi. Statistički se pokreću samo ako pristanete. ' +
      '<a href="privatnost.html">Više o kolačićima</a></p>' +
      '<div class="kolacici-btns">' +
      '<button type="button" id="kolacici-ne">Samo neophodni</button>' +
      '<button type="button" class="da" id="kolacici-da">Prihvatam</button>' +
      '</div>';
    document.body.appendChild(d);
    document.getElementById('kolacici-da').addEventListener('click', function () {
      upisi('da'); primijeni('da'); skloni();
    });
    document.getElementById('kolacici-ne').addEventListener('click', function () {
      upisi('ne'); primijeni('ne'); skloni();
    });
  }

  if (!stanje()) prikazi();

  // Dugme "Promijeni izbor" na stranici Politika privatnosti
  document.addEventListener('click', function (e) {
    if (e.target && e.target.id === 'opet-kolacici') {
      try { localStorage.removeItem(KLJUC); } catch (err) {}
      prikazi();
    }
  });
})();
