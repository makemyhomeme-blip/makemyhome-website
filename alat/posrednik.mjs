/* Mali posrednik: sve sto stigne na 8898 dohvati sa lokalnog PHP servera (8899),
   a u HTML-u zamijeni https://makemyhome.me/ svojom adresom — da <base href>
   ne odvuce pregledac na pravi sajt do kojeg iz ovog okruzenja nema pristupa.
   Bez ovoga Lighthouse ucita stranicu BEZ stilova i prijavi gomilu izmisljenih
   gresaka (navigacija "prevelika", kontrast "los") kojih na sajtu nema. */
import http from 'http';
const IZVOR = 'http://127.0.0.1:8899';
const MOJA  = 'http://127.0.0.1:8898';
http.createServer(async (req, res) => {
  try {
    const o = await fetch(IZVOR + req.url, {headers: {'accept': req.headers.accept || '*/*'}});
    const tip = o.headers.get('content-type') || 'application/octet-stream';
    if (/text\/html|application\/xml|text\/css|javascript/.test(tip)) {
      let t = await o.text();
      t = t.split('https://makemyhome.me/').join(MOJA + '/');
      res.writeHead(o.status, {'content-type': tip});
      res.end(t);
    } else {
      const b = Buffer.from(await o.arrayBuffer());
      res.writeHead(o.status, {'content-type': tip});
      res.end(b);
    }
  } catch (e) { res.writeHead(502); res.end('greska: ' + e.message); }
}).listen(8898, '127.0.0.1', () => console.log('posrednik na 8898'));
