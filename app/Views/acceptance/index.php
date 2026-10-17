<a class="skip-link" href="#main-content">Skip to acceptance tests</a>
<header class="ac-header">
    <a class="ac-brand" href="/developer/acceptance" aria-label="BoardPrep Human Acceptance Center home">
        <span class="ac-brand-mark" aria-hidden="true">B</span>
        <span><strong>BoardPrep</strong><small>Human Acceptance Center</small></span>
    </a>
    <div class="ac-header-status"><span class="status-dot" aria-hidden="true"></span><span>Private local test session</span></div>
</header>

<main id="main-content" class="ac-shell">
    <div id="notice" class="ac-notice" role="status" aria-live="polite" hidden></div>
    <noscript><p class="ac-notice error">Turn on JavaScript to save and resume your guided acceptance session.</p></noscript>

    <section class="ac-welcome panel" aria-labelledby="welcome-title">
        <p class="eyebrow">A guided check of the real learner experience</p>
        <h1 id="welcome-title">Try BoardPrep as a learner</h1>
        <p class="lede">Open each real learner page in a second tab, try it yourself, then record what worked or what you would change. Your notes save on this device.</p>
        <div class="privacy-note"><strong>Safe preview:</strong> this center runs locally against an isolated copy of the app. It does not use your usual learner records. Your feedback stays in a private folder outside the repository.</div>
    </section>

    <nav class="ac-tabs" aria-label="Acceptance Center sections">
        <button type="button" class="tab-button active" data-screen="dashboard">Home</button>
        <button type="button" class="tab-button" data-screen="overview">All tests</button>
        <button type="button" class="tab-button" data-screen="general">General feedback</button>
        <button type="button" class="tab-button" data-screen="export">Review &amp; export</button>
    </nav>

    <div class="ac-workspace">
        <aside class="ac-sidebar panel" aria-label="Test session details">
            <div class="sidebar-heading"><h2>Your test session</h2><span id="saveIndicator" class="save-indicator">Loading…</span></div>
            <label for="testerAlias">Your name or alias <span class="optional">optional</span></label>
            <input id="testerAlias" name="tester_alias" maxlength="100" autocomplete="nickname" placeholder="For example, Ana">
            <label for="deviceInfo">Phone or device <span class="optional">optional</span></label>
            <input id="deviceInfo" name="device" maxlength="180" placeholder="For example, Pixel 8 / Android 15">
            <label for="operatingSystemInfo">Operating system <span class="optional">auto-detected; check if correct</span></label>
            <input id="operatingSystemInfo" name="operating_system" maxlength="120" placeholder="For example, Android 15">
            <label for="browserInfo">Browser <span class="optional">optional</span></label>
            <input id="browserInfo" name="browser" maxlength="180" placeholder="Your browser name and version">
            <label for="viewportInfo">Current viewport <span class="optional">CSS pixels</span></label>
            <input id="viewportInfo" name="viewport" maxlength="32" readonly aria-describedby="viewportHint">
            <p id="viewportHint" class="small-note">This updates when the window size changes. Add other sizes you tried to a test note.</p>
            <div class="session-facts">
                <div><span>Build being tested</span><code id="buildSha">Loading…</code></div>
                <div><span>Test date (UTC)</span><strong id="testDate">—</strong></div>
                <div><span>Environment</span><strong>Isolated local preview</strong></div>
                <div><label for="savedSessions">Saved sessions</label><select id="savedSessions" aria-label="Resume a saved test session"></select></div>
            </div>
            <button type="button" id="newSession" class="button secondary full-width">Start a new session</button>
            <p class="small-note">Starting a new session keeps every earlier report. Build changes are shown before you record results.</p>
        </aside>

        <div class="ac-content">
            <section class="screen panel" data-screen-panel="dashboard" aria-labelledby="dashboard-title">
                <div class="section-topline"><p class="eyebrow">Your progress</p><span id="buildWarning" class="build-warning" hidden></span></div>
                <h2 id="dashboard-title">Your next step</h2>
                <div class="progress-block">
                    <div class="progress-label"><span id="progressText">0 of 12 tests have a recorded result</span><span id="progressPercent">0%</span></div>
                    <progress id="overallProgress" max="12" value="0">0 of 12</progress>
                </div>
                <article class="next-test-card">
                    <div class="next-test-copy"><p class="eyebrow" id="nextCaseId">MVP-01</p><h3 id="nextCaseTitle">Loading your saved session…</h3><p id="nextCaseWhy"></p></div>
                    <button type="button" id="launchNextTest" class="button primary large-button" disabled>Launch next test</button>
                </article>
                <p class="two-tab-hint"><strong>How this works:</strong> the learner page opens in another tab. Leave this center open, try the steps there, then return here to save your result and comments.</p>
                <div class="dashboard-actions">
                    <button type="button" class="button secondary" data-open-case="MVP-01">Open test guide</button>
                    <button type="button" class="button secondary" data-screen="overview">See all 12 tests</button>
                </div>
                <div class="quick-summary" id="quickSummary" aria-live="polite"></div>
            </section>

            <section class="screen panel" data-screen-panel="case" aria-labelledby="caseTitle" hidden>
                <div class="case-heading"><div><p class="eyebrow" id="caseIdLabel">MVP-01</p><h2 id="caseTitle"></h2></div><span id="caseOutcomeBadge" class="outcome-badge">NOT RUN</span></div>
                <p id="caseWhy" class="case-why"></p>
                <div class="instruction-grid">
                    <section><h3>Where to start</h3><p id="caseStart"></p></section>
                    <section><h3>What to do</h3><p id="caseActions"></p></section>
                    <section class="success-box"><h3>What success looks like</h3><p id="caseSuccess"></p></section>
                    <section class="watch-box"><h3>Watch for</h3><p id="caseWatchFor"></p></section>
                </div>
                <section class="route-launcher" aria-labelledby="routeLauncherTitle">
                    <div><h3 id="routeLauncherTitle">Open a learner page</h3><p>These links reuse one separate learner tab. Your guide stays open here.</p></div>
                    <div id="routeLinks" class="route-links"></div>
                    <p class="route-recorded" id="routesOpened"></p>
                    <button type="button" id="returnToGuide" class="button secondary">Continue / return to this test guide</button>
                </section>
                <section class="result-editor" aria-labelledby="resultTitle">
                    <h3 id="resultTitle" tabindex="-1">How did it go?</h3>
                    <p>Choose what you actually observed. Results save when selected; PASS also needs your personal confirmation. Suggestions can be useful even when the test passes.</p>
                    <fieldset class="outcome-options"><legend>Test result</legend>
                        <label><input type="radio" name="humanResult" value="PASS"><span><strong>PASS</strong><small>I tried it and it worked for me</small></span></label>
                        <label><input type="radio" name="humanResult" value="FAIL"><span><strong>FAIL</strong><small>I found a problem</small></span></label>
                        <label><input type="radio" name="humanResult" value="BLOCKED"><span><strong>BLOCKED</strong><small>I could not complete this test</small></span></label>
                        <label><input type="radio" name="humanResult" value="NOT_APPLICABLE"><span><strong>NOT APPLICABLE</strong><small>This test does not apply; explain why below</small></span></label>
                        <label><input type="radio" name="humanResult" value="NOT_RUN"><span><strong>NOT RUN</strong><small>I have not completed it yet</small></span></label>
                    </fieldset>
                    <label class="confirm-pass"><input id="confirmPass" type="checkbox"><span>I personally tried the steps and saw the success conditions.</span></label>
                    <button type="button" id="saveOutcome" class="button primary">Save this result</button>
                    <span class="muted-inline">A result is never set by automated tests.</span>
                </section>
                <section class="feedback-editor" aria-labelledby="feedbackTitle">
                    <h3 id="feedbackTitle">Add a note about this test</h3>
                    <p>Tell us anything you noticed in your own words. You can add more than one note.</p>
                    <div class="feedback-fields" data-feedback-fields="case">
                        <label>Kind of feedback<select data-field="category"></select></label>
                        <label>Priority, if you know<select data-field="priority"></select></label>
                        <label class="wide-field">What did you notice?<textarea data-field="comments" rows="3" maxlength="20000" placeholder="Describe what stood out. Everyday words are fine."></textarea></label>
                        <label>What did you expect?<textarea data-field="expected_behavior" rows="2" maxlength="8000"></textarea></label>
                        <label>What happened instead?<textarea data-field="actual_behavior" rows="2" maxlength="8000"></textarea></label>
                        <label>How would you improve it?<textarea data-field="suggested_improvement" rows="2" maxlength="8000"></textarea></label>
                        <label>What steps led to it?<textarea data-field="reproduction_steps" rows="2" maxlength="12000"></textarea></label>
                        <label>Screenshot or evidence reference <span class="optional">optional</span><input data-field="evidence_reference" maxlength="2000" placeholder="A filename or short note; no learner records"></label>
                    </div>
                    <p class="privacy-note compact">Do not paste passwords, browser cookies, private learner records, or exported learner data. The report includes only feedback you enter here.</p>
                    <div class="editor-actions"><span id="draftStatus" class="save-indicator">Draft saves as you type</span><button type="button" id="addObservation" class="button secondary">Save this note</button></div>
                    <div id="savedObservations" class="saved-observations"></div>
                </section>
                <div class="case-navigation">
                    <button type="button" id="previousCase" class="button secondary">Previous test</button>
                    <button type="button" id="skipCase" class="button secondary">Skip for now</button>
                    <button type="button" id="nextCase" class="button primary">Next test</button>
                </div>
            </section>

            <section class="screen panel" data-screen-panel="overview" aria-labelledby="overviewTitle" hidden>
                <p class="eyebrow">Your twelve guided tests</p><h2 id="overviewTitle">All tests</h2>
                <p>Open a guide to see the steps, then launch its learner page when you are ready. Skipping does not change a test result.</p>
                <div id="caseList" class="case-list"></div>
            </section>

            <section class="screen panel" data-screen-panel="general" aria-labelledby="generalTitle" hidden>
                <p class="eyebrow">Anything else you noticed?</p><h2 id="generalTitle">General feedback</h2>
                <p>Use this for ideas or problems that do not fit one of the twelve tests. Add as many separate notes as you need.</p>
                <div class="feedback-fields" data-feedback-fields="general">
                    <label>Kind of feedback<select data-field="category"></select></label>
                    <label>Priority, if you know<select data-field="priority"></select></label>
                    <label class="wide-field">What did you notice?<textarea data-field="comments" rows="4" maxlength="20000" placeholder="Share what you noticed in your own words."></textarea></label>
                    <label>What did you expect?<textarea data-field="expected_behavior" rows="2" maxlength="8000"></textarea></label>
                    <label>What happened instead?<textarea data-field="actual_behavior" rows="2" maxlength="8000"></textarea></label>
                    <label>How would you improve it?<textarea data-field="suggested_improvement" rows="2" maxlength="8000"></textarea></label>
                    <label>What steps led to it?<textarea data-field="reproduction_steps" rows="2" maxlength="12000"></textarea></label>
                    <label>Screenshot or evidence reference <span class="optional">optional</span><input data-field="evidence_reference" maxlength="2000"></label>
                </div>
                <p class="privacy-note compact">Do not include passwords, browser cookies, or private learner records.</p>
                <div class="editor-actions"><span id="generalDraftStatus" class="save-indicator">Draft saves as you type</span><button type="button" id="addGeneralFeedback" class="button secondary">Save this note</button></div>
                <div id="generalObservations" class="saved-observations"></div>
            </section>

            <section class="screen panel" data-screen-panel="export" aria-labelledby="exportTitle" hidden>
                <p class="eyebrow">Your notes, ready to share</p><h2 id="exportTitle" tabindex="-1">Review and export</h2>
                <p>Preview your saved feedback first. The export contains your observations and test results, not BoardPrep learner data or browser cookies.</p>
                <div class="export-summary" id="exportSummary"></div>
                <button type="button" id="refreshPreview" class="button secondary">Refresh report preview</button>
                <label for="reportPreview" class="preview-label">Report preview</label>
                <pre id="reportPreview" class="report-preview" tabindex="0">Choose “Refresh report preview” to prepare your report.</pre>
                <div class="export-actions">
                    <button type="button" id="copyMarkdown" class="button primary" disabled>Export Feedback for ChatGPT · Copy report</button>
                    <button type="button" id="downloadMarkdown" class="button secondary" disabled>Download Markdown</button>
                    <button type="button" id="downloadJson" class="button secondary" disabled>Download JSON</button>
                    <button type="button" id="shareReport" class="button secondary" disabled>Share report</button>
                </div>
                <p id="exportStatus" class="save-indicator" aria-live="polite"></p>
                <p class="small-note">Copying or downloading does not send the report to ChatGPT. Choose Share and select an app, or paste the copied report yourself.</p>
            </section>
        </div>
    </div>
</main>
<footer class="ac-footer">Human observations only. Automated checks never mark these tests as passed.</footer>
