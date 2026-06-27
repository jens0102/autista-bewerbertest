<?php if (!empty($message)): ?>
    <div class="alert alert-warning"><?= h($message) ?></div>
<?php endif; ?>
<div class="card mb-3">
    <div class="card-body">
        <form method="post" class="row g-2 align-items-end">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="create_invitation" value="1">
            <div class="col-md-3">
                <label class="form-label">Name</label>
                <input class="form-control" name="name" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">E-Mail</label>
                <input class="form-control" type="email" name="email" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Gültig bis</label>
                <input class="form-control" type="datetime-local" name="expires_at">
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary">Einladung erzeugen</button>
            </div>
        </form>
    </div>
</div>
<div class="table-responsive">
    <table class="table table-striped align-middle">
        <tr><th>Name</th><th>E-Mail</th><th>Status</th><th>Gültig bis</th><th>Link</th><th></th></tr>
        <?php foreach ($rows as $row): ?>
            <?php $link = $baseUrl . '?token=' . urlencode($row['token']); ?>
            <tr>
                <td><?= h($row['name']) ?></td>
                <td><?= h($row['email']) ?></td>
                <td><?= h($row['status']) ?></td>
                <td><?= h($row['expires_at']) ?></td>
                <td><input class="form-control mono" value="<?= h($link) ?>" readonly onclick="this.select()"></td>
                <td class="text-nowrap">
                    <?php if (!empty($row['attempt_id'])): ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?= h(url('/admin/attempt?id=' . $row['attempt_id'])) ?>">Teilnahme</a>
                    <?php endif; ?>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= h($row['id']) ?>">
                        <button class="btn btn-sm btn-outline-danger" name="delete_invitation" value="1" onclick="return confirm('Einladung löschen?')">Löschen</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
