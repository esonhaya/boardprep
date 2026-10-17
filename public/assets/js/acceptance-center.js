(() => {
  'use strict';

  const API = '/developer/acceptance';
  const LEARNER_TAB = 'boardprepAcceptanceJourney';
  const emptyObservation = () => ({
    category: 'OTHER',
    priority: 'UNSURE',
    comments: '',
    expected_behavior: '',
    actual_behavior: '',
    suggested_improvement: '',
    reproduction_steps: '',
    evidence_reference: '',
  });

  const state = {
    csrf: '',
    scenarios: [],
    session: null,
    sessions: [],
    currentBuild: '',
    learnerBaseUrl: '',
    categories: [],
    priorities: [],
    results: [],
    activeScreen: 'dashboard',
    activeCase: 'MVP-01',
    editingObservation: null,
    editingGeneral: null,
    previewReport: null,
    previewMarkdown: '',
    saveQueue: Promise.resolve(),
    metadataTimer: null,
    draftTimer: null,
    generalDraftTimer: null,
    formsConfigured: false,
  };

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  })[character]);

  function showNotice(message, kind = 'success') {
    const notice = $('#notice');
    notice.textContent = message;
    notice.className = `ac-notice ${kind === 'error' ? 'error' : kind === 'warning' ? 'warning' : ''}`;
    notice.hidden = false;
    if (kind !== 'error') {
      window.clearTimeout(showNotice.timer);
      showNotice.timer = window.setTimeout(() => { notice.hidden = true; }, 5000);
    }
  }

  function saveStatus(element, message, kind = '') {
    if (!element) return;
    element.textContent = message;
    element.className = `save-indicator ${kind}`.trim();
  }

  async function parseResponse(response) {
    let result;
    try {
      result = await response.json();
    } catch {
      throw new Error('The local test server returned an unreadable response.');
    }
    if (!response.ok || result.ok !== true) {
      throw new Error(result.error || 'That change could not be saved.');
    }
    return result;
  }

  async function getData(sessionId = '') {
    const query = sessionId ? `?session_id=${encodeURIComponent(sessionId)}` : '';
    const result = await parseResponse(await fetch(`${API}/data${query}`, {
      credentials: 'same-origin',
      cache: 'no-store',
    }));
    state.csrf = result.csrf_token;
    state.session = result.session;
    state.sessions = result.sessions || [];
    state.scenarios = result.scenarios || [];
    state.currentBuild = result.current_build_sha || '';
    state.learnerBaseUrl = result.learner_base_url || location.origin;
    state.categories = result.categories || [];
    state.priorities = result.priorities || [];
    state.results = result.results || [];
    state.activeCase = state.session.active_case || 'MVP-01';
    history.replaceState(null, '', `${location.pathname}?session_id=${encodeURIComponent(state.session.session_id)}`);
    configureFeedbackForms();
    fillMetadata();
    renderAll();
    return result;
  }

  function postUpdate(payload, { quiet = false, render = false } = {}) {
    const sessionId = payload.session_id || state.session?.session_id;
    if (!sessionId) return Promise.reject(new Error('Start a test session before saving.'));
    const requestData = { ...payload, session_id: sessionId };
    state.saveQueue = state.saveQueue
      .catch(() => undefined)
      .then(async () => {
        const response = await fetch(`${API}/update`, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'X-BoardPrep-CSRF': state.csrf,
          },
          body: JSON.stringify(requestData),
        });
        const result = await parseResponse(response);
        state.session = result.session;
        state.sessions = result.sessions || state.sessions;
        state.activeCase = state.session.active_case || state.activeCase;
        clearPreview();
        renderDashboard();
        renderSessionPicker();
        if (render) renderAll();
        if (!quiet) showNotice('Saved on this device.');
        return result;
      })
      .catch((error) => {
        if (!quiet) showNotice(error.message, 'error');
        throw error;
      });
    return state.saveQueue;
  }

  function clearPreview() {
    state.previewReport = null;
    state.previewMarkdown = '';
    $('#copyMarkdown').disabled = true;
    $('#downloadMarkdown').disabled = true;
    $('#downloadJson').disabled = true;
    $('#shareReport').disabled = true;
    $('#reportPreview').textContent = 'Your saved notes changed. Refresh the preview before exporting.';
  }

  function currentCase(caseId = state.activeCase) {
    return state.scenarios.find((scenario) => scenario.case_id === caseId) || state.scenarios[0];
  }

  function currentCaseRecord(caseId = state.activeCase) {
    return state.session?.cases?.[caseId] || null;
  }

  function draftHasContent(draft) {
    return Object.entries(draft || {}).some(([key, value]) =>
      !['category', 'priority'].includes(key) && String(value || '').trim() !== '');
  }

  function showScreen(screen) {
    state.activeScreen = screen;
    $$('[data-screen-panel]').forEach((panel) => {
      panel.hidden = panel.dataset.screenPanel !== screen;
    });
    $$('[data-screen]').forEach((button) => {
      const active = button.dataset.screen === screen;
      button.classList.toggle('active', active);
      if (button.classList.contains('tab-button')) button.setAttribute('aria-current', active ? 'page' : 'false');
    });
    if (screen === 'overview') renderOverview();
    if (screen === 'general') renderGeneralObservations();
    if (screen === 'export') $('#exportTitle').focus?.();
  }

  async function focusCase(caseId, screen = 'case') {
    if (!state.scenarios.some((item) => item.case_id === caseId)) return;
    if (state.activeScreen === 'case') await flushCaseDraft();
    state.activeCase = caseId;
    try {
      const result = await postUpdate({ action: 'focus', case_id: caseId }, { quiet: true });
      state.session = result.session;
    } catch {
      // Keep the selected test visible. A later save retries persistence.
    }
    state.editingObservation = null;
    renderCase();
    showScreen(screen);
  }

  function renderAll() {
    if (!state.session) return;
    $('#buildSha').textContent = state.session.build_sha || 'Unavailable';
    $('#testDate').textContent = state.session.test_date || '—';
    $('#buildWarning').hidden = state.session.build_sha === state.currentBuild;
    if (!$('#buildWarning').hidden) {
      $('#buildWarning').textContent = `The running app is ${state.currentBuild.slice(0, 12)}. This session began on ${String(state.session.build_sha).slice(0, 12)}. Start a new session to test this build.`;
    }
    renderSessionPicker();
    renderDashboard();
    renderCase();
    renderOverview();
    renderGeneralObservations();
    showScreen(state.activeScreen);
    saveStatus($('#saveIndicator'), 'Saved on this device', 'saved');
    $('#launchNextTest').disabled = state.scenarios.length === 0;
  }

  function fillMetadata() {
    const session = state.session;
    $('#testerAlias').value = session.tester_alias || '';
    $('#deviceInfo').value = session.device || '';
    const detectedOS = detectOperatingSystem();
    $('#operatingSystemInfo').value = session.operating_system || detectedOS;
    const detectedBrowser = detectBrowser();
    $('#browserInfo').value = session.browser || detectedBrowser;
    $('#viewportInfo').value = currentViewport();
    if ((!session.browser && detectedBrowser)
      || (!session.operating_system && detectedOS)
      || session.viewport !== $('#viewportInfo').value) scheduleMetadataSave();
  }

  function detectOperatingSystem() {
    const ua = navigator.userAgent;
    const android = ua.match(/Android\s+([\d.]+)/i);
    if (android) return `Android ${android[1]}`;
    const ios = ua.match(/(?:iPhone OS|CPU OS)\s+([\d_]+)/i);
    if (ios) return `iOS ${ios[1].replaceAll('_', '.')}`;
    if (/Windows NT/i.test(ua)) return 'Windows (version not detected)';
    if (/Macintosh|Mac OS X/i.test(ua)) return 'macOS (version not detected)';
    if (/Linux/i.test(ua)) return 'Linux (version not detected)';
    return '';
  }

  function currentViewport() {
    return `${window.innerWidth}x${window.innerHeight} CSS px`;
  }

  function detectBrowser() {
    const ua = navigator.userAgent;
    const chrome = ua.match(/(?:Chrome|CriOS)\/([\d.]+)/);
    const firefox = ua.match(/(?:Firefox|FxiOS)\/([\d.]+)/);
    const edge = ua.match(/Edg\/([\d.]+)/);
    if (edge) return `Microsoft Edge ${edge[1]}`;
    if (chrome) return `Chrome ${chrome[1]}`;
    if (firefox) return `Firefox ${firefox[1]}`;
    if (/Safari\//.test(ua)) return 'Safari (version not detected)';
    return '';
  }

  function renderSessionPicker() {
    const picker = $('#savedSessions');
    if (!picker) return;
    const current = state.session?.session_id || '';
    picker.replaceChildren();
    state.sessions.forEach((session) => {
      const option = document.createElement('option');
      option.value = session.session_id;
      option.textContent = `${new Date(session.created_at).toLocaleString()} · ${session.tests_recorded}/12 recorded${session.build_sha !== state.currentBuild ? ' · older build' : ''}`;
      option.selected = session.session_id === current;
      picker.append(option);
    });
  }

  function recordedCount() {
    return Object.values(state.session?.cases || {}).filter((item) => item.result && item.result !== 'NOT_RUN').length;
  }

  function resultCounts() {
    const counts = { PASS: 0, FAIL: 0, BLOCKED: 0, NOT_APPLICABLE: 0, NOT_RUN: 0 };
    Object.values(state.session?.cases || {}).forEach((item) => {
      const status = item.result || 'NOT_RUN';
      if (Object.hasOwn(counts, status)) counts[status] += 1;
      else counts.NOT_RUN += 1;
    });
    return counts;
  }

  function nextScenario() {
    const active = currentCaseRecord(state.session?.active_case);
    const hasSavedWork = Boolean(
      (active?.tested_routes || []).length
      || (active?.observations || []).length
      || draftHasContent(active?.draft)
    );
    if (hasSavedWork && active?.result === 'NOT_RUN') {
      const activeScenario = currentCase(state.session.active_case);
      if (activeScenario) return activeScenario;
    }
    return state.scenarios.find((scenario) => currentCaseRecord(scenario.case_id)?.result === 'NOT_RUN')
      || state.scenarios[0];
  }

  function renderDashboard() {
    if (!state.session || state.scenarios.length === 0) return;
    const count = recordedCount();
    const percent = Math.round(count / state.scenarios.length * 100);
    $('#progressText').textContent = `${count} of ${state.scenarios.length} tests have a recorded result`;
    $('#progressPercent').textContent = `${percent}%`;
    $('#overallProgress').value = count;
    $('#overallProgress').max = state.scenarios.length;
    const next = nextScenario();
    const hasUnrun = state.scenarios.some((scenario) => currentCaseRecord(scenario.case_id)?.result === 'NOT_RUN');
    const activeIsResumable = next.case_id === state.session.active_case
      && Boolean((currentCaseRecord(next.case_id)?.tested_routes || []).length
        || (currentCaseRecord(next.case_id)?.observations || []).length
        || draftHasContent(currentCaseRecord(next.case_id)?.draft));
    $('#nextCaseId').textContent = hasUnrun ? next.case_id : 'REVIEW';
    $('#nextCaseTitle').textContent = hasUnrun ? `${activeIsResumable ? 'Resume' : 'Start'}: ${next.title}` : 'All tests have a saved result';
    $('#nextCaseWhy').textContent = hasUnrun
      ? `${next.why} ${next.start}`
      : 'You can revisit any test, update a result, or add more feedback.';
    $('#launchNextTest').textContent = hasUnrun ? (activeIsResumable ? 'Resume this test' : 'Launch next test') : 'Review a test';
    const counts = resultCounts();
    $('#quickSummary').replaceChildren(...Object.entries(counts).map(([label, value]) => {
      const badge = document.createElement('span');
      badge.textContent = `${label.replace('_', ' ')} · ${value}`;
      return badge;
    }));
  }

  function renderCase() {
    const scenario = currentCase();
    const record = currentCaseRecord();
    if (!scenario || !record) return;
    $('#caseIdLabel').textContent = scenario.case_id;
    $('#caseTitle').textContent = scenario.title;
    $('#caseWhy').textContent = scenario.why;
    $('#caseStart').textContent = scenario.start;
    $('#caseActions').textContent = scenario.actions;
    $('#caseSuccess').textContent = scenario.success;
    $('#caseWatchFor').textContent = scenario.watch_for;
    const badge = $('#caseOutcomeBadge');
    badge.textContent = (record.result || 'NOT_RUN').replaceAll('_', ' ');
    badge.className = `outcome-badge ${(record.result || '').toLowerCase()}`;
    renderRouteLinks(scenario, record);
    renderOutcome(record);
    setFormValues($('[data-feedback-fields="case"]'), record.draft || emptyObservation());
    renderCaseObservations(record);
    $('#previousCase').disabled = state.scenarios.findIndex((item) => item.case_id === scenario.case_id) === 0;
    $('#nextCase').disabled = state.scenarios.findIndex((item) => item.case_id === scenario.case_id) === state.scenarios.length - 1;
  }

  function renderRouteLinks(scenario, record) {
    const container = $('#routeLinks');
    container.replaceChildren();
    scenario.routes.forEach((route, index) => {
      const link = document.createElement('a');
      link.className = 'route-link';
      link.href = new URL(route.path, state.learnerBaseUrl).toString();
      link.target = LEARNER_TAB;
      link.textContent = `${index + 1}. ${route.label}`;
      link.setAttribute('aria-label', `Open ${route.label} in the learner tab`);
      link.addEventListener('click', () => {
        void postUpdate({ action: 'launch', case_id: scenario.case_id, path: route.path }, { quiet: true })
          .then(() => { $('#routesOpened').textContent = `Opened from this guide: ${route.label}. Return to this tab when you are ready to record your result.`; })
          .catch((error) => showNotice(error.message, 'error'));
      });
      container.append(link);
    });
    const routes = record.tested_routes || [];
    $('#routesOpened').textContent = routes.length
      ? `Opened from this guide: ${routes.join(', ')}`
      : 'No route has been launched from this guide yet.';
  }

  function renderOutcome(record) {
    $$('input[name="humanResult"]').forEach((input) => {
      input.checked = input.value === (record.result || 'NOT_RUN');
    });
    $('#confirmPass').checked = record.pass_confirmed === true;
  }

  function renderOverview() {
    const container = $('#caseList');
    if (!container || state.scenarios.length === 0) return;
    container.replaceChildren();
    state.scenarios.forEach((scenario) => {
      const result = currentCaseRecord(scenario.case_id)?.result || 'NOT_RUN';
      const row = document.createElement('article');
      row.className = 'case-list-item';
      const copy = document.createElement('div');
      const eyebrow = document.createElement('p');
      eyebrow.className = 'eyebrow';
      eyebrow.textContent = `${scenario.case_id} · ${result.replaceAll('_', ' ')}`;
      const title = document.createElement('div');
      title.className = 'case-list-title';
      title.textContent = scenario.title;
      const description = document.createElement('p');
      description.textContent = scenario.why;
      copy.append(eyebrow, title, description);
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'button secondary';
      button.textContent = 'Open guide';
      button.addEventListener('click', () => { void focusCase(scenario.case_id); });
      row.append(copy, button);
      container.append(row);
    });
  }

  function configureFeedbackForms() {
    if (state.formsConfigured) return;
    $$('[data-feedback-fields]').forEach((form) => {
      const category = $('[data-field="category"]', form);
      const priority = $('[data-field="priority"]', form);
      if (category.options.length === 0) {
        category.replaceChildren(...state.categories.map((value) => {
          const option = document.createElement('option');
          option.value = value;
          option.textContent = value.replaceAll('_', ' ');
          return option;
        }));
      }
      if (priority.options.length === 0) {
        priority.replaceChildren(...state.priorities.map((value) => {
          const option = document.createElement('option');
          option.value = value;
          option.textContent = value;
          return option;
        }));
      }
      $$('input, textarea, select', form).forEach((input) => {
        input.addEventListener('input', () => {
          if (form.dataset.feedbackFields === 'case') scheduleCaseDraftSave();
          else scheduleGeneralDraftSave();
        });
        input.addEventListener('change', () => {
          if (form.dataset.feedbackFields === 'case') scheduleCaseDraftSave();
          else scheduleGeneralDraftSave();
        });
      });
    });
    state.formsConfigured = true;
  }

  function collectForm(form) {
    const value = emptyObservation();
    Object.keys(value).forEach((field) => {
      const control = $(`[data-field="${field}"]`, form);
      if (control) value[field] = control.value;
    });
    return value;
  }

  function setFormValues(form, values) {
    if (!form) return;
    Object.entries(values || {}).forEach(([field, value]) => {
      const control = $(`[data-field="${field}"]`, form);
      if (control) control.value = value ?? '';
    });
  }

  function scheduleCaseDraftSave() {
    const indicator = $('#draftStatus');
    saveStatus(indicator, 'Saving draft…');
    window.clearTimeout(state.draftTimer);
    state.draftTimer = window.setTimeout(() => { void flushCaseDraft(); }, 450);
  }

  function scheduleGeneralDraftSave() {
    saveStatus($('#generalDraftStatus'), 'Saving draft…');
    window.clearTimeout(state.generalDraftTimer);
    state.generalDraftTimer = window.setTimeout(() => { void flushGeneralDraft(); }, 450);
  }

  async function flushCaseDraft() {
    if (state.activeScreen !== 'case' && !state.editingObservation) return;
    const form = $('[data-feedback-fields="case"]');
    if (!form || !currentCaseRecord()) return;
    window.clearTimeout(state.draftTimer);
    try {
      await postUpdate({ action: 'draft', case_id: state.activeCase, draft: collectForm(form) }, { quiet: true });
      saveStatus($('#draftStatus'), 'Draft saved', 'saved');
    } catch (error) {
      saveStatus($('#draftStatus'), error.message, 'error');
    }
  }

  async function flushGeneralDraft() {
    const form = $('[data-feedback-fields="general"]');
    if (!form || !state.session) return;
    window.clearTimeout(state.generalDraftTimer);
    try {
      await postUpdate({ action: 'draft', general: true, draft: collectForm(form) }, { quiet: true });
      saveStatus($('#generalDraftStatus'), 'Draft saved', 'saved');
    } catch (error) {
      saveStatus($('#generalDraftStatus'), error.message, 'error');
    }
  }

  function scheduleMetadataSave() {
    saveStatus($('#saveIndicator'), 'Saving…');
    window.clearTimeout(state.metadataTimer);
    state.metadataTimer = window.setTimeout(() => { void flushMetadata(); }, 500);
  }

  async function flushMetadata() {
    if (!state.session) return;
    window.clearTimeout(state.metadataTimer);
    try {
      await postUpdate({
        action: 'metadata',
        metadata: {
          tester_alias: $('#testerAlias').value,
          device: $('#deviceInfo').value,
          operating_system: $('#operatingSystemInfo').value,
          browser: $('#browserInfo').value,
          viewport: $('#viewportInfo').value,
        },
      }, { quiet: true });
      saveStatus($('#saveIndicator'), 'Saved on this device', 'saved');
    } catch (error) {
      saveStatus($('#saveIndicator'), error.message, 'error');
    }
  }

  function renderCaseObservations(record) {
    const container = $('#savedObservations');
    container.replaceChildren();
    (record.observations || []).forEach((observation, index) => {
      container.append(createObservationCard(observation, index, false));
    });
    if ((record.observations || []).length === 0) {
      const empty = document.createElement('p');
      empty.className = 'small-note';
      empty.textContent = 'No notes saved yet.';
      container.append(empty);
    }
  }

  function renderGeneralObservations() {
    const container = $('#generalObservations');
    if (!container) return;
    container.replaceChildren();
    (state.session?.general_feedback || []).forEach((observation, index) => {
      container.append(createObservationCard(observation, index, true));
    });
    if ((state.session?.general_feedback || []).length === 0) {
      const empty = document.createElement('p');
      empty.className = 'small-note';
      empty.textContent = 'No general notes saved yet.';
      container.append(empty);
    }
    setFormValues($('[data-feedback-fields="general"]'), state.session?.general_draft || emptyObservation());
  }

  function createObservationCard(observation, index, general) {
    const card = document.createElement('article');
    card.className = 'observation-card';
    const top = document.createElement('div');
    top.className = 'observation-top';
    const tags = document.createElement('div');
    tags.className = 'observation-tags';
    [observation.category, observation.priority].forEach((value) => {
      const tag = document.createElement('span');
      tag.className = 'observation-tag';
      tag.textContent = String(value || '').replaceAll('_', ' ');
      tags.append(tag);
    });
    const actions = document.createElement('div');
    actions.className = 'observation-actions';
    const moveUp = smallButton('Move note up', '↑');
    const moveDown = smallButton('Move note down', '↓');
    const edit = smallButton('Edit note', 'Edit');
    const remove = smallButton('Delete note', 'Delete');
    moveUp.disabled = index === 0;
    const count = general ? state.session.general_feedback.length : currentCaseRecord()?.observations.length || 0;
    moveDown.disabled = index === count - 1;
    moveUp.addEventListener('click', () => mutateObservation('move', observation, general, 'up'));
    moveDown.addEventListener('click', () => mutateObservation('move', observation, general, 'down'));
    edit.addEventListener('click', () => beginEdit(observation, general));
    remove.addEventListener('click', () => deleteObservation(observation, general));
    actions.append(moveUp, moveDown, edit, remove);
    top.append(tags, actions);
    const copy = document.createElement('div');
    copy.className = 'observation-copy';
    const labels = [
      ['comments', 'What I noticed'],
      ['expected_behavior', 'What I expected'],
      ['actual_behavior', 'What happened instead'],
      ['suggested_improvement', 'Suggested improvement'],
      ['reproduction_steps', 'Steps'],
      ['evidence_reference', 'Evidence reference'],
    ];
    labels.forEach(([field, label]) => {
      if (!observation[field]) return;
      const paragraph = document.createElement('p');
      const strong = document.createElement('strong');
      strong.textContent = `${label}: `;
      paragraph.append(strong, document.createTextNode(observation[field]));
      copy.append(paragraph);
    });
    card.append(top, copy);
    return card;
  }

  function smallButton(label, text) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'small-button';
    button.setAttribute('aria-label', label);
    button.textContent = text;
    return button;
  }

  async function mutateObservation(action, observation, general, direction = '') {
    try {
      const result = await postUpdate({
        action: 'observation',
        observation_action: action,
        general,
        case_id: general ? undefined : state.activeCase,
        observation_id: observation.observation_id,
        direction,
      }, { quiet: true });
      state.session = result.session;
      renderCaseObservations(currentCaseRecord());
      renderGeneralObservations();
      clearPreview();
      showNotice(action === 'delete' ? 'Note deleted.' : 'Note order updated.');
    } catch (error) {
      showNotice(error.message, 'error');
    }
  }

  async function deleteObservation(observation, general) {
    if (!window.confirm('Delete this note from the saved test session?')) return;
    await mutateObservation('delete', observation, general);
  }

  async function beginEdit(observation, general) {
    const form = $(`[data-feedback-fields="${general ? 'general' : 'case'}"]`);
    setFormValues(form, observation);
    if (general) state.editingGeneral = observation.observation_id;
    else {
      state.editingObservation = observation.observation_id;
      if (state.activeScreen !== 'case') await focusCase(state.activeCase);
    }
    const button = general ? $('#addGeneralFeedback') : $('#addObservation');
    button.textContent = 'Update note';
    addCancelEditButton(general);
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function addCancelEditButton(general) {
    const actionRow = general ? $('#addGeneralFeedback').parentElement : $('#addObservation').parentElement;
    if ($('#cancelEdit', actionRow)) return;
    const cancel = document.createElement('button');
    cancel.id = 'cancelEdit';
    cancel.type = 'button';
    cancel.className = 'button secondary';
    cancel.textContent = 'Cancel edit';
    cancel.addEventListener('click', () => cancelEdit(general));
    actionRow.append(cancel);
  }

  function cancelEdit(general) {
    if (general) state.editingGeneral = null;
    else state.editingObservation = null;
    setFormValues($(`[data-feedback-fields="${general ? 'general' : 'case'}"]`), emptyObservation());
    const button = general ? $('#addGeneralFeedback') : $('#addObservation');
    button.textContent = 'Save this note';
    $('#cancelEdit', button.parentElement)?.remove();
  }

  async function submitObservation(general) {
    const form = $(`[data-feedback-fields="${general ? 'general' : 'case'}"]`);
    const observation = collectForm(form);
    const isEditing = general ? state.editingGeneral : state.editingObservation;
    const detailFields = [
      'comments',
      'expected_behavior',
      'actual_behavior',
      'suggested_improvement',
      'reproduction_steps',
      'evidence_reference',
    ];
    if (!detailFields.some((field) => String(observation[field] || '').trim() !== '')) {
      showNotice('Add a comment or another detail before saving this note.', 'warning');
      $('[data-field="comments"]', form)?.focus();
      return;
    }
    window.clearTimeout(general ? state.generalDraftTimer : state.draftTimer);
    await state.saveQueue.catch(() => undefined);
    try {
      const result = await postUpdate({
        action: 'observation',
        observation_action: isEditing ? 'edit' : 'add',
        general,
        case_id: general ? undefined : state.activeCase,
        observation_id: isEditing || undefined,
        observation,
      }, { quiet: true });
      state.session = result.session;
      if (general) {
        state.editingGeneral = null;
        setFormValues(form, state.session.general_draft || emptyObservation());
        renderGeneralObservations();
        $('#addGeneralFeedback').textContent = 'Save this note';
      } else {
        state.editingObservation = null;
        renderCase();
        $('#addObservation').textContent = 'Save this note';
      }
      $('#cancelEdit', (general ? $('#addGeneralFeedback') : $('#addObservation')).parentElement)?.remove();
      clearPreview();
      showNotice(isEditing ? 'Your note was updated.' : 'Your note was saved. You can add another one.');
    } catch (error) {
      showNotice(error.message, 'error');
    }
  }

  async function launchScenario(scenario, route = scenario.routes[0]) {
    if (!scenario || !route) return;
    const learnerUrl = new URL(route.path, state.learnerBaseUrl).toString();
    const learnerTab = window.open(learnerUrl, LEARNER_TAB);
    await focusCase(scenario.case_id);
    if (!learnerTab) {
      showNotice('Your browser blocked the learner tab. Use one of the “Open a learner page” links in this guide.', 'warning');
      return;
    }
    try {
      await postUpdate({ action: 'launch', case_id: scenario.case_id, path: route.path }, { quiet: true });
      showNotice(`Opened ${route.label} in the learner tab. Return here when you are ready to record your result.`);
    } catch (error) {
      showNotice(error.message, 'error');
    }
  }

  async function flushBeforeSwitch() {
    await state.saveQueue.catch(() => undefined);
    if (state.activeScreen === 'case') await flushCaseDraft();
    if (state.activeScreen === 'general') await flushGeneralDraft();
    await flushMetadata();
  }

  async function changeSession(sessionId) {
    if (!sessionId || sessionId === state.session.session_id) return;
    await flushBeforeSwitch();
    try {
      await getData(sessionId);
      clearPreview();
      showNotice('Resumed the selected saved session.');
    } catch (error) {
      showNotice(error.message, 'error');
    }
  }

  async function createNewSession() {
    const hasHistory = state.sessions.length > 0;
    const message = hasHistory
      ? 'Start a separate test session? Every saved result and note in this session will remain available.'
      : 'Start a new guided test session?';
    if (!window.confirm(message)) return;
    await flushBeforeSwitch();
    try {
      const result = await postUpdate({ action: 'new' }, { quiet: true, render: false });
      state.session = result.session;
      state.sessions = result.sessions || state.sessions;
      state.activeCase = 'MVP-01';
      state.activeScreen = 'dashboard';
      clearPreview();
      renderAll();
      showNotice('A new session is ready. Earlier sessions were kept.');
    } catch (error) {
      showNotice(error.message, 'error');
    }
  }

  async function saveOutcome() {
    const selected = $('input[name="humanResult"]:checked');
    if (!selected) {
      showNotice('Choose the result you observed first.', 'error');
      return;
    }
    if (selected.value === 'PASS' && !$('#confirmPass').checked) {
      showNotice('Before recording PASS, confirm that you personally tried the test and saw the success conditions.', 'error');
      $('#confirmPass').focus();
      return;
    }
    await flushCaseDraft();
    try {
      await postUpdate({
        action: 'outcome',
        case_id: state.activeCase,
        result: selected.value,
        pass_confirmed: $('#confirmPass').checked,
      }, { render: true });
      showNotice(`Saved ${selected.value.replaceAll('_', ' ')} for ${state.activeCase}.`);
    } catch (error) {
      showNotice(error.message, 'error');
    }
  }

  function fenced(text) {
    const value = String(text ?? '');
    const runs = value.match(/`+/g) || [];
    const fence = '`'.repeat(Math.max(3, ...runs.map((run) => run.length + 1)));
    return `${fence}\n${value}\n${fence}`;
  }

  function listField(value) {
    if (!Array.isArray(value) || value.length === 0) return '_None recorded._';
    return value.map((item) => `- ${String(item)}`).join('\n');
  }

  function reportToMarkdown(report) {
    const fields = [
      'PROJECT', 'REPORT_TYPE', 'REPORT_SCHEMA_VERSION', 'SESSION_ID', 'BUILD_SHA', 'TEST_DATE',
      'TESTER_ALIAS', 'DEVICE', 'OS_AND_VERSION', 'BROWSER', 'VIEWPORT', 'ENVIRONMENT', 'TESTS_TOTAL', 'TESTS_PASSED',
      'TESTS_FAILED', 'TESTS_BLOCKED', 'TESTS_NOT_RUN', 'TESTS_NOT_APPLICABLE', 'OVERALL_HUMAN_ACCEPTANCE_STATUS',
    ];
    const sections = ['# BoardPrep human acceptance feedback', '', ...fields.map((field) => {
      const value = ['TESTER_ALIAS', 'DEVICE', 'OS_AND_VERSION', 'BROWSER', 'VIEWPORT', 'ENVIRONMENT'].includes(field)
        ? String(report[field] ?? '').replace(/[\r\n]+/g, ' ')
        : report[field] ?? '';
      return `${field}=${value}`;
    }), ''];
    sections.push('## MVP test results', '');
    (report.CASE_RESULTS || []).forEach((item) => {
      sections.push(`### ${item.CASE_ID} — ${item.CASE_TITLE}`, '');
      sections.push(`CASE_ID=${item.CASE_ID}`);
      sections.push(`CASE_TITLE=${item.CASE_TITLE}`);
      sections.push('TESTED_ROUTE:', listField(item.TESTED_ROUTE));
      sections.push(`HUMAN_RESULT=${item.HUMAN_RESULT}`);
      sections.push('FEEDBACK_CATEGORY:', listField(item.FEEDBACK_CATEGORY));
      sections.push('PRIORITY:', listField(item.PRIORITY), '');
      (item.OBSERVATIONS || []).forEach((observation, index) => {
        sections.push(`#### Observation ${index + 1}`, '');
        sections.push(`FEEDBACK_CATEGORY=${observation.category || 'OTHER'}`);
        sections.push(`PRIORITY=${observation.priority || 'UNSURE'}`, '');
        [
          ['comments', 'What the tester noticed'],
          ['expected_behavior', 'What the tester expected'],
          ['actual_behavior', 'What happened instead'],
          ['suggested_improvement', 'Suggested improvement'],
          ['reproduction_steps', 'Reproduction steps'],
          ['evidence_reference', 'Evidence reference'],
        ].forEach(([field, label]) => {
          if (observation[field]) sections.push(`**${label}:**`, '', fenced(observation[field]), '');
        });
      });
      if (!item.OBSERVATIONS?.length) {
        sections.push('_No notes recorded for this test._');
        sections.push('COMMENTS=_None recorded._');
        sections.push('EXPECTED_BEHAVIOR=_None recorded._');
        sections.push('ACTUAL_BEHAVIOR=_None recorded._');
        sections.push('SUGGESTED_IMPROVEMENT=_None recorded._');
        sections.push('REPRODUCTION_STEPS=_None recorded._');
        sections.push('EVIDENCE_REFERENCES=_None recorded._', '');
      }
    });
    sections.push('## GENERAL_FEEDBACK · General feedback', '');
    sections.push(renderObservationGroup(report.GENERAL_FEEDBACK || []));
    sections.push('## ALL_REPORTED_BUGS · All reported bugs', '');
    sections.push(renderTriageGroup(report.ALL_REPORTED_BUGS || []));
    sections.push('## ALL_REQUESTED_IMPROVEMENTS · Requested improvements and missing features', '');
    sections.push(renderTriageGroup(report.ALL_REQUESTED_IMPROVEMENTS || []));
    sections.push('## UNRESOLVED_TESTS · Unresolved tests', '');
    sections.push(report.UNRESOLVED_TESTS?.length
      ? report.UNRESOLVED_TESTS.map((item) => `- ${item.CASE_ID}: ${item.HUMAN_RESULT}`).join('\n')
      : '_None._');
    sections.push('', '## SUMMARY_FOR_NEXT_CODEX_BATCH · Summary for the next Codex batch', '', report.SUMMARY_FOR_NEXT_CODEX_BATCH || '', '');
    sections.push('## Other feedback fields', '', `

JSON export includes all expected, actual, improvement, reproduction-step, priority, and evidence-reference fields for every observation. Report counts: ${JSON.stringify(report.FEEDBACK_COUNTS_BY_CATEGORY || {})}.`);
    return sections.join('\n');
  }

  function renderObservationGroup(items) {
    if (!items.length) return '_None recorded._';
    return items.map((item, index) => `### Note ${index + 1} (${item.category || 'OTHER'}, ${item.priority || 'UNSURE'})\n\n${renderObservationFields(item)}`).join('\n\n');
  }

  function renderTriageGroup(items) {
    if (!items.length) return '_None recorded._';
    return items.map((item) => `### ${item.case_id || 'General feedback'} · ${item.category || 'OTHER'} · ${item.priority || 'UNSURE'}\n\n${renderObservationFields(item)}`).join('\n\n');
  }

  function renderObservationFields(item) {
    return [
      ['comments', 'COMMENTS · What the tester noticed'],
      ['expected_behavior', 'EXPECTED_BEHAVIOR · What the tester expected'],
      ['actual_behavior', 'ACTUAL_BEHAVIOR · What happened instead'],
      ['suggested_improvement', 'SUGGESTED_IMPROVEMENT · Suggested improvement'],
      ['reproduction_steps', 'REPRODUCTION_STEPS · Steps to reproduce'],
      ['evidence_reference', 'EVIDENCE_REFERENCES · Evidence reference'],
    ].map(([field, label]) => {
      const value = String(item[field] ?? '');
      return `**${label}:**\n\n${value === '' ? '_Not provided._' : fenced(value)}`;
    }).join('\n\n');
  }

  async function refreshExportPreview() {
    try {
      await flushBeforeSwitch();
      const url = `${API}/export?session_id=${encodeURIComponent(state.session.session_id)}`;
      const result = await parseResponse(await fetch(url, { credentials: 'same-origin', cache: 'no-store' }));
      state.previewReport = result.report;
      state.previewMarkdown = reportToMarkdown(result.report);
      $('#reportPreview').textContent = state.previewMarkdown;
      $('#exportSummary').replaceChildren(...[
        `Passed ${result.report.TESTS_PASSED}`,
        `Failed ${result.report.TESTS_FAILED}`,
        `Blocked ${result.report.TESTS_BLOCKED}`,
        `Not run ${result.report.TESTS_NOT_RUN}`,
        `${(result.report.GENERAL_FEEDBACK || []).length} general notes`,
      ].map((text) => {
        const chip = document.createElement('span');
        chip.textContent = text;
        return chip;
      }));
      $('#copyMarkdown').disabled = false;
      $('#downloadMarkdown').disabled = false;
      $('#downloadJson').disabled = false;
      $('#shareReport').disabled = false;
      saveStatus($('#exportStatus'), 'Preview ready. Review it before copying, downloading, or sharing.', 'saved');
      $('#reportPreview').focus();
    } catch (error) {
      showNotice(error.message, 'error');
    }
  }

  function downloadText(fileName, mimeType, text) {
    const blob = new Blob([text], { type: `${mimeType};charset=utf-8` });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = fileName;
    link.click();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
  }

  async function copyReport() {
    if (!state.previewMarkdown) return;
    try {
      if (navigator.clipboard?.writeText) {
        try {
          await navigator.clipboard.writeText(state.previewMarkdown);
        } catch {
          if (!copyWithSelectionFallback(state.previewMarkdown)) throw new Error('Clipboard access was unavailable. Download the Markdown report instead.');
        }
      } else if (!copyWithSelectionFallback(state.previewMarkdown)) {
        throw new Error('Clipboard access was unavailable. Download the Markdown report instead.');
      }
      saveStatus($('#exportStatus'), 'Copied. Paste it into ChatGPT when you are ready; BoardPrep did not send it.', 'saved');
    } catch (error) {
      saveStatus($('#exportStatus'), `${error.message} You can still download the report.`, 'error');
    }
  }

  function copyWithSelectionFallback(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('aria-hidden', 'true');
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.append(textarea);
        textarea.select();
        const copied = document.execCommand('copy');
        textarea.remove();
        return copied;
  }

  async function shareReport() {
    if (!state.previewMarkdown) return;
    if (typeof navigator.share !== 'function') {
      saveStatus($('#exportStatus'), 'This browser does not offer a share sheet. Use copy or download instead.', 'error');
      return;
    }
    try {
      await navigator.share({ title: 'BoardPrep human acceptance feedback', text: state.previewMarkdown });
      saveStatus($('#exportStatus'), 'The browser share action completed. Check the destination yourself; BoardPrep does not confirm delivery.', 'saved');
    } catch (error) {
      if (error.name === 'AbortError') saveStatus($('#exportStatus'), 'Share cancelled. Your saved report is unchanged.');
      else saveStatus($('#exportStatus'), 'Sharing is unavailable here. Use copy or download instead.', 'error');
    }
  }

  function bindEvents() {
    $$('[data-screen]').forEach((button) => button.addEventListener('click', async () => {
      await flushBeforeSwitch();
      showScreen(button.dataset.screen);
    }));
    $$('[data-open-case]').forEach((button) => button.addEventListener('click', () => { void focusCase(button.dataset.openCase); }));
    ['testerAlias', 'deviceInfo', 'operatingSystemInfo', 'browserInfo'].forEach((id) => $(`#${id}`).addEventListener('input', scheduleMetadataSave));
    window.addEventListener('resize', () => {
      $('#viewportInfo').value = currentViewport();
      scheduleMetadataSave();
    });
    $('#savedSessions').addEventListener('change', (event) => { void changeSession(event.target.value); });
    $('#newSession').addEventListener('click', () => { void createNewSession(); });
    $('#launchNextTest').addEventListener('click', () => { void launchScenario(nextScenario()); });
    $('#returnToGuide').addEventListener('click', () => {
      $('#resultTitle').focus();
      $('#resultTitle').scrollIntoView({ behavior: 'smooth', block: 'center' });
      showNotice('Continue this test here. The learner page remains open in its other tab.');
    });
    $$('input[name="humanResult"]').forEach((input) => input.addEventListener('change', () => {
      if (input.value !== 'PASS' || $('#confirmPass').checked) void saveOutcome();
    }));
    $('#confirmPass').addEventListener('change', () => {
      if ($('#confirmPass').checked && $('input[name="humanResult"]:checked')?.value === 'PASS') void saveOutcome();
    });
    $('#saveOutcome').addEventListener('click', () => { void saveOutcome(); });
    $('#skipCase').addEventListener('click', async () => {
      await flushCaseDraft();
      const index = state.scenarios.findIndex((item) => item.case_id === state.activeCase);
      const next = state.scenarios[(index + 1) % state.scenarios.length];
      await focusCase(next.case_id);
      showNotice('Skipped for now. Its result remains unchanged.');
    });
    $('#previousCase').addEventListener('click', () => {
      const index = state.scenarios.findIndex((item) => item.case_id === state.activeCase);
      if (index > 0) void focusCase(state.scenarios[index - 1].case_id);
    });
    $('#nextCase').addEventListener('click', () => {
      const index = state.scenarios.findIndex((item) => item.case_id === state.activeCase);
      if (index < state.scenarios.length - 1) void focusCase(state.scenarios[index + 1].case_id);
    });
    $('#addObservation').addEventListener('click', () => { void submitObservation(false); });
    $('#addGeneralFeedback').addEventListener('click', () => { void submitObservation(true); });
    $('#refreshPreview').addEventListener('click', () => { void refreshExportPreview(); });
    $('#copyMarkdown').addEventListener('click', () => { void copyReport(); });
    $('#downloadMarkdown').addEventListener('click', () => {
      if (state.previewMarkdown) downloadText(`boardprep-human-feedback-${state.session.session_id}.md`, 'text/markdown', state.previewMarkdown);
    });
    $('#downloadJson').addEventListener('click', () => {
      if (state.previewReport) downloadText(`boardprep-human-feedback-${state.session.session_id}.json`, 'application/json', JSON.stringify(state.previewReport, null, 2));
    });
    $('#shareReport').addEventListener('click', () => { void shareReport(); });
    window.addEventListener('pagehide', () => {
      const draft = state.activeScreen === 'case' ? collectForm($('[data-feedback-fields="case"]')) : null;
      const generalDraft = state.activeScreen === 'general' ? collectForm($('[data-feedback-fields="general"]')) : null;
      if (!state.session || (!draft && !generalDraft)) return;
      const data = draft
        ? { action: 'draft', case_id: state.activeCase, draft, session_id: state.session.session_id }
        : { action: 'draft', general: true, draft: generalDraft, session_id: state.session.session_id };
      const body = JSON.stringify(data);
      void fetch(`${API}/update`, {
        method: 'POST',
        credentials: 'same-origin',
        keepalive: true,
        headers: { 'Content-Type': 'application/json', 'X-BoardPrep-CSRF': state.csrf },
        body,
      }).catch(() => undefined);
    });
  }

  async function checkBuild() {
    try {
      const response = await fetch(`${API}/build`, { cache: 'no-store', credentials: 'same-origin' });
      const result = await response.json();
      if (result.ok && result.build_sha !== state.currentBuild) {
        state.currentBuild = result.build_sha;
        const warning = $('#buildWarning');
        warning.hidden = state.session?.build_sha === state.currentBuild;
        warning.textContent = warning.hidden
          ? ''
          : `The running app is ${state.currentBuild.slice(0, 12)}. This session began on ${String(state.session.build_sha).slice(0, 12)}. Start a new session to test this build.`;
      }
    } catch {
      // A stopped preview is reported on the next save; local notes remain stored.
    }
  }

  async function start() {
    bindEvents();
    try {
      await getData(new URLSearchParams(location.search).get('session_id') || '');
      const hasUserAgent = Boolean($('#browserInfo').value);
      if (!hasUserAgent) scheduleMetadataSave();
      setInterval(() => { void checkBuild(); }, 60000);
    } catch (error) {
      showNotice(error.message, 'error');
      $('#launchNextTest').disabled = true;
      $('#nextCaseTitle').textContent = 'The guided session could not start';
      $('#nextCaseWhy').textContent = 'Check the local launch command and reload this page. Your saved reports are not deleted.';
      saveStatus($('#saveIndicator'), 'Not connected', 'error');
    }
  }

  document.addEventListener('DOMContentLoaded', start);
})();
