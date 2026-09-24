<?php

declare(strict_types=1);

namespace CompetitionDemo\Application;

use CompetitionDemo\Domain\Score;
use DateTimeImmutable;

final readonly class ResultService
{
    public function __construct(private CompetitionGateway $gateway)
    {
    }

    public function finalize(int $competitionId, int $matchId, int $home, int $away, DateTimeImmutable $now): void
    {
        $this->gateway->finalizeMatch($competitionId, $matchId, new Score($home, $away), $now);
    }
}
