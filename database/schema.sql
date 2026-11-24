CREATE DATABASE IF NOT EXISTS neco_futsal
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE neco_futsal;

CREATE TABLE IF NOT EXISTS tournaments (
  id CHAR(36) NOT NULL PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  edition VARCHAR(40) NULL,
  description TEXT NULL,
  logo_url VARCHAR(2048) NULL,
  banner_url VARCHAR(2048) NULL,
  venue VARCHAR(200) NULL,
  format ENUM('league', 'knockout', 'group-stage', 'group-knockout') NOT NULL DEFAULT 'league',
  start_date DATE NULL,
  end_date DATE NULL,
  status ENUM('draft', 'published', 'completed') NOT NULL DEFAULT 'draft',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id CHAR(36) NOT NULL PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(254) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('Super Admin', 'Admin', 'Editor') NOT NULL DEFAULT 'Editor',
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS teams (
  id CHAR(36) NOT NULL PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  short_name VARCHAR(20) NULL,
  logo_url VARCHAR(2048) NULL,
  coach VARCHAR(120) NULL,
  description VARCHAR(1500) NULL,
  group_name VARCHAR(80) NULL,
  tournament_id CHAR(36) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_teams_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_teams_tournament_active (tournament_id, active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS players (
  id CHAR(36) NOT NULL PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  jersey_number TINYINT UNSIGNED NULL,
  position VARCHAR(40) NULL,
  photo_url VARCHAR(2048) NULL,
  team_id CHAR(36) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_players_team FOREIGN KEY (team_id) REFERENCES teams(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_players_team_active (team_id, active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS matches (
  id CHAR(36) NOT NULL PRIMARY KEY,
  tournament_id CHAR(36) NOT NULL,
  home_team_id CHAR(36) NOT NULL,
  away_team_id CHAR(36) NOT NULL,
  kickoff_at DATETIME NOT NULL,
  venue VARCHAR(200) NULL,
  round VARCHAR(80) NULL,
  status ENUM('scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_matches_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_matches_home_team FOREIGN KEY (home_team_id) REFERENCES teams(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_matches_away_team FOREIGN KEY (away_team_id) REFERENCES teams(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_matches_tournament_kickoff (tournament_id, kickoff_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS results (
  id CHAR(36) NOT NULL PRIMARY KEY,
  match_id CHAR(36) NOT NULL UNIQUE,
  tournament_id CHAR(36) NOT NULL,
  home_team_id CHAR(36) NOT NULL,
  away_team_id CHAR(36) NOT NULL,
  home_score TINYINT UNSIGNED NOT NULL,
  away_score TINYINT UNSIGNED NOT NULL,
  status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  created_by CHAR(36) NOT NULL,
  published_by CHAR(36) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_results_match FOREIGN KEY (match_id) REFERENCES matches(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_results_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_results_home_team FOREIGN KEY (home_team_id) REFERENCES teams(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_results_away_team FOREIGN KEY (away_team_id) REFERENCES teams(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_results_created_by FOREIGN KEY (created_by) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_results_published_by FOREIGN KEY (published_by) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_results_public (tournament_id, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sponsors (
  id CHAR(36) NOT NULL PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  logo_url VARCHAR(2048) NULL,
  website_url VARCHAR(2048) NULL,
  tier VARCHAR(60) NULL,
  description VARCHAR(1000) NULL,
  tournament_id CHAR(36) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_sponsors_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_sponsors_active (active, tournament_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
  id CHAR(36) NOT NULL PRIMARY KEY,
  actor_id CHAR(36) NULL,
  action VARCHAR(40) NOT NULL,
  resource VARCHAR(60) NOT NULL,
  resource_id VARCHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users(id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_audit_created_at (created_at)
) ENGINE=InnoDB;
