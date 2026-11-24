<?php
declare(strict_types=1);

function handle_post(PDO $pdo): void
{
    $action = $_POST['action'] ?? '';
    if ($action === 'login') {
        verify_csrf();
        $attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'started' => time()];
        if (time() - (int)$attempts['started'] > 900) {
            $attempts = ['count' => 0, 'started' => time()];
        }
        if ((int)$attempts['count'] >= 10) {
            throw new RuntimeException('Too many sign-in attempts. Please wait 15 minutes and try again.');
        }
        $emailInput = $_POST['email'] ?? '';
        $passwordInput = $_POST['password'] ?? '';
        if (!is_string($emailInput) || !is_string($passwordInput)) {
            throw new RuntimeException('Invalid email or password.');
        }
        $email = strtolower(trim($emailInput));
        $statement = $pdo->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = ? AND active = 1');
        $statement->execute([$email]);
        $user = $statement->fetch();
        if (!$user || !password_verify($passwordInput, $user['password_hash'])) {
            $attempts['count']++;
            $_SESSION['login_attempts'] = $attempts;
            throw new RuntimeException('Invalid email or password.');
        }
        unset($user['password_hash'], $_SESSION['login_attempts']);
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
        audit($pdo, $user, 'login', 'auth', $user['id']);
        flash('Signed in successfully.');
        redirect('/admin');
    }

    verify_csrf();
    $user = require_user($pdo);
    if ($action === 'logout') {
        audit($pdo, $user, 'logout', 'auth', $user['id']);
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        redirect('/admin');
    }

    if ($action === 'bulk-import') {
        require_role($user, ['Super Admin', 'Admin']);
        unset($_SESSION['bulk_import_result']);
        $file = $_FILES['excel_file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Choose a completed Excel .xlsx workbook to upload.');
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $message = match ((int)$file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The workbook exceeds the server upload limit of ' . excel_upload_limit_label() . '.',
                UPLOAD_ERR_PARTIAL => 'The workbook upload was interrupted. Try again.',
                default => 'The workbook could not be uploaded. Check your PHP upload settings and try again.',
            };
            throw new RuntimeException($message);
        }
        if (!is_string($file['name'] ?? null) || strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'xlsx'
            || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Upload an Excel workbook saved in the .xlsx format.');
        }
        $sheets = excel_upload_sheets($file['tmp_name']);
        $_SESSION['bulk_import_result'] = import_excel_workbook($pdo, $user, $sheets);
        redirect('/admin?section=bulk-import');
    }

    $resource = (string)($_POST['resource'] ?? '');
    $configs = admin_resources();
    if ($action === 'save' || $action === 'delete' || $action === 'publish') {
        if (!isset($configs[$resource])) {
            throw new RuntimeException('Unknown record type.');
        }
        if ($resource === 'users') {
            require_role($user, ['Super Admin']);
        } elseif ($resource === 'audit-logs') {
            require_role($user, ['Super Admin', 'Admin']);
        } elseif (in_array($resource, ['matches', 'results'], true)) {
            require_role($user, ['Super Admin', 'Admin', 'Editor']);
        } else {
            require_role($user, ['Super Admin', 'Admin']);
        }

        $id = trim((string)($_POST['id'] ?? ''));
        if ($action === 'publish') {
            publish_result($pdo, $user, $id);
            flash('Result published. Public standings and statistics now include this score.');
            redirect('/admin?section=results');
        }
        if ($action === 'delete') {
            if (($_POST['confirm'] ?? '') !== '1') {
                throw new RuntimeException('Confirm the deletion before continuing.');
            }
            delete_resource($pdo, $user, $resource, $id);
            flash('Record deleted.');
            redirect('/admin?section=' . rawurlencode($resource));
        }
        save_resource($pdo, $user, $resource, $id);
        flash($configs[$resource]['title'] . ($id ? ' updated.' : ' created.') . ($resource === 'results' ? ' Draft scores stay private until published.' : ''));
        redirect('/admin?section=' . rawurlencode($resource));
    }
    throw new RuntimeException('Unknown action.');
}

function save_resource(PDO $pdo, array $user, string $resource, string $id): void
{
    $config = admin_resources()[$resource];
    $fields = $config['fields'];
    $values = [];
    foreach ($fields as $key => $field) {
        $type = $field[1];
        if ($type === 'url' && in_array($key, ['logo_url', 'banner_url', 'photo_url'], true)) {
            $uploaded = store_image_upload('upload_' . $key);
            if ($uploaded !== null) {
                $values[$key] = $uploaded;
            } elseif ($id !== '') {
                $existing = one($pdo, 'SELECT `' . $key . '` FROM `' . $config['table'] . '` WHERE id = ?', [$id]);
                if (!$existing) {
                    throw new RuntimeException('The record to update was not found.');
                }
                $values[$key] = $existing[$key];
            } else {
                $values[$key] = null;
            }
            continue;
        }
        if ($type === 'password') {
            $password = $_POST[$key] ?? '';
            if (!is_string($password)) {
                throw new RuntimeException('Invalid password value.');
            }
            if ($resource === 'users' && !$id && strlen($password) < 12) {
                throw new RuntimeException('Password must be at least 12 characters.');
            }
            if ($password !== '') {
                if (strlen($password) < 12) {
                    throw new RuntimeException('Password must be at least 12 characters.');
                }
                $values['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }
            continue;
        }
        $value = $_POST[$key] ?? null;
        if ($type === 'checkbox') {
            $values[$key] = isset($_POST[$key]) ? 1 : 0;
            continue;
        }
        if ($value !== null && !is_string($value) && !is_int($value)) {
            throw new RuntimeException('Invalid value for ' . $field[0] . '.');
        }
        if (is_string($value)) {
            $value = trim($value);
        } elseif (is_int($value)) {
            $value = (string)$value;
        }
        $required = (bool)($field[2] ?? false);
        if ($required && ($value === null || $value === '')) {
            throw new RuntimeException($field[0] . ' is required.');
        }
        if ($value === '') {
            $value = null;
        }
        if ($value !== null && $type === 'select' && !in_array($value, $field[3], true)) {
            throw new RuntimeException('Invalid value for ' . $field[0] . '.');
        }
        if ($value !== null && $type === 'number') {
            if (filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < 0) {
                throw new RuntimeException($field[0] . ' must be a non-negative whole number.');
            }
            if ($key === 'jersey_number' && (int)$value > 99) {
                throw new RuntimeException('Jersey number must be between 0 and 99.');
            }
            if (str_ends_with($key, '_score') && (int)$value > 99) {
                throw new RuntimeException('Scores must be 99 or lower.');
            }
            $value = (int)$value;
        }
        if ($value !== null && $type === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid email address.');
        }
        if ($value !== null && $type === 'url') {
            $localUpload = $key !== 'website_url'
                && preg_match('#^/uploads/[a-f0-9]{40}\.(?:jpg|png|webp|gif)$#', $value) === 1;
            $parts = parse_url($value);
            $webUrl = filter_var($value, FILTER_VALIDATE_URL)
                && isset($parts['scheme'], $parts['host'])
                && in_array(strtolower($parts['scheme']), ['http', 'https'], true);
            if (!$localUpload && !$webUrl) {
                throw new RuntimeException($field[0] . ' must be a valid HTTP or HTTPS URL.');
            }
        }
        if ($value !== null && $type === 'datetime-local') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
            $dateErrors = DateTimeImmutable::getLastErrors();
            if (!$date || ($dateErrors && ($dateErrors['warning_count'] || $dateErrors['error_count']))) {
                throw new RuntimeException('Enter a valid kick-off time.');
            }
            $value = $date->format('Y-m-d H:i:s');
        } elseif ($value !== null && $type === 'date') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            $dateErrors = DateTimeImmutable::getLastErrors();
            if (!$date || ($dateErrors && ($dateErrors['warning_count'] || $dateErrors['error_count']))) {
                throw new RuntimeException('Enter a valid date for ' . $field[0] . '.');
            }
        }
        if ($type === 'relation' && $value !== null) {
            $relationTable = ['tournaments' => 'tournaments', 'teams' => 'teams', 'matches' => 'matches'][$field[3]];
            if (!one($pdo, "SELECT id FROM {$relationTable} WHERE id = ?", [$value])) {
                throw new RuntimeException('The selected ' . strtolower($field[0]) . ' does not exist.');
            }
        }
        $values[$key] = $value;
    }

    if ($resource === 'users') {
        $values['email'] = strtolower((string)$values['email']);
    }
    if ($resource === 'matches') {
        if ($values['home_team_id'] === $values['away_team_id']) {
            throw new RuntimeException('A team cannot play itself.');
        }
        $teamCount = one(
            $pdo,
            'SELECT COUNT(*) AS total FROM teams WHERE id IN (?, ?) AND tournament_id = ?',
            [$values['home_team_id'], $values['away_team_id'], $values['tournament_id']]
        );
        if ((int)$teamCount['total'] !== 2) {
            throw new RuntimeException('Both fixture teams must belong to its tournament.');
        }
        $existing = $id ? one($pdo, "SELECT id FROM results WHERE match_id = ? AND status = 'published'", [$id]) : null;
        if ($existing && $values['status'] !== 'completed') {
            throw new RuntimeException('A fixture with a published result must remain completed.');
        }
        if (!$existing && $values['status'] === 'completed') {
            throw new RuntimeException('Publish a result to complete a fixture.');
        }
    }
    if ($resource === 'results') {
        $match = one($pdo, 'SELECT * FROM matches WHERE id = ?', [$values['match_id']]);
        if (!$match) {
            throw new RuntimeException('The selected fixture does not exist.');
        }
        $values['tournament_id'] = $match['tournament_id'];
        $values['home_team_id'] = $match['home_team_id'];
        $values['away_team_id'] = $match['away_team_id'];
    }
    if ($resource === 'players') {
        $team = one($pdo, 'SELECT id FROM teams WHERE id = ?', [$values['team_id']]);
        if (!$team) {
            throw new RuntimeException('Select a valid team.');
        }
    }

    $pdo->beginTransaction();
    try {
        if ($id) {
            $existing = one($pdo, 'SELECT id FROM ' . $config['table'] . ' WHERE id = ?', [$id]);
            if (!$existing) {
                throw new RuntimeException('Record not found.');
            }
            if ($resource === 'results') {
                $wasPublished = (bool)one($pdo, "SELECT id FROM results WHERE id = ? AND status = 'published'", [$id]);
                $values['status'] = 'draft';
                $values['published_at'] = null;
                $values['published_by'] = null;
                $values['created_by'] = $user['id'];
                if ($wasPublished) {
                    $pdo->prepare("UPDATE matches SET status = 'scheduled', updated_at = NOW() WHERE id = (SELECT match_id FROM results WHERE id = ?)")
                        ->execute([$id]);
                }
            }
            $sets = array_map(static fn(string $column): string => "`{$column}` = ?", array_keys($values));
            if ($sets) {
                $sql = 'UPDATE `' . $config['table'] . '` SET ' . implode(', ', $sets) . ' WHERE id = ?';
                $pdo->prepare($sql)->execute([...array_values($values), $id]);
            }
            $resourceId = $id;
            $verb = 'update';
        } else {
            $resourceId = uuid();
            $values['id'] = $resourceId;
            if ($resource === 'results') {
                $values['status'] = 'draft';
                $values['created_by'] = $user['id'];
            }
            $columns = array_keys($values);
            $quoted = array_map(static fn(string $column): string => "`{$column}`", $columns);
            $placeholders = array_fill(0, count($columns), '?');
            $sql = 'INSERT INTO `' . $config['table'] . '` (' . implode(', ', $quoted) . ', created_at, updated_at) VALUES ('
                . implode(', ', $placeholders) . ', NOW(), NOW())';
            $pdo->prepare($sql)->execute(array_values($values));
            $verb = 'create';
        }
        audit($pdo, $user, $verb, $resource, $resourceId);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function store_image_upload(string $field): ?string
{
    $file = $_FILES[$field] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (!is_string($file['tmp_name'] ?? null) || !is_numeric($file['size'] ?? null)) {
        throw new RuntimeException('The uploaded image could not be read.');
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed. Choose an image up to 5 MB and try again.');
    }
    if (!is_uploaded_file($file['tmp_name']) || (int)$file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Images must be 5 MB or smaller.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Choose a JPG, PNG, WebP, or GIF image.');
    }
    $directory = dirname(__DIR__) . '/uploads';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Image storage is unavailable.');
    }
    $filename = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('The image could not be saved. Please try again.');
    }
    return '/uploads/' . $filename;
}

function publish_result(PDO $pdo, array $user, string $id): void
{
    if (!$id) {
        throw new RuntimeException('Choose a result to publish.');
    }
    $result = one($pdo, 'SELECT id, match_id, status FROM results WHERE id = ?', [$id]);
    if (!$result) {
        throw new RuntimeException('Result not found.');
    }
    if ($result['status'] !== 'published') {
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE results SET status = 'published', published_at = NOW(), published_by = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$user['id'], $id]);
            $pdo->prepare("UPDATE matches SET status = 'completed', updated_at = NOW() WHERE id = ?")
                ->execute([$result['match_id']]);
            audit($pdo, $user, 'publish', 'results', $id);
            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }
}

function delete_resource(PDO $pdo, array $user, string $resource, string $id): void
{
    if (!$id) {
        throw new RuntimeException('Select a record to delete.');
    }
    $pdo->beginTransaction();
    try {
        $existing = one($pdo, 'SELECT * FROM `' . admin_resources()[$resource]['table'] . '` WHERE id = ?', [$id]);
        if (!$existing) {
            throw new RuntimeException('Record not found.');
        }
        if ($resource === 'users' && $id === $user['id']) {
            throw new RuntimeException('You cannot delete your own account.');
        }
        if ($resource === 'matches' && one($pdo, 'SELECT id FROM results WHERE match_id = ?', [$id])) {
            throw new RuntimeException('Delete the match result before deleting this fixture.');
        }
        if ($resource === 'results' && $existing['status'] === 'published') {
            $pdo->prepare("UPDATE matches SET status = 'scheduled', updated_at = NOW() WHERE id = ?")->execute([$existing['match_id']]);
        }
        $pdo->prepare('DELETE FROM `' . admin_resources()[$resource]['table'] . '` WHERE id = ?')->execute([$id]);
        audit($pdo, $user, 'delete', $resource, $id);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}
