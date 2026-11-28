    // ANCHOR: replaces     public static function createBill(PDO $pdo, array $b, array $o, array $acct): array through     }
    // (that last line is the first '    }' after the first line: the one that closes createBill. createBill's docblock above
    // it stays as live. Paste from the next line down; these two comment lines are not part of the code.)
    public static function createBill(PDO $pdo, array $b, array $o, array $acct): array
    {
        self::guardReal($pdo, $o);   // never real money for a test
        require_once __DIR__ . '/StagingGate.php';
        if (($why = StagingGate::hold(self::api() . '/v3/company/bill', 'a vendor bill for order #' . (int) $o['id'], 'order:' . (int) $o['id'])) !== null) throw new RuntimeException('Held on staging: ' . $why);
        if (self::env() === 'mock') return ['id' => 'MBL-' . (int) $b['id'], 'account' => (string) ($acct[1] ?? ''), 'from' => 'setting'];
        $name = mb_substr(trim(str_replace([':', '"'], ['-', ''], (string) $b['vendor'])), 0, 100);
        if ($name === '') throw new RuntimeException('The bill has no vendor named.');
        $f = self::query($pdo, "select Id from Vendor where DisplayName = '" . self::q($name) . "'");
        $vid = (string) ($f['Vendor'][0]['Id'] ?? '');
        if ($vid === '') $vid = (string) (self::call($pdo, 'POST', 'vendor', ['DisplayName' => $name], [], 'vendor-' . substr(hash('sha256', $name), 0, 20))['Vendor']['Id'] ?? '');
        if ($vid === '') throw new RuntimeException('QuickBooks didn\'t add the vendor ' . $name . '.');
        // Where it posts: the vendor's default category in QuickBooks when it has a usable one (read only; off until the
        // field is confirmed in the sandbox, VENDOR_CATEGORY_FIELD), else the setting "Vendor bills post to".
        $acct = self::billAccount(self::vendorCategory($pdo, $vid), $acct);
        $from = (string) ($acct[2] ?? 'setting');
        $an = (string) ($acct[1] ?? ''); $acct = (string) ($acct[0] ?? '');
        if ($acct === '') throw new RuntimeException('Choose where vendor bills post (System Configuration → Settings, Vendor bills post to): QuickBooks has no Cost of Goods Sold account yet.');
        $amt = ((int) $b['total_cents']) / 100;
        $key = 'vbill-' . (int) $b['id'];
        $body = ['VendorRef' => ['value' => $vid], 'TotalAmt' => $amt,
            'Line' => [['Amount' => $amt, 'DetailType' => 'AccountBasedExpenseLineDetail', 'Description' => mb_substr('Order #' . Orders::no($o) . ($b['po'] !== '' ? ', work order ' . $b['po'] : ''), 0, 4000),
                'AccountBasedExpenseLineDetail' => ['AccountRef' => ['value' => $acct]]]],
            'PrivateNote' => mb_substr('Traversence order #' . Orders::no($o) . ', the vendor\'s invoice ' . $b['bill_no'] . ' ' . self::mark($key), 0, 4000)];
        if ((string) $b['bill_no'] !== '') $body['DocNumber'] = mb_substr((string) $b['bill_no'], 0, 21);
        if (!empty($b['bill_date'])) $body['TxnDate'] = (string) $b['bill_date'];
        if (!empty($b['due_date'])) $body['DueDate'] = (string) $b['due_date'];
        $j = self::call($pdo, 'POST', 'bill', $body, [], $key);
        $id = (string) ($j['Bill']['Id'] ?? '');
        if ($id === '') throw new RuntimeException('QuickBooks didn\'t say which bill it made.');
        return ['id' => $id, 'account' => $an !== '' ? $an : 'account ' . $acct, 'from' => $from];   // from: 'vendor' (its default category) or 'setting'
    }

    // The Vendor field that holds its default category (default expense account) in QuickBooks' API. '' = not confirmed in
    // the sandbox yet, so bills post to the setting "Vendor bills post to" as before (handoffs/decided-not-live.md, Later:
    // "once confirmed in the sandbox"). Set it only after the sandbox shows the field and its shape. Never APAccountRef:
    // that is the vendor's payables account, not its category.
    public const VENDOR_CATEGORY_FIELD = '';
    // The minorversion that field needs, if newer than MINOR; '' = MINOR.
    public const VENDOR_CATEGORY_MINOR = '';

    /**
     * A vendor's default category in QuickBooks (its default expense account), read only: [id, label], or [] when it has
     * none, the account isn't usable (categoryUsable()), the field isn't confirmed yet (VENDOR_CATEGORY_FIELD ''), or
     * QuickBooks can't be read. Never blocks a bill: createBill() then posts to the setting "Vendor bills post to". Writes
     * nothing in QuickBooks. The local test (mock): vendor 101 has one (81, 5010 Printing and production), any other none.
     */
    public static function vendorCategory(PDO $pdo, string $vid): array
    {
        $vid = trim($vid);
        if ($vid === '') return [];
        if (self::env() === 'mock') return $vid === '101' ? ['81', '5010 Printing and production'] : [];
        if (self::VENDOR_CATEGORY_FIELD === '') return [];   // not confirmed in the sandbox: off
        try {
            $q = self::VENDOR_CATEGORY_MINOR !== '' ? ['minorversion' => self::VENDOR_CATEGORY_MINOR] : [];
            $v = (array) (self::call($pdo, 'GET', 'vendor/' . rawurlencode($vid), null, $q)['Vendor'] ?? []);
            $ref = $v[self::VENDOR_CATEGORY_FIELD] ?? null;
            $id = is_array($ref) ? trim((string) ($ref['value'] ?? '')) : '';
            if ($id === '' || !ctype_digit($id)) return [];
            $a = self::fetch($pdo, 'Account', $id);
            if (!self::categoryUsable($a)) return [];
            $label = trim(trim((string) ($a['AcctNum'] ?? '')) . ' ' . trim((string) ($a['Name'] ?? ($ref['name'] ?? ''))));
            return [$id, $label];
        } catch (Throwable $e) { error_log('qb vendor category: ' . $e->getMessage()); return []; }
    }

    /**
     * Can a vendor bill's one line post to this QuickBooks account: active, and a cost or expense account (Cost of Goods
     * Sold, Expense, Other Expense)? Anything else (a bank, payables or income account, or no type given): no, and the
     * bill posts to the setting.
     */
    public static function categoryUsable(array $a): bool
    {
        if (!$a) return false;
        if (($a['Active'] ?? true) === false) return false;
        return in_array((string) ($a['AccountType'] ?? ''), ['Cost of Goods Sold', 'Expense', 'Other Expense'], true);
    }

    /**
     * Where a vendor bill posts: the vendor's default category when it has one, else the setting (VendorBills::account()).
     * $vendor: vendorCategory()'s [id, label] or []; $setting: [id, label]. Returns [id, label, 'vendor'|'setting'].
     */
    public static function billAccount(array $vendor, array $setting): array
    {
        $vid = trim((string) ($vendor[0] ?? ''));
        if ($vid !== '') return [$vid, (string) ($vendor[1] ?? ''), 'vendor'];
        return [trim((string) ($setting[0] ?? '')), (string) ($setting[1] ?? ''), 'setting'];
    }
