<?php
// ANCHOR: replaces     public static function setStage(PDO $pdo, int $oid, string $stage, string $tracking, ?int $staff, string $detail = '', bool $mail = true, bool $checkPaid = true): void   // $detail: for our history only (the vendor's name), never the client's through the first line "    }" after it (the line before it is:             . (in_array($stage, ['shipped', 'ready'], true) && self::hasReceived($pdo) ? "\n\n" . self::GOT_IT_ASK . "\n" . self::url($o) . '#got-it' : "\n\nSee your order any time:\n" . self::url($o)));)
    public static function setStage(PDO $pdo, int $oid, string $stage, string $tracking, ?int $staff, string $detail = '', bool $mail = true, bool $checkPaid = true): void   // $detail: for our history only (the vendor's name), never the client's
    {
        $o = Orders::get($pdo, $oid);
        if (!$o || !self::isClient($o)) throw new RuntimeException('That order isn\'t here.');
        if (!isset(self::STAGES[$stage])) throw new RuntimeException('Choose a stage.');
        if (in_array($o['status'], ['draft', 'sent', 'void'], true)) throw new RuntimeException('The client hasn\'t approved this order yet.');
        if ($checkPaid && in_array($stage, ['shipped', 'ready'], true) && ($why = self::shipHold($pdo, $o)) !== '') throw new RuntimeException($why);
        if ($stage === 'ordered' && ($why = self::vendorHold($pdo, $o)) !== '') throw new RuntimeException($why);   // sent to a printer is released to the vendor (model 1.9)
        $tracking = mb_substr(trim($tracking), 0, 160);
        $pdo->prepare('UPDATE orders SET stage = ?, tracking = ? WHERE id = ?')->execute([$stage, $tracking, $oid]);
        if (in_array($stage, ['shipped', 'ready', 'complete'], true)) $pdo->prepare("UPDATE order_lines SET status = 'done' WHERE order_id = ? AND status = 'open'")->execute([$oid]);
        Orders::event($pdo, $oid, 'stage', self::STAGES[$stage] . ($detail !== '' ? ' ' . $detail : '') . ($tracking !== '' ? ' · ' . $tracking : ''), $staff);
        $say = ['production' => 'is in production', 'ordered' => 'has been sent to print', 'shipped' => 'has shipped', 'ready' => 'is ready for pickup', 'complete' => 'is complete'][$stage];
        if ($mail && self::wantsProgress($o)) self::tell($pdo, $o, 'order.client', 'stage-' . $stage, 'Your Traversence order ' . $say, 'Hello ' . $o['bill_name'] . ",\n\nYour order #" . Orders::no($o) . ' ' . $say . '.' . ($tracking !== '' ? "\n" . ($stage === 'shipped' ? 'Tracking: ' : '') . $tracking : '')
            . (in_array($stage, ['shipped', 'ready'], true) && self::hasReceived($pdo) ? "\n\n" . self::GOT_IT_ASK . "\n" . self::url($o) . '#got-it' : "\n\nSee your order any time:\n" . self::url($o)));
    }
