<?php

$context = $context ?? [];

$breadcrumbs = [];

if (!empty($context["board"])) {
    $breadcrumbs[] = $context["board"]["name"];
}

if (!empty($context["subject"])) {
    $breadcrumbs[] = $context["subject"]["name"];
}

if (!empty($context["domain"])) {
    $breadcrumbs[] = $context["domain"]["name"];
}

if (!empty($context["topic"])) {
    $breadcrumbs[] = $context["topic"]["name"];
}

if (!empty($context["concept"])) {
    $breadcrumbs[] = $context["concept"]["name"];
}

?>

<div
style="
display:flex;
gap:20px;
align-items:flex-start;
"
>

<aside
style="
width:260px;
border:1px solid #ccc;
padding:15px;
"
>

<h3>

Developer Workspace

</h3>

<hr>

<h4>

Current Context

</h4>

<?php if (empty($breadcrumbs)): ?>

<p>

Global Workspace

</p>

<?php else: ?>

<p>

<?= htmlspecialchars(
    implode(
        " > ",
        $breadcrumbs
    )
) ?>

</p>

<?php endif; ?>

<hr>

<h4>

Quick Actions

</h4>

<ul>

<li>

<a href="#">

Repository Health

</a>

</li>

<li>

<a href="#">

Coverage

</a>

</li>

<li>

<a href="#">

Blueprints

</a>

</li>

<li>

<a href="#">

Search Questions

</a>

</li>

<li>

<a href="#">

Import Questions

</a>

</li>

</ul>

<hr>

<h4>

Workspace

</h4>

<p>

Mode:
<strong>

<?= htmlspecialchars(
    ucfirst(
        $contentMode ?? "create"
    )
) ?>

</strong>

</p>

</aside>

<main
style="
flex:1;
"
>

<h2>

Question Workspace

</h2>

<?php if (!empty($breadcrumbs)): ?>

<p>

<?= htmlspecialchars(
    implode(
        " > ",
        $breadcrumbs
    )
) ?>

</p>

<hr>

<?php endif; ?>

<?php

require __DIR__
    . '/../question/form.php';

?>

</main>

</div>
