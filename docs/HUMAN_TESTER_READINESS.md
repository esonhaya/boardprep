# Human tester readiness

Run `php tools/doctor.php --simulate` first. Continue with manual MVP acceptance only when Doctor health and developer simulation health both pass.

## Acceptance journey

- First use: open the app, confirm the dashboard is understandable, and start a first quiz.
- Quiz: answer and navigate through questions, complete the quiz, then inspect the result and answer review.
- Learning loop: inspect history, profile, progress, and the study recommendation; launch and complete its targeted quiz and confirm the learning state updates.
- Exam: start an exam simulation, complete it, and review its result without changing or duplicating ordinary practice history.
- Mobile: at phone width, verify navigation, long questions and options, forms, and buttons remain readable and operable.
- Error experience: use back and refresh after completion, safely retry a stale answer if practical, and check that empty states explain the next action.

Record each observation with: page/action, expected behavior, actual behavior, severity, reproducibility, and a screenshot or notes when useful.

Automated simulation isolates its session files and restores learner records it creates. It uses the production HTTP entry point, repositories, analytics, recommendation services, question generation, quiz completion, and exam paths; it is a readiness gate, not a replacement for human usability testing.
