<?php
// admin/auto-imports.php — Admin → Crawler → Published: confidence-gated auto-import (decisions/0044, 0046)
//
// Listings the crawler published on its own, with why each qualified, for spot-checks. "Pull from site"
// suppresses one (reversible). What didn't publish waits in Review (admin/crawler-review.php); the old
// quick-approve POST still works for any open tab.
// Admin-only, checked server-side; POSTs carry the session CSRF token and redirect back.

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
header('Content-Type: text/html; charset=utf-8'); // bootstrap defaults to JSON
require_once __DIR__ . '/../../app/ui/admin-crawler.php';   // Admin → Crawler → Published (decisions/0046)

$pdo = Database::connection();
$me = tv_crawler_require_admin($pdo);
foreach (['app/lib/crawler/AutoImport.php', 'app/lib/ListingSafety.php'] as $f) {
    if (!is_file(__DIR__ . '/../../' . $f)) {
        tv_page_start('Published');
        echo '<p class="text-sm">Missing on the server: <code>' . tv_e($f) . '</code>. Upload it from the package, then reload.</p>';
        tv_page_end();
        exit;
    }
}
require_once __DIR__ . '/../../app/lib/crawler/AutoImport.php';
if (is_file(__DIR__ . '/../../app/lib/crawler/Verify.php')) require_once __DIR__ . '/../../app/lib/crawler/Verify.php';
$csrf = (string) ($_SESSION['csrf_token'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) throw new InvalidArgumentException('Your session expired. Reload the page and try again.');
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'pull' || $action === 'restore') {
            AutoImport::suppress($pdo, (int) ($_POST['entity'] ?? 0), $action === 'pull');
            $_SESSION['ai_flash'] = [true, $action === 'pull' ? 'Pulled from the public site.' : 'Back on the public site.'];
        } elseif ($action === 'quick') {
            $r = AutoImport::quickApprove($pdo, (array) ($_POST['rows'] ?? []), (int) $me['id']);
            $msg = "Approved {$r['approved']}.";
            if ($r['skipped']) {
                $msg .= ' Skipped ' . count($r['skipped']) . ': ' . implode('; ', array_map(fn ($id, $why) => "#$id $why", array_keys($r['skipped']), $r['skipped'])) . '.';
            }
            $_SESSION['ai_flash'] = [$r['approved'] > 0 || !$r['skipped'], $msg];
        }
    } catch (Throwable $e) {
        $_SESSION['ai_flash'] = [false, $e->getMessage()];
    }
    header('Location: ' . strtok((string) $_SERVER['REQUEST_URI'], '?'), true, 303);
    exit;
}
$flash = $_SESSION['ai_flash'] ?? null;
unset($_SESSION['ai_flash']);

$recent = AutoImport::recent($pdo, 100);

tv_crawler_start('published', 'Published', $pdo);
require_once __DIR__ . '/../../app/ui/table-kit.php'; echo tv_table_kit();   // the table standard (design/table-standard.md, T4)
?>
<style>
  .ai-card{background:#F7EFDC;border:1px solid rgba(203,185,141,.7);border-radius:1rem;box-shadow:0 4px 6px -1px rgba(0,0,0,.08);padding:1rem;margin-top:1.25rem;max-width:64rem}
  .ai-t{width:100%;font-size:.85rem;border-collapse:collapse;margin-top:.5rem}
  .ai-t td{padding:.35rem .6rem .35rem 0;border-top:1px solid rgba(203,185,141,.45);vertical-align:top}
  .ai-btn{background:#D97706;color:#fff;font-weight:600;border-radius:.75rem;padding:.5rem 1rem}
  .ai-lnk{color:#B45309;text-decoration:underline;font-size:.8rem;background:none;cursor:pointer}
  .ai-ok{color:#166534}.ai-no{color:#991B1B}
</style>

<div>
  <p class="text-sm text-[#5C4632] mt-1 max-w-2xl">
    Crawled listings publish on their own only when they are <strong>real</strong> (a trusted registry, 2+ independent websites, or its own site),
    <strong>complete</strong> (a name, a way to reach it and a place), <strong>appropriate</strong>, and <strong>not already listed</strong> (decisions/0133). The score only decides which are looked at first.
    Auto-import is <strong><?= AutoImport::enabled() ? 'on' : 'off' ?></strong><?= AutoImport::enabled() ? '' : (AutoImport::pausedWhy() !== null ? ': ' . htmlspecialchars(AutoImport::pausedWhy(), ENT_QUOTES) : ' (AUTO_IMPORT=off in .env)') ?>.
    Claims and public reports catch what slips through.
  </p>
</div>

<?php if ($flash): ?>
  <div class="ai-card" style="background:<?= $flash[0] ? '#ECFDF5' : '#FEF2F2' ?>;border-color:<?= $flash[0] ? '#6EE7B7' : '#FCA5A5' ?>" role="status"><?= tv_e($flash[1]) ?></div>
<?php endif; ?>

<section class="ai-card">
  <h2 class="font-serif font-bold text-lg">Recently published automatically</h2>
  <?php if (!$recent): ?>
    <p class="text-sm mt-1">None yet.</p>
  <?php else: ?>
  <table class="ai-t" data-tv-table>
    <?php foreach ($recent as $r): ?>
    <tr>
      <td><a class="ai-lnk" href="/listing/view.php?id=<?= (int) $r['entity_id'] ?>"><?= tv_e($r['name']) ?></a>
        <?= (int) $r['is_suppressed'] ? ' <span class="ai-no text-xs">pulled</span>' : '' ?><br>
        <span class="text-xs text-[#8A7355]"><?= tv_e((string) $r['cluster']) ?> · <?= tv_e(substr((string) $r['reviewed_at'], 0, 16)) ?></span></td>
      <td class="text-xs text-[#5C4632]"><?= tv_e(preg_replace('/^' . AutoImport::NOTE_PREFIX . ':\s*/', '', (string) $r['review_note'])) ?></td>
      <td>
        <form method="post"><input type="hidden" name="csrf" value="<?= tv_e($csrf) ?>"><input type="hidden" name="entity" value="<?= (int) $r['entity_id'] ?>">
          <a class="ai-lnk" href="/admin/listing-edit.php?id=<?= (int) $r['entity_id'] ?>">Edit</a>
          <?php if ((int) $r['is_suppressed']): ?>
            <input type="hidden" name="action" value="restore"><button class="ai-lnk" type="submit">Put back</button>
          <?php else: ?>
            <input type="hidden" name="action" value="pull"><button class="ai-lnk" type="submit">Pull from site</button>
          <?php endif; ?>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</section>

<p class="text-sm mt-4">Listings that didn't publish on their own wait in <a class="ai-lnk" style="font-size:.9rem" href="/admin/crawler-review.php">Review</a>.</p>
<?php tv_crawler_end();
