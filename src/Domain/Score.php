<?php

declare(strict_types=1);

namespace CompetitionDemo\Domain;

use InvalidArgumentException;

final readonly class Score
{
    public function __construct(public int $home, public int $away)
    {
        if ($home < 0 || $away < 0 || $home > 99 || $away > 99) {
            throw new InvalidArgumentException('Scores must be integers between 0 and 99.');
        }
    }

    public function difference(): int
    {
        return $this->home - $this->away;
    }

    public function outcome(): int
    {
        return $this->difference() <=> 0;
    }
}
