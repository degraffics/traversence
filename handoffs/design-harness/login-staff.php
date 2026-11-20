<?php
// harness only: sign in as staff user 1 (never shipped)
require_once __DIR__ . '/../../app/bootstrap.php';
$_SESSION['user_id'] = 1; $_SESSION['logged_in_at'] = time(); $_SESSION['session_version'] = 0; $_SESSION['tv_2s_ok'] = 1;
header('Content-Type: text/plain'); echo session_id();
