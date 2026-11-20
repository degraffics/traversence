<?php
// /guide/personal.php?id=: a Personal Guide (decisions/0174 §1, release 4; 0058 §1): a member's trip they published,
// public and credited. The trip page's renderer (user/trips/trip.php) in its guide mode.
declare(strict_types=1);
$_GET['g'] = (string) (int) ($_GET['id'] ?? 0);
require __DIR__ . '/../user/trips/trip.php';
