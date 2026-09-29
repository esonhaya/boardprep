<?php

$eligibility = is_array($eligibility ?? null) ? $eligibility : [];
$mappings = is_array($eligibility['mappings'] ?? null) ? $eligibility['mappings'] : [];
$options = is_array($eligibility['options'] ?? null) ? $eligibility['options'] : [];
$boards = is_array($options['boards'] ?? null) ? $options['boards'] : [];
$subjects = is_array($options['subjects'] ?? null) ? $options['subjects'] : [];
$domains = is_array($options['domains'] ?? null) ? $options['domains'] : [];
$topics = is_array($options['topics'] ?? null) ? $options['topics'] : [];
$concepts = is_array($options['concepts'] ?? null) ? $options['concepts'] : [];
$blueprintNodes = is_array($options['blueprint_nodes'] ?? null) ? $options['blueprint_nodes'] : [];
$eligibilityErrors = is_array($eligibilityErrors ?? null) ? $eligibilityErrors : [];
$eligibilityState = (string) ($eligibilityState ?? '');
$csrfToken = (string) ($csrfToken ?? '');
$questionId = urlencode((string) ($question['id'] ?? ''));
$esc = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$value = static function (string $key, array $fallback = []): string {
    $fallbackKey = preg_replace('/^target_/', '', $key) ?? $key;
    $candidate = $_POST[$key] ?? ($fallback[$key] ?? $fallback[$fallbackKey . '_id'] ?? '');
    return is_scalar($candidate) ? trim((string) $candidate) : '';
};
$select = static function (string $name, array $items, string $selected, string $labelKey = 'name', array $data = [] ) use ($esc): void {
    echo '<select name="' . $esc($name) . '" id="' . $esc($name) . '"' . ($name === 'target_subject' || $name === 'target_blueprint_node' ? ' required' : '') . '>';
    echo '<option value="">Select ' . $esc(ucwords(str_replace('_', ' ', preg_replace('/^target_/', '', $name) ?? $name))) . '</option>';
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $id = (string) ($item['id'] ?? $item['subject_id'] ?? '');
        if ($id === '') continue;
        $attrs = '';
        foreach ($data as $attribute => $field) {
            $attrs .= ' data-' . $esc($attribute) . '="' . $esc($item[$field] ?? '') . '"';
        }
        echo '<option value="' . $esc($id) . '"' . $attrs . ($selected === $id ? ' selected' : '') . '>' . $esc($item[$labelKey] ?? $id) . '</option>';
    }
    echo '</select>';
};
?>

<?php if (($contentMode ?? '') === 'edit'): ?>
<section class="card" aria-labelledby="eligibility-heading">
    <h3 id="eligibility-heading">Board Eligibility</h3>
    <p class="form-help">One canonical question can be used by multiple boards. Each board keeps its own placement.</p>
    <?php if (($eligibilityNotice ?? '') !== ''): ?><p class="form-success" role="status"><?= $esc(str_replace('-', ' ', (string) $eligibilityNotice)) ?>.</p><?php endif; ?>
    <?php if ($eligibilityErrors !== []): ?><div class="form-errors" role="alert"><strong>Eligibility change was not saved.</strong><ul><?php foreach ($eligibilityErrors as $code => $message): ?><li><code><?= $esc($code) ?></code>: <?= $esc($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <p class="badge badge-info">Used by <?= count($mappings) ?> board<?= count($mappings) === 1 ? '' : 's' ?></p>
    <?php if (count($mappings) > 1): ?><p class="form-warning" role="note"><strong>Shared canonical question.</strong> Editing the stem, choices, correct answer, explanation, or canonical source/study relationship changes this question everywhere it is used: <?= $esc(implode(', ', array_map(static fn(array $item): string => (string) ($item['board_name'] ?? $item['board_id'] ?? ''), $mappings))) ?>.</p><?php endif; ?>

    <?php foreach ($mappings as $mapping): ?>
        <article class="card" data-board-mapping="<?= $esc($mapping['board_id'] ?? '') ?>">
            <h4><?= $esc($mapping['board_name'] ?? $mapping['board_id'] ?? '') ?></h4>
            <p><?= $esc($mapping['subject_name'] ?? $mapping['subject_id'] ?? '') ?><?php if (($mapping['domain_name'] ?? '') !== ''): ?> → <?= $esc($mapping['domain_name']) ?><?php endif; ?><?php if (($mapping['topic_name'] ?? '') !== ''): ?> → <?= $esc($mapping['topic_name']) ?><?php endif; ?><?php if (($mapping['concept_name'] ?? '') !== ''): ?> → <?= $esc($mapping['concept_name']) ?><?php endif; ?></p>
            <small class="muted">Blueprint placement: <?= $esc($mapping['blueprint_name'] ?? $mapping['blueprint_node'] ?? '') ?></small>
            <details><summary>Change placement</summary>
                <p class="form-help">Only the <?= $esc($mapping['board_name'] ?? $mapping['board_id'] ?? 'selected') ?> placement will change. The canonical question and other board placements are unchanged.</p>
                <form method="POST" action="/question-editor/placement?id=<?= $questionId ?>" data-eligibility-form="placement">
                    <input type="hidden" name="_csrf" value="<?= $esc($csrfToken) ?>"><input type="hidden" name="id" value="<?= $esc($question['id'] ?? '') ?>"><input type="hidden" name="target_board" value="<?= $esc($mapping['board_id'] ?? '') ?>">
                    <label>Subject/domain<br><?php $select('target_subject', $subjects, $value('target_subject', $mapping), 'name', ['board' => 'board_id']); ?></label><div class="form-spacer"></div>
                    <label>Domain<br><?php $select('target_domain', $domains, $value('target_domain', $mapping), 'name', ['subject' => 'subject_id']); ?></label><div class="form-spacer"></div>
                    <label>Topic<br><?php $select('target_topic', $topics, $value('target_topic', $mapping), 'name', ['domain' => 'domain_id']); ?></label><div class="form-spacer"></div>
                    <label>Concept<br><?php $select('target_concept', $concepts, $value('target_concept', $mapping), 'name', ['topic' => 'topic_id']); ?></label><div class="form-spacer"></div>
                    <label>Blueprint placement<br><?php $nodes = $blueprintNodes[$mapping['board_id'] ?? ''] ?? []; $select('target_blueprint_node', $nodes, $value('target_blueprint_node', ['target_blueprint_node' => $mapping['blueprint_node'] ?? ''])); ?></label><div class="form-spacer"></div>
                    <?php if (($mapping['board_id'] ?? '') === 'let'): ?><label>LET level<br><select name="target_let_level"><option value="">Shared / unspecified</option><option value="ELEMENTARY" <?= $value('target_let_level', $mapping) === 'ELEMENTARY' ? 'selected' : '' ?>>Elementary</option><option value="SECONDARY" <?= $value('target_let_level', $mapping) === 'SECONDARY' ? 'selected' : '' ?>>Secondary</option></select></label><div class="form-spacer"></div><label>LET component<br><select name="target_let_component"><option value="">Infer shared GE/PE</option><option value="GENERAL_EDUCATION" <?= $value('target_let_component', $mapping) === 'GENERAL_EDUCATION' ? 'selected' : '' ?>>General Education</option><option value="PROFESSIONAL_EDUCATION" <?= $value('target_let_component', $mapping) === 'PROFESSIONAL_EDUCATION' ? 'selected' : '' ?>>Professional Education</option><option value="SPECIALIZATION" <?= $value('target_let_component', $mapping) === 'SPECIALIZATION' ? 'selected' : '' ?>>Specialization</option></select></label><div class="form-spacer"></div><label>LET specialization<br><select name="target_specialization_id"><option value="">None / shared</option><?php foreach (($options['let_specializations'] ?? []) as $specialization): ?><option value="<?= $esc($specialization['id'] ?? '') ?>" <?= $value('target_specialization_id', $mapping) === (string) ($specialization['id'] ?? '') ? 'selected' : '' ?>><?= $esc($specialization['official_name'] ?? $specialization['id'] ?? '') ?> (<?= $esc($specialization['level'] ?? '') ?>)</option><?php endforeach; ?></select></label><div class="form-spacer"></div><?php endif; ?>
                    <button type="submit">Save placement</button>
                </form>
            </details>
            <form method="POST" action="/question-editor/unshare?id=<?= $questionId ?>" onsubmit="return confirm('Remove this question from <?= $esc($mapping['board_name'] ?? $mapping['board_id'] ?? '') ?>?')">
                <input type="hidden" name="_csrf" value="<?= $esc($csrfToken) ?>"><input type="hidden" name="target_board" value="<?= $esc($mapping['board_id'] ?? '') ?>"><?php if (count($mappings) > 1): ?><button type="submit" class="button secondary">Remove from this board</button><?php else: ?><button type="button" class="button secondary" disabled>Only board eligibility</button><small class="muted">Archive or delete the question to remove its only board eligibility.</small><?php endif; ?>
            </form>
        </article>
    <?php endforeach; ?>

    <details open><summary><strong>Share with another board</strong></summary>
        <p class="form-help">Use Share when exactly the same question is appropriate for another board. This shares the existing canonical question. No duplicate question will be created.</p>
        <form method="POST" action="/question-editor/share" data-eligibility-form="share">
            <input type="hidden" name="_csrf" value="<?= $esc($csrfToken) ?>"><input type="hidden" name="id" value="<?= $esc($question['id'] ?? '') ?>">
            <label>Target board<br><select name="target_board" id="share_target_board" required><option value="">Select target board</option><?php foreach ($boards as $board): ?><option value="<?= $esc($board['id'] ?? '') ?>" <?= !empty($board['eligible']) ? 'disabled' : '' ?>><?= $esc($board['name'] ?? $board['id'] ?? '') ?><?= !empty($board['eligible']) ? ' (Already shared)' : '' ?></option><?php endforeach; ?></select></label><div class="form-spacer"></div>
            <label>Target subject/domain<br><?php $select('target_subject', $subjects, $value('target_subject'), 'name', ['board' => 'board_id']); ?></label><div class="form-spacer"></div>
            <label>Target domain<br><?php $select('target_domain', $domains, $value('target_domain'), 'name', ['subject' => 'subject_id']); ?></label><div class="form-spacer"></div>
            <label>Target topic<br><?php $select('target_topic', $topics, $value('target_topic'), 'name', ['domain' => 'domain_id']); ?></label><div class="form-spacer"></div>
            <label>Target concept<br><?php $select('target_concept', $concepts, $value('target_concept'), 'name', ['topic' => 'topic_id']); ?></label><div class="form-spacer"></div>
            <label>Blueprint placement<br><select name="blueprint_node" id="share_blueprint_node" required><option value="">Select blueprint placement</option><?php foreach ($blueprintNodes as $boardId => $nodes): foreach ($nodes as $node): ?><option value="<?= $esc($node['id'] ?? '') ?>" data-board="<?= $esc($boardId) ?>"><?= $esc($node['name'] ?? $node['id'] ?? '') ?></option><?php endforeach; endforeach; ?></select></label><div class="form-spacer"></div>
            <label>LET level (optional)<br><select name="let_level"><option value="">Shared / unspecified</option><option value="ELEMENTARY">Elementary</option><option value="SECONDARY">Secondary</option></select></label><div class="form-spacer"></div><label>LET component (optional)<br><select name="let_component"><option value="">Infer shared GE/PE</option><option value="GENERAL_EDUCATION">General Education</option><option value="PROFESSIONAL_EDUCATION">Professional Education</option><option value="SPECIALIZATION">Specialization</option></select></label><div class="form-spacer"></div><label>LET specialization (optional)<br><select name="let_specialization_id"><option value="">None / shared</option><?php foreach (($options['let_specializations'] ?? []) as $specialization): ?><option value="<?= $esc($specialization['id'] ?? '') ?>"><?= $esc($specialization['official_name'] ?? $specialization['id'] ?? '') ?> (<?= $esc($specialization['level'] ?? '') ?>)</option><?php endforeach; ?></select></label><div class="form-spacer"></div>
            <p class="form-help">The target board placement is validated against its configured blueprint before saving.</p><button type="submit">Share with this board</button>
        </form>
    </details>

    <details><summary><strong>Duplicate as new question</strong></summary>
        <p class="form-help">Use Duplicate when the new board needs a meaningfully different version, depth, context, answer, explanation, or source. A new canonical ID will be created.</p>
        <form method="POST" action="/question-editor/duplicate" data-eligibility-form="duplicate">
            <input type="hidden" name="_csrf" value="<?= $esc($csrfToken) ?>"><input type="hidden" name="id" value="<?= $esc($question['id'] ?? '') ?>">
            <label>Target board<br><select name="target_board" required><option value="">Select target board</option><?php foreach ($boards as $board): ?><option value="<?= $esc($board['id'] ?? '') ?>"><?= $esc($board['name'] ?? $board['id'] ?? '') ?></option><?php endforeach; ?></select></label><div class="form-spacer"></div>
            <label>Target subject<br><?php $select('target_subject', $subjects, '', 'name', ['board' => 'board_id']); ?></label><div class="form-spacer"></div>
            <label>Target domain<br><?php $select('target_domain', $domains, '', 'name', ['subject' => 'subject_id']); ?></label><div class="form-spacer"></div>
            <label>Target topic<br><?php $select('target_topic', $topics, '', 'name', ['domain' => 'domain_id']); ?></label><div class="form-spacer"></div>
            <label>Target concept<br><?php $select('target_concept', $concepts, '', 'name', ['topic' => 'topic_id']); ?></label><div class="form-spacer"></div>
            <label>Blueprint placement<br><select name="blueprint_node" required><option value="">Select blueprint placement</option><?php foreach ($blueprintNodes as $boardId => $nodes): foreach ($nodes as $node): ?><option value="<?= $esc($node['id'] ?? '') ?>" data-board="<?= $esc($boardId) ?>"><?= $esc($node['name'] ?? $node['id'] ?? '') ?></option><?php endforeach; endforeach; ?></select></label><div class="form-spacer"></div>
            <label>LET level (optional)<br><select name="let_level"><option value="">Shared / unspecified</option><option value="ELEMENTARY">Elementary</option><option value="SECONDARY">Secondary</option></select></label><div class="form-spacer"></div><label>LET component (optional)<br><select name="let_component"><option value="">Infer shared GE/PE</option><option value="GENERAL_EDUCATION">General Education</option><option value="PROFESSIONAL_EDUCATION">Professional Education</option><option value="SPECIALIZATION">Specialization</option></select></label><div class="form-spacer"></div><label>LET specialization (optional)<br><select name="let_specialization_id"><option value="">None / shared</option><?php foreach (($options['let_specializations'] ?? []) as $specialization): ?><option value="<?= $esc($specialization['id'] ?? '') ?>"><?= $esc($specialization['official_name'] ?? $specialization['id'] ?? '') ?> (<?= $esc($specialization['level'] ?? '') ?>)</option><?php endforeach; ?></select></label><div class="form-spacer"></div>
            <p class="form-help">NEW_CANONICAL_ID=will be created · INDEPENDENT_QUESTION=YES</p><button type="submit">Duplicate as new question</button>
        </form>
    </details>
</section>
<script>
document.querySelectorAll('[data-eligibility-form]').forEach(function (form) {
    var board = form.querySelector('[name="target_board"]');
    var subject = form.querySelector('[name="target_subject"]');
    var domain = form.querySelector('[name="target_domain"]');
    var topic = form.querySelector('[name="target_topic"]');
    var concept = form.querySelector('[name="target_concept"]');
    var blueprint = form.querySelector('[name="blueprint_node"]');
    var blueprintTarget = form.querySelector('[name="target_blueprint_node"]');
    function filterSelect(select, attribute, expected) {
        if (!select) return;
        Array.prototype.forEach.call(select.options, function (option) {
            if (!option.value) return;
            var applicable = !attribute || !option.dataset[attribute] || option.dataset[attribute] === expected;
            option.hidden = !applicable;
            if (!applicable && option.selected) select.value = '';
        });
    }
    function filter() {
        var boardId = board.value;
        filterSelect(subject, 'board', boardId);
        filterSelect(domain, 'subject', subject ? subject.value : '');
        filterSelect(topic, 'domain', domain ? domain.value : '');
        filterSelect(concept, 'topic', topic ? topic.value : '');
        [blueprint, blueprintTarget].forEach(function (select) {
            if (!select) return;
            Array.prototype.forEach.call(select.options, function (option) {
                if (!option.value) return;
                var applicable = option.dataset.board ? option.dataset.board === boardId : true;
                option.hidden = !applicable;
                if (!applicable && option.selected) select.value = '';
            });
        });
    }
    board.addEventListener('change', filter);
    [subject, domain, topic].forEach(function (select) { if (select) select.addEventListener('change', filter); });
    filter();
});
</script>
<?php endif; ?>
