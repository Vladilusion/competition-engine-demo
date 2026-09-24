<?php

declare(strict_types=1);

use CompetitionDemo\Application\PredictionService;
use CompetitionDemo\Application\ResultService;
use CompetitionDemo\Application\SimulationService;
use CompetitionDemo\Domain\ScoringEngine;

require dirname(__DIR__) . '/config/bootstrap.php';

$competitionId = 1;
$participantId = filter_input(INPUT_GET, 'participant', FILTER_VALIDATE_INT) ?: 1;
$notice = $_SESSION['notice'] ?? null;
unset($_SESSION['notice']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
            throw new DomainException('Security token expired. Refresh and try again.');
        }
        $action = (string) ($_POST['action'] ?? '');
        $matchId = filter_var($_POST['match_id'] ?? null, FILTER_VALIDATE_INT);
        $home = filter_var($_POST['home_score'] ?? null, FILTER_VALIDATE_INT);
        $away = filter_var($_POST['away_score'] ?? null, FILTER_VALIDATE_INT);
        if (!$matchId || $home === false || $away === false) {
            throw new DomainException('Enter valid whole-number scores.');
        }
        if ($action === 'predict') {
            (new PredictionService($gateway))->submit($competitionId, $matchId, $participantId, $home, $away, isset($_POST['wildcard']), new DateTimeImmutable());
            $_SESSION['notice'] = ['success', 'Prediction saved.'];
        } elseif ($action === 'finalize') {
            if (!hash_equals(env('ADMIN_TOKEN'), (string) ($_POST['admin_token'] ?? '')) || env('ADMIN_TOKEN') === '') {
                throw new DomainException('Administrative authorization failed.');
            }
            (new ResultService($gateway))->finalize($competitionId, $matchId, $home, $away, new DateTimeImmutable());
            $_SESSION['notice'] = ['success', 'Official result finalized and predictions scored.'];
        }
    } catch (Throwable $error) {
        $_SESSION['notice'] = ['error', $error->getMessage()];
    }
    header('Location: /?participant=' . $participantId);
    exit;
}

$participants = $gateway->participants($competitionId);
$matches = $gateway->matches($competitionId, $participantId);
$ranking = $gateway->ranking($competitionId);
$hypotheses = [];
$simulationError = null;
foreach ($matches as $match) {
    $homeKey = 'sim_home_' . $match['id'];
    $awayKey = 'sim_away_' . $match['id'];
    if ($match['status'] !== 'scheduled' || (!array_key_exists($homeKey, $_GET) && !array_key_exists($awayKey, $_GET))) {
        continue;
    }
    $range = ['options' => ['min_range' => 0, 'max_range' => 99]];
    $home = filter_input(INPUT_GET, $homeKey, FILTER_VALIDATE_INT, $range);
    $away = filter_input(INPUT_GET, $awayKey, FILTER_VALIDATE_INT, $range);
    if ($home === false || $home === null || $away === false || $away === null) {
        $simulationError = 'Simulation scores must be whole numbers between 0 and 99.';
        $hypotheses = [];
        break;
    }
    $hypotheses[$match['id']] = ['home' => $home, 'away' => $away];
}
$projected = [];
if ($hypotheses && $simulationError === null) {
    try {
        $projected = (new SimulationService($gateway, new ScoringEngine()))->project($competitionId, $hypotheses);
    } catch (InvalidArgumentException $error) {
        $simulationError = $error->getMessage();
    }
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Northstar Competition Engine</title>
    <link rel="stylesheet" href="/assets/app.css">
    <script src="/assets/app.js" defer></script>
</head>
<body>
<header class="topbar"><div class="wrap nav"><a class="brand" href="/"><span>N</span> Northstar Engine</a><nav><a href="#matches">Matches</a><a href="#ranking">Ranking</a><a href="#simulator">Simulator</a></nav></div></header>
<main class="wrap">
    <section class="hero"><div><p class="eyebrow">REFERENCE COMPETITION · 2026/27</p><h1>Every prediction.<br><em>Precisely scored.</em></h1><p>A transparent, deterministic competition engine built with PHP and MySQL.</p></div><div class="hero-stat"><strong><?= count($participants) ?></strong><span>Participants</span><strong><?= count(array_filter($matches, fn ($m) => $m['status'] === 'finalized')) ?>/<?= count($matches) ?></strong><span>Matches final</span></div></section>
    <?php if ($notice): ?><div class="notice <?= e($notice[0]) ?>" role="alert"><?= e($notice[1]) ?></div><?php endif; ?>
    <section id="matches"><div class="section-head"><div><p class="eyebrow">MATCH CENTER</p><h2>Fixtures & predictions</h2></div><form method="get"><label for="participant">Viewing as</label><select id="participant" name="participant" onchange="this.form.submit()"><?php foreach ($participants as $p): ?><option value="<?= e($p['id']) ?>" <?= $p['id'] == $participantId ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></form></div>
    <div class="match-grid"><?php foreach ($matches as $match): $open = $match['status'] === 'scheduled' && new DateTimeImmutable() < new DateTimeImmutable($match['closes_at']); ?>
        <article class="match-card"><div class="match-meta"><span class="badge <?= e($match['status']) ?>"><?= e($match['status']) ?></span><time><?= e((new DateTimeImmutable($match['starts_at']))->format('M j · H:i')) ?></time></div>
            <div class="fixture"><div><b><?= e($match['home_team']) ?></b><small>HOME</small></div><strong><?= $match['status'] === 'finalized' ? e($match['home_score'] . ' — ' . $match['away_score']) : 'VS' ?></strong><div><b><?= e($match['away_team']) ?></b><small>AWAY</small></div></div>
            <?php if ($match['status'] === 'finalized'): ?><p class="award">Your pick: <?= e($match['predicted_home'] ?? '–') ?>–<?= e($match['predicted_away'] ?? '–') ?> · <b><?= e($match['awarded_points'] ?? 0) ?> pts</b><br><small><?= e($match['award_reason'] ?? 'No prediction') ?></small></p>
            <?php else: ?><form method="post" class="score-form"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="predict"><input type="hidden" name="match_id" value="<?= e($match['id']) ?>"><label>Prediction <span><input aria-label="Home score" name="home_score" type="number" min="0" max="99" value="<?= e($match['predicted_home']) ?>" required> — <input aria-label="Away score" name="away_score" type="number" min="0" max="99" value="<?= e($match['predicted_away']) ?>" required></span></label><label class="check"><input type="checkbox" name="wildcard" <?= $match['is_wildcard'] ? 'checked' : '' ?>> 1.5× wildcard</label><button <?= !$open ? 'disabled' : '' ?>><?= $open ? 'Save prediction' : 'Predictions closed' ?></button><small>Closes <?= e((new DateTimeImmutable($match['closes_at']))->format('M j, H:i T')) ?></small></form><?php endif; ?>
        </article><?php endforeach; ?></div></section>
    <section id="ranking" class="panel"><div class="section-head"><div><p class="eyebrow">LIVE TABLE</p><h2>Official ranking</h2></div><span class="muted">Points · exact scores · name</span></div><?= renderRanking($ranking) ?></section>
    <section id="simulator" class="panel simulator"><div class="section-head"><div><p class="eyebrow">SANDBOX</p><h2>Scenario simulator</h2></div><span class="badge simulated">Non-persistent</span></div><p class="muted">Explore future outcomes. Projections are calculated in memory and never change official results or awarded points.</p><?php if ($simulationError): ?><div class="notice error" role="alert"><?= e($simulationError) ?></div><?php endif; ?><form method="get"><input type="hidden" name="participant" value="<?= e($participantId) ?>"><div class="sim-fields"><?php foreach ($matches as $match): if ($match['status'] !== 'scheduled') continue; ?><label><?= e($match['home_team']) ?> — <?= e($match['away_team']) ?><span><input type="number" min="0" max="99" name="sim_home_<?= e($match['id']) ?>" value="<?= e($hypotheses[$match['id']]['home'] ?? '') ?>" required> : <input type="number" min="0" max="99" name="sim_away_<?= e($match['id']) ?>" value="<?= e($hypotheses[$match['id']]['away'] ?? '') ?>" required></span></label><?php endforeach; ?></div><button>Run projection</button></form><?php if ($projected): ?><h3>Simulated ranking</h3><?= renderRanking($projected) ?><?php endif; ?></section>
    <details class="panel admin"><summary>Demo administration</summary><p>Finalize a scheduled match. Requires the local <code>ADMIN_TOKEN</code>.</p><form method="post" class="admin-form"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="finalize"><select name="match_id"><?php foreach ($matches as $m): if ($m['status'] === 'scheduled'): ?><option value="<?= e($m['id']) ?>"><?= e($m['home_team'] . ' vs ' . $m['away_team']) ?></option><?php endif; endforeach; ?></select><input name="home_score" type="number" min="0" max="99" placeholder="Home" required><input name="away_score" type="number" min="0" max="99" placeholder="Away" required><input name="admin_token" type="password" placeholder="Admin token" required><button>Finalize result</button></form></details>
</main><footer><div class="wrap">Independent portfolio reference implementation · Synthetic data only</div></footer>
</body></html>
<?php function renderRanking(array $rows): string { ob_start(); ?><div class="table-wrap"><table><thead><tr><th>Pos</th><th>Participant</th><th>Points</th><th>Exact</th><th>Scored</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><b>#<?= e($row['position']) ?></b></td><td><?= e($row['name']) ?></td><td><strong><?= e(number_format((float) $row['points'], 1)) ?></strong></td><td><?= e($row['exact_count']) ?></td><td><?= e($row['scored_count']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php return (string) ob_get_clean(); }
