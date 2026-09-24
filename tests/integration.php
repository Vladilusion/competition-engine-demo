<?php

declare(strict_types=1);

use CompetitionDemo\Domain\Score;
use CompetitionDemo\Domain\ScoringEngine;
use CompetitionDemo\Infrastructure\PdoCompetitionGateway;

require dirname(__DIR__) . '/src/autoload.php';

$pdo = new PDO(
    getenv('DB_DSN') ?: 'mysql:host=127.0.0.1;port=3306;dbname=competition_demo;charset=utf8mb4',
    getenv('DB_USER') ?: 'competition',
    getenv('DB_PASSWORD') ?: 'competition',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false],
);
$gateway = new PdoCompetitionGateway($pdo, new ScoringEngine());
$failures = 0;

function verify(string $name, callable $check): void
{
    global $failures;
    try {
        $check();
        echo "PASS {$name}\n";
    } catch (Throwable $error) {
        $failures++;
        echo "FAIL {$name}: {$error->getMessage()}\n";
    }
}

function expectDomainException(callable $operation, string $messageFragment): void
{
    try {
        $operation();
    } catch (DomainException $error) {
        if (!str_contains($error->getMessage(), $messageFragment)) {
            throw new RuntimeException("Unexpected domain error: {$error->getMessage()}");
        }
        return;
    }
    throw new RuntimeException('Expected a DomainException.');
}

// Keep fixture deadlines authoritative while making this disposable smoke test time-independent.
$pdo->exec("UPDATE matches SET starts_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 2 DAY), closes_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY) WHERE id IN (3, 4) AND status = 'scheduled'");

verify('ranking query executes against seeded data', function () use ($gateway): void {
    $ranking = $gateway->ranking(1);
    if (count($ranking) !== 4 || (int) $ranking[0]['position'] !== 1) {
        throw new RuntimeException('Seeded ranking was not returned.');
    }
});

verify('prediction is explicitly created and then updated', function () use ($pdo, $gateway): void {
    $pdo->exec('DELETE FROM predictions WHERE match_id = 4 AND participant_id = 4');
    $gateway->savePrediction(1, 4, 4, new Score(1, 0), false);
    $gateway->savePrediction(1, 4, 4, new Score(2, 0), false);
    $statement = $pdo->query('SELECT home_score, away_score FROM predictions WHERE match_id = 4 AND participant_id = 4');
    $prediction = $statement->fetch();
    if (!$prediction || (int) $prediction['home_score'] !== 2 || (int) $prediction['away_score'] !== 0) {
        throw new RuntimeException('Exact prediction row was not updated.');
    }
});

verify('second wildcard is rejected without mutating either prediction', function () use ($pdo, $gateway): void {
    $gateway->savePrediction(1, 3, 1, new Score(2, 0), true);
    $before = $pdo->query('SELECT match_id, home_score, away_score, is_wildcard FROM predictions WHERE participant_id = 1 AND match_id IN (3, 4) ORDER BY match_id')->fetchAll();
    expectDomainException(fn () => $gateway->savePrediction(1, 4, 1, new Score(9, 9), true), 'wildcard has already been used');
    $after = $pdo->query('SELECT match_id, home_score, away_score, is_wildcard FROM predictions WHERE participant_id = 1 AND match_id IN (3, 4) ORDER BY match_id')->fetchAll();
    if ($before !== $after || (int) $after[0]['is_wildcard'] !== 1 || (int) $after[1]['is_wildcard'] !== 0) {
        throw new RuntimeException('A prediction changed after the rejected wildcard write.');
    }
});

verify('official result finalization is one-way', function () use ($pdo, $gateway): void {
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $gateway->finalizeMatch(1, 3, new Score(2, 1), $now);
    expectDomainException(fn () => $gateway->finalizeMatch(1, 3, new Score(8, 8), $now), 'already finalized');
    $result = $pdo->query('SELECT status, home_score, away_score FROM matches WHERE id = 3')->fetch();
    if (!$result || $result['status'] !== 'finalized' || (int) $result['home_score'] !== 2 || (int) $result['away_score'] !== 1) {
        throw new RuntimeException('Finalized result was unexpectedly changed.');
    }
});

echo sprintf("\n4 integration checks, %d failures\n", $failures);
exit($failures === 0 ? 0 : 1);
