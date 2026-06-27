<?php
$pct = $attempt['max_points'] ? round($attempt['total_points'] / $attempt['max_points'] * 100, 1) : 0;
$passed = $attempt['status'] === 'submitted' && $pct >= $passPercent;
?>
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between gap-3">
            <div>
                <h2 class="h5"><?= h($attempt['name']) ?> · <?= h($attempt['email']) ?></h2>
                <p class="mb-1"><strong>Status:</strong> <?= h($attempt['status']) ?></p>
                <p class="mb-1"><strong>Entscheidung:</strong> <?= h($decisions[$attempt['review_decision'] ?? 'open'] ?? ($attempt['review_decision'] ?? 'open')) ?></p>
                <p class="mb-1"><strong>Fragenversion:</strong> <?= h($attempt['question_version'] ?? '') ?></p>
                <p class="mb-0">
                    <strong>Gesamt:</strong> <?= h($attempt['total_points']) ?> / <?= h($attempt['max_points']) ?> Punkte (<?= h($pct) ?>%)
                    <?php if ($attempt['status'] === 'submitted'): ?>
                        <span class="badge <?= $passed ? 'text-bg-success' : 'text-bg-warning' ?>"><?= $passed ? 'bestanden' : 'unter Grenze' ?></span>
                    <?php endif; ?>
                </p>
            </div>
            <button class="btn btn-outline-secondary align-self-start" onclick="window.print()">Drucken</button>
        </div>
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <h2 class="h5">Review</h2>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <div class="mb-3">
                <label class="form-label">Entscheidung</label>
                <select class="form-select" name="review_decision">
                    <?php foreach ($decisions as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= (($attempt['review_decision'] ?? 'open') === $value) ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Admin-Notiz</label>
                <textarea class="form-control" name="admin_note" rows="4"><?= h($attempt['admin_note'] ?? '') ?></textarea>
            </div>
            <?php if (!empty($attempt['reviewed_at'])): ?>
                <p class="small-muted">Zuletzt geprüft: <?= h($attempt['reviewed_at']) ?></p>
            <?php endif; ?>
            <button class="btn btn-primary">Review speichern</button>
        </form>
    </div>
</div>
<h2 class="h5">Kompetenzbereiche</h2>
<table class="table table-sm">
    <?php foreach ($byCategory as $category => $values): ?>
        <?php $categoryPct = $values['m'] ? round($values['s'] / $values['m'] * 100, 1) : 0; ?>
        <tr>
            <td><?= h($category) ?></td>
            <td><?= h($values['s']) ?> / <?= h($values['m']) ?></td>
            <td style="width:40%">
                <div class="progress">
                    <div class="progress-bar" style="width:<?= h($categoryPct) ?>%"><?= h($categoryPct) ?>%</div>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
<?php foreach ($questions as $i => $q): ?>
    <?php
    $answer = $answers[$q['id']] ?? ['given_answers' => '[]', 'score' => 0];
    $given = json_decode($answer['given_answers'], true) ?: [];
    $correct = json_decode($q['correct_answers'], true) ?: [];
    $ok = ((float)$answer['score'] >= (float)$q['points']);
    $partial = ((float)$answer['score'] > 0 && !$ok);
    ?>
    <div class="card mb-3 <?= $ok ? 'answer-ok' : ($partial ? '' : 'answer-bad') ?>">
        <div class="card-body">
            <div class="small-muted"><?= h($q['category']) ?> · Frage <?= h($i + 1) ?> · <?= h($q['type']) ?></div>
            <h3 class="h6"><?= h($q['question']) ?></h3>
            <p><strong>Bewerberantwort:</strong> <?= h(answer_label($q, $given) ?: '—') ?></p>
            <p><strong>Musterantwort:</strong> <?= h(answer_label($q, $correct)) ?></p>
            <p><strong>Bewertung:</strong> <?= h($answer['score']) ?> / <?= h($q['points']) ?> Punkt<?= ((float)$q['points'] == 1.0) ? '' : 'e' ?></p>
            <?php if (trim((string)($q['source_hint'] ?? '')) !== ''): ?>
                <p><strong>Quellenhinweis:</strong> <?= h($q['source_hint']) ?></p>
            <?php endif; ?>
            <p><strong>Erläuterung:</strong> <?= h($q['explanation']) ?></p>
        </div>
    </div>
<?php endforeach; ?>
