<div class="card">
    <div class="card-body">
        <?php if (($attempt['status'] ?? '') === 'expired'): ?>
            <p>Die Bearbeitungszeit ist abgelaufen. Der Test wurde als abgelaufen markiert.</p>
        <?php else: ?>
            <p>Vielen Dank. Ihre Antworten wurden gespeichert.</p>
        <?php endif; ?>
    </div>
</div>
