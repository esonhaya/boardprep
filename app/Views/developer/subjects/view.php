<h1>
<?= htmlspecialchars($subject["name"]) ?>
</h1>

<p>
<?= htmlspecialchars($subject["description"] ?? "No description provided.") ?>
</p>

<hr>

<div class="workspace-nav">

<a href="#">
Overview
</a>

|

<a href="#">
Repository
</a>

|

<a href="#">
Boards
</a>

|

<a href="#">
Blueprints
</a>

|

<a href="#">
Analytics
</a>

|

<a href="#">
Settings
</a>

</div>

<hr>

<h2>
Overview
</h2>

<table>

<tr>
<td>
Status
</td>

<td>
<?= htmlspecialchars(
    ucfirst(
        $subject["status"] ?? "active"
    )
) ?>
</td>
</tr>

<tr>
<td>
ID
</td>

<td>
<?= htmlspecialchars($subject["id"]) ?>
</td>
</tr>

<tr>
<td>
Boards
</td>

<td>
Coming Soon
</td>
</tr>

<tr>
<td>
Questions
</td>

<td>
Coming Soon
</td>
</tr>

<tr>
<td>
Domains
</td>

<td>
Coming Soon
</td>
</tr>

<tr>
<td>
Topics
</td>

<td>
Coming Soon
</td>
</tr>

<tr>
<td>
Concepts
</td>

<td>
Coming Soon
</td>
</tr>

</table>

<br>

<a href="/subject/edit?id=<?= urlencode($subject["id"]) ?>">
Edit Subject
</a>

|

<a href="/subjects">
Back to Subjects
</a>
