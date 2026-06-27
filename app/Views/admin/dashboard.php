<div class="row g-3">
    <?php
    $cards = [
        ['label' => 'Fragen gesamt', 'value' => $stats['q']],
        ['label' => 'Aktiv', 'value' => $stats['active']],
        ['label' => 'Teilnahmen', 'value' => $stats['attempts']],
        ['label' => 'Abgegeben', 'value' => $stats['submitted']],
        ['label' => 'Abgelaufen', 'value' => $stats['expired']],
    ];
    ?>
    <?php foreach ($cards as $card): ?>
        <div class="col-6 col-lg">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <h2><?= h($card['value']) ?></h2>
                    <p><?= h($card['label']) ?></p>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card mt-4">
    <div class="card-body">
        <div class="small-muted mb-1">Bewerberlink</div>
        <div class="mono text-break"><?= h($bewerberLink) ?></div>
    </div>
</div>
