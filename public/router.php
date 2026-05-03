<?php

if (PHP_SAPI !== 'cli-server') {
    header('HTTP/1.1 403 Forbidden');
    exit('This script is only intended for the PHP built-in server.');
}

// When running with -t public, __DIR__ is the public directory
$publicDir = __DIR__;
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Prevent directory traversal
if (strpos($requestUri, '..') !== false) {
    return false;
}

$filePath = $publicDir . $requestUri;

// If it's a file, let the server handle it from the document root
if (is_file($filePath)) {
    return false;
}

// Fallback to index.php for Shopware routing
$_SERVER['SCRIPT_FILENAME'] = $publicDir . '/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

require_once $_SERVER['SCRIPT_FILENAME'];
