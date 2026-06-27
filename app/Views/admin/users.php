<p><a class="btn btn-primary" href="<?= h(url('/admin/user/edit')) ?>">Admin anlegen</a></p>
<div class="table-responsive">
    <table class="table table-striped align-middle">
        <tr><th>Benutzer</th><th>Name</th><th>Status</th><th>Fehlversuche</th><th>Letzter Login</th><th></th></tr>
        <?php foreach ($rows as $row): ?>
            <?php $locked = !empty($row['locked_until']) && strtotime($row['locked_until'] . ' UTC') > time(); ?>
            <tr>
                <td class="mono"><?= h($row['username']) ?></td>
                <td><?= h($row['display_name'] ?? '') ?></td>
                <td>
                    <?php if ((int)($row['active'] ?? 1) !== 1): ?>
                        <span class="badge text-bg-secondary">deaktiviert</span>
                    <?php elseif ($locked): ?>
                        <span class="badge text-bg-danger">gesperrt</span>
                    <?php else: ?>
                        <span class="badge text-bg-success">aktiv</span>
                    <?php endif; ?>
                    <?php if (!empty($row['must_change_password'])): ?>
                        <span class="badge text-bg-warning">Passwortwechsel</span>
                    <?php endif; ?>
                </td>
                <td><?= h($row['failed_login_count'] ?? 0) ?></td>
                <td><?= h($row['last_login_at'] ?? '') ?></td>
                <td class="text-nowrap">
                    <a class="btn btn-sm btn-outline-primary" href="<?= h(url('/admin/user/edit?id=' . $row['id'])) ?>">Bearbeiten</a>
                    <?php if ($locked): ?>
                        <form method="post" action="<?= h(url('/admin/user/unlock')) ?>" class="d-inline">
                            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= h($row['id']) ?>">
                            <button class="btn btn-sm btn-outline-secondary">Entsperren</button>
                        </form>
                    <?php endif; ?>
                    <?php if ((int)$row['id'] !== $currentAdminId): ?>
                        <form method="post" action="<?= h(url('/admin/user/delete')) ?>" class="d-inline">
                            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= h($row['id']) ?>">
                            <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Admin wirklich löschen?')">Löschen</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
