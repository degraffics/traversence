<?php
/**
 * includes/location-modal.php
 *
 * The place picker, shared by every page with the header (decisions/0053). Compact, top to bottom:
 *   North America (the big picture: no place), the regions on one swipeable row, a destination typed by name
 *   (a town, an area, a region or a local name), State | City | ZIP on one line, auto-detect, and saved places
 *   (signed in) or a sign-in link. Behavior lives in js/location-scope.js (openLocationModal, handleLocationSubmit,
 *   chooseAnywhere, autoDetectLocation, the destination type-ahead ...).
 */
?>
  <style>
    #location-modal.hidden{display:none}
    .lm-box{box-sizing:border-box;background:#E3D3AC;border:1px solid #CBB98D;border-radius:1rem;padding:1.1rem 1.15rem 1rem;max-width:30rem;width:100%;position:relative;box-shadow:0 25px 50px -12px rgba(0,0,0,.5);max-height:92vh;overflow:auto}
    .lm-h{font-family:var(--tv-font-head,ui-serif,Georgia,serif);font-weight:700;font-size:1.35rem;color:#3E2B1F;margin:0 0 .6rem}
    .lm-lbl{display:block;font-size:.66rem;letter-spacing:.09em;text-transform:uppercase;color:#7A6348;font-weight:700;margin:.75rem 0 .3rem}
    .lm-na{display:flex;align-items:center;gap:.6rem;width:100%;text-align:left;background:#3E2B1F;color:#FFFBF2;border:0;border-radius:.75rem;padding:.6rem .8rem;cursor:pointer}
    .lm-na:hover{background:#2A1B12}.lm-na{text-decoration:none}.lm-tp{display:flex;color:#E0C68E}.lm-na b{display:block;font-size:.92rem}.lm-na small{color:#E8D6AE;font-size:.74rem}.lm-na span:last-child{margin-left:auto;font-size:1.1rem}
    .lm-row{display:flex;gap:.4rem;overflow-x:auto;scrollbar-width:none;padding-bottom:.15rem}.lm-row::-webkit-scrollbar{display:none}
    .lm-row button{flex:0 0 auto;white-space:nowrap}
    .lm-in{width:100%;box-sizing:border-box;background:#EFE3C8;border:1px solid #CBB98D;border-radius:.6rem;padding:.5rem .65rem;color:#3E2B1F;font-size:.88rem}
    .lm-3{display:grid;grid-template-columns:5.2rem minmax(0,1fr) 5.4rem auto;gap:.35rem;align-items:center}
    .lm-go{background:var(--tv-btn);color:var(--tv-btn-text);border:0;border-radius:.6rem;padding:.5rem .8rem;font-weight:600;font-size:.84rem;cursor:pointer}.lm-go:hover{background:var(--tv-btn-hover)}
    .lm-dest{position:relative}
    .lm-one{display:flex;gap:.4rem;align-items:flex-start}.lm-one .lm-dest{flex:1}
    .lm-tgt{flex:0 0 auto;width:2.3rem;height:2.3rem;border-radius:.6rem;border:0;background:var(--tv-btn);color:var(--tv-btn-text);display:flex;align-items:center;justify-content:center;cursor:pointer}.lm-tgt:hover{background:var(--tv-btn-hover)}
    .lm-sug{position:absolute;left:0;right:0;top:100%;z-index:5;background:#FFFBF2;border:1px solid #CBB98D;border-radius:.6rem;margin-top:.2rem;box-shadow:0 12px 24px -12px rgba(0,0,0,.4);overflow:hidden}
    .lm-sug[hidden]{display:none}
    .lm-sug button{display:flex;justify-content:space-between;gap:.6rem;width:100%;text-align:left;background:none;border:0;border-top:1px solid #EADFC4;padding:.45rem .65rem;font-size:.86rem;color:#3E2B1F;cursor:pointer}
    .lm-sug button:first-child{border-top:0}.lm-sug button:hover,.lm-sug button:focus{background:#F7EFDC;outline:none}.lm-sug small{color:#7A6348;text-align:right}
    .lm-sug p{margin:0;padding:.45rem .65rem;font-size:.82rem;color:#5C4632}
    .lm-two{display:grid;grid-template-columns:1fr 1fr;gap:.4rem;margin-top:.8rem}
    .lm-b{display:flex;align-items:center;justify-content:center;gap:.4rem;background:#EFE3C8;border:1px solid #CBB98D;border-radius:.6rem;padding:.5rem .6rem;font-size:.82rem;font-weight:600;color:#92400E;cursor:pointer;text-decoration:none}
    .lm-b:hover{background:#D5C39A}.lm-b.hidden{display:none}
    .lm-reach[hidden]{display:none}
    /* "near me" with no place (Jason, 2026-11-10: "It should try and set Auto-detect first? Maybe drop regions and trip
       planner since near me is already saying its local"): where you are, first; nothing about trips, regions, distance
       (near me is 2 mi) or everywhere */
    .lm-box.lm-near{display:flex;flex-direction:column}
    .lm-near .lm-na,.lm-near #loc-reach,.lm-near #loc-regions,.lm-near #loc-anywhere,.lm-near #loc-context-message{display:none !important}
    .lm-near .lm-h{order:-4}.lm-near #loc-near-why{order:-3;display:block}.lm-near .lm-one{order:-2;margin-bottom:.4rem}.lm-near .lm-two{order:-2;margin-top:0}.lm-near #device-suggestion{order:-1}
    .lm-near #loc-detect{grid-column:1/-1;background:var(--tv-btn);border-color:var(--tv-btn);color:var(--tv-btn-text);padding:.8rem;font-size:1rem}.lm-near #loc-detect:hover{background:var(--tv-btn-hover)}
    #loc-near-why{display:none;margin:-.2rem 0 .7rem;font-size:.88rem;color:#5C4632}
    /* inside the search's window (with the search open): under its top line, in place of the results */
    #location-modal.lm-docked{display:none !important}
    .tvs-panel.tvs-placing>.tvs-body{display:none}
    .tvs-panel .lm-box{max-width:none;width:auto;height:auto;max-height:none;margin:0;border:0;border-radius:0;box-shadow:none;overflow:visible;padding:1rem 1.15rem 1.2rem}
    .lm-rr{display:flex;align-items:center;gap:.7rem}.lm-rr input{flex:1;accent-color:#B45309}.lm-rr output{min-width:4.5rem;font-size:.86rem;font-weight:600;color:#3E2B1F}
    .lm-rr input:disabled+output{color:#8A7355}
    @media (max-width:420px){.lm-3{grid-template-columns:4.4rem minmax(0,1fr) 4.8rem}.lm-3 .lm-go{grid-column:1/-1}}
    /* phones: the whole screen, and the destination list in the page's flow so every match shows (decisions/0058 §25) */
    @media (max-width:640px){
      #location-modal{padding:0 !important;align-items:stretch !important}
      #location-modal:not(.hidden){position:fixed;inset:0;display:flex;background:rgba(0,0,0,.6)}
      .lm-box{max-width:none;height:100vh;height:100dvh;max-height:none;border-radius:0;border:0;padding:1rem 1rem 1.4rem;-webkit-overflow-scrolling:touch}
      .lm-h{font-size:1.5rem;padding-right:2.2rem}
      .lm-box .lm-sug{position:static;margin-top:.35rem;box-shadow:none}   /* this box's own list only: a place box's list (.tv-pbox-list, also .lm-sug) keeps floating under its box, or it lands inside the bar and pushes GO off a phone (2026-11-27) */
      .lm-sug button{padding:.7rem .75rem;font-size:.95rem}
      .lm-in{font-size:1rem;padding:.65rem .75rem}
    }
  </style>
  <div id="location-modal" role="dialog" aria-modal="true" aria-labelledby="location-modal-title" style="z-index:2000" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="lm-box">
      <button type="button" data-tv-click="closeLocationModal" aria-label="Close" class="absolute top-3 right-4 text-[#8A7355] hover:text-[#3E2B1F] text-xl">&times;</button>
      <h2 id="location-modal-title" class="lm-h">Where are you looking?</h2>
      <!-- Shown only when this modal was triggered by an attempted search with no place yet (requireLocationToSearch). -->
      <p id="loc-context-message" class="text-xs text-amber-700 mb-2 hidden">Choose where to search first.</p>
      <p id="loc-near-why">“Near me” searches 2 miles around you. Or type your town or ZIP below.</p>

      <!-- 1. The trip planner: where, when, how long and what (decisions/0058 §37). Your place stays as it is. -->
      <a class="lm-na" href="/discovery/?view=plan" data-tv-click="closeLocationModal">
        <span class="lm-tp" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="19" r="3"/><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"/><circle cx="18" cy="5" r="3"/></svg></span>
        <span><b>Trip Planner</b><small>Plan a trip: where, when, how long and what you're after</small></span><span aria-hidden="true">&rsaquo;</span>
      </a>


      <!-- Saved places (signed in; filled by js/location-scope.js) -->
      <div id="saved-places-section" class="hidden">
        <span class="lm-lbl">Your places</span>
        <div id="saved-places-list" class="lm-row" data-more></div>
      </div>

      <!-- Regions: one swipeable row (the pilot first, the rest coming soon) -->
      <div id="loc-regions">
        <span class="lm-lbl">Regions</span>
        <div id="region-picker" data-more class="lm-row text-xs text-[#5C4632]"></div>
      </div>

      <!-- 3. A destination by name: a town, an area, a region or a local name ("Wide Ruins") -->
      <label class="lm-lbl" for="loc-dest">Destination</label>
      <!-- Jason, 2026-11-11: the town box as on the home page's split search, one box for a town or ZIP and the target icon
           beside it (Auto-detect, in one tap); the rest of this window as it was -->
      <div class="lm-one">
        <div class="lm-dest">
          <input id="loc-dest" class="lm-in" type="search" placeholder="Town or ZIP" autocomplete="off" aria-autocomplete="list" aria-controls="loc-dest-list">
          <div id="loc-dest-list" class="lm-sug" role="listbox" hidden></div>
        </div>
        <button type="button" class="lm-tgt" id="loc-detect" data-tv-click="autoDetectLocation:event" title="Use my location" aria-label="Use my location"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon></svg></button>
      </div>

      <!-- 2. State | City | ZIP on one line -->
      <form data-tv-submit="handleLocationSubmit:event">
        <span class="lm-lbl">Or State · City · ZIP</span>
        <div class="lm-3">
          <select id="loc-state" class="lm-in" aria-label="State">
            <option value="all">State</option>
            <option value="AZ">AZ</option>
            <option value="NM">NM</option>
            <option value="UT">UT</option>
            <option value="CO">CO</option>
            <option disabled>──</option>
            <option value="AL">AL</option>
            <option value="AK">AK</option>
            <option value="AR">AR</option>
            <option value="CA">CA</option>
            <option value="CT">CT</option>
            <option value="DE">DE</option>
            <option value="DC">DC</option>
            <option value="FL">FL</option>
            <option value="GA">GA</option>
            <option value="HI">HI</option>
            <option value="ID">ID</option>
            <option value="IL">IL</option>
            <option value="IN">IN</option>
            <option value="IA">IA</option>
            <option value="KS">KS</option>
            <option value="KY">KY</option>
            <option value="LA">LA</option>
            <option value="ME">ME</option>
            <option value="MD">MD</option>
            <option value="MA">MA</option>
            <option value="MI">MI</option>
            <option value="MN">MN</option>
            <option value="MS">MS</option>
            <option value="MO">MO</option>
            <option value="MT">MT</option>
            <option value="NE">NE</option>
            <option value="NV">NV</option>
            <option value="NH">NH</option>
            <option value="NJ">NJ</option>
            <option value="NY">NY</option>
            <option value="NC">NC</option>
            <option value="ND">ND</option>
            <option value="OH">OH</option>
            <option value="OK">OK</option>
            <option value="OR">OR</option>
            <option value="PA">PA</option>
            <option value="RI">RI</option>
            <option value="SC">SC</option>
            <option value="SD">SD</option>
            <option value="TN">TN</option>
            <option value="TX">TX</option>
            <option value="VT">VT</option>
            <option value="VA">VA</option>
            <option value="WA">WA</option>
            <option value="WV">WV</option>
            <option value="WI">WI</option>
            <option value="WY">WY</option>
          </select>
          <input type="text" id="loc-city" class="lm-in" placeholder="City or town" aria-label="City or town">
          <input type="text" id="loc-zip" class="lm-in" placeholder="ZIP" inputmode="numeric" maxlength="10" aria-label="ZIP code">
          <button type="submit" class="lm-go">Go</button>
        </div>
      </form>

      <!-- After the place (Jason, 2026-11-11: shown only once a place is set). How far around the place to search: the search's own Distance setting, kept on this device; shown wherever
           the search is -->
      <div id="loc-reach" class="lm-reach" hidden>
        <label class="lm-lbl" for="loc-reach-in">Distance</label>
        <div class="lm-rr"><input type="range" id="loc-reach-in" min="0" max="7" step="1" value="2" aria-label="How far to search"><output id="loc-reach-v"></output></div>
      </div>

      <!-- 4. Auto-detect is the target beside the town box (applied when tapped, as the town area it finds; decisions/0058 §8) · 5. Sign in for saved places, or your default -->
      <div class="lm-two">
        <a class="lm-b" id="loc-signin" href="/login.php">Sign in for your places</a>
        <button type="button" class="lm-b hidden" id="loc-default" data-tv-click="resetLocationScope">My default place</button>
        <button type="button" class="lm-b" id="loc-anywhere" data-tv-click="chooseAnywhere" title="No place: search everywhere, nearest first only when you set one">Search everywhere</button>
      </div>
      <div id="device-suggestion" class="hidden mt-3 p-3 bg-[#EFE3C8] border border-[#CBB98D] rounded-lg" aria-live="polite"></div>
    </div>
  </div>
