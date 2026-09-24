<?php

declare(strict_types=1);

namespace CompetitionDemo\Domain;

final readonly class ScoreAward
{
    public function __construct(public float $points, public string $reason)
    {
    }
}
