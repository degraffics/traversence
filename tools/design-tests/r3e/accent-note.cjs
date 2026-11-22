const { chromium } = require('playwright'); const TW=require('fs').readFileSync('/home/user/x/node/node_modules/@tailwindcss/browser/dist/index.global.js','utf8');
const SS=process.env.SS; let pass=0, fail=0; const ok=(c,m)=>{(c?pass++:fail++);console.log((c?'PASS ':'FAIL ')+m)};
(async()=>{const b=await chromium.launch();
for (const w of [1280,390]) {
 const ctx=await b.newContext({viewport:{width:w,height:900}});await ctx.addCookies([{name:'traversence_session',value:'harness0000000000000000000002',domain:'127.0.0.1',path:'/'}]);
 const p=await ctx.newPage();const errs=[];p.on('pageerror',e=>errs.push(String(e)));
 await p.route(/^https?:\/\/(?!127\.0\.0\.1)/, r => /tailwind/.test(r.request().url())?r.fulfill({body:TW,contentType:'application/javascript'}):r.abort());
 await p.goto('http://127.0.0.1:8790/business/10/storefront');await p.waitForTimeout(800);
 const note=p.locator('[data-sf-acnote]'), acc=p.locator('#sf-form input[name=accent]');
 ok(await note.isVisible() && /3\.6:1 on the Canyon/.test(await note.textContent()), w+' on load: the saved accent #C2701F is said at 3.6:1');
 await acc.fill('#7A3410'); await acc.dispatchEvent('input'); ok(!(await note.isVisible()), w+' typing a dark accent (#7A3410) hides the warning');
 await acc.fill('#E8B070'); await acc.dispatchEvent('input'); ok(await note.isVisible() && /too close to read/.test(await note.textContent()), w+' a light accent (#E8B070) says it is too close and saving asks for another');
 await acc.fill('#C2701F'); await acc.dispatchEvent('input'); ok(/3\.6:1/.test(await note.textContent()), w+' back to #C2701F: the warning again');
 const night=p.locator('#sf-form input[name=theme][value=night]'); if (await night.count()) { await night.check({force:true}); ok(/on the Night theme/.test(await note.textContent()), w+' switching to the Night theme re-reads it on Night\'s cards ('+(await note.textContent()).slice(0,40)+')'); await p.locator('#sf-form input[name=theme][value=canyon]').check({force:true}); }
 const r=await p.evaluate(()=>({sw:document.documentElement.scrollWidth,cw:document.documentElement.clientWidth})); ok(r.sw<=r.cw, w+' no sideways scroll ('+r.sw+'/'+r.cw+')');
 await note.scrollIntoViewIfNeeded(); await p.screenshot({path:SS+'portal-accent-'+w+'.png'});
 if (w===1280) {
   await acc.fill('#C2701F'); await p.locator('#sf-form button.go').click(); const say=p.locator('.tv-say, [class*="tvask"], [role="status"]').last(); await p.waitForTimeout(600);
   const t=await p.evaluate(()=>document.body.innerText); console.log('  saw:', (t.match(/Saved[^\n]*|Error[^\n]*|didn.t work[^\n]*/)||[''])[0].slice(0,200)); ok(/Saved\. Your storefront is trvgo\.us\/pine-cafe\. Your accent reads at 3\.6:1/.test(t), 'Save keeps #C2701F and its answer carries the warning');
   await p.screenshot({path:SS+'portal-save-1280.png'}); await p.waitForTimeout(1200);
   await acc.fill('#E8B070'); await p.locator('#sf-form button.go').click(); await p.waitForTimeout(700);
   ok(/too close to the Canyon theme's cards/.test(await p.evaluate(()=>document.body.innerText)), 'Save refuses #E8B070 (under 3:1), as before');
 }
 ok(errs.length===0, w+' no script errors '+errs.slice(0,2)); await ctx.close();
}
console.log('passed',pass,'failed',fail); await b.close();})();
