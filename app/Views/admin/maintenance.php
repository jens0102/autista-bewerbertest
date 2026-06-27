<?php if (!empty($message)): ?>
    <div class="alert alert-info"><?= h($message) ?></div>
<?php endif; ?>
<div class="row g-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h5">Backup</h2>
                <p class="small-muted">SQLite-Datenbank: <span class="mono"><?= h($dbPath) ?></span></p>
                <a class="btn btn-primary" href="<?= h(url('/admin/backup/database')) ?>">Datenbank sichern</a>
                <a class="btn btn-outline-secondary" href="<?= h(url('/admin/export/questions')) ?>">Fragen exportieren</a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h5">Logdatei</h2>
                <p class="small-muted">Pfad: <span class="mono"><?= h($logPath) ?></span></p>
                <a class="btn btn-outline-secondary" href="<?= h(url('/admin/log/download')) ?>">Log herunterladen</a>
                <form method="post" class="d-inline">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <button class="btn btn-outline-danger" name="clear_log" value="1" onclick="return confirm('Logdatei wirklich leeren?')">Log leeren</button>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="card mt-3">
    <div class="card-body">
        <h2 class="h5">Letzte Backups</h2>
        <?php if (!$backups): ?>
            <p class="small-muted mb-0">Noch keine Backups vorhanden.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <tr><th>Datei</th><th>Größe</th><th>Erstellt</th></tr>
                    <?php foreach (array_slice($backups, 0, 10) as $backup): ?>
                        <tr>
                            <td class="mono"><?= h(basename($backup)) ?></td>
                            <td><?= h(number_format(filesize($backup) / 1024, 1, ',', '.')) ?> KB</td>
                            <td><?= h(date('d.m.Y H:i:s', filemtime($backup))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<div class="card mt-3">
    <div class="card-body">
        <h2 class="h5">Logauszug</h2>
        <?php if (!$logLines): ?>
            <p class="small-muted mb-0">Keine Logeinträge vorhanden.</p>
        <?php else: ?>
            <pre class="mono small mb-0" style="white-space:pre-wrap;max-height:360px;overflow:auto"><?= h(implode("\n", $logLines)) ?></pre>
        <?php endif; ?>
    </div>
</div>
