<h2>Taxonomy Manager</h2>

<hr>

<p>
The taxonomy is automatically generated from the question bank.
</p>

<form method="post" action="/taxonomy/rebuild">

    <button type="submit">

        Rebuild Taxonomy

    </button>

</form>

<hr>

<h3>Summary</h3>

<ul>

    <li>
        Domains:
        <?= count($domains) ?>
    </li>

    <li>
        Topics:
        <?= count($topics) ?>
    </li>

    <li>
        Concepts:
        <?= count($concepts) ?>
    </li>

</ul>

<hr>

<h3>Domains</h3>

<ul>

<?php foreach ($domains as $domain): ?>

    <li>

        <?= htmlspecialchars(
            is_array($domain)
                ? ($domain["name"] ?? "")
                : $domain
        ) ?>

    </li>

<?php endforeach; ?>

</ul>

<hr>

<h3>Topics</h3>

<ul>

<?php foreach ($topics as $topic): ?>

    <li>

        <?= htmlspecialchars(
            is_array($topic)
                ? ($topic["name"] ?? "")
                : $topic
        ) ?>

    </li>

<?php endforeach; ?>

</ul>

<hr>

<h3>Concepts</h3>

<ul>

<?php foreach ($concepts as $concept): ?>

    <li>

        <?= htmlspecialchars(
            is_array($concept)
                ? ($concept["name"] ?? "")
                : $concept
        ) ?>

    </li>

<?php endforeach; ?>

</ul>
