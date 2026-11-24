<?php
declare(strict_types=1);

function one(PDO $pdo, string $sql, array $params = []): ?array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function all(PDO $pdo, string $sql, array $params = []): array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}

function public_data(PDO $pdo): array
{
    $tournaments = all(
        $pdo,
        "SELECT * FROM tournaments WHERE status IN ('published', 'completed') ORDER BY start_date DESC, created_at DESC"
    );
    $tournament = $tournaments[0] ?? null;
    if (!$tournament) {
        return [
            'tournament' => null, 'tournaments' => [], 'teams' => [], 'players' => [],
            'matches' => [], 'sponsors' => [], 'standings' => [], 'stats' => [],
        ];
    }

    $id = $tournament['id'];
    $teams = all($pdo, 'SELECT * FROM teams WHERE tournament_id = ? AND active = 1 ORDER BY name', [$id]);
    $players = all(
        $pdo,
        'SELECT p.*, t.name AS team_name, t.short_name AS team_short_name, t.logo_url AS team_logo_url
         FROM players p JOIN teams t ON t.id = p.team_id
         WHERE t.tournament_id = ? AND p.active = 1 AND t.active = 1 ORDER BY t.name, p.name',
        [$id]
    );
    $matches = all(
        $pdo,
        "SELECT m.*,
                ht.name AS home_team_name, ht.short_name AS home_team_short_name, ht.logo_url AS home_team_logo_url,
                at.name AS away_team_name, at.short_name AS away_team_short_name, at.logo_url AS away_team_logo_url,
                r.home_score, r.away_score, r.status AS result_status
         FROM matches m
         JOIN teams ht ON ht.id = m.home_team_id
         JOIN teams at ON at.id = m.away_team_id
         LEFT JOIN results r ON r.match_id = m.id AND r.status = 'published'
         WHERE m.tournament_id = ?
         ORDER BY m.kickoff_at ASC",
        [$id]
    );
    $sponsors = all(
        $pdo,
        'SELECT * FROM sponsors WHERE active = 1 AND (tournament_id = ? OR tournament_id IS NULL)
         ORDER BY tier, name',
        [$id]
    );
    $publishedResults = all(
        $pdo,
        "SELECT r.*, ht.name AS home_team_name, ht.short_name AS home_team_short_name,
                at.name AS away_team_name, at.short_name AS away_team_short_name
         FROM results r
         JOIN teams ht ON ht.id = r.home_team_id
         JOIN teams at ON at.id = r.away_team_id
         WHERE r.tournament_id = ? AND r.status = 'published'",
        [$id]
    );
    $standings = calculate_standings($teams, $publishedResults);
    $goals = array_sum(array_map(
        static fn(array $result): int => (int)$result['home_score'] + (int)$result['away_score'],
        $publishedResults
    ));
    $matchCount = count($publishedResults);

    return [
        'tournament' => $tournament,
        'tournaments' => $tournaments,
        'teams' => $teams,
        'players' => $players,
        'matches' => $matches,
        'sponsors' => $sponsors,
        'standings' => $standings,
        'stats' => [
            'matchesPlayed' => $matchCount,
            'goalsScored' => $goals,
            'averageGoalsPerMatch' => $matchCount ? round($goals / $matchCount, 2) : 0,
            'teamCount' => count($teams),
        ],
    ];
}

function calculate_standings(array $teams, array $results): array
{
    $rows = [];
    foreach ($teams as $team) {
        $rows[$team['id']] = [
            'team_id' => $team['id'], 'team_name' => $team['short_name'] ?: $team['name'],
            'played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0,
            'goals_for' => 0, 'goals_against' => 0, 'goal_difference' => 0, 'points' => 0,
        ];
    }
    foreach ($results as $result) {
        if ($result['status'] !== 'published') {
            continue;
        }
        $homeId = $result['home_team_id'];
        $awayId = $result['away_team_id'];
        $homeScore = filter_var($result['home_score'], FILTER_VALIDATE_INT);
        $awayScore = filter_var($result['away_score'], FILTER_VALIDATE_INT);
        if ($homeId === $awayId || $homeScore === false || $awayScore === false
            || $homeScore < 0 || $awayScore < 0) {
            continue;
        }
        foreach ([
            $homeId => $result['home_team_short_name'] ?: $result['home_team_name'],
            $awayId => $result['away_team_short_name'] ?: $result['away_team_name'],
        ] as $teamId => $teamName) {
            if (!isset($rows[$teamId])) {
                $rows[$teamId] = [
                    'team_id' => $teamId, 'team_name' => $teamName,
                    'played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0,
                    'goals_for' => 0, 'goals_against' => 0, 'goal_difference' => 0, 'points' => 0,
                ];
            }
        }
        $rows[$homeId]['played']++;
        $rows[$awayId]['played']++;
        $rows[$homeId]['goals_for'] += $homeScore;
        $rows[$homeId]['goals_against'] += $awayScore;
        $rows[$awayId]['goals_for'] += $awayScore;
        $rows[$awayId]['goals_against'] += $homeScore;
        if ($homeScore > $awayScore) {
            $rows[$homeId]['wins']++;
            $rows[$homeId]['points'] += 3;
            $rows[$awayId]['losses']++;
        } elseif ($homeScore < $awayScore) {
            $rows[$awayId]['wins']++;
            $rows[$awayId]['points'] += 3;
            $rows[$homeId]['losses']++;
        } else {
            $rows[$homeId]['draws']++;
            $rows[$awayId]['draws']++;
            $rows[$homeId]['points']++;
            $rows[$awayId]['points']++;
        }
    }
    foreach ($rows as &$row) {
        $row['goal_difference'] = $row['goals_for'] - $row['goals_against'];
    }
    unset($row);
    $rows = array_values($rows);
    usort($rows, static fn(array $a, array $b): int =>
        ($b['points'] <=> $a['points'])
        ?: ($b['goal_difference'] <=> $a['goal_difference'])
        ?: ($b['goals_for'] <=> $a['goals_for'])
        ?: strcasecmp($a['team_name'], $b['team_name'])
    );
    return $rows;
}

function admin_resources(): array
{
    return [
        'tournaments' => [
            'title' => 'Tournaments', 'table' => 'tournaments',
            'fields' => [
                'name' => ['Tournament name', 'text', true], 'edition' => ['Edition', 'text'],
                'description' => ['Description', 'textarea'], 'logo_url' => ['Tournament logo', 'url'],
                'banner_url' => ['Tournament banner', 'url'], 'venue' => ['Venue', 'text'],
                'start_date' => ['Start date', 'date'], 'end_date' => ['End date', 'date'],
                'format' => ['Format', 'select', true, ['league', 'knockout', 'group-stage', 'group-knockout']],
                'status' => ['Publication status', 'select', true, ['draft', 'published', 'completed']],
            ],
        ],
        'teams' => [
            'title' => 'Teams', 'table' => 'teams',
            'fields' => [
                'name' => ['Team name', 'text', true], 'short_name' => ['Short name', 'text'],
                'logo_url' => ['Team logo', 'url'], 'coach' => ['Coach', 'text'],
                'description' => ['Description', 'textarea'], 'group_name' => ['Group', 'text'],
                'tournament_id' => ['Tournament', 'relation', true, 'tournaments'],
                'active' => ['Active team', 'checkbox'],
            ],
        ],
        'players' => [
            'title' => 'Players', 'table' => 'players',
            'fields' => [
                'name' => ['Player name', 'text', true], 'jersey_number' => ['Jersey number', 'number'],
                'position' => ['Position', 'text'], 'photo_url' => ['Player photo', 'url'],
                'team_id' => ['Team', 'relation', true, 'teams'], 'active' => ['Active player', 'checkbox'],
            ],
        ],
        'matches' => [
            'title' => 'Fixtures', 'table' => 'matches',
            'fields' => [
                'tournament_id' => ['Tournament', 'relation', true, 'tournaments'],
                'home_team_id' => ['Home team', 'relation', true, 'teams'],
                'away_team_id' => ['Away team', 'relation', true, 'teams'],
                'kickoff_at' => ['Kick-off time', 'datetime-local', true], 'venue' => ['Venue', 'text'],
                'round' => ['Round / matchday', 'text'],
                'status' => ['Match status', 'select', true, ['scheduled', 'cancelled', 'completed']],
            ],
        ],
        'results' => [
            'title' => 'Results', 'table' => 'results',
            'fields' => [
                'match_id' => ['Fixture', 'relation', true, 'matches'],
                'home_score' => ['Home score', 'number', true], 'away_score' => ['Away score', 'number', true],
            ],
        ],
        'sponsors' => [
            'title' => 'Sponsors', 'table' => 'sponsors',
            'fields' => [
                'name' => ['Sponsor name', 'text', true], 'logo_url' => ['Sponsor logo', 'url'],
                'website_url' => ['Website URL', 'url'], 'tier' => ['Sponsor category', 'text'],
                'description' => ['Description', 'textarea'],
                'tournament_id' => ['Tournament (optional)', 'relation', false, 'tournaments'],
                'active' => ['Active sponsor', 'checkbox'],
            ],
        ],
        'users' => [
            'title' => 'Admin users', 'table' => 'users',
            'fields' => [
                'name' => ['Full name', 'text', true], 'email' => ['Email address', 'email', true],
                'role' => ['Role', 'select', true, ['Super Admin', 'Admin', 'Editor']],
                'active' => ['Active account', 'checkbox'],
                'password' => ['Password (at least 12 characters)', 'password', false],
            ],
        ],
    ];
}

function resource_rows(PDO $pdo, string $resource): array
{
    return match ($resource) {
        'teams' => all($pdo, 'SELECT t.*, tr.name AS tournament_name FROM teams t JOIN tournaments tr ON tr.id = t.tournament_id ORDER BY t.created_at DESC'),
        'players' => all($pdo, 'SELECT p.*, t.name AS team_name FROM players p JOIN teams t ON t.id = p.team_id ORDER BY p.created_at DESC'),
        'matches' => all($pdo, 'SELECT m.*, tr.name AS tournament_name, h.name AS home_team_name, a.name AS away_team_name FROM matches m JOIN tournaments tr ON tr.id = m.tournament_id JOIN teams h ON h.id = m.home_team_id JOIN teams a ON a.id = m.away_team_id ORDER BY m.kickoff_at DESC'),
        'results' => all($pdo, 'SELECT r.*, h.name AS home_team_name, a.name AS away_team_name, m.kickoff_at FROM results r JOIN teams h ON h.id = r.home_team_id JOIN teams a ON a.id = r.away_team_id JOIN matches m ON m.id = r.match_id ORDER BY r.created_at DESC'),
        'sponsors' => all($pdo, 'SELECT s.*, t.name AS tournament_name FROM sponsors s LEFT JOIN tournaments t ON t.id = s.tournament_id ORDER BY s.created_at DESC'),
        'users' => all($pdo, 'SELECT id, name, email, role, active, last_login_at, created_at FROM users ORDER BY created_at DESC'),
        default => all($pdo, 'SELECT * FROM tournaments ORDER BY created_at DESC'),
    };
}
