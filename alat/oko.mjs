/**
 * PREGLED OKOM — ono što provjera kodom ne može vidjeti.
 *
 * Zašto postoji
 * -------------
 * Dvije prave greške prošle su kroz svih 46 pravila u alat/provjera.py:
 *
 *   1. ispod fotografije fabrike na Decor Boxu stajalo je golo `">`;
 *   2. četiri ikone iscrtavale su se kao prazan prostor, jer je font sažet
 *      na ikone koje su se koristile ranije, a CSS je dobio nove.
 *
 * Obje stranice su vraćale 200, HTML se učitavao, alt atributi su bili na
 * mjestu, ikone su postojale u CSS-u. Greške su se vidjele samo okom, na
 * slici stranice. Zato ovaj alat postoji: pokreće pravi pregledač, prolazi
 * kroz tipove stranica na telefonu i na računaru, i traži ono što se ne vidi
 * iz koda.
 *
 * Šta provjerava
 * --------------
 *   · JavaScript greška na stranici
 *   · resurs koji se ne učitava (404, prekinuta veza)
 *   · bočni skrol — stranica koja izlazi van ekrana
 *   · komad koda koji je iscurio u vidljivi tekst
 *   · ikona koja se ne iscrtava (prazan glif u fontu)
 *
 * Zašto preko posrednika
 * ----------------------
 * Stranice nose <base href="https://makemyhome.me/">, pa bi pregledač sve
 * relativne putanje tražio sa pravog sajta — do kojeg iz ovog okruženja nema
 * pristupa. Stranica bi se učitala BEZ stilova i alat bi prijavio gomilu
 * izmišljenih grešaka. alat/posrednik.mjs to prepisuje na lokalnu adresu.
 *
 * Pokretanje
 * ----------
 *     php -S 127.0.0.1:8899 -t . &
 *     node alat/posrednik.mjs &
 *     node alat/oko.mjs
 *
 * Slike se upisuju u alat/snimci/oko/. Izlaz 1 ako je nešto palo.
 */
import pkg from '/opt/node22/lib/node_modules/playwright/index.js';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const { chromium } = pkg;
const KORIJEN = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const IZLAZ = path.join(KORIJEN, 'alat', 'snimci', 'oko');
const BAZA = 'http://127.0.0.1:8898';

// Po jedna stranica svakog tipa. Više nema smisla: greška u zaglavlju,
// podnožju ili karticama pojavljuje se na svima, a ovako prolaz traje minut.
// Prvi proizvod iz podataka — tvrdo upisan id je jednom vec dao lazni 404
// jer tog proizvoda u podacima nema.
let PRVI_ID = '';
try {
  const d = JSON.parse(fs.readFileSync(path.join(KORIJEN, 'data', 'products.json'), 'utf8'));
  const P = Array.isArray(d) ? d : (d.products || []);
  PRVI_ID = String((P[0] || {}).id || '');
} catch { /* ostaje prazno — stranica proizvoda se preskace */ }

const STRANICE = [
  ['pocetna', '/'],
  ['katalog', '/products.php'],
  ...(PRVI_ID ? [['proizvod', '/product.php?id=' + PRVI_ID]] : []),
  ['decor-box', '/decor-box.php'],
  ['cjenovnik', '/cjenovnik.php'],
  ['inspiracija', '/inspiracija.php'],
  ['kontakt', '/contact.html'],
  ['korpa', '/korpa.html'],
  ['faq', '/faq.html'],
  ['montaza', '/montaza.html'],
];

const UREDJAJI = [['racunar', 1440, 900], ['telefon', 390, 844]];

// Komadi koda koji ne smiju stajati u tekstu koji posjetilac čita.
const OSTACI = ['">', "'>", '<div', '<img', '<span', '<?php', '<?=', 'onerror='];

fs.mkdirSync(IZLAZ, { recursive: true });

let palo = 0;
const prijavi = (gdje, sta) => { palo++; console.log(`  PAD  ${gdje}: ${sta}`); };

const b = await chromium.launch();

for (const [ime, put] of STRANICE) {
  for (const [uredjaj, w, h] of UREDJAJI) {
    const gdje = `${ime}/${uredjaj}`;
    const p = await b.newPage({ viewport: { width: w, height: h }, deviceScaleFactor: 1 });
    const greske = [];
    p.on('pageerror', e => greske.push('JS: ' + e.message.split('\n')[0]));
    // Spoljni servisi (Analytics, Google Maps) ne prolaze kroz proxy ovog
    // okruženja. Njihov neuspjeh nije greška sajta i ne smije se tako
    // prijavljivati — inače alat opet laže, a to je gore nego da ga nema.
    const nase = u => u.startsWith(BAZA);
    p.on('requestfailed', r => {
      if (nase(r.url())) greske.push('ne učitava se: ' + r.url().replace(BAZA, ''));
    });
    p.on('response', r => {
      if (r.status() >= 400 && nase(r.url())) {
        greske.push(r.status() + ' ' + r.url().replace(BAZA, ''));
      }
    });

    try {
      await p.goto(BAZA + put, { waitUntil: 'networkidle', timeout: 45000 });
    } catch (e) {
      prijavi(gdje, 'stranica se ne učitava — ' + e.message.split('\n')[0]);
      await p.close();
      continue;
    }

    // Do dna i nazad, da se pokrene sve što čeka na skrol (lazy slike, animacije).
    await p.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await p.waitForTimeout(900);
    await p.evaluate(() => window.scrollTo(0, 0));
    await p.waitForTimeout(300);

    const nalaz = await p.evaluate((ostaci) => {
      const out = {};
      out.bocno = document.documentElement.scrollWidth > window.innerWidth + 1
        ? `${document.documentElement.scrollWidth} > ${window.innerWidth}` : '';
      const t = document.body.innerText;
      out.curak = ostaci.filter(x => t.includes(x));
      // Ikona bez glifa: mjeri se stvarna širina iscrtanog znaka. Prazan <i>
      // ne može se mjeriti sam po sebi jer sadržaj dolazi iz ::before, pa se
      // pravi privremeni span sa istim fontom i istim znakom.
      out.prazneIkone = [];
      const vidjene = new Set();
      document.querySelectorAll('i[class*="fa-"]').forEach(i => {
        const kl = i.className;
        if (vidjene.has(kl)) return;
        vidjene.add(kl);
        const st = getComputedStyle(i, ':before');
        const znak = (st.content || '').replace(/^["']|["']$/g, '');
        if (!znak || znak === 'none' || znak === 'normal') return;
        const s = document.createElement('span');
        s.textContent = znak;
        s.style.cssText = `position:absolute;visibility:hidden;font-family:${st.fontFamily};`
                        + `font-weight:${st.fontWeight};font-size:64px;`;
        document.body.appendChild(s);
        const sirina = s.getBoundingClientRect().width;
        s.remove();
        // Znak kojeg font nema iscrta se kao nula ili kao .notdef kutija.
        if (sirina < 4) out.prazneIkone.push(kl);
      });
      return out;
    }, OSTACI);

    await p.screenshot({ path: path.join(IZLAZ, `${ime}-${uredjaj}.png`), fullPage: true });

    if (nalaz.bocno) prijavi(gdje, 'stranica izlazi van ekrana (' + nalaz.bocno + ')');
    if (nalaz.curak.length) prijavi(gdje, 'kod u vidljivom tekstu: ' + nalaz.curak.join(', '));
    if (nalaz.prazneIkone.length) prijavi(gdje, 'ikone bez znaka u fontu: ' + nalaz.prazneIkone.join(', ')
      + '  (pokreni: python3 alat/fontovi.py upisi)');
    for (const g of [...new Set(greske)].slice(0, 4)) prijavi(gdje, g);

    if (!nalaz.bocno && !nalaz.curak.length && !nalaz.prazneIkone.length && !greske.length) {
      console.log(`  OK   ${gdje}`);
    }
    await p.close();
  }
}

await b.close();
console.log('\n' + '='.repeat(60));
console.log(palo ? `PALO: ${palo}. Slike su u alat/snimci/oko/` : 'Sve stranice izgledaju kako treba.');
process.exit(palo ? 1 : 0);
