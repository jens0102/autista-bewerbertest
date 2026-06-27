<?php $groupCount = count($groups); ?>
<div class="alert alert-info timer shadow-sm">
    <div class="d-flex justify-content-between align-items-center gap-3">
        <div><strong>Restzeit:</strong> <span id="timer"></span></div>
        <div class="small-muted">Abschnitt <span id="stepNow">1</span> von <?= h($groupCount) ?></div>
    </div>
    <div class="progress mt-2" role="progressbar" aria-label="Fortschritt">
        <div id="progressBar" class="progress-bar" style="width:0%">0%</div>
    </div>
    <div class="small-muted mt-1"><span id="answeredCount">0</span> von <?= h($totalQuestions) ?> Fragen beantwortet</div>
</div>

<form id="testForm" method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <?php $step = 0; $globalNo = 1; ?>
    <?php foreach ($groups as $category => $items): ?>
        <?php $step++; ?>
        <section class="test-step" data-step="<?= h($step) ?>" <?= $step > 1 ? 'style="display:none"' : '' ?>>
            <div class="card mb-3">
                <div class="card-body">
                    <div class="small-muted">Kompetenzbereich</div>
                    <h2 class="h5 mb-1"><?= h($category) ?></h2>
                    <p class="mb-0 small-muted"><?= count($items) ?> Frage<?= count($items) === 1 ? '' : 'n' ?></p>
                </div>
            </div>

            <?php foreach ($items as $q): ?>
                <?php
                $options = json_decode($q['options'], true) ?: [];
                if ($q['type'] !== 'matching') {
                    usort($options, function ($a, $b) use ($attemptId, $q) {
                        $sa = crc32($attemptId . '|' . $q['id'] . '|' . $a['key']);
                        $sb = crc32($attemptId . '|' . $q['id'] . '|' . $b['key']);
                        return $sa <=> $sb;
                    });
                }
                ?>
                <div class="card question-box mb-3" data-question="<?= h($q['id']) ?>" data-type="<?= h($q['type']) ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between gap-2 align-items-start mb-2">
                            <div class="small-muted">Frage <?= h($globalNo) ?> von <?= h($totalQuestions) ?> - <?= h($q['category']) ?></div>
                            <span class="badge type-badge"><?= h($q['type']) ?></span>
                        </div>
                        <h3 class="h5 mb-3"><?= h($q['question']) ?></h3>
                        <?php if (trim((string)($q['source_hint'] ?? '')) !== ''): ?>
                            <div class="alert alert-secondary py-2 mb-3">
                                <strong>Hinweis:</strong> <?= h($q['source_hint']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($q['type'] === 'ordering'): ?>
                            <?php $draft = $drafts[$q['id']] ?? []; ?>
                            <?php for ($position = 0; $position < count($options); $position++): ?>
                                <div class="row g-2 align-items-center mb-2">
                                    <label class="col-sm-3 col-form-label"><?= h($position + 1) ?>. Position</label>
                                    <div class="col-sm-9">
                                        <select class="form-select unique-choice" name="q[<?= h($q['id']) ?>][<?= h($position) ?>]">
                                            <option value="">Bitte waehlen</option>
                                            <?php foreach ($options as $option): ?>
                                                <option value="<?= h($option['key']) ?>" <?= (($draft[(string)$position] ?? '') === $option['key']) ? 'selected' : '' ?>><?= h($option['text']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        <?php elseif ($q['type'] === 'matching'): ?>
                            <?php
                            $draft = $drafts[$q['id']] ?? [];
                            $leftItems = $options['left'] ?? [];
                            $answers = $options['answers'] ?? [];
                            usort($answers, function ($a, $b) use ($attemptId, $q) {
                                $sa = crc32($attemptId . '|' . $q['id'] . '|match|' . $a['key']);
                                $sb = crc32($attemptId . '|' . $q['id'] . '|match|' . $b['key']);
                                return $sa <=> $sb;
                            });
                            ?>
                            <?php foreach ($leftItems as $left): ?>
                                <div class="row g-2 align-items-center mb-2">
                                    <label class="col-sm-5 col-form-label"><?= h($left['text']) ?></label>
                                    <div class="col-sm-7">
                                        <select class="form-select unique-choice" name="q[<?= h($q['id']) ?>][<?= h($left['key']) ?>]">
                                            <option value="">Bitte waehlen</option>
                                            <?php foreach ($answers as $answer): ?>
                                                <option value="<?= h($answer['key']) ?>" <?= (($draft[$left['key']] ?? '') === $answer['key']) ? 'selected' : '' ?>><?= h($answer['text']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php $draft = $drafts[$q['id']] ?? []; ?>
                            <?php foreach ($options as $option): ?>
                                <?php
                                $name = 'q[' . $q['id'] . ']' . ($q['type'] === 'multiple' ? '[]' : '');
                                $inputType = $q['type'] === 'multiple' ? 'checkbox' : 'radio';
                                $inputId = 'q' . $q['id'] . $option['key'];
                                ?>
                                <label class="answer-option" for="<?= h($inputId) ?>">
                                    <input class="form-check-input" type="<?= h($inputType) ?>" name="<?= h($name) ?>" value="<?= h($option['key']) ?>" id="<?= h($inputId) ?>" <?= in_array($option['key'], $draft, true) ? 'checked' : '' ?>>
                                    <span><?= h($option['text']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php $globalNo++; ?>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>

    <div class="d-flex gap-2 justify-content-between my-4">
        <button type="button" id="prevBtn" class="btn btn-outline-secondary" style="display:none">Zurueck</button>
        <button type="button" id="nextBtn" class="btn btn-primary ms-auto">Weiter</button>
        <button type="submit" id="submitBtn" class="btn btn-success btn-lg ms-auto" style="display:none">Antworten abgeben</button>
    </div>
</form>

<script>
let s = <?= (int)$remaining ?>;
const totalSteps = <?= (int)$groupCount ?>;
const totalQuestions = <?= (int)$totalQuestions ?>;
let currentStep = 1;
let autosaveTimer = null;
function isAnswered(box){
    const type = box.dataset.type;
    if(type === "ordering" || type === "matching"){
        const selects = [...box.querySelectorAll("select")];
        return selects.length > 0 && selects.every(select => select.value !== "");
    }
    return !!box.querySelector("input:checked");
}
function updateProgress(){
    const answered = [...document.querySelectorAll("[data-question]")].filter(isAnswered).length;
    const pct = totalQuestions ? Math.round(answered / totalQuestions * 100) : 0;
    document.getElementById("answeredCount").textContent = String(answered);
    document.getElementById("progressBar").style.width = pct + "%";
    document.getElementById("progressBar").textContent = pct + "%";
}
function updateUniqueChoices(box){
    const selects = [...box.querySelectorAll("select.unique-choice")];
    const selected = selects.map(select => select.value).filter(Boolean);
    selects.forEach(select => {
        [...select.options].forEach(option => {
            option.disabled = option.value !== "" && option.value !== select.value && selected.includes(option.value);
        });
    });
}
function tick(){
    let m = Math.floor(s / 60), r = s % 60;
    document.getElementById("timer").textContent = String(m).padStart(2, "0") + ":" + String(r).padStart(2, "0");
    if(s <= 0){
        document.getElementById("testForm").submit();
    }
    s--;
}
function showStep(n){
    currentStep = n;
    document.querySelectorAll(".test-step").forEach(el => {
        el.style.display = Number(el.dataset.step) === n ? "" : "none";
    });
    document.getElementById("stepNow").textContent = String(n);
    document.getElementById("prevBtn").style.display = n > 1 ? "" : "none";
    document.getElementById("nextBtn").style.display = n < totalSteps ? "" : "none";
    document.getElementById("submitBtn").style.display = n === totalSteps ? "" : "none";
    window.scrollTo({top: 0, behavior: "smooth"});
}
document.querySelectorAll("input, select").forEach(el => el.addEventListener("change", () => {
    const box = el.closest("[data-question]");
    if(box) updateUniqueChoices(box);
    updateProgress();
    scheduleAutosave();
}));
function scheduleAutosave(){
    window.clearTimeout(autosaveTimer);
    autosaveTimer = window.setTimeout(saveDraft, 500);
}
function saveDraft(){
    const data = new FormData(document.getElementById("testForm"));
    fetch("<?= h(url('/test/autosave')) ?>", {method: "POST", body: data, credentials: "same-origin"}).catch(() => {});
}
document.getElementById("testForm").addEventListener("submit", event => {
    const open = totalQuestions - [...document.querySelectorAll("[data-question]")].filter(isAnswered).length;
    if(open > 0 && !confirm("Es sind noch " + open + " Fragen offen. Trotzdem abgeben?")){
        event.preventDefault();
    }
});
document.getElementById("nextBtn").addEventListener("click", () => showStep(Math.min(totalSteps, currentStep + 1)));
document.getElementById("prevBtn").addEventListener("click", () => showStep(Math.max(1, currentStep - 1)));
document.querySelectorAll("[data-question]").forEach(updateUniqueChoices);
updateProgress();
tick();
setInterval(tick, 1000);
</script>
