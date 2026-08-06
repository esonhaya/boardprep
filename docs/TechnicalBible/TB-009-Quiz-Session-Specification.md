# TB-009 — Quiz Session Specification

## Status

Approved

---

# Purpose

This document defines what constitutes a Quiz Session.

Every quiz in BoardPrep must follow this specification regardless of study mode.

---

# Definition

A Quiz Session represents one complete learning activity.

A session begins when questions are generated.

A session ends when results are calculated.

Everything occurring between those points belongs to the Quiz Session.

---

# Session Lifecycle

```
Create Session
        ↓
Generate Questions
        ↓
Present Questions
        ↓
Collect Answers
        ↓
Score Attempt
        ↓
Persist Attempt
        ↓
Generate Recommendations
        ↓
Display Results
```

---

# Session Types

Board

Subject

Domain

Future session types must extend these.

---

# Session Modes

Exam

Practice

Adaptive

Weakness Review

Mastery

Review Incorrect

Modes change behavior.

Modes never change curriculum.

---

# Required Session Information

Every session stores:

- Session ID
- Board
- Subject
- Domain
- Mode
- Blueprint Version (if applicable)
- Difficulty
- Question Count
- Generated Question IDs
- Start Time
- Finish Time
- Duration

---

# Question State

Every generated question has one of:

Not Visited

Current

Answered

Skipped

Flagged

Completed

---

# Answer State

Every answer is one of:

Correct

Incorrect

Unanswered

Skipped

---

# Session Completion

A session is considered complete when:

All required questions have been processed.

Exam Mode:

- Finish immediately.

Practice Mode:

- User may review.

Adaptive Mode:

- May continue depending on mastery rules.

---

# Result Generation

Results include:

- Score
- Percentage
- Accuracy
- Correct
- Incorrect
- Unanswered
- Time Spent

Learning metrics include:

- Weak Topics
- Weak Concepts
- Difficulty Breakdown
- Recommendation Summary

---

# Attempt Persistence

Every completed session creates one Attempt.

Attempts are immutable.

Corrections create new attempts rather than modifying history.

---

# Session Rules

1. Questions never change after generation.

2. Question order remains fixed unless explicitly shuffled during generation.

3. Blueprint information is stored for reproducibility.

4. Scoring always occurs after submission.

5. Learning analytics never modify raw attempt data.

---

# Future Extensions

The session model should support:

- Resume

- Pause

- Multiplayer competitions

- Review Center assignments

- AI tutoring

- Offline synchronization

without redesigning the session lifecycle.

