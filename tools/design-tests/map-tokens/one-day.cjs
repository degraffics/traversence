const { chromium } = require('playwright'); const fs=require('fs'); const LJ=fs.readFileSync('/home/user/x/node/node_modules/leaflet/dist/leaflet.js','utf8');
(async()=>{const b=await chromium.launch();const ctx=await b.newContext({viewport:{width:390,height:900}});await ctx.addCookies([{name:'traversence_session',value:'harness0000000000000000000002',domain:'127.0.0.1',path:'/'}]);const p=await ctx.newPage();const e=[];p.on('pageerror',x=>e.push(String(x)));
await p.route(/^https?:\/\/(?!127)/,r=>/leaflet\.min\.js/.test(r.request().url())?r.fulfill({body:LJ,contentType:'application/javascript'}):r.abort());
await p.goto('http://127.0.0.1:8790/user/trips/trip.php?id=2');await p.waitForTimeout(1500);console.log(p.url(), await p.title(), await p.locator('[id=tp-route]').count(), await p.locator('[role=group]').count());
const L=await p.evaluate(()=>[...document.querySelectorAll('#tp-map svg path')].map(x=>[x.getAttribute('stroke'),x.getAttribute('stroke-opacity')]).filter(x=>x[0]));
const hid=await p.evaluate(()=>document.getElementById('tp-route').hidden);
console.log((hid&&L.length===2&&L.some(x=>x[0]==='#B6F22E'&&x[1]==='1')?'PASS':'FAIL')+' one-day trip: no Route row, its line lime over the casing '+JSON.stringify(L)+(e.length?' ERR '+e:''));await b.close()})();
