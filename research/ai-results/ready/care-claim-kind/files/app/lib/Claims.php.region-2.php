// ANCHOR: replaces     public static function activate(PDO $pdo, int $uid, int $claimId, string $plan, bool $agreed = false): void
// through     }
// (that is, the closing brace of activate(): the line straight after
//         if ($plan !== 'free') { try { require_once __DIR__ . '/Roles.php'; Roles::log($pdo, $uid, null, 'billing', 'trial terms agreed', 'listing ' . $e . ': ' . $plan . ', 30 days, ' . ($_SERVER['REMOTE_ADDR'] ?? '')); } catch (Throwable) {} })
// The docblock above activate() is unchanged.
// Changes against draft 17b: the care guard now runs before the Terms check. A paid choice on a health care listing
// therefore gets the true reason (plans are coming), never "agree to the Terms". $e moves up with it.
    public static function activate(PDO $pdo, int $uid, int $claimId, string $plan, bool $agreed = false): void
    {
        require_once __DIR__ . '/Billing.php';
        $c = self::ownClaim($pdo, $uid, $claimId);
        if (!isset(self::PLANS[$plan])) throw new InvalidArgumentException('Pick a plan.');
        $e = (int) $c['entity_id'];
        // health care stays on Free until a decision on its own plan (decisions/0070 note, 2026-11-27/28): checked first,
        // so a paid choice never reaches the trial's Terms or Billing
        if ($plan !== 'free' && self::kind($pdo, $e) === 'care') throw new InvalidArgumentException('Health care listings are on Free for now: plans for them are coming.');
        // a paid plan's trial starts only after its checkout: the plan, the dates, what happens at the end, and the
        // Terms agreed (Jason, 2026-10-24: "no terms or confirmation upon clicking activate")
        if ($plan !== 'free' && !$agreed) throw new InvalidArgumentException('Review the trial and agree to the Terms first.');
        $cur = Billing::current($pdo, 'listing', $e);
        if ($cur && $cur['status'] !== 'trial') throw new InvalidArgumentException('This listing has a paid plan: change it on its Plans page.');
        Database::transaction(function (PDO $pdo) use ($c, $plan, $e, $cur) {
            if ($cur) Billing::endNow($pdo, (int) $cur['id'], 'another plan chosen');
            if ($plan !== 'free') Billing::trial($pdo, 0, 'listing', $e, $plan, 30);
            else Billing::syncTier($pdo, 'listing', $e);
            $pdo->prepare('UPDATE listing_claims SET plan = ?, activated_at = NOW() WHERE id = ?')->execute([$plan, $c['id']]);
        });
        if ($plan !== 'free') { try { require_once __DIR__ . '/Roles.php'; Roles::log($pdo, $uid, null, 'billing', 'trial terms agreed', 'listing ' . $e . ': ' . $plan . ', 30 days, ' . ($_SERVER['REMOTE_ADDR'] ?? '')); } catch (Throwable) {} }
    }
