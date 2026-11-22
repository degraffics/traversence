const { chromium } = require('playwright');
const SS=process.env.SS+'/3e/'; const TW=require('fs').readFileSync('/home/user/x/node/node_modules/@tailwindcss/browser/dist/index.global.js','utf8');
const PAGES=(process.env.PAGES||'/market/,/market/view.php?id=1,/market/cart.php,/market/rules.php').split(',');
(async()=>{const b=await chromium.launch();
for (const [tag,port] of [['before',+process.env.BP||8791],['after',+process.env.AP||8790]]) for (const u of PAGES) for (const w of [1280,390]){
 const ctx=await b.newContext({viewport:{width:w,height:900}}); if(process.env.SID) await ctx.addCookies([{name:'traversence_session',value:process.env.SID,domain:'127.0.0.1',path:'/'}]);
 const p=await ctx.newPage();const errs=[];p.on('pageerror',e=>errs.push(String(e)));
 await p.route(/^https?:\/\/(?!127\.0\.0\.1|localhost)/, r => /tailwind/.test(r.request().url())?r.fulfill({body:TW,contentType:'application/javascript'}):r.abort());
 await p.goto('http://127.0.0.1:'+port+u,{waitUntil:'load'});await p.waitForTimeout(1200);
 const r=await p.evaluate(()=>{const P=c=>{const m=c.match(/[\d.]+/g).map(Number);if(c.startsWith('color(srgb'))return [m[0]*255,m[1]*255,m[2]*255,m[3]??1];return [m[0],m[1],m[2],m[3]??1]};
  const L=([r,g,b])=>{const f=v=>{v/=255;return v<=.03928?v/12.92:Math.pow((v+.055)/1.055,2.4)};return .2126*f(r)+.7152*f(g)+.0722*f(b)};
  const bg=e=>{let st=[];while(e){const v=P(getComputedStyle(e).backgroundColor);if(v[3]>0)st.push(v);if(v[3]>=1)break;if(getComputedStyle(e).backgroundImage!=='none')return null;e=e.parentElement;}let x=[255,255,255];for(let i=st.length-1;i>=0;i--){const[r,g,b,a]=st[i];x=[r*a+x[0]*(1-a),g*a+x[1]*(1-a),b*a+x[2]*(1-a)];}return x};
  const main=document.querySelector('.ta-work')||document.body;const bad=[];let n=0;const cards=new Set();
  for(const e of main.querySelectorAll('*')){if(!e.offsetParent||![...e.childNodes].some(t=>t.nodeType===3&&t.data.trim()))continue;const B=bg(e);if(!B)continue;n++;const f=P(getComputedStyle(e).color);const a=f[3];const F=[f[0]*a+B[0]*(1-a),f[1]*a+B[1]*(1-a),f[2]*a+B[2]*(1-a)];const r=(Math.max(L(F),L(B))+.05)/(Math.min(L(F),L(B))+.05);const fs=parseFloat(getComputedStyle(e).fontSize);const big=fs>=24||(fs>=18.6&&+getComputedStyle(e).fontWeight>=700);if(r<(big?3:4.5))bad.push(r.toFixed(2)+' '+e.tagName+'.'+String(e.className).slice(0,25)+' "'+e.textContent.trim().slice(0,22)+'"');}
  for(const e of document.querySelectorAll('.mk-card,.mk-tile,.mk-promise,.mk-c,[class*="card"]')){const c=getComputedStyle(e).backgroundColor;if(c!=='rgba(0, 0, 0, 0)')cards.add(c)}
  return {sw:document.documentElement.scrollWidth,cw:document.documentElement.clientWidth,texts:n,under:bad.length,ex:[...new Set(bad)].slice(0,5),bg:getComputedStyle(document.body).backgroundColor,cards:[...cards].slice(0,4)};});
 console.log(tag,w,u,JSON.stringify(r),errs.length?'ERRORS '+errs.slice(0,2):'');
 await p.screenshot({path:SS+tag+'-'+w+u.replace(/\W+/g,'_')+'.png',fullPage:w===390?false:false});await ctx.close();}
await b.close();})();
