<?php

declare(strict_types=1);

use CompetitionDemo\Application\CompetitionGateway;
use CompetitionDemo\Application\PredictionService;
use CompetitionDemo\Application\SimulationService;
use CompetitionDemo\Domain\Score;
use CompetitionDemo\Domain\ScoringEngine;
require dirname(__DIR__) . '/src/autoload.php';

final class MemoryGateway implements CompetitionGateway
{
    public int $writes = 0;
    public array $context = ['status' => 'scheduled', 'closes_at' => '2030-01-01T12:00:00+00:00', 'wildcard_used_elsewhere' => false];
    public array $snapshotData = ['participants' => [['id' => 1, 'name' => 'Avery']], 'predictions' => [['participant_id' => 1, 'match_id' => 7, 'home_score' => 2, 'away_score' => 1, 'is_wildcard' => false]], 'results' => []];
    public function predictionContext(int $competitionId, int $matchId, int $participantId): ?array { return $this->context; }
    public function savePrediction(int $competitionId, int $matchId, int $participantId, Score $score, bool $wildcard): void { $this->writes++; }
    public function finalizeMatch(int $competitionId, int $matchId, Score $result, DateTimeImmutable $now): void { $this->writes++; }
    public function snapshot(int $competitionId): array { return $this->snapshotData; }
}

$tests = [];
function test(string $name, Closure $test): void { global $tests; $tests[$name] = $test; }
function assertSameValue(mixed $expected, mixed $actual): void { if ($expected !== $actual) throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)); }
function assertThrows(Closure $operation): void { try { $operation(); } catch (DomainException) { return; } throw new RuntimeException('Expected DomainException.'); }

$engine = new ScoringEngine();
test('exact score', fn () => assertSameValue(5.0, $engine->score(new Score(2, 1), new Score(2, 1))->points));
test('goal difference', fn () => assertSameValue(3.0, $engine->score(new Score(3, 1), new Score(2, 0))->points));
test('winner', fn () => assertSameValue(2.0, $engine->score(new Score(3, 0), new Score(2, 1))->points));
test('draw', fn () => assertSameValue(2.0, $engine->score(new Score(2, 2), new Score(1, 1))->points));
test('incorrect', fn () => assertSameValue(0.0, $engine->score(new Score(0, 2), new Score(1, 0))->points));
test('wildcard multiplier', fn () => assertSameValue(7.5, $engine->score(new Score(2, 1), new Score(2, 1), true)->points));
test('exact takes precedence over goal difference and winner', fn () => assertSameValue('Exact score', $engine->score(new Score(2, 1), new Score(2, 1))->reason));
test('draw category takes precedence over zero goal difference', fn () => assertSameValue('Correct draw', $engine->score(new Score(2, 2), new Score(1, 1))->reason));
test('goal difference takes precedence over winner', fn () => assertSameValue('Correct goal difference', $engine->score(new Score(3, 1), new Score(2, 0))->reason));
test('prediction accepted before close', function (): void { $g = new MemoryGateway(); (new PredictionService($g))->submit(1, 7, 1, 1, 0, false, new DateTimeImmutable('2030-01-01T11:59:59+00:00')); assertSameValue(1, $g->writes); });
test('prediction rejected at close', function (): void { $g = new MemoryGateway(); assertThrows(fn () => (new PredictionService($g))->submit(1, 7, 1, 1, 0, false, new DateTimeImmutable('2030-01-01T12:00:00+00:00'))); assertSameValue(0, $g->writes); });
test('prediction rejected after close', function (): void { $g = new MemoryGateway(); assertThrows(fn () => (new PredictionService($g))->submit(1, 7, 1, 1, 0, false, new DateTimeImmutable('2030-01-01T12:00:01+00:00'))); assertSameValue(0, $g->writes); });
test('simulation does not persist official changes', function () use ($engine): void { $g = new MemoryGateway(); $before = $g->snapshotData; $ranking = (new SimulationService($g, $engine))->project(1, [7 => ['home' => 2, 'away' => 1]]); assertSameValue(5.0, $ranking[0]['points']); assertSameValue(0, $g->writes); assertSameValue($before, $g->snapshotData); });

$failures = 0;
foreach ($tests as $name => $test) {
    try { $test(); echo "\033[32mPASS\033[0m {$name}\n"; } catch (Throwable $error) { $failures++; echo "\033[31mFAIL\033[0m {$name}: {$error->getMessage()}\n"; }
}
echo sprintf("\n%d tests, %d failures\n", count($tests), $failures);
exit($failures === 0 ? 0 : 1);
