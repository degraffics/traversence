<?php
// harness only: sign in as member user 2 (never shipped)
require_once __DIR__ . '/../../app/bootstrap.php';
$_SESSION['user_id'] = 2; $_SESSION['logged_in_at'] = time(); $_SESSION['session_version'] = 0;
header('Content-Type: text/plain'); echo session_id();
