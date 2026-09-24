<?php

declare(strict_types=1);

namespace CompetitionDemo\Application;

use CompetitionDemo\Domain\Score;
use CompetitionDemo\Domain\ScoringEngine;

final readonly class SimulationService
{
    public function __construct(private CompetitionGateway $gateway, private ScoringEngine $scoring)
    {
    }

    /** Pure projection: reads one snapshot and never invokes a gateway write method. */
    public function project(int $competitionId, array $hypotheses): array
    {
        $snapshot = $this->gateway->snapshot($competitionId);
        $totals = [];
        foreach ($snapshot['participants'] as $participant) {
            $totals[$participant['id']] = ['id' => $participant['id'], 'name' => $participant['name'], 'points' => 0.0, 'exact_count' => 0, 'scored_count' => 0];
        }

        foreach ($snapshot['predictions'] as $prediction) {
            $matchId = $prediction['match_id'];
            $result = $hypotheses[$matchId] ?? $snapshot['results'][$matchId] ?? null;
            if ($result === null) {
                continue;
            }
            $award = $this->scoring->score(
                new Score((int) $prediction['home_score'], (int) $prediction['away_score']),
                new Score((int) $result['home'], (int) $result['away']),
                (bool) $prediction['is_wildcard'],
            );
            $row =& $totals[$prediction['participant_id']];
            $row['points'] += $award->points;
            $row['scored_count']++;
            $row['exact_count'] += $award->reason === 'Exact score' ? 1 : 0;
        }

        $ranking = array_values($totals);
        usort($ranking, static function (array $a, array $b): int {
            return ($b['points'] <=> $a['points'])
                ?: ($b['exact_count'] <=> $a['exact_count'])
                ?: ($a['name'] <=> $b['name'])
                ?: ($a['id'] <=> $b['id']);
        });
        foreach ($ranking as $index => &$row) {
            $row['position'] = $index + 1;
        }
        return $ranking;
    }
}
