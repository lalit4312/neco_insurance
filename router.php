<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$staticFile = $path === '/client/src/styles.css'
    || preg_match('#^/uploads/[a-f0-9]{40}\.(?:jpg|png|webp|gif)$#', $path) === 1;
if ($staticFile) {
    $file = realpath(__DIR__ . $path);
    if ($file !== false && str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR) && is_file($file)) {
        return false;
    }
}
require __DIR__ . '/index.php';
