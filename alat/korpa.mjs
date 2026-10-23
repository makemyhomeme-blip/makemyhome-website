/**
 * Provjera korpe i narudzbe, od pocetka do kraja.
 *
 * Zasto zaseban alat: alat/provjera.py radi preko curl-a i ne pokrece
 * JavaScript, a korpa je bas JavaScript — localStorage, racunanje, forma.
 * Ovdje se pokrece pravi pregledac i prolazi isti put kao kupac.
 *
 * Narudzba se NE salje stvarno: zahtjev prema formsubmit.co se presrece i
 * ispisuje se sta bi bilo poslato.
 *
 * Pokretanje (uz lokalni server na 8899):
 *   php -S 127.0.0.1:8899 -t . &
 *   node alat/korpa.mjs
 */
import pkg from '/opt/node22/lib/node_modules/playwright/index.js';
const { chromium } = pkg;

const BAZA = 'http://127.0.0.1:8899';
let palo = 0;
const ok = (uslov, tekst, dodatno = '') => {
  console.log(`  ${uslov ? 'OK ' : 'PAD'}  ${tekst}${dodatno ? '   ' + dodatno : ''}`);
  if (!uslov) palo++;
};

const b = await chromium.launch();
const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
let poslato = null;
await ctx.route('**/*', async r => {
  const u = r.request().url();
  if (u.includes('formsubmit.co')) {
    poslato = r.request().postData() || '';
    return r.fulfill({ status: 200, headers: { 'content-type': 'text/html' }, body: 'OK' });
  }
  if (!u.startsWith('https://makemyhome.me/')) return r.continue();
  try {
    const o = await fetch(u.replace('https://makemyhome.me/', BAZA + '/'));
    return r.fulfill({ status: o.status, headers: { 'content-type': o.headers.get('content-type') || 'application/octet-stream' }, body: Buffer.from(await o.arrayBuffer()) });
  } catch { return r.abort(); }
});
const p = await ctx.newPage();
const izuzeci = [];
p.on('pageerror', e => izuzeci.push(String(e).split('\n')[0].slice(0, 110)));
p.on('dialog', d => d.accept());

const stanje = () => p.evaluate(() => ({
  stavki: [...document.querySelectorAll('.korpa-item')].filter(e => e.offsetParent !== null).length,
  korpa: JSON.parse(localStorage.getItem('mmh_cart') || '[]'),
  badge: (document.querySelector('.cart-badge') || {}).textContent,
  prazna: /prazna/i.test(document.body.innerText),
}));

console.log('=== KORPA I NARUDZBA ===');

await p.goto(BAZA + '/product.php?slug=drveni-panel-mocha-oak', { waitUntil: 'load' });
await p.waitForTimeout(1500);
await p.evaluate(() => { const b = [...document.querySelectorAll('button')].find(e => /dodaj u korpu/i.test(e.textContent)); if (b) b.click(); });
await p.waitForTimeout(600);
let s = await p.evaluate(() => JSON.parse(localStorage.getItem('mmh_cart') || '[]'));
ok(s.length === 1, 'dugme "Dodaj u korpu" stvarno dodaje proizvod', s.length ? s[0].name : '');
ok(s[0] && s[0].price > 0 && s[0].sku, 'stavka nosi cijenu i sifru', s[0] ? `${s[0].price} € / ${s[0].sku}` : '');

await p.goto(BAZA + '/korpa.html', { waitUntil: 'load' });
await p.waitForTimeout(1200);
let st = await stanje();
ok(st.stavki === 1, 'korpa prikazuje dodati proizvod');
ok(st.badge === '1', 'brojac u zaglavlju pokazuje 1', 'pokazuje ' + st.badge);

await p.click('.korpa-item .ki-qty-btn:last-of-type');
await p.waitForTimeout(600);
st = await stanje();
ok(st.korpa[0].qty === 2, 'dugme + povecava kolicinu');
const oc = st.korpa[0].price * 2;
const prik = await p.evaluate(() => (document.body.innerText.match(/Ukupno\s*([\d.,]+)/) || [])[1]);
ok(prik && Math.abs(parseFloat(prik.replace(',', '.')) - oc) < 0.02, 'ukupan iznos je tacan',
   `prikazano ${prik} €, ocekivano ${oc.toFixed(2)} €`);

await p.click('.korpa-item .ki-remove');
await p.waitForTimeout(600);
st = await stanje();
ok(st.stavki === 0 && st.prazna, 'brisanje stavke prazni korpu i javlja da je prazna');

// puna korpa -> placanje
await p.evaluate(() => localStorage.setItem('mmh_cart', JSON.stringify([
  { id: 23, name: 'Mocha Oak', sku: 'MW300', price: 69.59, originalPrice: 86.99, discount: 20, image: 'images/products/mw300.jpg', unit: 'kom', qty: 2 }])));
await p.goto(BAZA + '/checkout.html', { waitUntil: 'load' });
await p.waitForTimeout(1200);
for (const [sel, v] of [['[name=Ime]', 'Marko'], ['[name=Prezime]', 'Markovic'], ['[name=Telefon]', '069123456'],
                        ['[name=Email]', 'test@test.me'], ['[name=Adresa]', 'Nikca od Rovina 5'], ['[name=Grad]', 'Podgorica']])
  await p.fill(sel, v);

await p.click('#co-submit-btn');
await p.waitForTimeout(900);
ok(poslato === null, 'narudzba se NE salje dok se ne izabere nacin placanja');
ok(await p.evaluate(() => !!localStorage.getItem('mmh_cart')), 'korpa ostaje puna kad slanje ne prodje');

await p.evaluate(() => { const o = [...document.querySelectorAll('.pay-opt')][1]; o.click(); });
await p.waitForTimeout(300);
await p.click('#co-submit-btn');
await p.waitForTimeout(2000);
ok(!!poslato, 'narudzba se salje kad je sve popunjeno');
if (poslato) {
  const d = decodeURIComponent(poslato).replace(/\+/g, ' ');
  const uzmi = k => (d.match(new RegExp(k + '=([^&]*)')) || [])[1] || '';
  for (const k of ['Ime', 'Prezime', 'Telefon', 'Email', 'Adresa', 'Grad', 'Narudzba', 'Ukupno', 'Nacin_placanja'])
    ok(uzmi(k).trim() !== '', `u mejlu stize: ${k}`, uzmi(k).slice(0, 40));
  ok(uzmi('Ukupno').startsWith('139.18'), 'iznos u mejlu odgovara korpi', uzmi('Ukupno'));
}

await p.goto(BAZA + '/hvala.html', { waitUntil: 'load' });
await p.waitForTimeout(1000);
ok(await p.evaluate(() => !localStorage.getItem('mmh_cart')), 'stranica "hvala" prazni korpu tek kad narudzba prodje');

ok(izuzeci.length === 0, 'nijedan JavaScript izuzetak', izuzeci.join(' | '));
console.log(`\nZAVRSNO: ${palo === 0 ? 'sve prolazi' : palo + ' provjera palo'}`);
await b.close();
process.exit(palo ? 1 : 0);
