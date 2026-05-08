<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$documentRoot = __DIR__.'/public';

if (is_string($path) && $path !== '/' && file_exists($documentRoot.$path)) {
    return false;
}

require $documentRoot.'/index.php';
