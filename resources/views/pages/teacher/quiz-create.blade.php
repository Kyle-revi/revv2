@php
    $layout = in_array(auth()->user()->role, ['admin', 'superadmin'])
        ? 'layouts.appAdmin'
        : 'layouts.appTeach';
@endphp
@extends($layout)
@php
    $existingQuestions = collect($existingQuestions ?? []);
    $isEditing = $existingQuestions->isNotEmpty();
    $isAssessment = (bool) $module->is_formal_assessment;
    $pageLabel = $isEditing
        ? ($isAssessment ? 'Edit Assessment' : 'Edit Pre-Test')
        : ($isAssessment ? 'Create Assessment' : 'Create Pre-Test');
@endphp

@section('title', $pageLabel)
@section('page-heading', $pageLabel)

@section('header-actions')
    @if($existingQuestions->isNotEmpty())
        <form method="POST" action="{{ route('modules.duplicate', $module) }}" style="display:inline-flex;">
            @csrf
            <button type="submit" class="rv-btn rv-btn-secondary" title="Create a fresh reusable copy with no student data">
                <i class="fas fa-copy"></i> Duplicate
            </button>
        </form>
        <form method="POST" action="{{ route('test-bank.modules.import', $module) }}"
              style="display:inline-flex;align-items:center;gap:6px;">
            @csrf
            <input type="text" name="topic" list="existingTopicsList" placeholder="Topic (e.g. Chapter 3)"
                   class="rv-input" style="width:280px;height:46px;font-size:16px;padding:0 14px;">
            <datalist id="existingTopicsList">
                @foreach($existingTopics ?? [] as $topic)
                    <option value="{{ $topic }}"></option>
                @endforeach
            </datalist>
            <button type="submit" class="rv-btn rv-btn-secondary"><i class="fas fa-database"></i> Save Questions to Test Bank</button>
        </form>
    @endif
    <a href="{{ $backUrl ?? url()->previous() }}" class="rv-btn rv-btn-secondary">
        <i class="fas fa-arrow-left"></i> Back
    </a>
@endsection

@section('content')
<style>
    .qc-card {
        background: #fff;
        border: 1px solid #ebebeb;
        border-radius: 14px;
        padding: 28px 28px 24px;
        margin-bottom: 20px;
    }

    .qc-meta {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 24px;
    }

    .qc-meta-icon {
        width: 40px; height: 40px; border-radius: 10px;
        background: #f3f3f3; display: flex; align-items: center;
        justify-content: center; font-size: 16px; color: #888;
    }

    .qc-meta-title { font-size: 16px; font-weight: 500; color: #111; }
    .qc-meta-sub { font-size: 16px; color: #aaa; }

    /* Tabs */
    .qc-tabs { display: flex; gap: 4px; border-bottom: 2px solid #ebebeb; margin-bottom: 24px; }

    .qc-tab {
        padding: 9px 18px; font-size: 16px; font-weight: 500; color: #888;
        border: none; background: none; cursor: pointer; border-bottom: 2px solid transparent;
        margin-bottom: -2px; transition: color 0.15s, border-color 0.15s;
    }

    .qc-tab:hover { color: #444; }
    .qc-tab.active { color: #ED773C; border-bottom-color: #ED773C; }

    .qc-tab-panel { display: none; }
    .qc-tab-panel.active { display: block; }

    /* Form rows */
    .qc-row { display: grid; grid-template-columns: 1fr; gap: 16px; margin-bottom: 16px; }
    .qc-row-single { margin-bottom: 16px; }

    .qc-label {
        display: block; font-size: 16px; font-weight: 500;
        letter-spacing: 0.06em; text-transform: uppercase;
        color: #aaa; margin-bottom: 6px;
    }

    /* Question blocks */
    .qb-block {
        background: #fafafa;
        border: 1.5px solid #e8e8e8;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 16px;
        transition: border-color 0.5s ease, box-shadow 0.5s ease, background 0.5s ease, transform 0.35s ease;
        animation: qb-in 0.32s cubic-bezier(0.16, 1, 0.3, 1) both;
    }

    .qb-block.qb-just-added {
        border-color: #245E55;
        box-shadow: 0 0 0 4px rgba(36, 94, 85, 0.14), 0 8px 20px rgba(0,0,0,0.06);
        background: #fcfffd;
    }

    @keyframes qb-in {
        from { opacity: 0; transform: translateY(20px) scale(0.985); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    .qb-header {
        display: flex; justify-content: space-between;
        align-items: center; margin-bottom: 14px;
    }

    .qb-num { font-size: 16px; font-weight: 600; color: #555; }

.qb-option {
    display: flex;
    align-items: center;
    gap: 10px;
}

.qb-radio-opt {
    display: flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
    margin: 0;
    flex-shrink: 0;
}

.qb-remove-opt {
    width: 32px !important;
    height: 32px !important;
    padding: 0 !important;
    display: flex !important;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
    .qb-option-letter {
        width: 26px; height: 26px; border-radius: 6px;
        background: #ebebeb; display: flex; align-items: center;
        justify-content: center; font-size: 14px; font-weight: 600;
        color: #666; flex-shrink: 0;
    }

    .qb-correct-row {
        display: flex; align-items: center; gap: 8px;
        margin-top: 14px;
    }
    .qb-correct-label { font-size: 16px; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: 0.05em; margin-right: 4px; }

    .qb-radio-opt {
        display: flex; align-items: center; gap: 4px;
        font-size: 16px; color: #555; cursor: pointer;
    }

    /* File list item adjustments */
    .qc-file-item {
        display: flex; justify-content: space-between; align-items: center;
        padding: 12px; background: #fafafa; border: 1px solid #ebebeb;
        border-radius: 8px; margin-bottom: 6px; font-size: 16px; gap: 16px;
    }

    .qc-file-name { font-weight: 500; color: #333; word-break: break-all; }
    .qc-file-size { font-size: 14px; color: #aaa; margin-top: 1px; }

    .qc-empty { color: #bbb; font-size: 16px; text-align: center; padding: 24px 0; }

    .qc-add-q-bar {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 16px;
    }

    .qc-section-title { font-size: 16px; font-weight: 600; color: #666; }

    .qc-toast {
        position: fixed;
        top: 22px;
        right: 22px;
        z-index: 12000;
        min-width: 240px;
        max-width: min(92vw, 360px);
        padding: 10px 12px;
        border-radius: 10px;
        border: 1px solid #f4b3b2;
        background: #fff4f3;
        color: #7b2220;
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.12);
        font-size: 16px;
        opacity: 0;
        transform: translateY(-6px);
        transition: opacity 0.2s ease, transform 0.2s ease;
        pointer-events: none;
    }

    .qc-toast.show {
        opacity: 1;
        transform: translateY(0);
    }

    .qc-toast.success {
        border-color: #c9e8d4;
        background: #e9f8ef;
        color: #17653f;
    }
    .tb-item {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 12px 14px;
    border: 1px solid #ebebeb;
    border-radius: 10px;
    margin-bottom: 8px;
    background: #fafafa;
}
.tb-item label { cursor: pointer; flex: 1; margin: 0; }
.tb-item .tb-q-text { font-size: 15px; color: #222; font-weight: 500; }
.tb-item .tb-meta { margin-top: 4px; font-size: 13px; color: #888; }
.tb-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 99px;
    background: #eef1ff;
    color: #3a4180;
    font-size: 12px;
    font-weight: 600;
    margin-left: 8px;
}
.qb-from-tb {
    font-size: 12px;
    font-weight: 600;
    color: #3a4180;
    background: #eef1ff;
    padding: 2px 8px;
    border-radius: 99px;
    margin-left: 8px;
}
</style>

<div class="qc-card">
    <div class="qc-meta">
        <div class="qc-meta-icon"><i class="fas fa-clipboard-list"></i></div>
      <div>
            @php
                $isMb = ($isMockBoard ?? false) || ($module->is_mock_board ?? false) || !empty($mockBoard) || \DB::table('mock_board_phases')->where('module_id', $module->id)->exists();
                $stageName = $isMb
                    ? 'Mock Board'
                    : match($module->quiz_stage) {
                        'pre_test' => 'Pre-Test',
                        'post_test' => 'Post-Test',
                        default => ($module->is_formal_assessment ? 'Formal Assessment' : 'Quiz')
                    };
                $stageColor = $isMb
                    ? '#172b4d'
                    : match($module->quiz_stage) {
                        'pre_test' => '#1d9e75',
                        'post_test' => '#7b1fa2',
                        default => ($module->is_formal_assessment ? '#172b4d' : '#5e72e4')
                    };
            @endphp
            <div class="qc-meta-title" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <span>{{ $module->title }}</span>
                <span style="font-size:12px; font-weight:700; text-transform:uppercase; padding:3px 10px; border-radius:99px; background:{{ $stageColor }}; color:#fff; letter-spacing:0.5px;">{{ $stageName }}</span>
            </div>
            <div class="qc-meta-sub">
                @if($class)
                    {{ $class->name }}
                    
                    @php
                        $yr = $class->year_level ?? $class->year ?? null;
                        $yrLabel = match((string)$yr) {
                            '1' => '1st Year',
                            '2' => '2nd Year',
                            '3' => '3rd Year',
                            '4' => '4th Year',
                            default => $yr ? $yr . ' Year' : null
                        };
                    @endphp

                    @if($yrLabel)
                        &bull; {{ $yrLabel }}
                    @endif

                    @if($class->school_year)
                        (S.Y. {{ $class->school_year }})
                    @endif
                @else
                    {{ ($isMockBoard ?? false) ? 'Mock Board Exam' : 'No Class Assigned' }}
                @endif
            </div>
        </div>
    </div>

    <div style="display:flex; align-items:center; gap:10px; padding:12px 14px; background:#fafafa; border:1px solid #ebebeb; border-radius:10px; margin-bottom:20px;">
        <label style="font-size:16px; font-weight:500; color:#666; white-space:nowrap;">Max Attempts (for students):</label>
        <input type="number" id="maxAttemptsInput" min="1" max="20" value="{{ $module->max_attempts ?? 1 }}" class="rv-input" style="width:80px;">
        <button type="button" id="saveMaxAttemptsBtn" class="rv-btn rv-btn-secondary" style="height:34px;font-size:14px;">
            <i class="fas fa-save"></i> Save
        </button>
        <span id="maxAttemptsStatus" style="font-size:14px; color:#1d9e75; display:none;"></span>
    </div>

@if(!($isMockBoard ?? false))
    <div class="qc-tabs">
        <button class="qc-tab {{ $isEditing ? '' : 'active' }}" data-panel="ai">
            <i class="fas fa-robot" style="margin-right:5px;"></i> AI Generator
        </button>
        <button class="qc-tab {{ $isEditing ? 'active' : '' }}" data-panel="manual">
            <i class="fas fa-pen" style="margin-right:5px;"></i> Manual
        </button>
        <button class="qc-tab" data-panel="testbank">
            <i class="fas fa-database" style="margin-right:5px;"></i> Test Bank
        </button>
    </div>
@endif

    {{-- AI Tab (Only rendered if it's NOT a mock board) --}}
    @if(!($isMockBoard ?? false))
        <div class="qc-tab-panel {{ $isEditing ? '' : 'active' }}" id="panel-ai">
            {{-- AI Accuracy Disclaimer Notice --}}
            <div style="background:#FFF9F2; border:1px solid #FFE2C6; border-left:4px solid #ED773C; border-radius:8px; padding:12px 16px; margin-bottom:20px; display:flex; align-items:flex-start; gap:12px;">
                <i class="fas fa-exclamation-triangle" style="color:#ED773C; font-size:16px; margin-top:2px; flex-shrink:0;"></i>
                <div style="font-size:14px; color:#6B4B32; line-height:1.5;">
                    <strong style="display:block; margin-bottom:2px; color:#B44615;">AI Generation Disclaimer</strong>
                    AI-generated assessment content is an assistive drafting tool and is not 100% accurate. Please review, verify, and edit all questions, choices, and answer keys carefully before publishing this quiz to students.
                </div>
            </div>

            <form id="aiQuizForm" method="POST" action="{{ route('quiz.generate', $module) }}" enctype="multipart/form-data">
                @csrf
<div class="qc-row" style="grid-template-columns: 1fr;">
    <div>
        <label class="qc-label">Choices per Question</label>
        <input type="number" name="choice_count" class="rv-input" min="2" max="10" value="4" placeholder="4" style="max-width:200px;">
    </div>
</div>

                <div class="qc-row-single">
                    <label class="qc-label">Additional Instructions (Optional)</label>
                    <textarea name="extra_instructions" class="rv-textarea" rows="2" placeholder="e.g. Focus on higher order thinking skills (HOTS), avoid pure recall questions"></textarea>
                </div>

                <div id="aiDisabledNotice" class="qc-toast show" style="position:static;margin-top:8px;transform:none;pointer-events:auto;{{ (isset($isAiQuizGenerationEnabled) && ! $isAiQuizGenerationEnabled) ? '' : 'display:none;' }}">
                    AI quiz generation is currently disabled for this class.
                </div>

                <div class="qc-row-single" style="margin-top:16px;">
                    <label class="qc-label">Upload PDFs / Documents & Set Target Question Balance</label>
                    <div style="display:flex;gap:8px;align-items:center;margin-bottom:12px;">
                        <input type="file" id="fileInput" style="display:none;" accept=".pdf,.docx,.txt" multiple>
                        <button type="button" id="addFilesBtn" class="rv-btn rv-btn-secondary">
                            <i class="fas fa-paperclip"></i> Attach Files
                        </button>
                        <span style="font-size: 16px;color:#aaa;">PDF, DOC, DOCX, TXT supported</span>
                    </div>
                    
                    <div id="selectedFilesList"></div>
                </div>

                <button type="submit" id="aiSubmitBtn" class="rv-btn rv-btn-primary" style="margin-top:14px;" {{ (isset($isAiQuizGenerationEnabled) && ! $isAiQuizGenerationEnabled) ? 'disabled' : '' }}>
                    <i class="fas fa-robot"></i> Generate with AI
                </button>
                <p style="font-size:13px; color:#888; margin-top:8px; display:flex; align-items:center; gap:6px;">
                    <i class="fas fa-info-circle"></i>
                    <span>Generated questions will load below for teacher verification and editing before saving.</span>
                </p>
            </form>
        </div>
    @endif
    {{-- Test Bank Tab --}}
@if(!($isMockBoard ?? false))
<div class="qc-tab-panel" id="panel-testbank">
    <div class="qc-row" style="grid-template-columns: 1fr 160px auto; gap: 10px; margin-bottom: 16px;">
        <div>
            <input type="text" id="tbSearch" class="rv-input" placeholder="Search questions...">
        </div>
        <div>
        <select id="tbDifficulty" class="rv-input">
            <option value="">All difficulties</option>
            <option value="Easy">Easy</option>
            <option value="Average">Average</option>
            <option value="Difficult">Difficult</option>
        </select>
        </div>
        <button type="button" id="tbFilterBtn" class="rv-btn rv-btn-secondary">
            <i class="fas fa-search"></i> Filter
        </button>
    </div>

    <div class="qc-add-q-bar">
        <span class="qc-section-title" id="tbSelectedLabel">0 selected</span>
        <button type="button" id="tbAddSelectedBtn" class="rv-btn rv-btn-primary" style="height:34px;font-size:16px;" disabled>
            <i class="fas fa-plus"></i> Add selected to Manual
        </button>
    </div>

    <div id="tbList" style="max-height: 420px; overflow-y: auto;">
        <div class="qc-empty">Click Filter to load Test Bank questions.</div>
    </div>
</div>
@endif
    {{-- Manual Tab (Always visible and active instantly for Mock Boards) --}}
    <div class="qc-tab-panel {{ ($isEditing || ($isMockBoard ?? false)) ? 'active' : '' }}" id="panel-manual">
        <div class="qc-add-q-bar">
            <span class="qc-section-title" id="qCountLabel">0 question(s)</span>
            <button type="button" id="addQuestionBtn" class="rv-btn rv-btn-primary" style="height:34px;font-size: 16px;">
                <i class="fas fa-plus"></i> Add Question
            </button>
        </div>

        <form id="manualQuizForm" method="POST" action="{{ route('quiz.store', $module) }}">
            @csrf
            <div id="questionsContainer"></div>
            <div id="emptyState" class="qc-empty">
                <i class="fas fa-clipboard" style="font-size:24px;margin-bottom:8px;display:block;"></i>
                No questions yet. Click "Add Question" to start.
            </div>
            
            {{-- Shuffle Option --}}
            <div style="margin: 16px 0; padding: 12px 16px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 15px; color: #495057;">
                    <input type="checkbox" name="shuffle_questions" value="1" style="width: 18px; height: 18px; cursor: pointer;">
                    <span><i class="fas fa-random" style="margin-right: 6px; color: #6c757d;"></i> Shuffle question order when saving</span>
                </label>
                <p style="margin: 6px 0 0 28px; font-size: 13px; color: #6c757d;">Questions will be randomly reordered before saving to the database.</p>
            </div>
            
            <button type="submit" class="rv-btn rv-btn-primary" style="width:100%;margin-top:8px;" id="saveQuestionsBtn">
                <i class="fas fa-save"></i> {{ $isEditing ? ($isAssessment ? 'Update Exam Questions' : 'Update Questions') : ($isAssessment ? 'Save Exam Questions' : 'Save All Questions') }}
            </button>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    let qcToastTimer = null;
    const isMockBoard = {{ ($isMockBoard ?? false) ? 'true' : 'false' }};
    const loadedTestBankIds = new Set();

    // Max Attempts save (formal assessments lang)
    const saveMaxAttemptsBtn = document.getElementById('saveMaxAttemptsBtn');
    if (saveMaxAttemptsBtn) {
        saveMaxAttemptsBtn.addEventListener('click', function () {
            const input = document.getElementById('maxAttemptsInput');
            const status = document.getElementById('maxAttemptsStatus');
            const value = parseInt(input.value, 10) || 1;

            saveMaxAttemptsBtn.disabled = true;

            fetch('{{ route('quiz.max-attempts.update', $module) }}', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ max_attempts: value }),
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        status.textContent = 'Saved!';
                        status.style.display = 'inline';
                        setTimeout(() => { status.style.display = 'none'; }, 2000);
                    } else {
                        alert(data.message || 'Failed to save max attempts.');
                    }
                })
                .catch(() => alert('An error occurred while saving max attempts.'))
                .finally(() => { saveMaxAttemptsBtn.disabled = false; });
        });
    }

    function showQuizCreateToast(message, type) {
        type = type || 'error';
        let toast = document.getElementById('qcToast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'qcToast';
            toast.className = 'qc-toast';
            document.body.appendChild(toast);
        }

        toast.classList.remove('success');
        if (type === 'success') {
            toast.classList.add('success');
        }
        toast.textContent = message;
        toast.classList.add('show');

        if (qcToastTimer) {
            clearTimeout(qcToastTimer);
        }
        qcToastTimer = setTimeout(function () {
            toast.classList.remove('show');
        }, 2800);
    }

    const existingQuestions = @json($existingQuestions->values());
    const classId = {{ $class?->id ?? 'null' }};
    const serverQuizSettings = {
        quiz_defaults: {
            question_count: {{ (int) ($classQuizDefaults['question_count'] ?? max($existingQuestions->count(), 5)) }},
            difficulty: @json((string) ($classQuizDefaults['difficulty'] ?? 'Average')),
        },
        features: {
            quiz_generation_enabled: {{ (isset($isAiQuizGenerationEnabled) && ! $isAiQuizGenerationEnabled) ? 'false' : 'true' }},
        },
    };

    function applyAiSettingsToForm(settings) {
        if (isMockBoard || !settings) { return; }

        const quizDefaults = settings.quiz_defaults || {};
        const features = settings.features || {};
        const aiSubmitBtn = document.getElementById('aiSubmitBtn');
        const aiDisabledNotice = document.getElementById('aiDisabledNotice');

 

        if (Object.prototype.hasOwnProperty.call(features, 'quiz_generation_enabled')) {
            const isEnabled = Boolean(features.quiz_generation_enabled);
            if (aiSubmitBtn) { aiSubmitBtn.disabled = !isEnabled; }
            if (aiDisabledNotice) { aiDisabledNotice.style.display = isEnabled ? 'none' : ''; }
        }
    }

    function syncAiSettingsFromStorage() {
        if (isMockBoard) return;
        try {
            const raw = localStorage.getItem(`aiSettings.class.${classId}`);
            if (!raw) { return; }
            const parsed = JSON.parse(raw);
            if (Number(parsed.class_id) !== Number(classId)) { return; }
            applyAiSettingsToForm(parsed.settings || {});
        } catch (_) {}
    }

    applyAiSettingsToForm(serverQuizSettings);
    syncAiSettingsFromStorage();
    window.addEventListener('pageshow', function () {
        applyAiSettingsToForm(serverQuizSettings);
        syncAiSettingsFromStorage();
    });
    window.addEventListener('storage', function (event) {
        if (event.key === `aiSettings.class.${classId}` || event.key === 'aiSettings.lastUpdateAt') {
            syncAiSettingsFromStorage();
        }
    });

    // ── Tabs Switching ──
    if (!isMockBoard) {
        document.querySelectorAll('.qc-tab').forEach(tab => {
            tab.addEventListener('click', function () {
                document.querySelectorAll('.qc-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.qc-tab-panel').forEach(p => p.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('panel-' + this.dataset.panel).classList.add('active');
            });
        });
    }

    // ── Manual Setup Engine ──
    let questionCount = 0;

    function updateCountLabel() {
        const n = document.querySelectorAll('.qb-block').length;
        document.getElementById('qCountLabel').textContent = n + ' question(s)';
        document.getElementById('emptyState').style.display = n === 0 ? 'block' : 'none';
        document.getElementById('saveQuestionsBtn').style.display = n === 0 ? 'none' : 'block';
    }

    window.addQuestionBlock = function (text = '', options = {A:'',B:'',C:'',D:''}, correct = 'A', testBankId = null, shouldScroll = false) {
        questionCount++;
        const qn = questionCount;
        const block = document.createElement('div');
        block.className = 'qb-block';
        block.id = 'q' + qn;
        if (testBankId) {
            block.dataset.testBankId = testBankId;
            loadedTestBankIds.add(String(testBankId));
        }

        const optionKeys = Object.keys(options).length ? Object.keys(options) : ['A', 'B'];
        const tbBadge = testBankId ? '<span class="qb-from-tb">From Test Bank</span>' : '';

        block.innerHTML = `
            <div class="qb-header">
                <span class="qb-num">Question ${qn}${tbBadge}</span>
                <button type="button" class="rv-btn rv-btn-danger" style="height:28px;padding:0 10px;font-size: 16px;" onclick="removeQuestion('q${qn}')">
                    <i class="fas fa-trash"></i> Remove
                </button>
            </div>
            ${testBankId ? `<input type="hidden" name="questions[${qn}][test_bank_question_id]" value="${testBankId}">` : ''}
            <label class="qc-label">Question Text</label>
            <textarea name="questions[${qn}][text]" class="rv-textarea" rows="3" placeholder="Enter question..." required>${text}</textarea>

            <div class="qb-options-list" id="options-${qn}" style="margin-top:12px;display:flex;flex-direction:column;gap:8px;"></div>

            <button type="button" class="rv-btn rv-btn-secondary" style="margin-top:8px;height:32px;font-size:14px;" onclick="addOption(${qn})">
                <i class="fas fa-plus"></i> Add Choice
            </button>

            <div class="qb-correct-row" id="correct-row-${qn}">
                <span class="qb-correct-label">Correct:</span>
            </div>
            <input type="hidden" name="questions[${qn}][points]" value="1">
        `;
        document.getElementById('questionsContainer').appendChild(block);

        optionKeys.forEach(key => addOption(qn, key, options[key] || '', correct === key));

        updateCountLabel();

        if (shouldScroll) {
            block.classList.add('qb-just-added');
            setTimeout(() => {
                block.scrollIntoView({ behavior: 'smooth', block: 'center' });
                const textarea = block.querySelector('textarea');
                if (textarea) {
                    setTimeout(() => textarea.focus(), 320);
                }
                setTimeout(() => {
                    block.classList.remove('qb-just-added');
                }, 1800);
            }, 60);
        }
    };

    function nextOptionLetter(qn) {
        const existing = document.querySelectorAll(`#options-${qn} .qb-option`).length;
        let n = existing;
        let letter = '';
        do {
            letter = String.fromCharCode(65 + (n % 26)) + letter;
            n = Math.floor(n / 26) - 1;
        } while (n >= 0);
        return letter;
    }

    window.addOption = function (qn, key = null, value = '', isCorrect = false) {
        const optionsList = document.getElementById(`options-${qn}`);
        const currentCount = optionsList.querySelectorAll('.qb-option').length;

        if (currentCount >= 10) {
            alert('A maximum of 10 choices is allowed per question.');
            return;
        }

        const letter = key || nextOptionLetter(qn);
        const optDiv = document.createElement('div');
        optDiv.className = 'qb-option';
        optDiv.dataset.key = letter;
        optDiv.style = 'display:flex;align-items:center;gap:8px;';
        optDiv.innerHTML = `
            <div class="qb-option-letter">${letter}</div>
            <input type="text" name="questions[${qn}][options][${letter}]" class="rv-input" placeholder="Option ${letter}" value="${value}" required style="flex:1; min-width:0;">
            <label class="qb-radio-opt">
                <input type="radio" name="questions[${qn}][correct]" value="${letter}" ${isCorrect ? 'checked' : ''}> Correct
            </label>
            <button type="button" class="rv-btn rv-btn-danger qb-remove-opt" onclick="removeOption(${qn}, this)">
                <i class="fas fa-times"></i>
            </button>
        `;
        optionsList.appendChild(optDiv);

        if (optionsList.querySelectorAll('.qb-option').length === 1) {
            optDiv.querySelector('input[type="radio"]').checked = true;
        }
    };

    window.removeOption = function (qn, btn) {
        const optionsList = document.getElementById(`options-${qn}`);
        if (optionsList.querySelectorAll('.qb-option').length <= 2) {
            alert('A minimum of 2 choices is required per question.');
            return;
        }
        const wasChecked = btn.closest('.qb-option').querySelector('input[type="radio"]').checked;
        btn.closest('.qb-option').remove();

        if (wasChecked) {
            const firstRadio = optionsList.querySelector('input[type="radio"]');
            if (firstRadio) firstRadio.checked = true;
        }
    };

    window.removeQuestion = function (id) {
        const el = document.getElementById(id);
        if (el) {
            const tbId = el.dataset.testBankId;
            if (tbId) loadedTestBankIds.delete(String(tbId));
            el.remove();
        }
        updateCountLabel();
    };

    document.getElementById('addQuestionBtn').addEventListener('click', () => {
        addQuestionBlock('', { A: '', B: '', C: '', D: '' }, 'A', null, true);
    });

    if (existingQuestions.length) {
        existingQuestions.forEach(question => {
            addQuestionBlock(
                question.text || '',
                question.options || {A:'',B:'',C:'',D:''},
                question.correct || 'A',
                question.test_bank_question_id || null
            );
        });
    }

    updateCountLabel();

    // ── Test Bank Tab ──
    const tbList = document.getElementById('tbList');
    const tbSearch = document.getElementById('tbSearch');
    const tbDifficulty = document.getElementById('tbDifficulty');
    const tbFilterBtn = document.getElementById('tbFilterBtn');
    const tbAddSelectedBtn = document.getElementById('tbAddSelectedBtn');
    const tbSelectedLabel = document.getElementById('tbSelectedLabel');

    function updateTbSelectedLabel() {
        if (!tbList) return;
        const n = tbList.querySelectorAll('input[name="tb_pick"]:checked').length;
        tbSelectedLabel.textContent = n + ' selected';
        tbAddSelectedBtn.disabled = n === 0;
    }

    function renderTbList(questions) {
        if (!questions.length) {
            tbList.innerHTML = '<div class="qc-empty">No Test Bank questions found.</div>';
            updateTbSelectedLabel();
            return;
        }

        tbList.innerHTML = questions.map(q => {
            const already = loadedTestBankIds.has(String(q.id));
            const preview = (q.question_text || '').length > 140
                ? (q.question_text || '').slice(0, 140) + '…'
                : (q.question_text || '');
            const keys = q.options ? Object.keys(q.options).join(', ') : '';
            return `
                <div class="tb-item">
                    <input type="checkbox" name="tb_pick" value="${q.id}"
                        data-question='${JSON.stringify(q).replace(/'/g, '&#39;')}'
                        ${already ? 'disabled' : ''}>
                    <label>
                        <div class="tb-q-text">
                            ${preview}
                            ${already ? '<span class="tb-badge">Already added</span>' : ''}
                        </div>
                        <div class="tb-meta">
                            ${q.difficulty || '—'} · Choices: ${keys || '—'}
                        </div>
                    </label>
                </div>
            `;
        }).join('');

        tbList.querySelectorAll('input[name="tb_pick"]').forEach(cb => {
            cb.addEventListener('change', updateTbSelectedLabel);
        });
        updateTbSelectedLabel();
    }

    function loadTestBankQuestions() {
        if (!tbList) return;
        tbList.innerHTML = '<div class="qc-empty"><i class="fas fa-spinner fa-spin"></i> Loading…</div>';

        const params = new URLSearchParams();
        if (tbSearch && tbSearch.value.trim()) params.set('search', tbSearch.value.trim());
        if (tbDifficulty && tbDifficulty.value) params.set('difficulty', tbDifficulty.value);

        fetch(`{{ route('test-bank.questions.json') }}?${params.toString()}`, {
            headers: { 'Accept': 'application/json' },
        })
        .then(r => r.json())
        .then(payload => renderTbList(payload.data || []))
        .catch(() => {
            tbList.innerHTML = '<div class="qc-empty">Failed to load Test Bank questions.</div>';
        });
    }

    if (tbFilterBtn) {
        tbFilterBtn.addEventListener('click', loadTestBankQuestions);

        document.querySelector('.qc-tab[data-panel="testbank"]')?.addEventListener('click', function () {
            if (tbList && tbList.dataset.loaded !== '1') {
                tbList.dataset.loaded = '1';
                loadTestBankQuestions();
            }
        });
    }

    if (tbAddSelectedBtn) {
        tbAddSelectedBtn.addEventListener('click', function () {
            const checked = tbList.querySelectorAll('input[name="tb_pick"]:checked');
            if (!checked.length) return;

            let added = 0;
            checked.forEach(cb => {
                if (cb.disabled) return;
                let q;
                try { q = JSON.parse(cb.getAttribute('data-question')); } catch (_) { return; }
                if (!q || loadedTestBankIds.has(String(q.id))) return;

                addQuestionBlock(
                    q.question_text || '',
                    q.options || { A: '', B: '' },
                    q.correct_option || 'A',
                    q.id
                );
                loadedTestBankIds.add(String(q.id));
                added++;
            });

            document.querySelector('.qc-tab[data-panel="manual"]')?.click();

            if (added > 0) {
                setTimeout(() => {
                    const lastBlock = document.querySelector('.qb-block:last-child');
                    if (lastBlock) {
                        lastBlock.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }, 100);
                showQuizCreateToast(added + ' question(s) added from Test Bank. Review then Save.', 'success');
            } else {
                showQuizCreateToast('No new questions added (already on the form).', 'error');
            }

            loadTestBankQuestions();
        });
    }

    // ── Balanced File Upload Setup ──
    const aiForm = document.getElementById('aiQuizForm');
    if (aiForm) {
        const fileInput    = document.getElementById('fileInput');
        const addFilesBtn  = document.getElementById('addFilesBtn');
        const fileListDiv  = document.getElementById('selectedFilesList');
        let selectedFiles  = [];

        addFilesBtn.addEventListener('click', () => fileInput.click());

fileInput.addEventListener('change', function () {
    Array.from(this.files).forEach(file => {
        if (!selectedFiles.some(f => f.name === file.name && f.size === file.size)) {
            file.difficultyCounts = { Easy: 0, Average: 5, Difficult: 0 };
            selectedFiles.push(file);
        }
    });
    fileInput.value = '';
    renderFileList();
});
function renderFileList() {
    fileListDiv.innerHTML = '';
    if (selectedFiles.length === 0) { return; }

    const tiers = ['Easy', 'Average', 'Difficult'];

    selectedFiles.forEach((file, i) => {
        if (!file.difficultyCounts) {
            file.difficultyCounts = { Easy: 0, Average: 5, Difficult: 0 };
        }

        const total = tiers.reduce((sum, t) => sum + (parseInt(file.difficultyCounts[t], 10) || 0), 0);

        const tierInputs = tiers.map(t => `
            <label style="font-size:13px;color:#666;font-weight:500;white-space:nowrap;margin:0;">${t}:</label>
            <input type="number" class="rv-input tier-count-input" style="width:68px;height:34px;padding:4px 6px;text-align:center;margin:0;" min="0" max="100" value="${file.difficultyCounts[t]}" data-index="${i}" data-tier="${t}">
        `).join('');

        const div = document.createElement('div');
        div.className = 'qc-file-item';
        div.style = 'flex-wrap:wrap;';
        div.innerHTML = `
            <div style="flex-grow:1;min-width:160px;">
                <div class="qc-file-name"><i class="fas fa-file-alt" style="color:#888;margin-right:6px;"></i>${file.name}</div>
                <div class="qc-file-size">${(file.size/1024).toFixed(1)} KB &bull; <span class="file-total-label" data-index="${i}">${total} question(s) total</span></div>
            </div>
            <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;flex-wrap:wrap;">
                ${tierInputs}
            </div>
            <button type="button" class="rv-btn rv-btn-danger" style="height:34px;width:34px;padding:0;display:flex;align-items:center;justify-content:center;flex-shrink:0;" data-index="${i}">
                <i class="fas fa-times"></i>
            </button>`;

        div.querySelectorAll('.tier-count-input').forEach(input => {
            input.addEventListener('input', function () {
                const idx = parseInt(this.dataset.index, 10);
                const tier = this.dataset.tier;
                selectedFiles[idx].difficultyCounts[tier] = parseInt(this.value, 10) || 0;

                const newTotal = tiers.reduce((sum, t) => sum + (parseInt(selectedFiles[idx].difficultyCounts[t], 10) || 0), 0);
                const label = div.querySelector('.file-total-label');
                if (label) { label.textContent = newTotal + ' question(s) total'; }
            });
        });

        div.querySelector('button').addEventListener('click', function () {
            selectedFiles.splice(parseInt(this.dataset.index, 10), 1);
            renderFileList();
        });

        fileListDiv.appendChild(div);
    });
}

        aiForm.addEventListener('submit', function (e) {
            e.preventDefault();

            if (selectedFiles.length === 0) {
                showQuizCreateToast('Please attach at least one file before generating.', 'error');
                return;
            }

            const btn = document.getElementById('aiSubmitBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';

            const formData = new FormData(this);

selectedFiles.forEach((file, index) => {
    formData.append('context_files[' + index + ']', file);
    ['Easy', 'Average', 'Difficult'].forEach(tier => {
        formData.append(
            'file_difficulty_counts[' + index + '][' + tier + ']',
            (file.difficultyCounts && file.difficultyCounts[tier]) || 0
        );
    });
});

           fetch(this.action, {
    method: 'POST',
    headers: {
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Accept': 'application/json',
    },
    body: formData,
})
            .then(async r => {
                const data = await r.json().catch(() => ({}));
                if (!r.ok || data.success === false) {
                    throw new Error(data.message || ('Server error (HTTP ' + r.status + ')'));
                }
                return data;
            })
            .then(response => {
                if (response.success && response.questions) {
                    document.getElementById('questionsContainer').innerHTML = '';
                    questionCount = 0;
                    loadedTestBankIds.clear();
                    response.questions.forEach(q =>
                        addQuestionBlock(
                            q.question_text || q.question || '',
                            q.options || { A: '', B: '', C: '', D: '' },
                            q.correct || q.correct_option || 'A'
                        )
                    );
                    document.querySelector('[data-panel="manual"]').click();
                    showQuizCreateToast(response.message || 'Questions generated successfully!', 'success');
                } else {
                    showQuizCreateToast(response.message || 'No questions returned.', 'error');
                }
            })
            .catch(err => showQuizCreateToast(err.message || 'Failed to generate questions. Verify files are readable text content.', 'error'))
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-robot"></i> Generate with AI';
            });
        });
    }
});
</script>
@endsection