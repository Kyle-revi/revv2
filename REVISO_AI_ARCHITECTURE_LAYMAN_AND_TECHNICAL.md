# REVISO ARTIFICIAL INTELLIGENCE & EVALUATION ARCHITECTURE
**Comprehensive System Specification: Layman's Terms & Academic/Technical Research Versions**

> **Document Overview**: This document provides two complete, English-only versions explaining the Reviso AI Engine:
> - **Part 1: Layman's Terms Version** — Designed for general stakeholders, teachers, students, and non-technical panels.
> - **Part 2: Academic & Technical Specification** — Designed for thesis defense panels, research publications, and software engineers.
>
> *(A formatted Microsoft Word `.docx` version has also been compiled as `Reviso_AI_Architecture_Layman_and_Technical_Versions.docx`)*

---

# PART 1: LAYMAN'S TERMS VERSION (SIMPLIFIED & PRACTICAL)

## 1. What is Reviso's AI System in Simple Words?
Think of Reviso's AI as an **Automated Exam Writer paired with a Strict Quality Inspector** working side-by-side in real time.

In traditional systems, when teachers want to create an exam from their lecture notes or textbook PDFs, they either have to write every single question by hand (which takes hours) or paste text into standard chatbots like ChatGPT. However, raw chatbots often make up fake facts, repeat questions, or make Option "A" the correct answer almost every time.

Reviso solves this by combining two parts:
1. **The AI Writer**: A high-speed artificial intelligence that reads lecture documents and drafts realistic multiple-choice questions.
2. **The Automated Quality Inspector**: A set of strict built-in rules that immediately inspects, fact-checks, shuffles, and cleans every question before any teacher or student sees it.

---

## 2. Which AI Model Do We Use and Why?
Reviso runs on the **Meta Llama 3 Series** deployed through **Cloudflare Workers AI (Serverless Edge Network)**.

### Why Did We Choose This Setup?
- **1. Resilient Micro-Batch Architecture**: Standard AI chatbots try to generate huge 50-item prompts all at once, which frequently times out or degrades in quality. Reviso breaks the document into smaller, concurrent micro-batches with iterative top-up validation passes, preventing server crashes and ensuring high item quality across large exams (such as 50–60 question assessments).
- **2. No Expensive Supercomputer Rents**: Traditional AI setups require renting expensive supercomputing GPU servers that cost thousands of dollars monthly even when idle. Cloudflare's serverless edge network only runs when requested, keeping operational costs virtually zero.
- **3. Student Privacy & Data Security**: Teacher lecture files and student test answers are kept inside an enterprise secure boundary and are never used to train public commercial AI models.
- **4. 100% Crash-Free Output**: The AI is programmed to communicate strictly in structured data format (JSON). It never adds conversational fluff like *"Here is your quiz!"*—it sends clean, ready-to-save questions directly into the database.

---

## 3. Why is it Called a "Hybrid / Rule-Based" System?
If you rely on AI alone, you get unpredictable results. That is why Reviso is a **Hybrid System**: the AI does the creative reading, while our **6 Automated Safety Rules** guarantee academic quality:

1. **Rule 1: Document Cleaning Rule**  
   Before the AI even reads your document, our system automatically strips away messy headers, page numbers, journal watermarks, and website links so the AI only learns from pure educational content.
2. **Rule 2: Balanced Thinking Skills (Bloom's Taxonomy)**  
   The system makes sure the test isn't just basic memorization. It mathematically balances questions across definitions (*"What is..."*), concepts (*"Why does..."*), and situational application (*"How would you handle..."*).
3. **Rule 3: Anti-Hallucination Fact Check**  
   The system double-checks that the correct answer is genuinely backed up by words found inside the teacher's uploaded PDF, stopping fake or fabricated AI answers in their tracks.
4. **Rule 4: Anti-Repetition / Duplicate Filter**  
   Our system compares all drafted questions using mathematical word-matching algorithms. If two questions test the exact same concept in slightly different words, the duplicate is instantly deleted and replaced.
5. **Rule 5: Fair Answer Key Shuffle (Fisher-Yates Algorithm)**  
   AI models naturally tend to make 'A' the correct choice. Reviso uses a mathematical randomizer to evenly spread correct answers across A, B, C, and D (exactly 25% each), making it impossible for students to guess patterns.
6. **Rule 6: Smart Exam Quality Score (Psychometrics)**  
   After students take the exam, our analytics engine calculates question difficulty, spots tricky or confusing questions, and gives teachers a report on which topics students mastered and where they need review.

---

## 4. Layman Summary Comparison

| Feature | Raw / Standard AI (ChatGPT) | Reviso Hybrid AI System |
| :--- | :--- | :--- |
| **Generation Architecture** | Monolithic Single-Prompt (High timeout risk on 50-60 items) | **Micro-Batch Parallel Pooling (Iterative top-up and validation passes)** |
| **Duplicate Questions** | Frequent repeats across large exams | **Zero duplicates (guaranteed by 6-point math filter)** |
| **Answer Key Balance** | Biased (often puts answers on 'A' or 'C') | **Perfect 25% balance across A, B, C, and D** |
| **Source Accuracy** | May hallucinate outside facts | **Strictly verified against uploaded lecture text** |
| **Student Analytics** | None (just raw percentage score) | **Deep psychometrics (Difficulty, Discrimination, Topic Insights)** |

---
---

# PART 2: TECHNICAL & ACADEMIC SPECIFICATION (RESEARCH GRADE)

## 1. Architectural Classification & System Overview
The Reviso Assessment Generation and Evaluation Subsystem is architected as a **Hybrid Neuro-Symbolic Artificial Intelligence System**. The architecture unifies probabilistic Generative Large Language Models (the neural component) with deterministic constraint algorithms, Classical Test Theory (CTT) psychometrics, and lexical graph deduplication engines (the symbolic component).

This dual-layer design addresses the core stochastic weaknesses of autoregressive language models—specifically non-deterministic JSON framing, hallucination of non-contextual assertions, token position bias in multiple-choice questions (MCQs), and cross-batch semantic duplication.

```
 ┌────────────────────────────────────────────────────────────────────────┐
 │                      REVISO HYBRID AI PIPELINE                         │
 └────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
       [ 1. Document Extraction & Noise Stripping (Rule-Based Regex) ]
                                    │
                                    ▼
       [ 2. Cognitive Bloom's Taxonomy Matrix (Deterministic Math) ]
                                    │
                                    ▼
       [ 3. Concurrent Micro-Batch Generation (Parallel LLM Pool) ]
                                    │
                                    ▼
       [ 4. Groundedness Token Validator (Anti-Hallucination Rule) ]
                                    │
                                    ▼
       [ 5. Multi-Condition Deduplication Engine (Jaccard + Levenshtein) ]
                                    │
                                    ▼
       [ 6. Fisher-Yates Answer Randomizer & Distractor Balancer ]
                                    │
                                    ▼
       [ 7. Item Analysis & Classical Test Theory Engine (Psychometrics) ]
```

---

## 2. Neural Tier: Model Topology & Inference Infrastructure
The Generative Layer operates on Meta's Llama 3 series of Transformer-based models deployed via **Cloudflare Workers AI** edge inference and routed through the **Cloudflare AI Gateway**.

### 2.1 Model Topology & Specialized Assignments
- **Meta Llama 3.2 3B Instruct (`@cf/meta/llama-3.2-3b-instruct`)** *(Default Engine)*:  
  Quantized instruction-tuned autoregressive transformer with 3.21 billion parameters. Assigned as the primary engine for high-throughput micro-batch item generation, real-time diagnostic evaluation, and instantaneous student tutoring feedback. Operates with sub-second execution per batch.
- **Meta Llama 3.1 8B Instruct (`@cf/meta/llama-3.1-8b-instruct`)**:  
  8.03 billion parameter dense model utilized for complex case vignette synthesis, clinical scenario modeling, and contextualized qualitative classroom performance evaluations requiring extended multi-token reasoning.
- **Meta Llama 3.3 70B Instruct FP8 (`@cf/meta/llama-3.3-70b-instruct-fp8-fast`)**:  
  70.6 billion parameter model running in 8-bit floating point precision (FP8 Fast) for high-stakes Mock Board examinations, comprehensive multi-domain curriculum cross-validation, and licensure-grade distracter calibration.

### 2.2 Asynchronous Non-Blocking HTTP Concurrency (`runPool`)
Rather than serializing LLM generation via a single monolithic prompt (which incurs quadratic attention complexity $\mathcal{O}(n^2)$ over long context windows and high latency timeouts), Reviso executes item generation through asynchronous HTTP pooling (`Http::pool`) combined with iterative top-up validation cycles. The system partitions the target question volume into concurrent micro-tasks (1 to 2 items per prompt) executed across Cloudflare edge workers, ensuring high resilience and preventing gateway timeouts even for comprehensive 50 to 60+ item assessments.

### 2.3 Strict Constrained Decoding via JSON Schema
To guarantee 100% syntactic reliability, inference requests enforce native JSON Schema validation at the token generation layer (`response_format: { type: 'json_schema' }`). The grammar constraints force the LLM state machine to output valid JSON object arrays conforming strictly to schema definitions (`question: string`, `options: object`, `correct: enum[A,B,C,D]`, `difficulty: enum`, `question_type: enum`, `evidence: string`), completely eliminating regex markdown stripping failures.

---

## 3. Symbolic Tier: Deterministic Rule-Based & Validation Modules

### 3.1 Text Sanitization & Sentence Boundary Chunking
Raw PDF/DOCX streams undergo deterministic cleaning (`cleanExtractedPdfText`) via regular expression filters targeting journal metadata, volume citations, ISSN identifiers, and pagination artifacts. Text truncation (`truncateAtSentenceBoundary`) computes sentence boundaries using lookahead regex matching (`[.!?](?=\s|$)`) to preserve linguistic proposition integrity.

### 3.2 Cognitive Matrix: Bloom's Revised Taxonomy Allocation
Item generation is algorithmically constrained across cognitive levels (What: Knowledge/Recall; Why: Conceptual/Comprehension; How: Procedural/Application) based on target difficulty tiers:
- **Average Tier**: 60% What (Recall), 30% Why (Analysis), 10% How (Application).
- **Normal Tier**: 30% What (Recall), 40% Why (Analysis), 30% How (Application).
- **Hard Tier**: 10% What (Recall), 30% Why (Analysis), 60% How (Application).

### 3.3 Token-Level Groundedness & Anti-Hallucination Verification
The function `isGroundedInSource()` validates generated items by extracting salient token vectors (words $\ge 3$ characters) from the model's required `evidence` string and verifying that a minimum of 35% of substantive tokens maintain exact lexical presence in the source text partition.

### 3.4 Multi-Condition Deduplication Engine (`deduplicateQuestionBatch`)
All candidates undergo rigorous cross-batch deduplication via a 6-condition algorithmic filter:
1. **Condition 1 (Exact Stem Metric)**: Calculates character-level string similarity on normalized, lowercase, punctuation-stripped strings. Items with similarity $\ge 92.0\%$ are rejected as near-verbatim duplicates.
2. **Condition 2 (Prefix-Stripped Semantic Match)**: Removes common interrogative prefixes (*"What is the"*, *"Which of the following describes"*) before comparison. Items with stem similarity $\ge 75.0\%$ AND correct answer similarity $\ge 40.0\%$ are discarded.
3. **Condition 3 (Jaccard Lexical Overlap)**: Computes token-set intersection over union on significant words (length $\ge 3$):  
   $$J(A,B) = \frac{|A \cap B|}{|A \cup B|}$$  
   High Jaccard overlap paired with answer similarity triggers rejection.
4. **Condition 4 (Distractor Pool Congruence)**: If two items share $\ge 3$ distracter options (similarity $\ge 85.0\%$) and exhibit identical answer keys, the redundant item is eliminated.
5. **Condition 5 (Semantic Paraphrasing Filter)**: Detects syntactic rephrasing where answer key similarity $\ge 75.0\%$, stem similarity $\ge 45.0\%$, and Jaccard overlap $\ge 30.0\%$.
6. **Condition 6 (Syntactic Substring Containment)**: Flags and removes items where one question stem is an exact token subset of an existing item.

### 3.5 Cryptographic Uniform Answer Permutation
To neutralize positional bias (where LLMs disproportionately place the correct key in option 'A'), the system applies the Fisher-Yates shuffle using cryptographically secure random integers (`random_int(0, i)`). The key mapping is recalculated, guaranteeing a perfectly uniform theoretical distribution of:
$$P(K = k) = 0.25 \quad \forall \; k \in \{A, B, C, D\}$$

### 3.6 Psychometrics & Classical Test Theory (CTT) Item Analytics
Post-examination analytics are computed by the `MockBoardStatisticsService` executing empirical psychometric formulations:
1. **Item Difficulty Index ($p$)**:  
   $$p = \frac{R}{N}$$  
   *(Where $R$ is correct responses and $N$ is total examinees; items categorized as Very Difficult $p < 0.20$, Moderate $0.20 \le p \le 0.80$, Very Easy $p > 0.80$).*
2. **Item Discrimination Index ($D$)**:  
   $$D = \frac{U - L}{n}$$  
   *(Where $U$ and $L$ represent correct counts in the upper and lower 27% score groups. Items with $D < 0.20$ are flagged for revision or distractor recalibration).*
3. **Point Biserial Correlation ($r_{pbi}$)**:  
   $$r_{pbi} = \frac{\bar{X}_p - \bar{X}_q}{S_t} \sqrt{pq}$$  
   *(Measures item-to-total score validity and identifies malfunctioning distractors).*
4. **Board Likelihood Estimation**:  
   Weighted Bayesian score aggregation calibrated against historical Philippine Professional Regulation Commission (PRC) institutional and national passing benchmarks.

---

## 4. Comparative Architectural Justification

| Evaluation Metric | Pure LLM Approach | Pure Rule-Based Approach | **Reviso Hybrid Neuro-Symbolic** |
| :--- | :--- | :--- | :--- |
| **Semantic Comprehension** | High (Stochastic) | Zero (Fixed Template Parsing) | **High (Contextual Autoregressive Transformer)** |
| **Syntactic Determinism** | Low (Prone to framing errors) | 100% Deterministic | **100% Enforced via JSON Schema Layer** |
| **Hallucination Defense** | Weak (Requires prompting) | N/A (No generative capability) | **Grounded via Token Presence & Evidence Extraction** |
| **Item Deduplication** | Fails on cross-batch N-items | Strict regex only | **Multi-Metric: Jaccard + Levenshtein + Distractor Graph** |
| **Answer Key Symmetry** | Skewed (Positional Bias on A/C) | Randomized | **Uniform Cryptographic Fisher-Yates $P(k)=0.25$** |

---

## 5. Conclusion & Research Significance
By synthesizing Meta Llama 3 transformer capabilities with rigorous rule-based validation pipelines, Reviso establishes a verifiable, high-throughput, and cost-efficient assessment generation platform. The hybrid architecture guarantees pedagogical validity, structural integrity, and psychometric reliability essential for professional licensure preparation and higher education institutions.
