<?php

declare(strict_types=1);

$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$public = realpath(__DIR__.'/../../public');
$asset = $public ? realpath($public.$path) : false;

if ($path !== '/' && $asset && str_starts_with($asset, $public.DIRECTORY_SEPARATOR) && is_file($asset)) {
    return false;
}

require __DIR__.'/../../public/index.php';
