<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/env.php';
load_environment(dirname(__DIR__));
require dirname(__DIR__) . '/config/database.php';
require dirname(__DIR__) . '/app/common.php';
require dirname(__DIR__) . '/app/data.php';

$name = trim((string)($argv[1] ?? getenv('ADMIN_NAME') ?: ''));
$email = strtolower(trim((string)($argv[2] ?? getenv('ADMIN_EMAIL') ?: '')));
$password = (string)(getenv('ADMIN_PASSWORD') ?: '');

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "Usage: set ADMIN_PASSWORD (12+ characters), then run php bin/create-admin.php \"Full name\" admin@example.com\n");
    exit(1);
}

$pdo = database();
if (one($pdo, 'SELECT id FROM users WHERE email = ?', [$email])) {
    fwrite(STDERR, "An account with that email already exists.\n");
    exit(1);
}
$statement = $pdo->prepare(
    "INSERT INTO users (id, name, email, password_hash, role, active, created_at, updated_at)
     VALUES (?, ?, ?, ?, 'Super Admin', 1, NOW(), NOW())"
);
$statement->execute([uuid(), $name, $email, password_hash($password, PASSWORD_DEFAULT)]);
fwrite(STDOUT, "Super Admin created for {$email}.\n");
