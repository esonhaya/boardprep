<?php

$contentMode =
    $contentMode ?? "create";

$context =
    $context ?? [];

?>

<div
style="
display:flex;
gap:24px;
align-items:flex-start;
"
>

<aside
style="
width:240px;
border:1px solid #ccc;
padding:15px;
"
>

<?php

require __DIR__
    . "/partials/workspace-sidebar.php";

?>

</aside>

<main
style="
flex:1;
"
>

<?php

require __DIR__
    . "/partials/workspace-header.php";

?>

<?php

require __DIR__
    . "/question-form.php";

?>

</main>

</div>
