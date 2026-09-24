<?php

declare(strict_types=1);

namespace CompetitionDemo\Application;

use CompetitionDemo\Domain\PredictionWindow;
use CompetitionDemo\Domain\Score;
use DateTimeImmutable;
use DomainException;

final readonly class PredictionService
{
    public function __construct(private CompetitionGateway $gateway)
    {
    }

    public function submit(int $competitionId, int $matchId, int $participantId, int $home, int $away, bool $wildcard, DateTimeImmutable $now): void
    {
        $context = $this->gateway->predictionContext($competitionId, $matchId, $participantId);
        if ($context === null) {
            throw new DomainException('Participant or match is not authorized for this competition.');
        }
        if ($context['status'] !== 'scheduled' || !PredictionWindow::isOpen($now, new DateTimeImmutable($context['closes_at']))) {
            throw new DomainException('Predictions are closed for this match.');
        }
        if ($wildcard && $context['wildcard_used_elsewhere']) {
            throw new DomainException('The wildcard has already been used in this competition.');
        }

        $this->gateway->savePrediction($competitionId, $matchId, $participantId, new Score($home, $away), $wildcard);
    }
}
