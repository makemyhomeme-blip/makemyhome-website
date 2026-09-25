/**
 * r2-jsonld.mjs — vidi li Google nase strukturirane podatke.
 *
 * Za svaku adresu:
 *   · JSON-LD iz SIROVOG HTML-a (curl, Googlebot UA, sa zivog sajta)
 *   · JSON-LD iz SIROVOG HTML-a lokalne kopije (isti kod, radi tacnog poredjenja)
 *   · JSON-LD iz ISCRTANOG DOM-a (pravi Chromium, poslije JavaScripta)
 * pa poredi broj blokova i spisak @type.
 *
 * Oznake:
 *   [!!] blok postoji u DOM-u a nema ga u sirovom HTML-u — ubacuje ga
 *        JavaScript, a Google ga u prvom prolazu cesto ne pokupi
 *   [!!] blok postoji u sirovom HTML-u a nestane iz DOM-a — JavaScript ga
 *        brise ili prepisuje (isti obrazac kao sa mrezom proizvoda)
 *   [!!] blok nije ispravan JSON
 *   [!!] nedostaje obavezno polje
 *   [!]  nedostaje preporuceno polje
 *
 * Obavezna polja se provjeravaju po Googleovoj dokumentaciji:
 *   Product        name, image (apsolutna adresa), offers.price,
 *                  offers.priceCurrency, offers.availability
 *   LocalBusiness  address, telephone, openingHours(Specification)
 *   BreadcrumbList itemListElement sa position 1..N bez rupa, svaki sa name
 *
 * Rich Results Test nema javni API (Googleov alat trazi prijavu), pa se ovdje
 * provjeravaju ista polja koja on trazi, a greske i upozorenja se broje odvojeno.
 *
 *   node alat/r2-jsonld.mjs spisak.txt   →  R2-JSONLD.md
 */
import fs from 'fs';
import { execFileSync } from 'child_process';
import { createRequire } from 'module';
const require = createRequire(import.meta.url);

const ZIVI = 'https://makemyhome.me';
const LOKALNO = 'http://127.0.0.1:8898';
const GBOT = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
const IZLAZ = process.env.MMH_IZLAZ || 'R2-JSONLD';

function curl(url) {
  try {
    /* -L: prati preusmjerenja. Bez toga je lokalna kopija za jedan proizvod
       vratila prazno telo 301 odgovora, pa je alat prijavio da "JavaScript doda
       2 JSON-LD bloka" — a stranica nije ni bila ucitana. Lokalni
       data/products.json je stariji od serverskog (vlasnik ga mijenja kroz
       admin), pa se za neke proizvode racuna drugi slug i product.php 301-uje. */
    return execFileSync('curl', ['-sk', '-L', '--cacert', '/root/.ccr/ca-bundle.crt',
      '--max-time', '30', '-A', GBOT, url], { maxBuffer: 40 * 1024 * 1024 }).toString('utf8');
  } catch (e) { return ''; }
}

/** Izvuci sirov tekst svakog ld+json bloka iz HTML-a. */
function blokoviIzHtml(html) {
  const out = [];
  const rx = /<script[^>]*type=["']application\/ld\+json["'][^>]*>([\s\S]*?)<\/script>/gi;
  let m;
  while ((m = rx.exec(html)) !== null) out.push(m[1]);
  return out;
}

/** Razmotaj @graph i liste u ravan spisak cvorova. */
function cvorovi(d, izlaz = []) {
  if (Array.isArray(d)) { for (const x of d) cvorovi(x, izlaz); return izlaz; }
  if (d && typeof d === 'object') {
    if (d['@graph']) { cvorovi(d['@graph'], izlaz); return izlaz; }
    izlaz.push(d);
  }
  return izlaz;
}

function tipoviOd(tekstovi) {
  const t = [];
  let nevalidnih = 0;
  for (const s of tekstovi) {
    let d;
    try { d = JSON.parse(s); } catch (e) { nevalidnih++; continue; }
    for (const c of cvorovi(d)) {
      const v = c['@type'];
      if (Array.isArray(v)) t.push(...v);
      else if (v) t.push(v);
    }
  }
  return { tipovi: t.sort(), nevalidnih };
}

/** Obavezna i preporucena polja — isto sto Rich Results Test trazi. */
function poljaGreske(tekstovi, url) {
  const greske = [];
  const upozorenja = [];
  for (const s of tekstovi) {
    let d;
    try { d = JSON.parse(s); } catch (e) { greske.push('blok nije ispravan JSON'); continue; }
    for (const c of cvorovi(d)) {
      const tip = Array.isArray(c['@type']) ? c['@type'][0] : c['@type'];

      if (tip === 'Product') {
        if (!c.name) greske.push('Product bez `name`');
        const sl = c.image;
        const slike = Array.isArray(sl) ? sl : (sl ? [sl] : []);
        if (!slike.length) greske.push('Product bez `image`');
        for (const s2 of slike) {
          const adr = typeof s2 === 'string' ? s2 : (s2 && s2.url) || '';
          if (!/^https?:\/\//.test(adr)) greske.push(`Product ima relativnu sliku: ${String(adr).slice(0, 60)}`);
        }
        const of = c.offers;
        const ponude = Array.isArray(of) ? of : (of ? [of] : []);
        if (!ponude.length) greske.push('Product bez `offers`');
        for (const o of ponude) {
          if (o.price === undefined && !(o.priceSpecification || {}).price) greske.push('Offer bez `price`');
          if (!o.priceCurrency && !(o.priceSpecification || {}).priceCurrency) greske.push('Offer bez `priceCurrency`');
          if (!o.availability) greske.push('Offer bez `availability`');
          if (!o.priceValidUntil) upozorenja.push('Offer bez `priceValidUntil`');
          if (!o.url) upozorenja.push('Offer bez `url`');
        }
        if (!c.sku && !c.gtin && !c.mpn) upozorenja.push('Product bez `sku`/`gtin`/`mpn`');
        if (!c.brand) upozorenja.push('Product bez `brand`');
        if (!c.description) upozorenja.push('Product bez `description`');
      }

      if (tip === 'LocalBusiness' || tip === 'HomeGoodsStore' || tip === 'Store'
          || tip === 'FurnitureStore') {
        if (!c.address) greske.push(`${tip} bez \`address\``);
        else {
          const a = c.address;
          for (const p of ['streetAddress', 'addressLocality', 'addressCountry']) {
            if (!a[p]) upozorenja.push(`address bez \`${p}\``);
          }
        }
        if (!c.telephone) greske.push(`${tip} bez \`telephone\``);
        if (!c.openingHours && !c.openingHoursSpecification) greske.push(`${tip} bez \`openingHours\``);
        if (!c.image) upozorenja.push(`${tip} bez \`image\``);
        if (!c.priceRange) upozorenja.push(`${tip} bez \`priceRange\``);
      }

      if (tip === 'BreadcrumbList') {
        const st = c.itemListElement;
        if (!Array.isArray(st) || !st.length) {
          greske.push('BreadcrumbList bez `itemListElement`');
        } else {
          const pos = st.map(x => Number(x.position));
          const ocekivano = st.map((_, i) => i + 1);
          if (JSON.stringify(pos) !== JSON.stringify(ocekivano)) {
            greske.push(`BreadcrumbList: position ${pos.join(',')} umjesto ${ocekivano.join(',')}`);
          }
          for (const x of st) {
            if (!x.name && !(x.item && x.item.name)) greske.push('ListItem bez `name`');
            if (x !== st[st.length - 1] && !x.item) greske.push('ListItem bez `item`');
          }
        }
      }

      if (tip === 'ItemList') {
        const st = c.itemListElement;
        if (!Array.isArray(st) || !st.length) greske.push('ItemList bez `itemListElement`');
      }
    }
  }
  return { greske: [...new Set(greske)], upozorenja: [...new Set(upozorenja)] };
}

/* Kao i kod r2-render.mjs: ako se preda .json, ne mjeri se nista nego se
   izvjestaj sastavi od vec izmjerenih dijelova. */
const ULAZ = process.argv[2] || '/dev/stdin';
const SPAJANJE = ULAZ.endsWith('.json');

const putanje = SPAJANJE ? [] : fs.readFileSync(ULAZ, 'utf8')
  .split('\n').map(s => s.trim()).filter(Boolean)
  .map(u => u.replace(ZIVI, '') || '/');

const pw = require('/opt/node22/lib/node_modules/playwright/index.js');

(async () => {
  if (SPAJANJE) { napraviIzvjestaj(JSON.parse(fs.readFileSync(ULAZ, 'utf8'))); return; }
  const browser = await pw.chromium.launch({ args: ['--no-sandbox', '--disable-dev-shm-usage'] });
  const ctx = await browser.newContext({ userAgent: GBOT, viewport: { width: 412, height: 915 } });
  await ctx.route('**/*', (r) =>
    /^http:\/\/127\.0\.0\.1:/.test(r.request().url()) ? r.continue() : r.abort());

  const redovi = [];
  let gotovo = 0;
  for (let k = 0; k < putanje.length; k += 3) {
    const grupa = putanje.slice(k, k + 3);
    const rez = await Promise.all(grupa.map(async (put) => {
      const red = { put };
      try {
        const sirovoZivi = blokoviIzHtml(curl(ZIVI + put));
        const sirovoLok = blokoviIzHtml(curl('http://127.0.0.1:8899' + put));
        const p = await ctx.newPage();
        await p.goto(LOKALNO + put, { waitUntil: 'domcontentloaded', timeout: 45000 });
        await p.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
        await p.waitForTimeout(2000);
        const dom = await p.evaluate(() =>
          [...document.querySelectorAll('script[type="application/ld+json"]')].map(s => s.textContent || ''));
        await p.close();

        red.zivi = tipoviOd(sirovoZivi);
        red.lok = tipoviOd(sirovoLok);
        red.dom = tipoviOd(dom);
        red.brojZivi = sirovoZivi.length;
        red.brojLok = sirovoLok.length;
        red.brojDom = dom.length;
        Object.assign(red, poljaGreske(sirovoZivi, put));

        const oznake = [];
        if (red.brojDom > red.brojLok) {
          oznake.push(`[!!] JavaScript doda ${red.brojDom - red.brojLok} JSON-LD blok(ova) — Google ih u prvom prolazu ne vidi`);
        }
        if (red.brojDom < red.brojLok) {
          oznake.push(`[!!] JavaScript ukloni ${red.brojLok - red.brojDom} JSON-LD blok(ova)`);
        }
        if (JSON.stringify(red.lok.tipovi) !== JSON.stringify(red.dom.tipovi)) {
          oznake.push('[!!] spisak @type nije isti prije i poslije JavaScripta');
        }
        if (JSON.stringify(red.zivi.tipovi) !== JSON.stringify(red.lok.tipovi)) {
          oznake.push('[!] zivi sajt i lokalna kopija nemaju isti spisak @type');
        }
        if (red.zivi.nevalidnih) oznake.push(`[!!] ${red.zivi.nevalidnih} blok(ova) nije ispravan JSON`);
        for (const g of red.greske) oznake.push(`[!!] ${g}`);
        for (const g of red.upozorenja) oznake.push(`[!] ${g}`);
        red.oznake = oznake;
      } catch (e) {
        red.greska = String(e).split('\n')[0].slice(0, 120);
      }
      return red;
    }));
    redovi.push(...rez);
    gotovo += grupa.length;
    process.stderr.write(`[${gotovo}/${putanje.length}]\n`);
    fs.writeFileSync(IZLAZ + '.json', JSON.stringify(redovi, null, 1));
  }
  await browser.close();
  napraviIzvjestaj(redovi);
})();

function napraviIzvjestaj(redovi) {
  const L = [];
  const r = (s) => L.push(s);
  r('# R2 — JSON-LD: sirovi HTML naspram iscrtanog DOM-a');
  r('');
  r(`Napravljeno: ${new Date().toISOString().slice(0, 16).replace('T', ' ')} UTC`);
  r('');
  r('Za svaku adresu tri mjerenja: sirovi HTML sa **zivog** sajta (curl, Googlebot),');
  r('sirovi HTML **lokalne kopije** i **iscrtani DOM** poslije JavaScripta.');
  r('');
  const lose = redovi.filter(x => (x.oznake || []).some(o => o.startsWith('[!!]')));
  const upoz = redovi.filter(x => (x.oznake || []).some(o => o.startsWith('[!]')) && !lose.includes(x));
  r(`- stranica sa greskom **[!!]**: **${lose.length}**`);
  r(`- stranica samo sa upozorenjem **[!]**: **${upoz.length}**`);
  r(`- stranica bez ijednog JSON-LD bloka: **${redovi.filter(x => x.brojZivi === 0).length}**`);
  r('');
  if (lose.length) {
    r('## Greske');
    r('');
    for (const x of lose) {
      r(`**${x.put}**`);
      for (const o of x.oznake.filter(o => o.startsWith('[!!]'))) r(`- ${o}`);
      r('');
    }
  } else {
    r('[i] Nijedna stranica nema gresku u strukturiranim podacima.');
    r('');
  }
  if (upoz.length) {
    r('## Upozorenja (preporucena polja)');
    r('');
    const zbir = {};
    for (const x of upoz) for (const o of x.oznake.filter(o => o.startsWith('[!]'))) {
      zbir[o] = (zbir[o] || 0) + 1;
    }
    for (const [o, n] of Object.entries(zbir).sort((a, b) => b[1] - a[1])) r(`- ${o} — na ${n} stranica`);
    r('');
  }
  r('## Tabela');
  r('');
  r('| adresa | @type u sirovom (zivi) | @type u DOM-u | blokova sirovo/DOM | validan | greske |');
  r('|---|---|---|---|---|---|');
  for (const x of redovi) {
    if (x.greska) { r(`| \`${x.put}\` | — | — | — | — | pukla: ${x.greska} |`); continue; }
    const kratko = (t) => [...new Set(t)].join(', ') || '—';
    const val = x.zivi.nevalidnih === 0 ? 'da' : `**NE (${x.zivi.nevalidnih})**`;
    const gr = x.greske.length ? x.greske.join('; ') : '—';
    r(`| \`${x.put}\` | ${kratko(x.zivi.tipovi)} | ${kratko(x.dom.tipovi)} `
      + `| ${x.brojZivi}/${x.brojDom} | ${val} | ${gr} |`);
  }
  r('');
  r('## Rich Results Test');
  r('');
  r('Googleov Rich Results Test nema javni API — alat je web stranica koja trazi');
  r('prijavu, pa se ne moze pozvati iz skripte. Ovdje su provjerena ista polja koja');
  r('on trazi, po Googleovoj dokumentaciji, i greske su odvojene od upozorenja:');
  r('');
  r('- **Product**: `name`, `image` (apsolutna adresa), `offers.price`,');
  r('  `offers.priceCurrency`, `offers.availability` — obavezno;');
  r('  `sku`, `brand`, `description`, `priceValidUntil`, `offers.url` — preporuceno');
  r('- **LocalBusiness**: `address`, `telephone`, `openingHours` — obavezno;');
  r('  `image`, `priceRange` — preporuceno');
  r('- **BreadcrumbList**: `itemListElement` sa `position` 1..N bez rupa, svaki sa `name`');

  fs.writeFileSync(IZLAZ + '.md', L.join('\n') + '\n');
  const pukle = redovi.filter(x => x.greska);
  if (pukle.length) {
    console.log(`  [!!] stranica koje se nisu ucitale: ${pukle.length} — nisu provjerene`);
  }
  console.log(`Gotovo → ${IZLAZ}.md  (greske ${lose.length}, upozorenja ${upoz.length}, `
            + `pukle ${pukle.length})`);
  if (lose.length || pukle.length) process.exitCode = 1;
}
