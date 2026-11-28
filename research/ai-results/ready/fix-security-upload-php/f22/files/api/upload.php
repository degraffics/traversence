<?php
// api/upload.php — every file upload on the site (decisions/0127), sent by js/tv-upload.js; app/lib/Uploads.php does
// the work and each destination's rules are in app/lib/upload-targets.php.
//   POST JSON {do: start, target, ctx: {…}, name, size, type}  → {token, mode: s3|pieces, part, urls | pieces, max}
//   POST form do=piece, token, index, piece (a slice of the file)  when S3 isn't set up: pieces through this server
//   POST JSON {do: done, token, etags: [...], extra: {…}}      → what the destination says ({message, …})
//   POST JSON {do: abort, token}
//   POST JSON {do: status, token}                               → how far it got, to carry on from another page
//   POST JSON {do: gallery, target, ctx: {_from_item, …}, extra}  a photo or file from the Gallery: copied (or used where it
//                                                                  is) server-side, nothing sent again (Uploads::gallery)
// Signed in with the page's X-CSRF-Token, except a target open to guests (the guest photo drop), which checks its own key.
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/lib/Uploads.php';

// if PHP stops part way (out of memory, out of time), still answer in JSON so the page says why
register_shutdown_function(function () {
    $e = error_get_last();
    if (!$e || !in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) return;
    // f22: file and line go only to the log; the visitor gets a reference that finds this log line
    $ref = substr(md5(uniqid('', true)), 0, 8);
    error_log('upload stopped [' . $ref . ']: ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line']);
    if (headers_sent()) return;
    http_response_code(500); header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => ['code' => 'stopped', 'message' => str_contains($e['message'], 'memory') ? 'The server ran out of memory working on that file.' : 'The server stopped part way. Try again; if it keeps happening, tell us (reference ' . $ref . ').']]);
});

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') Response::error('Send it as a POST.', 405);
$json = str_starts_with((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json');
$in = $json ? Response::jsonBody() : $_POST;
$do = (string) ($in['do'] ?? '');
$uid = (int) (Auth::currentUserId() ?? 0);
$pdo = Database::connection();
try {
    $target = in_array($do, ['start', 'gallery'], true) ? (string) ($in['target'] ?? '') : Uploads::targetOf((string) ($in['token'] ?? ''));
    $guest = $target !== '' && Uploads::guest($target);   // open to guests: the target checks its own key (and the token is signed)
    if (!$guest) {
        if (!$uid) Response::error('Sign in first.', 401, 'unauthenticated');
        if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) Response::error('This page had expired. Reload it and try again.', 403, 'csrf');
    }
    switch ($do) {
        case 'start': Response::success(Uploads::start($pdo, $uid, $target, (array) ($in['ctx'] ?? []), (string) ($in['name'] ?? ''), (int) ($in['size'] ?? 0), (string) ($in['type'] ?? '')));
        case 'piece': Response::success(Uploads::piece($uid, (string) ($in['token'] ?? ''), (int) ($in['index'] ?? -1), $_FILES['piece'] ?? []));
        case 'done': Response::success(Uploads::finish($pdo, $uid, (string) ($in['token'] ?? ''), (array) ($in['etags'] ?? []), (array) ($in['extra'] ?? [])));
        case 'abort': Uploads::abort($uid, (string) ($in['token'] ?? '')); Response::success();
        case 'status': Response::success(Uploads::status($uid, (string) ($in['token'] ?? '')));
        case 'gallery': if ($guest) Response::error('Sign in first.', 401, 'unauthenticated');
            Response::success(Uploads::gallery($pdo, (int) $uid, $target, (array) ($in['ctx'] ?? []), (array) ($in['extra'] ?? [])));
    }
    Response::error('Unknown action.', 422, 'bad_action');
} catch (InvalidArgumentException $x) {
    Response::error($x->getMessage(), 422, 'refused', (property_exists($x, 'upgrade') && $x->upgrade ? ['upgrade' => $x->upgrade] : []) + (property_exists($x, 'feature') && $x->feature ? ['feature' => $x->feature] : []));   // feature: the upgrade sheet's (js/tv-upgrade.js)
} catch (RuntimeException $x) {
    error_log('upload: ' . $x->getMessage());
    Response::error($x->getMessage(), 409, 'error');
}
