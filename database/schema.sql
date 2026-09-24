CREATE DATABASE IF NOT EXISTS competition_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE competition_demo;

CREATE TABLE competitions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE participants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    display_name VARCHAR(80) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_participant_name (display_name)
) ENGINE=InnoDB;

CREATE TABLE competition_participants (
    competition_id BIGINT UNSIGNED NOT NULL,
    participant_id BIGINT UNSIGNED NOT NULL,
    joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (competition_id, participant_id),
    CONSTRAINT fk_cp_competition FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    CONSTRAINT fk_cp_participant FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE teams (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    code CHAR(3) NOT NULL,
    UNIQUE KEY uq_team_name (name),
    UNIQUE KEY uq_team_code (code)
) ENGINE=InnoDB;

CREATE TABLE matches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    competition_id BIGINT UNSIGNED NOT NULL,
    home_team_id BIGINT UNSIGNED NOT NULL,
    away_team_id BIGINT UNSIGNED NOT NULL,
    starts_at DATETIME NOT NULL,
    closes_at DATETIME NOT NULL,
    status ENUM('scheduled', 'finalized') NOT NULL DEFAULT 'scheduled',
    home_score TINYINT UNSIGNED NULL,
    away_score TINYINT UNSIGNED NULL,
    finalized_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_distinct_teams CHECK (home_team_id <> away_team_id),
    CONSTRAINT chk_result_complete CHECK ((status = 'scheduled' AND home_score IS NULL AND away_score IS NULL AND finalized_at IS NULL) OR (status = 'finalized' AND home_score IS NOT NULL AND away_score IS NOT NULL AND finalized_at IS NOT NULL)),
    CONSTRAINT chk_close_before_start CHECK (closes_at <= starts_at),
    CONSTRAINT fk_match_competition FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    CONSTRAINT fk_match_home FOREIGN KEY (home_team_id) REFERENCES teams(id),
    CONSTRAINT fk_match_away FOREIGN KEY (away_team_id) REFERENCES teams(id),
    UNIQUE KEY uq_match_competition (id, competition_id),
    INDEX idx_matches_competition_status_start (competition_id, status, starts_at)
) ENGINE=InnoDB;

CREATE TABLE predictions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    competition_id BIGINT UNSIGNED NOT NULL,
    match_id BIGINT UNSIGNED NOT NULL,
    participant_id BIGINT UNSIGNED NOT NULL,
    home_score TINYINT UNSIGNED NOT NULL,
    away_score TINYINT UNSIGNED NOT NULL,
    is_wildcard BOOLEAN NOT NULL DEFAULT FALSE,
    wildcard_slot TINYINT GENERATED ALWAYS AS (IF(is_wildcard, 1, NULL)) STORED,
    awarded_points DECIMAL(6,2) NULL,
    award_reason VARCHAR(40) NULL,
    scored_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_prediction (match_id, participant_id),
    UNIQUE KEY uq_one_wildcard (competition_id, participant_id, wildcard_slot),
    CONSTRAINT chk_score_state CHECK ((awarded_points IS NULL AND award_reason IS NULL AND scored_at IS NULL) OR (awarded_points IS NOT NULL AND award_reason IS NOT NULL AND scored_at IS NOT NULL)),
    CONSTRAINT fk_prediction_match_context FOREIGN KEY (match_id, competition_id) REFERENCES matches(id, competition_id) ON DELETE CASCADE,
    CONSTRAINT fk_prediction_membership FOREIGN KEY (competition_id, participant_id) REFERENCES competition_participants(competition_id, participant_id) ON DELETE CASCADE,
    INDEX idx_predictions_ranking (competition_id, participant_id, awarded_points)
) ENGINE=InnoDB;
