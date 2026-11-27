<?php
// /user/api/posts.php — Moments and Keep (decisions/0153, app/lib/MemberPosts.php). POSTs need X-CSRF-Token.
//   GET ?owner=N[&before=ID]   -> {posts}        a member's posts this viewer may see (anyone; guests see Public only)
//   GET ?owner=<you>&as=anyone|follower|connection -> {posts}   your posts as that viewer would see them (preview)
//   GET ?business=N            -> {posts}        a business's posts this viewer may see (its listing page)
//   GET ?id=N                  -> {post}
//   GET ?comments=N            -> {comments}
//   GET ?viewers=N             -> {viewers}      who saw your post (named when linked to you, the rest counted)
//   GET ?reactors=N            -> {reactors}
//   GET ?history=N             -> {history}      your post's earlier words
//   GET ?me=1                  -> {limits, audience, audiences, windows, reactions, comments_from}  for the composer
//   GET ?to=words              -> {to}           who a Snap can go to: groups, connections, people found (each ok or why not)
//   POST {action: post, body, media: [ids], audience, window, keep, tz, to: [{kind: person|group, id}], feed, as: business id} -> {post (+ sent)}
//   POST {action: keep|delete, id}                                            -> {post} | {}
//   POST {action: edit, id, body?, audience?, window?, pinned?, comments_from?} -> {post}
//   POST {action: react, id, reaction}                                        -> {reactions, reacted}
//   POST {action: comment, id, body} | {action: uncomment, comment}          -> {comment} | {}
//   POST {action: seen, ids: […]}                                             -> {n}
//   POST {action: report, id, reason, note}                                   -> {}
//   POST {action: audience, audience}                                         -> {audience}   your default for new posts
declare(strict_types=1);

require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/lib/MemberPosts.php';

header('Cache-Control: no-store');
$pdo = Database::connection();
if (!MemberPosts::ready($pdo)) Response::error('Posts aren\'t switched on yet.', 503, 'not_ready');
$me = Auth::currentUser();
$uid = $me ? (int) $me['id'] : null;
$need = function () use ($uid): int { if (!$uid) Response::error('Please sign in.', 401, 'unauthenticated'); return $uid; };

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['owner'], $_GET['as']) && $uid === (int) $_GET['owner'] && in_array($_GET['as'], ['anyone', 'follower', 'connection'], true))   // your profile as others see it
            Response::json(['ok' => true, 'posts' => MemberPosts::forOwnerAs($pdo, $uid, (string) $_GET['as'], (int) ($_GET['before'] ?? 0))]);
        if (isset($_GET['owner'])) Response::json(['ok' => true, 'posts' => MemberPosts::forOwner($pdo, (int) $_GET['owner'], $uid, (int) ($_GET['before'] ?? 0))]);
        if (isset($_GET['business'])) Response::json(['ok' => true, 'posts' => MemberPosts::forBusiness($pdo, (int) $_GET['business'], $uid, (int) ($_GET['before'] ?? 0))]);
        if (isset($_GET['id'])) {
            $p = MemberPosts::get($pdo, (int) $_GET['id']);
            if (!$p || !MemberPosts::canSee($pdo, $p, $uid)) Response::error('That post isn\'t available. It may have ended (posts last 3 or 7 days unless kept), or it\'s shared with fewer people.', 404, 'not_found');
            Response::json(['ok' => true, 'post' => MemberPosts::view($pdo, $p, $uid)]);
        }
        if (isset($_GET['comments'])) Response::json(['ok' => true, 'comments' => MemberPosts::comments($pdo, (int) $_GET['comments'], $uid)]);
        if (isset($_GET['viewers'])) Response::json(['ok' => true, 'viewers' => MemberPosts::viewers($pdo, $need(), (int) $_GET['viewers'])]);
        if (isset($_GET['reactors'])) Response::json(['ok' => true, 'reactors' => MemberPosts::reactors($pdo, $need(), (int) $_GET['reactors'])]);
        if (isset($_GET['to'])) Response::json(['ok' => true, 'to' => MemberPosts::recipients($pdo, $need(), (string) $_GET['to'])]);   // the composer's To line
        if (isset($_GET['history'])) Response::json(['ok' => true, 'history' => MemberPosts::history($pdo, $need(), (int) $_GET['history'])]);
        if (isset($_GET['me'])) {
            $u = $need();
            $list = fn (array $a) => array_map(fn ($k, $v) => is_array($v) ? [$k, $v[0], $v[1]] : [$k, $v], array_keys($a), $a);
            Response::json(['ok' => true, 'limits' => MemberPosts::limits($pdo, $u), 'audience' => MemberPosts::defaultAudience($pdo, $u), 'businesses' => MemberPosts::businessesOf($pdo, $u),
                'audiences' => $list(MemberPosts::AUDIENCES), 'windows' => $list(MemberPosts::WINDOWS), 'reactions' => $list(MemberPosts::REACTIONS), 'comments_from' => $list(MemberPosts::COMMENTS_FROM),
                'csrf' => $_SESSION['csrf_token'] ?? '']);
        }
        Response::error('Unknown request.', 400, 'bad_request');
    }
    Response::requireMethod('POST');
    Response::requireCsrf();
    $u = $need();
    $b = Response::jsonBody();
    $id = (int) ($b['id'] ?? 0);
    switch ((string) ($b['action'] ?? '')) {
        case 'post': Response::json(['ok' => true, 'post' => MemberPosts::post($pdo, $u, $b)]);
        case 'keep': Response::json(['ok' => true, 'post' => MemberPosts::keep($pdo, $u, $id), 'message' => 'Kept: it stays, and its photos and videos are in your Gallery.']);
        case 'delete': MemberPosts::delete($pdo, $u, $id); Response::json(['ok' => true, 'message' => 'Deleted.']);
        case 'edit': Response::json(['ok' => true, 'post' => MemberPosts::edit($pdo, $u, $id, $b)]);
        case 'react': Response::json(['ok' => true] + MemberPosts::react($pdo, $u, $id, (string) ($b['reaction'] ?? '')));
        case 'comment': Response::json(['ok' => true, 'comment' => MemberPosts::comment($pdo, $u, $id, (string) ($b['body'] ?? ''))]);
        case 'uncomment': MemberPosts::uncomment($pdo, $u, (int) ($b['comment'] ?? 0)); Response::json(['ok' => true]);
        case 'seen': Response::json(['ok' => true, 'n' => MemberPosts::seen($pdo, $u, (array) ($b['ids'] ?? []))]);
        case 'audience': Response::json(['ok' => true, 'audience' => MemberPosts::setDefaultAudience($pdo, $u, (string) ($b['audience'] ?? ''))]);
        case 'report':
            require_once __DIR__ . '/../../../app/lib/Reports.php';
            Reports::file($pdo, $u, 'member_post', $id, (string) ($b['reason'] ?? ''), (string) ($b['note'] ?? ''));
            Response::json(['ok' => true, 'message' => 'Reported. Our team looks at it; they never tell the person who reported it.']);
    }
    Response::error('Unknown action.', 400, 'bad_request');
} catch (InvalidArgumentException $x) {
    Response::error($x->getMessage(), 422, 'not_allowed');
} catch (PDOException $x) {   // a database error is a RuntimeException too: logged, never shown (its SQLSTATE and query are ours), and the way forward said
    error_log('user/api/posts.php database: ' . $x->getMessage());
    Response::error('Something went wrong on our side. Try again in a minute; if it keeps happening, tell us at support@traversence.com.', 500, 'server_error');
} catch (RuntimeException $x) {
    $c = (int) $x->getCode();
    Response::error($x->getMessage(), in_array($c, [401, 428, 429], true) ? $c : 500, $c === 428 ? 'human_check' : ($c === 429 ? 'too_many' : 'server_error'));
} catch (Throwable $x) {
    error_log('user/api/posts.php: ' . $x->getMessage());
    Response::error('Something went wrong. Please try again.', 500, 'server_error');
}
