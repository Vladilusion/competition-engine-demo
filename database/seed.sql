USE competition_demo;

INSERT INTO competitions (id, name) VALUES (1, 'Northstar Cup 2026');
INSERT INTO participants (id, display_name) VALUES (1, 'Avery Stone'), (2, 'Mina Park'), (3, 'Theo Brooks'), (4, 'Zara Quinn');
INSERT INTO competition_participants (competition_id, participant_id) VALUES (1, 1), (1, 2), (1, 3), (1, 4);
INSERT INTO teams (id, name, code) VALUES
    (1, 'Harbor Rovers', 'HBR'), (2, 'Summit Athletic', 'SUM'), (3, 'Cedar United', 'CDR'), (4, 'Metro Falcons', 'MET');
INSERT INTO matches (id, competition_id, home_team_id, away_team_id, starts_at, closes_at, status, home_score, away_score, finalized_at) VALUES
    (1, 1, 1, 2, '2026-09-20 16:00:00', '2026-09-20 15:55:00', 'finalized', 2, 1, '2026-09-20 18:00:00'),
    (2, 1, 3, 4, '2026-09-22 18:00:00', '2026-09-22 17:55:00', 'finalized', 1, 1, '2026-09-22 20:00:00'),
    (3, 1, 1, 3, '2027-06-12 16:00:00', '2027-06-12 15:55:00', 'scheduled', NULL, NULL, NULL),
    (4, 1, 2, 4, '2027-06-14 18:00:00', '2027-06-14 17:55:00', 'scheduled', NULL, NULL, NULL);
INSERT INTO predictions (competition_id, match_id, participant_id, home_score, away_score, is_wildcard, awarded_points, award_reason, scored_at) VALUES
    (1, 1, 1, 2, 1, 0, 5, 'Exact score', '2026-09-20 18:00:00'),
    (1, 1, 2, 3, 2, 0, 3, 'Correct goal difference', '2026-09-20 18:00:00'),
    (1, 1, 3, 1, 0, 1, 3, 'Correct goal difference', '2026-09-20 18:00:00'),
    (1, 1, 4, 0, 1, 0, 0, 'Incorrect', '2026-09-20 18:00:00'),
    (1, 2, 1, 0, 0, 0, 2, 'Correct draw', '2026-09-22 20:00:00'),
    (1, 2, 2, 1, 1, 0, 5, 'Exact score', '2026-09-22 20:00:00'),
    (1, 2, 3, 2, 2, 0, 2, 'Correct draw', '2026-09-22 20:00:00'),
    (1, 2, 4, 2, 0, 0, 0, 'Incorrect', '2026-09-22 20:00:00'),
    (1, 3, 1, 2, 0, 0, NULL, NULL, NULL), (1, 3, 2, 1, 1, 0, NULL, NULL, NULL),
    (1, 3, 3, 1, 2, 0, NULL, NULL, NULL), (1, 3, 4, 3, 1, 0, NULL, NULL, NULL),
    (1, 4, 1, 1, 2, 0, NULL, NULL, NULL), (1, 4, 2, 2, 0, 0, NULL, NULL, NULL),
    (1, 4, 3, 0, 1, 0, NULL, NULL, NULL), (1, 4, 4, 2, 2, 0, NULL, NULL, NULL);
