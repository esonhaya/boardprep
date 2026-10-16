const { execFileSync } = require('node:child_process');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');

const {
  test,
  expect,
  assertPageHealthy,
  tabToKeyboardFocus,
  recordBrowserEvidence,
} = require('../fixtures/test.cjs');

const boards = [
  ['LET', 'let'],
  ['CSE', 'civil-service'],
  ['CLE', 'criminologist'],
  ['NLE', 'nursing'],
  ['PLE', 'psychometrician'],
  ['CE', 'civil-engineering'],
  ['CPA', 'cpa'],
  ['ME', 'mechanical-engineering'],
  ['REE', 'registered-electrical-engineering'],
  ['ARCH', 'architecture'],
];

const fullJourneyBoards = [
  ['LET', 'let'],
  ['CSE', 'civil-service'],
  ['NLE', 'nursing'],
  ['CLE', 'criminologist'],
  ['CE', 'civil-engineering'],
  ['ME', 'mechanical-engineering'],
  ['REE', 'registered-electrical-engineering'],
  ['ARCH', 'architecture'],
  ['PLE', 'psychometrician'],
  ['CPA', 'cpa'],
];

let questions;
function canonicalQuestions() {
  if (questions) return questions;
  const source = fs.readFileSync(path.resolve(__dirname, '../../..', 'storage', 'questions.json'), 'utf8');
  questions = JSON.parse(source);
  return questions;
}

function questionById(id) {
  return canonicalQuestions().find((question) => String(question.id) === String(id));
}

function normalize(value) {
  return String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();
}

function extractPdfText(buffer) {
  const runs = [];
  const source = buffer.toString('latin1');
  for (const match of source.matchAll(/\(((?:\\[\s\S]|[^\\)])*)\)\s*Tj/g)) {
    runs.push(match[1]
      .replace(/\\([\\()])/g, '$1')
      .replace(/\\n/g, ' ')
      .replace(/\\r/g, ' '));
  }
  return runs.join(' ').replace(/\s+/g, ' ').trim();
}

async function startQuiz(page, {
  exam = 'LET',
  subject = 'English',
  topic = '',
  count = '5',
  difficulty = 'mixed',
} = {}) {
  const query = new URLSearchParams({ exam, topic });
  await page.goto(`/quiz?${query.toString()}`);
  const activeSessionNotice = page.locator('.active-session-warning');
  if (await activeSessionNotice.count()) {
    await expect(activeSessionNotice).toContainText('Starting the session below will end this unfinished attempt and discard its answers.');
    await expect(page.getByRole('link', { name: 'Resume current session' })).toHaveAttribute('href', '/quiz');
  }
  const subjectSelect = page.locator('#quiz-subject');
  const subjectOptions = await subjectSelect.locator('option').evaluateAll((options) => options
    .filter((option) => !option.disabled && !option.hidden)
    .map((option) => ({ value: option.value, label: option.label })));
  const wantedSubject = normalize(subject);
  const subjectOption = subjectOptions.find((option) => (
    normalize(option.value) === wantedSubject || normalize(option.label) === wantedSubject
  ));
  if (!subjectOption) throw new Error(`Subject is not offered by the learner form: ${subject}`);
  await subjectSelect.selectOption(subjectOption.value);
  await page.locator('#quiz-count').selectOption(count);
  await page.locator('#quiz-difficulty').selectOption(difficulty);
  await page.getByRole('button', { name: 'Start practice' }).click();
    await expect(page.locator('.quiz-question')).toBeVisible();
    await expect(page.locator('.quiz-question')).toBeFocused();
}

async function configureAvailableBoardQuiz(page, exam) {
  await expect(page.locator('#quiz-exam')).toHaveValue(exam);

  if (exam === 'CSE') {
    await page.locator('#civil-service-level').selectOption('PROFESSIONAL');
  }

  const subjectOption = page.locator(`#quiz-subject option[data-exam="${exam}"]`).first();
  await expect(subjectOption).toHaveCount(1);
  const subject = await subjectOption.getAttribute('value');
  await page.locator('#quiz-subject').selectOption(subject);
  await page.locator('#quiz-count').selectOption('5');
  await page.locator('#quiz-mode').selectOption('practice');
  await page.getByRole('button', { name: 'Start practice' }).click();
  await expect(page.locator('.quiz-question')).toBeVisible();
}

async function startAvailableBoardQuiz(page, exam) {
  await page.goto(`/quiz?exam=${encodeURIComponent(exam)}`);
  await configureAvailableBoardQuiz(page, exam);
}

async function submitAnswer(page, wrong = false) {
  const questionId = await page.locator('input[name="question_id"]').inputValue();
  const question = questionById(questionId);
  const choices = question?.choices || [];
  const correctIndex = choices.findIndex((choice) => normalize(choice) === normalize(question?.answer));
  let answerIndex = correctIndex >= 0 ? correctIndex : 0;
  if (wrong && choices.length > 1) answerIndex = (answerIndex + 1) % choices.length;
  await page.locator('input[name="answer"]').nth(answerIndex).check();
  await expect(page.locator('input[name="answer"]').nth(answerIndex)).toBeChecked();
  await page.getByRole('button', { name: 'Submit Answer' }).click();
  await expect(page.locator('.quiz-feedback')).toBeVisible();
  await expect(page.locator('.quiz-feedback')).toBeFocused();
}

async function completeQuiz(page, wrong = false) {
  for (let index = 0; index < 5; index += 1) {
    await submitAnswer(page, wrong);
    const next = page.getByRole('button', { name: index === 4 ? 'Finish Quiz' : 'Next Question' });
    await next.click();
    if (index < 4) {
      await expect(page.locator('.quiz-answer-form')).toBeVisible();
    }
  }
  await expect(page.locator('h1')).toContainText('Quiz Result');
}

async function inspectStructuredQuestions(page, testInfo, targets, maxQuestions) {
  const found = new Set();

  for (let index = 0; index < maxQuestions; index += 1) {
    const questionId = await page.locator('input[name="question_id"]').inputValue();
    const target = targets[questionId];
    if (target) {
      await expect(page.locator(target.selector)).toHaveCount(1);
      if (target.visible) {
        await expect(page.locator(target.selector)).toBeVisible();
      }
      found.add(questionId);
      await assertPageHealthy(page, testInfo, `quiz-${target.name}`);
    }

    if (found.size === Object.keys(targets).length) return;

    await submitAnswer(page, false);
    if (await page.getByRole('button', { name: 'Finish Quiz' }).isVisible()) break;
    const next = page.getByRole('button', { name: 'Next Question' });
    await expect(next).toBeVisible();
    await next.click();
    await expect(page.locator('.quiz-answer-form')).toBeVisible();
  }

  throw new Error(`Structured fixtures not rendered: ${JSON.stringify({
    expected: Object.keys(targets),
    found: [...found],
  })}`);
}

test.describe('BoardPrep learner browser acceptance', () => {
  test('records the actual browser engine, version, and viewport', async ({ page, browser }, testInfo) => {
    await page.goto('/');
    const engine = browser.browserType().name();
    const version = browser.version();
    const viewport = await page.evaluate(() => ({
      width: document.documentElement.clientWidth,
      height: document.documentElement.clientHeight,
    }));
    expect(engine).toBe('chromium');
    expect(version).not.toBe('');
    await expect(page.locator('.site-footer')).toContainText('BoardPrep v1.0.1');
    const healthResponse = await page.request.get(new URL('/health.php', page.url()).toString());
    expect(healthResponse.status()).toBe(200);
    const health = await healthResponse.json();
    expect(health.product_version).toBe('1.0.1');
    expect(health.build_commit).toBe(execFileSync('git', ['rev-parse', 'HEAD'], {
      cwd: path.resolve(__dirname, '../../..'),
      encoding: 'utf8',
    }).trim());
    expect(health.status).toBe('PASS');
    recordBrowserEvidence('runtime', { engine, version, viewport }, testInfo);
  });

  test('study lesson PDF link serves the identified document and supports keyboard download', async ({ page }, testInfo) => {
    const lessons = JSON.parse(fs.readFileSync(path.resolve(__dirname, '../../..', 'storage', 'study-lessons.json'), 'utf8'));
    const lesson = lessons.find((item) => item.board_id === 'let' && item.pdf_file);
    expect(lesson, 'A complete LET lesson with a generated PDF is required for the document fixture.').toBeTruthy();
    await page.goto('/study-library?exam=LET');
    const material = page.locator(`#study-material-${lesson.material_id}`);
    await expect(material).toBeVisible();
    await expect(material.getByRole('link', { name: 'Open complete lesson' })).toBeVisible();
    const downloadLink = material.locator('a[download][href$=".pdf"]').first();
    await expect(downloadLink).toBeVisible();
    const pdfHref = await downloadLink.getAttribute('href');
    expect(pdfHref).toBe(`/study-resources/${lesson.pdf_file}`);

    const pdfResponse = await page.request.get(pdfHref);
    expect(pdfResponse.status()).toBe(200);
    expect(pdfResponse.headers()['content-type']).toContain('application/pdf');
    const servedBytes = await pdfResponse.body();
    expect(servedBytes.subarray(0, 5).toString('ascii')).toBe('%PDF-');
    const servedText = extractPdfText(servedBytes);
    expect(servedText).toContain('BoardPrep');
    expect(servedText).toContain(lesson.title);
    const manifest = JSON.parse(fs.readFileSync(path.resolve(__dirname, '../../..', 'public', 'study-resources', 'manifest.json'), 'utf8'));
    const manifestEntry = manifest.files.find((entry) => entry.lesson_id === lesson.id);
    expect(manifestEntry, 'The lesson must have a generated-document identity entry.').toBeTruthy();
    expect(manifestEntry.path).toBe(`public/study-resources/${lesson.pdf_file}`);
    expect(manifestEntry.searchable_text).toBe(true);
    expect(crypto.createHash('sha256').update(servedBytes).digest('hex')).toBe(manifestEntry.sha256);

    // Chromium's headless browser starts a download when navigating directly to
    // an application/pdf response. Validate the link's document response above,
    // and exercise the actual browser download interaction below.
    await tabToKeyboardFocus(page, downloadLink);
    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.keyboard.press('Enter'),
    ]);
    expect(download.suggestedFilename()).toBe(lesson.pdf_file);
    const downloadPath = await download.path();
    expect(downloadPath).toBeTruthy();
    const downloadedBytes = fs.readFileSync(downloadPath);
    expect(crypto.createHash('sha256').update(downloadedBytes).digest('hex'))
      .toBe(crypto.createHash('sha256').update(servedBytes).digest('hex'));
    await assertPageHealthy(page, testInfo, 'study-pdf-download');
    recordBrowserEvidence('study-pdf', {
      lesson_id: lesson.id,
      material_id: lesson.material_id,
      filename: lesson.pdf_file,
      pdf_response: pdfResponse.status(),
      manifest_sha256: manifestEntry.sha256,
      download_bytes_match_served_document: true,
      pdf_identity: lesson.title,
      keyboard_accessible: true,
      viewport: page.viewportSize(),
    }, testInfo);
  });

  test('mobile board navigation opens its study resource and lesson', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'mobile', 'Mobile study-resource navigation is exercised at the phone viewport.');
    const lessons = JSON.parse(fs.readFileSync(path.resolve(__dirname, '../../..', 'storage', 'study-lessons.json'), 'utf8'));
    const lesson = lessons.find((item) => item.board_id === 'let' && item.pdf_file);
    expect(lesson).toBeTruthy();
    await page.goto('/exams?exam=LET');
    await page.getByRole('link', { name: 'Study materials' }).click();
    await expect(page).toHaveURL(/\/study-library\?exam=let/i);
    await expect(page.locator('h1')).toHaveText('Study resources');
    const material = page.locator(`#study-material-${lesson.material_id}`);
    await expect(material).toBeVisible();
    await material.getByRole('link', { name: 'Open complete lesson' }).click();
    await expect(page.locator(`#boardprep-lesson-${lesson.id}`)).toBeVisible();
    await expect(page.locator(`#lesson-title-${lesson.id}`)).toHaveText(lesson.title);
    await assertPageHealthy(page, testInfo, 'mobile-study-resource-lesson');
    recordBrowserEvidence('mobile-study-navigation', {
      board: 'let', lesson_id: lesson.id, resource_to_lesson_navigation: 'PASS',
      viewport: page.viewportSize(),
    }, testInfo);
  });

  test('developer question editor opens in isolated read-only browser workflow', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'The isolated developer editor workflow runs once at desktop width.');
    const methods = [];
    page.on('request', (request) => methods.push(request.method()));
    const response = await page.goto('http://127.0.0.1:4174/question-editor/edit?id=616');
    expect(response?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'Edit Question', exact: true })).toBeVisible();
    await expect(page.locator('form[action^="/question-editor/update"]')).toBeVisible();
    expect(methods.every((method) => method === 'GET'), 'Read-only acceptance must not submit developer forms.').toBeTruthy();
    await assertPageHealthy(page, testInfo, 'developer-editor-read-only');
    recordBrowserEvidence('developer-editor', {
      route: '/question-editor/edit?id=616',
      response: response.status(),
      write_requests: 0,
      workflow: 'READ_ONLY',
    }, testInfo);
  });

  test('production hides developer routes and presents a safe not-found page', async ({ page }, testInfo) => {
    const response = await page.goto('/developer');
    expect(response.status()).toBe(404);
    await expect(page.locator('.site-header')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'We couldn’t find that page' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Browse examinations' })).toHaveAttribute('href', '/exams');
    await expect(page.locator('body')).not.toContainText(/stack trace|fatal error|doctor dashboard/i);
    await assertPageHealthy(page, testInfo, 'production-developer-route', {
      allowHttpErrors: [{
        status: 404,
        url: page.url(),
        pageUrl: page.url(),
        resourceType: 'document',
        consoleMessage: /^Failed to load resource: the server responded with a status of 404\b/,
      }],
    });
    for (const sensitivePath of ['/.env', '/config/release.php', '/storage/questions.json']) {
      const privateResponse = await page.request.get(new URL(sensitivePath, page.url()).toString());
      expect(privateResponse.status(), `${sensitivePath} must not be public`).toBe(404);
    }
    recordBrowserEvidence('production-boundary', {
      developerRoute: 'NOT_FOUND',
      sensitiveFiles: 'NOT_FOUND',
    }, testInfo);
  });

  test('dashboard navigation and mobile menu are usable', async ({ page }, testInfo) => {
    await page.goto('/dashboard');
    const dashboardHeading = page.locator('main h1');
    await expect(dashboardHeading).toHaveCount(1);
    await expect(dashboardHeading).toHaveText('Welcome to BoardPrep');
    const emptyAccuracy = page.locator('.stat-card').filter({ hasText: 'Average accuracy' });
    const emptyBestScore = page.locator('.stat-card').filter({ hasText: 'Best score' });
    await expect(emptyAccuracy).toContainText('—');
    await expect(emptyAccuracy).toContainText('No completed quizzes yet');
    await expect(emptyBestScore).toContainText('—');
    await expect(emptyBestScore).toContainText('No completed quizzes yet');
    for (const href of ['/exams', '/history', '/flashcards', '/progress', '/quiz']) {
      await expect(page.locator(`a[href="${href}"]:visible`).first()).toBeVisible();
    }
    const menu = page.locator('.mobile-menu-button');
    if (testInfo.project.name === 'mobile') {
      await expect(menu).toHaveAttribute('aria-expanded', 'false');
      await expect(menu).toHaveAccessibleName('Open primary navigation');
      await menu.click();
      await expect(menu).toHaveAttribute('aria-expanded', 'true');
      await expect(menu).toHaveAccessibleName('Close primary navigation');
      await expect(page.locator('#learner-nav')).toHaveClass(/is-open/);
      await menu.press('Escape');
      await expect(menu).toHaveAttribute('aria-expanded', 'false');
      await expect(menu).toHaveAccessibleName('Open primary navigation');
      await expect(page.locator('#learner-nav')).not.toHaveClass(/is-open/);
      await expect(menu).toBeFocused();
      await menu.click();
      await expect(menu).toHaveAttribute('aria-expanded', 'true');
      const mobileExamsLink = page.locator('#learner-nav a[href="/exams"]');
      await expect(mobileExamsLink).toBeVisible();
      await tabToKeyboardFocus(page, mobileExamsLink);
      await assertPageHealthy(page, testInfo, 'dashboard');
      await page.keyboard.press('Enter');
      await expect(page).toHaveURL(/\/exams/);
    } else {
      await expect(menu).toBeHidden();
      const desktopExamsLink = page.locator('#learner-nav a[href="/exams"]');
      await expect(desktopExamsLink).toBeVisible();
      await tabToKeyboardFocus(page, desktopExamsLink);
      await assertPageHealthy(page, testInfo, 'dashboard');
      await page.keyboard.press('Enter');
      await expect(page).toHaveURL(/\/exams/);
    }
    recordBrowserEvidence('keyboard', { keyboardNavigation: 'PASS', visibleFocus: 'PASS' }, testInfo);
  });

  test('all populated board details render without browser failures', async ({ page }, testInfo) => {
    await page.goto('/exams');
    await expect(page.locator('.exam-card')).toHaveCount(10);
    await expect(page.locator('.status-soon')).toHaveCount(0);
    await assertPageHealthy(page, testInfo, 'exams');

    for (const [code] of boards) {
      await page.goto(`/exams?exam=${encodeURIComponent(code)}`);
      await expect(page.locator('.exam-detail h1')).toBeVisible();
      await expect(page.locator('body')).not.toContainText('Coming soon');
      await assertPageHealthy(page, testInfo, `board-${code}`, {
        screenshot: ['LET', 'ME', 'ARCH'].includes(code),
      });
    }
  });

  test('quiz configuration and deterministic text round trip work', async ({ page }, testInfo) => {
    await page.goto('/quiz');
    await expect(page.locator('#quiz-mode option[value="exam"]')).toHaveText('Exam simulation');
    await expect(page.locator('.mode-disclosure')).toContainText('separate from the official board examination');
    await page.locator('#quiz-mode').selectOption('exam');
    await expect(page.locator('#exam-timer-fields')).toBeVisible();
    await expect(page.locator('#quiz-timer-mode')).toHaveValue('untimed');
    await page.locator('#quiz-timer-mode').selectOption('timed');
    await expect(page.locator('#quiz-time-limit')).toBeEnabled();
    await expect(page.locator('#quiz-time-limit')).toHaveAttribute('min', '1');
    await page.locator('#quiz-mode').selectOption('practice');
    await expect(page.locator('#exam-timer-fields')).toBeHidden();
    const quizLabels = await page.evaluate(() => [...document.querySelectorAll(
      'main input:not([type="hidden"]), main select, main textarea'
    )].filter((control) => !control.labels?.length
      && !control.getAttribute('aria-label')
      && !control.getAttribute('aria-labelledby'))
      .map((control) => control.outerHTML.slice(0, 120)));
    expect(quizLabels, 'Learner quiz controls should have programmatic labels').toEqual([]);

    await startQuiz(page, {
      exam: 'LET',
      subject: 'professional-education',
      topic: 'prof-development',
      count: '5',
    });
    await expect(page.locator('.quiz-meta')).toContainText('Question 1 / 5');
    await completeQuiz(page, true);
    await expect(page.locator('.result-score .eyebrow')).toHaveText('Score');
    await expect(page.locator('.result-score h2')).toHaveText(/\d+\s*\/\s*\d+/);
    await expect(page.locator('.result-percentage .eyebrow')).toHaveText('Accuracy');
    await expect(page.locator('.result-percentage h3')).toHaveText(/\d+%/);
    await assertPageHealthy(page, testInfo, 'quiz-result');

    await expect(page.getByRole('link', { name: 'Review missed concepts' })).toBeVisible();
    await page.getByRole('link', { name: 'Review missed concepts' }).click();
    await expect(page.locator('.flashcard-card')).toBeVisible();
    await assertPageHealthy(page, testInfo, 'missed-flashcards');
    await page.goto('/quiz?action=result');

    await page.getByRole('link', { name: 'Back to Study Dashboard' }).click();
    await expect(page.locator('h1')).toHaveText('Study Dashboard');
    await assertPageHealthy(page, testInfo, 'study-after-result');

    await page.getByRole('link', { name: /Practice/ }).first().click();
    await expect(page.locator('h1')).toContainText('Choose practice or an exam simulation');
    await assertPageHealthy(page, testInfo, 'quiz-config');

    await page.goto('/history');
    await expect(page.locator('.quiz-history-item').first()).toBeVisible();
    await page.getByRole('link', { name: 'View result' }).first().click();
    await expect(page.locator('h1')).toHaveText('Historical Quiz Result');
    await assertPageHealthy(page, testInfo, 'history-result');
    recordBrowserEvidence('accessibility', {
      quizFormLabels: 'PASS',
      resultFeedback: 'PASS',
      namedPrimaryActions: 'PASS',
      examOfficialBoundaryDisclosed: 'PASS',
    }, testInfo);
  });

  test('timed exam simulation resumes, supports answer review, and submits once', async ({ page }, testInfo) => {
    await page.goto('/quiz?exam=LET');
    await page.locator('#quiz-mode').selectOption('exam');
    await page.locator('#quiz-timer-mode').selectOption('timed');
    await page.locator('#quiz-time-limit').fill('10');
    await page.locator('#quiz-count').selectOption('5');
    await page.getByRole('button', { name: 'Start exam simulation' }).click();
    await expect(page.locator('.quiz-meta')).toContainText('Question 1 / 5');
    await expect(page.locator('.quiz-question')).toBeFocused();
    await expect(page.locator('.exam-countdown')).toBeVisible();
    const initialRemaining = Number(await page.locator('.exam-countdown').getAttribute('data-remaining-seconds'));

    await page.reload();
    await expect(page.locator('.quiz-meta')).toContainText('Question 1 / 5');
    await expect(page.locator('.quiz-question')).toBeFocused();
    const resumedRemaining = Number(await page.locator('.exam-countdown').getAttribute('data-remaining-seconds'));
    expect(resumedRemaining).toBeLessThanOrEqual(initialRemaining);

    await page.locator('input[name="answer"]').first().check();
    await page.getByRole('button', { name: 'Save answer and continue' }).click();
    await expect(page.locator('.quiz-meta')).toContainText('Question 2 / 5');
    await page.getByRole('button', { name: 'Skip to next question' }).click();
    await expect(page.locator('.quiz-meta')).toContainText('Question 3 / 5');
    await page.getByRole('link', { name: 'Review all answers' }).click();
    await expect(page.locator('#exam-review-heading')).toBeFocused();
    await expect(page.locator('.exam-review-summary')).toContainText('1 of 5 answered');
    await expect(page.locator('.exam-review-summary')).toContainText('4 will be marked unanswered');
    await assertPageHealthy(page, testInfo, 'exam-simulation-review', { screenshot: true });

    await page.getByRole('button', { name: 'Review question 1' }).click();
    await page.locator('input[name="answer"]').nth(1).check();
    await page.getByRole('button', { name: 'Save answer and continue' }).click();
    await page.getByRole('link', { name: 'Review all answers' }).click();
    await page.getByRole('button', { name: 'Submit exam simulation' }).click();
    await expect(page.locator('h1')).toHaveText('Exam Simulation Result');
    await expect(page.locator('.quiz-result-page h1')).toBeFocused();
    await expect(page.locator('.result-score h2')).toHaveText(/\d+\s*\/\s*5/);
    await expect(page.locator('.result-answer-review')).toContainText('No answer');
    await page.reload();
    await expect(page.locator('h1')).toHaveText('Exam Simulation Result');
    await assertPageHealthy(page, testInfo, 'exam-simulation-result');
    recordBrowserEvidence('exam-simulation', {
      timerResume: 'PASS',
      savedAnswerReview: 'PASS',
      unansweredScoring: 'PASS',
      resultRefresh: 'PASS',
    }, testInfo);
  });

  test('table and figure questions remain usable on the production path', async ({ page }, testInfo) => {
    await startQuiz(page, {
      exam: 'LET',
      subject: 'Science',
      topic: 'science-electricity-circuits',
      count: '20',
      difficulty: 'mixed',
    });
    await inspectStructuredQuestions(page, testInfo, {
      34: { name: 'figure', selector: '.question-figure img', visible: true },
    }, 20);

    await startQuiz(page, {
      exam: 'CPA',
      subject: 'Financial Accounting',
      topic: 'cpa-financial-accounting-liabilities',
      count: '20',
      difficulty: 'mixed',
    });
    await inspectStructuredQuestions(page, testInfo, {
      1267: { name: 'table', selector: '.question-table-wrap', visible: true },
    }, 20);
  });

  test('flashcard review, progress, history, and recovery states work', async ({ page }, testInfo) => {
    await page.goto('/flashcards/review?include_new=0');
    await expect(page.locator('h2')).toContainText('No review card is available');
    await expect(page.getByRole('link', { name: 'Adjust flashcards' })).toBeVisible();
    await assertPageHealthy(page, testInfo, 'flashcards-empty');

    await page.goto('/flashcards?include_new=1');
    await expect(page.locator('h1')).toHaveText('Flashcards');
    await page.getByRole('link', { name: /Start review|Review now/ }).first().click();
    await expect(page.locator('.flashcard-card')).toBeVisible();
    await expect(page.locator('.flashcard-answer')).toHaveCount(0);
    await assertPageHealthy(page, testInfo, 'flashcards-review-before-reveal');
    await page.getByRole('link', { name: 'Reveal answer' }).click();
    await expect(page.locator('.flashcard-answer')).toBeVisible();
    await expect(page.getByRole('button', { name: /Good/ })).toBeVisible();
    await assertPageHealthy(page, testInfo, 'flashcards-review-after-reveal');
    await page.getByRole('button', { name: /Good/ }).click();
    await expect(page).toHaveURL(/\/flashcards\/review/);

    await page.goto('/progress');
    await expect(page.locator('h1')).toHaveText('Know what to review next');
    await assertPageHealthy(page, testInfo, 'progress');
    await page.goto('/history');
    await expect(page.locator('h1')).toHaveText('Quiz history');
    await assertPageHealthy(page, testInfo, 'history');

    await page.goto('/missing-browser-acceptance-route');
    await expect(page.locator('body')).not.toContainText('Stack trace');
    await expect(page.locator('body')).not.toContainText('Fatal error');
    await assertPageHealthy(page, testInfo, '404', {
      allowHttpErrors: [{
        status: 404,
        url: page.url(),
        pageUrl: page.url(),
        resourceType: 'document',
        consoleMessage: /^Failed to load resource: the server responded with a status of 404\b/,
      }],
    });
  });

  test('privacy export, cancellation, confirmed deletion, and learner isolation work in the browser', async ({ page, browser }, testInfo) => {
    await startAvailableBoardQuiz(page, 'LET');
    await completeQuiz(page, true);
    await page.goto('/history');
    await expect(page.locator('.quiz-history-item')).toHaveCount(1);

    const secondContext = await browser.newContext({ baseURL: 'http://127.0.0.1:4173' });
    const secondPage = await secondContext.newPage();
    await startAvailableBoardQuiz(secondPage, 'LET');
    await completeQuiz(secondPage, false);
    await secondPage.goto('/history');
    await expect(secondPage.locator('.quiz-history-item')).toHaveCount(1);

    await page.goto('/privacy');
    await expect(page.locator('h1')).toHaveText('Data and privacy');
    const originalCookies = await page.context().cookies(page.url());
    const originalSession = originalCookies.find((cookie) => cookie.name === 'PHPSESSID');
    expect(originalSession).toBeTruthy();
    expect(originalSession.httpOnly).toBe(true);
    expect(originalSession.sameSite).toBe('Lax');

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.getByRole('link', { name: 'Download my saved learning data' }).click(),
    ]);
    const exported = JSON.parse(fs.readFileSync(await download.path(), 'utf8'));
    expect(exported.format).toBe('boardprep-anonymous-learner-data-v1');
    expect(exported.records.quiz_attempts).toHaveLength(1);
    expect(JSON.stringify(exported)).not.toContain('user_id');

    await page.goto('/privacy');
    const confirmation = page.locator('input[name="confirm_clear"]');
    await expect(confirmation).not.toBeChecked();
    await page.getByRole('button', { name: 'Clear my learning data' }).click();
    await expect(confirmation).not.toBeChecked();
    await page.goto('/history');
    await expect(page.locator('.quiz-history-item')).toHaveCount(1);

    await page.goto('/privacy');
    await confirmation.check();
    await page.getByRole('button', { name: 'Clear my learning data' }).click();
    await expect(page.getByText(/Saved learning data for this browser session was cleared/)).toBeVisible();
    const rotatedCookies = await page.context().cookies(page.url());
    const rotatedSession = rotatedCookies.find((cookie) => cookie.name === 'PHPSESSID');
    expect(rotatedSession).toBeTruthy();
    expect(rotatedSession.value).not.toBe(originalSession.value);

    await page.goto('/history');
    await expect(page.locator('.quiz-history-item')).toHaveCount(0);
    await secondPage.goto('/history');
    await expect(secondPage.locator('.quiz-history-item')).toHaveCount(1);
    await secondContext.close();
    recordBrowserEvidence('privacy', {
      export: 'PASS',
      cancel: 'PASS',
      confirmedDeletion: 'PASS',
      sessionRotation: 'PASS',
      postDeletionHistory: 'PASS',
      secondLearnerPreserved: 'PASS',
      sessionCookieHttpOnly: 'PASS',
      sessionCookieSameSiteLax: 'PASS',
    }, testInfo);
  });

  test('production browser flow reaches study, practice, results, history, and progress for all boards', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'mobile', 'The complete ten-board browser matrix runs once at a phone viewport.');
    test.setTimeout(180_000);

    for (const [boardIndex, [code]] of fullJourneyBoards.entries()) {
      await page.goto(`/exams?exam=${encodeURIComponent(code)}`);
      const boardName = await page.locator('.exam-detail h1').innerText();
      await expect(page.getByRole('link', { name: 'Study materials' })).toBeVisible();
      await page.getByRole('link', { name: 'Study materials' }).click();
      await expect(page.locator('h1')).toHaveText('Study resources');
      await page.goBack();

      await page.getByRole('link', { name: 'Configure practice' }).click();
      await configureAvailableBoardQuiz(page, code);
      await completeQuiz(page, false);
      await expect(page.locator('.result-score h2')).toHaveText(/\d+\s*\/\s*5/);

      await page.goto('/history');
      await expect(page.locator('.quiz-history-item')).toHaveCount(boardIndex + 1);
      await page.getByRole('link', { name: 'View result' }).first().click();
      await expect(page.locator('h1')).toHaveText('Historical Quiz Result');
      await expect(page.locator('body')).toContainText(boardName);
      await page.goto('/progress');
      await expect(page.locator('h1')).toHaveText('Know what to review next');
    }
    recordBrowserEvidence('ten-board', {
      boards: fullJourneyBoards.map(([code]) => code),
      studyPracticeResultsHistoryProgress: 'PASS',
    }, testInfo);
  });

  test('mobile layouts remain in the viewport at supported narrow widths', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'mobile', 'Viewport sweep runs once in the mobile project.');
    const viewports = [
      { width: 320, height: 640 },
      { width: 360, height: 800 },
      { width: 390, height: 844 },
      { width: 430, height: 932 },
      { width: 768, height: 1024 },
    ];
    const routes = [
      '/dashboard',
      '/exams',
      '/study-library?exam=LET',
      '/quiz',
      '/flashcards?include_new=1',
      '/progress',
      '/history',
      '/privacy',
    ];

    for (const viewport of viewports) {
      await page.setViewportSize(viewport);
      for (const route of routes) {
        await page.goto(route);
        const layout = await page.evaluate(() => {
          const viewportWidth = document.documentElement.clientWidth;
          const interactive = [...document.querySelectorAll(
            'main button, main a.button, main select, .mobile-menu-button'
          )].filter((element) => {
            const rect = element.getBoundingClientRect();
            return rect.width > 0 && rect.height > 0;
          });
          return {
            viewportWidth,
            documentWidth: document.documentElement.scrollWidth,
            clippedControls: interactive
              .filter((element) => {
                const rect = element.getBoundingClientRect();
                return rect.left < -1 || rect.right > viewportWidth + 1;
              })
              .map((element) => element.outerHTML.slice(0, 120)),
            shortTouchTargets: interactive
              .filter((element) => element.matches('button, a.button, .mobile-menu-button'))
              .filter((element) => element.getBoundingClientRect().height < 44)
              .map((element) => element.outerHTML.slice(0, 120)),
          };
        });
        expect(layout.documentWidth, `${route} at ${viewport.width}px`).toBeLessThanOrEqual(viewport.width + 2);
        expect(layout.clippedControls, `${route} clipped controls at ${viewport.width}px`).toEqual([]);
        expect(layout.shortTouchTargets, `${route} undersized primary touch controls at ${viewport.width}px`).toEqual([]);
      }
    }
    recordBrowserEvidence('viewports', {
      sizes: viewports.map(({ width, height }) => `${width}x${height}`),
      routes,
      horizontalOverflow: 'PASS',
      primaryControlBounds: 'PASS',
      primaryTouchTargetMinimum: 'PASS',
    }, testInfo);
  });
});
