const { chromium } = require('playwright'); const fs=require('fs');
const TW=fs.readFileSync('/home/user/x/node/node_modules/@tailwindcss/browser/dist/index.global.js','utf8');
const LJ=fs.readFileSync('/home/user/x/node/node_modules/leaflet/dist/leaflet.js','utf8'), LC=fs.readFileSync('/home/user/x/node/node_modules/leaflet/dist/leaflet.css','utf8');
const SS=process.env.SS, PORT=process.env.PORT||8790, TAG=process.env.TAG||'B', ONLY=process.env.ONLY; let pass=0,fail=0; const ok=(c,m)=>{(c?pass++:fail++);console.log((c?'PASS ':'FAIL ')+m)};
async function page(b,w){const ctx=await b.newContext({viewport:{width:w,height:900}});await ctx.addCookies([{name:'traversence_session',value:'harness0000000000000000000002',domain:'127.0.0.1',path:'/'}]);
 const p=await ctx.newPage();p.errs=[];p.on('pageerror',e=>p.errs.push(String(e)));
 await p.route(/^https?:\/\/(?!127\.0\.0\.1)/,r=>{const u=r.request().url(); if(/tailwind/.test(u))return r.fulfill({body:TW,contentType:'application/javascript'}); if(/leaflet\.min\.js/.test(u))return r.fulfill({body:LJ,contentType:'application/javascript'}); if(/leaflet\.min\.css/.test(u))return r.fulfill({body:LC,contentType:'text/css'}); return r.abort();});
 await p.goto('http://127.0.0.1:'+PORT+'/user/trips/trip.php?id=1');await p.waitForTimeout(1500);return [ctx,p];}
const lines=p=>p.evaluate(()=>[...document.querySelectorAll('#tp-map path.leaflet-interactive, #tp-map svg path')].map(e=>({s:e.getAttribute('stroke'),o:+(e.getAttribute('stroke-opacity')||1),w:+e.getAttribute('stroke-width')})));
(async()=>{const b=await chromium.launch();
for (const w of [1280,390]) { const [ctx,p]=await page(b,w);
 await p.locator('#tp-map').scrollIntoViewIfNeeded(); await p.waitForTimeout(400);
 if (ONLY) { await p.screenshot({path:SS+'map-'+TAG+'-'+w+'.png'}); console.log('shot',TAG,w); await ctx.close(); continue; }
 let L=await lines(p); const lime=L.filter(x=>/B6F22E/i.test(x.s)), cas=L.filter(x=>/3E2B1F/i.test(x.s));
 ok(lime.length===2&&cas.length===2, w+' two days: a lime line over a casing for each ('+JSON.stringify(L)+')');
 ok(cas.every(c=>c.w===6)&&lime.every(l=>l.w===4), w+' the casing is 2px wider than the lime (6 under 4)');
 const bar=p.locator('#tp-route'); ok(await bar.isVisible() && (await bar.locator('button').count())===2, w+' the Route row shows Day 1 and Day 2');
 ok(await bar.locator('[data-route-day="1"]').getAttribute('aria-pressed')==='true', w+' Day 1 is chosen at first');
 ok(lime.filter(l=>l.o===1).length===1 && lime.filter(l=>l.o===0).length===1, w+' only the chosen day is lime; the other is casing only');
 ok(cas.filter(c=>c.o<0.5).length===1, w+' the other day\'s casing is faded');
 await p.screenshot({path:SS+'map-B-'+w+'.png'});
 await bar.locator('[data-route-day="2"]').click(); await p.waitForTimeout(200); L=await lines(p);
 const l2=L.filter(x=>/B6F22E/i.test(x.s)); ok(await bar.locator('[data-route-day="2"]').getAttribute('aria-pressed')==='true' && l2.filter(l=>l.o===1).length===1, w+' pressing Day 2 moves the lime to day 2');
 await p.screenshot({path:SS+'map-B-day2-'+w+'.png'});
 await p.locator('#tp-map .leaflet-marker-icon').first().click(); await p.waitForTimeout(300);
 ok(await bar.locator('[data-route-day="1"]').getAttribute('aria-pressed')==='true', w+' tapping a day-1 stop chooses day 1 again (and opens its card)');
 ok(await p.locator('.leaflet-popup').isVisible(), w+' the stop\'s card still opens');
 const r=await p.evaluate(()=>({sw:document.documentElement.scrollWidth,cw:document.documentElement.clientWidth})); ok(r.sw<=r.cw, w+' no sideways scroll ('+r.sw+'/'+r.cw+')');
 ok(p.errs.length===0, w+' no script errors '+p.errs.slice(0,2)); await ctx.close(); }
if(!ONLY)console.log('passed',pass,'failed',fail); await b.close();})();
