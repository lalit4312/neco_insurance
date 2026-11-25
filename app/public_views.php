<?php
declare(strict_types=1);

function public_header(?array $tournament, string $page): void
{
    $navigation = [
        '/' => 'Overview', '/fixtures' => 'Fixtures', '/results' => 'Results',
        '/bracket' => 'Bracket', '/standings' => 'Standings', '/teams' => 'Teams',
        '/players' => 'Players', '/stats' => 'Stats',
    ];
    ?>
    <header class="site-header">
      <div class="header-inner">
        <a class="brand" href="/">
          <img class="brand-logo" src="/client/assets/neco_logo.png" alt="Neco Insurance Limited">
        </a>
        <nav class="main-nav" aria-label="Main navigation">
          <?php foreach ($navigation as $path => $label): ?>
            <a class="nav-link <?= $page === $path ? 'active' : '' ?>" href="<?= e($path) ?>"><?= e($label) ?></a>
          <?php endforeach; ?>
        </nav>
        <div class="header-side">
          <span class="edition-tag"><span class="edition-dot"></span><?= e($tournament['edition'] ?? 'TOURNAMENT') ?></span>
          <a class="admin-link" href="/admin">Admin</a>
        </div>
      </div>
    </header>
    <?php
}

function public_footer(array $sponsors): void
{
    ?>
    <footer class="site-footer">
      <div class="sponsor-band">
        <div class="sponsor-band-copy"><span class="eyebrow">GOOD COMPANY</span><h2>Made possible by<br>our partners.</h2></div>
        <div class="sponsor-logos">
          <?php if ($sponsors): foreach (array_slice($sponsors, 0, 5) as $sponsor): ?>
            <a class="sponsor-logo" href="<?= e($sponsor['website_url'] ?: '#') ?>" <?= $sponsor['website_url'] ? 'target="_blank" rel="noreferrer"' : '' ?>>
              <?php if ($sponsor['logo_url']): ?><img src="<?= e($sponsor['logo_url']) ?>" alt="<?= e($sponsor['name']) ?> logo"><?php else: ?><?= e($sponsor['name']) ?><?php endif; ?>
            </a>
          <?php endforeach; else: ?>
            <span class="sponsor-placeholder">Partner announcements coming soon</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="footer-bottom">
        <p>Every match. Every moment. <span>© <?= date('Y') ?> Neco Insurance Limited.</span></p>
        <div class="footer-links"><a href="/sponsors">Partners</a><a href="/admin">Admin portal</a><a href="https://necoinsurance.com" target="_blank" rel="noreferrer">Company</a></div>
      </div>
    </footer>
    <?php
}

function match_card(array $match, bool $compact = false): string
{
    $completed = $match['result_status'] === 'published' || $match['status'] === 'completed';
    $state = $match['status'] === 'cancelled' ? 'Cancelled' : ($completed ? 'Full time' : ($match['round'] ?: 'Upcoming'));
    $statusClass = $completed ? 'status-done' : '';
    $date = date_label($match['kickoff_at'], 'M j');
    $time = date_label($match['kickoff_at'], 'g:i A');
    $home = ['name' => $match['home_team_name'], 'short_name' => $match['home_team_short_name'], 'logo_url' => $match['home_team_logo_url']];
    $away = ['name' => $match['away_team_name'], 'short_name' => $match['away_team_short_name'], 'logo_url' => $match['away_team_logo_url']];
    $score = $completed
        ? e((string)$match['home_score']) . ' — ' . e((string)$match['away_score'])
        : '<span>VS</span>';
    return '<article class="match-card ' . ($compact ? 'match-card-compact' : '') . '">'
        . '<div class="match-meta"><span class="match-status ' . $statusClass . '"><i></i>' . e($state) . '</span><span>' . e($date . ' · ' . $time) . '</span></div>'
        . '<div class="match-teams"><div class="match-side">' . team_badge($home) . '<span>' . e($home['name']) . '</span></div>'
        . '<div class="match-score">' . $score . '</div><div class="match-side match-side-away">' . team_badge($away) . '<span>' . e($away['name']) . '</span></div></div>'
        . '<div class="match-footer"><span>' . e($match['venue'] ?: 'Venue to be confirmed') . '</span><span>' . e($match['round'] ?? '') . '</span></div></article>';
}

function standings_table(array $rows, bool $compact = false): void
{
    $rows = $compact ? array_slice($rows, 0, 5) : $rows;
    ?>
    <div class="table-scroll"><table class="standings-table">
      <thead><tr><th>#</th><th>TEAM</th><th>P</th><th>W</th><th>D</th><th>L</th><th>GD</th><th>PTS</th></tr></thead>
      <tbody>
      <?php if (!$rows): ?><tr><td class="table-empty" colspan="8">No standings data yet.</td></tr><?php endif; ?>
      <?php foreach ($rows as $index => $row): ?>
        <tr><td><span class="position <?= $index === 0 ? 'position-first' : '' ?>"><?= $index + 1 ?></span></td>
          <td><div class="table-team"><?= team_badge(['name' => $row['team_name']], 'small') ?><b><?= e($row['team_name']) ?></b></div></td>
          <td><?= (int)$row['played'] ?></td><td><?= (int)$row['wins'] ?></td><td><?= (int)$row['draws'] ?></td>
          <td><?= (int)$row['losses'] ?></td><td><?= (int)$row['goal_difference'] ?></td><td class="points-cell"><?= (int)$row['points'] ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php
}

function render_public_page(string $page, array $data): void
{
    $tournament = $data['tournament'];
    $matches = $data['matches'];
    $titleMap = [
        '/fixtures' => ['The schedule', 'Fixtures', 'Every kick-off, all in one place.'],
        '/results' => ['The scoreboard', 'Match results', 'The latest scores from the tournament.'],
        '/standings' => ['The table', 'Standings', 'The race to the top, updated after every result.'],
        '/teams' => ['Meet the clubs', 'Teams', 'The teams bringing the competition to life.'],
        '/players' => ['The talent', 'Players', 'Get to know the players making their mark.'],
        '/stats' => ['By the numbers', 'Tournament stats', 'The stories behind every goal and result.'],
        '/bracket' => ['Knockout stage', 'Tournament bracket', 'The path to the final.'],
        '/sponsors' => ['Our partners', 'Sponsors', 'Proudly supported by our partners.'],
    ];
    if ($page !== '/' && !isset($titleMap[$page])) {
        ?>
        <main class="page-main">
          <section class="page-intro error-state" role="alert">
            <span class="eyebrow">PAGE NOT FOUND</span>
            <h1>That page isn’t here<span class="heading-period">.</span></h1>
            <p>The address may be incorrect, or the page may have moved.</p>
            <a class="button button-coral button-small" href="/">Return to the tournament homepage</a>
          </section>
        </main>
        <?php
        return;
    }
    if ($page === '/') {
        $upcoming = array_slice(array_values(array_filter($matches, static fn(array $m): bool =>
            in_array($m['status'], ['scheduled'], true) && $m['result_status'] !== 'published'
        )), 0, 2);
        $recent = array_slice(array_values(array_filter($matches, static fn(array $m): bool =>
            $m['result_status'] === 'published'
        )), 0, 3);
        ?>
        <section class="tournament-banner" data-banner-slider aria-label="Tournament banners" aria-roledescription="carousel">
          <div class="banner-track" data-banner-track>
            <div class="banner-slide" role="group" aria-roledescription="slide" aria-label="1 of 2">
              <img src="/client/assets/banner1.png" alt="Neco Insurance Futsal Cup tournament banner" fetchpriority="high">
            </div>
            <div class="banner-slide" role="group" aria-roledescription="slide" aria-label="2 of 2" aria-hidden="true">
              <img src="/client/assets/banner2.png" alt="Participating banks in the Neco Insurance Futsal Cup">
            </div>
          </div>
          <div class="banner-controls" aria-label="Banner controls">
            <button type="button" class="banner-arrow" data-banner-previous aria-label="Previous banner">‹</button>
            <div class="banner-pagination">
              <button type="button" class="banner-dot" data-banner-to="0" aria-label="Show banner 1" aria-current="true"></button>
              <button type="button" class="banner-dot" data-banner-to="1" aria-label="Show banner 2" aria-current="false"></button>
            </div>
            <button type="button" class="banner-arrow" data-banner-next aria-label="Next banner">›</button>
          </div>
        </section>
        <main class="page-main">
          <section class="section-block"><div class="section-heading"><div><span class="eyebrow">THE NEXT 90</span><h2>Up next<span class="heading-period">.</span></h2></div><a class="arrow-link" href="/fixtures">Full fixture list →</a></div>
            <?php if ($upcoming): ?><div class="match-grid"><?php foreach ($upcoming as $match) echo match_card($match); ?></div><?php else: ?><div class="empty-state"><strong>No upcoming fixtures</strong><p>New match schedules will appear here as soon as they are published.</p></div><?php endif; ?>
          </section>
          <section class="section-block standings-preview"><div class="section-heading"><div><span class="eyebrow">THE RACE</span><h2>Standings<span class="heading-period">.</span></h2></div><a class="arrow-link" href="/standings">Full table →</a></div><div class="standings-panel"><div class="panel-top"><span><?= e($tournament['name'] ?? 'Tournament table') ?></span><span>UPDATED AUTOMATICALLY</span></div><?php standings_table($data['standings'], true); ?></div></section>
          <section class="results-section section-block"><div class="section-heading"><div><span class="eyebrow">THE LATEST</span><h2>Final whistle<span class="heading-period">.</span></h2></div><a class="arrow-link" href="/results">All results →</a></div>
            <?php if ($recent): ?><div class="result-list"><?php foreach ($recent as $match) echo match_card($match, true); ?></div><?php else: ?><div class="empty-state"><strong>The first whistle is still to come</strong><p>Published match results and final scores will show here.</p></div><?php endif; ?>
          </section><section class="callout"><div><span class="eyebrow">MORE THAN A GAME</span><h2>Good things happen<br>when we play together.</h2></div><a class="button button-dark" href="/teams">Meet the teams →</a></section>
        </main>
        <?php
        return;
    }

    [$label, $title, $description] = $titleMap[$page] ?? $titleMap['/fixtures'];
    ?>
    <main class="page-main listing-page">
      <div class="page-intro"><div class="breadcrumb"><a href="/">HOME</a><span>/</span><span><?= e(strtoupper($label)) ?></span></div><span class="eyebrow"><?= e($label) ?></span><h1><?= e($title) ?><i>.</i></h1><p><?= e($description) ?></p></div>
      <?php if (!$tournament): ?>
        <div class="empty-state"><strong>Tournament information is not available yet.</strong><p>Published tournament information will appear here.</p></div>
      <?php elseif (in_array($page, ['/fixtures', '/results'], true)):
          $visible = array_filter($matches, static fn(array $match): bool =>
              $page === '/fixtures' ? $match['result_status'] !== 'published' : $match['result_status'] === 'published'
          );
          $query = trim((string)($_GET['q'] ?? ''));
          if ($query !== '') {
              $visible = array_filter($visible, static fn(array $match): bool =>
                  stripos($match['home_team_name'] . ' ' . $match['away_team_name'] . ' ' . ($match['round'] ?? ''), $query) !== false
              );
          }
      ?>
        <form class="listing-filter" method="get"><label class="sr-only" for="q">Search teams or rounds</label><input id="q" name="q" type="search" value="<?= e($query) ?>" placeholder="Search teams or rounds"><button class="button button-dark button-small">Search</button></form>
        <?php if ($visible): ?><div class="match-grid"><?php foreach ($visible as $match) echo match_card($match); ?></div><?php else: ?><div class="empty-state"><strong>No <?= $page === '/fixtures' ? 'fixtures' : 'published results' ?> found</strong><p>Try a different search or check back later.</p></div><?php endif; ?>
      <?php elseif ($page === '/standings'): ?>
        <div class="standings-panel"><div class="panel-top"><span><?= e($tournament['name']) ?></span><span>UPDATED AUTOMATICALLY</span></div><?php standings_table($data['standings']); ?></div>
      <?php elseif ($page === '/teams'): ?>
        <?php if ($data['teams']): ?><div class="team-grid"><?php foreach ($data['teams'] as $index => $team): ?><article class="team-card"><div class="team-card-top team-tone-<?= $index % 4 ?>"><span class="team-card-index"><?= sprintf('%02d', $index + 1) ?></span><?= team_badge($team) ?><span class="team-card-decoration"><?= sprintf('%02d', $index + 1) ?></span></div><div class="team-card-bottom"><div><h3><?= e($team['name']) ?></h3><span><?= e($team['group_name'] ?: ($team['coach'] ? 'Coach: ' . $team['coach'] : 'Tournament team')) ?></span></div></div></article><?php endforeach; ?></div><?php else: ?><div class="empty-state"><strong>No teams yet</strong><p>Teams will appear once they are added to this tournament.</p></div><?php endif; ?>
      <?php elseif ($page === '/players'): ?>
        <?php if ($data['players']): ?><div class="player-grid"><?php foreach ($data['players'] as $player): ?><article class="player-card"><div class="player-photo"><?php if ($player['photo_url']): ?><img src="<?= e($player['photo_url']) ?>" alt="<?= e($player['name']) ?>"><?php else: ?><span><?= e(strtoupper(substr($player['name'], 0, 1))) ?></span><?php endif; ?><span class="jersey"><?= e((string)($player['jersey_number'] ?? '—')) ?></span></div><div class="player-info"><div><span class="eyebrow"><?= e($player['team_short_name'] ?: $player['team_name']) ?></span><h3><?= e($player['name']) ?></h3><span><?= e($player['position'] ?: 'Player') ?></span></div></div></article><?php endforeach; ?></div><?php else: ?><div class="empty-state"><strong>No players yet</strong><p>Player profiles will appear here as teams are added.</p></div><?php endif; ?>
      <?php elseif ($page === '/stats'): ?>
        <div class="stat-overview"><article class="stat-tile"><span class="eyebrow">MATCHES PLAYED</span><strong><?= (int)$data['stats']['matchesPlayed'] ?></strong></article><article class="stat-tile"><span class="eyebrow">GOALS SCORED</span><strong><?= (int)$data['stats']['goalsScored'] ?></strong></article><article class="stat-tile"><span class="eyebrow">GOALS PER MATCH</span><strong><?= e((string)$data['stats']['averageGoalsPerMatch']) ?></strong></article><article class="stat-tile"><span class="eyebrow">TEAMS</span><strong><?= (int)$data['stats']['teamCount'] ?></strong></article></div>
      <?php elseif ($page === '/bracket'): ?>
        <?php $rounds = []; foreach ($matches as $match) { $rounds[$match['round'] ?: 'Upcoming'][$match['id']] = $match; } ?>
        <?php if ($rounds): ?><div class="bracket-wrap"><?php foreach ($rounds as $round => $roundMatches): ?><section><div class="bracket-round-heading"><span class="eyebrow"><?= e($round) ?></span><h2><?= e($round) ?></h2></div><?php foreach ($roundMatches as $match) echo match_card($match, true); ?></section><?php endforeach; ?></div><?php else: ?><div class="empty-state"><strong>Bracket coming soon</strong><p>Knockout fixtures will appear here when announced.</p></div><?php endif; ?>
      <?php elseif ($page === '/sponsors'): ?>
        <?php if ($data['sponsors']): ?><div class="sponsor-grid"><?php foreach ($data['sponsors'] as $sponsor): ?><article class="sponsor-card"><div class="sponsor-card-logo"><?php if ($sponsor['logo_url']): ?><img src="<?= e($sponsor['logo_url']) ?>" alt="<?= e($sponsor['name']) ?> logo"><?php else: ?><?= e($sponsor['name']) ?><?php endif; ?></div><span class="eyebrow"><?= e($sponsor['tier'] ?: 'OUR PARTNER') ?></span><h3><?= e($sponsor['name']) ?></h3><p><?= e($sponsor['description'] ?? '') ?></p><?php if ($sponsor['website_url']): ?><a href="<?= e($sponsor['website_url']) ?>" target="_blank" rel="noreferrer">Visit website →</a><?php endif; ?></article><?php endforeach; ?></div><?php else: ?><div class="empty-state"><strong>Partner announcements coming soon</strong><p>Our tournament partners will be listed here.</p></div><?php endif; ?>
      <?php endif; ?>
    </main>
    <?php
}
