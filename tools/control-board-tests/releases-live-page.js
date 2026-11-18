module.exports = function (v) {
  const card = (id, state, label, body, open) => `<details class="rel-fold" id="rel-${id}" data-rel-fold="${id}" data-rel-state="${state}"${open ? ' open' : ''}><summary>#${id} ${label}</summary><p data-tv-status="${state==='staged'?'bad':'ok'}">${body}</p><input class="ak-in" data-rel-note="${id}:ui" value=""></details>`;
  const cards = v === 1
    ? card(121, 'staged', 'On staging', 'On staging, and a check failed', true) + card(120, 'live', 'Live', 'Live for everyone', false)
    : card(123, 'staged', 'On staging', 'On staging and its checks passed', true) + card(121, 'withdrawn', 'Withdrawn', 'Withdrawn by itself: its checks on staging failed', false) + card(120, 'live', 'Live', 'Live for everyone', false);
  return `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><div id="rel" data-csrf="x">top</div>${cards}<script src="/admin-releases.js"></script></body></html>`;
};
