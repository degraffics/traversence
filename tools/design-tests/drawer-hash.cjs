// Run on a site serving the admin at 127.0.0.1:8790: node drawer-hash.cjs [/admin/…#id]
// An anchored admin link opened in the drawer lands on its card (Jason, 2026-11-19: "Open card shows the specific place to fix issues")
const { chromium } = require('playwright');(async()=>{const b=await chromium.launch();const p=await b.newPage({viewport:{width:1300,height:850}});const errs=[];p.on('pageerror',e=>errs.push(e.message));await p.route(/^https:\/\/(?!127)/,r=>r.abort());
await p.goto('http://127.0.0.1:8790/admin/config/control',{waitUntil:'load'});await p.waitForTimeout(1000);
const target=process.argv[2]||'/admin/dashboard/today#pb-fdd';
await p.evaluate(t=>TvAdmin.frame(t,'Test'),target);await p.waitForTimeout(2500);
const fr=p.frames().find(f=>f!==p.mainFrame()&&/panel=1|embed=1/.test(f.url()));
console.log('frame url:',fr&&fr.url().replace('http://127.0.0.1:8790',''));
const id=target.split('#')[1];
const r=fr?await fr.evaluate(id=>{const el=document.getElementById(id);if(!el)return 'no element';const b=el.getBoundingClientRect();return {inView:b.top>=0&&b.top<innerHeight,marked:el.classList.contains('ak-landed')||!!el.closest('.ak-landed'),hash:location.hash,query:location.search}},id):'no frame';
console.log(JSON.stringify(r),errs);await b.close()})()
