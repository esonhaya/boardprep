<aside class="developer-sidebar">
    <?php $developerPath = parse_url($_SERVER["REQUEST_URI"] ?? "/developer", PHP_URL_PATH) ?: "/developer"; ?>
    <div class="developer-brand">
        <?php $brandClass = "brand developer-brand-link"; $brandHref = "/developer"; require __DIR__ . "/../components/brand.php"; ?>
        <small>Content Studio</small>
    </div>
    <button class="mobile-menu-button developer-menu-button" type="button" aria-expanded="false" aria-controls="developer-nav"><span class="sr-only">Toggle developer navigation</span><span aria-hidden="true">☰</span></button>

    <nav id="developer-nav">

        <h3>Overview</h3>

        <a href="/developer"<?= $developerPath === "/developer" ? ' aria-current="page"' : "" ?>>

            Dashboard

        </a>

        <hr>

        <h3>

            Content

        </h3>

        <a href="/question-editor/create"<?= $developerPath === "/question-editor/create" ? ' aria-current="page"' : "" ?>>

            Create Question

        </a>

        <a href="/question-editor"<?= $developerPath === "/question-editor" ? ' aria-current="page"' : "" ?>>

            Questions

        </a>

        <a href="/coverage">

            Coverage

        </a>

        <a href="/question-quality">

            Quality

        </a>

        <a href="/question-inspector">

            Inspector

        </a>

        <hr>

        <h3>

            Repository

        </h3>

        <a href="/boards">

            Boards

        </a>

        <a href="/subjects">

            Subjects

        </a>

        <a href="/taxonomy">

            Taxonomy

        </a>

        <a href="/blueprints">

            Blueprints

        </a>

        <hr>

        <h3>

            Content operations

        </h3>

        <a href="/question-import">

            Import

        </a>

        <a href="/question-export">

            Export

        </a>

        <hr>

        <h3>

            Developer Tools

        </h3>

        <a href="/developer?action=analyze">

            Analyze Repository

        </a>

        <a href="/developer?action=fix-all">

            Fix Everything

        </a>

    </nav>

</aside>
