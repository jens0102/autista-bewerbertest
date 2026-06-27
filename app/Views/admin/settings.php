<div class="card">
    <div class="card-body">
        <?php if (!empty($_SESSION['admin_must_change_password'])): ?>
            <div class="alert alert-warning">Bitte ändern Sie das initiale Admin-Passwort, bevor Sie den Adminbereich weiter nutzen.</div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <div class="mb-3">
                <label>Titel</label>
                <input class="form-control" name="test_title" value="<?= h($testTitle) ?>">
            </div>
            <div class="mb-3">
                <label>Einleitungstext</label>
                <textarea class="form-control" name="intro_text" rows="3"><?= h($introText) ?></textarea>
            </div>
            <div class="mb-3">
                <label>Datenschutzhinweis</label>
                <textarea class="form-control" name="privacy_text" rows="4"><?= h($privacyText) ?></textarea>
            </div>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label>Dauer in Minuten</label>
                    <input class="form-control" type="number" name="duration_minutes" value="<?= h($durationMinutes) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label>Fragenanzahl</label>
                    <input class="form-control" type="number" name="question_limit" value="<?= h($questionLimit) ?>">
                    <div class="small-muted">0 = alle aktiven Fragen</div>
                </div>
                <div class="col-md-3 mb-3">
                    <label>Bestehensgrenze (%)</label>
                    <input class="form-control" type="number" step="0.1" name="pass_percent" value="<?= h($passPercent) ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label>Aufbewahrung (Tage)</label>
                    <input class="form-control" type="number" name="retention_days" value="<?= h($retentionDays) ?>">
                    <div class="small-muted">0 = keine automatische Löschung</div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label>Admin-Session-Timeout (Minuten)</label>
                    <input class="form-control" type="number" name="admin_session_timeout_minutes" value="<?= h($adminSessionTimeoutMinutes) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label>Fehlerdiagnose</label>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="debug_mode" id="debugMode" <?= $debugMode === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="debugMode">Debug-Meldungen anzeigen</label>
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label>Aktuelle Fragenversion</label>
                <input class="form-control" value="<?= h($questionVersion) ?>" readonly>
            </div>
            <div class="mb-3">
                <label>Neues Admin-Passwort</label>
                <input class="form-control" type="password" name="new_password">
            </div>
            <button class="btn btn-primary">Speichern</button>
            <hr>
            <button class="btn btn-danger" name="delete_attempts" value="1" onclick="return confirm('Alle Teilnahmen löschen?')">Alle Bewerberdaten löschen</button>
        </form>
    </div>
</div>
