# Mock Board Readiness Report — Plan (Student Side)

**Status:** PROPOSED — waiting for approval before coding
**Scope:** Student-facing report, shown on the mock board results page
**Related files:**
- `resources/views/pages/student/mock-boards/results.blade.php` (current likelihood card, lines 85–127)
- `app/Http/Controllers/StudentMockBoardController.php` (`results()` ~line 767, `insights()` ~line 646)
- `app/Services/MockBoardStatisticsService.php` (teacher-side likelihood, ~line 742)
- `app/Services/CloudflareAI.php`, `app/Services/AiSettingsResolver.php`

---

## 1. Locked Decisions

| # | Question | Decision |
|---|---|---|
| 1 | When does the report appear? | **Only after the entire mock board is completed** (pre-test + at least one post-test), same rule as the current likelihood card |
| 2 | Peer benchmark (percentile vs batch)? | **Pending** — see Section 6 |
| 3 | AI-generated action plan? | **Yes** — AI with rule-based fallback |

---

## 2. Current State

The student currently sees one card only:

> **Board Passing Likelihood** — High Chance / Moderate Chance / Low Chance + one sentence

### Problem found: inconsistent likelihood logic

| Location | High | Moderate | Low |
|---|---|---|---|
| Student `results.blade.php` | ≥ board `passing_percentage` (default 75) | ≥ threshold − 10 (min 50) | below that |
| Teacher `MockBoardStatisticsService` | ≥ 75 (hardcoded) | ≥ 65 (hardcoded) | below 65 |

If a board's passing % is not 75, the student and the teacher can see **different labels for the same score**.

**Fix (part of this work):** move the tier computation into a single method in `MockBoardStatisticsService` that both sides call, and use the board's `passing_percentage`.

---

## 3. Report Sections

### Section A — Readiness Summary (header)
- **Readiness score:** best post-test %
- **Tier:** Board Ready / Almost Ready / At-Risk (uses the unified logic above)
- **Gap to pass:** in % and in items
  - e.g. *"You need 6.5% more (about 7 more correct items out of 100) to reach the 75% passing mark."*
- **Visual:** gauge or progress bar with a marker at the passing threshold

### Section B — Growth & Progress
- Pre-test % → best post-test %, with points gained
- Trend of every attempt (line chart when there are multiple post-tests)
- Consistency indicator: spread between highest and lowest post-test
  - small spread = "Consistent", large spread = "Inconsistent — results may vary on exam day"

### Section C — Domain / Subject Mastery
Source: `quiz_questions.domain` (FAR, AFAR, AUD, TAX, MAS, RFBT, etc.)

| Domain | Pre-test | Post-test | Change | Status |
|---|---|---|---|---|
| FAR | 45% | 72% | +27 | Developing |
| TAX | 30% | 40% | +10 | Weak |
| AUD | 80% | 85% | +5 | Strong |

- Status bands: **Strong** ≥ 75%, **Developing** 60–74%, **Weak** < 60%
- Visual: radar chart or horizontal bar chart
- Callouts: the domain with the most improvement and the domain with the least improvement
- Fallback: if questions have no `domain`, group by phase or module title

### Section D — Item-Level Insights
- **Repeatedly missed concepts:** questions answered wrong in both pre-test and post-test (only when `is_same_questions` is true for the phases)
- **Regressed items:** correct in pre-test but wrong in post-test, a sign of weak retention
- Shows up to 5 question stems per group, truncated

### Section E — AI Action Plan
- Input to the AI: tier, gap, domain mastery table, top missed concepts
- Output format (same parsing approach as the new quiz insights):
  - **Priority Domains:** top 2–3 weakest domains and why
  - **Specific Topics to Review:** concepts named from the missed items
  - **Study Plan:** 3–5 concrete steps before the real board exam
- Same rules as the updated prompts: specific, no generic advice like "study harder"
- **Fallback:** rule-based plan built from the weakest domains and missed items if the AI fails
- **Caching:** generated once per student per mock board and saved; regenerated only when a newer post-test attempt exists

### Section F — Historical Comparison (optional)
Source: `historical_board_exam_results` (teacher/admin-entered real exam results)
- That table only stores **passing rate** (`passed_count / total_examinees`), not individual scores
- What we can show: *"National passing rate for the October 2024 CPA Licensure Exam was 32%. Your batch's mock board passing rate is 40%."*
- Shown only if the mock board is linked to a historical result

---

## 4. Data Sources (all already in the database)

| Need | Source |
|---|---|
| Pre/post scores | `mock_board_attempts` (`phase_type`, `percentage`, `passed`) |
| Per-question answers | `quiz_attempts` → `quiz_answers` → `quiz_questions` |
| Domain | `quiz_questions.domain` |
| Threshold | `mock_boards.passing_percentage` |
| Same-question check | `mock_board_phases.is_same_questions` |
| Historical | `historical_board_exam_results` via `mock_boards.historical_board_exam_result_id` |
| Batch data (if peer benchmark is approved) | `mock_board_attempts` of the same mock board |

No new data collection is needed. One new storage column or table is needed for the cached AI action plan (see Section 5).

---

## 5. Proposed Implementation

1. **Service:** `MockBoardReadinessService` (new, in `app/Services/`)
   - `buildReport(MockBoard $board, User $student): array` builds Sections A–D and F
   - `generateActionPlan(array $report): array` handles Section E with AI + fallback
2. **Unify tier logic:** one method in `MockBoardStatisticsService` used by the student view, the teacher analytics, and the new report
3. **Storage for the AI action plan:** new migration, either
   - columns on `mock_board_attempts` (e.g. `readiness_action_plan` JSON, `readiness_generated_at`), or
   - a small `mock_board_readiness_reports` table (cleaner, one row per student per board)
4. **Route + controller:** `GET student/mock-boards/{mock_board}/readiness` → `StudentMockBoardController@readiness`
   - Returns 403/redirect if the board is not fully completed
5. **View:** new `readiness.blade.php` page, plus a "View Readiness Report" button on the existing likelihood card
6. **Charts:** use the chart library already used in the results/analytics pages (to confirm before building)
7. **Tests (PHPUnit feature tests):**
   - Report is blocked before completion
   - Tier is identical on student and teacher side for the same score and threshold
   - Domain mastery math is correct
   - Regressed / repeatedly-missed detection
   - AI success path is parsed and saved; AI failure falls back to the rule-based plan
   - Cached plan is reused and regenerated after a newer attempt

---

## 6. Pending Decision — Peer Benchmark

**What it is:** comparing the student's score with the rest of the students who took the **same mock board**, without showing anyone's name.

Example display:
> *"Your score is higher than 70% of the students who took this mock board."*
> *"Batch average: 62% · Your score: 71%"*

**Pros**
- Gives context: 65% means more if the batch average is 50%
- Motivating for students in the upper half

**Cons / risks**
- Can discourage low-ranking students
- In small batches (e.g. 10 students), percentile can make it easy to guess who is who

**Options**
1. **Show percentile + batch average** (full benchmark)
2. **Show batch average only** (no percentile/rank)
3. **Do not show any peer data**
4. Show it only when the batch has at least N students (e.g. 20) to protect anonymity

---

## 7. Proposed Phasing

| Phase | Contents |
|---|---|
| **Phase 1** | Unified tier logic, Sections A, B, C, E (AI action plan + fallback), tests |
| **Phase 2** | Section D (item-level insights), peer benchmark (if approved) |
| **Phase 3** | Section F (historical comparison), downloadable PDF |

---

## 8. Open Questions for Approval
1. Peer benchmark: choose an option from Section 6.
2. AI action plan storage: new table (recommended) or columns on `mock_board_attempts`?
3. Separate readiness page (recommended), or expand it inline on the existing results page?
4. Approve the Phase 1 scope to start coding?
