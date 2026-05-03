<?php

if (PHP_SAPI !== 'cli-server') {
    header('HTTP/1.1 403 Forbidden');
    exit('This script is only intended for the PHP built-in server.');
}

$publicDir = __DIR__ . '/public';
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Prevent directory traversal
if (strpos($requestUri, '..') !== false) {
    return false;
}

$filePath = realpath($publicDir . $requestUri);

// Debug logging (check PHP server output)
error_log("Checking path: " . ($filePath ?: "NOT FOUND") . " for URI: " . $requestUri);

if ($filePath && is_file($filePath)) {
    return false;
}

// Fallback to index.php for everything else
// We must set SCRIPT_FILENAME and SCRIPT_NAME so Shopware detects the base path correctly
$_SERVER['SCRIPT_FILENAME'] = $publicDir . '/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

require_once $_SERVER['SCRIPT_FILENAME'];
