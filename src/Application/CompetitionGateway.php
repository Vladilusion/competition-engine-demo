<?php

declare(strict_types=1);

namespace CompetitionDemo\Application;

use CompetitionDemo\Domain\Score;
use DateTimeImmutable;

interface CompetitionGateway
{
    public function predictionContext(int $competitionId, int $matchId, int $participantId): ?array;
    public function savePrediction(int $competitionId, int $matchId, int $participantId, Score $score, bool $wildcard): void;
    public function finalizeMatch(int $competitionId, int $matchId, Score $result, DateTimeImmutable $now): void;
    public function snapshot(int $competitionId): array;
}
