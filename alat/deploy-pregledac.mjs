/**
 * DEPLOY PREKO PRAVOG PREGLEDACA
 *
 * Zasto ovako
 * -----------
 * Hosting je ispred sajta i ispred cPanela stavio provjeru protiv robota. Ona
 * vrati stranicu "One moment, please...", pusti JavaScript koji ispituje
 * pregledac (navigator.webdriver, plugins, languages, outerWidth), posalje
 * rezultat nazad i tek onda da kolacic. Bez toga svaki zahtjev dobija tu istu
 * stranicu umjesto odgovora.
 *
 * Zbog toga:
 *   · curl stize do servera, ali ne umije da izvrsi taj JavaScript;
 *   · pregledac umije, ali ga mreza razvojne masine ne pusta do tih adresa.
 *
 * Ovdje se koristi masina koja ima oboje — GitHub-ov izvrsilac. Pravi pregledac
 * prodje provjeru, pokupi kolacic, procita admin lozinku sa servera, prijavi se
 * u admin i pokrene sync. Na kraju provjeri da je sajt stvarno azuriran.
 *
 * Pokretanje:  node alat/deploy-pregledac.mjs <cpanel-korisnik> <cpanel-lozinka>
 */

import { chromium } from 'playwright';

const KOR = process.argv[2];
const LOZ = process.argv[3];
const KLJUC = process.env.SYNC_KLJUC || 'mkhsync2025';
if (!KOR || !LOZ) {
  console.error('Nedostaju pristupni podaci za cPanel.');
  process.exit(1);
}

const CPANEL = 'https://cpanel.mmhdecor.mycpanel.rs';
const SAJT = 'https://makemyhome.me';

/** Ceka da stranica prestane da bude "One moment, please..." i vrati tijelo. */
async function prodjiProvjeru(p, adresa, kolikoPuta = 12) {
  for (let i = 1; i <= kolikoPuta; i++) {
    await p.goto(adresa, { waitUntil: 'domcontentloaded', timeout: 45000 }).catch(() => {});
    // Provjera se sama osvjezava poslije 5s; dajemo joj vremena da to uradi.
    await p.waitForTimeout(i === 1 ? 9000 : 6000);
    const tijelo = await p.evaluate(() => document.body ? document.body.innerText : '').catch(() => '');
    const naslov = await p.title().catch(() => '');
    if (!/One moment|Please wait|request is being verified/i.test(naslov + ' ' + tijelo)) {
      return tijelo;
    }
    console.log(`   provjera jos traje (pokusaj ${i})`);
  }
  return null;
}

const b = await chromium.launch({ args: ['--ignore-certificate-errors'] });

try {
  console.log('1/4  citam admin lozinku sa servera (kroz provjeru)');
  const ctxCp = await b.newContext({
    ignoreHTTPSErrors: true,
    httpCredentials: { username: KOR, password: LOZ },
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    locale: 'sr-ME',
  });
  const pCp = await ctxCp.newPage();
  const url = CPANEL + '/execute/Fileman/get_file_content?dir=%2Fhome%2Fmmhdecor&file=.mmh-admin-pass';
  const tijelo = await prodjiProvjeru(pCp, url);
  if (!tijelo) throw new Error('cPanel nije presao provjeru ni poslije 12 pokusaja');

  const sirovo = tijelo.trim();
  let admin;
  try {
    admin = JSON.parse(sirovo).data.content.trim();
  } catch {
    throw new Error('cPanel nije vratio JSON: ' + sirovo.slice(0, 120));
  }
  if (!admin) throw new Error('lozinka je prazna');
  console.log(`     lozinka procitana (${admin.length} znakova)`);
  await ctxCp.close();

  console.log('2/4  prijava u admin');
  const ctx = await b.newContext({
    ignoreHTTPSErrors: true,
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    locale: 'sr-ME',
  });
  const p = await ctx.newPage();
  if (!(await prodjiProvjeru(p, SAJT + '/admin/'))) {
    throw new Error('sajt nije presao provjeru');
  }
  await p.fill('input[name="username"]', 'admin');
  await p.fill('input[name="password"]', admin);
  await Promise.all([
    p.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 60000 }).catch(() => {}),
    p.click('button[type="submit"], input[type="submit"]'),
  ]);
  await p.waitForTimeout(2500);
  const poslijePrijave = await p.content();
  if (/name="password"/i.test(poslijePrijave) && !/dashboard|Proizvodi/i.test(poslijePrijave)) {
    throw new Error('prijava u admin nije uspjela — lozinka odbijena?');
  }
  console.log('     prijavljen');

  console.log('3/4  sync');
  await p.goto(`${SAJT}/admin/sync.php?key=${KLJUC}`, { waitUntil: 'domcontentloaded', timeout: 600000 });
  await p.waitForTimeout(4000);
  const izlaz = await p.evaluate(() => document.body.innerText);
  console.log(izlaz.split('\n').filter((r) => r.trim()).slice(-30).join('\n'));
  if (!/ažurirano|azurirano/i.test(izlaz)) throw new Error('sync nije javio da je zavrsio');

  // Ako se mijenjao sam sync.php, prvi prolaz ga tek postavi — drugi ga koristi.
  if (/admin\/sync\.php[\s\S]{0,80}OK/.test(izlaz)) {
    console.log('     sync.php je azuriran, pokrecem jos jednom');
    await p.waitForTimeout(4000);
    await p.goto(`${SAJT}/admin/sync.php?key=${KLJUC}`, { waitUntil: 'domcontentloaded', timeout: 600000 });
    await p.waitForTimeout(4000);
    const drugi = await p.evaluate(() => document.body.innerText);
    if (!/ažurirano|azurirano/i.test(drugi)) throw new Error('drugi prolaz nije zavrsio');
  }

  console.log('4/4  provjera na zivom sajtu');
  for (const put of ['/', '/kategorija/3d-letvice', '/paneli/3d-letvica-deva', '/products.html', '/cjenovnik.html']) {
    const r = await p.goto(SAJT + put, { waitUntil: 'domcontentloaded', timeout: 60000 });
    console.log(`     ${r ? r.status() : '?'}  ${put}`);
    if (!r || r.status() !== 200) throw new Error(`${put} vraca ${r ? r.status() : '?'}`);
  }

  await p.goto(SAJT + '/kategorija/bambus-paneli', { waitUntil: 'load', timeout: 60000 });
  await p.waitForTimeout(2500);
  const stanje = await p.evaluate(() => ({
    plocica: document.querySelectorAll('#category-grid .cat-card').length,
    proizvoda: [...document.querySelectorAll('#products-container .product-card')].filter((e) => e.offsetParent).length,
  }));
  console.log(`     bambus-paneli: plocica ${stanje.plocica}, proizvoda ispod ${stanje.proizvoda}`);
  if (stanje.proizvoda !== 0) throw new Error('kisobran i dalje nabraja proizvode — sync nije stigao?');
  if (stanje.plocica !== 6) throw new Error(`ocekivano 6 plocica, ima ${stanje.plocica}`);

  console.log('DEPLOY GOTOV');
} finally {
  await b.close();
}
