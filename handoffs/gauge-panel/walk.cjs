// presses every segment, card, chip and Back once at 1280 and 390, and checks nothing in a panel is inert text
const {chromium}=require('playwright');(async()=>{const b=await chromium.launch();const f='file://'+process.argv[2],out=process.argv[3];let bad=0;
const ok=(c,m)=>{if(!c){bad++;console.log('FAIL',m);}};
for(const w of [1280,390]){const p=await b.newPage({viewport:{width:w,height:900}});const errs=[];p.on('pageerror',e=>errs.push(e.message));await p.goto(f);
 await p.screenshot({path:`${out}/dev-${w}.png`,fullPage:true});
 ok(await p.evaluate(()=>document.documentElement.scrollWidth)<=w,w+' sideways scroll');
 ok(await p.locator('[data-view=dev] .it:visible').count()===10,w+' first 10 rows');
 // inert text: every row's name, every chip, every segment is a link or a button
 const inert=await p.evaluate(()=>[...document.querySelectorAll('.sp .it .ih > :first-child + *, .sp .chip, .sp .seg, .sp .lg')].filter(e=>!/^(A|BUTTON)$/.test(e.tagName)&&!e.classList.contains('nm2')&&!e.classList.contains('dots')).map(e=>e.outerHTML.slice(0,80)));
 ok(inert.length===0,w+' inert: '+inert.slice(0,3));
 for(const s of await p.locator('[data-view=dev] .seg:visible').all()){await s.click();const n=await p.locator('[data-view=dev] .it:visible').count();ok(n>0,w+' segment shows rows');await s.click();}
 await p.click('[data-view=dev] .info');ok(await p.locator('.dleg').isVisible(),w+' legend opens');await p.screenshot({path:`${out}/dev-${w}-legend.png`});
 await p.keyboard.press('Escape');ok(!(await p.locator('.dleg').isVisible()),w+' legend Esc');
 await p.click('[data-view=dev] .info');await p.click('.dlx');ok(!(await p.locator('.dleg').isVisible()),w+' legend ×');
 await p.click('[data-view=dev] .info');await p.mouse.click(5,300);ok(!(await p.locator('.dleg').isVisible()),w+' legend outside');
 await p.click('[data-view=dev] .lg[data-f=fail]');await p.click('[data-view=dev] .it:visible .ex >> nth=0');await p.screenshot({path:`${out}/dev-${w}-fail-open.png`,fullPage:true});
 for(const c of await p.locator('[data-view=dev] .ib:visible .chip[data-open]').all()){await c.click();ok(await p.locator('.sub').count()===1,w+' chip opens');await p.click('.sub [data-back]');}
 await p.click('[data-view=dev] .ib:visible .acts [data-open]');await p.screenshot({path:`${out}/dev-${w}-finding.png`});await p.click('.sub [data-back]');
 // Jason's process (Q2): view the issue, Fix it, the row reads Fixing with Undo; no sign-off anywhere
 ok(await p.locator('[data-sign]:not([data-sign=scope]):not([data-sign=target])').count()===0,w+' no Sign off on items');
 await p.click('[data-view=dev] .ib:visible .acts [data-send]');ok(await p.locator('[data-view=dev] .ib:visible .done').count()===1&&!(await p.locator('[data-view=dev] .ib:visible .acts [data-send]').isVisible()),w+' Fixing line replaces Fix it');
 await p.screenshot({path:`${out}/dev-${w}-fixing.png`,fullPage:true});
 ok(/^Next issue: /.test(await p.locator('[data-view=dev] .ib:visible .done .b').textContent()),w+' step 4 after Fixing: Next issue');
 await p.click('[data-view=dev] .ib:visible .done [data-demo]');ok(await p.locator('[data-view=dev] .ib:visible .done b.ok').count()===1,w+' cleared shows');
 ok(await p.locator('[data-view=dev] .ib:visible .done .b').count()===1,w+' step 4: a Next button');
 ok(await p.locator('[data-view=dev] .lg[data-f=fail] b').textContent()==='5'&&await p.locator('[data-view=dev] .ac [data-count=fail] >> nth=0').textContent()==='5',w+' counts follow: 6 issues -> 5');ok(/^5 · /.test(await p.locator('[data-view=dev] #lh-t').textContent()),w+' heading follows');ok(await p.locator('[data-view=dev] .ib:visible .acts:visible').count()===0&&await p.locator('[data-view=dev] .ib:visible .c-fail').count()===0,w+' cleared: the issue buttons and chip go');await p.screenshot({path:`${out}/dev-${w}-next.png`,fullPage:true});
 await p.click('[data-view=dev] .ex[aria-expanded=true]');await p.click('[data-view=dev] .it:visible .ex >> nth=1');await p.click('[data-view=dev] .ib:visible .acts [data-send]');await p.click('[data-view=dev] .ib:visible .done [data-undo]');ok(await p.locator('[data-view=dev] .ib:visible .acts [data-send]').isVisible(),w+' Undo puts it back');
 await p.click('[data-view=dev] .lg[data-f=fail]');await p.click('[data-view=dev] .more');ok(await p.locator('[data-view=dev] .it:visible').count()===30,w+' Show all 30');
 for(const c of await p.locator('[data-view=dev] .ac .b').all()){await c.click();if(await p.locator('.sub').count())await p.click('.sub [data-back]');}
 await p.click('[data-show=rt]');await p.screenshot({path:`${out}/rt-${w}.png`,fullPage:true});ok(await p.evaluate(()=>document.documentElement.scrollWidth)<=w,w+' rt sideways');
 await p.click('[data-view=rt] details.nd summary');await p.screenshot({path:`${out}/rt-${w}-needs.png`,fullPage:true});
 for(const c of await p.locator('[data-view=rt] .ac .b').all()){await c.click();if(await p.locator('.sub').count())await p.click('.sub [data-back]');}
 ok(errs.length===0,w+' page errors '+errs);console.log(w,'walked');await p.close();}
await b.close();console.log(bad?bad+' failed':'all passed');process.exit(bad?1:0);})();
