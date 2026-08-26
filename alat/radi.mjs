/**
 * DA LI SVE ZAISTA RADI — provjera funkcija, ne prisustva.
 *
 * Zasto postoji
 * -------------
 * alat/provjera.py ima 76 pravila i sva prolaze, a i dalje su se pojavljivale
 * greske: slika u schemi koja vraca 404, pretraga obecana Google-u koja ne
 * filtrira, admin koji poslije snimanja pokazuje staro stanje. Sve su bile ista
 * vrsta greske — provjeravalo se DA LI nesto postoji, a ne DA LI RADI.
 *
 * Ovaj alat otvara pravi pregledac i prolazi kroz ono sto kupac stvarno radi:
 * trazi proizvod, dodaje u korpu, mijenja kolicinu, otvara galeriju, racuna
 * kolicinu kalkulatorom, ide na naplatu. Za svaki korak provjerava ISHOD, ne
 * postojanje dugmeta.
 *
 * Pokretanje
 * ----------
 *   node alat/radi.mjs                     # protiv lokalnog PHP servera
 *   node alat/radi.mjs https://makemyhome.me
 *
 * Za lokalno: php -S 127.0.0.1:8899 -t .
 * Izlazni kod je 1 ako bilo koja provjera padne.
 */

import pkg from '/opt/node22/lib/node_modules/playwright/index.js';
const { chromium } = pkg;

const BAZA = process.argv[2] || 'http://127.0.0.1:8899';
const LOKALNO = BAZA.includes('127.0.0.1') || BAZA.includes('localhost');

const nalazi = [];
let ukupno = 0;

function tvrdi(ime, uslov, detalj = '') {
  ukupno++;
  if (uslov) {
    console.log(`OK   ${ime}`);
  } else {
    console.log(`PAD  ${ime}${detalj ? '  — ' + detalj : ''}`);
    nalazi.push(ime + (detalj ? ' — ' + detalj : ''));
  }
}

/**
 * Stranice nose <base href="https://makemyhome.me/">, pa se pri lokalnom
 * testiranju svi resursi traze sa pravog domena. Ovdje se preusmjeravaju na
 * lokalni server, da se testira BAS kod iz radnog direktorijuma.
 */
async function novaStrana(b, sirina, visina) {
  const p = await b.newPage({ viewport: { width: sirina, height: visina } });
  const greske = [];
  p.on('pageerror', (e) => greske.push('JS: ' + String(e).slice(0, 120)));
  /* Lokalno u repou nema images/products/* ni images/categories/* — vlasnik ih
     dodaje kroz admin, pa stoje samo na serveru. Ta 404 nisu greska sajta i
     ne smiju rusiti test; na pravom domenu se broje normalno. */
  p.on('response', (r) => {
    if (r.status() < 400) return;
    const u = r.url();
    if (LOKALNO && /\/images\/(products|categories|og)\//.test(u)) return;
    if (/favicon|gtag|analytics|googletag/i.test(u)) return;
    greske.push('HTTP ' + r.status() + ' ' + u.replace(BAZA, '').slice(0, 70));
  });
  p.on('console', (m) => {
    if (m.type() !== 'error') return;
    const t = m.text();
    if (/favicon|gtag|analytics/i.test(t)) return;
    // "Failed to load resource" je vec pokriven gore, sa punom adresom
    if (/Failed to load resource/i.test(t)) return;
    greske.push('konzola: ' + t.slice(0, 120));
  });
  if (LOKALNO) {
    await p.route(/^https:\/\/makemyhome\.me\//, async (r) => {
      const lok = r.request().url().replace('https://makemyhome.me/', BAZA + '/');
      try { await r.fulfill({ response: await p.request.fetch(lok) }); }
      catch { await r.fulfill({ status: 204, body: '' }); }
    });
  }
  await p.route(/googletagmanager|google-analytics|formsubmit/, (r) =>
    r.fulfill({ status: 204, body: '' }));
  p._greske = greske;
  return p;
}

const put = (s) => (LOKALNO ? BAZA + s.replace('/kategorija/', '/products.php?k=')
                                     .replace('/paneli/', '/product.php?slug=')
                                     .replace(/^\/$/, '/index.html')
                                     .replace('/products.html', '/products.php')
                                     .replace('/cjenovnik.html', '/cjenovnik.php')
                                     .replace('/inspiracija.html', '/inspiracija.php')
                                     .replace('/decor-box.html', '/decor-box.php')
                            : BAZA + s);

const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });

// ─────────────────────────────────────────────────────────────────────────────
// 1. PRETRAGA — mora filtrirati, ne samo otvoriti stranicu
// ─────────────────────────────────────────────────────────────────────────────
{
  const p = await novaStrana(b, 1440, 900);
  await p.goto(put('/products.html') + (LOKALNO ? '?' : '?') + 'search=deva', { waitUntil: 'load' });
  const naso = await p.locator('.product-card').count();
  await p.goto(put('/products.html') + '?search=zzqqxx', { waitUntil: 'load' });
  const prazno = await p.locator('.product-card').count();
  const tijelo = await p.content();

  tvrdi('pretraga: "deva" vraca proizvode', naso > 0, `${naso} kartica`);
  tvrdi('pretraga: filtrira (deva != zzqqxx)', naso !== prazno, `${naso} vs ${prazno}`);
  tvrdi('pretraga: prazan rezultat javlja "nema rezultata"', /nema rezultata/i.test(tijelo));
  tvrdi('pretraga: rezultati nose noindex', /noindex/i.test(tijelo));
  await p.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. PRETRAGA U ZAGLAVLJU — racunar i telefon
// ─────────────────────────────────────────────────────────────────────────────
for (const [ime, w, h, hamburger] of [['racunar', 1440, 900, false], ['telefon', 390, 844, true]]) {
  const p = await novaStrana(b, w, h);
  await p.goto(put('/'), { waitUntil: 'load' });
  await p.waitForTimeout(1500);
  if (hamburger) {
    await p.locator('#menu-toggle, .menu-toggle, .hamburger').first().click().catch(() => {});
    await p.waitForTimeout(600);
  }
  const polje = p.locator(hamburger ? '#mob-search-input' : '#desk-search-input').first();
  const vidi = await polje.isVisible().catch(() => false);
  tvrdi(`pretraga u zaglavlju (${ime}): polje vidljivo`, vidi);
  if (vidi) {
    await polje.fill('deva');
    await p.waitForTimeout(1200);
    const rez = p.locator(hamburger ? '#mob-search-results' : '#desk-search-results');
    const tekst = await rez.innerText().catch(() => '');
    tvrdi(`pretraga u zaglavlju (${ime}): nalazi po imenu`, /deva/i.test(tekst), tekst.slice(0, 40));
    await polje.fill('I3D160BW008');
    await p.waitForTimeout(1200);
    const t2 = await rez.innerText().catch(() => '');
    tvrdi(`pretraga u zaglavlju (${ime}): nalazi po sifri`, /letvica|deva/i.test(t2), t2.slice(0, 40));
  }
  tvrdi(`pocetna (${ime}): bez JS gresaka`, p._greske.length === 0, p._greske.slice(0, 2).join(' | '));
  await p.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. KORPA — dodavanje, kolicina, brisanje, iznos
// ─────────────────────────────────────────────────────────────────────────────
{
  const p = await novaStrana(b, 390, 844);
  await p.goto(put('/paneli/3d-letvica-deva'), { waitUntil: 'load' });
  await p.waitForTimeout(1500);

  const dugme = p.locator('button:has-text("Dodaj u Korpu")').first();
  tvrdi('proizvod: dugme "Dodaj u Korpu" postoji', await dugme.isVisible().catch(() => false));
  await dugme.click().catch(() => {});
  await p.waitForTimeout(1000);

  const korpa = await p.evaluate(() => {
    try { return JSON.parse(localStorage.getItem('mmh_cart') || '[]'); } catch { return []; }
  });
  tvrdi('korpa: artikal upisan u localStorage', korpa.length === 1, `stavki: ${korpa.length}`);
  if (korpa.length) {
    tvrdi('korpa: artikal nosi cijenu', Number(korpa[0].price) > 0, String(korpa[0].price));
    tvrdi('korpa: artikal nosi jedinicu', !!korpa[0].unit, String(korpa[0].unit));
  }

  await p.goto(put('/korpa.html'), { waitUntil: 'load' });
  await p.waitForTimeout(1500);
  const tekstKorpe = await p.locator('body').innerText();
  tvrdi('korpa: stranica prikazuje artikal', /Deva/i.test(tekstKorpe));
  tvrdi('korpa: prikazuje ukupan iznos', /Ukupno/i.test(tekstKorpe));

  const plus = p.locator('button:has-text("+"), .ki-plus').first();
  if (await plus.isVisible().catch(() => false)) {
    await plus.click();
    await p.waitForTimeout(900);
    const k2 = await p.evaluate(() => {
      try { return JSON.parse(localStorage.getItem('mmh_cart') || '[]'); } catch { return []; }
    });
    tvrdi('korpa: dugme + povecava kolicinu', (k2[0]?.qty || 0) === 2, `qty=${k2[0]?.qty}`);
  }
  tvrdi('korpa: bez JS gresaka', p._greske.length === 0, p._greske.slice(0, 2).join(' | '));
  await p.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. NAPLATA — forma, obavezna polja, iznos se slaze sa korpom
// ─────────────────────────────────────────────────────────────────────────────
{
  const p = await novaStrana(b, 390, 844);
  await p.goto(put('/paneli/3d-letvica-deva'), { waitUntil: 'load' });
  await p.waitForTimeout(1200);
  await p.locator('button:has-text("Dodaj u Korpu")').first().click().catch(() => {});
  await p.waitForTimeout(800);

  await p.goto(put('/checkout.html'), { waitUntil: 'load' });
  await p.waitForTimeout(1500);
  const polja = await p.locator('form input:not([type=hidden]), form select, form textarea').count();
  tvrdi('naplata: forma ima polja', polja >= 5, `${polja} polja`);
  const obavezna = await p.locator('form [required]').count();
  tvrdi('naplata: ima obaveznih polja', obavezna >= 3, `${obavezna} obaveznih`);
  const honey = await p.locator('input[name="_honey"]').count();
  tvrdi('naplata: mamac za robote postoji', honey === 1);
  const next = await p.locator('input[name="_next"]').getAttribute('value').catch(() => '');
  tvrdi('naplata: poslije slanja vodi na hvala.html', /hvala\.html$/.test(next || ''), next || '');
  const tekst = await p.locator('body').innerText();
  tvrdi('naplata: prikazuje pregled narudzbe', /Deva/i.test(tekst));
  tvrdi('naplata: bez JS gresaka', p._greske.length === 0, p._greske.slice(0, 2).join(' | '));
  await p.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. GALERIJA I HARMONIKA na stranici proizvoda
// ─────────────────────────────────────────────────────────────────────────────
{
  const p = await novaStrana(b, 1440, 900);
  await p.goto(put('/paneli/3d-letvica-deva'), { waitUntil: 'load' });
  await p.waitForTimeout(1800);

  const glavna = p.locator('#gallery-main-img');
  tvrdi('galerija: glavna slika postoji', await glavna.isVisible().catch(() => false));
  const prije = await glavna.getAttribute('src').catch(() => '');
  const slicice = p.locator('.gallery-thumb');
  const brSlicica = await slicice.count();
  if (brSlicica > 1) {
    await slicice.nth(1).click();
    await p.waitForTimeout(900);
    const poslije = await glavna.getAttribute('src').catch(() => '');
    tvrdi('galerija: klik na slicicu mijenja glavnu sliku', prije !== poslije);
  } else {
    // Proizvod sa jednom fotografijom je legitiman — provjerava se samo da
    // glavna slika nije prazna.
    tvrdi('galerija: glavna slika ima izvor', !!prije && !/^\s*$/.test(prije), prije || '(prazno)');
  }

  tvrdi('proizvod: bez JS gresaka', p._greske.length === 0, p._greske.slice(0, 2).join(' | '));
  await p.close();
}

// Harmonika se otvara i zatvara SAMO na telefonu. Na racunaru je CSS-om
// (.gallery-specs-desktop .spec-body { display:block !important }) trajno
// otvorena, pa klik tamo namjerno nista ne mijenja.
{
  const p = await novaStrana(b, 390, 844);
  await p.goto(put('/paneli/3d-letvica-deva'), { waitUntil: 'load' });
  await p.waitForTimeout(1800);
  /* Strogo unutar mobilnog bloka: selektor sa zarezom bi pokupio i onu kopiju
     harmonike koja stoji ispod slike za racunar, a ona je na telefonu skrivena. */
  const zaglavlja = p.locator('.accordion-mobile-only .spec-header');
  if (await zaglavlja.count() > 1) {
    const tijelo = p.locator('.accordion-mobile-only .spec-body').nth(1);
    const prije = await tijelo.isVisible().catch(() => false);
    await zaglavlja.nth(1).click().catch(() => {});
    await p.waitForTimeout(700);
    const poslije = await tijelo.isVisible().catch(() => false);
    tvrdi('harmonika (telefon): klik otvara/zatvara odjeljak', prije !== poslije,
          `prije=${prije} poslije=${poslije}`);
  } else {
    tvrdi('harmonika (telefon): ima vise odjeljaka', false);
  }
  await p.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// 6. KALKULATOR — mora dati broj, i mijenjati ga kad se promijene mjere
// ─────────────────────────────────────────────────────────────────────────────
{
  const p = await novaStrana(b, 1440, 900);
  await p.goto(put('/paneli/3d-letvica-deva'), { waitUntil: 'load' });
  await p.waitForTimeout(1800);
  const sirina = p.locator('#wall-w').first();
  const visina = p.locator('#wall-h').first();
  const izlaz = p.locator('#calc-result').first();
  if (await sirina.isVisible().catch(() => false)) {
    await sirina.fill('300'); await visina.fill('260');
    await p.waitForTimeout(900);
    const a = (await izlaz.innerText().catch(() => '')).replace(/\s+/g, ' ');
    await sirina.fill('600');
    await p.waitForTimeout(900);
    const c = (await izlaz.innerText().catch(() => '')).replace(/\s+/g, ' ');
    tvrdi('kalkulator: daje rezultat', /\d/.test(a), a.slice(0, 60));
    tvrdi('kalkulator: rezultat se mijenja sa mjerama', a !== c, `${a.slice(0, 30)} → ${c.slice(0, 30)}`);
  } else {
    tvrdi('kalkulator: polja za mjere postoje', false);
  }
  await p.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// 7. KATEGORIJE — svaka mora prikazati bar jedan proizvod
// ─────────────────────────────────────────────────────────────────────────────
{
  const KAT = ['3d-letvice', 'akusticni-paneli', 'pu-kamen', 'spc-pod', 'mdf',
               'flex-stone', 'aluminijum-lajsne', 'bambus-drveni', 'bambus-tekstilni',
               'bambus-mermerni', 'bambus-metalni', 'bambus-kozni', 'classic'];
  const p = await novaStrana(b, 1440, 900);
  for (const k of KAT) {
    await p.goto(put('/kategorija/' + k), { waitUntil: 'load' });
    await p.waitForTimeout(700);
    const n = await p.locator('.product-card').count();
    tvrdi(`kategorija ${k}: ima proizvoda`, n > 0, `${n}`);
  }
  await p.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// 8. KONTAKT FORMA — validacija radi, prazna forma se ne salje
// ─────────────────────────────────────────────────────────────────────────────
{
  const p = await novaStrana(b, 1440, 900);
  await p.goto(put('/contact.html'), { waitUntil: 'load' });
  await p.waitForTimeout(1200);
  const obavezna = await p.locator('#contact-form [required]').count();
  tvrdi('kontakt: forma ima obavezna polja', obavezna >= 3, `${obavezna}`);
  const email = p.locator('#contact-form input[type=email], #email').first();
  tvrdi('kontakt: polje za e-mail je tipa email',
        (await email.getAttribute('type').catch(() => '')) === 'email');
  await p.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// 9. DECOR BOX — katalozi se otvaraju
// ─────────────────────────────────────────────────────────────────────────────
{
  const p = await novaStrana(b, 1440, 900);
  await p.goto(put('/decor-box.html'), { waitUntil: 'load' });
  await p.waitForTimeout(1200);
  const pdf = await p.locator('a[href$=".pdf"]').all();
  tvrdi('decor box: postoje linkovi na kataloge', pdf.length >= 2, `${pdf.length}`);
  for (const a of pdf) {
    const href = await a.getAttribute('href');
    const puna = href.startsWith('http') ? href : BAZA + '/' + href.replace(/^\//, '');
    const r = await p.request.get(puna).catch(() => null);
    tvrdi(`katalog ${href.split('/').pop().slice(0, 34)}`, r && r.status() === 200,
          r ? String(r.status()) : 'nema odgovora');
  }
  await p.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// 10. RASPRODATO — kartica mora biti vidljivo drugacija
// ─────────────────────────────────────────────────────────────────────────────
{
  const p = await novaStrana(b, 1440, 900);
  await p.goto(put('/kategorija/pu-kamen'), { waitUntil: 'load' });
  await p.waitForTimeout(1200);
  const nema = p.locator('.product-card.out-of-stock').first();
  if (await nema.count()) {
    const traka = nema.locator('.oos-tag');
    tvrdi('rasprodato: traka sa natpisom vidljiva', await traka.isVisible().catch(() => false));
    const filtar = await nema.locator('.product-img img').first()
      .evaluate((e) => getComputedStyle(e).filter).catch(() => 'none');
    tvrdi('rasprodato: fotografija je ugasena', /grayscale/.test(filtar), filtar);
  } else {
    console.log('--   nema rasprodatih proizvoda u ovoj kategoriji, preskoceno');
  }
  await p.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// 10b. KISOBRAN KATEGORIJE — samo plocice, bez spiska proizvoda
//
// Zasto: Bambus Paneli su ispod sest plocica nabrajali jos i sve proizvode iz
// svih podtipova. Plocice su time gubile smisao — posjetilac bira tip, a roba
// je ionako vec sva ispisana ispod. Sada kisobran prikazuje samo plocice.
// Kad se trazi (?search=), spisak se prikazuje i tu.
// ─────────────────────────────────────────────────────────────────────────────
{
  const p = await novaStrana(b, 1440, 900);
  await p.goto(put('/kategorija/bambus-paneli'), { waitUntil: 'load' });
  await p.waitForTimeout(1200);
  const plocica = await p.locator('#category-grid .cat-card').count();
  const natpis = (await p.locator('body').innerText()).match(/(\d+)\s+podkategorij/);
  const vidljivi = await p.evaluate(() =>
    [...document.querySelectorAll('#products-container .product-card')].filter((e) => e.offsetParent).length);
  tvrdi('kisobran: natpis se slaze sa brojem plocica',
        natpis && Number(natpis[1]) === plocica, `natpis=${natpis && natpis[1]} plocica=${plocica}`);
  tvrdi('kisobran: NE nabraja proizvode ispod plocica', vidljivi === 0, `vidljivih kartica=${vidljivi}`);
  await p.close();

  // Podkategorija i dalje mora prikazivati svoju robu.
  const q = await novaStrana(b, 1440, 900);
  await q.goto(put('/kategorija/bambus-drveni'), { waitUntil: 'load' });
  await q.waitForTimeout(1200);
  const list = await q.evaluate(() =>
    [...document.querySelectorAll('#products-container .product-card')].filter((e) => e.offsetParent).length);
  tvrdi('podkategorija: prikazuje svoje proizvode', list > 0, `${list}`);
  await q.close();
}

// ─────────────────────────────────────────────────────────────────────────────
// 11. ZAJEDNICKI DJELOVI — moraju izgledati ISTO na svakoj stranici
//
// Zasto: zaglavlje i podnozje su bili prepisani u <style> blok svake stranice,
// 22 kopije u tri razlicite verzije. Podnozje je zato imalo razmak 8px na
// pocetnoj i katalogu, a 9px na ostalih devetnaest stranica, i slova 14px
// naspram 13px. Niko to nije vidio jer se nikad nisu gledale dvije stranice
// jedna do druge. Ovdje se mjeri stvarno izracunat stil i trazi razlika.
// ─────────────────────────────────────────────────────────────────────────────
{
  const STRANE = ['/', '/about.html', '/faq.html', '/montaza.html',
                  '/cjenovnik.html', '/products.html', '/contact.html', '/decor-box.html'];
  const MJERE = {
    'nav: velicina slova':      ['.nav-link', 'fontSize'],
    'nav: unutrasnji razmak':   ['.nav-link', 'padding'],
    'logo: visina':             ['.logo-img', 'height'],
    'logo: ime':                ['.logo-text .name', 'fontSize'],
    'podnozje: razmak stavki':  ['.footer-links-grid li', 'marginBottom'],
    'podnozje: slova linkova':  ['.footer-links-grid a', 'fontSize'],
    'podnozje: broj kolona':    ['.footer-links-grid', 'columnCount'],
    'zaglavlje: sirina':        ['.header-inner', 'maxWidth'],
  };
  const skup = {};
  for (const s of STRANE) {
    const p = await novaStrana(b, 1440, 900);
    await p.goto(put(s), { waitUntil: 'load' });
    await p.waitForTimeout(900);
    const v = await p.evaluate((M) => {
      const o = {};
      for (const [ime, [sel, prop]] of Object.entries(M)) {
        const e = document.querySelector(sel);
        o[ime] = e ? getComputedStyle(e)[prop] : '(nema elementa)';
      }
      return o;
    }, MJERE);
    for (const [ime, vr] of Object.entries(v)) {
      (skup[ime] = skup[ime] || []).push([s, vr]);
    }
    await p.close();
  }
  for (const [ime, parovi] of Object.entries(skup)) {
    const razl = [...new Set(parovi.map(([, v]) => v))];
    tvrdi(`isto na svim stranicama — ${ime}`, razl.length === 1,
          razl.length === 1 ? '' : parovi.map(([s, v]) => `${s}=${v}`).join('  '));
  }
}

await b.close();

console.log('\n' + '='.repeat(66));
console.log(`ZAVRSNO: ${ukupno} provjera, ${nalazi.length} palo`);
for (const n of nalazi) console.log('  PAD  ' + n);
process.exit(nalazi.length ? 1 : 0);
