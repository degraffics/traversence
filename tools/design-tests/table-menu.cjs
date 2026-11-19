// The ⋯ menu on a table's last rows is never cut off (Jason, 2026-11-19, "design issue"): every item visible and tappable
// at 1280 and 390, upward near the window's bottom. Serve table-page.php on 8791 (TV_ROOT=<site checkout>), then: node table-menu.cjs <shot dir>
const { chromium } = require('playwright');(async()=>{const b=await chromium.launch();const errs=[];
for (const [W,H,n] of [[1280,800,3],[390,800,3],[1280,800,10]]) {const p=await b.newPage({viewport:{width:W,height:H}});p.on('pageerror',e=>errs.push(e.message));
await p.route(/\/mock\//,r=>{const rows=Array.from({length:n},(_,i)=>({id:i+1,name:'Process '+(i+1),means:'what it is',stage:{tone:'ok',word:'Working'},runs:i}));r.fulfill({contentType:'application/json',body:JSON.stringify({rows,total:n,sortable:[]})});});
await p.goto('http://127.0.0.1:8791/?view=list',{waitUntil:'load'});await p.waitForTimeout(1200);
const btns=p.locator('[data-ak-rm]');const c=await btns.count();const last=btns.nth(c-1);
// worst case: the table's bottom at the window's bottom
await p.evaluate(()=>{const t=document.querySelector('.ak-t');window.scrollTo(0,t.getBoundingClientRect().bottom+scrollY-innerHeight+20)});await p.waitForTimeout(100);
await last.click();await p.waitForTimeout(200);
const r=await p.evaluate(()=>{const l=[...document.querySelectorAll('.akt-rml')].find(x=>!x.hidden);const b=l.getBoundingClientRect();const it=[...l.querySelectorAll('button,a')];const ok=it.every(x=>{const q=x.getBoundingClientRect();const h=document.elementFromPoint(q.x+q.width/2,q.y+q.height/2);return h&&l.contains(h)});return {up:l.classList.contains('up'),top:Math.round(b.top),bottom:Math.round(b.bottom),vh:innerHeight,everyItemVisibleAndTappable:ok,inWindow:b.top>=0&&b.bottom<=innerHeight}});
console.log(W,'rows',n,JSON.stringify(r));await p.screenshot({path:process.argv[2]+'/dots-'+W+'-'+n+'.png'});
// the first row's menu still opens downward
await p.keyboard.press('Escape');await p.evaluate(()=>scrollTo(0,0));await btns.nth(0).click();await p.waitForTimeout(150);console.log(W,'first row opens down:',await p.evaluate(()=>!document.querySelector('.akt-rml:not([hidden])').classList.contains('up')));
console.log(W,'corners:',await p.evaluate(()=>{const t=document.querySelector('.ak-t');return getComputedStyle(t).overflow+' / th '+getComputedStyle(t.querySelector('thead th')).borderTopLeftRadius}));
await p.close();}
console.log('errors',errs);await b.close()})()
