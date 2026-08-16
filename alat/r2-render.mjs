/**
 * r2-render.mjs — druga runda: mjeri PONASANJE, ne izvor.
 *
 * Zasto postoji:
 * Prva runda je nad svih 149 stranica prosla cisto, a ipak je na
 * /kategorija/bambus-paneli JavaScript gasio 39 proizvoda koje je server vec
 * ispisao. Alat koji cita staticni HTML to ne moze vidjeti — greska je bila u
 * ponasanju poslije iscrtavanja.
 *
 * Ovdje se svaka stranica ucitava DVA PUTA u istom pregledacu:
 *   1) sa iskljucenim JavaScriptom  — ono sto Google vidi u prvom prolazu
 *   2) sa ukljucenim JavaScriptom   — ono sto vidi posjetilac i drugi prolaz
 * pa se broji isto u oba slucaja: pojave €, kartice proizvoda, h2, h3 i duzina
 * vidljivog teksta.
 *
 * Ovako se poredi jabuka sa jabukom. Prvi alat je sirovi HTML mjerio skidanjem
 * tagova sa cijelog dokumenta, a iscrtani DOM preko innerText — pa je SVAKA
 * stranica pokazivala pad i pravi pad se u tome gubio.
 *
 * Uz to se povlaci i sirovi HTML curl-om (Googlebot UA) i broji isto u izvoru,
 * kao treca kolona.
 *
 * Oznake:
 *   [!!] iscrtano MANJE nego bez JavaScripta — potpis greske sa bambusa
 *   [!]  iscrtano ZNATNO VISE — sadrzaj koji Google u prvom prolazu ne vidi
 *
 * Pokretanje:  node alat/r2-render.mjs   →  R2-RENDER.md + R2-RENDER.json
 */
import fs from 'fs';
import { execFileSync } from 'child_process';
import { createRequire } from 'module';
const require = createRequire(import.meta.url);

const ZIVI = 'https://makemyhome.me';
const LOKALNO = 'http://127.0.0.1:8898';
const GBOT = 'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 '
           + '(KHTML, like Gecko) Chrome/125.0.0.0 Mobile Safari/537.36 '
           + '(compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

/** Prag ispod kojeg se razlika smatra sumom mjerenja, ne kvarom.
 *  Postavljen je na 0 za kartice i €, jer se oni broje tacno — element po
 *  element, u istom DOM-u. Za tekst se dozvoljava 5%: dugmad koja mijenjaju
 *  natpis ("Dodaj u korpu" -> "Dodato") i brojaci legitimno pomjeraju duzinu. */
const PRAG_TEKST = 0.05;

function sirovi(url) {
  try {
    return execFileSync('curl', ['-sk', '--cacert', '/root/.ccr/ca-bundle.crt',
      '--max-time', '30', '-A', GBOT, url], { maxBuffer: 40 * 1024 * 1024 }).toString('utf8');
  } catch (e) { return ''; }
}

/** Brojanje u izvoru — samo za trecu kolonu, ne za ocjenu. */
function uIzvoru(html) {
  return {
    eura: (html.match(/€/g) || []).length,
    kartice: (html.match(/class="[^"]*\bproduct-card\b/g) || []).length,
    h2: (html.match(/<h2[\s>]/gi) || []).length,
    h3: (html.match(/<h3[\s>]/gi) || []).length,
  };
}

/** Isto brojanje, ali u zivom DOM-u. Radi identicno sa i bez JavaScripta. */
const MJERI = () => ({
  eura: ((document.body.innerText || '').match(/€/g) || []).length,
  kartice: document.querySelectorAll('.product-card').length,
  plocice: document.querySelectorAll('.cat-card').length,
  ld: document.querySelectorAll('script[type="application/ld+json"]').length,
  h1: document.querySelectorAll('h1').length,
  h2: document.querySelectorAll('h2').length,
  h3: document.querySelectorAll('h3').length,
  tekst: (document.body.innerText || '').replace(/\s+/g, ' ').trim().length,
  naslov: document.title || '',
});

/* Izlaz se moze preusmjeriti (MMH_IZLAZ), da se 149 adresa moze mjeriti u
   dijelovima pa spojiti. Pozadinski procesi u ovom okruzenju ne prezive dugo,
   pa je jedan prolaz od dvadesetak minuta dva puta prekinut bez traga. */
const IZLAZ = process.env.MMH_IZLAZ || 'R2-RENDER';

/* Ako se preda .json, ne mjeri se nista nego se izvjestaj sastavi od vec
   izmjerenih dijelova — 149 adresa je mjereno u cetiri prolaza. */
const ULAZ = process.argv[2] || '/dev/stdin';
const SPAJANJE = ULAZ.endsWith('.json');

const putanje = SPAJANJE ? [] : fs.readFileSync(ULAZ, 'utf8')
  .split('\n').map(s => s.trim()).filter(Boolean)
  .map(u => u.replace(ZIVI, '') || '/');

const pw = require('/opt/node22/lib/node_modules/playwright/index.js');

(async () => {
  if (SPAJANJE) { napraviIzvjestaj(JSON.parse(fs.readFileSync(ULAZ, 'utf8'))); return; }
  const browser = await pw.chromium.launch({ args: ['--no-sandbox', '--disable-dev-shm-usage'] });
  const opcije = { userAgent: GBOT, viewport: { width: 412, height: 915 } };
  const bezJS = await browser.newContext({ ...opcije, javaScriptEnabled: false });
  const saJS = await browser.newContext({ ...opcije, javaScriptEnabled: true });

  /* Tudji domeni se odbijaju. Google Analytics i Maps iz ovog kontejnera ne
     odgovaraju, pa `networkidle` na svakoj stranici ceka do isteka vremena —
     prvo mjerenje je zbog toga islo 45 s po stranici, oko dva sata za 149.
     Odbijanje je i posteno: mjeri se nas kod, ne tudji. */
  const samoNase = (route) => {
    const u = route.request().url();
    return /^http:\/\/127\.0\.0\.1:/.test(u) ? route.continue() : route.abort();
  };
  await bezJS.route('**/*', samoNase);
  await saJS.route('**/*', samoNase);

  async function jednu(put) {
    const url = LOKALNO + put;
    const red = { put, greska: null };
    try {
      const p1 = await bezJS.newPage();
      await p1.goto(url, { waitUntil: 'load', timeout: 45000 });
      red.bez = await p1.evaluate(MJERI);
      await p1.close();

      const p2 = await saJS.newPage();
      await p2.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
      await p2.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
      await p2.waitForTimeout(2000);
      red.sa = await p2.evaluate(MJERI);
      await p2.close();
    } catch (e) {
      red.greska = String(e).split('\n')[0].slice(0, 120);
      return red;
    }
    red.izvor = uIzvoru(sirovi(ZIVI + put));
    return red;
  }

  /* Tri stranice odjednom. Sa cetiri je kontejneru ponestalo memorije na
     pola posla i mjerenje je izgubljeno bez ijedne poruke — zato se poslije
     svake grupe upisuje i medjustanje, pa se prekid vidi i ne kosta sve. */
  const redovi = [];
  let gotovo = 0;
  for (let k = 0; k < putanje.length; k += 3) {
    const grupa = putanje.slice(k, k + 3);
    const rez = await Promise.all(grupa.map(jednu));
    for (const red of rez) redovi.push(red);
    gotovo += grupa.length;
    process.stderr.write(`[${gotovo}/${putanje.length}] ${grupa[grupa.length - 1]}\n`);
    fs.writeFileSync(IZLAZ + '.json', JSON.stringify(redovi, null, 1));
  }
  for (const red of redovi) {
    if (red.greska) continue;

    // Ocjena
    const oznake = [];
    if (red.sa.kartice < red.bez.kartice) {
      oznake.push(`[!!] JS ukloni ${red.bez.kartice - red.sa.kartice} kartica proizvoda`);
    }
    if (red.sa.eura < red.bez.eura) {
      oznake.push(`[!!] JS ukloni ${red.bez.eura - red.sa.eura} cijena`);
    }
    if (red.sa.plocice < red.bez.plocice) {
      oznake.push(`[!!] JS ukloni ${red.bez.plocice - red.sa.plocice} plocica kategorija`);
    }
    if (red.sa.ld < red.bez.ld) oznake.push(`[!!] JS ukloni ${red.bez.ld - red.sa.ld} JSON-LD blokova`);
    if (red.sa.ld > red.bez.ld) oznake.push(`[!] JS doda ${red.sa.ld - red.bez.ld} JSON-LD blokova — Google ih u prvom prolazu ne vidi`);
    if (red.sa.h1 !== 1 || red.bez.h1 !== 1) oznake.push(`[!!] h1: ${red.bez.h1} bez JS-a, ${red.sa.h1} sa JS-om (mora biti tacno 1)`);
    if (red.sa.h2 < red.bez.h2) oznake.push(`[!!] JS ukloni ${red.bez.h2 - red.sa.h2} h2`);
    if (red.sa.h3 < red.bez.h3) oznake.push(`[!!] JS ukloni ${red.bez.h3 - red.sa.h3} h3`);
    if (red.bez.tekst > 0 && red.sa.tekst < red.bez.tekst * (1 - PRAG_TEKST)) {
      const pad = Math.round((1 - red.sa.tekst / red.bez.tekst) * 100);
      oznake.push(`[!!] JS ukloni ${pad}% vidljivog teksta`);
    }
    if (red.bez.tekst > 0 && red.sa.tekst > red.bez.tekst * 1.5) {
      const rast = Math.round((red.sa.tekst / red.bez.tekst - 1) * 100);
      oznake.push(`[!] JS doda ${rast}% teksta — Google to ne vidi u prvom prolazu`);
    }
    if (red.bez.kartice === 0 && red.sa.kartice > 0) {
      oznake.push(`[!] kartice postoje samo poslije JS-a (${red.sa.kartice})`);
    }
    red.oznake = oznake;
  }
  process.stderr.write('\n');
  await browser.close();
  napraviIzvjestaj(redovi);
})();

function napraviIzvjestaj(redovi) {
  // ---------------------------------------------------------------- izvjestaj
  const L = [];
  const r = (s) => L.push(s);
  r('# R2 — sta ostane poslije JavaScripta');
  r('');
  r(`Napravljeno: ${new Date().toISOString().slice(0, 16).replace('T', ' ')} UTC`);
  r('');
  r(`${redovi.length} adresa iz sitemapa. Svaka ucitana dva puta u istom Chromiumu:`);
  r('jednom sa **iskljucenim** JavaScriptom, jednom sa **ukljucenim** (`networkidle` + 2 s).');
  r('Broji se u zivom DOM-u, isto u oba slucaja — zato su brojevi uporedivi.');
  r('');
  r('> Mjereno na lokalnoj kopiji (`php -S` + `alat/posrednik.mjs`) jer pregledac iz');
  r('> ovog okruzenja ne moze do zivog sajta. Kolona `izvor` je povucena curl-om sa');
  r('> **zivog** sajta, kao kontrola.');
  r('');
  r('**[!!]** iscrtano manje nego bez JS-a · **[!]** iscrtano znatno vise · **[i]** uredu');

  const lose = redovi.filter(x => (x.oznake || []).some(o => o.startsWith('[!!]')));
  const sumnjivo = redovi.filter(x => (x.oznake || []).some(o => o.startsWith('[!]')) && !lose.includes(x));
  const pukle = redovi.filter(x => x.greska);

  r('');
  r('## Zakljucak');
  r('');
  r(`- stranica sa **[!!]** (JS gasi sadrzaj): **${lose.length}**`);
  r(`- stranica sa **[!]** (JS dodaje sadrzaj): **${sumnjivo.length}**`);
  r(`- stranica koje se nisu ucitale: **${pukle.length}**`);

  if (lose.length) {
    r('');
    r('### [!!] JavaScript gasi sadrzaj koji server ispise');
    r('');
    for (const x of lose) { r(`**${x.put}**`); for (const o of x.oznake) r(`- ${o}`); r(''); }
  }
  if (sumnjivo.length) {
    r('');
    r('### [!] JavaScript dodaje sadrzaj kojeg u sirovom HTML-u nema');
    r('');
    for (const x of sumnjivo) { r(`**${x.put}**`); for (const o of x.oznake) r(`- ${o}`); r(''); }
  }
  if (pukle.length) {
    r('');
    r('### Stranice koje se nisu ucitale');
    r('');
    for (const x of pukle) r(`- \`${x.put}\` — ${x.greska}`);
  }

  r('');
  r('## Sve stranice');
  r('');
  r('`kart` = kartice proizvoda · `bez` = bez JavaScripta · `sa` = poslije JavaScripta');
  r('');
  r('| adresa | € bez | € sa | kart bez | kart sa | ploc bez | ploc sa | LD bez | LD sa | h1 | h2 bez | h2 sa | tekst bez | tekst sa | status |');
  r('|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|');
  for (const x of redovi) {
    if (x.greska) { r(`| \`${x.put}\` | — | — | — | — | — | — | — | — | — | — | — | — | — | pukla |`); continue; }
    const st = (x.oznake || []).length
      ? (x.oznake.some(o => o.startsWith('[!!]')) ? '**[!!]**' : '[!]')
      : '[i]';
    r(`| \`${x.put}\` | ${x.bez.eura} | ${x.sa.eura} | ${x.bez.kartice} | ${x.sa.kartice} `
      + `| ${x.bez.plocice} | ${x.sa.plocice} | ${x.bez.ld} | ${x.sa.ld} | ${x.sa.h1} `
      + `| ${x.bez.h2} | ${x.sa.h2} | ${x.bez.tekst} | ${x.sa.tekst} | ${st} |`);
  }

  r('');
  r('## Kategorije posebno');
  r('');
  r('| kategorija | € izvor (zivi) | € bez JS | € sa JS | kart izvor | kart bez | kart sa | status |');
  r('|---|---|---|---|---|---|---|---|');
  for (const x of redovi.filter(y => y.put.startsWith('/kategorija/') && !y.greska)) {
    const st = (x.oznake || []).length
      ? (x.oznake.some(o => o.startsWith('[!!]')) ? '**[!!]**' : '[!]')
      : '[i]';
    r(`| \`${x.put}\` | ${x.izvor.eura} | ${x.bez.eura} | ${x.sa.eura} `
      + `| ${x.izvor.kartice} | ${x.bez.kartice} | ${x.sa.kartice} | ${st} |`);
  }

  fs.writeFileSync(IZLAZ + '.md', L.join('\n') + '\n');
  fs.writeFileSync(IZLAZ + '.json', JSON.stringify(redovi, null, 1));
  console.log(`Gotovo → ${IZLAZ}.md  ([!!] ${lose.length}, [!] ${sumnjivo.length}, pukle ${pukle.length})`);
}
