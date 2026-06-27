<?php if (!empty($message)): ?>
    <div class="alert alert-info"><?= h($message) ?></div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body">
        <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="import_catalog" value="1">
            <div class="col-md-8">
                <label class="form-label">Fragenkatalog importieren</label>
                <input class="form-control" type="file" name="catalog" accept="application/json,.json">
            </div>
            <div class="col-md-4">
                <button class="btn btn-outline-primary w-100">Importieren</button>
            </div>
        </form>
    </div>
</div>

<form method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="toggle" value="1">
    <div class="d-flex gap-2 justify-content-between align-items-center mb-3">
        <a class="btn btn-primary" href="<?= h(url('/admin/question/edit')) ?>">Neue Frage</a>
        <button class="btn btn-outline-primary">Aktiv-Auswahl speichern</button>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0 align-middle">
                <tr>
                    <th>Aktiv</th>
                    <th>#</th>
                    <th>Kategorie</th>
                    <th>Kompetenz</th>
                    <th>Schwere</th>
                    <th>Hinweis</th>
                    <th>Frage</th>
                    <th>Typ</th>
                    <th></th>
                </tr>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><input class="form-check-input" type="checkbox" name="active[<?= h($row['id']) ?>]" <?= $row['active'] ? 'checked' : '' ?>></td>
                        <td class="mono"><?= h($row['sort_order']) ?></td>
                        <td><?= h($row['category']) ?></td>
                        <td><?= h($row['competency'] ?? '') ?></td>
                        <td><?= h($row['difficulty'] ?? '') ?></td>
                        <td><?= trim((string)($row['source_hint'] ?? '')) !== '' ? '<span class="badge text-bg-info">ja</span>' : '<span class="small-muted">nein</span>' ?></td>
                        <td><?= h($row['question']) ?></td>
                        <td><span class="badge type-badge"><?= h($row['type']) ?></span></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= h(url('/admin/question/edit?id=' . $row['id'])) ?>">Bearbeiten</a></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</form>
