<?php
/**
 * logout.php — Đăng xuất
 */
require_once __DIR__ . '/_path.php';

$auth->logout();
redirect('/index.php');
