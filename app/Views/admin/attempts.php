<form method="get" class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Suche</label>
                <input class="form-control" name="q" value="<?= h($filters['q'] ?? '') ?>" placeholder="Name oder E-Mail">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">alle</option>
                    <?php foreach (['started' => 'gestartet', 'submitted' => 'abgegeben', 'expired' => 'abgelaufen'] as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= (($filters['status'] ?? '') === $value) ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Von</label>
                <input class="form-control" type="date" name="from" value="<?= h($filters['from'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Bis</label>
                <input class="form-control" type="date" name="to" value="<?= h($filters['to'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Entscheidung</label>
                <select class="form-select" name="decision">
                    <option value="">alle</option>
                    <?php foreach ($decisions as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= (($filters['decision'] ?? '') === $value) ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <button class="btn btn-primary w-100">Filtern</button>
            </div>
        </div>
    </div>
</form>

<div class="d-flex gap-2 flex-wrap mb-3">
    <a class="btn btn-sm btn-outline-secondary" href="<?= h(url('/admin/attempts?csv=1&' . http_build_query($filters))) ?>">CSV exportieren</a>
    <form method="post" class="m-0">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <button class="btn btn-sm btn-outline-danger" name="cleanup" value="1" onclick="return confirm('Abgelaufene Altdaten gemaess Aufbewahrungsfrist loeschen?')">Altdaten bereinigen</button>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-striped mb-0 align-middle">
            <tr>
                <th>Name</th>
                <th>E-Mail</th>
                <th>Status</th>
                <th>Entscheidung</th>
                <th>Ergebnis</th>
                <th>Start</th>
                <th>Abgabe</th>
                <th>Version</th>
                <th></th>
            </tr>
            <?php foreach ($rows as $attempt): ?>
                <?php
                $pct = $attempt['max_points'] ? round($attempt['total_points'] / $attempt['max_points'] * 100, 1) : 0;
                $passed = $attempt['status'] === 'submitted' && $pct >= $passPercent;
                $status = $attempt['status'] ?: 'started';
                ?>
                <tr>
                    <td><?= h($attempt['name']) ?></td>
                    <td><?= h($attempt['email']) ?></td>
                    <td><span class="status-pill status-<?= h($status) ?>"><?= h($status) ?></span></td>
                    <td><?= h($decisions[$attempt['review_decision'] ?? 'open'] ?? ($attempt['review_decision'] ?? 'open')) ?></td>
                    <td>
                        <div><?= h($attempt['total_points']) ?> / <?= h($attempt['max_points']) ?> (<?= h($pct) ?>%)</div>
                        <?php if ($attempt['status'] === 'submitted'): ?>
                            <span class="badge <?= $passed ? 'text-bg-success' : 'text-bg-warning' ?>"><?= $passed ? 'bestanden' : 'unter Grenze' ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= h($attempt['started_at']) ?></td>
                    <td class="small"><?= h($attempt['submitted_at']) ?></td>
                    <td class="small mono"><?= h($attempt['question_version'] ?? '') ?></td>
                    <td>
                        <div class="table-actions">
                            <a class="btn btn-sm btn-outline-primary" href="<?= h(url('/admin/attempt?id=' . $attempt['id'])) ?>">Details</a>
                            <form method="post" class="m-0">
                                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= h($attempt['id']) ?>">
                                <button class="btn btn-sm btn-outline-secondary" name="reset" value="1" onclick="return confirm('Teilnahme zuruecksetzen und E-Mail erneut freigeben?')">Reset</button>
                            </form>
                            <form method="post" class="m-0">
                                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= h($attempt['id']) ?>">
                                <button class="btn btn-sm btn-outline-danger" name="delete" value="1" onclick="return confirm('Teilnahme loeschen?')">Loeschen</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body">
        <form method="post" class="row g-2 align-items-end">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="release_email" value="1">
            <div class="col-md-8">
                <label class="form-label">E-Mail gezielt freigeben</label>
                <input class="form-control" type="email" name="email">
            </div>
            <div class="col-md-4">
                <button class="btn btn-outline-secondary w-100">Freigeben</button>
            </div>
        </form>
    </div>
</div>
