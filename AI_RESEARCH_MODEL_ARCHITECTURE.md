# Reviso AI Architecture & Research Documentation
**Technical Report on AI Model Selection, Hybrid (Neuro-Symbolic) Design, and Rule-Based Validation Systems**

---

## 1. Executive Summary

Ang AI engine ng **Reviso** ay gumagamit ng isang **Hybrid (Neuro-Symbolic) AI Architecture**. Hindi ito purong Large Language Model (LLM) lamang at hindi rin purong Rule-Based system; bagkus, ito ay pinagsamang:

1. **Generative Neural Layer (LLMs via Cloudflare Workers AI)**:
   - Tumatanggap ng unstructured learning documents (PDFs, PPTs, DOCX) at bumubuo ng contextualized board exam multiple-choice questions (MCQs), diagnostic insights, at classroom performance analytics.
2. **Deterministic Symbolic & Rule-Based Layer (Algorithms & Post-Processing)**:
   - Mahigpit na nagpapatupad ng psychometric rules, anti-hallucination token validation, multi-condition deduplication, cognitive Bloom's taxonomy balancing, at randomized answer key permutations.
3. **Statistical & Psychometric Engine (Classical Test Theory)**:
   - Nagko-compute ng Item Difficulty Index ($p$), Discrimination Index ($D$), Point Biserial Correlation ($r_{pbi}$), at Historical PRC Board Exam passing likelihoods.

---

## 2. AI Models & Infrastructure (The Neural Layer)

### 2.1 Anong AI Models ang Ginamit?

Ang Reviso ay pinapatakbo ng **Meta Llama 3 Series** na naka-deploy sa **Cloudflare Workers AI (Edge Inference)**:

| Model Identifier | Display Name | Role / Purpose sa Reviso | Bakit ito ang ginamit? |
| :--- | :--- | :--- | :--- |
| `@cf/meta/llama-3.2-3b-instruct` *(Default)* | **Llama 3.2 3B Instruct** | Rapid Question Generation & Student Feedback | **Ultra-low latency (< 1s per micro-batch)**, mababang token cost, mataas ang accuracy sa structured JSON schema outputs. |
| `@cf/meta/llama-3.1-8b-instruct` | **Llama 3.1 8B Instruct** | Deep Reasoning & Complex Vignette Questions | Mas malalim na contextual understanding para sa case-based scenarios at analytical questions. |
| `@cf/meta/llama-3.3-70b-instruct-fp8-fast` | **Llama 3.3 70B Instruct (FP8)** | Mock Board Synthesis & High-Stakes Assessments | Pinakamataas na reasoning capability para sa cross-domain synthesis at comparative evaluation. |

---

### 2.2 Bakit Cloudflare Workers AI & Cloudflare AI Gateway?

1. **Serverless Edge Latency**:
   - Tumatakbo ang inference sa higit 300+ data centers ng Cloudflare sa buong mundo (kabilang ang Manila edge servers), kaya mabilis ang response time kumpara sa traditional self-hosted GPU instances.
2. **Cost-Efficiency**:
   - Hindi kailangang mag-maintain ng mamahaling dedicated GPU servers (tulad ng AWS EC2 `p3/g4` instances o GCP A100 VMs) na nagkakahalaga ng libo-libong piso buwan-buwan kahit walang gumagamit.
3. **Enterprise AI Gateway Integration**:
   - May built-in rate-limiting, automatic retries, prompt token caching, at detailed token usage auditing na integrated sa `AiSettingsResolver`.
4. **Native Structured Output (`json_schema`) Enforcement**:
   - Sinusuportahan ng inference engine ang strict JSON Schema constraint sa decoding level, na pumipigil sa LLM na maglabas ng markdown conversational fluff (e.g. *"Here are your questions..."*).

---

## 3. The Rule-Based & Algorithmic Guardrails (The Symbolic Layer)

### Bakit Hindi Pwedeng Purong LLM Lamang? (The Limitations of Raw LLMs)

Kung aasa lamang sa purong prompt ng LLM nang walang rule-based system:
- **Hallucinations**: Maaaring mag-imbento ang LLM ng impormasyon na wala sa libro o lecture file.
- **Duplicate Questions**: Sa malalaking exam (e.g., 50–100 items), madalas na inuulit ng LLM ang parehong konsepto gamit lamang ang bahagyang magkaibang salita.
- **Answer Letter Bias**: Likas sa LLMs na ilagay ang tamang sagot sa Option 'A' o 'C' nang paulit-ulit.
- **Surface-Level Recall (Stem Echoing)**: Kinokopya lamang ng LLM ang eksaktong pangungusap mula sa PDF nang walang cognitive challenge.

Upang masiguro ang academic integrity at board-exam quality, ipinatupad sa Reviso ang mga sumusunod na **Deterministic Rule-Based Modules**:

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

### 3.1 Document Ingestion & Text Sanitation (Rule-Based)
- **Header/Footer & Watermark Stripping**: Awtomatikong tinatanggal ang mga journal headers, ISSN numbers, website URLs, at page numbers gamit ang strict regex filters (`cleanExtractedPdfText`).
- **Sentence Boundary Truncation (`truncateAtSentenceBoundary`)**: Hindi pinuputol ang teksto sa gitna ng salita o pangungusap; pinuputol lamang ito sa mga wastong punctuation boundaries (`.`, `!`, `?`) upang mapanatili ang buong diwa ng konteksto.

---

### 3.2 Cognitive Bloom's Taxonomy Matrix (Rule-Based Balancing)
Bago tawagin ang AI, may deterministic mathematical distribution algorithm na naghahati sa exam:

$$\text{Total Questions} = \text{What (Knowledge/Recall)} + \text{Why (Comprehension/Analysis)} + \text{How (Application/Synthesis)}$$

- **Average Tier**: 60% What, 30% Why, 10% How.
- **Normal Tier**: 30% What, 40% Why, 30% How.
- **Hard Tier**: 10% What, 30% Why, 60% How.

---

### 3.3 Source Groundedness Verification (Anti-Hallucination Filter)
- Sinusuri ng `isGroundedInSource()` kung ang nabuong `evidence` string at key terms ng tanong ay mayroong token match sa orihinal na PDF gamit ang word frequency extraction. Kapag mas mababa sa threshold ang overlap, ire-reject ang tanong.

---

### 3.4 Multi-Condition Deduplication Engine (`deduplicateQuestionBatch`)
Gumagamit ang system ng 6-stage algorithmic validation upang maiwasan ang mga magkakaparehong tanong:

1. **Exact & Near-Exact Stem Similarity (Levenshtein Metric $\ge 92\%$)**:
   - Sinusukat ang character-level similarity gamit ang normalized string algorithms.
2. **Prefix-Stripped Stem Matching**:
   - Tinatanggal ang boilerplate prefixes (*"What is the..."*, *"Which of the following describes..."*) bago suriin ang core question concept.
3. **Jaccard Word-Level N-Gram Overlap**:
   - Kinakalkula ang set intersection at union ng mga makabuluhang salita:
     $$J(A, B) = \frac{|A \cap B|}{|A \cup B|} \times 100\%$$
4. **Answer Key Cross-Validation**:
   - Kung ang dalawang tanong ay may halos magkaparehong tamang sagot ($\text{Similarity} \ge 80\%$) sa parehong topic, tinatrato ito bilang duplicate.
5. **Distractor Pool Overlap Check**:
   - Sinusuri kung 3 o higit pang choices ang magkapareho sa pagitan ng magkahiwalay na tanong.
6. **Token Containment / Substring Search**:
   - Sinisiguro na ang isang tanong ay hindi simpleng subset o pinaikling kopya ng isa pa.

---

### 3.5 Fisher-Yates Uniform Answer Shuffling
- Ang LLM ay may tendensiyang maglagay ng tamang sagot sa unang option.
- Gumagamit ang Reviso ng **Cryptographically Secure Fisher-Yates Algorithm (`random_int`)** upang balansehin ang tamang sagot sa 'A', 'B', 'C', at 'D', na pumipigil sa mga pattern na maaaring hulaan ng estudyante.

---

### 3.6 Psychometrics & Classical Test Theory Engine (`MockBoardStatisticsService`)
Ang Reviso ay may sariling statistical analysis suite para sa item quality:

1. **Item Difficulty Index ($p$)**:
   $$p = \frac{R}{N}$$
   *(Kung saan ang $R$ ay dami ng tamang sumagot at $N$ ang kabuuang kumuha).*
2. **Item Discrimination Index ($D$)**:
   $$D = \frac{U - L}{n}$$
   *(Sinusukat kung epektibong napagbubukod ng tanong ang top 27% high scorers laban sa bottom 27% low scorers).*
3. **Point Biserial Correlation ($r_{pbi}$)**:
   $$r_{pbi} = \frac{\bar{X}_p - \bar{X}_q}{S_t} \sqrt{pq}$$
   *(Sinusukat ang statistical correlation ng bawat aytem sa pangkalahatang board exam performance).*

---

## 4. Comparison Summary: Why Hybrid (Neuro-Symbolic)?

| Criteria | Pure LLM (Direct ChatGPT/Llama) | Pure Rule-Based (Legacy System) | **Reviso Hybrid AI (LLM + Rules)** |
| :--- | :--- | :--- | :--- |
| **Natural Language Understanding** | Mataas | Wala (Templates lang) | **Mataas (LLM Generative Layer)** |
| **Document Summarization** | Flexible | Limitado / Hindi kaya | **Flexible sa kahit anong PDF/DOCX** |
| **Hallucination Control** | Mahina (madaling mag-imbento) | 100% Deterministic | **100% Guarded (Token Validation)** |
| **Duplicate Prevention** | Mahina sa maramihang aytem | Strict pero rigid | **Algorithmic (Jaccard + Levenshtein)** |
| **Balanced Answer Key** | Madalas biased sa iisang letra | Random | **Perpektong 25% distribution (Fisher-Yates)** |
| **Generation Speed** | Mabagal kung 50 items sabay | Mabilis | **Ultra-fast via Parallel HTTP Pool** |
| **Psychometric Validity** | Walang CTT calculation | Manual statistics | **Automated Item & Board Analysis** |

---

## 5. Architectural Benefits for Review Centers & Universities

1. **Zero Cold-Start / Real-Time Batching**:
   - Sa pamamagitan ng **Parallel Non-Blocking HTTP Pooling (`Http::pool`)**, ang pagbuo ng 30–50 questions ay tumatagal lamang ng **4 hanggang 8 segundo**, kumpara sa 45–90 segundo sa traditional linear LLM architectures.
2. **Data Privacy & Compliance**:
   - Ang mga pagsusulit at student records ay hindi ipinapadala sa third-party public training pipelines; nananatili itong naka-isolate sa Cloudflare private edge boundary.
3. **Explainable & Pedagogically Sound**:
   - Bawat nabubuong tanong ay may kaakibat na cognitive type (What/Why/How), difficulty tier, evidence trail, at psychometric validation.

---

*Authored by: Reviso Engineering & AI Research Team*  
*Document Version: 1.0 (Academic & System Architecture Specification)*
