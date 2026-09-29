# AI Quiz Generation — Reliability & Latency Issue Brief

**Audience:** an AI coding agent with read/write access to this repository (Reviso).
**Purpose:** give enough grounded context to instrument, diagnose, and fix the issue below without re-deriving it from scratch.
**Primary file:** `app/Http/Controllers/ClassManagerController.php`, method `generateQuizAi()`.
**Supporting method:** `deduplicateQuestionBatch()` (same file).
**Related service:** `app/Services/CloudflareAI.php` (not yet reviewed — see Open Questions).
**Status:** Instrumentation from §5 Step A is deployed and has produced real production logs (see §4a). The diagnosis below is now data-backed, not speculative — read §4a before making any threshold changes.

---

## 1. Symptom (reported by the product owner, in their words)

> "antagal nya masyado mag generate minsan 2-5 mins minsan ayaw na generate loading nalang" —
> generation is very slow, sometimes 2–5 minutes, and sometimes it just hangs on loading and never completes.

> Separately, requesting N questions (e.g. 60) sometimes yields the full N and sometimes yields a small fraction (e.g. 10) of the same request, with no code change in between — pure run-to-run variance.

Two distinct but related problems:

- **A. Yield variance** — same request, wildly different output counts across runs.
- **B. Latency / hangs** — multi-minute generation, sometimes never resolving on the frontend.

---

## 2. How `generateQuizAi()` works (context an agent needs before touching it)

Flow, in order, all inside one synchronous HTTP request/response cycle:

1. Validates uploaded context files + a `file_difficulty_counts` matrix (Easy/Average/Difficult × per-file counts, max 100 total).
2. For each uploaded file: extracts text (PDF via `smalot/pdfparser`, DOCX via raw zip/XML parsing, TXT raw), cleans it (`cleanExtractedPdfText`), truncates to ~25,000 chars at a sentence boundary.
3. Slices each file's text into sections (`sliceDocumentIntoSections`) so different generation tasks read different parts of the source.
4. Builds a **task queue**: for each (difficulty × question-type[what/why/how]) combination, splits the requested count into micro-batches of ≤5, each targeting a specific document section, rotating through sections as more tasks are processed.
5. **Sequentially**, for every task: builds a prompt (with an "avoid these existing questions" block drawn from up to the last 35 generated stems), calls `CloudflareAI::run()` **synchronously**, parses the JSON response, filters each candidate through `aiQuestionRejectionReason()` (structure / empty fields / duplicate options / **groundedness check** against source text / **stem-echo** similarity check), shuffles options, and appends survivors to `$allGeneratedQuestions`.
6. Runs `deduplicateQuestionBatch()` on the full accumulated set — six similarity-based conditions (see §4) can flag a legitimate, distinct question as a duplicate of an earlier one.
7. If the total is still short of the requested count, enters a **top-up loop**: up to `max(20, ceil($missingInitial/2)+12)` more **sequential** AI calls, each asking for a small batch (≤3 + buffer), against a rotating document section, re-running the same rejection + dedup logic after each call.
8. Caps to the exact requested count, persists via `QuizQuestion::create()` in a transaction, returns JSON.

**Every one of those AI calls is a blocking HTTP request, run one after another, inside a single PHP request.** This is the root architectural fact behind both symptoms.

---

## 3. Root cause of Symptom B (latency / hangs)

- For a 60-question request: realistically 12+ task calls in the main pass, plus up to ~37 more in the top-up loop — **all sequential**. At 3–8s per call, 2–5 minutes end-to-end is expected, not anomalous.
- `set_time_limit(max(120, $totalQuestionsToGenerate * 10))` caps **PHP's own execution time**, not the outbound HTTP call to Cloudflare. Nothing in the code currently sets a timeout on the `CloudflareAI::run()` call itself.
- If a single Cloudflare call stalls, PHP blocks indefinitely on it (bounded only by whatever timeout, if any, lives inside `CloudflareAI.php` — **unverified, needs review**, see §6).
- Depending on infra config (nginx/php-fpm timeouts vs. PHP's own), the request either: (a) eventually dies via `set_time_limit` with a 500 the frontend may not surface cleanly, or (b) the web server kills the connection first, leaving the browser spinner with no error to reset UI state — this is almost certainly what "loading nalang" is: a synchronous request that silently died server-side.

**This will not be fixed by threshold tuning.** It requires either an explicit timeout+retry at the HTTP client level, request parallelization, or moving generation off the request/response cycle entirely (see §7).

**Confirmed by production logs (see §4a's source run):** top-up iteration timestamps landed roughly every 6–9 seconds apart (`18:16:25 → 18:16:42 → 18:16:46 → 18:16:54 → 18:17:06 → 18:17:12 → 18:17:25`). At ~7s/call, a run needing the full ~37-iteration top-up budget lands at **4+ minutes from sequential HTTP round trips alone** — this is the architecture behaving exactly as built, not an anomaly or infra flake.

---

## 4. Root cause of Symptom A (yield variance) — ORIGINAL HYPOTHESIS (superseded, kept for context)

Four compounding, independently-stochastic stages, all driven by one LLM's non-deterministic output per run:

1. **Thin initial buffer** — each task asks for `$typeCount + 1` candidates only. No task-level retry on shortfall; any shortfall is punted entirely to the global top-up phase.
2. **Rejection filter strictness varies by phrasing, not by underlying quality** — `isGroundedInSource()` requires ≥50% of significant words in the model's own `evidence` field to literally appear in the source; `getStemEchoThreshold()` rejects stems that are 85–92% textually similar to one of their own options.
3. ~~**Dedup Condition 5 is the prime suspect for over-flagging legitimate questions**~~ — **this was wrong. See §4a: Condition 5 fired zero times across the first real production log. Do not prioritize it.**
4. **Top-up loop budget isn't shortfall-proportional in practice** — `max(20, ceil($missingInitial/2)+12)` iterations, ≤3 questions requested per iteration. Confirmed directionally correct by §4a (see latency evidence), though the dominant loss mechanism turned out to be different from what was guessed here.

**This section is left in place so the reasoning trail is visible, not because it should be acted on. Use §4a below for the actual fix priorities.**

---

## 4a. Root cause of Symptom A — CONFIRMED from production logs (2026-08-31, run ~18:16–18:17 UTC)

A real generation run (2 source PDFs, mixed difficulty/type tasks) was captured with the Step A instrumentation live. This is what it actually showed, and it changes the priority order from §4.

**Dedup condition breakdown — 23 questions removed, by condition:**

| Condition | What it checks | Times fired | Share |
|---|---|---|---|
| **4** (`$sharedOptions >= 3`) | ≥3 near-identical distractor options between two questions, **no stem/answer corroboration required** | **16** | **70%** |
| 3 (matching correct answer + some overlap) | 4 | 17% |
| 1 (near-identical stem, ≥88% similarity) | 2 | 9% |
| 2 (highly similar stem ≥75% + answer match) | 1 | 4% |
| **5 (the condition originally flagged as the prime suspect in §4)** | ansSimilarity ≥60% + (stemSim ≥40% OR jaccard ≥30%) | **0** | **0%** |

**Condition 4 is the real problem, and inspecting the actual removed pairs confirms it's a false-positive generator.** Example from the log:

> Dropped: *"How do AI-based grading systems impact educators' workload?"*
> Kept: *"How do students prefer to receive feedback in AI-based assessments?"*
> Stem similarity: 34.71% · Jaccard: 16.67% · Answer similarity: 24.19% · **Condition fired: 4**

Nothing about that pair reads as a duplicate by topic, stem, or answer — they were flagged purely because ≥3 of their multiple-choice **distractor options** happened to be near-identical strings. This happens naturally on any single-topic source document: a set of documents about "AI in education" produces a small shared vocabulary of plausible wrong answers ("written feedback," "reduces workload," "peer review," etc.) across many genuinely distinct questions, and Condition 4 has no requirement that the *questions themselves* also be similar before treating shared distractors as proof of duplication.

**But dedup is not even the largest loss source in this run.** Summing rejection reasons across every logged task + top-up iteration in the same run:

| Rejection reason | Total count | Where |
|---|---|---|
| **`stem_echo`** | **34** | Concentrated almost entirely in **why/how** tasks |
| `ungrounded` | 20 | Spread across both files |
| `duplicate_options` (per-candidate validator, distinct from dedup Condition 4) | 12 | Mostly top-up iterations |
| dedup Condition 4 | 16 | See above |
| dedup Condition 3 | 4 | — |
| dedup Conditions 1+2 | 3 | — |

`stem_echo` alone outweighs all dedup losses combined, and its concentration in why/how tasks is a structural pattern, not noise: `getStemEchoThreshold()` already applies a *stricter* bar to why/how (92% vs. 85% for "what"), yet they still dominate. This makes sense once you consider the content shape — a "why" answer like *"To enforce reference locking"* will naturally echo a "why" question like *"Why does the Core System enforce reference locking?"* far more than a "what" fact-recall pair would. Reasoning-type Q&A pairs have inherently higher legitimate lexical overlap between stem and answer than fact-recall pairs do; the current check doesn't distinguish "genuinely just restating the question" from "the honest phrasing of a why/how answer necessarily reuses the question's key terms."

**Revised priority for Symptom A, replacing §4:**

1. **Fix dedup Condition 4 first** — require a corroborating topical signal, not option-overlap alone (see §5 Step B, revised).
2. **Investigate/fix `stem_echo` for why/how types** — largest single loss source; needs either a type-specific threshold revision or a prompt-level fix so why/how answers aren't structurally prone to echoing the stem.
3. **Deprioritize Condition 5** — it did not fire in this run. Do not spend effort tightening it until logs show it's actually contributing.
4. Continue monitoring `ungrounded` (20 hits) — currently third-largest loss, worth a second data point before deciding whether to touch `isGroundedInSource()`'s 50% word-overlap bar.

**Caveat:** this is one run, on two specific source documents. The instrumentation is working — keep collecting across more runs (varied documents, difficulties, request sizes) before hard-coding new thresholds. The pattern (Condition 4 dominant, stem_echo dominant on why/how) is strong enough to act on now, but should be re-confirmed after the Condition 4 fix ships, in case it was partly masking the next-largest contributor.

---

## 5. Action plan, in priority order

### Step A — Instrumentation — ✅ DONE, confirmed working
Per-task rejection logging, dedup condition tracking, and run summary logging are live in production and have already produced the data analyzed in §4a. No further work needed here beyond continuing to let it run and collecting more samples across varied documents/request sizes to confirm §4a's pattern holds.

### Step B — Fix dedup Condition 4 (confirmed by §4a as the dominant dedup loss — 70% of removals, and inspection shows clear false positives)
Condition 4 currently fires on distractor-option overlap alone, with no requirement that the questions themselves be topically related:
```php
// current
elseif ($sharedOptions >= 3) {
    $isDuplicate = true;
    $firedCondition = 4;
}
// proposed — require option overlap AND some corroborating topical signal
elseif ($sharedOptions >= 3 && ($maxStemSim >= 25.0 || $jaccard >= 15.0)) {
    $isDuplicate = true;
    $firedCondition = 4;
}
```
The corroboration thresholds (25% stem sim / 15% jaccard) are deliberately low — the goal isn't to make Condition 4 strict, just to stop it from firing on option-vocabulary overlap alone when the questions are otherwise unrelated (as in the logged false positive: two "how" questions about AI grading systems, 34.71% stem sim, 16.67% jaccard, flagged as duplicates purely on shared distractors). Re-run and check the condition-4 share of removals drops meaningfully without silently reintroducing true duplicates — spot-check a sample of what's no longer flagged.

**Condition 5 does not need fixing.** It fired zero times in the observed run (see §4a) — the earlier hypothesis prioritizing it was wrong. Leave it as-is unless future log data shows otherwise.

### Step B2 — Address `stem_echo` for why/how question types (confirmed by §4a as the single largest rejection cause, 34 hits, ahead of all dedup losses combined)
Two independent angles, not mutually exclusive:
1. **Threshold angle:** why/how already gets a stricter 92% bar (`getStemEchoThreshold()`) vs. 85% for "what," yet still dominates — the threshold alone isn't fixing this. Consider whether why/how needs a *different kind* of check rather than just a higher number, since some stem/answer lexical overlap is structurally unavoidable for reasoning-type Q&A (a "why" answer restating the mechanism named in the question is often the *correct*, non-redundant answer, not an echo).
2. **Prompt angle:** revise the why/how generation prompt (see `$typeInstructions` in `generateQuizAi()`) to explicitly instruct the model to phrase the answer using different terminology than the question stem where possible, rather than relying entirely on post-hoc filtering to catch it.
Instrument before and after any change here specifically for why/how `stem_echo` counts to confirm improvement — this is the highest-leverage single fix identified so far.

### Step C — Shortfall-proportional top-up budget (directionally supported by §4a's latency evidence — see the ~7s/iteration spacing in the logs)
```php
// current
$maxMicroBatches = max(20, (int) ceil($missingInitial / 2) + 12);
// proposed
$maxMicroBatches = max(30, $missingInitial * 3);
$batchTarget = min($missingCount, $missingCount > 20 ? 6 : 3);
```

### Step D — Widen the initial per-task buffer
```php
// current
$bufferedCount = $typeCount + 1;
// proposed
$bufferedCount = (int) ceil($typeCount * 1.4) + 1;
```

### Step E — Task-level retry before falling through to global top-up
Currently a task's shortfall is punted straight to the generic top-up loop, which uses a weaker, less-targeted prompt (no difficulty/type framing) against a different section. Consider retrying the *same* task (same section, same type/difficulty target) once before giving up on it, likely recovering more on-target questions with less topic drift than global top-up alone.

### Step F — Sanity-check the ask against available material
If a teacher requests, say, 60 questions from a 2-page document, no amount of retrying will produce 60 distinct, grounded questions — the filters are (correctly) throttling an unreasonable ask. Consider a warning or soft cap, e.g. flag if `$requestedQuestionCount > count($sections) * 3`, so this scenario is surfaced to the teacher rather than silently producing a partial result that looks like a bug.

### Step G — Fix the latency/hang problem (independent of A–F, should happen in parallel)
Three options, increasing effort and increasing how completely they fix the UX symptom:

1. **Minimum viable fix:** add an explicit timeout + bounded retry to every Cloudflare call inside `CloudflareAI::run()` (e.g. a client-level `timeout(20)` + `retry(2, 300)` if using Laravel's `Http` facade) so a stalled call fails fast with a surfaced error instead of hanging indefinitely. **Needs `app/Services/CloudflareAI.php` reviewed first** — not yet examined in this brief.
2. **Real speed win:** parallelize the *initial* task batch using `Http::pool()` (or equivalent), since each task's prompt is independent of the others until dedup runs. The top-up loop can't be parallelized the same way since it depends on running totals, but the main pass (often the majority of calls) can be.
3. **Correct long-term fix for "loading forever":** move generation into a queued job (`php artisan queue:work`) with a job status/progress the frontend polls (e.g. `12/60 generated`) instead of holding one HTTP request open for minutes. No synchronous request should reasonably run 2–5 minutes regardless of how fast individual AI calls become — this is the fix that eliminates the symptom category outright rather than shrinking it.

---

## 6. Open questions / things an agent should verify before making changes

- **`app/Services/CloudflareAI.php` has not been reviewed in this brief.** Before implementing Step G.1, read it to find: what HTTP client it uses, whether any timeout is currently set, whether it has its own retry logic, and whether it supports batched/pooled requests.
- Confirm actual production infra timeouts (nginx `proxy_read_timeout`, php-fpm `request_terminate_timeout`) — these determine which failure mode (PHP `set_time_limit` vs. web-server kill) is actually occurring in the "loading nalang" reports.
- Confirm queue infrastructure availability (is a queue worker/driver already running in production, or would Step G.3 require new infra work?) before proposing it as the primary fix.
- The `$typePatterns` array referenced in `generateQuizAi()` has a `'Normal'` fallback key referenced in a comment/pattern lookup (`$typePatterns[$targetDifficulty] ?? $typePatterns['Normal']`) but the array only defines `Easy`/`Average`/`Difficult` — verify this isn't a latent bug (`$typePatterns['Normal']` would be `null`, causing `$base = array_sum($pattern)` to fail) if a difficulty value outside the three ever reaches that line.

---

## 7. Summary for quick orientation

| Symptom | Root cause (confirmed by production logs, §4a) | Primary fix |
|---|---|---|
| Yield varies wildly per run (10/60 vs 58/60) | **`stem_echo` rejections dominate (34 hits, concentrated in why/how tasks); dedup Condition 4 over-flags on shared distractor options alone (70% of dedup removals, confirmed false positives on inspection); Condition 5 is NOT a factor (0 hits)** | Fix Condition 4 (Step B), address why/how `stem_echo` (Step B2), scale top-up budget (Step C) |
| 2–5 min generation time | All AI calls run sequentially, synchronously, inside one HTTP request — **confirmed by log timestamps showing ~7s/iteration spacing** | Parallelize main-pass calls (Step G.2); ultimately move to a queued job (Step G.3) |
| Sometimes never finishes ("loading nalang") | No timeout on the Cloudflare HTTP call; PHP/web-server timeout kills the request silently with no clean error surfaced to the frontend | Add explicit timeout + retry at the HTTP client level (Step G.1) — do this regardless of the other fixes |

**Revision note:** the first version of this brief (pre-data) prioritized dedup Condition 5 as the likely main cause of yield variance. Production logs from 2026-08-31 disproved that (0 hits) and identified `stem_echo` and Condition 4 as the actual dominant losses instead. This is the corrected version — see §4a for the full evidence and reasoning.
