<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Your session expired. Go back and try again.');
    }
}

function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function take_flash(): ?array
{
    $value = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($value) ? $value : null;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_user(PDO $pdo): array
{
    $user = current_user();
    if (!$user) {
        flash('Please sign in to continue.', 'error');
        redirect('/admin');
    }
    $freshUser = one($pdo, 'SELECT id, name, email, role FROM users WHERE id = ? AND active = 1', [$user['id']]);
    if (!$freshUser) {
        unset($_SESSION['user']);
        flash('Your account is no longer active. Please contact an administrator.', 'error');
        redirect('/admin');
    }
    $_SESSION['user'] = $freshUser;
    return $freshUser;
}

function has_role(array $user, array $roles): bool
{
    return in_array($user['role'] ?? '', $roles, true);
}

function require_role(array $user, array $roles): void
{
    if (!has_role($user, $roles)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}

function uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
        . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}

function audit(PDO $pdo, ?array $actor, string $action, string $resource, ?string $resourceId = null): void
{
    $statement = $pdo->prepare(
        'INSERT INTO audit_logs (id, actor_id, action, resource, resource_id, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())'
    );
    $statement->execute([uuid(), $actor['id'] ?? null, $action, $resource, $resourceId]);
}

function date_label(?string $value, string $format = 'M j, Y'): string
{
    if (!$value) {
        return 'Date to be confirmed';
    }
    try {
        return (new DateTimeImmutable($value))->format($format);
    } catch (Throwable) {
        return 'Date to be confirmed';
    }
}

function team_name(?array $team): string
{
    return $team['name'] ?? 'TBD';
}

function team_badge(?array $team, string $size = ''): string
{
    $name = team_name($team);
    $logo = $team['logo_url'] ?? '';
    $initials = '';
    foreach (array_slice(preg_split('/\s+/', trim($name)) ?: [], 0, 2) as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }
    return '<span class="team-badge ' . ($size === 'small' ? 'team-badge-small' : '') . '">'
        . ($logo ? '<img src="' . e($logo) . '" alt="">' : '<span>' . e($initials ?: 'TBD') . '</span>')
        . '</span>';
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}
