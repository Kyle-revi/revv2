# Psychometric Item Analysis Module: Specification & Implementation Brief

> **For:** Gemini (coding agent) and the capstone team
> **Project:** Laravel 12 / PHP 8.5
> **Addresses panel comment:** *"Include psychometric item analysis such as the item difficulty index, because there is no explicit algorithm or formula dedicated to calculating whether specific test items are too easy or too difficult."*
> **Related:** `AI_QUALITY_UPGRADE_BRIEF.md` (Task E4), `CLOUDFLARE_AI_GENERATION.md`

---

## 1. The Problem (in plain terms)

Right now the system **labels** each question as Easy, Average or Difficult when the AI generates it. That label is only a **prediction**. Nothing in the system checks it against how students actually performed.

So if a question labeled "Difficult" is answered correctly by 95% of the class, the system never notices. Teachers have no formula-based way to tell which items are too easy, too hard, confusing, or badly written.

**Psychometric item analysis** fixes this. After students take a quiz, standard formulas use their real answers to measure every question.

### What exists today vs. what's missing

| Already in the system | Missing |
|---|---|
| `quiz_answers` stores each student's `selected_option` and `is_correct` per question ✅ | No formal **Item Difficulty Index (p)** with interpretation |
| `PerformanceController` computes `AVG(is_correct) * 100 AS pct_correct` (L79, L169, L424, L504, L581), but only to list "weak topics" | No **Discrimination Index (D)** |
| `quiz_attempts` stores each student's total `score` ✅ | No **distractor analysis** (which wrong choices actually attract students) |
| AI assigns a `difficulty` label at generation time | No comparison of the **AI label vs. actual difficulty** |
| | No **test reliability** (KR-20) |
| | No **Retain / Revise / Reject** decision per item |

> [!NOTE]
> All the raw data needed already exists. No new data collection is required, only computation, storage and display.

---

## 2. The Formulas

### 2.1 Item Difficulty Index (p) ⭐ (the one the panel asked for)

The proportion of students who answered the item correctly.

$$
p = \frac{R}{N}
$$

- $R$ = number of students who answered the item correctly
- $N$ = total number of students who answered the item

*Note:* a **higher** p means an **easier** item. (It's called a "difficulty" index, but it measures how easy the item is.)

**Interpretation table** (a common classification used in Philippine educational assessment textbooks):

| p value | Interpretation | Typical action |
|---|---|---|
| 0.00 – 0.20 | Very Difficult | Revise or reject |
| 0.21 – 0.40 | Difficult | Retain (check wording) |
| 0.41 – 0.60 | Average / Moderately Difficult | Retain (ideal range) |
| 0.61 – 0.80 | Easy | Retain |
| 0.81 – 1.00 | Very Easy | Revise or reject |

### 2.2 Discrimination Index (D)

Measures whether an item separates students who know the material from those who don't. Uses the **upper-lower 27% method** (Kelley, 1939).

1. Sort students by total quiz score (highest to lowest).
2. Take the top 27% (Upper group, U) and the bottom 27% (Lower group, L).

$$
D = p_U - p_L = \frac{R_U}{n_U} - \frac{R_L}{n_L}
$$

- $R_U$, $R_L$ = number of correct answers in the upper and lower groups
- $n_U$, $n_L$ = size of each group, where $n_U = n_L = \max(1, \text{round}(0.27 \times N))$

**Interpretation table** (Ebel & Frisbie, 1991):

| D value | Interpretation | Action |
|---|---|---|
| ≥ 0.40 | Very good item | Retain |
| 0.30 – 0.39 | Reasonably good | Retain (possibly improve) |
| 0.20 – 0.29 | Marginal | Revise |
| 0.00 – 0.19 | Poor | Reject or rewrite |
| < 0.00 (negative) | Lower group did **better** than upper group | Reject (probably a wrong answer key or an ambiguous item) |

> [!IMPORTANT]
> A **negative D** is a strong sign of a **wrong answer key**. This links directly to the AI answer-key verifier in the quality brief.

### 2.3 Point-Biserial Correlation (r_pb), optional and more precise

Correlation between getting the item right (1/0) and the student's total score. Unlike D, it uses all students, not just the top and bottom 27%.

$$
r_{pb} = \frac{\bar{X}_1 - \bar{X}_0}{s_X} \sqrt{p\,(1-p)}
$$

- $\bar{X}_1$ = mean total score of students who got the item **right**
- $\bar{X}_0$ = mean total score of students who got it **wrong**
- $s_X$ = standard deviation of total scores (population SD)
- $p$ = item difficulty index

Use the **corrected** version: compute $X$ as the total score **minus this item** (the "rest score"), so the item isn't correlated with itself. Interpret with the same thresholds as D (≥ 0.30 good, < 0.20 poor).

### 2.4 Distractor Analysis

For each wrong option (distractor):

$$
\text{Selection rate} = \frac{\text{students who chose this distractor}}{N}
$$

- **Functional distractor:** chosen by **≥ 5%** of students, **and** chosen more by the Lower group than the Upper group.
- **Non-functional distractor:** chosen by < 5%. Nobody is fooled, so it's an obvious wrong answer.
- **Problem distractor:** chosen **more by the Upper group** than the Lower group. Possibly a second correct answer, or confusing wording.

$$
\text{Distractor Efficiency (DE)} = \frac{\text{number of functional distractors}}{\text{total distractors}} \times 100\%
$$

For a 4-choice item: DE is 100% when all 3 distractors work, 66.7% for 2, 33.3% for 1, and 0% for none.

> [!TIP]
> This measures the quality of the **AI-generated wrong choices**, a known weak spot of LLM question generators. It's a strong metric to present.

### 2.5 Test Reliability: KR-20 (whole quiz)

Kuder–Richardson Formula 20 measures how consistent the quiz is as a whole.

$$
KR_{20} = \frac{k}{k-1}\left(1 - \frac{\sum_{i=1}^{k} p_i q_i}{\sigma_X^2}\right)
$$

- $k$ = number of items
- $p_i$ = difficulty index of item $i$, and $q_i = 1 - p_i$
- $\sigma_X^2$ = variance of total scores

| KR-20 | Interpretation |
|---|---|
| ≥ 0.90 | Excellent |
| 0.80 – 0.89 | Good |
| 0.70 – 0.79 | Acceptable |
| 0.60 – 0.69 | Questionable (fine for short classroom quizzes) |
| < 0.60 | Poor |

### 2.6 AI Difficulty Label Accuracy (connects item analysis to the AI)

This is what makes the module more than a generic stats page. Map each item's **empirical p** to an **empirical difficulty band**:

| Empirical p | Empirical band |
|---|---|
| ≤ 0.40 | Difficult |
| 0.41 – 0.70 | Average |
| ≥ 0.71 | Easy |

Then compare it with the AI-assigned `difficulty`:

$$
\text{Label Accuracy} = \frac{\text{items where AI label} = \text{empirical band}}{\text{total analyzed items}} \times 100\%
$$

Also report the **mean p for each AI label**. If the AI labels are meaningful, the order should be:

$$
\bar{p}_{\text{Easy}} > \bar{p}_{\text{Average}} > \bar{p}_{\text{Difficult}}
$$

> [!NOTE]
> The band cutoffs (0.40 / 0.70) are configurable. Document whichever values you choose and cite your reference in the manuscript.

### 2.7 Item Decision Matrix (final verdict per item)

Combine p and D into one verdict:

| | **D ≥ 0.30** | **0.20 ≤ D < 0.30** | **D < 0.20** |
|---|---|---|---|
| **0.21 ≤ p ≤ 0.80** | ✅ **Retain** | ✏️ **Revise** | ❌ **Reject** |
| **p < 0.21 or p > 0.80** | ✏️ **Revise** | ✏️ **Revise** | ❌ **Reject** |

Override rules:
- **D < 0** → ❌ **Reject** and show the alert *"Possible wrong answer key."*
- **Any problem distractor** → ✏️ at least **Revise**, with the alert *"Option X attracts high-scoring students; check for a second correct answer."*
- **N < minimum sample** (see §3.3) → ⚪ **Insufficient data** (show the numbers, but no verdict)

---

## 3. Data Rules (important for correct numbers)

### 3.1 Which attempts to include
- Only attempts with `status = 'completed'`.
- **One attempt per student per quiz:** use each student's **first completed attempt**. Retakes are inflated by practice and memory of the questions, which would make items look easier than they are. Make this configurable (`first` | `best` | `latest`), defaulting to `first`.
- Analyze `pre_test` and `post_test` **separately** (filter by `quiz_stage`), since they are different question sets.
- Exclude teacher/admin accounts (`role != 'student'`).

### 3.2 Unanswered items
If a student's attempt has no `quiz_answers` row for a question, count it as **incorrect** (it was presented but not answered correctly). It counts toward N, with `selected_option = null` reported as "Omitted" in the distractor table.

### 3.3 Minimum sample size
Item statistics are unstable with very few students.
- `N < 10` → show raw counts only, verdict = **Insufficient data**
- `10 ≤ N < 30` → show all indices with a **"low confidence"** badge
- `N ≥ 30` → full confidence

Make these thresholds configurable.

### 3.4 ⚠️ Existing data-loss risk
`generateQuizAi` (ClassManagerController ~L2762) **deletes** all `QuizQuestion` rows for the module and stage when regenerating. Because `quiz_answers.question_id` uses `onDelete('cascade')`, **all answer history for those questions is also deleted**, and so is their item analysis.

**Fix (required):**
- Before regenerating, **save the item analysis results** for the old questions into `item_analysis_results` (§4.1). That table must **not** cascade-delete; store a `question_text` copy.
- Better: soft-delete or archive old questions instead of hard-deleting them (add `archived_at` to `quiz_questions` and filter it out of active quizzes).

---

## 4. Implementation

### 4.1 Database

**Migration: `item_analysis_results`** (one row per question per analysis run)
```php
Schema::create('item_analysis_results', function (Blueprint $table) {
    $table->id();
    $table->foreignId('module_id')->constrained()->cascadeOnDelete();
    $table->unsignedBigInteger('question_id')->index();   // NO FK cascade: survives question deletion
    $table->string('quiz_stage')->nullable();
    $table->text('question_text');                         // snapshot
    $table->string('correct_option', 2);
    $table->string('ai_difficulty_label')->nullable();     // Easy / Average / Difficult
    $table->unsignedInteger('n_examinees');
    $table->unsignedInteger('n_correct');
    $table->decimal('p_index', 5, 4)->nullable();          // difficulty index
    $table->decimal('d_index', 5, 4)->nullable();          // discrimination index
    $table->decimal('point_biserial', 5, 4)->nullable();
    $table->json('option_stats');                          // per option: total, upper, lower, rate
    $table->decimal('distractor_efficiency', 5, 2)->nullable();
    $table->string('difficulty_interpretation')->nullable();     // "Very Easy" ... "Very Difficult"
    $table->string('discrimination_interpretation')->nullable(); // "Very good" ... "Poor"
    $table->string('empirical_band')->nullable();          // Easy / Average / Difficult
    $table->boolean('label_matches')->nullable();
    $table->string('verdict');                             // retain / revise / reject / insufficient
    $table->json('flags')->nullable();                     // ["negative_d","problem_distractor:B", ...]
    $table->string('confidence');                          // full / low / insufficient
    $table->timestamp('analyzed_at');
    $table->timestamps();
    $table->index(['module_id', 'quiz_stage', 'analyzed_at']);
});
```

**Migration: `test_analysis_results`** (one row per quiz per analysis run)
`module_id`, `quiz_stage`, `n_examinees`, `n_items`, `mean_score`, `sd_score`, `kr20`, `kr20_interpretation`, `mean_p`, `mean_d`, `label_accuracy`, `mean_p_by_label` (json), `verdict_counts` (json), `analyzed_at`, timestamps.

### 4.2 Config: `config/item_analysis.php`
```php
return [
    'group_fraction'        => 0.27,
    'attempt_policy'        => env('ITEM_ANALYSIS_ATTEMPT_POLICY', 'first'), // first|best|latest
    'min_n_insufficient'    => 10,
    'min_n_full_confidence' => 30,
    'functional_distractor_min_rate' => 0.05,
    'p_bands' => [ // [min, max, label, action]
        [0.00, 0.20, 'Very Difficult', 'revise'],
        [0.21, 0.40, 'Difficult',      'retain'],
        [0.41, 0.60, 'Average',        'retain'],
        [0.61, 0.80, 'Easy',           'retain'],
        [0.81, 1.00, 'Very Easy',      'revise'],
    ],
    'd_bands' => [
        [0.40,  1.00, 'Very good'],
        [0.30,  0.39, 'Reasonably good'],
        [0.20,  0.29, 'Marginal'],
        [0.00,  0.19, 'Poor'],
        [-1.00, -0.01, 'Negative'],
    ],
    'empirical_bands' => ['difficult_max' => 0.40, 'easy_min' => 0.71],
];
```

### 4.3 Service: `app/Services/ItemAnalysis/ItemAnalysisService.php`

Public API:
```php
public function analyzeQuiz(int $moduleId, ?string $quizStage = null, bool $persist = true): array;
// returns ['test' => [...test-level stats], 'items' => [...per-item stats]]
```

**Algorithm:**
```
1. attempts ← completed quiz_attempts for module + stage, students only,
              reduced to ONE per user via attempt_policy
2. questions ← active quiz_questions for module + stage (id, text, correct_option, options, difficulty)
3. answers  ← quiz_answers WHERE attempt_id IN attempts  (one query, keyed [attempt_id][question_id])
4. Build response matrix X[student][item] ∈ {0,1} and choice matrix C[student][item] ∈ {A..J, null}
   (missing answer → 0 / null)
5. total[s] ← Σ X[s][*]   (recompute from the matrix; don't trust the stored score, in case the question set changed)
6. Sort students by total desc (tie-break by student id for determinism)
   g ← max(1, round(0.27 × N)); Upper ← first g; Lower ← last g
7. For each item i:
     p    ← Σ X[*][i] / N
     pU   ← Σ X[Upper][i] / g ;  pL ← Σ X[Lower][i] / g ;  D ← pU − pL
     rpb  ← point-biserial using the rest score (total − X[s][i])
     option_stats[opt] ← {total, upper, lower, rate}   for every option + "omitted"
     distractors ← options ≠ correct
     functional  ← rate ≥ 0.05 AND lower ≥ upper
     problem     ← upper > lower AND rate ≥ 0.05
     DE ← functional / count(distractors) × 100
     interpretations, empirical band, label match, verdict (§2.7), flags, confidence (§3.3)
8. Test level: mean, SD, KR-20 = k/(k−1) × (1 − Σ p_i q_i / σ²), mean p, mean D,
   label accuracy, mean p per AI label, verdict counts
9. If persist: insert into item_analysis_results + test_analysis_results in one DB transaction
```

**Edge cases:**
- `N = 0` → return empty results with the message "No completed attempts yet"
- `σ² = 0` (everyone got the same score) → KR-20 = null, labeled "Not computable (no score variance)"
- `p = 0` or `p = 1` → point-biserial = null (no variance in the item)
- Items with `k < 2` → KR-20 = null

> [!IMPORTANT]
> Keep the service **pure and deterministic**: no AI calls, no randomness. Panels ask whether the AI decides item quality, and the answer must be **no, it's computed with standard psychometric formulas.**

### 4.4 When to run it
- **On demand:** a teacher clicks "Run Item Analysis" on the quiz results page.
- **Automatically:** a queued job after each completed attempt, debounced (e.g. at most once every 10 minutes per quiz), or nightly via the scheduler: `php artisan item-analysis:run --module= --stage=`.
- **Before regeneration:** automatically, before `generateQuizAi` replaces questions (§3.4).

### 4.5 Teacher UI Specification & Wireframes (Quiz Results Page)

The Item Analysis UI lives under **Teacher Dashboard → Class → Quiz/Module → "Item Analysis" Tab** (alongside existing student attempts). Students cannot view this tab.

```
┌─────────────────────────────────────────────────────────────────────────────────────────────┐
│ CLASS: NURSING 101  >  MODULE 3: PHARMACOLOGY QUIZ                                          │
│ [Overview]  [Student Attempts]  [★ Item Analysis]  [Settings]                               │
└─────────────────────────────────────────────────────────────────────────────────────────────┘
```

---

#### Screen 1: Executive KPI Cards (Top of Page)

```
┌───────────────────┬───────────────────┬───────────────────┬───────────────────┐
│   TOTAL STUDENTS  │    CLASS AVERAGE  │  TEST RELIABILITY │ AI LABEL ACCURACY │
│        40         │       68.5%       │    KR-20: 0.78    │        72%        │
│   (First Attempt) │    (σ = 12.4)     │   Acceptable ✅    │ (Matches Actual)  │
├───────────────────┴───────────────────┴───────────────────┴───────────────────┤
│ SUMMARY VERDICTS:                                                             │
│   ✅ Retain (Keep): 18 items    ✏️ Revise: 8 items    ❌ Reject: 4 items      │
└───────────────────────────────────────────────────────────────────────────────┘
```

---

#### Screen 2: Interactive Item Analysis Table (Sortable & Filterable)

Filters available: `All Items` | `Needs Review (Reject/Revise)` | `Flagged Keys ⚠️`

```
┌────┬─────────────────────────────┬───────────┬──────────────┬───────────────┬──────┬────────────┬────────────────────────────┐
│ #  │ Question Stem (Excerpt)     │ AI Label  │ Diff (p)     │ Discrim (D)   │ DE   │ Verdict    │ Action & Alerts            │
├────┼─────────────────────────────┼───────────┼──────────────┼───────────────┼──────┼────────────┼────────────────────────────┤
│ 1  │ What is the first-line...   │ Easy      │ 0.92 [V.Easy]│ 0.10 [Poor]   │ 33%  │ ❌ Reject  │ Too easy; rewrite choices  │
│ 2  │ Why is epinephrine used...  │ Difficult │ 0.38 [Diff]  │ 0.45 [V.Good] │ 100% │ ✅ Retain  │ Ideal discriminator        │
│ 3  │ How should dosage be adj... │ Average   │ 0.55 [Avg]   │ -0.12 [Neg]   │ 67%  │ ❌ Reject  │ ⚠️ Check answer key!       │
│ 4  │ What adverse reaction is... │ Difficult │ 0.85 [V.Easy]│ 0.25 [Marg]   │ 33%  │ ✏️ Revise  │ AI labeled Diff, actual Easy│
└────┴─────────────────────────────┴───────────┴──────────────┴───────────────┴──────┴────────────┴────────────────────────────┘
```

---

#### Screen 3: Question Diagnostic Drawer (Opens upon clicking any row)

When clicking **Question #3**:

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│ QUESTION DIAGNOSTIC DETAILS (ITEM #3)                                                  │
│ "How should dosage be adjusted when creatinine clearance drops below 30 mL/min?"       │
│ Registered Answer Key: [ C ]                                                           │
├────────────────────────────────────────────────────────────────────────────────────────┤
│ OPTION BREAKDOWN & DISTRACTOR TRAFFIC:                                                 │
│                                                                                        │
│ Choice  Option Text                     Upper 27% (11)    Lower 27% (11)    Total (40) │
│ ────────────────────────────────────────────────────────────────────────────────────── │
│   A     Increase dosage frequency           2 [18%]           3 [27%]         6 [15%]  │
│   B     Maintain current dose               8 [72%] ⚠️        1 [ 9%]        11 [28%]  │
│ ★ C     Reduce dose by 50% (Key)            1 [ 9%]           7 [64%]        15 [38%]  │
│   D     Administer with saline bolus        0 [ 0%]           0 [ 0%] 💤      0 [ 0%]  │
│                                                                                        │
│ DIAGNOSTIC FINDINGS:                                                                   │
│ 🚨 ALERT: Discrimination is NEGATIVE (D = -0.12). 72% of top-performing students chose│
│    Option B, while only 9% chose marked key C.                                         │
│    → Potential flaw: Answer key error or ambiguous medical guideline.                 │
│ 💤 WEAK DISTRACTOR: Option D was chosen by 0% of examinees (Distractor Efficiency: 67%)│
│    → Distractor D is non-functional and should be replaced with a realistic distractor│
├────────────────────────────────────────────────────────────────────────────────────────┤
│ ACTIONS:                                                                               │
│ [ ✏️ Edit Key / Choices Manually ]  [ 🤖 Regenerate Distractors via AI ]  [ Mark Reviewed ]│
└────────────────────────────────────────────────────────────────────────────────────────┘
```

---

#### Screen 4: AI Difficulty Label Validation Chart

Demonstrates empirical validation of the generative model's difficulty calibration for panelists:

```
EMPIRICAL DIFFICULTY INDEX (Mean p) PER AI-ASSIGNED LABEL
(Higher p = Easier Exam Item)

AI Label: Easy      ████████████████████████████ 0.78  (Actual: Mostly Easy)
AI Label: Average   ██████████████████ 0.58            (Actual: Moderately Difficult)
AI Label: Difficult ████████████ 0.39                  (Actual: Difficult)

→ Monotonic Order Verified: p(Easy) > p(Average) > p(Difficult)
→ Proof that AI taxonomical budgeting rules correlate with real cognitive difficulty.
```

---

#### 4.5.1 Live Defense Demonstration Script
1. Pre-seed 30 student attempts on a quiz using automated seeders or historical class data.
2. Navigate to **Quiz → Item Analysis Tab**.
3. Highlight **Screen 1**: Point out the **KR-20 test reliability** and **AI Label Accuracy**.
4. Highlight **Screen 2**: Show how the system surfaces one **ideal question** (Item #2, ✅ Retain) versus one **problematic question** (Item #3, negative discrimination).
5. Open **Screen 3 Drawer**: Walk the panelists through the Upper vs. Lower group distribution, proving that the system mathematically uncovers wrong answer keys without manual inspection.
6. Conclude with **Screen 4**: Show the mean $p$ bar chart proving that AI-generated "Difficult" items truly test higher-order cognitive ability.


### 4.6 Closing the loop with the AI generator (strong capstone point)
- When an item is **Revised** or **Regenerated**, pass the analysis reason into the regeneration prompt:
  - p > 0.80 → "Make this question more challenging; it was too easy (p = 0.85)."
  - DE < 67% → "Rewrite the wrong options to be more plausible; options B and D were chosen by fewer than 5% of students."
  - D < 0 → don't regenerate automatically; ask the teacher to verify the answer key first.
- Over time, report **AI label accuracy per model and per question type** (what/why/how) using `test_analysis_results`. This becomes evidence for tuning the difficulty budgeting rules.

### 4.7 Tests (Pest)
Use a hand-computed fixture so every number can be checked by hand:
- 10 students × 5 items with a known response matrix → assert exact p, D, r_pb, DE and KR-20 values.
- An item that everyone gets right → p = 1.00, D = 0, r_pb = null, verdict Reject or Revise.
- An item where the lower group outperforms the upper group → D < 0, flag `negative_d`, verdict Reject.
- A distractor chosen mostly by the upper group → flag `problem_distractor:X`.
- N = 5 → confidence `insufficient`.
- Retakes: only the first attempt counts under policy `first`.
- Regeneration doesn't delete `item_analysis_results` rows.

---

## 5. Worked Example (for the manuscript and defense)

A quiz taken by **N = 40** students. Upper and lower groups: g = round(0.27 × 40) = **11** students each.

**Item 7** (AI label: *Average*, correct answer **C**)

| Option | Upper (11) | Lower (11) | Total (40) |
|---|---|---|---|
| A | 1 | 3 | 6 |
| B | 0 | 1 | 1 |
| **C ✔** | **9** | **4** | **26** |
| D | 1 | 3 | 7 |

- **p** = 26 / 40 = **0.65** → *Easy*
- **D** = 9/11 − 4/11 = 0.818 − 0.364 = **0.45** → *Very good*
- **Distractors:** A (6/40 = 15%, lower > upper) ✅ functional; B (1/40 = 2.5%) ❌ non-functional; D (7/40 = 17.5%, lower > upper) ✅ functional
- **DE** = 2/3 = **66.7%**
- **Empirical band** = Average (0.41–0.70) → **matches** the AI label ✅
- **Verdict:** ✅ **Retain** (p within 0.21–0.80, D ≥ 0.30). Suggestion: *"Option B is rarely chosen; consider a more plausible distractor."*

---

## 6. Defense Q&A Additions

**Q: "How do you know if a generated question is too easy or too difficult?"**
> "Through psychometric item analysis. After students take the quiz, the system computes the **Item Difficulty Index**, p = R/N, the proportion of students who answered correctly. Items with p above 0.80 are flagged as very easy and items with p below 0.21 as very difficult, following standard interpretation tables. We don't rely only on the AI's label. We validate it against actual student performance."

**Q: "How do you know a question is a *good* question, not just easy or hard?"**
> "We use the **Discrimination Index**, D = p(upper 27%) − p(lower 27%). A good item is answered correctly more often by high-scoring students than by low-scoring ones. D ≥ 0.30 is retained, 0.20–0.29 is revised, and below 0.20 is rejected. A negative D alerts the teacher to a possible wrong answer key."

**Q: "Is the AI deciding which items are good?"**
> "No. Item analysis is 100% deterministic and formula-based: difficulty index, discrimination index, point-biserial correlation, distractor efficiency and KR-20 reliability. The AI only generates questions. Their quality is then measured with classical test theory."

**Q: "Are the AI's Easy/Average/Difficult labels accurate?"**
> "We measure that directly. We compare each item's AI label with its empirical difficulty band from p and report the label accuracy percentage and the mean p per label. [Insert your results, e.g. mean p: Easy 0.78, Average 0.58, Difficult 0.39.]"

**Q: "Why the 27%?"**
> "Kelley (1939) showed that the upper and lower 27% give the best balance between having large enough groups and maximizing the difference between them. It's the standard in classical item analysis."

---

## 7. References (verify editions before citing)
- Kelley, T. L. (1939). The selection of upper and lower groups for the validation of test items. *Journal of Educational Psychology, 30*(1), 17–24.
- Ebel, R. L., & Frisbie, D. A. (1991). *Essentials of Educational Measurement* (5th ed.). Prentice Hall.
- Kuder, G. F., & Richardson, M. W. (1937). The theory of the estimation of test reliability. *Psychometrika, 2*(3), 151–160.
- Crocker, L., & Algina, J. (2008). *Introduction to Classical and Modern Test Theory*. Cengage.
- Philippine assessment references commonly used in teacher education (e.g. *Assessment of Learning 1* textbooks) for the p and D interpretation tables. Cite the specific one your adviser prefers.

---

## 8. Definition of Done
- [ ] Migrations `item_analysis_results` and `test_analysis_results` (no cascade from `quiz_questions`)
- [ ] `config/item_analysis.php`
- [ ] `ItemAnalysisService` computing p, D, r_pb, option stats, DE, KR-20, label accuracy, verdicts, flags and confidence
- [ ] Data rules: completed only, one attempt per student (policy), students only, stage-separated, omitted = incorrect
- [ ] Analysis saved automatically before AI regeneration deletes questions (or questions are archived instead)
- [ ] Artisan command `item-analysis:run` plus an on-demand button
- [ ] Teacher UI: summary cards, item table, detail drawer, label-accuracy chart
- [ ] Regeneration prompt receives the item-analysis reason
- [ ] Pest tests with a hand-computed fixture pass
- [ ] Manuscript / `CLOUDFLARE_AI_GENERATION.md` updated with the formulas, worked example and Q&A
