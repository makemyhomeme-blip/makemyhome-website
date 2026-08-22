import pw from '/opt/node22/lib/node_modules/playwright/index.js'; const { chromium } = pw;
const LOK='http://127.0.0.1:8877';
const OUT='/tmp/claude-0/-home-user-makemyhome-website/b08f72b9-df3e-5dae-978d-4f1956d6f140/scratchpad/';
const b = await chromium.launch();
const ctx = await b.newContext({ viewport:{width:390,height:844}, isMobile:true, hasTouch:true, deviceScaleFactor:2 });
await ctx.route('**/*', async r => { const u=r.request().url();
  if (u.startsWith(LOK)) return r.continue();
  if (u.startsWith('https://makemyhome.me/')) { try { const o=await fetch(LOK+u.slice(21)); const buf=Buffer.from(await o.arrayBuffer()); const h={}; o.headers.forEach((v,k)=>{if(k!=='content-encoding'&&k!=='content-length')h[k]=v;}); return r.fulfill({status:o.status,headers:h,body:buf}); } catch(e){ return r.abort(); } }
  return r.abort(); });
const p = await ctx.newPage();
const g=[]; p.on('pageerror', e=>g.push(String(e).slice(0,110)));
await p.goto(LOK+'/decor-box.html', { waitUntil:'domcontentloaded' });
await p.waitForTimeout(1400);
await p.addStyleTag({content:'.animate-on-scroll{opacity:1!important;transform:none!important;}'});
await p.evaluate(async () => { for(let y=0;y<document.body.scrollHeight;y+=400){window.scrollTo(0,y);await new Promise(r=>setTimeout(r,40));} window.scrollTo(0,0); });
await p.waitForTimeout(700);
const i = await p.evaluate(() => {
  const sek=document.querySelector('.db-namjena').getBoundingClientRect();
  const red=document.querySelector('.db-namjena-tabela tbody tr').getBoundingClientRect();
  const dek=document.querySelector('.db-dekori');
  const niski=[...document.querySelectorAll('.db-namjena a')].filter(e=>e.getBoundingClientRect().height<30).length;
  return { odjeljak: Math.round(sek.height), red: Math.round(red.height),
    dekoriJedanRed: Math.round(dek.getBoundingClientRect().height) < 45,
    dekoriSkrol: dek.scrollWidth > dek.clientWidth,
    nizihOd30: niski, stranica: document.body.scrollHeight,
    vodoravno: document.documentElement.scrollWidth > window.innerWidth+2 };
});
console.log(JSON.stringify(i), 'JSgreske:', g.length, g[0]||'');
const y = await p.evaluate(()=>{const e=document.querySelector('.db-namjena-uvod');return Math.round(e.getBoundingClientRect().top+scrollY-90);});
await p.evaluate(v=>window.scrollTo(0,v), y);
await p.waitForTimeout(700);
await p.screenshot({path:OUT+'u-tab.png'});
await b.close();
