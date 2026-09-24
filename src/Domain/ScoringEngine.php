<?php

declare(strict_types=1);

namespace CompetitionDemo\Domain;

final readonly class ScoringEngine
{
    public function __construct(private ScoringRules $rules = new ScoringRules())
    {
    }

    /** Precedence: exact score, draw, exact goal difference, winner, otherwise zero. */
    public function score(Score $prediction, Score $result, bool $wildcard = false): ScoreAward
    {
        if ($prediction == $result) {
            $award = new ScoreAward($this->rules->exact, 'Exact score');
        } elseif ($prediction->outcome() === 0 && $result->outcome() === 0) {
            $award = new ScoreAward($this->rules->draw, 'Correct draw');
        } elseif ($prediction->difference() === $result->difference()) {
            $award = new ScoreAward($this->rules->goalDifference, 'Correct goal difference');
        } elseif ($prediction->outcome() === $result->outcome()) {
            $award = new ScoreAward($this->rules->winner, 'Correct winner');
        } else {
            $award = new ScoreAward(0, 'Incorrect');
        }

        return new ScoreAward($wildcard ? $award->points * $this->rules->wildcardMultiplier : $award->points, $award->reason);
    }
}
