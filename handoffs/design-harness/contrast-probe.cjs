const { chromium } = require('playwright');
const SS='/tmp/claude-0/-home-user-traversence/46cad1da-0b53-5b00-81f7-a7d767fc9fdf/scratchpad/';
(async()=>{const b=await chromium.launch();const ctx=await b.newContext();await ctx.addCookies([{name:'traversence_session',value:'harness0000000000000000000000',domain:'127.0.0.1',path:'/'}]);
const tag=process.env.TAG||'new';
for (const [u,signed] of [['/user/dashboard.php',1],['/community/group.php?id=701',1],['/community/group.php?id=701',0],['/community/',0]]) for (const w of [1280,390]){
 const p=signed?await ctx.newPage():await b.newPage();await p.setViewportSize({width:w,height:900});const errs=[];p.on('pageerror',e=>errs.push(String(e)));await p.route(/^https?:\/\/(?!127\.0\.0\.1|localhost)/, r => r.abort());
 await p.goto('http://127.0.0.1:8790'+u,{waitUntil:'load'});await p.waitForTimeout(1500);
 const r=await p.evaluate(()=>{const P=c=>{const m=c.match(/[\d.]+/g).map(Number);if(c.startsWith('color(srgb'))return [m[0]*255,m[1]*255,m[2]*255,m[3]??1];return [m[0],m[1],m[2],m[3]??1]};
  const L=([r,g,b])=>{const f=v=>{v/=255;return v<=.03928?v/12.92:Math.pow((v+.055)/1.055,2.4)};return .2126*f(r)+.7152*f(g)+.0722*f(b)};
  const bg=e=>{let st=[];while(e){const v=P(getComputedStyle(e).backgroundColor);if(v[3]>0)st.push(v);if(v[3]>=1)break;if(getComputedStyle(e).backgroundImage!=='none')return null;e=e.parentElement;}let x=[255,255,255];for(let i=st.length-1;i>=0;i--){const[r,g,b,a]=st[i];x=[r*a+x[0]*(1-a),g*a+x[1]*(1-a),b*a+x[2]*(1-a)];}return x};
  const main=document.querySelector('.ta-work')||document.body;const bad=[];let n=0;
  for(const e of main.querySelectorAll('*')){if(!e.offsetParent||![...e.childNodes].some(t=>t.nodeType===3&&t.data.trim()))continue;const B=bg(e);if(!B)continue;n++;const f=P(getComputedStyle(e).color);const a=f[3];const F=[f[0]*a+B[0]*(1-a),f[1]*a+B[1]*(1-a),f[2]*a+B[2]*(1-a)];const r=(Math.max(L(F),L(B))+.05)/(Math.min(L(F),L(B))+.05);const fs=parseFloat(getComputedStyle(e).fontSize);const big=fs>=24||(fs>=18.6&&+getComputedStyle(e).fontWeight>=700);if(r<(big?3:4.5))bad.push(r.toFixed(2)+' '+e.tagName+'.'+String(e.className).slice(0,25)+' "'+e.textContent.trim().slice(0,22)+'"');}
  return {sw:document.documentElement.scrollWidth,texts:n,under:bad.length,ex:[...new Set(bad)].slice(0,6)};});
 console.log(tag,w,u,signed?'in':'out',JSON.stringify(r),errs.length?'ERRORS '+errs.slice(0,2):'');
 if(w===390||u.includes('dashboard'))await p.screenshot({path:SS+'r3d-'+tag+'-'+w+u.replace(/\W+/g,'_')+(signed?'in':'out')+'.png'});await p.close();}
await b.close();})();
