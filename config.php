<?php
define('DB_HOST', '');
define('DB_NAME', '');
define('DB_USER', '');
define('DB_PASS', '');
define('APP_NAME', 'BeeScale');
define('BASE_URL', '');
define('SESSION_NAME', 'beescale_sess');
ini_set('session.cookie_httponly', 1);
define('MAIL_FROM', '');
define('MAIL_FROM_NAME', 'BeeScale');
ini_set('session.use_strict_mode', 1);
session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
?>