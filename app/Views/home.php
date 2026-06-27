<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<div class="hero-panel">
    <div class="intro-panel">
        <div class="intro-kicker">Bewerbertest</div>
        <h2 class="h2 mb-3">Willkommen zum Autista Bewerbertest</h2>
        <p class="lead mb-3"><?= h($introText) ?></p>
        <?php if (!empty($privacyText)): ?>
            <div class="alert alert-secondary mb-0"><?= nl2br(h($privacyText)) ?></div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (!empty($invitation)): ?>
                <div class="alert alert-info">Sie starten den Test ueber eine persoenliche Einladung.</div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <?php if (!empty($invitation)): ?>
                    <input type="hidden" name="invite_token" value="<?= h($invitation['token']) ?>">
                <?php endif; ?>
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input class="form-control" name="name" value="<?= h($invitation['name'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">E-Mail</label>
                    <input type="email" class="form-control" name="email" value="<?= h($invitation['email'] ?? '') ?>" <?= !empty($invitation) ? 'readonly' : '' ?> required>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="privacyAccepted" required>
                    <label class="form-check-label" for="privacyAccepted">Ich habe den Hinweis zur Speicherung gelesen.</label>
                </div>
                <button class="btn btn-primary w-100">Test starten</button>
            </form>
        </div>
    </div>
</div>
