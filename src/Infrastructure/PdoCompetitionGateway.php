<?php

declare(strict_types=1);

namespace CompetitionDemo\Infrastructure;

use CompetitionDemo\Application\CompetitionGateway;
use CompetitionDemo\Domain\Score;
use CompetitionDemo\Domain\ScoringEngine;
use DateTimeImmutable;
use DomainException;
use PDO;
use PDOException;
use Throwable;

final readonly class PdoCompetitionGateway implements CompetitionGateway
{
    public function __construct(private PDO $pdo, private ScoringEngine $scoring)
    {
    }

    public function predictionContext(int $competitionId, int $matchId, int $participantId): ?array
    {
        $sql = 'SELECT m.status, m.closes_at,
                       EXISTS(SELECT 1 FROM predictions wp WHERE wp.competition_id = m.competition_id AND wp.participant_id = cp.participant_id AND wp.is_wildcard = 1 AND wp.match_id <> m.id) wildcard_used_elsewhere
                FROM matches m
                JOIN competition_participants cp ON cp.competition_id = m.competition_id AND cp.participant_id = :participant_id
                WHERE m.id = :match_id AND m.competition_id = :competition_id';
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['competition_id' => $competitionId, 'match_id' => $matchId, 'participant_id' => $participantId]);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    public function savePrediction(int $competitionId, int $matchId, int $participantId, Score $score, bool $wildcard): void
    {
        $this->pdo->beginTransaction();
        try {
            // This membership row is the per-participant/competition serialization point.
            // Locking it prevents concurrent requests from both passing the wildcard check.
            $membership = $this->pdo->prepare('SELECT participant_id FROM competition_participants WHERE competition_id = :competition_id AND participant_id = :participant_id FOR UPDATE');
            $membership->execute(['competition_id' => $competitionId, 'participant_id' => $participantId]);
            if ($membership->fetchColumn() === false) {
                throw new DomainException('Participant is not authorized for this competition.');
            }

            $lock = $this->pdo->prepare('SELECT status, closes_at FROM matches WHERE id = :match_id AND competition_id = :competition_id FOR UPDATE');
            $lock->execute(['match_id' => $matchId, 'competition_id' => $competitionId]);
            $match = $lock->fetch();
            if (!$match || $match['status'] !== 'scheduled' || new DateTimeImmutable() >= new DateTimeImmutable($match['closes_at'])) {
                throw new DomainException('Predictions are closed for this match.');
            }

            if ($wildcard) {
                $wildcardCheck = $this->pdo->prepare('SELECT id FROM predictions WHERE competition_id = :competition_id AND participant_id = :participant_id AND is_wildcard = 1 AND match_id <> :match_id FOR UPDATE');
                $wildcardCheck->execute(['competition_id' => $competitionId, 'participant_id' => $participantId, 'match_id' => $matchId]);
                if ($wildcardCheck->fetchColumn() !== false) {
                    throw new DomainException('The wildcard has already been used in this competition.');
                }
            }

            $existing = $this->pdo->prepare('SELECT id FROM predictions WHERE match_id = :match_id AND participant_id = :participant_id FOR UPDATE');
            $existing->execute(['match_id' => $matchId, 'participant_id' => $participantId]);
            $predictionId = $existing->fetchColumn();
            if ($predictionId !== false) {
                $statement = $this->pdo->prepare('UPDATE predictions SET home_score = :home_score, away_score = :away_score, is_wildcard = :is_wildcard, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
                $statement->execute(['home_score' => $score->home, 'away_score' => $score->away, 'is_wildcard' => (int) $wildcard, 'id' => $predictionId]);
            } else {
                $statement = $this->pdo->prepare('INSERT INTO predictions (competition_id, match_id, participant_id, home_score, away_score, is_wildcard) VALUES (:competition_id, :match_id, :participant_id, :home_score, :away_score, :is_wildcard)');
                $statement->execute([
                    'competition_id' => $competitionId, 'match_id' => $matchId, 'participant_id' => $participantId,
                    'home_score' => $score->home, 'away_score' => $score->away, 'is_wildcard' => (int) $wildcard,
                ]);
            }
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($error instanceof PDOException && $error->getCode() === '23000') {
                throw new DomainException('Prediction conflicts with an existing competition entry.', 0, $error);
            }
            throw $error;
        }
    }

    public function finalizeMatch(int $competitionId, int $matchId, Score $result, DateTimeImmutable $now): void
    {
        $this->pdo->beginTransaction();
        try {
            $lock = $this->pdo->prepare('SELECT status FROM matches WHERE id = :match_id AND competition_id = :competition_id FOR UPDATE');
            $lock->execute(['match_id' => $matchId, 'competition_id' => $competitionId]);
            $status = $lock->fetchColumn();
            if ($status === false) {
                throw new DomainException('Match not found in this competition.');
            }
            if ($status !== 'scheduled') {
                throw new DomainException('This match is already finalized; official results cannot be replaced.');
            }
            $update = $this->pdo->prepare("UPDATE matches SET status = 'finalized', home_score = :home, away_score = :away, finalized_at = :finalized_at WHERE id = :match_id AND competition_id = :competition_id");
            $update->execute(['home' => $result->home, 'away' => $result->away, 'finalized_at' => $now->format('Y-m-d H:i:s'), 'match_id' => $matchId, 'competition_id' => $competitionId]);

            $predictions = $this->pdo->prepare('SELECT id, home_score, away_score, is_wildcard FROM predictions WHERE match_id = :match_id AND competition_id = :competition_id FOR UPDATE');
            $predictions->execute(['match_id' => $matchId, 'competition_id' => $competitionId]);
            $award = $this->pdo->prepare('UPDATE predictions SET awarded_points = :points, award_reason = :reason, scored_at = :scored_at WHERE id = :id');
            foreach ($predictions->fetchAll() as $prediction) {
                $score = $this->scoring->score(new Score((int) $prediction['home_score'], (int) $prediction['away_score']), $result, (bool) $prediction['is_wildcard']);
                $award->execute(['points' => $score->points, 'reason' => $score->reason, 'scored_at' => $now->format('Y-m-d H:i:s'), 'id' => $prediction['id']]);
            }
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function matches(int $competitionId, int $participantId): array
    {
        $statement = $this->pdo->prepare('SELECT m.*, ht.name home_team, at.name away_team, p.home_score predicted_home, p.away_score predicted_away, p.is_wildcard, p.awarded_points, p.award_reason FROM matches m JOIN teams ht ON ht.id = m.home_team_id JOIN teams at ON at.id = m.away_team_id LEFT JOIN predictions p ON p.match_id = m.id AND p.participant_id = :participant_id WHERE m.competition_id = :competition_id ORDER BY m.starts_at');
        $statement->execute(['competition_id' => $competitionId, 'participant_id' => $participantId]);
        return $statement->fetchAll();
    }

    public function participants(int $competitionId): array
    {
        $statement = $this->pdo->prepare('SELECT p.id, p.display_name name FROM participants p JOIN competition_participants cp ON cp.participant_id = p.id WHERE cp.competition_id = :competition_id ORDER BY p.display_name');
        $statement->execute(['competition_id' => $competitionId]);
        return $statement->fetchAll();
    }

    public function ranking(int $competitionId): array
    {
        $sql = 'WITH totals AS (SELECT p.id, p.display_name name, COALESCE(SUM(pr.awarded_points), 0) points, COALESCE(SUM(pr.award_reason = \'Exact score\'), 0) exact_count, COUNT(pr.scored_at) scored_count FROM participants p JOIN competition_participants cp ON cp.participant_id = p.id LEFT JOIN predictions pr ON pr.participant_id = p.id AND pr.competition_id = cp.competition_id WHERE cp.competition_id = :competition_id GROUP BY p.id, p.display_name) SELECT ROW_NUMBER() OVER (ORDER BY points DESC, exact_count DESC, name ASC, id ASC) position, id, name, points, exact_count, scored_count FROM totals ORDER BY position';
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['competition_id' => $competitionId]);
        return $statement->fetchAll();
    }

    public function snapshot(int $competitionId): array
    {
        $participants = $this->participants($competitionId);
        $statement = $this->pdo->prepare('SELECT participant_id, match_id, home_score, away_score, is_wildcard FROM predictions WHERE competition_id = :competition_id');
        $statement->execute(['competition_id' => $competitionId]);
        $predictions = $statement->fetchAll();
        $resultsStatement = $this->pdo->prepare("SELECT id, home_score home, away_score away FROM matches WHERE competition_id = :competition_id AND status = 'finalized'");
        $resultsStatement->execute(['competition_id' => $competitionId]);
        $results = [];
        foreach ($resultsStatement->fetchAll() as $result) {
            $results[$result['id']] = $result;
        }
        return compact('participants', 'predictions', 'results');
    }
}
