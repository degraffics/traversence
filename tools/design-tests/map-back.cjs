// The map's side panel: back to the list (Jason, 2026-11-19: "no way for me to go back to the list i had before I click an item").
// Serve the site at 127.0.0.1:8790 with website/t-map.html (below, test only, never shipped); Leaflet 1.9.4 and
// leaflet.markercluster 1.5.3 unpacked from npm into NPM_DIR. Run: NPM_DIR=<dir> node map-back.cjs <shots dir>
const { chromium } = require('playwright');(async()=>{const b=await chromium.launch();const errs=[];
for (const [W,H] of [[1280,800],[390,800]]) {const p=await b.newPage({viewport:{width:W,height:H}});p.on('pageerror',e=>errs.push(e.message));
await p.route(/cdnjs\.cloudflare\.com/,r=>{const u=r.request().url();const fs=require('fs');const m={'leaflet/1.9.4/leaflet.min.css':'leaflet-1.9.4/dist/leaflet.css','leaflet/1.9.4/leaflet.min.js':'leaflet-1.9.4/dist/leaflet.js','leaflet.markercluster/1.5.3/MarkerCluster.css':'leaflet.markercluster-1.5.3/dist/MarkerCluster.css','leaflet.markercluster/1.5.3/MarkerCluster.Default.css':'leaflet.markercluster-1.5.3/dist/MarkerCluster.Default.css','leaflet.markercluster/1.5.3/leaflet.markercluster.js':'leaflet.markercluster-1.5.3/dist/leaflet.markercluster.js'};const k=Object.keys(m).find(x=>u.endsWith('/'+x));if(!k)return r.abort();r.fulfill({contentType:k.endsWith('.css')?'text/css':'text/javascript',body:fs.readFileSync((process.env.NPM_DIR || '.') + '/'+m[k])});});
await p.route(/tile\.openstreetmap|arcgis|basemaps|tiles?\./,r=>r.abort());await p.route(/\/api\//,r=>r.fulfill({contentType:'application/json',body:'{"ok":false}'}));
await p.goto('http://127.0.0.1:8790/t-map.html',{waitUntil:'load'});await p.evaluate(()=>go());await p.waitForTimeout(3000);if(await p.evaluate(()=>document.querySelector('.tvm-side').hidden))await p.click('[data-side]');await p.waitForTimeout(300);
const sb=p.locator('.tvm-sb');const list=p.locator('.tvm-list');console.log(W,'list rows:',await p.locator('.tvm-li').count(),'visible:',await list.isVisible());
// scroll the list down and open the 20th row
await p.evaluate(()=>{const r=document.querySelectorAll('.tvm-li')[19];r.scrollIntoView({block:'center'})});await p.waitForTimeout(200);const before=await p.evaluate(()=>document.querySelector('.tvm-sb').scrollTop);
await p.locator('.tvm-li').nth(19).click();await p.waitForTimeout(500);
console.log(W,'card open, list hidden:',!(await list.isVisible()),'back button:',await p.locator('[data-card-back]').textContent(),'× there:',await p.locator('[data-card-x]').count()>0);
await p.screenshot({path:process.argv[2]+'/map-card-'+W+'.png'});
await p.click('[data-card-back]');await p.waitForTimeout(300);const after=await p.evaluate(()=>document.querySelector('.tvm-sb').scrollTop);
console.log(W,'back: list shown:',await list.isVisible(),'scroll kept:',before,'->',after,'row marked:',await p.evaluate(()=>{const w=document.querySelector('.tvm-li.was');return w&&w.textContent.slice(0,9)}));
await p.locator('.tvm-li').nth(5).click();await p.waitForTimeout(300);await p.locator('[data-step="1"]').click();await p.waitForTimeout(300);await p.keyboard.press('Escape');await p.waitForTimeout(300);
console.log(W,'Esc from a card: back to list, map still open:',await list.isVisible(),await p.locator('.tvm').count()>0);
await p.locator('.tvm-li').nth(2).click();await p.waitForTimeout(300);await p.click('[data-card-x]');await p.waitForTimeout(200);console.log(W,'× back to list:',await list.isVisible());
await p.keyboard.press('Escape');await p.waitForTimeout(300);if(await p.locator('.tvm').count()){await p.keyboard.press('Escape');await p.waitForTimeout(300);}console.log(W,'Esc with no card leaves the map:',await p.locator('.tvm').count()===0, 'sideways scroll:', await p.evaluate(()=>document.documentElement.scrollWidth>innerWidth));
await p.close();}
console.log('errors',errs);await b.close()})()

/* website/t-map.html:
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>map test</title></head><body>
<script>var currentLocation = { lat: 35.1, lon: -109.7, city: "Holbrook", source: "device" };</script><script src="/js/map-view.js"></script>
<script>
window.go = function () {
  var pins = []; for (var i = 0; i < 30; i++) pins.push({ id: 100 + i, name: 'Place ' + (i + 1), category: 'Park', lat: 35 + i * 0.01, lon: -109.8 + i * 0.01 });
  TvMap.open({ title: 'Everything', radius: 100, load: function () { return Promise.resolve({ pins: pins, center: { lat: 35.1, lon: -109.7 } }); } });
};
</script></body></html>
*/
