<?php
declare(strict_types=1);

require __DIR__ . '/app/env.php';
load_environment(__DIR__);

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('neco_futsal_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; img-src 'self' https:; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; form-action 'self'; base-uri 'self'; frame-ancestors 'none'");

require __DIR__ . '/config/database.php';
require __DIR__ . '/app/common.php';
require __DIR__ . '/app/data.php';
require __DIR__ . '/app/actions.php';
require __DIR__ . '/app/excel.php';
require __DIR__ . '/app/public_views.php';
require __DIR__ . '/app/admin_views.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';
$validPublicPages = ['/', '/fixtures', '/results', '/standings', '/teams', '/players', '/stats', '/bracket', '/sponsors'];
$isAdmin = $path === '/admin';
$actionError = '';

if ($path === '/api/health') {
    try {
        database()->query('SELECT 1');
        json_response(['status' => 'ok', 'database' => 'connected']);
    } catch (Throwable $error) {
        error_log('Health check database connection failed: ' . $error->getMessage());
        json_response(['status' => 'unavailable', 'database' => 'disconnected'], 503);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        handle_post(database());
    } catch (Throwable $error) {
        if (!$error instanceof RuntimeException) {
            error_log('Application request failed: ' . $error->getMessage());
            if ($error instanceof PDOException && (string)$error->getCode() === '23000') {
                $actionError = 'This record conflicts with related data or a value that must be unique. Check the linked records and try again.';
            } else {
                $actionError = 'The request could not be completed. Check the submitted information and try again.';
            }
        } else {
            $actionError = $error->getMessage();
        }
        if ($isAdmin && current_user()) {
            if (($_POST['action'] ?? '') === 'bulk-import') {
                $_GET['section'] = 'bulk-import';
            } elseif (in_array($_POST['resource'] ?? '', array_keys(admin_resources()), true)) {
                $_GET['section'] = (string)$_POST['resource'];
                if (!empty($_POST['id'])) {
                    $_GET['edit'] = (string)$_POST['id'];
                }
            }
        }
        if ($isAdmin && !current_user()) {
            http_response_code(401);
            render_document('Admin sign in', static fn() => render_login(take_flash(), $actionError), false, []);
            exit;
        }
    }
}

if ($isAdmin) {
    try {
        $pdo = database();
        $user = current_user();
        if ($user) {
            $user = one($pdo, 'SELECT id, name, email, role FROM users WHERE id = ? AND active = 1', [$user['id']]);
            if ($user) {
                $_SESSION['user'] = $user;
            } else {
                unset($_SESSION['user']);
            }
        }
        if (($_GET['download'] ?? '') === 'bulk-template') {
            if (!$user) {
                http_response_code(401);
                exit('Please sign in to download the Excel template.');
            }
            require_role($user, ['Super Admin', 'Admin']);
            $workbook = excel_template_bytes();
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="neco-futsal-bulk-import-template.xlsx"');
            header('Content-Length: ' . strlen($workbook));
            header('Cache-Control: private, no-store');
            echo $workbook;
            exit;
        }
        if (!$user) {
            if (isset($_SESSION['flash'])) {
                $notice = take_flash();
            } else {
                $notice = null;
            }
            render_document('Admin sign in', static fn() => render_login($notice, $actionError), false, []);
            exit;
        }
        render_document(
            'Admin · Neco Futsal Cup',
            static fn() => render_admin($pdo, $user, take_flash(), $actionError),
            false,
            []
        );
    } catch (Throwable $error) {
        error_log('Unable to render admin application: ' . $error->getMessage());
        http_response_code(503);
        render_document('Service unavailable', static function (): void {
            echo '<main class="admin-shell"><div class="error-state" role="alert">The database is currently unavailable. Please try again later.</div></main>';
        }, false, []);
    }
    exit;
}

if (!in_array($path, $validPublicPages, true)) {
    http_response_code(404);
}

try {
    $data = public_data(database());
    $title = $path === '/' ? ($data['tournament']['name'] ?? 'Neco Insurance Futsal Cup') : ucfirst(trim($path, '/')) . ' · Neco Futsal Cup';
    render_document($title, static function () use ($path, $data): void {
        public_header($data['tournament'], $path);
        render_public_page($path, $data);
        public_footer($data['sponsors']);
    }, true, $data);
} catch (Throwable $error) {
    error_log('Unable to render public application: ' . $error->getMessage());
    http_response_code(503);
    render_document('Service unavailable', static function (): void {
        echo '<main class="page-main"><div class="error-state" role="alert"><strong>We could not load tournament data.</strong><span>Check the database connection and try again.</span><a class="button button-light button-small" href="/">Try again</a></div></main>';
    }, true, ['tournament' => null, 'sponsors' => []]);
}

function render_document(string $title, callable $content, bool $public, array $data): void
{
    ?><!doctype html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <meta name="theme-color" content="#101c2c">
      <title><?= e($title) ?></title>
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
      <link rel="stylesheet" href="/client/src/styles.css">
    </head>
    <body>
      <?php $content(); ?>
    </body>
    </html><?php
}
