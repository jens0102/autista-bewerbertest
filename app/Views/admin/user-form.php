<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>
<div class="card">
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <div class="mb-3">
                <label>Benutzername</label>
                <input class="form-control" name="username" value="<?= h($user['username'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label>Anzeigename</label>
                <input class="form-control" name="display_name" value="<?= h($user['display_name'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label><?= $user ? 'Neues Passwort' : 'Passwort' ?></label>
                <input class="form-control" type="password" name="password" <?= $user ? '' : 'required' ?>>
            </div>
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="active" id="active" <?= (($user['active'] ?? 1) ? 'checked' : '') ?>>
                <label class="form-check-label" for="active">aktiv</label>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="must_change_password" id="mustChange" <?= (($user['must_change_password'] ?? 1) ? 'checked' : '') ?>>
                <label class="form-check-label" for="mustChange">Passwortwechsel beim nächsten Login erzwingen</label>
            </div>
            <button class="btn btn-primary">Speichern</button>
            <a class="btn btn-outline-secondary" href="<?= h(url('/admin/users')) ?>">Abbrechen</a>
        </form>
    </div>
</div>
