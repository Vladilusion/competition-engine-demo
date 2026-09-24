<?php

declare(strict_types=1);

namespace CompetitionDemo\Domain;

final readonly class ScoringRules
{
    public function __construct(
        public float $exact = 5,
        public float $goalDifference = 3,
        public float $winner = 2,
        public float $draw = 2,
        public float $wildcardMultiplier = 1.5,
    ) {
    }
}
