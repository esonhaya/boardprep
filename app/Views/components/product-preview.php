<?php
$preview = (string) ($preview ?? 'dashboard');
$previewData = [
    'dashboard' => ['label'=>'Dashboard preview','title'=>'Welcome back','body'=>'<div class="product-preview-summary"><div class="product-preview-ring"><strong>72%</strong><small>readiness</small></div><div><b>Keep your momentum</b><span>4 completed quizzes</span><span>Next focus: Assessment</span></div></div><div class="product-preview-chart"><i style="height:38%"></i><i style="height:54%"></i><i style="height:46%"></i><i style="height:72%"></i><i style="height:82%"></i></div><div class="product-preview-cards"><span><b>Weak areas</b><small>3 topics to revisit</small></span><span><b>Recent result</b><small>84% · Practice</small></span></div>'],
    'practice' => ['label'=>'Practice preview','title'=>'LET · General Education','body'=>'<div class="product-preview-context"><b>Mathematics</b><span>Question 6 of 10 · Medium</span></div><p class="product-preview-question">Which expression represents five more than twice n?</p><div class="product-preview-options"><span>A. 2n + 5</span><span>B. 5n + 2</span><span>C. 2(n + 5)</span><span>D. n + 10</span></div><div class="product-preview-progress"><span style="width:60%"></span></div>'],
    'insights' => ['label'=>'Results and insights preview','title'=>'Your study signals','body'=>'<div class="product-preview-score"><strong>84%</strong><span>Recent practice score</span></div><div class="product-preview-insight"><b>Focus next</b><span>Assessment · 61% accuracy</span><em>Practice topic</em></div><div class="product-preview-insight is-positive"><b>Strength</b><span>English · 88% accuracy</span><em>Keep building</em></div>'],
];
$data = $previewData[$preview] ?? $previewData['dashboard'];
?>
<div class="ui-product-preview product-preview product-preview-<?= htmlspecialchars($preview) ?>" data-ui-component="product-preview" data-preview-state="illustrative" aria-label="<?= htmlspecialchars($data['label']) ?>">
    <div class="ui-product-preview-window">
        <div class="product-preview-header"><span class="product-preview-brand"><i>BP</i> BoardPrep</span><span class="product-preview-dots">•••</span></div>
        <div class="product-preview-title"><small><?= htmlspecialchars($data['label']) ?></small><b><?= htmlspecialchars($data['title']) ?></b></div>
        <div class="ui-product-preview-content"><?= $data['body'] ?></div>
    </div>
</div>
