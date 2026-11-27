/**
 * js-check.js — sta se desava u pravom pregledacu, ocima Googlebota.
 *
 * Za 15 kljucnih stranica, sa Googlebot user-agentom i mobilnim ekranom:
 *   · hvata JavaScript greske, greske u konzoli, neuspjele zahtjeve i svaki
 *     odgovor sa statusom 400+
 *   · cita localStorage i sessionStorage
 *   · iz gotovog DOM-a vadi title, lang, canonical, meta robots, broj h1/h2,
 *     broj JSON-LD blokova, slike (ukupno / bez alt / neucitane), duzinu
 *     teksta i broj pojava €
 *   · KLJUCNO: povlaci SIROVI HTML prije JavaScripta i uporedjuje broj € i
 *     duzinu teksta sa onim poslije iscrtavanja. Ako se cijene pojavljuju tek
 *     poslije JavaScripta, to je [!!] — Google ih u prvom prolazu ne vidi.
 *
 * Pokretanje:  node js-check.js      →  JS-RAPORT.md
 */
const fs = require('fs');
const { execFileSync } = require('child_process');

// Pregledac: koristi se onaj koji je vec u okruzenju, da se nista ne skida.
let puppeteer, opcijePokretanja = { args: ['--no-sandbox', '--disable-dev-shm-usage'] };
try {
  puppeteer = require('puppeteer');
} catch (e) {
  // Puppeteer nije instaliran — koristi se Playwright-ov Chromium preko
  // puppeteer-core ako postoji, inace Playwright direktno. Rezultat je isti:
  // pravi Chromium, pravi DOM.
  puppeteer = null;
}

const ZIVI = 'https://makemyhome.me';
const LOKALNO = 'http://127.0.0.1:8898';   // php -S 8899 + alat/posrednik.mjs
// Pregledac iz ovog okruzenja ne moze do zivog sajta — proxy propusta curl ali
// ne i Chromium (ERR_CONNECTION_RESET). Zato se DOM mjeri na lokalnoj kopiji
// ISTOG koda, a sirovi HTML se i dalje povlaci sa ZIVOG sajta preko curl-a.
// To je posteno razdvojeno i tako je i oznaceno u izvjestaju.
let BAZA = ZIVI;
let naLokalnom = false;
const GBOT = 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 '
           + '(KHTML, like Gecko) Chrome/125.0.0.0 Mobile Safari/537.36 '
           + '(compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

const STRANICE = [
  '/',
  '/products.html',
  '/kategorija/bambus-paneli',
  '/kategorija/3d-letvice',
  '/kategorija/akusticni-paneli',
  '/kategorija/mdf',
  '/paneli/drveni-panel-golden-teak',
  '/paneli/3d-letvica-obsidian',
  '/inspiracija.html',
  '/cjenovnik.html',
  '/montaza.html',
  '/faq.html',
  '/decor-box.html',
  '/contact.html',
  '/korpa.html',
];

/** Sirovi HTML — onako kako ga server posalje, bez ijedne linije JavaScripta. */
function sirovi(put) {
  try {
    return execFileSync('curl', [
      '-sk', '--cacert', '/root/.ccr/ca-bundle.crt', '--max-time', '30',
      '-A', GBOT, ZIVI + put,          // sirovi HTML UVIJEK sa zivog sajta
    ], { maxBuffer: 40 * 1024 * 1024 }).toString('utf8');
  } catch (e) { return ''; }
}

function brojEura(s) { return (s.match(/€/g) || []).length; }
function tekstIzHtml(s) {
  return s.replace(/<script[\s\S]*?<\/script>/g, '')
          .replace(/<style[\s\S]*?<\/style>/g, '')
          .replace(/<[^>]+>/g, ' ')
          .replace(/\s+/g, ' ').trim();
}

(async () => {
  let chromium;
  if (puppeteer) {
    chromium = puppeteer;
  } else {
    const pw = require('/opt/node22/lib/node_modules/playwright/index.js');
    chromium = null;
    var playwrightChromium = pw.chromium;
  }

  const red = [];
  const izvj = [];
  const dodaj = (s) => izvj.push(s);

  dodaj('# JS provjera — makemyhome.me');
  dodaj('');
  dodaj(`Napravljeno: ${new Date().toISOString().slice(0, 16).replace('T', ' ')} UTC`);
  dodaj('');
  dodaj('Pravi Chromium, Googlebot user-agent, mobilni ekran 412×915.');
  dodaj('');
  dodaj('**[!!]** blokator · **[!]** ozbiljno · **[i]** informacija');

  let browser;
  if (chromium) browser = await chromium.launch(opcijePokretanja);
  else browser = await playwrightChromium.launch();

  // Moze li pregledac uopste do zivog sajta iz ovog okruzenja?
  {
    const p0 = chromium ? await browser.newPage() : await (await browser.newContext()).newPage();
    try {
      await p0.goto(ZIVI + '/', { waitUntil: 'domcontentloaded', timeout: 20000 });
    } catch (e) {
      naLokalnom = true;
      BAZA = LOKALNO;
      dodaj('');
      dodaj('> **Napomena o mjerenju.** Pregledac iz ovog okruzenja ne moze do zivog');
      dodaj('> sajta — proxy propusta `curl` ali ne i Chromium. Zato je **iscrtani DOM**');
      dodaj('> mjeren na lokalnoj kopiji istog koda (`php -S` + `alat/posrednik.mjs`),');
      dodaj('> a **sirovi HTML** je i dalje povucen sa **zivog sajta**. Kod je isti —');
      dodaj('> pravilo G4 provjerava da git, cPanel i sajt nose iste fajlove. Razlika');
      dodaj('> je samo u podacima: `data/products.json` je na serveru noviji jer ga');
      dodaj('> vlasnik mijenja kroz admin.');
    }
    await p0.close();
  }

  for (const put of STRANICE) {
    const url = BAZA + put;
    const nalazi = { js: [], konzola: [], neuspjeli: [], status4xx: [] };

    let page;
    if (chromium) {
      page = await browser.newPage();
      await page.setUserAgent(GBOT);
      await page.setViewport({ width: 412, height: 915, isMobile: true });
    } else {
      const ctx = await browser.newContext({
        userAgent: GBOT, viewport: { width: 412, height: 915 }, isMobile: true,
      });
      page = await ctx.newPage();
    }

    page.on('pageerror', (e) => nalazi.js.push(String(e.message).split('\n')[0]));
    page.on('console', (m) => {
      const t = m.type();
      if (t === 'error' || t === 'warning') nalazi.konzola.push(`${t}: ${m.text().slice(0, 140)}`);
    });
    page.on('requestfailed', (rq) => {
      const u = rq.url();
      if (!/googletagmanager|google-analytics|doubleclick/.test(u)) {
        nalazi.neuspjeli.push(u.slice(0, 130));
      }
    });
    page.on('response', (rs) => {
      if (rs.status() >= 400) nalazi.status4xx.push(`${rs.status()} ${rs.url().slice(0, 120)}`);
    });

    let greskaUcitavanja = null;
    try {
      await page.goto(url, { waitUntil: 'networkidle0', timeout: 60000 });
    } catch (e) {
      try { await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 }); }
      catch (e2) { greskaUcitavanja = String(e2.message).split('\n')[0]; }
    }
    if (!greskaUcitavanja) {
      await new Promise((r) => setTimeout(r, 1500));
    }

    let d = null;
    if (!greskaUcitavanja) {
      d = await page.evaluate(() => {
        const q = (s) => document.querySelector(s);
        const slike = [...document.images];
        return {
          title: document.title || '',
          lang: document.documentElement.getAttribute('lang') || '',
          canonical: (q('link[rel="canonical"]') || {}).href || '',
          robots: (q('meta[name="robots"]') || {}).content || '',
          h1: document.querySelectorAll('h1').length,
          h2: document.querySelectorAll('h2').length,
          jsonld: document.querySelectorAll('script[type="application/ld+json"]').length,
          slikaUkupno: slike.length,
          slikaBezAlt: slike.filter((i) => !i.getAttribute('alt')).length,
          slikaNeucitane: slike.filter((i) => i.complete && i.naturalWidth === 0).length,
          duzinaTeksta: (document.body.innerText || '').replace(/\s+/g, ' ').trim().length,
          eura: ((document.body.innerText || '').match(/€/g) || []).length,
          localStorage: Object.keys(localStorage || {}),
          sessionStorage: Object.keys(sessionStorage || {}),
        };
      });
    }

    // Sirovi HTML — prije JavaScripta
    const sir = sirovi(put);
    const sirEur = brojEura(sir);
    const sirTekst = tekstIzHtml(sir).length;

    dodaj('');
    dodaj(`## ${put}`);
    dodaj('');
    if (greskaUcitavanja) {
      dodaj(`[!!] Stranica se ne ucitava: \`${greskaUcitavanja}\``);
      red.push({ put, ok: false });
      await page.close();
      continue;
    }

    dodaj('```');
    dodaj(`title           ${d.title.slice(0, 70)}`);
    dodaj(`lang            ${d.lang || '(nema)'}`);
    dodaj(`canonical       ${d.canonical || '(nema)'}`);
    dodaj(`meta robots     ${d.robots || '(nema)'}`);
    dodaj(`h1 / h2         ${d.h1} / ${d.h2}`);
    dodaj(`JSON-LD blokova ${d.jsonld}`);
    dodaj(`slike           ${d.slikaUkupno} ukupno · ${d.slikaBezAlt} bez alt · ${d.slikaNeucitane} neucitanih`);
    dodaj(`localStorage    ${d.localStorage.join(', ') || '(prazno)'}`);
    dodaj(`sessionStorage  ${d.sessionStorage.join(', ') || '(prazno)'}`);
    dodaj('```');
    dodaj('');
    dodaj('**Sirovi HTML (bez JS) naspram iscrtanog:**');
    dodaj('');
    dodaj('```');
    dodaj(`                 sirovi HTML   poslije JS`);
    dodaj(`znak €           ${String(sirEur).padEnd(13)} ${d.eura}`);
    dodaj(`duzina teksta    ${String(sirTekst).padEnd(13)} ${d.duzinaTeksta}`);
    dodaj('```');

    const cijeneTekPosleJs = sirEur === 0 && d.eura > 0;
    const tekstTekPosleJs = sirTekst > 0 && d.duzinaTeksta > sirTekst * 2.5;
    if (cijeneTekPosleJs) dodaj('[!!] Cijene se pojavljuju TEK poslije JavaScripta — Google ih u prvom prolazu ne vidi.');
    else if (sirEur > 0) dodaj('[i] Cijene postoje u sirovom HTML-u — server ih ispisuje.');
    if (tekstTekPosleJs) dodaj('[!!] Vecina teksta dolazi tek poslije JavaScripta.');

    if (d.h1 !== 1) dodaj(`[!] Broj H1 je ${d.h1}, treba tacno 1.`);
    if (!d.canonical) dodaj('[!!] Nema canonical.');
    if (/noindex/i.test(d.robots)) dodaj(`[!!] meta robots sadrzi noindex: \`${d.robots}\``);
    if (d.slikaBezAlt > 0) dodaj(`[!] ${d.slikaBezAlt} slika bez alt opisa.`);
    if (d.slikaNeucitane > 0) dodaj(`[!] ${d.slikaNeucitane} slika se ne ucitava.`);
    if (d.jsonld === 0) dodaj('[!] Nema nijedan JSON-LD blok.');

    const ispis = (naslov, niz, oznaka) => {
      if (!niz.length) return;
      dodaj('');
      dodaj(`${oznaka} ${naslov}: ${niz.length}`);
      dodaj('```');
      [...new Set(niz)].slice(0, 6).forEach((x) => dodaj('  ' + x));
      dodaj('```');
    };
    ispis('JavaScript greske', nalazi.js, '[!!]');
    ispis('Greske i upozorenja u konzoli', nalazi.konzola, '[!]');
    ispis('Neuspjeli zahtjevi', nalazi.neuspjeli, '[!]');
    ispis('Odgovori sa statusom 400+', nalazi.status4xx, '[!]');

    if (!nalazi.js.length && !nalazi.neuspjeli.length && !nalazi.status4xx.length) {
      dodaj('');
      dodaj('[i] Bez JavaScript gresaka i bez neuspjelih zahtjeva.');
    }

    red.push({
      put, ok: true, sirEur, jsEur: d.eura, sirTekst, jsTekst: d.duzinaTeksta,
      h1: d.h1, jsonld: d.jsonld, greske: nalazi.js.length,
      neuspjeli: nalazi.neuspjeli.length, s4xx: nalazi.status4xx.length,
    });
    await page.close();
  }

  await browser.close();

  // ------------------------------------------------------------- ZBIRNO ----
  dodaj('');
  dodaj('---');
  dodaj('');
  dodaj('## Zbirna tabela');
  dodaj('');
  dodaj('| stranica | € sirovo | € poslije JS | tekst sirovo | tekst JS | h1 | JSON-LD | JS greske | neuspjeli | 4xx |');
  dodaj('|---|---|---|---|---|---|---|---|---|---|');
  for (const x of red) {
    if (!x.ok) { dodaj(`| ${x.put} | — | — | — | — | — | — | NE UCITAVA SE | — | — |`); continue; }
    dodaj(`| ${x.put} | ${x.sirEur} | ${x.jsEur} | ${x.sirTekst} | ${x.jsTekst} | ${x.h1} | ${x.jsonld} | ${x.greske} | ${x.neuspjeli} | ${x.s4xx} |`);
  }
  dodaj('');
  const blokatori = red.filter((x) => x.ok && x.sirEur === 0 && x.jsEur > 0);
  if (blokatori.length) {
    dodaj(`[!!] Na ${blokatori.length} stranica cijene se pojavljuju tek poslije JavaScripta:`);
    dodaj('```');
    blokatori.forEach((x) => dodaj('  ' + x.put));
    dodaj('```');
  } else {
    dodaj('[i] Ni na jednoj stranici cijene ne zavise od JavaScripta.');
  }

  fs.writeFileSync('JS-RAPORT.md', izvj.join('\n') + '\n');
  console.log('Gotovo → JS-RAPORT.md');
})();
