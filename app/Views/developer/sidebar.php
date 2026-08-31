<aside class="developer-sidebar">
    <?php $developerPath = parse_url($_SERVER["REQUEST_URI"] ?? "/developer", PHP_URL_PATH) ?: "/developer"; ?>
    <div class="developer-brand">
        <?php $brandClass = "brand developer-brand-link"; $brandHref = "/developer"; require __DIR__ . "/../components/brand.php"; ?>
        <small>Content Studio</small>
    </div>
    <button class="mobile-menu-button developer-menu-button" type="button" aria-expanded="false" aria-controls="developer-nav"><span class="sr-only">Toggle developer navigation</span><span aria-hidden="true">☰</span></button>

    <nav id="developer-nav">

        <h3>

            Dashboard

        </h3>

        <a href="/developer"<?= $developerPath === "/developer" ? ' aria-current="page"' : "" ?>>

            Dashboard

        </a>

        <hr>

        <h3>

            Question Management

        </h3>

        <a href="/question-editor/create"<?= $developerPath === "/question-editor/create" ? ' aria-current="page"' : "" ?>>

            Question Workspace

        </a>

        <a href="/question-editor"<?= $developerPath === "/question-editor" ? ' aria-current="page"' : "" ?>>

            Question Library

        </a>

        <a href="/coverage">

            Coverage Matrix

        </a>

        <a href="/question-quality">

            Question Quality

        </a>

        <a href="/question-inspector">

            Question Inspector

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

            Import / Export

        </h3>

        <a href="/question-import">

            Import Questions

        </a>

        <a href="/question-export">

            Export Questions

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
