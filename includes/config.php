<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'Rintis_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application configuration
define('SITE_NAME', 'Rintis Sejahtera');
define('APP_URL', 'http://localhost/php/primatable'); // Adjust this to your actual URL

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);
session_start();