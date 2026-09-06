/* Mali posrednik: sve sto stigne na 8898 dohvati sa lokalnog PHP servera (8899),
   a u HTML-u zamijeni https://makemyhome.me/ svojom adresom — da <base href>
   ne odvuce pregledac na pravi sajt do kojeg iz ovog okruzenja nema pristupa.
   Bez ovoga Lighthouse ucita stranicu BEZ stilova i prijavi gomilu izmisljenih
   gresaka (navigacija "prevelika", kontrast "los") kojih na sajtu nema.

   Slike se dovlace SA SERVERA kad ih lokalno nema.
   images/products/* i images/categories/* vlasnik dodaje kroz admin i ti fajlovi
   se namjerno ne drze u gitu. Lokalno ih zato nema, pa je pregled izgleda
   prijavljivao 27 "gresaka" koje na sajtu ne postoje — isto lazno kao i ranije.
   Sada se takav zahtjev preusmjeri na pravi sajt preko curl-a (koji, za razliku
   od pregledaca, prolazi kroz proxy okruzenja). Tako se gleda prava kombinacija:
   lokalni kod + slike sa servera. */
import http from 'http';
import { spawnSync } from 'child_process';

const IZVOR = 'http://127.0.0.1:8899';
const MOJA = 'http://127.0.0.1:8898';
const SAJT = 'https://makemyhome.me';

/* Tri pokusaja, ne jedan.
   Kad provjera ide punom parom, na server ide vise desetina zahtjeva u sekundi
   i poneki curl se prekine ili istekne. Sa jednim pokusajem to znaci 404 za
   sliku koja na sajtu postoji, pa pregled izgleda prijavi kvar kojeg nema —
   bas se to i desilo za cat-mdf-1775496655.jpg: slika je i na sajtu i preko
   posrednika 200, a jedan prolazni promasaj je oborio cijeli korak. */
function saServera(put) {
  for (let pokusaj = 0; pokusaj < 3; pokusaj++) {
    const r = spawnSync('curl', ['-sk', '--cacert', '/root/.ccr/ca-bundle.crt',
      '--max-time', '20', '--retry', '1', '-w', '\\n@@%{http_code}|%{content_type}', SAJT + put],
      { encoding: 'buffer', maxBuffer: 40 * 1024 * 1024 });
    if (r.status === 0 && r.stdout) {
      const buf = r.stdout;
      const i = buf.lastIndexOf(Buffer.from('\n@@'));
      if (i >= 0) {
        const [kod, tip] = buf.slice(i + 3).toString().split('|');
        if (kod === '200') {
          return { telo: buf.slice(0, i), tip: (tip || 'application/octet-stream').trim() };
        }
        if (kod === '404' || kod === '410') return null;   // stvarno ga nema
      }
    }
    spawnSync('sleep', [String(0.4 * (pokusaj + 1))]);
  }
  return null;
}

http.createServer(async (req, res) => {
  try {
    const o = await fetch(IZVOR + req.url, { headers: { accept: req.headers.accept || '*/*' } });

    if (o.status === 404 && /^\/images\//.test(req.url)) {
      const sa = saServera(req.url);
      if (sa) {
        res.writeHead(200, { 'content-type': sa.tip });
        return res.end(sa.telo);
      }
    }

    const tip = o.headers.get('content-type') || 'application/octet-stream';
    if (/text\/html|application\/xml|text\/css|javascript/.test(tip)) {
      let t = await o.text();
      t = t.split(SAJT + '/').join(MOJA + '/');
      res.writeHead(o.status, { 'content-type': tip });
      res.end(t);
    } else {
      const b = Buffer.from(await o.arrayBuffer());
      res.writeHead(o.status, { 'content-type': tip });
      res.end(b);
    }
  } catch (e) { res.writeHead(502); res.end('greska: ' + e.message); }
}).listen(8898, '127.0.0.1', () => console.log('posrednik na 8898'));
