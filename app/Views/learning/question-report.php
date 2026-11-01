<?php
$question = is_array($question ?? null) ? $question : [];
$types = is_array($types ?? null) ? $types : [];
$csrfToken = is_string($csrfToken ?? null) ? $csrfToken : '';
$questionId = is_scalar($question['id'] ?? null) ? (string) $question['id'] : '';
$attemptId = is_scalar($attemptId ?? null) ? (string) $attemptId : '';
$escape = static fn(mixed $value): string => htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="page-container learner-question-report">
    <nav class="breadcrumbs" aria-label="Breadcrumb"><ol><li><a href="/history">Quiz history</a></li><li aria-current="page">Report a question concern</li></ol></nav>
    <section class="page-header">
        <div><p class="eyebrow">Help us review the question</p><h1>Report a question concern</h1><p>Reports are read by an authorized reviewer. They do not change the question, answer key, eligibility, academic approval, or your score.</p></div>
        <a class="button button-outline" href="/question-reports">My reports</a>
    </section>
    <section class="card" aria-labelledby="reported-question-heading">
        <h2 id="reported-question-heading">Question <?= $escape($questionId) ?></h2>
        <p><?= nl2br($escape($question['question'] ?? '')) ?></p>
        <p class="muted-note">This report is linked to the completed quiz result that showed this question<?= ($question['archived'] ?? false) === true ? '; its saved question version will be used for review' : '' ?>.</p>
    </section>
    <form class="card learner-question-report-form" method="post" action="/question-report">
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
        <input type="hidden" name="attempt_id" value="<?= $escape($attemptId) ?>">
        <input type="hidden" name="question_id" value="<?= $escape($questionId) ?>">
        <div class="form-field">
            <label for="question-report-type">What should be reviewed?</label>
            <select id="question-report-type" name="type" required>
                <option value="">Choose a concern</option>
                <?php foreach ($types as $value => $label): ?><option value="<?= $escape($value) ?>"><?= $escape($label) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label for="question-report-description">Optional note</label>
            <textarea id="question-report-description" name="description" rows="4" maxlength="500" aria-describedby="question-report-privacy-note"></textarea>
            <small id="question-report-privacy-note" class="muted-note">Up to 500 characters. Please do not include your name, contact information, or other personal details.</small>
        </div>
        <p class="muted-note">A report starts as Submitted. Only a qualified reviewer can change its review status, and the report itself never edits academic records.</p>
        <div class="page-actions"><button class="button button-green" type="submit">Submit concern</button><a class="button button-outline" href="/history">Cancel</a></div>
    </form>
</div>
