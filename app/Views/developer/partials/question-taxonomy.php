<?php if (empty($context["subject"])): ?>

<label>

Subject

</label>

<br>

<select
name="subject"
id="subject"
required
>

<option value="">

Select Subject

</option>

<?php foreach ($subjects as $subject): ?>

<option
value="<?= htmlspecialchars($subject["id"]) ?>"
<?= (($question["taxonomy"]["subject_id"] ?? "") === ($subject["id"] ?? "")) ? "selected" : "" ?>
>

<?= htmlspecialchars($subject["name"]) ?>

</option>

<?php endforeach; ?>

</select>

<br><br>

<?php endif; ?>

<?php if (empty($context["domain"])): ?>

<label>

Domain

</label>

<br>

<select
name="domain"
id="domain"
required
>

<option value="">

Select Domain

</option>

<?php foreach ($domains as $domain): ?>

<option
value="<?= htmlspecialchars($domain["id"]) ?>"
<?= (($question["taxonomy"]["domain_id"] ?? "") === ($domain["id"] ?? "")) ? "selected" : "" ?>
>

<?= htmlspecialchars($domain["name"]) ?>

</option>

<?php endforeach; ?>

</select>

<br><br>

<?php endif; ?>

<?php if (empty($context["topic"])): ?>

<label>

Topic

</label>

<br>

<select
name="topic"
id="topic"
required
>

<option value="">

Select Topic

</option>

<?php foreach ($topics as $topic): ?>

<option
value="<?= htmlspecialchars($topic["id"]) ?>"
<?= (($question["taxonomy"]["topic_id"] ?? "") === ($topic["id"] ?? "")) ? "selected" : "" ?>
>

<?= htmlspecialchars($topic["name"]) ?>

</option>

<?php endforeach; ?>

</select>

<br><br>

<?php endif; ?>

<?php if (empty($context["concept"])): ?>

<label>

Concept

</label>

<br>

<select
name="concept"
id="concept"
required
>

<option value="">

Select Concept

</option>

<?php foreach ($concepts as $concept): ?>

<option
value="<?= htmlspecialchars($concept["id"]) ?>"
<?= (($question["taxonomy"]["concept_id"] ?? "") === ($concept["id"] ?? "")) ? "selected" : "" ?>
>

<?= htmlspecialchars($concept["name"]) ?>

</option>

<?php endforeach; ?>

</select>

<br><br>

<?php endif; ?>

<label>

Difficulty

</label>

<br>

<select
name="difficulty"
required
>

<option
value="easy"
<?= (($question["difficulty"] ?? "") === "easy") ? "selected" : "" ?>
>

Easy

</option>

<option
value="medium"
<?= (($question["difficulty"] ?? "") === "medium") ? "selected" : "" ?>
>

Medium

</option>

<option
value="hard"
<?= (($question["difficulty"] ?? "") === "hard") ? "selected" : "" ?>
>

Hard

</option>

</select>

<br><br>
