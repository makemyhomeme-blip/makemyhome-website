/**
 * r2-resursi.mjs — svaki zahtjev koji pregledac napravi, i koliko to kosta.
 *
 * Pokriva ono sto se ne vidi iz HTML-a:
 *   · svaki zahtjev: adresa, status, vrsta, velicina, zaglavlje kesa
 *   · 4xx i 5xx na slikama, skriptama, stilovima, fontovima
 *   · mijesani sadrzaj (http:// na https:// stranici) i CORS odbijanja
 *   · ukupno bajtova po vrsti (JS, CSS, slike, fontovi, JSON)
 *   · LCP, CLS i odziv na prvi dodir (INP se ne moze izmjeriti bez pravog
 *     korisnika, pa se mjeri vrijeme obrade jednog klika kao priblizna mjera)
 *
 * Stranica koja se nije ucitala se prijavljuje kao NIJE IZMJERENO i alat vraca
 * izlazni kod 1. Nikad "0 problema" za stranicu koja nije otvorena.
 *
 * Ogranicenje: zaglavlja kesa se vide samo ako se mjeri zivi sajt. Lokalni
 * `php -S` ih ne salje, pa provjeru verzija na zivom sajtu rade pravila G16,
 * G17 i G24 u alat/provjera.py.
 *
 *   node alat/r2-resursi.mjs spisak.txt
 *   MMH_IZLAZ=/put/ime MMH_BAZA=http://127.0.0.1:8898 node alat/r2-resursi.mjs spisak.txt
 */
import fs from 'fs';
import { createRequire } from 'module';
const require = createRequire(import.meta.url);

const ZIVI = 'https://makemyhome.me';
const LOKALNO = process.env.MMH_BAZA || 'http://127.0.0.1:8898';
const IZLAZ = process.env.MMH_IZLAZ || 'R2-RESURSI';
const GBOT = 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 '
           + '(KHTML, like Gecko) Chrome/125.0.0.0 Mobile Safari/537.36 '
           + '(compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

/* Googleovi pragovi. Iznad njih se prijavljuje, ispod se samo ispise. */
const PRAG = { lcp: 2500, cls: 0.1, slika: 300 * 1024, js: 500 * 1024, css: 150 * 1024 };

const putanje = fs.readFileSync(process.argv[2] || '/dev/stdin', 'utf8')
  .split('\n').map(s => s.trim()).filter(Boolean).map(u => u.replace(ZIVI, '') || '/');

const pw = require('/opt/node22/lib/node_modules/playwright/index.js');

(async () => {
  const browser = await pw.chromium.launch({ args: ['--no-sandbox', '--disable-dev-shm-usage'] });
  const redovi = [];

  for (const put of putanje) {
    /* Dvije korpe:
       kriticno — kvar koji Google ili kupac odmah osjeti: 4xx/5xx, mijesani
                  sadrzaj, greska u konzoli, sredstvo koje se kesira godinu bez
                  verzije. Ovo obara provjeru.
       savjet   — velika slika, LCP ili CLS iznad Googleovog praga. Vrijedi
                  popraviti, ali su fotografije proizvoda vlasnikove i ne diraju
                  se bez dogovora, pa ovo NE obara provjeru. */
    const red = { put, greska: null, zahtjevi: [], kriticno: [], savjet: [], mjere: {} };
    const ctx = await browser.newContext({ userAgent: GBOT, viewport: { width: 412, height: 915 } });
    const p = await ctx.newPage();

    p.on('response', async (o) => {
      const u = o.url();
      // Tudji domeni (analitika, mape) se ne mjere — nisu nas kod, a iz ovog
      // okruzenja im nema pristupa pa bi svaki bio lazan nalaz.
      if (!/^https?:\/\/(127\.0\.0\.1|makemyhome\.me)/.test(u)) return;
      let velicina = 0;
      try { velicina = Number((await o.allHeaders())['content-length'] || 0); } catch (e) { /* prazno */ }
      if (!velicina) { try { velicina = (await o.body()).length; } catch (e) { /* prazno */ } }
      let kes = '';
      try { kes = (await o.allHeaders())['cache-control'] || ''; } catch (e) { /* prazno */ }
      red.zahtjevi.push({ url: u, status: o.status(), vrsta: o.request().resourceType(),
                          velicina, kes });
    });
    p.on('requestfailed', (q) => {
      const u = q.url();
      if (!/^https?:\/\/(127\.0\.0\.1|makemyhome\.me)/.test(u)) return;
      red.kriticno.push(`${q.resourceType()} nije uspio: ${u.slice(0, 90)} (${q.failure()?.errorText})`);
    });
    const konzola = [];
    p.on('pageerror', (e) => konzola.push('pageerror: ' + String(e).slice(0, 100)));
    p.on('console', (m) => { if (m.type() === 'error') konzola.push(m.text().slice(0, 100)); });

    try {
      await p.addInitScript(() => {
        window.__cls = 0; window.__lcp = 0;
        new PerformanceObserver((l) => {
          for (const e of l.getEntries()) if (!e.hadRecentInput) window.__cls += e.value;
        }).observe({ type: 'layout-shift', buffered: true });
        new PerformanceObserver((l) => {
          const e = l.getEntries(); window.__lcp = e[e.length - 1].startTime;
        }).observe({ type: 'largest-contentful-paint', buffered: true });
      });
      const odg = await p.goto(LOKALNO + put, { waitUntil: 'domcontentloaded', timeout: 45000 });
      if (!odg || odg.status() >= 400) {
        red.greska = `stranica: status ${odg ? odg.status() : 'bez odgovora'}`;
      } else {
        await p.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
        await p.waitForTimeout(2500);
        red.mjere = await p.evaluate(() => ({
          cls: window.__cls, lcp: window.__lcp,
          dom: document.querySelectorAll('*').length,
        }));
        // Priblizna mjera odziva: koliko traje obrada jednog klika na prazno mjesto.
        const t0 = Date.now();
        await p.mouse.click(5, 5).catch(() => {});
        await p.evaluate(() => new Promise(r => requestAnimationFrame(() => r())));
        red.mjere.odziv = Date.now() - t0;
      }
    } catch (e) {
      red.greska = String(e).split('\n')[0].slice(0, 120);
    }
    red.konzola = konzola.filter(g => !/ERR_/.test(g));
    await ctx.close();

    // ---- ocjena ----
    if (!red.greska) {
      for (const z of red.zahtjevi) {
        if (z.status >= 400) red.kriticno.push(`${z.status} ${z.vrsta}: ${z.url.slice(0, 90)}`);
        if (/^http:\/\/makemyhome\.me/.test(z.url)) red.kriticno.push(`mijesani sadrzaj: ${z.url.slice(0, 90)}`);
        if (z.vrsta === 'image' && z.velicina > PRAG.slika) {
          red.savjet.push(`slika ${Math.round(z.velicina / 1024)} kB: ${z.url.split('/').pop()}`);
        }
      }
      const zbir = (v) => red.zahtjevi.filter(z => z.vrsta === v)
        .reduce((s, z) => s + (z.velicina || 0), 0);
      red.zbir = { js: zbir('script'), css: zbir('stylesheet'), slike: zbir('image'),
                   font: zbir('font'), json: zbir('fetch') + zbir('xhr') };
      if (red.zbir.js > PRAG.js) red.savjet.push(`ukupno JS ${Math.round(red.zbir.js / 1024)} kB`);
      if (red.zbir.css > PRAG.css) red.savjet.push(`ukupno CSS ${Math.round(red.zbir.css / 1024)} kB`);
      if ((red.mjere.lcp || 0) > PRAG.lcp) red.savjet.push(`LCP ${Math.round(red.mjere.lcp)} ms`);
      if ((red.mjere.cls || 0) > PRAG.cls) red.savjet.push(`CLS ${red.mjere.cls.toFixed(3)}`);
      // Dugorocno kesiran fajl bez verzije u adresi — pregledac ga drzi godinu.
      for (const z of red.zahtjevi) {
        if (/immutable|max-age=31536000/.test(z.kes) && !/\?v=/.test(z.url)
            && !/\.(jpg|jpeg|png|webp|avif)$/i.test(z.url)) {
          red.kriticno.push(`kesira se godinu, a nema ?v=: ${z.url.slice(0, 90)}`);
        }
      }
    }
    redovi.push(red);
    process.stderr.write(`${put}  ${red.greska ? 'NIJE IZMJERENO'
      : `${red.zahtjevi.length} zahtjeva, kriticno ${red.kriticno.length}, savjet ${red.savjet.length}`}\n`);
  }
  await browser.close();

  const pukle = redovi.filter(x => x.greska);
  const sKriticnim = redovi.filter(x => !x.greska && x.kriticno.length);
  const sSavjetom = redovi.filter(x => !x.greska && x.savjet.length);
  const sKonzolom = redovi.filter(x => !x.greska && x.konzola.length);

  const L = [];
  const r = (s) => L.push(s);
  r('# Resursi, greske i brzina');
  r('');
  r(`Napravljeno: ${new Date().toISOString().slice(0, 16).replace('T', ' ')} UTC`);
  r('');
  r(`Stranica: **${redovi.length}**, mobilni ekran 412×915, Googlebot user-agent.`);
  r(`Mjereno na \`${LOKALNO}\` (lokalna kopija, slike se dovlace sa servera).`);
  r('');
  r(`- stranica koje se **nisu ucitale**: **${pukle.length}**  ← za njih vazi NIJE IZMJERENO`);
  r(`- stranica sa **kriticnim** nalazom (4xx/5xx, mijesani sadrzaj, kes bez verzije): **${sKriticnim.length}**`);
  r(`- stranica sa savjetom (velika slika, LCP, CLS): **${sSavjetom.length}**`);
  r(`- stranica sa greskom u konzoli: **${sKonzolom.length}**`);
  r('');
  if (pukle.length) {
    r('## [!!] NIJE IZMJERENO');
    r('');
    for (const x of pukle) r(`- \`${x.put}\` — ${x.greska}`);
    r('');
  }
  if (sKriticnim.length) {
    r('## [!!] Kriticno');
    r('');
    for (const x of sKriticnim) {
      r(`**${x.put}**`);
      for (const g of [...new Set(x.kriticno)]) r(`- ${g}`);
      r('');
    }
  }
  if (sSavjetom.length) {
    r('## [!] Savjeti (ne obaraju provjeru)');
    r('');
    r('Fotografije proizvoda su vlasnikove i ne mijenjaju se bez dogovora, pa');
    r('velika slika ostaje savjet a ne kvar.');
    r('');
    for (const x of sSavjetom) {
      r(`**${x.put}**`);
      for (const g of [...new Set(x.savjet)]) r(`- ${g}`);
      r('');
    }
  }
  if (sKonzolom.length) {
    r('## [!] Konzola');
    r('');
    for (const x of sKonzolom) for (const g of x.konzola) r(`- \`${x.put}\`: ${g}`);
    r('');
  }
  r('## Mjerenja');
  r('');
  r('| adresa | zahtjeva | 4xx/5xx | JS kB | CSS kB | slike kB | LCP ms | CLS | odziv ms | DOM |');
  r('|---|---|---|---|---|---|---|---|---|---|');
  for (const x of redovi) {
    if (x.greska) { r(`| \`${x.put}\` | — | — | — | — | — | — | — | — | **nije izmjereno** |`); continue; }
    const kb = (n) => Math.round((n || 0) / 1024);
    r(`| \`${x.put}\` | ${x.zahtjevi.length} | ${x.zahtjevi.filter(z => z.status >= 400).length} `
      + `| ${kb(x.zbir.js)} | ${kb(x.zbir.css)} | ${kb(x.zbir.slike)} `
      + `| ${Math.round(x.mjere.lcp || 0)} | ${(x.mjere.cls || 0).toFixed(3)} `
      + `| ${x.mjere.odziv ?? '–'} | ${x.mjere.dom ?? '–'} |`);
  }
  r('');
  r('## Zaglavlja kesa po vrsti sredstva');
  r('');
  r('> **Ogranicenje:** kad se mjeri na lokalnoj kopiji (`php -S`), zaglavlja kesa');
  r('> ne postoje — lokalni server ih ne salje, pa ovdje stoji „(nema)". Prava');
  r('> zaglavlja i verzije na zivom sajtu provjeravaju pravila G16, G17 i G24 u');
  r('> `alat/provjera.py`. Provjera „kesira se godinu a nema ?v=" u ovom alatu');
  r('> ima smisla samo ako se MMH_BAZA usmjeri na zivi sajt.');
  r('');
  const kesovi = {};
  for (const x of redovi) for (const z of x.zahtjevi) {
    const k = `${z.vrsta} · ${z.kes || '(nema)'}`;
    kesovi[k] = (kesovi[k] || 0) + 1;
  }
  r('| vrsta i zaglavlje | broj zahtjeva |');
  r('|---|---|');
  for (const [k, n] of Object.entries(kesovi).sort((a, b) => b[1] - a[1])) r(`| ${k} | ${n} |`);

  fs.writeFileSync(IZLAZ + '.md', L.join('\n') + '\n');
  fs.writeFileSync(IZLAZ + '.json', JSON.stringify(redovi, null, 1));
  console.log(`Gotovo → ${IZLAZ}.md  (nije izmjereno ${pukle.length}, kriticno ${sKriticnim.length}, `
            + `savjeti ${sSavjetom.length}, konzola ${sKonzolom.length})`);
  if (pukle.length || sKriticnim.length || sKonzolom.length) process.exitCode = 1;
})();
