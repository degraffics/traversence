<?php
// app/ui/score-gauge.php — the board's score gauge, in Arc (handoffs/control-board-plan/step3/03-cards.md §27; Jason,
// 2026-11-22: "how about we dont use a needle and instead use the score like gauge", then Arc, his choice: "just show the
// top half of the circle and make the band go 0 - 100 with 50 being the half way mark"). The top half only: 0 at the left
// end, 50 at the top, 100 at the right end, the number in the opening, a tiny 0 and 100 at the ends. No needle, no Ring, no
// style switch: Arc is the board's only style. One measure per gauge (Jason, 2026-11-22: "dont combine or split the focus
// of the gauge").
// Colour: the stage's (Planning taupe, Phased Development amber, Audit & Review copper, Implementation slate, Monitoring
// sage); at Monitoring a runtime gauge takes its reading against its target (met sage, below amber, a trip red, no reading
// taupe). The stage word is the caller's pill under the gauge, so colour is never the only signal (brand.md §8).
// From the mockups' generator (step3/mockups/src/scorering.py), drawn the same, on the site's tokens (app/ui/theme-tokens.php).
declare(strict_types=1);

/** the five stages of the work (Jason, 2026-11-22: "(a) Stage of the work"): key => [word, colour class] */
const TV_SG_STAGES = [
    'planning' => ['Planning', 'pl'],
    'phased' => ['Phased Development', 'pd'],
    'audit' => ['Audit & Review', 'ar'],
    'impl' => ['Implementation', 'im'],
    'monitor' => ['Monitoring', 'mo'],
];
/** a reading against its target, at Monitoring: band => colour class */
const TV_SG_BANDS = ['good' => 'g', 'below' => 'a', 'poor' => 'r', 'none' => 'n'];

/**
 * One gauge. $score: 0–100 (the band's share), or null for no reading (the track only, "—").
 * $label: the aria-label ("Directory: 52 percent, Phased Development"). $k: a stage key (TV_SG_STAGES) or a band (TV_SG_BANDS).
 * $o: 'shown' (the number as written), 'unit' ('%'), 'big' (true for a snapshot's big reading), 'cls', 'attrs'.
 */
function tv_score_gauge(?float $score, string $label, string $k, array $o = []): string
{
    $e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    $cls = TV_SG_STAGES[$k][1] ?? (TV_SG_BANDS[$k] ?? (in_array($k, ['pl', 'pd', 'ar', 'im', 'mo', 'g', 'a', 'r', 'n'], true) ? $k : 'n'));
    $v = $score === null ? null : max(0.0, min(100.0, $score));
    $d = $v ?? 0.0;
    $num = $v === null ? '—' : (string) ($o['shown'] ?? number_format($v, 0));
    $unit = $v === null ? '' : '<small>' . $e($o['unit'] ?? '%') . '</small>';
    $arc = 'M6 38 A30 30 0 0 1 66 38';
    return '<span class="sr k-' . $cls . (!empty($o['big']) ? ' big' : '') . (!empty($o['cls']) ? ' ' . $e($o['cls']) : '') . '" role="img" aria-label="' . $e($label) . '" data-score="' . number_format($d, 1, '.', '') . '"' . ($o['attrs'] ?? '') . '>'
        . '<svg class="sr-arc" viewBox="0 0 72 48" aria-hidden="true" focusable="false"><path class="sr-t" d="' . $arc . '"/>'
        . '<path class="sr-a" d="' . $arc . '" pathLength="100" stroke-dasharray="' . number_format($d, 1, '.', '') . ' 100"/>'
        . '<text class="sr-e" x="6" y="47">0</text><text class="sr-e" x="66" y="47">100</text></svg>'
        . '<span class="sr-n' . (mb_strlen($num) > 3 ? ' sr-long' : '') . '">' . $e($num) . $unit . '</span></span>';
}

/** the stage word as a pill (the gauge's colour is never the only signal) */
function tv_score_stage_pill(string $stage): string
{
    [$word, $c] = TV_SG_STAGES[$stage] ?? ['Planning', 'pl'];
    return '<span class="sr-pill k-' . $c . '">' . htmlspecialchars($word, ENT_QUOTES, 'UTF-8') . '</span>';
}

/** the gauge's rules, once per page */
function tv_score_gauge_css(): string
{
    static $done = false;
    if ($done) return '';
    $done = true;
    return '<style>'
        . '.sr{position:relative;display:block;width:76px;margin:.15rem auto 0;line-height:1;color:var(--tv-taupe,#7A6A52)}'
        . '.sr svg{display:block;width:100%;height:auto;overflow:visible}'
        . '.sr-t{fill:none;stroke:var(--tv-card-edge,#E3DBC6);stroke-width:6}.sr-a{fill:none;stroke:currentColor;stroke-width:6;stroke-linecap:butt;transition:stroke-dasharray 150ms ease-out}'
        . '.sr-e{font:600 7px system-ui,sans-serif;fill:var(--tv-meta,#6b5d49);text-anchor:middle}'
        . '.sr-n{position:absolute;left:0;right:0;bottom:17%;text-align:center;font-weight:700;font-size:18px;color:currentColor;letter-spacing:-.02em}'
        . '.sr-n small{font-size:10px;font-weight:600;margin-left:1px}.sr .sr-n.sr-long{font-size:15px}.sr.big .sr-n.sr-long{font-size:26px}'
        . '.sr.k-pl,.sr.k-n{color:var(--tv-taupe,#7A6A52)}.sr.k-pd,.sr.k-a{color:var(--tv-hold,#8f5600)}.sr.k-ar{color:var(--tv-copper,#C85A17)}'
        . '.sr.k-im{color:var(--tv-river,#3B5A6B)}.sr.k-mo,.sr.k-g{color:#3d6b52}.sr.k-r{color:var(--tv-fail,#a62b2b)}'
        . '.sr.big{width:140px}.sr.big .sr-n{font-size:30px}.sr.big .sr-n small{font-size:14px}'
        . '.sr-pill{display:inline-block;font-size:11px;font-weight:600;padding:.1rem .45rem;border-radius:999px;border:1px solid currentColor;line-height:1.3;white-space:nowrap}'
        . '.sr-pill.k-pl{color:var(--tv-meta,#6b5d49)}.sr-pill.k-pd{color:var(--tv-hold,#8f5600)}.sr-pill.k-ar{color:#9a3f0c}.sr-pill.k-im{color:var(--tv-river,#3B5A6B)}.sr-pill.k-mo{color:#3d6b52}'
        . '@media (max-width:767px){.sr{width:68px}.sr-n{font-size:16px}}'
        . '</style>';
}
