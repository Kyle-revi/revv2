# AI Prompt Optimization & Insights Enhancement Report

**Date:** October 2026  
**System:** Reviso (Accounting Review & Assessment Platform)  
**Status:** PROPOSED & READY FOR APPROVAL (`mdfile report muna`)  
**Target Files:**
- `app/Services/AiSettingsResolver.php`
- `app/Http/Controllers/ClassManagerController.php`
- `app/Http/Controllers/QuizController.php`
- `app/Http/Controllers/StudentMockBoardController.php`
- `database/migrations/2026_10_04_000001_update_ai_settings_default_prompts.php` (New Migration)

---

## 1. Executive Summary

The user requested three critical enhancements to Reviso's AI capabilities:
1. **Lecture-Based Question Generation:** Questions must be strictly derived from the theoretical concepts, standards, rules, criteria, and classifications taught in the lecture.
2. **Strict Prohibition on Worked Examples & Sample Q&A (Anti-Hallucination):** Prevent the AI from copying or testing numbers, specific entity names, or scenarios from worked examples in lecture slides. For non-lecture documents or files with existing sample questions/answer keys, prevent the AI from extracting or confusing existing Q&A, which currently causes severe hallucinations.
3. **Specific, Diagnostic AI Insights & Actionable Recommendations:** Eliminate generic one-liner recommendations (e.g., *"Revisit the module content first, then retake the quiz"* or *"Action Required: Prioritize reviewing your low-scoring concepts, specifically targeting General Assessment"*). Provide granular, concept-specific strengths, weaknesses, and a structured multi-step study action plan.

This report documents the **root cause diagnosis** of why these issues occur in the current codebase, followed by the **exact prompt designs and code fixes** ready for implementation.

---

## 2. Root Cause Analysis & Code Audit

### Issue A: Why Quiz Generation Hallucinates & Copies Worked Examples

#### Current Implementation:
- In `app/Http/Controllers/ClassManagerController.php` (lines 2371–2387 and 2582–2593) and `app/Services/AiSettingsResolver.php` (line 27):
```text
Generate EXACTLY {num_questions} unique multiple-choice questions based ONLY on the text below.
...
Requirements:
- Formulate questions specifically testing concepts, rules, facts, or scenarios found in the content below.
- Return ONLY a valid JSON array...
```

#### Diagnostic Findings:
1. **No Negative Directives Against Examples:** In accounting and business subjects, lecture slides frequently contain **worked numerical illustrations** (e.g., *"Illustration 1: On Jan 1, 2024, ABC Corp. acquired machinery for P500,000 with a 5-year useful life..."*). Because the prompt instructs the model to test *"scenarios found in the content"*, the LLM directly copies the specific names and figures from the example, generating brittle, trivial questions (e.g., *"How much did ABC Corp. pay?"*).
2. **Hallucination on Reviewers / Non-Lecture Documents:** When teachers upload handouts, reviewers, or practice exams that contain pre-existing exercises and answer keys, the LLM treats the already-answered questions as text to summarize or scramble. It frequently confuses the question stems with the answer keys, resulting in invalid options, inverted correct answers, or nonsensical questions.
3. **Lack of Conceptual Anchoring:** The prompt fails to command the model to isolate the **underlying lecture principles, standards (PFRS/PAS), definitions, rules, criteria, or accounting entries**, and instead lets the model latch onto whatever sentences have the highest token density (which are usually the worked examples).

---

### Issue B: Why AI Insights & Recommendations Are Currently Generic

Our deep audit of `app/Http/Controllers/QuizController.php` and `app/Http/Controllers/StudentMockBoardController.php` uncovered **four compounding root causes**:

#### Root Cause 1: Critical Code Bug in `QuizController.php` (Line 222)
In `QuizController::generateInsights()` (lines 210–225):
```php
$result = $ai->run($resolver->getModel(), [
    'messages' => [
        ['role' => 'system', 'content' => $resolver->getPromptTemplate('quiz_insights', 'system')],
        ['role' => 'user', 'content' => $userPrompt],
    ],
    'max_tokens' => $resolver->getMaxTokens(),
    'temperature' => 0.6,
]);

// Always persist the fallback insights regardless of whether the
// AI call returned usable text...
$attempt->update([
    'ai_strong' => $fallback['strong'],
    'ai_weak' => $fallback['weak'],
    'ai_recommendation' => $fallback['recommendation']
]);
```
> [!CAUTION]
> **Smoking Gun Bug:** Cloudflare Workers AI is called, but **`$result['response']` is never parsed or saved!**
> The controller unconditionally updates the database with `$fallback['recommendation']`. **The AI's actual generated response is 100% discarded**, meaning students always receive the static fallback string!

#### Root Cause 2: Hardcoded Generic Fallback Templates in `QuizController.php`
In `QuizController::buildFallbackQuizInsights()` (lines 72–76):
```php
$recommendation = match (true) {
    $attempt->percentage >= 85 => 'Keep the pace and review the missed items once for retention.',
    $attempt->percentage >= 50 => 'Review the incorrect questions and revisit the related lesson sections before the next attempt.',
    default => 'Revisit the module content first, then retake the quiz.',
};
```
Because of Root Cause 1, every student who completed a quiz was guaranteed to see one of these three exact generic one-liners.

#### Root Cause 3: Overly Restrictive Prompt in `AiSettingsResolver.php`
In `app/Services/AiSettingsResolver.php` (lines 28–29):
```php
'prompt.quiz_insights.system' => 'You are a strict tutor. Reply ONLY in the exact format requested. Keep it short and clear. No extra text. Keep in mind that this is not MATH or Any Form of MATH related question.',
'prompt.quiz_insights.user_template' => "Student scored {score}% on '{module_title}'.\n\n{answers_context}\n\nAnalyze and reply in this exact short format (maximum 3 lines per section):\nStrong Areas: - point1\n - point2\nWeak Areas: - point1\n - point2\nRecommendation: One short sentence.",
```
- **"Keep in mind that this is not MATH..."**: Actively degrades the AI's ability to explain accounting computations, financial ratios, or tax rates.
- **"Recommendation: One short sentence."**: Explicitly forbids the AI from giving detailed, diagnostic, or multi-step study recommendations!

#### Root Cause 4: Schema Mismatch in `StudentMockBoardController.php` (Line 666)
In `StudentMockBoardController::insights()` (lines 665–666):
```php
$question = $answer->question;
$subject = $question->category ?? $question->subject ?? 'General Assessment';
```
- The `quiz_questions` table has a **`domain`** column, NOT `category` or `subject`.
- As a result, `$subject` **always evaluates to `'General Assessment'`**.
- The recommendation then outputs:
  ```text
  "Action Required: Prioritize reviewing your low-scoring concepts, specifically targeting General Assessment."
  ```
  Students never see which board exam domain (e.g., FAR, AUD, TAX, MAS, RFBT, AFAR) they actually struggled with.

---

## 3. Proposed Solution & Implementation Blueprint

### Part 1: Quiz Generation Prompt Overhaul

#### A. In `app/Http/Controllers/ClassManagerController.php` (Main & Top-up Prompts)
We will add strict **Lecture-Based** and **Anti-Example** directives directly into the prompt payload:

```text
════════════════════════════════════════
CORE GENERATION DIRECTIVES:
1. STRICTLY LECTURE-BASED:
   - Formulate questions testing ONLY the underlying theoretical principles, definitions, standards, classifications, criteria, rules, and methodologies taught in the lecture text.
   
2. NO WORKED EXAMPLES OR ILLUSTRATIONS:
   - DO NOT copy, convert, or reference specific worked examples, numerical illustrations, case studies, or hypothetical entity/person names found in the text (e.g., do NOT test "In Example 1...", "Company ABC purchased...", "Mr. Tan invested...").
   - If testing a calculation or rule application, construct a fresh, independent scenario testing the general standard or formula—NEVER reuse the illustrative numbers from the text.

3. NO PRE-EXISTING QUESTIONS OR EXERCISE KEYS:
   - If the source document contains practice questions, self-test drills, quizzes, or answer keys, DO NOT copy, adapt, or extract those questions. Base questions solely on the expository lecture discussion.

4. BOARD EXAM QUALITY:
   - Questions must be clear, unambiguous, and plausible.
   - All distractors must be plausible, distinct concepts from the same domain—no repetitive or obvious throwaway options.
════════════════════════════════════════
```

#### B. In `app/Services/AiSettingsResolver.php` (Global Defaults & Admin Interface)
Update `prompt.quiz_generation.system` and `prompt.quiz_generation.user_template`:

- **System Prompt:**
  ```text
  You are an expert academic examiner and board examination test developer. Output ONLY a valid JSON array of exactly {num_questions} question objects. Every question must be lecture-based, concept-focused, distinct, and free of illustrative examples. No markdown, no backticks, no conversational text.
  ```

- **User Template:**
  ```text
  Generate EXACTLY {num_questions} high-quality, lecture-based multiple-choice questions for '{module_title}'.
  Difficulty: {difficulty}.
  
  Content:
  {combined_text}
  
  STRICT RULES:
  1. Base questions SOLELY on core lecture concepts, principles, rules, definitions, and frameworks.
  2. NEVER use or extract worked examples, numerical illustrations, case study numbers, or specific company/individual names from the text.
  3. If the document contains practice quizzes, exercises, or answer keys, IGNORE them and test the lecture concepts instead.
  4. Return ONLY a valid JSON array of {num_questions} objects matching:
     {"question":"...","options":{"A":"...","B":"...","C":"...","D":"..."},"correct":"A|B|C|D"}
  5. Stop immediately after {num_questions} questions.
  ```

---

### Part 2: AI Insights & Recommendation Engine Overhaul

#### A. In `app/Services/AiSettingsResolver.php`
- **System Prompt:**
  ```text
  You are an expert academic mentor and board exam review advisor. Analyze the student's performance diagnostics objectively and provide specific, high-yield feedback. Do not provide generic advice like "study harder" or "review notes". Always cite specific concepts, standards, or rules.
  ```

- **User Template:**
  ```text
  Student scored {score}% on the module '{module_title}'.
  
  Student Assessment Item Breakdown:
  {answers_context}
  
  Analyze the student's performance and provide detailed, actionable feedback in this exact format:
  
  Strong Areas:
  - [Specific concept, standard, or topic where the student showed mastery, explaining why or how]
  - [Additional specific strong concept]
  
  Weak Areas:
  - [Specific concept, rule, or calculation method the student missed, noting the specific confusion]
  - [Additional specific weak area identified from incorrect answers]
  
  Recommendations:
  1. [Specific action step naming the exact topic or rule to re-read and clarify]
  2. [Targeted practice or distinction to master, contrasting correct vs incorrect treatments]
  3. [Pre-retake checklist or concrete study technique for this specific subject]
  ```

#### B. In `app/Http/Controllers/QuizController.php`
1. **Parse Real AI Output:**
   Implement a robust parser that extracts `Strong Areas:`, `Weak Areas:`, and `Recommendations:` from `$result['response']`.
   ```php
   $rawResponse = (string) ($result['response'] ?? '');
   $parsed = $this->parseAiInsightsResponse($rawResponse);
   
   $attempt->update([
       'ai_strong' => !empty($parsed['strong']) ? $parsed['strong'] : $fallback['strong'],
       'ai_weak' => !empty($parsed['weak']) ? $parsed['weak'] : $fallback['weak'],
       'ai_recommendation' => !empty($parsed['recommendation']) ? $parsed['recommendation'] : $fallback['recommendation'],
   ]);
   ```
2. **Upgrade Local Fallback Engine:**
   When the AI API is unavailable, instead of static 1-line platitudes, the fallback dynamically inspects the actual questions the student got wrong, extracts their specific question stems/topics, and formats a concrete 3-step action plan citing those exact topics.

---

### Part 3: Mock Board Insights Correction

#### In `app/Http/Controllers/StudentMockBoardController.php`:
1. **Fix Domain Resolution:**
   ```php
   $question = $answer->question;
   $subject = $question->domain 
       ?? $question->module?->title 
       ?? $mockBoardPhase->title 
       ?? 'Core Assessment';
   ```
2. **Actionable Recommendations:**
   Instead of a generic single sentence, construct a targeted breakdown by domain:
   - Identify domains below 75% mastery.
   - List the specific missed concepts/questions in those domains.
   - Output structured, concrete review instructions for each weak domain.

---

## 4. Before vs. After Comparison

### Example 1: Question Generation from Lecture Slide with Worked Example

| Scenario | Before (Current) | After (Proposed) |
|---|---|---|
| **Lecture Slide Content:** *"Depreciation under Cost Model. Straight-line method allocates cost evenly. **Illustration 1:** ABC Co. purchased a machine for P120,000 with residual value of P20,000 and 5-year life. Annual depreciation is P20,000."* | **AI Generates:** *"How much was the annual depreciation of ABC Co. in Illustration 1? A) P20,000 B) P10,000..."* (Copies transient numbers from illustration) | **AI Generates:** *"Under the straight-line depreciation method, how is the depreciable base of a depreciable asset determined? A) Cost less residual value B) Cost plus estimated salvage value C) Carrying amount less accumulated depreciation D) Fair value less costs to sell"* (Tests the general rule/concept) |

---

### Example 2: Student Quiz Insights & Recommendation

| Component | Before (Current) | After (Proposed) |
|---|---|---|
| **Weak Areas** | *"Review: What is the journal entry for depreciation? \| Impairment loss."* (Truncated line) | **Specific Diagnostic:** <br>• *Struggled with the recognition criteria for impairment loss under PAS 36, particularly determining recoverable amount.*<br>• *Missed questions concerning the timing of depreciation commencement upon asset availability.* |
| **Recommendation** | *"Review the incorrect questions and revisit the related lesson sections before the next attempt."* (Generic one-liner) | **Concrete 3-Step Action Plan:** <br>1. *Re-read Section 3 on PAS 36 Impairment, focusing on how 'Value in Use' is contrasted with 'Fair Value less Costs of Disposal'.*<br>2. *Practice differentiating between the date an asset is acquired versus the date it is ready for its intended use.*<br>3. *Review the summary table of depreciation methods before attempting the post-test.* |

---

### Example 3: Student Mock Board Insights

| Component | Before (Current) | After (Proposed) |
|---|---|---|
| **Weak Areas** | *"General Assessment (40% Mastery)"* (Category column bug) | **Domain Mastery Breakdown:** <br>• *Financial Accounting and Reporting (FAR) (45% Mastery)*<br>• *Regulatory Framework for Business Transactions (RFBT) (50% Mastery)* |
| **Recommendation** | *"Action Required: Prioritize reviewing your low-scoring concepts, specifically targeting General Assessment."* | **Targeted Domain Guidance:** <br>*Action Required: Prioritize reviewing your low-scoring domains: FAR (45%) and RFBT (50%). Focus on revenue recognition standards under PFRS 15 in FAR, and obligations and contracts provisions in RFBT before proceeding to the Post-Board phase.* |

---

## 5. Database Migration & Deployment Plan

Since default prompts were previously seeded into the `ai_settings` table via `database/migrations/2026_03_28_104028_create_ai_settings_table.php`, we will provide:
1. **Database Migration:** A new migration (`2026_10_04_000001_update_ai_settings_default_prompts.php`) that safely updates existing default prompt rows in `ai_settings` if they contain the old prompt text, ensuring the fix applies automatically to production and Railway databases.
2. **Code Layer Defaults:** Update `AiSettingsResolver::GLOBAL_DEFAULTS` so all future resets and fallback lookups use the new optimized prompts.
3. **Pint Formatting & Automated Tests:**
   - Execute `vendor/bin/pint --format agent` to guarantee clean code style.
   - Run existing feature tests (`QuizInsightsTest.php`) and add new assertions verifying that structured AI insight output is properly parsed and stored.

---

## 6. Request for Feedback

This document is submitted as requested (`mdfile report muna`).  
Upon your signal to proceed, we will implement the code changes and verify them against the test suite.
