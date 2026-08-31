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

<div class="card metric-list">

<div class="metric-row"><strong>
Status
</strong><span>
<?= htmlspecialchars(
    ucfirst(
        $subject["status"] ?? "active"
    )
) ?>
</span></div>

<div class="metric-row"><strong>
ID
</strong><span>
<?= htmlspecialchars($subject["id"]) ?>
</span></div>

<div class="metric-row"><strong>
Boards
</strong><span>
Coming Soon
</span></div>

<div class="metric-row"><strong>
Questions
</strong><span>
Coming Soon
</span></div>

<div class="metric-row"><strong>
Domains
</strong><span>
Coming Soon
</span></div>

<div class="metric-row"><strong>
Topics
</strong><span>
Coming Soon
</span></div>

<div class="metric-row"><strong>
Concepts
</strong><span>
Coming Soon
</span></div>

</div>

<br>

<a href="/subject/edit?id=<?= urlencode($subject["id"]) ?>">
Edit Subject
</a>

|

<a href="/subjects">
Back to Subjects
</a>
