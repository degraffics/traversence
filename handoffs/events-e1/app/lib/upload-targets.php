<?php
// app/lib/upload-targets.php — where an uploaded file can go (decisions/0127). Each destination says who may add to it,
// what kinds of file and how big (check), and what happens once the file is whole (done): the library that owns the
// record puts it where it belongs. A new kind of upload is a new target here, never a page taking files itself.
//   check(PDO, uid, ctx, file{name, ext, size}) → ['kind' => owner kind, 'ref' => owner ref, 'types' => [ext => mime(s)],
//          'max' => bytes, 'sniff' => bool, 'what' => 'a proof']   (throws when this person may not add here)
//   done(PDO, uid, ctx, Upload, extra) → what the page needs: ['message' => …, …]
//   pick(PDO, uid, ctx, gallery item) → the same for a photo chosen from the Gallery, used where it is (optional; null: copy it)
declare(strict_types=1);

require_once __DIR__ . '/Uploads.php';

// ---- client orders (decisions/0101): proofs, artwork, reference files and shipping labels, for our team -----------------

$tvOrderStaff = function (): void {
    require_once __DIR__ . '/../admin/Access.php';
    // our team's own: a staff login, never a member's session read as the Founder (the CEO's urgent fix, 2026-11-26)
    if (Access::staff() === null) throw new InvalidArgumentException('Sign in with your staff login to add files to an order.');
    [$ok, $why] = Access::can('backoffice/orders.edit', ['min' => 'staff', 'depts' => ['fin', 'svc', 'sal']]);
    if (!$ok) throw new InvalidArgumentException(($why ?: 'Your role can\'t change orders') . '.');
};

Uploads::target('order_proof', [
    'check' => function (PDO $pdo, int $uid, array $ctx, array $f) use ($tvOrderStaff): array {
        $tvOrderStaff();
        require_once __DIR__ . '/ClientOrders.php';
        $oid = (int) ($ctx['order'] ?? 0);
        ClientOrders::canAddFiles($pdo, $oid, true, (int) ($ctx['line'] ?? 0));
        return ['kind' => 'orders', 'ref' => $oid, 'types' => ClientOrders::extTypes(ClientOrders::PROOF_TYPES), 'sniff' => true, 'what' => 'a proof'];
    },
    'done' => function (PDO $pdo, int $uid, array $ctx, Upload $u): array {
        if ((int) ($ctx['replace'] ?? 0)) return ClientOrders::replaceProofFile($pdo, (int) $ctx['order'], (int) $ctx['replace'], $u, $uid);   // one file of a version, swapped
        return ClientOrders::proofFromUpload($pdo, (int) $ctx['order'], (int) $ctx['line'], $u, $uid, (string) ($ctx['batch'] ?? ''));
    },
]);

Uploads::target('order_file', [
    // its staff thread and where its button opens (decisions/0176 §6, release 4): UploadThreads::orderFile, its Notify kind
    'thread' => ['kind' => 'upload.order_file', 'told' => 'the order\'s team (backoffice/orders.edit), never the uploader'],
    'land' => '/admin/backoffice/orders/{order}#docs',
    'check' => function (PDO $pdo, int $uid, array $ctx, array $f) use ($tvOrderStaff): array {
        $tvOrderStaff();
        require_once __DIR__ . '/ClientOrders.php';
        $oid = (int) ($ctx['order'] ?? 0);
        ClientOrders::canAddFiles($pdo, $oid, false, (int) ($ctx['line'] ?? 0));
        $label = ($ctx['kind'] ?? '') === 'label';
        return ['kind' => 'orders', 'ref' => $oid, 'types' => ClientOrders::extTypes($label ? ClientOrders::FILE_TYPES : ClientOrders::ALL_TYPES), 'sniff' => true, 'what' => $label ? 'a shipping label' : 'artwork, reference and documents'];
    },
    'done' => function (PDO $pdo, int $uid, array $ctx, Upload $u): array {
        $kind = (string) ($ctx['kind'] ?? 'artwork');
        $id = ClientOrders::fileFromUpload($pdo, (int) $ctx['order'], (int) ($ctx['line'] ?? 0), $u, $uid, $kind);
        require_once __DIR__ . '/UploadThreads.php'; UploadThreads::orderFile($pdo, (int) $ctx['order'], $kind, $uid);   // the team's thread (0176 §6); never throws
        return ['file' => $id, 'message' => $u->name . ' added' . ($kind === 'label' ? ' as the shipping label.' : (empty($ctx['line']) ? ' to the order.' : '.'))];
    },
]);

// the vendor's invoice dropped on an order's Vendor line (M1b, design §3.6.3): kept as the order's file (kind 'invoice'),
// read in the browser without AI (js/vendor-bill.js), never sent to anyone, so no team thread (unlike order_file)
Uploads::target('order_vendor_bill', [
    'check' => function (PDO $pdo, int $uid, array $ctx, array $f) use ($tvOrderStaff): array {
        $tvOrderStaff();
        require_once __DIR__ . '/ClientOrders.php';
        $oid = (int) ($ctx['order'] ?? 0);
        ClientOrders::canAddFiles($pdo, $oid, false, 0);
        return ['kind' => 'orders', 'ref' => $oid, 'types' => ClientOrders::extTypes(ClientOrders::FILE_TYPES), 'sniff' => true, 'max' => 25 * 1048576, 'what' => 'the vendor\'s invoice (a PDF, or a photo of it)'];
    },
    'done' => function (PDO $pdo, int $uid, array $ctx, Upload $u): array {
        $id = ClientOrders::fileFromUpload($pdo, (int) $ctx['order'], 0, $u, $uid, 'invoice');
        return ['file' => $id, 'message' => $u->name . ' kept with this order\'s files.'];
    },
]);

// ---- the gallery (decisions/0126): videos, originals, brand files and their versions ------------------------------------

/** Whose gallery: a member's own, or a business's for its team (ctx b = the listing). [kind, ref] */
$tvGalleryOwner = function (PDO $pdo, int $uid, array $ctx): array {
    require_once __DIR__ . '/GalleryModes.php';
    if (!$uid) throw new InvalidArgumentException('Sign in first.');
    $b = (int) ($ctx['b'] ?? 0);
    if ($b) {
        if (!Gallery::canManage($pdo, $uid, 'businesses', (string) $b)) throw new InvalidArgumentException('You can\'t manage that business\'s gallery (it needs Catalog & content).');
        return ['businesses', (string) $b];
    }
    $album = (int) ($ctx['album'] ?? 0);
    if ($album) {   // into an album: its owner's, or a contributor's own photos
        $a = Gallery::album($pdo, $album);
        if (!$a || !Gallery::canContribute($pdo, $a, $uid)) throw new InvalidArgumentException('You can\'t add to that album.');
        if (Gallery::canManage($pdo, $uid, (string) $a['owner_kind'], (string) $a['owner_ref'])) return [(string) $a['owner_kind'], (string) $a['owner_ref']];
    }
    return ['members', (string) $uid];
};

Uploads::target('gallery_photo', [
    'check' => function (PDO $pdo, int $uid, array $ctx, array $f) use ($tvGalleryOwner): array {
        [$kind, $ref] = $tvGalleryOwner($pdo, $uid, $ctx);
        Gallery::roomFor($pdo, $kind, $ref, (int) $f['size']);
        return ['kind' => $kind, 'ref' => $ref, 'types' => ['jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp']], 'max' => Gallery::PHOTO_MAX, 'sniff' => true, 'what' => 'a photo'];
    },
    // the copies people see are made from it; in a Portfolio or Brand album (keep) the original is kept too, for downloads
    'done' => function (PDO $pdo, int $uid, array $ctx, Upload $u) use ($tvGalleryOwner): array {
        [$kind, $ref] = $tvGalleryOwner($pdo, $uid, $ctx);
        $item = Gallery::addPhoto($pdo, $uid, $kind, $ref, $u->asFile(), (string) ($ctx['taken'] ?? ''), (string) ($ctx['caption'] ?? ''), (int) ($ctx['album'] ?? 0));
        if (!empty($ctx['keep'])) { require_once __DIR__ . '/GalleryModes.php'; GalleryModes::fileFromUpload($pdo, $uid, ['item' => (int) $item['id']], $u); }
        return ['item' => $item, 'message' => $u->name . ' added.'];
    },
    // chosen from the Gallery (js/tv-art.js): already there, so into the album only (decisions/0143 follow-up: one tool for art)
    'pick' => function (PDO $pdo, int $uid, array $ctx, array $it) use ($tvGalleryOwner): ?array {
        [$kind, $ref] = $tvGalleryOwner($pdo, $uid, $ctx);
        if ($it['owner_kind'] !== $kind || (string) $it['owner_ref'] !== (string) $ref) return null;
        $album = (int) ($ctx['album'] ?? 0);
        if ($album) Gallery::addToAlbum($pdo, $uid, $album, [(int) $it['id']]);
        return ['item' => Gallery::view($it), 'message' => $album ? 'Added to the album from your Gallery.' : 'It\'s already in your Gallery.'];
    },
]);

Uploads::target('gallery_video', [
    'check' => function (PDO $pdo, int $uid, array $ctx, array $f) use ($tvGalleryOwner): array {
        [$kind, $ref] = $tvGalleryOwner($pdo, $uid, $ctx);
        Gallery::roomFor($pdo, $kind, $ref, (int) $f['size']);
        return ['kind' => $kind, 'ref' => $ref, 'types' => ['mp4' => ['video/mp4'], 'mov' => ['video/quicktime'], 'webm' => ['video/webm'], 'm4v' => ['video/x-m4v', 'video/mp4']], 'what' => 'a video'];
    },
    'done' => function (PDO $pdo, int $uid, array $ctx, Upload $u, array $extra) use ($tvGalleryOwner): array {
        [$kind, $ref] = $tvGalleryOwner($pdo, $uid, $ctx);
        $item = Gallery::videoFromUpload($pdo, $uid, $kind, $ref, $u, $extra + ['album' => (int) ($ctx['album'] ?? 0)]);
        return ['item' => $item, 'message' => $u->name . ' added.'];
    },
]);

Uploads::target('gallery_file', [
    'check' => function (PDO $pdo, int $uid, array $ctx, array $f): array {
        require_once __DIR__ . '/GalleryModes.php';
        if (!$uid) throw new InvalidArgumentException('Sign in first.');
        [$kind, $ref] = GalleryModes::fileCheck($pdo, $uid, $ctx, $f['name'], $f['ext'], (int) $f['size']);
        $types = []; foreach (GalleryModes::FILES as $e => $m) $types[$e] = [$m];
        return ['kind' => $kind, 'ref' => $ref, 'types' => $types, 'what' => 'a file here'];
    },
    'done' => function (PDO $pdo, int $uid, array $ctx, Upload $u): array {
        return GalleryModes::fileFromUpload($pdo, $uid, $ctx, $u);
    },
]);

// ---- the Marketplace (decisions/0135): a member's photos of what they're offering ----------------------------------------
// Each goes into their own gallery (so it counts with their photos) and shows through their Marketplace album only while
// the post it's on is up (Market::showPhotos). The page sends the photo first, then the post with its id.

Uploads::target('market_photo', [
    'check' => function (PDO $pdo, int $uid, array $ctx, array $f): array {
        if (!$uid) throw new InvalidArgumentException('Sign in first.');
        require_once __DIR__ . '/Market.php'; require_once __DIR__ . '/Gallery.php'; require_once __DIR__ . '/Standing.php';
        if (!Market::ready($pdo)) throw new InvalidArgumentException('The Marketplace isn\'t switched on yet.');
        Standing::guard($pdo, $uid);
        return ['kind' => 'members', 'ref' => (string) $uid, 'types' => ['jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp']], 'max' => Gallery::PHOTO_MAX, 'sniff' => true, 'what' => 'a photo'];
    },
    'done' => function (PDO $pdo, int $uid, array $ctx, Upload $u): array {
        require_once __DIR__ . '/Gallery.php';
        $item = Gallery::addPhoto($pdo, $uid, 'members', (string) $uid, $u->asFile(), '', mb_substr((string) ($ctx['caption'] ?? ''), 0, 120));
        return ['item' => ['id' => (int) $item['id'], 'card' => $item['card']], 'message' => $u->name . ' added.'];
    },
    'pick' => function (PDO $pdo, int $uid, array $ctx, array $it): ?array {   // one of their own Gallery photos, used where it is
        if ($it['owner_kind'] !== 'members' || (string) $it['owner_ref'] !== (string) $uid) return null;
        return ['item' => ['id' => (int) $it['id'], 'card' => Gallery::url($it, 'card')], 'message' => 'Added from your Gallery.'];
    },
]);

// ---- a business's catalog items on the Marketplace (decisions/0135, step 3): photos for an item's draft ------------------
// Into the business's gallery (so they count toward its plan's storage), then onto the item's draft; publishing puts them
// in its public Marketplace album. Its team with Catalog & content may add them.

Uploads::target('catalog_photo', [
    'check' => function (PDO $pdo, int $uid, array $ctx, array $f): array {
        if (!$uid) throw new InvalidArgumentException('Sign in first.');
        require_once __DIR__ . '/Gallery.php'; require_once __DIR__ . '/MarketBiz.php';
        $b = (int) ($ctx['b'] ?? 0);
        if (!MarketBiz::ready($pdo)) throw new InvalidArgumentException('The Marketplace for businesses isn\'t switched on yet.');
        if (!$b || !Gallery::canManage($pdo, $uid, 'businesses', (string) $b)) throw new InvalidArgumentException('You can\'t add photos for that business (it needs Catalog & content).');
        if (count(MarketBiz::photoIds($pdo, $b, (int) ($ctx['product'] ?? 0), false)) >= MarketBiz::MAX_PHOTOS) throw new InvalidArgumentException('That\'s ' . MarketBiz::MAX_PHOTOS . ' photos, the most for one item.');
        Gallery::roomFor($pdo, 'businesses', (string) $b, (int) $f['size']);   // its plan's storage
        return ['kind' => 'businesses', 'ref' => (string) $b, 'types' => ['jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp']], 'max' => Gallery::PHOTO_MAX, 'sniff' => true, 'what' => 'a photo'];
    },
    'done' => function (PDO $pdo, int $uid, array $ctx, Upload $u): array {
        require_once __DIR__ . '/Gallery.php'; require_once __DIR__ . '/MarketBiz.php';
        $b = (int) $ctx['b'];
        $item = Gallery::addPhoto($pdo, $uid, 'businesses', (string) $b, $u->asFile());
        MarketBiz::addDraftPhoto($pdo, $b, (int) ($ctx['product'] ?? 0), (int) $item['id']);
        return ['item' => ['id' => (int) $item['id'], 'card' => $item['card']], 'message' => $u->name . ' added to the draft. Publish changes to show it.'];
    },
    'pick' => function (PDO $pdo, int $uid, array $ctx, array $it): ?array {   // one of the business's Gallery photos, used where it is
        $b = (int) $ctx['b'];
        if ($it['owner_kind'] !== 'businesses' || (string) $it['owner_ref'] !== (string) $b) return null;
        MarketBiz::addDraftPhoto($pdo, $b, (int) ($ctx['product'] ?? 0), (int) $it['id']);
        return ['item' => ['id' => (int) $it['id'], 'card' => Gallery::url($it, 'card')], 'message' => 'Added to the draft from the Gallery. Publish changes to show it.'];
    },
]);

// A Digital goods item's file (decisions/0141 §5, ItemKinds): kept private in the business's folder, never public; buyers
// reach it only through their download link (market/download.php), which counts its uses and days.
Uploads::target('market_digital', [
    'check' => function (PDO $pdo, int $uid, array $ctx, array $f): array {
        if (!$uid) throw new InvalidArgumentException('Sign in first.');
        require_once __DIR__ . '/Gallery.php'; require_once __DIR__ . '/MarketBiz.php';
        $b = (int) ($ctx['b'] ?? 0);
        if (!$b || !MarketBiz::onTeam($pdo, $uid, $b, 'catalog', 'edit')) throw new InvalidArgumentException('You can\'t add files for that business (it needs Catalog & content).');
        Gallery::roomFor($pdo, 'businesses', (string) $b, (int) $f['size']);   // its plan's storage
        return ['kind' => 'businesses', 'ref' => (string) $b, 'types' => ['pdf' => ['application/pdf'], 'zip' => ['application/zip', 'application/x-zip-compressed'], 'epub' => ['application/epub+zip', 'application/zip'],
            'mp3' => ['audio/mpeg'], 'm4a' => ['audio/mp4', 'audio/x-m4a'], 'mp4' => ['video/mp4'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png']], 'max' => 500 * 1024 * 1024, 'sniff' => true, 'what' => 'the file buyers download'];
    },
    'done' => function (PDO $pdo, int $uid, array $ctx, Upload $u): array {
        require_once __DIR__ . '/BusinessData.php'; require_once __DIR__ . '/Products.php'; require_once __DIR__ . '/ItemKinds.php';
        $b = (int) $ctx['b']; $pid = (int) ($ctx['product'] ?? 0);
        BusinessData::open($b);
        $p = Products::get($pdo, $pid);
        if (!$p) throw new InvalidArgumentException('That item isn\'t in your catalog.');
        $rel = 'businesses/' . Store::folder('businesses', (string) $b) . '/market/digital/' . $pid . '/' . bin2hex(random_bytes(8)) . '.' . ($u->ext === 'jpeg' ? 'jpg' : $u->ext);
        $old = (string) (ItemKinds::data('digital', $p['kind_data'] ?? null)['file_key'] ?? '');
        $mime = $u->mime();   // read before it moves
        $u->moveTo(dirname(__DIR__) . '/storage/' . $rel);
        Products::setKindValue($pdo, $pid, ['file_key' => $rel, 'file_name' => mb_substr($u->name, 0, 160), 'file_size' => $u->size, 'file_mime' => $mime]);
        if ($old !== '' && $old !== $rel && !MarketBiz::fileInUse($pdo, $old)) Store::remove(dirname(__DIR__) . '/storage/' . $old);   // the one it replaced, once no published version still sells it
        return ['message' => $u->name . ' is its file now. Publish changes to sell it.'];
    },
]);

// ---- a business's quotes (decisions/0120): proofs and files, for its team --------------------------------------------

/** Opens the business's realm for this person (their role on it, then Quotes edit). Returns the listing id. */
$tvBizOpen = function (PDO $pdo, int $uid, array $ctx): int {
    require_once __DIR__ . '/../business/portal.php';
    require_once __DIR__ . '/BusinessData.php';
    require_once __DIR__ . '/BizFiles.php';
    $id = (int) ($ctx['b'] ?? 0);
    $bctx = $uid ? tv_biz_context($pdo, $uid, $id) : null;
    if (!$bctx) throw new InvalidArgumentException('This isn\'t one of your businesses.');
    [$ok, $why] = tv_biz_can($pdo, $bctx, 'quotes', 'edit');
    if (!$ok) throw new InvalidArgumentException($why . '.');
    if (!Scope::isBusiness()) BusinessData::open($id);
    Scope::setPeople($bctx['people']);
    return $id;
};

Uploads::target('biz_file', [
    'check' => function (PDO $pdo, int $uid, array $ctx, array $f) use ($tvBizOpen): array {
        $id = $tvBizOpen($pdo, $uid, $ctx);
        $kind = ($ctx['kind'] ?? '') === 'proof' ? 'proof' : 'file';
        return ['kind' => 'businesses', 'ref' => $id, 'types' => BizFiles::uploadCheck($pdo, (int) ($ctx['quote'] ?? 0), $kind, $f['name'], (int) $f['size']), 'sniff' => true, 'what' => $kind === 'proof' ? 'a proof' : 'a file'];
    },
    'done' => function (PDO $pdo, int $uid, array $ctx, Upload $u) use ($tvBizOpen): array {
        $id = $tvBizOpen($pdo, $uid, $ctx);
        $msg = BizFiles::fromUpload($pdo, (int) ($ctx['quote'] ?? 0), (string) ($ctx['kind'] ?? 'file'), $u, !empty($ctx['shared']), (string) ($ctx['note'] ?? ''), (string) ($ctx['batch'] ?? ''), $uid);
        try { $role = tv_biz_context($pdo, $uid, $id)['role'] ?? ''; if ($role === 'white_glove') Audit::log($pdo, ['user' => $uid, 'listing' => $id, 'route' => 'white_glove', 'action' => 'quotes.file_add', 'detail' => 'quote ' . (int) ($ctx['quote'] ?? 0)]); } catch (Throwable) {}
        return ['message' => $msg];
    },
]);

// ---- guest photos (decisions/0126 phase 2): anyone with an album's guest link, no account ----------------------------

/** The guest's album, after its page's own key; and a guest, without storing who they are. [album, who] */
$tvGuest = function (PDO $pdo, array $ctx): array {
    require_once __DIR__ . '/Gallery.php';
    $key = preg_match('/^[a-f0-9]{32}$/', (string) ($ctx['g'] ?? '')) ? (string) $ctx['g'] : '';
    $secret = (string) (getenv('GUEST_HASH_KEY') ?: 'tv-guest');
    if ($key === '' || !hash_equals(hash_hmac('sha256', 'gallery-drop|' . $key, $secret), (string) ($ctx['fk'] ?? ''))) throw new InvalidArgumentException('This page had expired. Reload it and try again.');
    $a = Gallery::guestAlbum($pdo, $key);
    if (!$a) throw new InvalidArgumentException('This album isn\'t taking photos from guests any more.');
    return [$a, substr(hash_hmac('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''), $secret), 0, 16), $key];
};

Uploads::target('gallery_guest', [
    'guest' => true,
    'check' => function (PDO $pdo, int $uid, array $ctx, array $f) use ($tvGuest): array {
        [$a, $who] = $tvGuest($pdo, $ctx);
        Gallery::guestRoom($pdo, (int) $a['id'], $who);
        return ['kind' => (string) $a['owner_kind'], 'ref' => (string) $a['owner_ref'], 'types' => ['jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp']], 'max' => 25 * 1024 * 1024, 'sniff' => true, 'what' => 'a photo'];
    },
    'done' => function (PDO $pdo, int $uid, array $ctx, Upload $u) use ($tvGuest): array {
        [, $who, $key] = $tvGuest($pdo, $ctx);
        $v = Gallery::addGuestPhoto($pdo, $key, $u->asFile(), (string) ($ctx['name'] ?? ''), (string) ($ctx['taken'] ?? ''), $who);
        return ['id' => (int) $v['id'], 'message' => 'Added. Thank you!'];
    },
]);

// ---- a business's storefront (decisions/0139, step 5a): its logo or cover ---------------------------------------------
// Into the business's gallery (its plan's storage), then onto the storefront, which keeps them in a public album.

// ---- an event's cover photo (decisions/0134's minimum): into the business's Gallery; the form then saves the event with it
Uploads::target('event_photo', [
    'check' => function (PDO $pdo, int $uid, array $ctx, array $f): array {
        if (!$uid) throw new InvalidArgumentException('Sign in first.');
        require_once __DIR__ . '/Gallery.php'; require_once __DIR__ . '/Events.php';
        $b = (int) ($ctx['b'] ?? 0);
        if (!$b || !Events::ready($pdo)) throw new InvalidArgumentException('Events aren\'t open yet.');
        [$ok, $why] = Events::canPost($pdo, $uid, $b); if (!$ok) throw new InvalidArgumentException($why);
        if (!Gallery::canManage($pdo, $uid, 'businesses', (string) $b)) throw new InvalidArgumentException('You can\'t add photos for that business (it needs Catalog & content).');
        Gallery::roomFor($pdo, 'businesses', (string) $b, (int) $f['size']);   // its plan's storage
        return ['kind' => 'businesses', 'ref' => (string) $b, 'types' => ['jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp']], 'max' => Gallery::PHOTO_MAX, 'sniff' => true, 'what' => 'a photo'];
    },
    'done' => function (PDO $pdo, int $uid, array $ctx, Upload $u): array {
        require_once __DIR__ . '/Gallery.php';
        $item = Gallery::addPhoto($pdo, $uid, 'businesses', (string) (int) $ctx['b'], $u->asFile());
        return ['item' => ['id' => (int) $item['id'], 'card' => $item['card']], 'message' => $u->name . ' is the event\'s cover.'];
    },
    'pick' => function (PDO $pdo, int $uid, array $ctx, array $it): ?array {   // one of the business's own Gallery photos
        if ($it['owner_kind'] !== 'businesses' || (string) $it['owner_ref'] !== (string) (int) $ctx['b']) return null;
        return ['item' => ['id' => (int) $it['id'], 'card' => Gallery::url($it, 'card')], 'message' => 'That photo is the event\'s cover.'];
    },
]);

Uploads::target('storefront_photo', [
    'check' => function (PDO $pdo, int $uid, array $ctx, array $f): array {
        if (!$uid) throw new InvalidArgumentException('Sign in first.');
        require_once __DIR__ . '/Gallery.php'; require_once __DIR__ . '/Storefront.php';
        $b = (int) ($ctx['b'] ?? 0);
        if (!Storefront::ready($pdo) || !$b || !Storefront::get($pdo, $b)) throw new InvalidArgumentException('Set up the storefront first.');
        if (!in_array($ctx['which'] ?? '', ['logo', 'cover'], true)) throw new InvalidArgumentException('A logo or a cover.');
        if (!Gallery::canManage($pdo, $uid, 'businesses', (string) $b)) throw new InvalidArgumentException('You can\'t add photos for that business (it needs Catalog & content).');
        Gallery::roomFor($pdo, 'businesses', (string) $b, (int) $f['size']);   // its plan's storage
        return ['kind' => 'businesses', 'ref' => (string) $b, 'types' => ['jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp']], 'max' => Gallery::PHOTO_MAX, 'sniff' => true, 'what' => 'a photo'];
    },
    'done' => function (PDO $pdo, int $uid, array $ctx, Upload $u): array {
        require_once __DIR__ . '/Gallery.php'; require_once __DIR__ . '/Storefront.php';
        $b = (int) $ctx['b'];
        $item = Gallery::addPhoto($pdo, $uid, 'businesses', (string) $b, $u->asFile());
        Storefront::setPhoto($pdo, $b, (string) $ctx['which'], (int) $item['id']);
        return ['item' => ['id' => (int) $item['id'], 'card' => $item['card']], 'message' => $u->name . ' is your ' . ($ctx['which'] === 'logo' ? 'logo' : 'cover') . ' now.'];
    },
    'pick' => function (PDO $pdo, int $uid, array $ctx, array $it): ?array {   // one of the business's Gallery photos, used where it is
        $b = (int) $ctx['b'];
        if ($it['owner_kind'] !== 'businesses' || (string) $it['owner_ref'] !== (string) $b) return null;
        Storefront::setPhoto($pdo, $b, (string) $ctx['which'], (int) $it['id']);
        return ['item' => ['id' => (int) $it['id'], 'card' => Gallery::url($it, 'card')], 'message' => 'That photo is your ' . ($ctx['which'] === 'logo' ? 'logo' : 'cover') . ' now.'];
    },
]);

// ---- the photos at the top of a listing or a profile (decisions/0058 §21) --------------------------------------------

Uploads::target('hero_photo', [
    'check' => function (PDO $pdo, int $uid, array $ctx, array $f): array {
        require_once __DIR__ . '/HeroMedia.php';
        $kind = ($ctx['kind'] ?? '') === 'listing' ? 'listing' : 'user'; $ref = (int) ($ctx['ref'] ?? 0);
        if (!HeroMedia::ready($pdo)) throw new InvalidArgumentException('Hero photos aren\'t switched on yet.');
        if (!$uid || !HeroMedia::canManage($pdo, $uid, $kind, $ref)) throw new InvalidArgumentException('You can\'t change these photos.');
        return ['kind' => $kind === 'listing' ? 'businesses' : 'members', 'ref' => $ref, 'types' => ['jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp']], 'max' => 15 * 1024 * 1024, 'sniff' => true, 'what' => 'a photo'];
    },
    'done' => function (PDO $pdo, int $uid, array $ctx, Upload $u): array {
        $id = HeroMedia::addPhoto($pdo, $uid, ($ctx['kind'] ?? '') === 'listing' ? 'listing' : 'user', (int) ($ctx['ref'] ?? 0), $u->asFile(), (string) ($ctx['caption'] ?? ''));
        return ['id' => $id, 'message' => 'Photo added.'];
    },
]);

// ---- journeys: their photos (the author's, or an editor's) ----------------------------------------------------------

Uploads::target('journey_photo', [
    // its staff thread and where its button opens (decisions/0176 §6, release 4): a place's photo, live at once (post first)
    'thread' => ['kind' => 'upload.place_photo', 'told' => 'the safety team (who see Reports), never the uploader'],
    'land' => '/journeys/view.php?id={journey}',
    'check' => function (PDO $pdo, int $uid, array $ctx, array $f): array {
        require_once __DIR__ . '/Journeys.php';
        $j = Journeys::get($pdo, (int) ($ctx['journey'] ?? 0));
        if (!$uid || !$j || !Journeys::canEdit($pdo, $j, $uid)) throw new InvalidArgumentException('That journey isn\'t yours.');
        if (count(Journeys::media($pdo, (int) $j['id'])) >= Journeys::MAX_PHOTOS) throw new InvalidArgumentException('A journey holds up to ' . Journeys::MAX_PHOTOS . ' photos.');
        return ['kind' => 'members', 'ref' => (int) ($j['author_id'] ?: $uid), 'types' => ['jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp']], 'max' => 15 * 1024 * 1024, 'sniff' => true, 'what' => 'a photo'];
    },
    'done' => function (PDO $pdo, int $uid, array $ctx, Upload $u): array {
        $id = (int) $ctx['journey'];
        $mid = Journeys::addPhoto($pdo, $uid, $id, $u->asFile());
        $pt = Journeys::point((string) ($ctx['pin'] ?? ''));
        Journeys::updatePhoto($pdo, $uid, $mid, '', '', $pt[0] ?? null, $pt[1] ?? null, ['taken' => (string) ($ctx['taken'] ?? ''), 'source' => (string) ($ctx['source'] ?? 'own'), 'credit' => (string) ($ctx['credit'] ?? '')]);
        // the answer says where it stands (0176 §6.1): live now on a published journey, which tells staff; else it goes live on Publish
        $live = (Journeys::get($pdo, $id)['status'] ?? '') === 'published';
        if ($live) { require_once __DIR__ . '/UploadThreads.php'; UploadThreads::placePhoto($pdo, $id, $uid); }
        return ['id' => $mid, 'message' => $u->name . ' added: ' . ($live ? 'it\'s live now.' : 'it goes live when you publish.') . (Journeys::$pinNote ? ' ' . Journeys::$pinNote : '')];
    },
]);

// ---- a member's profile and cover photos ----------------------------------------------------------------------------

Uploads::target('profile_photo', [
    'check' => function (PDO $pdo, int $uid, array $ctx, array $f): array {
        require_once __DIR__ . '/MemberProfile.php';
        if (!$uid) throw new InvalidArgumentException('Sign in first.');
        if (!MemberProfile::ready($pdo)) throw new InvalidArgumentException('Profiles aren\'t switched on yet.');
        if (!in_array($ctx['kind'] ?? '', ['avatar', 'cover'], true)) throw new InvalidArgumentException('Which photo?');
        return ['kind' => 'members', 'ref' => $uid, 'types' => ['jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp']], 'max' => 15 * 1024 * 1024, 'sniff' => true, 'what' => 'a photo'];
    },
    // the framing (a profile picture's crop, a cover's position) and who sees it come with it, saved in the same step
    'done' => function (PDO $pdo, int $uid, array $ctx, Upload $u): array {
        $kind = (string) $ctx['kind'];
        $crop = isset($ctx['z']) ? ['x' => $ctx['x'] ?? 50, 'y' => $ctx['y'] ?? 50, 'z' => $ctx['z']] : [];
        $p = MemberProfile::photo($pdo, $uid, $kind, $u->asFile(), (int) ($ctx['pos'] ?? 50), $crop);
        $more = [];
        if (isset($ctx['cover_y']) && $kind === 'cover') $more['cover_y'] = (int) $ctx['cover_y'];
        if (isset($ctx['public'])) { $pub = array_values(array_diff($p['published'], [$kind])); if ((string) $ctx['public'] === '1') $pub[] = $kind; $more['published'] = $pub; }
        if (isset($ctx['all_public'])) $more['photos_public'] = (string) $ctx['all_public'] === '1';
        if ($more) MemberProfile::save($pdo, $uid, $more);
        return ['message' => $kind === 'avatar' ? 'Profile picture saved.' : 'Cover photo saved.'];
    },
]);

// ---- claims (decisions/0070): a document that shows the business is yours, or a dispute's proof ------------------------

Uploads::target('claim_doc', [
    'check' => function (PDO $pdo, int $uid, array $ctx, array $f): array {
        require_once __DIR__ . '/Claims.php';
        if (!$uid) throw new InvalidArgumentException('Sign in first.');
        Claims::canDocument($pdo, $uid, (int) ($ctx['claim'] ?? 0));
        return ['kind' => 'members', 'ref' => $uid, 'types' => ['pdf' => ['application/pdf'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png']], 'max' => Claims::DOC_MAX, 'sniff' => true, 'what' => 'a document'];
    },
    'done' => function (PDO $pdo, int $uid, array $ctx, Upload $u): array {
        Claims::document($pdo, $uid, (int) $ctx['claim'], $u->asFile());
        return ['message' => 'Received. An admin will look at it.'];
    },
]);

// A business card's photos (decisions/0108): the front makes the card (read at once, or once its back comes: ctx.more),
// then the back joins it (ctx.card). Made small on the phone; kept as a 1600 px JPEG with no metadata.
Uploads::target('card_scan', [
    'check' => function (PDO $pdo, int $uid, array $ctx, array $f): array {
        require_once __DIR__ . '/CardScan.php';
        if (!CardScan::mayScan($pdo, Auth::currentUser())) throw new InvalidArgumentException('Staff only.');
        CardScan::canSend($pdo, $uid, (int) ($ctx['card'] ?? 0));
        return ['kind' => 'members', 'ref' => $uid, 'types' => ['jpg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp']], 'max' => CardScan::MAX_BYTES, 'sniff' => true, 'what' => 'the card'];
    },
    'done' => function (PDO $pdo, int $uid, array $ctx, Upload $u): array {
        require_once __DIR__ . '/CardScan.php';
        if ($card = (int) ($ctx['card'] ?? 0)) CardScan::addBack($pdo, $uid, $card, $u->local(), $u->mime());
        else {
            $num = fn (string $k) => isset($ctx[$k]) && is_numeric($ctx[$k]) ? (float) $ctx[$k] : null;
            $card = CardScan::create($pdo, $uid, $u->local(), $u->mime(), $num('lat'), $num('lon'), (string) ($ctx['at'] ?? ''), !empty($ctx['more']), (string) ($ctx['tz'] ?? ''));   // tz: where it was scanned, so its time shows there
        }
        $u->remove();
        return ['card' => CardScan::get($pdo, $card)];
    },
]);

// A GO Code's logo (decisions/0096): for a member or a guest (their browser's key). It's made into a small square PNG
// in their folder and waits there; the code it's for takes it (api/go-codes.php action=create, logo: its id).
Uploads::target('go_logo', [
    'guest' => true,   // no account needed, but the page's form token is, like every GO Codes request
    'check' => function (PDO $pdo, int $uid, array $ctx, array $f): array {
        require_once __DIR__ . '/QrIssue.php';
        if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) throw new InvalidArgumentException('This page had expired. Reload it and try again.');
        $o = QrIssue::owner(Auth::currentUser(), true);
        if (!$o['uid'] && $o['guest'] === '') throw new InvalidArgumentException('Your browser didn\'t keep our cookie, so the logo can\'t be kept. Allow cookies for this site and try again.');
        return ['kind' => $o['uid'] ? 'members' : 'guests', 'ref' => $o['uid'] ?: $o['guest'], 'types' => ['png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'webp' => ['image/webp']], 'max' => 2 * 1024 * 1024, 'sniff' => true, 'what' => 'a logo'];
    },
    'done' => function (PDO $pdo, int $uid, array $ctx, Upload $u): array {
        require_once __DIR__ . '/QrIssue.php';
        return ['logo' => QrIssue::logoWaiting(QrIssue::owner(Auth::currentUser()), $u)];
    },
]);

// The Census population file, saved by hand when the server can't reach the Census (Admin → Cluster tools).
Uploads::target('census_file', [
    'check' => function (PDO $pdo, int $uid, array $ctx, array $f): array {
        require_once __DIR__ . '/crawler/IntakeStager.php'; require_once __DIR__ . '/ZipPopulation.php';
        if (!IntakeStager::canUseIntake($pdo, Auth::currentUser() ?? [])) throw new InvalidArgumentException('Admins only.');
        if (!ZipPopulation::hasColumn($pdo)) throw new RuntimeException('Run migrations/2026-09-28_zip_population.sql in phpMyAdmin first.');
        return ['kind' => 'members', 'ref' => $uid, 'types' => ['json' => ['application/json', 'text/plain'], 'txt' => ['text/plain', 'application/json']], 'max' => 50 * 1024 * 1024, 'sniff' => true, 'what' => 'the saved Census .json file'];
    },
    'done' => function (PDO $pdo, int $uid, array $ctx, Upload $u): array {
        require_once __DIR__ . '/ZipPopulation.php';
        try { $population = ZipPopulation::parse((string) file_get_contents($u->local())); } finally { $u->remove(); }
        $p = ZipPopulation::preview($pdo, $population);
        ZipPopulation::apply($pdo, $population, true);
        return ['message' => 'Population loaded: ' . number_format(count($population)) . ' Census ZIP areas; ' . number_format($p['matched']) . ' of your '
            . number_format($p['zips']) . ' US ZIPs matched, ' . number_format($p['missing']) . ' set to 0 (PO box / no residents).'];
    },
]);

// A creative item's print-ready file on a client order (no print in its project; decisions/0101, addendum 2026-10-27):
// added after the client approves the artwork, released to them once the order is paid in full.
Uploads::target('order_final', [
    'check' => function (PDO $pdo, int $uid, array $ctx, array $f) use ($tvOrderStaff): array {
        require_once __DIR__ . '/ClientOrders.php';
        $tvOrderStaff();
        $oid = (int) ($ctx['order'] ?? 0);
        ClientOrders::canAddFiles($pdo, $oid, false, (int) ($ctx['line'] ?? 0));
        return ['kind' => 'orders', 'ref' => $oid, 'types' => ClientOrders::extTypes(ClientOrders::ART_TYPES), 'sniff' => true, 'what' => 'a print-ready file'];
    },
    'done' => function (PDO $pdo, int $uid, array $ctx, Upload $u): array {
        return ClientOrders::finalFromUpload($pdo, (int) $ctx['order'], (int) $ctx['line'], $u, $uid);
    },
]);

// ---- a post's photos and videos (decisions/0153): into the member's Temporary bin until posted, kept or ended --------------

Uploads::target('moment', [
    'check' => function (PDO $pdo, int $uid, array $ctx, array $f): array {
        if (!$uid) throw new InvalidArgumentException('Sign in first.');
        require_once __DIR__ . '/MemberPosts.php';
        if (!MemberPosts::ready($pdo)) throw new InvalidArgumentException('Posts aren\'t switched on yet.');
        $b = (int) ($ctx['b'] ?? 0);   // posting as a business (its team): its own folder, no posting caps (decisions/0141 §2.4)
        if ($b && !MemberPosts::canPostAs($pdo, $uid, $b)) throw new InvalidArgumentException('You can\'t post for that business (it needs Catalog & content on its team).');
        if (!$b) MemberPosts::checkRoom($pdo, $uid, isset(MemberPosts::VIDEO[$f['ext']]));
        return ['kind' => $b ? 'businesses' : 'members', 'ref' => (string) ($b ?: $uid), 'types' => MemberPosts::PHOTO + MemberPosts::VIDEO, 'max' => MemberPosts::fileMax($pdo, $uid, $b), 'sniff' => true, 'what' => 'a photo or video for a post'];
    },
    'done' => function (PDO $pdo, int $uid, array $ctx, Upload $u, array $extra = []): array {
        require_once __DIR__ . '/MemberPosts.php';
        $m = MemberPosts::mediaFromUpload($pdo, $uid, $u, $extra, (int) ($ctx['b'] ?? 0));
        return ['media' => $m, 'message' => 'Your ' . (($m['kind'] ?? '') === 'video' ? 'video' : 'photo') . ' is ready to post.'];
    },
]);

// ---- a release's zip (decisions/0145 Phase 5): System Configuration → Releases. Files at their real paths; nothing goes live
// until the release is published.

Uploads::target('release_zip', [
    'check' => function (PDO $pdo, int $uid, array $ctx, array $f): array {
        require_once __DIR__ . '/Releases.php';
        [$ok, $why] = Releases::can('releases.publish');
        if (!$ok) throw new InvalidArgumentException($why);
        return ['kind' => 'system', 'ref' => 'releases', 'types' => ['zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream']], 'max' => 50 * 1024 * 1024, 'sniff' => false, 'what' => 'a release\'s zip'];
    },
    'done' => function (PDO $pdo, int $uid, array $ctx, Upload $u): array {
        require_once __DIR__ . '/Releases.php';
        $tz = preg_match('#^[A-Za-z_]+(/[A-Za-z0-9_+-]+)*$#', (string) ($ctx['tz'] ?? '')) ? (string) $ctx['tz'] : '';
        try { $r = Releases::fromUpload($pdo, $uid, $u->local(), $u->name, $tz); }
        catch (RuntimeException $e) { throw new InvalidArgumentException($e->getMessage()); }
        // straight onto staging, its checks run there (decisions/0159 §5: no separate Approve the plan or Put it on staging)
        return ['id' => $r['id'], 'message' => Releases::straightOn($pdo, (int) $r['id'], $uid)];
    },
]);

// The site manager's Upload, on staging (decisions/0159 §5): the zip is kept in staging's own inbox (app/storage, never
// served or synced), and the bar then asks the live site, which reads it from there (the same hosting account), to make
// it a release and put it straight onto staging. Only for whoever came in from the live admin with releases.publish (the
// bar's signed cookie says so); nowhere but staging.
Uploads::target('release_zip_bar', [
    'check' => function (PDO $pdo, int $uid, array $ctx, array $f): array {
        require_once __DIR__ . '/Staging.php';
        require_once __DIR__ . '/Releases.php';
        if (!Staging::here()) throw new InvalidArgumentException('Releases are uploaded on the live site (System Configuration → Releases), or from the site manager on staging.');
        Database::readEnv();
        $bar = Releases::verify((string) ($_COOKIE['tv_stg_bar'] ?? ''), (string) (getenv('RELEASE_BAR_KEY') ?: ''));
        if (!$bar) throw new InvalidArgumentException('The site manager has run out (it lasts 12 hours): open staging again from the live admin.');
        if (!in_array('releases.publish', (array) ($bar['perms'] ?? []), true)) throw new InvalidArgumentException('Your role can\'t upload releases (System Configuration → Permissions).');
        return ['kind' => 'system', 'ref' => 'releases', 'types' => ['zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream']], 'max' => 50 * 1024 * 1024, 'sniff' => false, 'what' => 'a release\'s zip'];
    },
    'done' => function (PDO $pdo, int $uid, array $ctx, Upload $u): array {
        $dir = dirname(__DIR__) . '/storage/release-inbox';
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) throw new InvalidArgumentException('Couldn\'t make staging\'s inbox for releases.');
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
        foreach (glob($dir . '/*.zip') ?: [] as $old) if (filemtime($old) < time() - 86400) @unlink($old);   // a day is long enough to be picked up
        $name = bin2hex(random_bytes(16)) . '.zip';
        if (!@copy($u->local(), $dir . '/' . $name)) throw new InvalidArgumentException('Couldn\'t keep the zip on staging. Try again.');
        return ['inbox' => $name, 'name' => (string) $u->name, 'message' => 'Uploaded: making it a release…'];
    },
]);

// A picture in a block (decisions/0159 step 3, the editor's core): the site's own file, kept with our pages (Store's
// system/<folder>/public/pages/), never in a staff member's Gallery (Jason, 2026-11-05). Staff with pages.edit.
// On a storefront's page (zone "shop.<page id>", decisions/0159 step 5a): the business's own photo, in its Gallery and its
// storage, by its team (Storefront & theme to edit, and Catalog & content for photos, the Gallery's own rule).
Uploads::target('block_image', [
    'check' => function (PDO $pdo, int $uid, array $ctx, array $f): array {
        require_once __DIR__ . '/Blocks.php';
        $zone = (string) ($ctx['zone'] ?? '');
        if (Blocks::isShop($zone)) {
            [$ok, $why] = Blocks::can('pages.edit', $zone);
            if (!$ok) throw new InvalidArgumentException($why);
            require_once __DIR__ . '/Gallery.php';
            $b = Blocks::shopOwner($pdo, $zone);
            if (!Gallery::canManage($pdo, $uid, 'businesses', (string) $b)) throw new InvalidArgumentException('You can\'t add photos for this business (it needs Catalog & content).');
            Gallery::roomFor($pdo, 'businesses', (string) $b, (int) $f['size']);   // its plan's storage
            return ['kind' => 'businesses', 'ref' => (string) $b, 'types' => ['jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp']], 'max' => Gallery::PHOTO_MAX, 'sniff' => true, 'what' => 'a photo'];
        }
        [$ok, $why] = Blocks::can('pages.edit');
        if (!$ok) throw new InvalidArgumentException($why);
        return ['kind' => 'system', 'ref' => 'pages', 'types' => ['jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp']], 'max' => 10 * 1024 * 1024, 'sniff' => true, 'what' => 'a picture'];
    },
    'done' => function (PDO $pdo, int $uid, array $ctx, Upload $u): array {
        $zone = (string) ($ctx['zone'] ?? '');
        if (Blocks::isShop($zone)) {   // into the business's Gallery; the storefront's album takes it once a draft or version shows it
            require_once __DIR__ . '/Gallery.php';
            $item = Gallery::addPhoto($pdo, $uid, 'businesses', (string) Blocks::shopOwner($pdo, $zone), $u->asFile());
            return ['url' => '/gallery/m.php?i=' . (int) $item['id'] . '&s=full', 'item' => ['id' => (int) $item['id']], 'message' => $u->name . ' is uploaded, and kept in your Gallery.'];
        }
        require_once __DIR__ . '/Store.php';
        $path = Store::path('system', 'pages', 'blocks', bin2hex(random_bytes(8)) . '.' . $u->ext, true);
        $u->moveTo($path);
        return ['url' => Store::address($path), 'message' => $u->name . ' is uploaded.'];
    },
]);
