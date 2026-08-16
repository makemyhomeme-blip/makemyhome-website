/**
 * r2-bljesak.mjs — da li sadrzaj bljesne, i koliko skace raspored.
 *
 * Zasto postoji:
 * Na /kategorija/bambus-paneli je server ispisivao 39 kartica, a JavaScript ih
 * je gasio. Posjetilac je vidio kartice pa ih vidio kako nestaju. To se ne vidi
 * ni u HTML-u ni u gotovom DOM-u — vidi se samo ako se gleda kroz vrijeme.
 *
 * Za svaku kategoriju:
 *   · snimak i brojanje na 200 ms, 600 ms, 1500 ms i 3000 ms od pocetka
 *   · ako broj kartica ili duzina teksta poraste pa padne — to je bljesak
 *   · mjeri se CLS (skok rasporeda) i LCP (kad se pojavi glavni sadrzaj)
 *   · provjerava se da li `loading="lazy"` krije proizvode od Googlebota
 *     (Googlebot ne skroluje; ono sto je lazy ispod pregiba mu je svejedno
 *     vidljivo u HTML-u, ali slika koja se nikad ne ucita ne ide u Slike)
 *
 * Mobilni ekran 412×915, Googlebot user-agent.
 *
 *   node alat/r2-bljesak.mjs   →  R2-BLJESAK.md + alat/snimci/*.png
 */
import fs from 'fs';
import path from 'path';
import { createRequire } from 'module';
const require = createRequire(import.meta.url);

const LOKALNO = 'http://127.0.0.1:8898';
const GBOT = 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 '
           + '(KHTML, like Gecko) Chrome/125.0.0.0 Mobile Safari/537.36 '
           + '(compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
const TRENUCI = [200, 600, 1500, 3000];
const SNIMCI = 'alat/snimci';

const KATEGORIJE = [
  'bambus-paneli', 'bambus-drveni', 'bambus-tekstilni', 'bambus-mermerni',
  'bambus-metalni', 'bambus-kozni', '3d-letvice', 'akusticni-paneli',
  'aluminijum-lajsne', 'spc-pod', 'pu-kamen', 'classic', 'mdf', 'flex-stone',
];

const MJERI = () => ({
  kartice: document.querySelectorAll('.product-card').length,
  vidljive: [...document.querySelectorAll('.product-card')]
    .filter(e => e.offsetParent !== null).length,
  tekst: (document.body.innerText || '').replace(/\s+/g, ' ').trim().length,
  lazy: document.querySelectorAll('img[loading="lazy"]').length,
  slikeBezIzvora: [...document.querySelectorAll('img')]
    .filter(i => !i.currentSrc && !i.src).length,
});

fs.mkdirSync(SNIMCI, { recursive: true });
const pw = require('/opt/node22/lib/node_modules/playwright/index.js');

(async () => {
  const browser = await pw.chromium.launch({ args: ['--no-sandbox', '--disable-dev-shm-usage'] });
  const ctx = await browser.newContext({
    userAgent: GBOT, viewport: { width: 412, height: 915 },
  });
  await ctx.route('**/*', (route) =>
    /^http:\/\/127\.0\.0\.1:/.test(route.request().url()) ? route.continue() : route.abort());

  const redovi = [];
  for (const k of KATEGORIJE) {
    const put = `/kategorija/${k}`;
    const p = await ctx.newPage();
    const red = { put, tacke: [], cls: null, lcp: null, greska: null };
    try {
      // Mjerenje CLS i LCP mora biti postavljeno prije nego stranica krene.
      await p.addInitScript(() => {
        window.__cls = 0; window.__lcp = 0;
        new PerformanceObserver((l) => {
          for (const e of l.getEntries()) if (!e.hadRecentInput) window.__cls += e.value;
        }).observe({ type: 'layout-shift', buffered: true });
        new PerformanceObserver((l) => {
          const e = l.getEntries(); window.__lcp = e[e.length - 1].startTime;
        }).observe({ type: 'largest-contentful-paint', buffered: true });
      });
      const t0 = Date.now();
      await p.goto(LOKALNO + put, { waitUntil: 'commit', timeout: 45000 });
      for (const ms of TRENUCI) {
        const cekaj = ms - (Date.now() - t0);
        if (cekaj > 0) await p.waitForTimeout(cekaj);
        const m = await p.evaluate(MJERI).catch(() => null);
        if (m) red.tacke.push({ ms, ...m });
        await p.screenshot({ path: path.join(SNIMCI, `${k}-${ms}ms.png`) }).catch(() => {});
      }
      red.cls = await p.evaluate(() => window.__cls).catch(() => null);
      red.lcp = await p.evaluate(() => window.__lcp).catch(() => null);
    } catch (e) {
      red.greska = String(e).split('\n')[0].slice(0, 120);
    }
    await p.close();

    // Bljesak: broj vidljivih kartica poraste pa padne.
    const v = red.tacke.map(t => t.vidljive);
    red.bljesak = v.some((x, i) => i > 0 && x < Math.max(...v.slice(0, i)));
    const t = red.tacke.map(x => x.tekst);
    red.tekstPada = t.some((x, i) => i > 0 && x < Math.max(...t.slice(0, i)) * 0.95);
    redovi.push(red);
    process.stderr.write(`${put}  kartice ${v.join('→')}  CLS ${Number(red.cls).toFixed(3)}\n`);
  }
  await browser.close();

  const L = [];
  const r = (s) => L.push(s);
  r('# R2 — bljesak, skok rasporeda i mobilni prikaz');
  r('');
  r(`Napravljeno: ${new Date().toISOString().slice(0, 16).replace('T', ' ')} UTC`);
  r('');
  r('Svaka kategorija je snimljena na 200, 600, 1500 i 3000 ms od pocetka ucitavanja,');
  r('na mobilnom ekranu 412×915, sa Googlebot user-agentom. Snimci su u `alat/snimci/`.');
  r('');
  r('**Bljesak** = broj VIDLJIVIH kartica proizvoda poraste pa padne. Tacno to se');
  r('desavalo na bambusu prije popravke: 39 kartica se iscrta, pa nestane.');
  r('');
  const sBljeskom = redovi.filter(x => x.bljesak || x.tekstPada);
  const losCls = redovi.filter(x => (x.cls ?? 0) > 0.1);
  const losLcp = redovi.filter(x => (x.lcp ?? 0) > 2500);
  r(`- kategorija sa bljeskom: **${sBljeskom.length}**`);
  r(`- kategorija sa CLS iznad 0,1 (Googleov prag): **${losCls.length}**`);
  r(`- kategorija sa LCP iznad 2,5 s: **${losLcp.length}**`);
  r('');
  r('| kategorija | kartice 200/600/1500/3000 ms | tekst 200→3000 ms | CLS | LCP | ocjena |');
  r('|---|---|---|---|---|---|');
  for (const x of redovi) {
    if (x.greska) { r(`| \`${x.put}\` | — | — | — | — | pukla: ${x.greska} |`); continue; }
    const kart = x.tacke.map(t => t.vidljive).join(' / ');
    const tek = `${x.tacke[0]?.tekst ?? '—'} → ${x.tacke[x.tacke.length - 1]?.tekst ?? '—'}`;
    const oc = x.bljesak ? '**[!!] bljesak**'
      : x.tekstPada ? '**[!] tekst nestane**'
      : (x.cls ?? 0) > 0.1 ? '[!] skace raspored'
      : '[i] uredu';
    r(`| \`${x.put}\` | ${kart} | ${tek} | ${Number(x.cls).toFixed(3)} `
      + `| ${Math.round(x.lcp)} ms | ${oc} |`);
  }
  r('');
  r('## Lazy loading');
  r('');
  r('Googlebot ne skroluje. `loading="lazy"` na slici ne krije samu karticu — kartica');
  r('je u HTML-u — ali slika koja se nikad ne ucita ne moze uci u Google Slike.');
  r('');
  r('| kategorija | slika sa lazy | slika bez izvora poslije 3 s |');
  r('|---|---|---|');
  for (const x of redovi) {
    if (x.greska) continue;
    const z = x.tacke[x.tacke.length - 1];
    r(`| \`${x.put}\` | ${z?.lazy ?? '—'} | ${z?.slikeBezIzvora ?? '—'} |`);
  }

  fs.writeFileSync('R2-BLJESAK.md', L.join('\n') + '\n');
  fs.writeFileSync('R2-BLJESAK.json', JSON.stringify(redovi, null, 1));
  console.log(`Gotovo → R2-BLJESAK.md  (bljesak ${sBljeskom.length}, CLS>0,1 ${losCls.length}, LCP>2,5s ${losLcp.length})`);
})();
