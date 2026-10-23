/**
 * r2-server-pregledac.mjs — dva odvojena mjerenja iste stranice, polje po polju.
 *
 * A) SERVER    sta HTTP server vrati (curl, Googlebot user-agent, sirovi HTML)
 * B) PREGLEDAC sta pregledac ima poslije izvrsavanja JavaScripta
 *
 * Porede se imenovana polja, ne samo brojevi:
 *   title · meta description · canonical · meta robots · h1 · ime proizvoda ·
 *   cijena · Product schema (name, price, sku, image, availability) · broj slika ·
 *   broj internih linkova · tragovi recenzija i ocjena
 *
 * Zasto ovako:
 * Sve dosadasnje provjere gledale su samo stranu A. Recenzije su bile obrisane
 * sa servera i provjera je javljala "0 tragova", a vlasnik ih je gledao na
 * telefonu — jer je njegov pregledac imao stari js/products.js iz kesa. Razlika
 * izmedju A i B se ne vidi ako se mjeri samo A.
 *
 * Stranica koja se NIJE ucitala se prijavljuje kao greska, nikad kao "0 razlika".
 *
 *   node alat/r2-server-pregledac.mjs spisak.txt        # desktop + mobilni
 *   MMH_IZLAZ=/put/ime node alat/r2-server-pregledac.mjs spisak.txt
 */
import fs from 'fs';
import { execFileSync } from 'child_process';
import { createRequire } from 'module';
const require = createRequire(import.meta.url);

const ZIVI = 'https://makemyhome.me';
const LOKALNO = process.env.MMH_BAZA || 'http://127.0.0.1:8898';
const IZLAZ = process.env.MMH_IZLAZ || 'R2-SERVER-PREGLEDAC';
const GBOT_M = 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 '
             + '(KHTML, like Gecko) Chrome/125.0.0.0 Mobile Safari/537.36 '
             + '(compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
const GBOT_D = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

/** Tragovi recenzija i ocjena — ne smiju postojati ni na jednoj strani. */
const TRAGOVI = ['rv-card', 'rv-wrap', 'testimonial-card', 'aggregateRating',
                 'ratingValue', 'reviewCount', 'Ocjene korisnika', 'Google ocjena'];

function curl(url, ua) {
  try {
    return execFileSync('curl', ['-sk', '-L', '--cacert', '/root/.ccr/ca-bundle.crt',
      '--max-time', '30', '-A', ua, url], { maxBuffer: 40 * 1024 * 1024 }).toString('utf8');
  } catch (e) { return ''; }
}

/** Ista polja, izvucena iz SIROVOG HTML-a. */
function saServera(html) {
  const jedan = (rx) => { const m = html.match(rx); return m ? m[1].trim() : null; };
  const bezSkripti = html.replace(/<script[\s\S]*?<\/script>/gi, '');
  const ld = [];
  for (const m of html.matchAll(/<script[^>]*application\/ld\+json[^>]*>([\s\S]*?)<\/script>/gi)) {
    try { ld.push(JSON.parse(m[1])); } catch (e) { ld.push({ __nevalidan: true }); }
  }
  return {
    naslov: jedan(/<title>([^<]*)<\/title>/i),
    opis: jedan(/<meta[^>]*name="description"[^>]*content="([^"]*)"/i),
    canonical: jedan(/<link[^>]*rel="canonical"[^>]*href="([^"]*)"/i),
    robots: jedan(/<meta[^>]*name="robots"[^>]*content="([^"]*)"/i),
    h1: (bezSkripti.match(/<h1[\s>]/gi) || []).length,
    imeProizvoda: jedan(/<h1[^>]*class="product-name"[^>]*>([^<]*)<\/h1>/i),
    cijene: [...new Set((bezSkripti.match(/[0-9]+,[0-9]{2}\s*€/g) || []).map(x => x.trim()))].slice(0, 4),
    slike: (bezSkripti.match(/<img[\s>]/gi) || []).length,
    linkovi: (() => {
      // <base href> se MORA postovati: stranice na lijepim adresama nose
      // <base href="https://makemyhome.me/">, pa se "paneli/x" rjesava na
      // /paneli/x. Bez toga se server i pregledac ne mogu porediti.
      const mb = html.match(/<base\b[^>]+href="([^"]+)"/i);
      const osnova = mb ? mb[1] : ZIVI + '/';
      const put = new Set();
      for (const m of bezSkripti.matchAll(/<a\b[^>]+href="([^"#]+)"/g)) {
        try {
          const u = new URL(m[1], osnova);
          if (u.hostname === 'makemyhome.me' || u.hostname === '127.0.0.1') put.add(u.pathname);
        } catch (e) { /* mailto:, tel:, javascript: */ }
      }
      return put.size;
    })(),
    ld: ld.length,
    tragovi: TRAGOVI.filter(t => html.replace(/<!--[\s\S]*?-->/g, '').includes(t)),
    schema: izvuciProduct(ld),
  };
}

function izvuciProduct(ld) {
  const ravno = [];
  const razmotaj = (d) => {
    if (Array.isArray(d)) return d.forEach(razmotaj);
    if (d && typeof d === 'object') {
      if (d['@graph']) return razmotaj(d['@graph']);
      ravno.push(d);
      if (d.itemListElement) razmotaj(d.itemListElement.map(x => x.item).filter(Boolean));
    }
  };
  ld.forEach(razmotaj);
  const p = ravno.find(x => x['@type'] === 'Product');
  if (!p) return null;
  const of = Array.isArray(p.offers) ? p.offers[0] : p.offers || {};
  return {
    name: p.name || null, sku: p.sku || null,
    slika: Array.isArray(p.image) ? p.image[0] : p.image || null,
    price: of.price != null ? String(of.price) : null,
    valuta: of.priceCurrency || null,
    stanje: (of.availability || '').split('/').pop() || null,
    stanjeRobe: (of.itemCondition || '').split('/').pop() || null,
    imaOcjenu: !!(p.review || p.aggregateRating),
  };
}

/** Ista polja, ali iz zivog DOM-a. */
const IZ_DOMA = (TRAGOVI_UNUTRA) => {
  const t = (s) => (s || '').trim() || null;
  const txt = document.body.innerText || '';
  const ld = [...document.querySelectorAll('script[type="application/ld+json"]')]
    .map(s => { try { return JSON.parse(s.textContent); } catch (e) { return { __nevalidan: true }; } });
  return {
    naslov: t(document.title),
    opis: t(document.querySelector('meta[name="description"]')?.content),
    canonical: t(document.querySelector('link[rel="canonical"]')?.href),
    robots: t(document.querySelector('meta[name="robots"]')?.content),
    h1: document.querySelectorAll('h1').length,
    imeProizvoda: t(document.querySelector('h1.product-name')?.textContent),
    cijene: [...new Set((txt.match(/[0-9]+,[0-9]{2}\s*€/g) || []).map(x => x.trim()))].slice(0, 4),
    slike: document.querySelectorAll('img').length,
    /* a.href je RIJESENA adresa — pregledac je vec spojio sa <base href>.
       Sirovi atribut (getAttribute) daje "paneli/x" bez kose crte na pocetku, pa
       je filter po "/" izbacivao prave linkove i alat je prijavio da JavaScript
       uklanja osam internih linkova. Nije uklanjao nijedan. */
    linkovi: new Set([...document.querySelectorAll('a[href]')]
      .map(a => { try { return new URL(a.href).pathname; } catch (e) { return null; } })
      .filter(Boolean)).size,
    ld: ld.length,
    tragovi: TRAGOVI_UNUTRA.filter(x => document.documentElement.innerHTML.includes(x)),
    ldTekst: ld.map(x => JSON.stringify(x)),
  };
};

const putanje = fs.readFileSync(process.argv[2] || '/dev/stdin', 'utf8')
  .split('\n').map(s => s.trim()).filter(Boolean).map(u => u.replace(ZIVI, '') || '/');

const pw = require('/opt/node22/lib/node_modules/playwright/index.js');

(async () => {
  const browser = await pw.chromium.launch({ args: ['--no-sandbox', '--disable-dev-shm-usage'] });
  const uredjaji = [
    { ime: 'mobilni', viewport: { width: 412, height: 915 }, userAgent: GBOT_M },
    { ime: 'racunar', viewport: { width: 1366, height: 900 }, userAgent: GBOT_D },
  ];
  const konteksti = {};
  for (const u of uredjaji) {
    const c = await browser.newContext({ userAgent: u.userAgent, viewport: u.viewport });
    await c.route('**/*', (r) =>
      /^http:\/\/127\.0\.0\.1:/.test(r.request().url()) ? r.continue() : r.abort());
    konteksti[u.ime] = c;
  }

  const redovi = [];
  for (let k = 0; k < putanje.length; k += 3) {
    const grupa = putanje.slice(k, k + 3);
    const rez = await Promise.all(grupa.map(async (put) => {
      const red = { put, greska: null, razlike: [] };
      try {
        red.server = saServera(curl(ZIVI + put, GBOT_D));
        if (!red.server.naslov) { red.greska = 'server nije vratio stranicu (nema <title>)'; return red; }
        red.pregledac = {};
        for (const u of uredjaji) {
          const p = await konteksti[u.ime].newPage();
          const greske = [];
          p.on('pageerror', (e) => greske.push(String(e).slice(0, 100)));
          p.on('console', (m) => { if (m.type() === 'error') greske.push(m.text().slice(0, 100)); });
          const odg = await p.goto(LOKALNO + put, { waitUntil: 'domcontentloaded', timeout: 45000 });
          if (!odg || odg.status() >= 400) {
            red.greska = `pregledac (${u.ime}): status ${odg ? odg.status() : 'bez odgovora'}`;
            await p.close();
            return red;
          }
          await p.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
          await p.waitForTimeout(1500);
          red.pregledac[u.ime] = await p.evaluate(IZ_DOMA, TRAGOVI);
          red.pregledac[u.ime].jsGreske = greske;
          await p.close();
        }
      } catch (e) {
        red.greska = String(e).split('\n')[0].slice(0, 120);
        return red;
      }

      // ---- poredjenje ----
      const S = red.server;
      for (const [ime, B] of Object.entries(red.pregledac)) {
        const isto = (polje, a, b) => {
          if (a === b) return;
          red.razlike.push({ ime, polje, server: String(a), pregledac: String(b) });
        };
        // Lokalna kopija ima starije podatke od servera, pa se tekstualna polja
        // koja zavise od podataka porede po tome DA LI POSTOJE, ne po vrijednosti.
        if (!!S.naslov !== !!B.naslov) isto('title postoji', !!S.naslov, !!B.naslov);
        if (!!S.opis !== !!B.opis) isto('meta description postoji', !!S.opis, !!B.opis);
        if (!!S.canonical !== !!B.canonical) isto('canonical postoji', !!S.canonical, !!B.canonical);
        isto('meta robots', S.robots, B.robots);
        isto('broj h1', S.h1, B.h1);
        isto('broj JSON-LD blokova', S.ld, B.ld);
        if (B.tragovi.length > S.tragovi.length) {
          red.razlike.push({ ime, polje: 'TRAGOVI RECENZIJA', server: S.tragovi.join(',') || '(nema)',
                             pregledac: B.tragovi.join(',') });
        }
        if (S.slike > B.slike) isto('broj slika (pregledac ima manje)', S.slike, B.slike);
        if (S.linkovi > B.linkovi) isto('broj internih linkova (manje)', S.linkovi, B.linkovi);
        const nestale = S.cijene.filter(c => !B.cijene.includes(c));
        if (nestale.length && B.cijene.length) {
          red.razlike.push({ ime, polje: 'cijena nestala poslije JS-a',
                             server: S.cijene.join(' '), pregledac: B.cijene.join(' ') });
        }
      }
      if (S.schema && S.schema.imaOcjenu) {
        red.razlike.push({ ime: 'server', polje: 'Product schema ima ocjenu', server: 'da', pregledac: '-' });
      }
      return red;
    }));
    redovi.push(...rez);
    process.stderr.write(`[${redovi.length}/${putanje.length}]\n`);
  }
  await browser.close();

  const pukle = redovi.filter(x => x.greska);
  const sRazlikom = redovi.filter(x => !x.greska && x.razlike.length);
  const sJsGreskom = redovi.filter(x => !x.greska &&
    Object.values(x.pregledac || {}).some(b => (b.jsGreske || []).filter(g => !/ERR_/.test(g)).length));

  const L = [];
  const r = (s) => L.push(s);
  r('# Server naspram pregledaca — polje po polju');
  r('');
  r(`Napravljeno: ${new Date().toISOString().slice(0, 16).replace('T', ' ')} UTC`);
  r('');
  r(`Stranica: **${redovi.length}** · svaka mjerena kao **mobilni i racunar**.`);
  r('');
  r('- **A) server**: sirovi HTML sa zivog sajta, Googlebot user-agent, curl');
  r(`- **B) pregledac**: Chromium na lokalnoj kopiji (${LOKALNO}), poslije JavaScripta`);
  r('');
  r('> Lokalna kopija nosi starije podatke od servera (vlasnik ih mijenja kroz');
  r('> admin), pa se tekstualna polja porede po tome **da li postoje**, a');
  r('> strukturna — robots, broj h1, broj JSON-LD blokova, tragovi recenzija,');
  r('> nestale cijene, slike i linkovi — po **tacnoj vrijednosti**.');
  r('');
  r(`- stranica koje se nisu ucitale: **${pukle.length}**`);
  r(`- stranica sa razlikom server↔pregledac: **${sRazlikom.length}**`);
  r(`- stranica sa JavaScript greskom: **${sJsGreskom.length}**`);
  r('');
  if (pukle.length) {
    r('## [!!] Nisu izmjerene');
    r('');
    for (const x of pukle) r(`- \`${x.put}\` — ${x.greska}`);
    r('');
  }
  if (sRazlikom.length) {
    r('## [!!] Razlike');
    r('');
    for (const x of sRazlikom) {
      r(`**${x.put}**`);
      for (const d of x.razlike) r(`- ${d.ime} · ${d.polje}: server \`${d.server}\` · pregledac \`${d.pregledac}\``);
      r('');
    }
  }
  if (sJsGreskom.length) {
    r('## [!] JavaScript greske');
    r('');
    for (const x of sJsGreskom) {
      for (const [ime, b] of Object.entries(x.pregledac)) {
        for (const g of (b.jsGreske || []).filter(g => !/ERR_/.test(g))) r(`- \`${x.put}\` (${ime}): ${g}`);
      }
    }
    r('');
  }
  r('## Tabela');
  r('');
  r('| adresa | title | canonical | robots | h1 | LD | cijene (server) | tragovi | razlika |');
  r('|---|---|---|---|---|---|---|---|---|');
  for (const x of redovi) {
    if (x.greska) { r(`| \`${x.put}\` | — | — | — | — | — | — | — | **nije izmjereno** |`); continue; }
    const S = x.server;
    r(`| \`${x.put}\` | ${S.naslov ? 'da' : '**NE**'} | ${S.canonical ? 'da' : '**NE**'} `
      + `| ${S.robots || '–'} | ${S.h1} | ${S.ld} | ${S.cijene.length} `
      + `| ${S.tragovi.length ? '**' + S.tragovi.join(',') + '**' : 'nema'} `
      + `| ${x.razlike.length ? '**' + x.razlike.length + '**' : '0'} |`);
  }
  r('');
  r('## Product schema (samo stranice proizvoda)');
  r('');
  r('| adresa | name | sku | price | valuta | stanje | stanje robe | slika https | ocjena |');
  r('|---|---|---|---|---|---|---|---|---|');
  for (const x of redovi) {
    if (x.greska || !x.server?.schema) continue;
    const s = x.server.schema;
    r(`| \`${x.put}\` | ${s.name ? 'da' : '**NE**'} | ${s.sku || '**NE**'} | ${s.price || '**NE**'} `
      + `| ${s.valuta || '**NE**'} | ${s.stanje || '**NE**'} | ${s.stanjeRobe || '**NE**'} `
      + `| ${/^https:\/\//.test(s.slika || '') ? 'da' : '**NE**'} `
      + `| ${s.imaOcjenu ? '**IMA**' : 'nema'} |`);
  }

  fs.writeFileSync(IZLAZ + '.md', L.join('\n') + '\n');
  fs.writeFileSync(IZLAZ + '.json', JSON.stringify(redovi, null, 1));
  console.log(`Gotovo → ${IZLAZ}.md  (pukle ${pukle.length}, razlike ${sRazlikom.length}, `
            + `js greske ${sJsGreskom.length})`);
  if (pukle.length || sRazlikom.length) process.exitCode = 1;
})();
