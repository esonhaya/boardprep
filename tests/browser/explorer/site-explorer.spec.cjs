'use strict';

const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { test, expect } = require('@playwright/test');

const APP = 'http://127.0.0.1:4174';
const ALLOWED_PATHS = new Set([
  '/', '/exams', '/quiz', '/history', '/history/result', '/study', '/study-library',
  '/flashcards', '/flashcards/review', '/progress', '/profile', '/privacy',
  '/dashboard', '/developer', '/developer/question-origin',
]);
const BOARDS = [
  ['LET', 'let'], ['CSE', 'civil-service'], ['CLE', 'criminologist'], ['NLE', 'nursing'],
  ['PLE', 'psychometrician'], ['CE', 'civil-engineering'], ['CPA', 'cpa'],
  ['ME', 'mechanical-engineering'], ['REE', 'registered-electrical-engineering'], ['ARCH', 'architecture'],
];
const TOP_LEVEL = ['/', '/exams', '/study', '/study-library', '/flashcards', '/history', '/progress', '/profile', '/privacy'];

function seededRandom(seed) {
  let value = seed >>> 0;
  return () => {
    value = (value + 0x6D2B79F5) | 0;
    let next = Math.imul(value ^ (value >>> 15), 1 | value);
    next = (next + Math.imul(next ^ (next >>> 7), 61 | next)) ^ next;
    return ((next ^ (next >>> 14)) >>> 0) / 4294967296;
  };
}

function seededShuffle(values, seed) {
  const random = seededRandom(seed);
  const result = [...values];
  for (let index = result.length - 1; index > 0; index -= 1) {
    const other = Math.floor(random() * (index + 1));
    [result[index], result[other]] = [result[other], result[index]];
  }
  return result;
}

function allowedUrl(raw) {
  try {
    const url = new URL(raw, APP);
    if (url.origin !== APP || !ALLOWED_PATHS.has(url.pathname)) return null;
    if (/^(?:clear|delete|logout|reset|remove)$/i.test(url.searchParams.get('action') || '')) return null;
    return `${url.origin}${url.pathname}${url.search}`;
  } catch {
    return null;
  }
}

function expectedNegative(url) {
  const parsed = new URL(url);
  return (parsed.pathname === '/quiz' && parsed.searchParams.get('action') === 'not-a-real-action')
    || (parsed.pathname === '/quiz' && parsed.searchParams.get('topic') === '__no_such_topic__')
    || (parsed.pathname === '/flashcards/review' && parsed.searchParams.get('include_new') === '0');
}

function questionIndex() {
  const root = path.resolve(__dirname, '../../..');
  const questions = JSON.parse(fs.readFileSync(path.join(root, 'storage/questions.json'), 'utf8'));
  const byId = new Map(questions.map((question) => [String(question.id), question]));
  return { root, byId };
}

function markdown(evidence) {
  const lines = [
    '# BoardPrep bounded site explorer',
    '',
    `STATUS=${evidence.status}`,
    `TESTED_COMMIT=${evidence.tested_commit}`,
    `SEED=${evidence.seed}`,
    `MODE=${evidence.mode}`,
    `VIEWPORT=${evidence.viewport}`,
    `PAGES_VISITED=${evidence.routes_visited.length}`,
    `ACTIONS_EXECUTED=${evidence.actions_executed}`,
    `BOARDS_COVERED=${evidence.boards_covered.join(', ')}`,
    '',
    '## Confirmed defects',
    evidence.confirmed_defects.length ? evidence.confirmed_defects.map((item) => `- ${item.code}: ${item.observed}`).join('\n') : '- None observed.',
    '',
    '## Review cues',
    evidence.review_cues.length ? evidence.review_cues.map((item) => `- ${item.code}: ${item.observed}`).join('\n') : '- None observed.',
    '',
    '## Routes visited',
    evidence.routes_visited.map((route) => `- ${route}`).join('\n') || '- None.',
    '',
    '## Untested or blocked paths',
    evidence.untested_paths.map((item) => `- ${item}`).join('\n') || '- None.',
    '',
    'Evidence includes a reproduction summary, limited screenshots and DOM snapshots for confirmed defects, plus sanitized console/network diagnostics. Automated results are not human acceptance.',
    '',
  ];
  return lines.join('\n');
}

test('bounded stateful BoardPrep journey exploration', async ({ page }, testInfo) => {
  test.skip(process.env.BOARDPREP_RUN_EXPLORER !== '1', 'Run this explorer with npm run test:explore.');
  test.skip(testInfo.project.name !== 'desktop', 'The bounded explorer uses one supported desktop Chromium worker.');

  const mode = process.env.BOARDPREP_EXPLORER_MODE === 'smoke' ? 'smoke' : 'full';
  const seed = Number.parseInt(process.env.BOARDPREP_EXPLORER_SEED || '691', 10) || 691;
  const maxPages = Number.parseInt(process.env.BOARDPREP_EXPLORER_MAX_PAGES || (mode === 'smoke' ? '60' : '100'), 10);
  const maxActions = Number.parseInt(process.env.BOARDPREP_EXPLORER_MAX_ACTIONS || (mode === 'smoke' ? '180' : '320'), 10);
  const maxDurationMs = Number.parseInt(process.env.BOARDPREP_EXPLORER_MAX_DURATION_MS || (mode === 'smoke' ? '180000' : '540000'), 10);
  const started = Date.now();
  const evidenceDir = path.resolve(process.env.BOARDPREP_EXPLORER_EVIDENCE_DIR || path.join(os.tmpdir(), 'boardprep-site-explorer'));
  fs.mkdirSync(evidenceDir, { recursive: true });
  const { root, byId } = questionIndex();
  const evidence = {
    schema_version: 1,
    report_type: 'BOUNDED_SITE_EXPLORER',
    status: 'RUNNING',
    tested_commit: execFileSync('git', ['rev-parse', 'HEAD'], { cwd: root, encoding: 'utf8' }).trim(),
    seed,
    mode,
    limits: { max_pages: maxPages, max_actions: maxActions, max_duration_ms: maxDurationMs, concurrency: 1 },
    viewport: '1366x768',
    routes_visited: [],
    forms_exercised: [],
    transitions: [],
    board_subject_combinations: [],
    boards_covered: [],
    actions_executed: 0,
    confirmed_defects: [],
    review_cues: [],
    untested_paths: [],
    console_errors: [],
    page_errors: [],
    failed_internal_requests: [],
    http_errors: [],
    question_metadata_checks: [],
  };
  const routeKeys = new Set();
  const queued = new Set();
  const queue = [];
  const screenshotByDefect = new Set();
  let activeUrl = `${APP}/`;

  const addDefect = async (code, observed, details = {}) => {
    const defectRoute = String(details.route || activeUrl);
    const item = { code, observed: String(observed).slice(0, 600), route: defectRoute, ...details };
    evidence.confirmed_defects.push(item);
    const safeCode = code.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    if (!screenshotByDefect.has(safeCode)) {
      screenshotByDefect.add(safeCode);
      const stem = `${safeCode}-${evidence.confirmed_defects.length}`;
      const screenshot = path.join(evidenceDir, `${stem}.png`);
      const dom = path.join(evidenceDir, `${stem}.html`);
      await page.screenshot({ path: screenshot, fullPage: true }).catch(() => undefined);
      await fs.promises.writeFile(dom, await page.locator('body').innerHTML().catch(() => ''), 'utf8');
      item.screenshot = path.basename(screenshot);
      item.dom_snapshot = path.basename(dom);
      item.reproduction_steps = [`Open ${defectRoute}`, `Observe: ${item.observed}`];
    }
  };

  const inspectPage = async (url, response) => {
    activeUrl = url;
    if (!routeKeys.has(url)) {
      routeKeys.add(url);
      evidence.routes_visited.push(url);
    }
    const state = await page.evaluate(() => {
      const width = document.documentElement.scrollWidth;
      const viewport = document.documentElement.clientWidth;
      const visible = (element) => {
        const rect = element.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0 && getComputedStyle(element).visibility !== 'hidden';
      };
      const fields = [...document.querySelectorAll('main input:not([type="hidden"]), main select, main textarea')].filter(visible);
      const clipped = [...document.querySelectorAll('main button, main a.button, main select, main input:not([type="hidden"])')]
        .filter(visible).filter((element) => {
          const rect = element.getBoundingClientRect();
          return rect.left < -1 || rect.right > viewport + 1;
        }).map((element) => (element.innerText || element.getAttribute('aria-label') || element.tagName).trim().slice(0, 100));
      const missingLabels = fields.filter((element) => !element.labels?.length && !element.getAttribute('aria-label') && !element.getAttribute('aria-labelledby'))
        .map((element) => element.outerHTML.slice(0, 120));
      return {
        width,
        viewport,
        clipped,
        missingLabels,
        forms: [...document.forms].map((form) => ({ action: form.action, method: form.method, fields: [...form.elements].map((field) => field.name).filter(Boolean) })),
        links: [...document.querySelectorAll('a[href]')].map((anchor) => anchor.href),
      };
    });
    if (state.width > state.viewport + 2) await addDefect('HORIZONTAL_OVERFLOW', `Document width ${state.width}px exceeds viewport ${state.viewport}px by more than 2px.`);
    if (state.clipped.length) await addDefect('CLIPPED_PRIMARY_CONTROL', `Visible controls extend beyond the viewport: ${state.clipped.join(', ')}.`);
    if (state.missingLabels.length) await addDefect('MISSING_FORM_LABEL', `Visible learner fields have no programmatic label: ${state.missingLabels.join('; ')}.`);
    for (const form of state.forms) {
      const actionPath = (() => { try { return new URL(form.action).pathname; } catch { return ''; } })();
      if (['/quiz', '/flashcards', '/flashcards/review', '/profile/preferences'].includes(actionPath)) {
        evidence.forms_exercised.push({ route: url, action: actionPath, method: form.method, fields: form.fields });
      }
    }
    for (const link of state.links) {
      const approved = allowedUrl(link);
      if (approved && !queued.has(approved) && !routeKeys.has(approved)) {
        queued.add(approved);
        queue.push(approved);
      } else if (new URL(link, APP).origin === APP && !approved) {
        evidence.untested_paths.push(`Internal link outside the explicit safe-route allowlist was not followed: ${new URL(link, APP).pathname}`);
      }
    }
    return state;
  };

  page.setDefaultNavigationTimeout(15000);
  page.setDefaultTimeout(10000);
  page.setViewportSize({ width: 1366, height: 768 });
  await page.addInitScript(() => {
    window.addEventListener('unhandledrejection', (event) => {
      console.error('[SITE_EXPLORER_UNHANDLED_REJECTION]', String(event.reason || 'unhandled rejection'));
    });
  });
  await page.route('**/*', async (route) => {
    const requestUrl = route.request().url();
    if (new URL(requestUrl).origin === APP) return route.continue();
    evidence.review_cues.push({ code: 'EXTERNAL_REQUEST_BLOCKED', observed: `Blocked nonlocal request to ${new URL(requestUrl).host}.` });
    return route.abort();
  });
  page.on('console', (message) => { if (message.type() === 'error') evidence.console_errors.push({ route: page.url(), message: message.text().slice(0, 400) }); });
  page.on('pageerror', (error) => evidence.page_errors.push({ route: page.url(), message: String(error).slice(0, 400) }));
  page.on('response', (response) => {
    const responseUrl = response.url();
    if (new URL(responseUrl).origin !== APP || response.status() < 400) return;
    const resourceType = response.request().resourceType();
    if (resourceType === 'document' && expectedNegative(responseUrl)) return;
    evidence.http_errors.push({ url: responseUrl, status: response.status(), resource_type: resourceType, route: page.url() });
  });
  page.on('requestfailed', (request) => {
    if (new URL(request.url()).origin === APP && request.resourceType() !== 'document') evidence.failed_internal_requests.push({ route: page.url(), url: request.url(), error: request.failure()?.errorText || 'failed' });
  });

  testInfo.setTimeout(Math.ceil((maxDurationMs + 90000) / 1000) * 1000);
  try {
    for (const route of TOP_LEVEL) {
      const url = allowedUrl(route);
      if (url) { queued.add(url); queue.push(url); }
    }
    const homepage = `${APP}/`;
    activeUrl = homepage;
    await inspectPage(homepage, await page.goto(homepage));

    // Configure the persistent Civil Service candidate choice once in its
    // profile before exercising CSE learner routes.
    const profileUrl = `${APP}/profile`;
    await page.goto(profileUrl);
    await page.locator('#profile-cse-level').selectOption('PROFESSIONAL');
    await page.getByRole('button', { name: 'Save exam preferences' }).click();
    await expect(page.locator('[role="status"]')
      .filter({ hasText: 'Your learner preferences were saved' })).toHaveCount(1);
    evidence.actions_executed += 2;
    evidence.transitions.push('anonymous profile preferences: blank -> CSE PROFESSIONAL');

    const boardList = mode === 'smoke' ? seededShuffle(BOARDS, seed).slice(0, 2) : seededShuffle(BOARDS, seed);
    for (const [code] of boardList) {
      if (Date.now() - started > maxDurationMs || evidence.actions_executed >= maxActions) {
        evidence.untested_paths.push(`Stopped at the configured action/time budget before ${code}.`);
        break;
      }
      const boardUrl = `${APP}/exams?exam=${encodeURIComponent(code)}`;
      activeUrl = boardUrl;
      const boardResponse = await page.goto(boardUrl);
      await inspectPage(boardUrl, boardResponse);
      const boardTitle = await page.locator('.exam-detail h1').textContent().catch(() => code);
      const subjectLinks = await page.locator(`a[href^="/quiz?exam=${code}&subject="]`).evaluateAll((links) => links.map((link) => ({ href: link.getAttribute('href'), label: link.innerText.trim() })));
      if (subjectLinks.length === 0) {
        await addDefect('BOARD_HAS_NO_SUBJECT_ENTRY', `${code} has no learner subject entry link despite its available board status.`);
        continue;
      }
      let compatibleSubjectLinks = subjectLinks;
      if (code === 'CSE') {
        const clerical = subjectLinks.find((link) => new URL(link.href, APP).searchParams.get('subject') === 'clerical-ability');
        if (clerical) {
          await page.goto(`${APP}${clerical.href}`);
          await page.locator('#quiz-count').selectOption('5');
          await page.getByRole('button', { name: 'Start practice' }).click();
          const compatibilityMessage = await page.locator('[role="alert"]').textContent().catch(() => '');
          if (!/Clerical Ability is available only for the Subprofessional examination\./i.test(compatibilityMessage)) {
            await addDefect('PROFILE_SCOPE_VALIDATION_MISSING', 'Professional Civil Service preferences did not clearly reject Clerical Ability.');
          } else {
            evidence.transitions.push('CSE: Professional preferences safely rejected incompatible Clerical Ability before quiz start');
          }
          evidence.actions_executed += 2;
          await page.goto(boardUrl);
          await inspectPage(boardUrl, boardResponse);
        }
        compatibleSubjectLinks = subjectLinks.filter((link) => new URL(link.href, APP).searchParams.get('subject') !== 'clerical-ability');
      }
      if (compatibleSubjectLinks.length === 0) {
        await addDefect('BOARD_HAS_NO_PROFILE_COMPATIBLE_SUBJECT', `${code} has no subject entry compatible with the saved learner profile.`);
        continue;
      }
      const chosen = compatibleSubjectLinks[seed % compatibleSubjectLinks.length];
      const settingsUrl = `${APP}${chosen.href}`;
      activeUrl = settingsUrl;
      const settingsResponse = await page.goto(settingsUrl);
      await inspectPage(settingsUrl, settingsResponse);
      if (await page.locator('.quiz-context-summary').count() === 0) {
        await addDefect('SUBJECT_CONTEXT_NOT_VISIBLE', `${code} subject route did not preserve a visible board/subject context header.`);
      }
      await page.locator('#quiz-count').selectOption('5');
      await page.locator('#quiz-mode').selectOption('practice');
      await page.getByRole('button', { name: 'Start practice' }).click();
      evidence.actions_executed += 3;
      const selectedBoard = code;
      const expectedSubject = new URL(settingsUrl).searchParams.get('subject') || '*';
      let questionNumber = 0;
      let finished = false;
      while (!finished && questionNumber < 5 && Date.now() - started <= maxDurationMs && evidence.actions_executed < maxActions) {
        const questionId = await page.locator('input[name="question_id"]').inputValue().catch(() => '');
        const question = byId.get(String(questionId));
        const diagnosticUrl = `${APP}/developer/question-origin?id=${encodeURIComponent(questionId)}`;
        const diagnosticResponse = await page.request.get(diagnosticUrl);
        let diagnostic = null;
        if (diagnosticResponse.ok()) diagnostic = await diagnosticResponse.json();
        const effectiveSubject = String(diagnostic?.effective_eligible_subject || '').toLowerCase();
        if (!question || !diagnostic || diagnostic.selected_board.toUpperCase() !== selectedBoard.toUpperCase()
          || effectiveSubject === '' || (expectedSubject !== '*' && effectiveSubject !== expectedSubject.toLowerCase())) {
          await addDefect('QUESTION_SCOPE_MISMATCH', `Question ${questionId || '(missing id)'} did not independently meet ${selectedBoard}/${expectedSubject} scope.`, { question_id: questionId, diagnostic: diagnostic || null });
        }
        const taxonomy = question?.taxonomy || {};
        if (diagnostic && String(diagnostic.topic || '') !== String(taxonomy.topic_id || '')) {
          await addDefect('QUESTION_TOPIC_METADATA_MISMATCH', `Question ${questionId} displayed topic metadata disagrees with canonical taxonomy.`, { question_id: questionId, diagnostic_topic: diagnostic.topic, canonical_topic: taxonomy.topic_id || '' });
        }
        evidence.question_metadata_checks.push({ question_id: questionId, expected_board: selectedBoard, expected_subject: expectedSubject, effective_subject: diagnostic?.effective_eligible_subject || '', topic: diagnostic?.topic || '', source_references: diagnostic?.source_references || [], passed: Boolean(question && diagnostic && effectiveSubject !== '' && (expectedSubject === '*' || effectiveSubject === expectedSubject.toLowerCase())) });
        if (questionId) evidence.board_subject_combinations.push(`${selectedBoard}/${expectedSubject}`);
        await page.locator('input[name="answer"]').first().check();
        await page.getByRole('button', { name: 'Submit answer' }).click();
        await expect(page.locator('.quiz-feedback')).toBeVisible();
        const finalQuestion = (await page.locator('.quiz-position').textContent().catch(() => '')).includes('5 / 5');
        await page.getByRole('button', { name: finalQuestion ? 'Finish Quiz' : 'Next Question' }).click();
        evidence.actions_executed += 3;
        questionNumber += 1;
        finished = finalQuestion;
        if (!finished) await expect(page.locator('.quiz-answer-form')).toBeVisible();
      }
      await expect(page.locator('h1')).toContainText('Quiz Result');
      await expect(page.locator('.result-answer-review')).toBeVisible();
      await expect(page.locator('.question-review')).toHaveCount(5);
      await inspectPage(page.url(), null);
      evidence.transitions.push(`${selectedBoard}: subject configuration -> five answer submissions -> result`);
      evidence.boards_covered.push(selectedBoard);
      evidence.routes_visited.push(`${APP}/quiz (active ${selectedBoard} practice)`);

      if (evidence.boards_covered.length === 1) {
        await page.goto(`${APP}/history`);
        await inspectPage(`${APP}/history`, null);
        await page.goto(`${APP}/progress`);
        await inspectPage(`${APP}/progress`, null);
        await page.goBack();
        if (new URL(page.url()).pathname !== '/history') {
          await addDefect('BACK_NAVIGATION_LOST_ROUTE', `Back navigation landed on ${new URL(page.url()).pathname} instead of history.`);
        }
        await page.goForward();
        if (new URL(page.url()).pathname !== '/progress') {
          await addDefect('FORWARD_NAVIGATION_LOST_ROUTE', `Forward navigation landed on ${new URL(page.url()).pathname} instead of progress.`);
        }
        evidence.transitions.push('browser back/forward: result -> history -> progress -> history -> progress');
        evidence.actions_executed += 4;
      }

      if (mode === 'smoke') continue;
      if (code === 'LET') {
        const secondStart = `${APP}/quiz?exam=LET&subject=english`;
        await page.goto(secondStart);
        await page.locator('#quiz-count').selectOption('5');
        await page.locator('#quiz-adaptive').check();
        await page.getByRole('button', { name: 'Start practice' }).click();
        for (let attemptQuestion = 0; attemptQuestion < 5; attemptQuestion += 1) {
          await page.locator('input[name="answer"]').first().check();
          await page.getByRole('button', { name: 'Submit answer' }).click();
          await expect(page.locator('.quiz-feedback')).toBeVisible();
          const last = (await page.locator('.quiz-position').textContent().catch(() => '')).includes('5 / 5');
          await page.getByRole('button', { name: last ? 'Finish Quiz' : 'Next Question' }).click();
          if (!last) await expect(page.locator('.quiz-answer-form')).toBeVisible();
        }
        await expect(page.locator('h1')).toContainText('Quiz Result');
        await page.goto(`${APP}/history`);
        await expect(page.locator('.quiz-history-item')).toHaveCount(evidence.boards_covered.length + 1);
        evidence.transitions.push('LET: second adaptive attempt persisted alongside the first result');
        evidence.actions_executed += 18;

        await page.goto(`${APP}/quiz?exam=LET&subject=english`);
        await page.locator('#quiz-count').selectOption('5');
        await page.locator('#quiz-mode').selectOption('exam');
        await page.locator('#quiz-timer-mode').selectOption('timed');
        await page.locator('#quiz-time-limit').fill('5');
        await page.getByRole('button', { name: 'Start exam simulation' }).click();
        await expect(page.locator('.exam-countdown')).toBeVisible();
        await page.reload();
        await expect(page.locator('.exam-countdown')).toBeVisible();
        for (let examQuestion = 0; examQuestion < 5; examQuestion += 1) {
          await page.locator('input[name="answer"]').first().check();
          await page.getByRole('button', { name: 'Save answer and continue' }).click();
          if (examQuestion < 4) {
            await expect(page.locator('.quiz-position')).toContainText(`${examQuestion + 2} / 5`);
          }
        }
        await expect(page.locator('#exam-review-heading')).toBeVisible();
        await inspectPage(page.url(), null);
        await page.getByRole('button', { name: 'Submit exam simulation' }).click();
        await expect(page.locator('h1')).toContainText('Exam Simulation Result');
        await expect(page.locator('.question-review')).toHaveCount(5);
        evidence.transitions.push('LET: timed exam started, recovered after refresh, reviewed all answers, and completed');
        evidence.actions_executed += 17;
      }
    }

    const auxiliary = ['/study', '/study-library', '/history', '/progress', '/profile', '/privacy', '/flashcards'];
    for (const route of auxiliary) {
      if (Date.now() - started > maxDurationMs || evidence.actions_executed >= maxActions || routeKeys.size >= maxPages) break;
      activeUrl = `${APP}${route}`;
      const response = await page.goto(activeUrl);
      await inspectPage(activeUrl, response);
      evidence.actions_executed += 1;
    }
    await page.goto(`${APP}/flashcards?board=let`);
    await inspectPage(`${APP}/flashcards?board=let`, null);
    const subjectOptions = await page.locator('#flashcard-subject option').evaluateAll((options) => options.map((option) => option.value).filter(Boolean));
    if (subjectOptions.length > 0) {
      const firstSubject = subjectOptions[seed % subjectOptions.length];
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
        page.locator('#flashcard-subject').selectOption(firstSubject),
      ]);
      evidence.actions_executed += 1;
      await inspectPage(page.url(), null);
      const topicOptions = await page.locator('#flashcard-topic option').evaluateAll((options) => options.map((option) => option.value).filter(Boolean));
      if (topicOptions.length > 0) {
        const selectedTopic = topicOptions[seed % topicOptions.length];
        await page.locator('#flashcard-topic').selectOption(selectedTopic);
        await Promise.all([
          page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
          page.getByRole('button', { name: 'Apply filters' }).click(),
        ]);
        evidence.actions_executed += 1;
        await inspectPage(page.url(), null);
        evidence.transitions.push(`flashcards: LET -> ${firstSubject} -> ${selectedTopic}`);
        const otherSubject = subjectOptions.find((subject) => subject !== firstSubject);
        if (otherSubject) {
          await Promise.all([
            page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
            page.locator('#flashcard-subject').selectOption(otherSubject),
          ]);
          const clearedTopic = await page.locator('#flashcard-topic').inputValue();
          if (clearedTopic !== '') {
            await addDefect('FLASHCARD_TOPIC_NOT_CLEARED', `Changing subject from ${firstSubject} to ${otherSubject} retained topic ${clearedTopic}.`);
          }
          evidence.transitions.push(`flashcards: changing ${firstSubject} to ${otherSubject} cleared its descendant topic`);
          evidence.actions_executed += 1;
          await inspectPage(page.url(), null);
        }
      } else {
        evidence.untested_paths.push(`LET/${firstSubject}: no topic options were available to exercise the descendant filter.`);
      }
    } else {
      evidence.untested_paths.push('LET flashcard setup had no subject options, so dependent-filter behavior was not exercised.');
    }
    const reviewLink = page.locator('a[href^="/flashcards/review"]:visible').first();
    if (await reviewLink.count()) {
      const href = await reviewLink.getAttribute('href');
      activeUrl = `${APP}${href}`;
      const response = await page.goto(activeUrl);
      await inspectPage(activeUrl, response);
      evidence.actions_executed += 1;
      if (await page.locator('.flashcard-prompt').count()) {
        await Promise.all([
          page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
          page.getByRole('link', { name: 'Reveal answer' }).click(),
        ]);
        await expect(page.locator('.flashcard-answer')).toBeVisible();
        await inspectPage(page.url(), null);
        await Promise.all([
          page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
          page.getByRole('button', { name: 'Good Remembered' }).click(),
        ]);
        await inspectPage(page.url(), null);
        evidence.actions_executed += 2;
        evidence.transitions.push('flashcards: eligible board/subject/topic filter -> prompt -> reveal -> saved Good rating');
      } else {
        evidence.untested_paths.push('The isolated flashcard route had no available card prompt, so reveal and rating actions were skipped.');
      }
    } else {
      evidence.untested_paths.push('Flashcard reveal and rating skipped because the isolated fixture had no available review card.');
    }

    for (const route of [
      '/quiz?exam=LET&topic=__no_such_topic__',
      '/quiz?action=not-a-real-action',
      '/flashcards/review?include_new=0',
    ]) {
      activeUrl = `${APP}${route}`;
      const response = await page.goto(activeUrl);
      await inspectPage(activeUrl, response);
      evidence.transitions.push(`${route}: deliberate negative/recovery route visited`);
      evidence.actions_executed += 1;
    }

    // Bounded dynamic route sweep uses only discovered same-origin safe links.
    while (queue.length > 0 && routeKeys.size < maxPages && evidence.actions_executed < maxActions && Date.now() - started <= maxDurationMs) {
      const candidate = queue.shift();
      if (!candidate || routeKeys.has(candidate)) continue;
      const safe = allowedUrl(candidate);
      if (!safe) continue;
      activeUrl = safe;
      const response = await page.goto(safe);
      await inspectPage(safe, response);
      evidence.actions_executed += 1;
    }

    activeUrl = page.url();
    await page.keyboard.press('Tab');
    const focus = await page.evaluate(() => {
      const element = document.activeElement;
      const style = getComputedStyle(element);
      return { tag: element?.tagName || '', outline: style.outlineStyle, width: style.outlineWidth, shadow: style.boxShadow };
    });
    if (focus.tag && focus.tag !== 'BODY' && focus.outline === 'none' && focus.width === '0px' && focus.shadow === 'none') {
      await addDefect('KEYBOARD_FOCUS_NOT_VISIBLE', `Focused ${focus.tag} has no computed outline or shadow.`);
    }
    evidence.actions_executed += 1;

    for (const item of evidence.console_errors) await addDefect('BROWSER_CONSOLE_ERROR', item.message, { error_route: item.route });
    for (const item of evidence.page_errors) await addDefect('JAVASCRIPT_EXCEPTION', item.message, { error_route: item.route });
    for (const item of evidence.failed_internal_requests) await addDefect('FAILED_INTERNAL_REQUEST', item.error, { failed_url: item.url });
    const httpErrorKeys = new Set();
    for (const item of evidence.http_errors) {
      const key = `${item.status}|${item.url}`;
      if (httpErrorKeys.has(key)) continue;
      httpErrorKeys.add(key);
      await addDefect('UNEXPECTED_HTTP_ERROR', `HTTP ${item.status} for an internal ${item.resource_type} request.`, { route: item.route, failed_url: item.url });
    }
    if (evidence.boards_covered.length < (mode === 'smoke' ? 2 : BOARDS.length)) {
      evidence.untested_paths.push(`Board coverage stopped at ${evidence.boards_covered.length}/${mode === 'smoke' ? 2 : BOARDS.length} due to a configured time or action budget.`);
    }
    evidence.boards_covered = [...new Set(evidence.boards_covered)];
    evidence.board_subject_combinations = [...new Set(evidence.board_subject_combinations)];
    const pageLimitExceeded = evidence.routes_visited.length > maxPages;
    const actionLimitExceeded = evidence.actions_executed > maxActions;
    if (pageLimitExceeded || actionLimitExceeded) {
      evidence.untested_paths.push(`Configured exploration bound exceeded: pages=${evidence.routes_visited.length}/${maxPages}, actions=${evidence.actions_executed}/${maxActions}.`);
    }
    evidence.status = evidence.confirmed_defects.length
      ? 'FAIL'
      : (pageLimitExceeded || actionLimitExceeded
        ? 'INCOMPLETE'
        : (evidence.review_cues.length ? 'PASS_WITH_REVIEW_CUES' : 'PASS'));
    if (evidence.confirmed_defects.length) {
      console.error(`EXPLORER_CONFIRMED_DEFECTS=${JSON.stringify(evidence.confirmed_defects.map(({ code, observed, route, question_id }) => ({ code, observed, route, question_id })))}`);
      throw new Error(`Explorer confirmed ${evidence.confirmed_defects.length} reproducible defect(s); inspect ${path.join(evidenceDir, 'site-explorer.json')}`);
    }
    if (pageLimitExceeded || actionLimitExceeded) {
      throw new Error(`Explorer exceeded its configured bound; inspect ${path.join(evidenceDir, 'site-explorer.json')}`);
    }
    expect(evidence.question_metadata_checks.filter((item) => item.passed).length).toBeGreaterThan(0);
  } catch (error) {
    evidence.status = evidence.confirmed_defects.length ? 'FAIL' : 'INCOMPLETE';
    evidence.untested_paths.push(`Explorer terminated: ${String(error.message || error).slice(0, 500)}`);
    throw error;
  } finally {
    evidence.elapsed_ms = Date.now() - started;
    evidence.routes_visited = [...new Set(evidence.routes_visited)];
    const serializedEvidence = `${JSON.stringify(evidence, null, 2)}\n`;
    fs.writeFileSync(path.join(evidenceDir, `site-explorer-attempt-${testInfo.retry + 1}.json`), serializedEvidence);
    fs.writeFileSync(path.join(evidenceDir, 'site-explorer.json'), serializedEvidence);
    fs.writeFileSync(path.join(evidenceDir, 'site-explorer.md'), markdown(evidence));
    testInfo.attach('site-explorer.json', { path: path.join(evidenceDir, 'site-explorer.json'), contentType: 'application/json' }).catch(() => undefined);
    testInfo.attach('site-explorer.md', { path: path.join(evidenceDir, 'site-explorer.md'), contentType: 'text/markdown' }).catch(() => undefined);
  }
});
