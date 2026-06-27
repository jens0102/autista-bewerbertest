<?php
$q = $question;
$type = $q['type'] ?? 'single';
$options = $q ? (json_decode($q['options'], true) ?: []) : [];
$correct = $q ? (json_decode($q['correct_answers'], true) ?: []) : ['A'];

$choiceRows = [];
if (in_array($type, ['single', 'multiple', 'true_false'], true) && array_is_list($options)) {
    foreach ($options as $index => $option) {
        $key = (string)($option['key'] ?? chr(65 + $index));
        $choiceRows[] = [
            'index' => $index,
            'key' => $key,
            'text' => (string)($option['text'] ?? ''),
            'correct' => in_array($key, $correct, true),
        ];
    }
}
if (!$choiceRows) {
    $defaults = $type === 'true_false'
        ? [['A', 'Richtig'], ['B', 'Falsch']]
        : [['A', 'Antwort A'], ['B', 'Antwort B'], ['C', 'Antwort C'], ['D', 'Antwort D']];
    foreach ($defaults as $index => [$key, $text]) {
        $choiceRows[] = ['index' => $index, 'key' => $key, 'text' => $text, 'correct' => $index === 0];
    }
}

$orderingRows = [];
if ($type === 'ordering' && array_is_list($options)) {
    $byKey = [];
    foreach ($options as $option) {
        $byKey[(string)($option['key'] ?? '')] = $option;
    }
    foreach ($correct as $key) {
        if (isset($byKey[(string)$key])) {
            $option = $byKey[(string)$key];
            $orderingRows[] = ['key' => (string)$option['key'], 'text' => (string)$option['text']];
            unset($byKey[(string)$key]);
        }
    }
    foreach ($byKey as $option) {
        $orderingRows[] = ['key' => (string)($option['key'] ?? ''), 'text' => (string)($option['text'] ?? '')];
    }
}
if (!$orderingRows) {
    $orderingRows = [
        ['key' => 'A', 'text' => 'Erster Schritt'],
        ['key' => 'B', 'text' => 'Zweiter Schritt'],
        ['key' => 'C', 'text' => 'Dritter Schritt'],
    ];
}

$matchingRows = [];
if ($type === 'matching') {
    $left = $options['left'] ?? [];
    $answers = $options['answers'] ?? [];
    $answersByKey = [];
    foreach ($answers as $answer) {
        $answersByKey[(string)($answer['key'] ?? '')] = (string)($answer['text'] ?? '');
    }
    foreach ($left as $item) {
        $leftKey = (string)($item['key'] ?? '');
        $answerKey = (string)($correct[$leftKey] ?? '');
        $matchingRows[] = [
            'left' => (string)($item['text'] ?? ''),
            'answer' => $answersByKey[$answerKey] ?? '',
        ];
    }
}
if (!$matchingRows) {
    $matchingRows = [
        ['left' => 'Begriff 1', 'answer' => 'Bedeutung 1'],
        ['left' => 'Begriff 2', 'answer' => 'Bedeutung 2'],
    ];
}
?>
<div class="card">
    <div class="card-body">
        <form method="post" id="questionForm">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="editor_mode" value="structured">

            <div class="mb-2">
                <label>Kategorie</label>
                <input class="form-control" name="category" value="<?= h($q['category'] ?? 'Allgemein') ?>">
            </div>
            <div class="row">
                <div class="col-md-5 mb-2">
                    <label>Kompetenz</label>
                    <input class="form-control" name="competency" value="<?= h($q['competency'] ?? '') ?>">
                </div>
                <div class="col-md-2 mb-2">
                    <label>Schwere</label>
                    <input class="form-control" type="number" name="difficulty" value="<?= h($q['difficulty'] ?? 0) ?>">
                </div>
                <div class="col-md-5 mb-2">
                    <label>Dokument/Quelle</label>
                    <input class="form-control" name="document_ref" value="<?= h($q['document_ref'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-3">
                <label>Quellenhinweis fuer Bewerber</label>
                <textarea class="form-control" name="source_hint" rows="2" placeholder="Optional, z. B. siehe Handbuch Abschnitt 3.2"><?= h($q['source_hint'] ?? '') ?></textarea>
                <div class="form-text">Wird im Test nur angezeigt, wenn hier ein Hinweis eingetragen ist.</div>
            </div>

            <div class="mb-2">
                <label>Typ</label>
                <select class="form-select" name="type" id="questionType">
                    <?php foreach (['single', 'multiple', 'true_false', 'ordering', 'matching'] as $optionType): ?>
                        <option value="<?= h($optionType) ?>" <?= $type === $optionType ? 'selected' : '' ?>><?= h($optionType) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label>Frage</label>
                <textarea class="form-control" name="question" rows="3"><?= h($q['question'] ?? '') ?></textarea>
            </div>

            <section class="editor-section" data-section="choice">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h6 mb-0">Antwortoptionen</h2>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-add-choice>Option hinzufuegen</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th style="width: 90px;">Key</th>
                                <th>Antworttext</th>
                                <th class="single-only text-center" style="width: 110px;">Richtig</th>
                                <th class="multi-only text-center" style="width: 110px;">Richtig</th>
                                <th style="width: 80px;"></th>
                            </tr>
                        </thead>
                        <tbody id="choiceRows">
                        <?php foreach ($choiceRows as $row): ?>
                            <tr data-choice-row>
                                <td><input class="form-control form-control-sm" name="choice_key[<?= h($row['index']) ?>]" value="<?= h($row['key']) ?>"></td>
                                <td><input class="form-control form-control-sm" name="choice_text[<?= h($row['index']) ?>]" value="<?= h($row['text']) ?>"></td>
                                <td class="single-only text-center">
                                    <input class="form-check-input" type="radio" name="choice_correct_single" value="<?= h($row['index']) ?>" <?= $row['correct'] ? 'checked' : '' ?>>
                                </td>
                                <td class="multi-only text-center">
                                    <input class="form-check-input" type="checkbox" name="choice_correct_multi[]" value="<?= h($row['index']) ?>" <?= $row['correct'] ? 'checked' : '' ?>>
                                </td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger" data-remove-row>Entfernen</button></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="editor-section" data-section="ordering">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h6 mb-0">Richtige Reihenfolge</h2>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-add-ordering>Schritt hinzufuegen</button>
                </div>
                <div id="orderingRows">
                <?php foreach ($orderingRows as $index => $row): ?>
                    <div class="row g-2 align-items-center mb-2" data-ordering-row>
                        <div class="col-md-1"><input class="form-control form-control-sm" name="ordering_key[<?= $index ?>]" value="<?= h($row['key']) ?>"></div>
                        <div class="col-md"><input class="form-control form-control-sm" name="ordering_text[<?= $index ?>]" value="<?= h($row['text']) ?>"></div>
                        <div class="col-md-auto">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-move-up>Hoch</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-move-down>Runter</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-remove-row>Entfernen</button>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            </section>

            <section class="editor-section" data-section="matching">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h6 mb-0">Zuordnungspaare</h2>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-add-matching>Paar hinzufuegen</button>
                </div>
                <div id="matchingRows">
                <?php foreach ($matchingRows as $index => $row): ?>
                    <div class="row g-2 align-items-center mb-2" data-matching-row>
                        <div class="col-md"><input class="form-control form-control-sm" name="match_left_text[<?= $index ?>]" value="<?= h($row['left']) ?>" placeholder="Begriff / Aussage"></div>
                        <div class="col-md"><input class="form-control form-control-sm" name="match_answer_text[<?= $index ?>]" value="<?= h($row['answer']) ?>" placeholder="Passende Antwort"></div>
                        <div class="col-md-auto"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-row>Entfernen</button></div>
                    </div>
                <?php endforeach; ?>
                </div>
            </section>

            <div class="mb-2 mt-3">
                <label>Mustererklaerung</label>
                <textarea class="form-control" name="explanation"><?= h($q['explanation'] ?? '') ?></textarea>
            </div>
            <div class="row">
                <div class="col">
                    <label>Punkte</label>
                    <input class="form-control" name="points" value="<?= h($q['points'] ?? 1) ?>">
                </div>
                <div class="col">
                    <label>Sortierung</label>
                    <input class="form-control" name="sort_order" value="<?= h($q['sort_order'] ?? 999) ?>">
                </div>
            </div>
            <div class="form-check my-3">
                <input class="form-check-input" type="checkbox" name="active" <?= (($q['active'] ?? 1) ? 'checked' : '') ?>>
                <label class="form-check-label">aktiv</label>
            </div>
            <button class="btn btn-primary">Speichern</button>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body">
        <h2 class="h6">Vorschau</h2>
        <p class="mb-2"><strong id="previewQuestion"><?= h($q['question'] ?? '') ?></strong></p>
        <div id="previewOptions" class="small"></div>
    </div>
</div>

<script>
const form = document.getElementById('questionForm');
const typeField = document.getElementById('questionType');
const previewQuestion = document.getElementById('previewQuestion');
const previewOptions = document.getElementById('previewOptions');
let nextIndex = 1000;

function optionKey(index) {
    return String.fromCharCode(65 + (index % 26));
}

function activeSectionName() {
    const type = typeField.value;
    if (type === 'ordering' || type === 'matching') {
        return type;
    }
    return 'choice';
}

function updateSections() {
    const type = typeField.value;
    document.querySelectorAll('.editor-section').forEach(section => {
        section.style.display = section.dataset.section === activeSectionName() ? '' : 'none';
    });
    document.querySelectorAll('.single-only').forEach(el => el.style.display = type === 'multiple' ? 'none' : '');
    document.querySelectorAll('.multi-only').forEach(el => el.style.display = type === 'multiple' ? '' : 'none');
    updatePreview();
}

function inputValue(row, selector) {
    const input = row.querySelector(selector);
    return input ? input.value.trim() : '';
}

function updatePreview() {
    previewQuestion.textContent = form.question.value || '';
    const type = typeField.value;
    let html = '';

    if (activeSectionName() === 'choice') {
        document.querySelectorAll('[data-choice-row]').forEach(row => {
            const key = inputValue(row, '[name^="choice_key"]');
            const text = inputValue(row, '[name^="choice_text"]');
            const radio = row.querySelector('[name="choice_correct_single"]');
            const check = row.querySelector('[name="choice_correct_multi[]"]');
            const isCorrect = type === 'multiple' ? (check && check.checked) : (radio && radio.checked);
            if (key || text) {
                html += `<div>${isCorrect ? '<strong>' : ''}${key} - ${text}${isCorrect ? '</strong>' : ''}</div>`;
            }
        });
    }

    if (type === 'ordering') {
        document.querySelectorAll('[data-ordering-row]').forEach((row, index) => {
            html += `<div>${index + 1}. ${inputValue(row, '[name^="ordering_text"]')}</div>`;
        });
    }

    if (type === 'matching') {
        document.querySelectorAll('[data-matching-row]').forEach(row => {
            html += `<div>${inputValue(row, '[name^="match_left_text"]')} = ${inputValue(row, '[name^="match_answer_text"]')}</div>`;
        });
    }

    previewOptions.innerHTML = html;
}

function removeRow(button) {
    const row = button.closest('[data-choice-row], [data-ordering-row], [data-matching-row]');
    if (row) {
        row.remove();
        updatePreview();
    }
}

document.querySelector('[data-add-choice]').addEventListener('click', () => {
    const index = nextIndex++;
    const key = optionKey(document.querySelectorAll('[data-choice-row]').length);
    document.getElementById('choiceRows').insertAdjacentHTML('beforeend', `
        <tr data-choice-row>
            <td><input class="form-control form-control-sm" name="choice_key[${index}]" value="${key}"></td>
            <td><input class="form-control form-control-sm" name="choice_text[${index}]" value=""></td>
            <td class="single-only text-center"><input class="form-check-input" type="radio" name="choice_correct_single" value="${index}"></td>
            <td class="multi-only text-center"><input class="form-check-input" type="checkbox" name="choice_correct_multi[]" value="${index}"></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger" data-remove-row>Entfernen</button></td>
        </tr>
    `);
    updateSections();
});

document.querySelector('[data-add-ordering]').addEventListener('click', () => {
    const index = nextIndex++;
    const key = optionKey(document.querySelectorAll('[data-ordering-row]').length);
    document.getElementById('orderingRows').insertAdjacentHTML('beforeend', `
        <div class="row g-2 align-items-center mb-2" data-ordering-row>
            <div class="col-md-1"><input class="form-control form-control-sm" name="ordering_key[${index}]" value="${key}"></div>
            <div class="col-md"><input class="form-control form-control-sm" name="ordering_text[${index}]" value=""></div>
            <div class="col-md-auto">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-move-up>Hoch</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-move-down>Runter</button>
                <button type="button" class="btn btn-sm btn-outline-danger" data-remove-row>Entfernen</button>
            </div>
        </div>
    `);
    updatePreview();
});

document.querySelector('[data-add-matching]').addEventListener('click', () => {
    const index = nextIndex++;
    document.getElementById('matchingRows').insertAdjacentHTML('beforeend', `
        <div class="row g-2 align-items-center mb-2" data-matching-row>
            <div class="col-md"><input class="form-control form-control-sm" name="match_left_text[${index}]" value="" placeholder="Begriff / Aussage"></div>
            <div class="col-md"><input class="form-control form-control-sm" name="match_answer_text[${index}]" value="" placeholder="Passende Antwort"></div>
            <div class="col-md-auto"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-row>Entfernen</button></div>
        </div>
    `);
    updatePreview();
});

form.addEventListener('click', event => {
    if (event.target.matches('[data-remove-row]')) {
        removeRow(event.target);
    }
    if (event.target.matches('[data-move-up]')) {
        const row = event.target.closest('[data-ordering-row]');
        if (row && row.previousElementSibling) {
            row.parentNode.insertBefore(row, row.previousElementSibling);
            updatePreview();
        }
    }
    if (event.target.matches('[data-move-down]')) {
        const row = event.target.closest('[data-ordering-row]');
        if (row && row.nextElementSibling) {
            row.parentNode.insertBefore(row.nextElementSibling, row);
            updatePreview();
        }
    }
});

form.addEventListener('input', updatePreview);
form.addEventListener('change', updatePreview);
typeField.addEventListener('change', updateSections);
updateSections();
</script>
