<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>
<div class="card">
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <div class="mb-3">
                <label>Benutzer</label>
                <input class="form-control" name="username" value="admin">
            </div>
            <div class="mb-3">
                <label>Passwort</label>
                <input type="password" class="form-control" name="password">
            </div>
            <button class="btn btn-primary">Login</button>
        </form>
        <p class="small-muted mt-3">Initial: admin / admin123. Bitte unter Einstellungen ändern.</p>
    </div>
</div>
