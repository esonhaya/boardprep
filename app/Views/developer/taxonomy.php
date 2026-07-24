<h2>Taxonomy Manager</h2>

<hr>

<h3>Add Domain</h3>

<form method="post" action="?page=taxonomy&action=add-domain">

<input
type="text"
name="name"
required>

<button>

Add

</button>

</form>

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

<h3>Add Topic</h3>

<form method="post" action="?page=taxonomy&action=add-topic">

<input
type="text"
name="name"
required>

<button>

Add

</button>

</form>

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

<h3>Add Concept</h3>

<form method="post" action="?page=taxonomy&action=add-concept">

<input
type="text"
name="name"
required>

<button>

Add

</button>

</form>

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
