<?php
declare(strict_types=1);

function render_admin(PDO $pdo, array $user, ?array $notice, string $error = ''): void
{
    $sections = [
        'overview' => 'Overview', 'tournaments' => 'Tournaments', 'teams' => 'Teams',
        'players' => 'Players', 'matches' => 'Fixtures', 'results' => 'Results',
        'sponsors' => 'Sponsors', 'users' => 'Admin users', 'audit-logs' => 'Audit log',
        'bulk-import' => 'Excel bulk import',
    ];
    if (($user['role'] ?? '') === 'Editor') {
        unset($sections['tournaments'], $sections['teams'], $sections['players'], $sections['sponsors'], $sections['users'], $sections['audit-logs'], $sections['bulk-import']);
    } elseif (($user['role'] ?? '') === 'Admin') {
        unset($sections['users']);
    }
    $section = (string)($_GET['section'] ?? 'overview');
    if (!isset($sections[$section])) {
        $section = 'overview';
    }
    ?>
    <main class="admin-shell">
      <aside class="admin-sidebar">
          <a class="brand" href="/"><img class="brand-logo" src="/client/assets/neco_logo.png" alt="Neco Insurance Limited"></a>
          <div class="admin-sidebar-label">TOURNAMENT MANAGEMENT</div>
          <nav aria-label="Admin navigation">
            <?php foreach ($sections as $key => $label): ?><a class="admin-sidebar-item <?= $section === $key ? 'admin-sidebar-active' : '' ?>" href="/admin?section=<?= e($key) ?>"><?= e($label) ?></a><?php endforeach; ?>
          </nav>
          <a href="/">View public tournament</a>
          <div class="admin-sidebar-bottom"><span class="admin-user-icon"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></span><div><b><?= e($user['name']) ?></b><small><?= e($user['role']) ?></small></div><form method="post" action="/admin"><input type="hidden" name="action" value="logout"><?= csrf_field() ?><button type="submit">Logout</button></form></div>
      </aside>
      <section class="admin-workspace">
          <?php if ($notice): ?><div class="auth-toast auth-toast-<?= e($notice['type'] ?? 'success') ?>" role="status"><?= e($notice['message'] ?? '') ?></div><?php endif; ?>
          <?php if ($error): ?><div class="error-state" role="alert"><?= e($error) ?></div><?php endif; ?>
          <?php if ($section === 'overview'): render_admin_overview($pdo, $user); ?>
          <?php elseif ($section === 'audit-logs'): render_audit_logs($pdo); ?>
          <?php elseif ($section === 'bulk-import'): render_bulk_import($user); ?>
          <?php else: render_resource_manager($pdo, $user, $section); ?>
          <?php endif; ?>
      </section>
    </main>
    <?php
}

function render_admin_overview(PDO $pdo, array $user): void
{
    $counts = [];
    foreach (['tournaments', 'teams', 'players', 'matches', 'results', 'sponsors'] as $table) {
        $counts[$table] = (int)one($pdo, "SELECT COUNT(*) AS total FROM {$table}")['total'];
    }
    $published = (int)one($pdo, "SELECT COUNT(*) AS total FROM results WHERE status = 'published'")['total'];
    $upcoming = all(
        $pdo,
        'SELECT m.kickoff_at, m.round, h.name AS home_team_name, a.name AS away_team_name
         FROM matches m JOIN teams h ON h.id = m.home_team_id JOIN teams a ON a.id = m.away_team_id
         WHERE m.kickoff_at >= NOW() AND m.status = "scheduled" ORDER BY m.kickoff_at LIMIT 5'
    );
    ?>
    <section class="admin-panel">
      <div class="admin-panel-heading"><div><span class="eyebrow">TOURNAMENT CONTROL</span><h1>Welcome, <?= e($user['name']) ?><span class="heading-period">.</span></h1><p>Manage your tournament and keep every update in one place.</p></div></div>
      <?php if (has_role($user, ['Super Admin', 'Admin'])): ?>
        <div class="bulk-import-quick-card"><div><span class="eyebrow">SAVE TIME ON DATA ENTRY</span><strong>Set up your tournament in one go.</strong><p>Download the workbook, fill in the sheets, and import everything together.</p></div><a class="button button-coral button-small" href="/admin?section=bulk-import">Open bulk import →</a></div>
      <?php endif; ?>
      <div class="admin-stat-grid">
        <?php foreach (['tournaments' => 'TOURNAMENTS', 'teams' => 'TEAMS', 'players' => 'PLAYERS', 'matches' => 'FIXTURES', 'results' => 'RESULTS', 'sponsors' => 'SPONSORS'] as $key => $label): ?>
          <article class="stat-tile"><span class="eyebrow"><?= e($label) ?></span><strong><?= $counts[$key] ?></strong></article>
        <?php endforeach; ?>
      </div>
      <div class="admin-panel-heading"><div><span class="eyebrow">PUBLIC SCOREBOARD</span><h2><?= $published ?> published results</h2></div><a class="button button-coral button-small" href="/admin?section=results">Manage results →</a></div>
      <h3>Upcoming fixtures</h3>
      <?php if ($upcoming): ?><div class="admin-resource-table-wrap"><table class="admin-resource-table"><thead><tr><th>FIXTURE</th><th>KICK-OFF</th><th>ROUND</th></tr></thead><tbody>
      <?php foreach ($upcoming as $match): ?><tr><td><?= e($match['home_team_name']) ?> vs <?= e($match['away_team_name']) ?></td><td><?= e(date_label($match['kickoff_at'], 'M j, Y · g:i A')) ?></td><td><?= e($match['round'] ?: '—') ?></td></tr><?php endforeach; ?>
      </tbody></table></div><?php else: ?><div class="empty-state"><strong>No upcoming fixtures</strong><p>Scheduled matches will be listed here.</p></div><?php endif; ?>
    </section>
    <?php
}

function render_bulk_import(array $user): void
{
    require_role($user, ['Super Admin', 'Admin']);
    $result = $_SESSION['bulk_import_result'] ?? null;
    ?>
    <section class="admin-panel admin-resource-panel bulk-import-panel">
      <div class="admin-panel-heading"><div><span class="eyebrow">ADMIN-ONLY WORKFLOW</span><h1>Excel bulk import<span class="heading-period">.</span></h1><p>Prepare your workbook once, then import linked tournament records in the correct order.</p></div>
        <a class="button button-light" href="/admin?download=bulk-template">Download Excel template ↓</a>
      </div>
      <div class="bulk-import-steps">
        <article><span>01</span><div><strong>Download</strong><p>Use the workbook template with separate sheets for each record type.</p></div></article>
        <article><span>02</span><div><strong>Fill in your data</strong><p>Keep column headings unchanged. Import uses tournament and team names to link records.</p></div></article>
        <article><span>03</span><div><strong>Review the report</strong><p>Valid rows are saved. Duplicates or invalid rows include the sheet, row, and reason.</p></div></article>
      </div>
      <div class="bulk-import-order"><b>Import order</b><span>Tournaments</span><i>→</i><span>Teams</span><i>→</i><span>Players</span><i>→</i><span>Fixtures</span><i>→</i><span>Results</span><i>→</i><span>Sponsors</span></div>
      <form class="bulk-import-form" method="post" action="/admin" enctype="multipart/form-data">
        <input type="hidden" name="action" value="bulk-import"><?= csrf_field() ?>
        <label for="excel-file">Your completed workbook</label>
        <input id="excel-file" name="excel_file" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
        <small>Excel .xlsx only · up to <?= e(excel_upload_limit_label()) ?> (your PHP upload limit) · maximum 5,000 data rows. Each valid row is committed independently; invalid rows are reported without overwriting existing records.</small>
        <button class="button button-coral">Validate and import workbook →</button>
      </form>
      <?php if (is_array($result)): ?>
        <section class="bulk-import-report" aria-live="polite">
          <?php if (!empty($result['audit_error'])): ?><div class="error-state" role="alert"><?= e($result['audit_error']) ?></div><?php endif; ?>
          <div class="bulk-report-heading"><div><span class="eyebrow">IMPORT SUMMARY</span><h2><?= (int)$result['imported'] ?> of <?= (int)$result['total'] ?> rows imported</h2></div>
            <span class="bulk-report-status <?= $result['error_count'] ? 'has-errors' : 'all-clear' ?>"><?= $result['error_count'] ? (int)$result['error_count'] . ' rows need attention' : 'No row errors' ?></span>
          </div>
          <div class="bulk-report-counts"><?php foreach ($result['records'] as $sheet => $count): ?>
            <?php if ($count['imported'] || $count['failed']): ?><div><strong><?= (int)$count['imported'] ?></strong><span><?= e($sheet) ?> imported</span><?php if ($count['failed']): ?><small><?= (int)$count['failed'] ?> failed</small><?php endif; ?></div><?php endif; ?>
          <?php endforeach; ?></div>
          <?php if ($result['errors']): ?><div class="bulk-errors"><h3>Rows to correct</h3><div class="bulk-errors-scroll"><table class="admin-resource-table"><thead><tr><th>SHEET</th><th>ROW</th><th>REASON</th></tr></thead><tbody>
            <?php foreach (array_slice($result['errors'], 0, 100) as $error): ?><tr><td><?= e($error['sheet']) ?></td><td><?= (int)$error['row'] ?></td><td><?= e($error['message']) ?></td></tr><?php endforeach; ?>
          </tbody></table></div><?php if ($result['error_count'] > 100): ?><p>Showing the first 100 of <?= (int)$result['error_count'] ?> errors. Fix these rows and re-upload in smaller batches to review the remainder.</p><?php endif; ?></div><?php endif; ?>
        </section>
      <?php endif; ?>
    </section>
    <?php
}

function render_resource_manager(PDO $pdo, array $user, string $resource): void
{
    $configs = admin_resources();
    $config = $configs[$resource];
    $rows = resource_rows($pdo, $resource);
    $editingId = (string)($_GET['edit'] ?? '');
    $editing = null;
    if ($editingId) {
        foreach ($rows as $row) {
            if ($row['id'] === $editingId) {
                $editing = $row;
                break;
            }
        }
    }
    $lookups = [
        'tournaments' => all($pdo, 'SELECT id, name FROM tournaments ORDER BY created_at DESC'),
        'teams' => all($pdo, 'SELECT id, name, tournament_id FROM teams ORDER BY name'),
        'matches' => all($pdo, 'SELECT m.id, m.kickoff_at, h.name AS home_name, a.name AS away_name FROM matches m JOIN teams h ON h.id = m.home_team_id JOIN teams a ON a.id = m.away_team_id ORDER BY m.kickoff_at DESC'),
    ];
    $tableHeaders = [
        'tournaments' => ['name' => 'NAME', 'edition' => 'EDITION', 'status' => 'STATUS'],
        'teams' => ['name' => 'TEAM', 'short_name' => 'SHORT NAME', 'tournament_name' => 'TOURNAMENT', 'active' => 'ACTIVE'],
        'players' => ['name' => 'PLAYER', 'team_name' => 'TEAM', 'position' => 'POSITION', 'jersey_number' => 'NUMBER'],
        'matches' => ['home_team_name' => 'FIXTURE', 'tournament_name' => 'TOURNAMENT', 'kickoff_at' => 'KICK-OFF', 'status' => 'STATUS'],
        'results' => ['home_team_name' => 'HOME', 'away_team_name' => 'AWAY', 'home_score' => 'HOME SCORE', 'away_score' => 'AWAY SCORE', 'status' => 'STATUS'],
        'sponsors' => ['name' => 'NAME', 'tier' => 'CATEGORY', 'tournament_name' => 'TOURNAMENT', 'active' => 'ACTIVE'],
        'users' => ['name' => 'NAME', 'email' => 'EMAIL', 'role' => 'ROLE', 'active' => 'ACTIVE'],
    ];
    $columns = $tableHeaders[$resource];
    ?>
    <section class="admin-panel admin-resource-panel">
      <div class="admin-panel-heading"><div><span class="eyebrow">CONTENT MANAGEMENT</span><h1><?= e($config['title']) ?><span class="heading-period">.</span></h1></div><a class="button button-coral" href="/admin?section=<?= e($resource) ?>">Add <?= e(rtrim($config['title'], 's')) ?> +</a></div>
      <form class="resource-form" method="post" action="/admin" enctype="multipart/form-data">
        <div class="resource-form-heading"><b><?= $editing ? 'Edit record' : 'New record' ?></b><?php if ($editing): ?><a class="resource-cancel" href="/admin?section=<?= e($resource) ?>">Cancel edit</a><?php endif; ?></div>
        <input type="hidden" name="action" value="save"><input type="hidden" name="resource" value="<?= e($resource) ?>"><input type="hidden" name="id" value="<?= e($editing['id'] ?? '') ?>"><?= csrf_field() ?>
        <div class="resource-fields">
          <?php foreach ($config['fields'] as $key => $field):
              $label = $field[0];
              $type = $field[1];
              $required = (bool)($field[2] ?? false);
              $submitted = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['resource'] ?? '') === $resource;
              $value = $submitted ? ($_POST[$key] ?? '') : ($editing[$key] ?? '');
              if ($key === 'password') $value = '';
              if ($type === 'datetime-local' && $value) $value = date('Y-m-d\TH:i', strtotime((string)$value));
              if ($type === 'date' && $value) $value = date('Y-m-d', strtotime((string)$value));
          ?>
            <?php if ($type === 'checkbox'): ?>
              <label class="resource-checkbox"><input type="checkbox" name="<?= e($key) ?>" value="1" <?= $submitted ? (isset($_POST[$key]) ? 'checked' : '') : ((!$editing || !empty($value)) ? 'checked' : '') ?>><?= e($label) ?></label>
            <?php elseif ($type === 'textarea'): ?>
              <label class="resource-field resource-field-wide"><?= e($label) ?><textarea name="<?= e($key) ?>" rows="3" <?= $required ? 'required' : '' ?>><?= e($value) ?></textarea></label>
            <?php elseif ($type === 'url' && in_array($key, ['logo_url', 'banner_url', 'photo_url'], true)): ?>
              <label class="resource-field"><?= e($label) ?>
                <?php if (!empty($editing[$key])): ?><small>Current image is saved. Choose a file to replace it.</small><?php endif; ?>
                <input name="upload_<?= e($key) ?>" type="file" accept="image/jpeg,image/png,image/webp,image/gif">
                <small>Choose a JPG, PNG, WebP, or GIF image up to 5 MB.</small>
              </label>
            <?php elseif ($type === 'select' || $type === 'relation'): ?>
              <label class="resource-field"><?= e($label) ?><select name="<?= e($key) ?>" <?= $required ? 'required' : '' ?>><option value="">Select…</option>
                <?php if ($type === 'select'): foreach ($field[3] as $choice): ?><option value="<?= e($choice) ?>" <?= (string)$value === $choice ? 'selected' : '' ?>><?= e($choice) ?></option><?php endforeach;
                else: foreach ($lookups[$field[3]] as $choice):
                    $choiceLabel = $choice['name'] ?? ($choice['home_name'] . ' vs ' . $choice['away_name'] . ' · ' . date_label($choice['kickoff_at'], 'M j, Y'));
                ?><option value="<?= e($choice['id']) ?>" <?= (string)$value === $choice['id'] ? 'selected' : '' ?>><?= e($choiceLabel) ?></option><?php endforeach; endif; ?>
              </select></label>
            <?php else: ?>
              <label class="resource-field"><?= e($label) ?><input name="<?= e($key) ?>" type="<?= e($type) ?>" value="<?= e($value) ?>" <?= $required && !($key === 'password' && $editing) ? 'required' : '' ?>
                <?= $key === 'jersey_number' ? 'min="0" max="99"' : (str_ends_with($key, '_score') ? 'min="0" max="99"' : '') ?>
                <?= $type === 'password' && $editing ? 'placeholder="Leave blank to keep current password"' : '' ?>></label>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
        <div class="resource-form-actions"><button class="button button-dark"><?= $editing ? 'Save changes' : 'Create record' ?></button><span>Changes are saved to the MySQL database.</span></div>
      </form>
      <?php if ($rows): ?><div class="admin-resource-table-wrap"><table class="admin-resource-table"><thead><tr><?php foreach ($columns as $label): ?><th><?= e($label) ?></th><?php endforeach; ?><th>ACTIONS</th></tr></thead><tbody>
        <?php foreach ($rows as $row): ?><tr>
          <?php foreach ($columns as $key => $_label):
              $value = $row[$key] ?? null;
              if ($key === 'kickoff_at') $value = date_label($value, 'M j, Y · g:i A');
              if ($key === 'home_team_name' && $resource === 'matches') $value .= ' vs ' . ($row['away_team_name'] ?? 'TBD');
              if ($key === 'home_team_name' && $resource === 'results') $value = $row['home_team_name'];
              if ($key === 'active') $value = $value ? 'Yes' : 'No';
              if (str_ends_with($key, '_score') || $key === 'jersey_number') $value = $value === null ? '—' : (string)$value;
          ?><td><?= e($value ?? '—') ?></td><?php endforeach; ?>
          <td><div class="resource-actions"><a href="/admin?section=<?= e($resource) ?>&edit=<?= e($row['id']) ?>">Edit</a>
            <?php if ($resource === 'results' && $row['status'] !== 'published'): ?><form method="post" action="/admin" class="inline-action"><input type="hidden" name="action" value="publish"><input type="hidden" name="resource" value="results"><input type="hidden" name="id" value="<?= e($row['id']) ?>"><?= csrf_field() ?><button>Publish</button></form><?php endif; ?>
            <form method="post" action="/admin" class="inline-action"><input type="hidden" name="action" value="delete"><input type="hidden" name="resource" value="<?= e($resource) ?>"><input type="hidden" name="id" value="<?= e($row['id']) ?>"><?= csrf_field() ?><label class="delete-confirm"><input type="checkbox" name="confirm" value="1" required>Confirm</label><button class="danger-action">Delete</button></form>
          </div></td>
        </tr><?php endforeach; ?>
      </tbody></table></div><?php else: ?><div class="empty-state"><strong>No <?= e(strtolower($config['title'])) ?> yet</strong><p>Create your first record using the form above.</p></div><?php endif; ?>
    </section>
    <?php
}

function render_audit_logs(PDO $pdo): void
{
    $rows = all(
        $pdo,
        'SELECT a.*, u.name AS actor_name, u.email AS actor_email
         FROM audit_logs a LEFT JOIN users u ON u.id = a.actor_id
         ORDER BY a.created_at DESC LIMIT 100'
    );
    ?>
    <section class="admin-panel admin-resource-panel"><div class="admin-panel-heading"><div><span class="eyebrow">ADMINISTRATIVE HISTORY</span><h1>Audit log<span class="heading-period">.</span></h1></div></div>
      <?php if ($rows): ?><div class="admin-resource-table-wrap"><table class="admin-resource-table"><thead><tr><th>ACTOR</th><th>ACTION</th><th>RECORD TYPE</th><th>RECORD ID</th><th>DATE</th></tr></thead><tbody>
      <?php foreach ($rows as $row): ?><tr><td><?= e($row['actor_name'] ?: $row['actor_email'] ?: 'System') ?></td><td><?= e($row['action']) ?></td><td><?= e($row['resource']) ?></td><td><?= e($row['resource_id'] ?: '—') ?></td><td><?= e(date_label($row['created_at'], 'M j, Y · g:i A')) ?></td></tr><?php endforeach; ?>
      </tbody></table></div><?php else: ?><div class="empty-state"><strong>No administrative activity yet</strong><p>Important administrative changes will be recorded here.</p></div><?php endif; ?>
    </section>
    <?php
}

function render_login(?array $notice, string $error = ''): void
{
    ?>
    <main class="admin-shell admin-login-shell">
      <a class="back-link" href="/">← Back to tournament</a>
      <?php if ($notice): ?><div class="auth-toast auth-toast-<?= e($notice['type'] ?? 'success') ?>"><?= e($notice['message'] ?? '') ?></div><?php endif; ?>
      <?php if ($error): ?><div class="auth-toast auth-toast-error" role="alert"><?= e($error) ?></div><?php endif; ?>
      <section class="admin-login-card"><a class="brand" href="/"><img class="brand-logo" src="/client/assets/neco_logo.png" alt="Neco Insurance Limited"></a>
        <span class="eyebrow">SECURE ADMIN PORTAL</span><h1>Welcome<br>back<span>.</span></h1><p>Sign in to manage tournament content and results.</p>
        <form method="post" action="/admin"><input type="hidden" name="action" value="login"><?= csrf_field() ?>
          <label>Email address<input name="email" type="email" autocomplete="username" required></label>
          <label>Password<input name="password" type="password" autocomplete="current-password" required></label>
          <button class="button button-coral button-wide">Sign in →</button>
        </form>
      </section>
    </main>
    <?php
}
