@extends('layouts.appAdmin')

@section('title', 'Mock Board Approvals')

@section('page-heading')
    Mock Board <span style="color: #245E55;">Approvals</span>
@endsection

@section('content')
<div class="mock-boards-container">

    {{-- SUCCESS ALERT --}}
    @if(session('success'))
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
        </div>
    @endif

    {{-- STATUS FILTER TABS --}}
    <div class="status-tabs">
        @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $key => $label)
            <a href="{{ route('admin.mock-boards.approvals', ['status' => $key]) }}"
               class="status-tab {{ $selectedStatus === $key ? 'active' : '' }} tab-{{ $key }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- MOCK BOARDS LIST --}}
    @if($mockBoards->isEmpty())
        <div class="empty-state">
            <i class="fas fa-clipboard-check" style="font-size: 56px; color: #8a8580; margin-bottom: 16px;"></i>
            <h3>No {{ ucfirst($selectedStatus) }} Mock Boards</h3>
            <p>There are currently no mock boards with this status.</p>
        </div>
    @else
        <div class="mock-boards-list">
            @foreach($mockBoards as $board)
                <div class="mock-board-card">
                    <div class="board-header">
                        <div>
                            <h3 style="margin:0; font-size: 1.25rem; color: #1a202c;">{{ $board->title ?? 'Untitled Mock Board' }}</h3>
                            <span style="font-size: 12px; color: #718096; text-transform: uppercase; font-weight: bold;">
                                Program: {{ $board->program ?? 'N/A' }} &middot; Teacher: {{ $board->teacher->name ?? 'Unknown' }}
                            </span>
                        </div>
                        <span class="status-badge {{ $board->status }}">
                            {{ ucfirst($board->status) }}
                        </span>
                    </div>

                    @if($board->status === 'rejected' && $board->rejection_reason)
                        <div style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:10px 14px; border-radius:8px; font-size:13px; margin-bottom:14px;">
                            <strong>Rejection Reason:</strong> {{ $board->rejection_reason }}
                        </div>
                    @endif

                    <p class="board-description">{{ $board->description ?? 'No description provided.' }}</p>

                    <div class="board-meta">
                        @if($board->review_period_start && $board->review_period_end)
                            <span><i class="fas fa-calendar-alt"></i> {{ $board->review_period_start->format('M d') }} - {{ $board->review_period_end->format('M d, Y') }}</span>
                        @endif
                        <span><i class="fas fa-bullseye"></i> Passing: {{ $board->passing_percentage ?? 75 }}%</span>
                        <span>
                            <i class="fas fa-layer-group"></i>
                            Phases:
                            @forelse($board->phases as $phase)
                                {{ ucfirst(str_replace('_', ' ', $phase->phase_type)) }}{{ !$loop->last ? ',' : '' }}
                            @empty
                                None yet
                            @endforelse
                        </span>
                    </div>

                    <div class="board-actions">
                        <button type="button" class="rv-btn rv-btn-secondary" onclick="openQuestionsModal({{ $board->id }})">
                            <i class="fas fa-list-ol"></i> View Questions
                        </button>

                        @if($selectedStatus === 'pending')
                            <form action="{{ route('admin.mock-boards.approve', $board) }}" method="POST" style="display:inline;">
                                @csrf
                                <button type="submit" class="rv-btn rv-btn-primary">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                            </form>

                            <button class="rv-btn rv-btn-danger-outline" onclick="openRejectModal({{ $board->id }})">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>

{{-- Questions Preview Modal --}}
<div id="questionsModal" class="modal">
    <div class="modal-content qm-modal-content">
        {{-- Modal Header --}}
        <div class="qm-header">
            <div>
                <h3 id="qmBoardTitle" style="margin: 0; color: #1a202c; font-size: 1.35rem; font-weight: 700;">Mock Board Questions</h3>
                <div id="qmBoardMeta" class="qm-meta-text"></div>
            </div>
            <button type="button" class="qm-close-btn" onclick="closeQuestionsModal()">&times;</button>
        </div>

        {{-- Loading State --}}
        <div id="qmLoading" style="text-align: center; padding: 60px 20px; color: #64748b;">
            <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: #245E55; margin-bottom: 12px;"></i>
            <p style="margin: 0; font-size: 14px;">Loading questions and phase details...</p>
        </div>

        {{-- Modal Content State --}}
        <div id="qmContent" style="display: none; flex-direction: column; flex: 1; min-height: 0; overflow: hidden;">
            {{-- Phase Tabs --}}
            <div class="qm-tabs-bar" id="qmPhaseTabs"></div>

            {{-- Questions Scroll Area --}}
            <div class="qm-questions-scroll" id="qmQuestionsList"></div>

            {{-- Modal Footer --}}
            <div class="qm-footer">
                <div id="qmFooterActions" style="display: flex; gap: 8px;"></div>
                <button type="button" class="rv-btn rv-btn-secondary" onclick="closeQuestionsModal()">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Reject Modal --}}
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
            <h3 style="margin: 0; color: #1a202c; font-size: 1.5rem;">Reject Mock Board</h3>
            <span style="font-size: 24px; cursor: pointer; color: #a0aec0;" onclick="closeRejectModal()">&times;</span>
        </div>
        <form id="rejectForm" method="POST">
            @csrf
            <div class="modal-form-group">
                <label class="modal-label">Reason (Optional)</label>
                <textarea name="rejection_reason" class="modal-input" rows="3" placeholder="Let the teacher know why this was rejected..."></textarea>
            </div>
            <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="rv-btn rv-btn-secondary" onclick="closeRejectModal()">Cancel</button>
                <button type="submit" class="rv-btn rv-btn-danger">Confirm Reject</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('head')
<style>
    .mock-boards-container { max-width: 1100px; padding: 20px; margin: 0 auto; }

    .status-tabs { display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 1px solid #e2e8f0; }
    .status-tab {
        padding: 10px 18px; font-size: 14px; font-weight: 600; color: #718096;
        text-decoration: none; border-bottom: 3px solid transparent; margin-bottom: -1px;
    }
    .status-tab.active.tab-pending { color: #92400e; border-color: #f59e0b; }
    .status-tab.active.tab-approved { color: #15803d; border-color: #22c55e; }
    .status-tab.active.tab-rejected { color: #b91c1c; border-color: #ef4444; }

    .empty-state { text-align: center; padding: 60px 20px; color: #5a5550; background: #fff; border-radius: 12px; border: 2px dashed #e2e8f0; }

    .mock-boards-list { display: flex; flex-direction: column; gap: 20px; }
    .mock-board-card {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    .board-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
    .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
    .status-badge.approved { background: #dcfce7; color: #15803d; }
    .status-badge.pending { background: #fef3c7; color: #92400e; }
    .status-badge.rejected { background: #fee2e2; color: #b91c1c; }
    .board-description { color: #4a5568; margin-bottom: 16px; line-height: 1.5; font-size: 14px; }

    .board-meta { display: flex; gap: 25px; font-size: 13px; color: #718096; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #f1f5f9; flex-wrap: wrap; }
    .board-meta i { color: #245E55; margin-right: 6px; }
    .board-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }

    .rv-btn-primary { background: #245E55; color: white; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
    .rv-btn-primary:hover { background: #1b4740; }
    .rv-btn-secondary { background: #f8fafc; border: 1px solid #cbd5e1; color: #475569; font-weight: 600; padding: 8px 16px; border-radius: 8px; font-size: 14px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
    .rv-btn-secondary:hover { background: #f1f5f9; }
    .rv-btn-danger { background: #c53030; color: #fff; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
    .rv-btn-danger-outline { background: #fff5f5; border: 1px solid #feb2b2; color: #c53030; padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
    .rv-btn-danger-outline:hover { background: #c53030; color: white; }
    .alert-success { background: #dcfce7; color: #15803d; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
    .alert-error { background: #fee2e2; color: #b91c1c; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }

    .modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); display: none; align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(4px); }
    .modal-content { background: #fff; border-radius: 15px; padding: 30px; max-width: 500px; width: 95%; }
    .modal-form-group { margin-bottom: 14px; }
    .modal-label { display: block; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #475569; margin-bottom: 5px; letter-spacing: 0.5px; }
    .modal-input { width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box; }

    /* Questions Modal Specifics */
    .qm-modal-content {
        max-width: 880px; width: 95%; height: 88vh; padding: 0;
        display: flex; flex-direction: column; overflow: hidden; border-radius: 16px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }
    .qm-header {
        display: flex; justify-content: space-between; align-items: flex-start;
        padding: 20px 24px; border-bottom: 1px solid #e2e8f0; background: #fff; flex-shrink: 0;
    }
    .qm-meta-text { font-size: 13px; color: #64748b; margin-top: 4px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
    .qm-meta-text i { color: #245E55; }
    .qm-close-btn { background: none; border: none; font-size: 26px; line-height: 1; color: #94a3b8; cursor: pointer; padding: 0; }
    .qm-close-btn:hover { color: #1e293b; }

    .qm-tabs-bar {
        display: flex; gap: 8px; padding: 12px 24px; background: #f8fafc;
        border-bottom: 1px solid #e2e8f0; overflow-x: auto; flex-shrink: 0;
    }
    .qm-tab-btn {
        display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px;
        border-radius: 8px; border: 1px solid #cbd5e1; background: #fff;
        color: #475569; font-size: 13.5px; font-weight: 600; cursor: pointer; transition: all 0.15s;
        white-space: nowrap;
    }
    .qm-tab-btn:hover { background: #f1f5f9; border-color: #94a3b8; }
    .qm-tab-btn.active {
        background: #245E55; color: #fff; border-color: #245E55;
    }
    .qm-tab-badge {
        font-size: 11px; padding: 2px 6px; border-radius: 99px; background: rgba(0,0,0,0.08); color: inherit;
    }
    .qm-tab-btn.active .qm-tab-badge { background: rgba(255,255,255,0.25); color: #fff; }

    .qm-phase-summary-bar {
        display: flex; gap: 20px; padding: 10px 16px; background: #f1f5f9;
        border-radius: 8px; font-size: 13px; color: #334155; margin-bottom: 16px;
        flex-wrap: wrap; border-left: 4px solid #245E55;
    }

    .qm-questions-scroll {
        flex: 1; overflow-y: auto; padding: 20px 24px; display: flex; flex-direction: column; gap: 16px;
        background: #fafafa;
    }
    .qm-question-card {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
        padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    }
    .qm-q-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
    .qm-q-num { font-weight: 700; color: #1e293b; font-size: 14px; }
    .qm-q-tags { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
    .qm-tag { font-size: 11.5px; padding: 2px 8px; border-radius: 6px; font-weight: 600; }
    .qm-tag.domain { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
    .qm-tag.difficulty.easy { background: #dcfce7; color: #15803d; }
    .qm-tag.difficulty.medium { background: #fef3c7; color: #b45309; }
    .qm-tag.difficulty.hard { background: #fee2e2; color: #b91c1c; }
    .qm-tag.points { background: #f1f5f9; color: #475569; }

    .qm-q-text { font-size: 15px; color: #1e293b; line-height: 1.55; margin-bottom: 14px; font-weight: 500; }
    .qm-options-list { display: flex; flex-direction: column; gap: 8px; }
    .qm-option-item {
        display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 8px;
        border: 1px solid #e2e8f0; background: #fff; font-size: 14px; color: #334155;
    }
    .qm-option-item.correct {
        border-color: #86efac; background: #f0fdf4; color: #14532d; font-weight: 600;
    }
    .qm-opt-letter {
        width: 24px; height: 24px; border-radius: 6px; display: flex; align-items: center;
        justify-content: center; font-size: 12px; font-weight: 700; background: #e2e8f0; color: #475569;
        flex-shrink: 0;
    }
    .qm-option-item.correct .qm-opt-letter {
        background: #22c55e; color: #fff;
    }
    .qm-opt-text { flex: 1; min-width: 0; }
    .qm-correct-badge {
        font-size: 12px; font-weight: 700; color: #15803d; background: #dcfce7;
        padding: 3px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;
        flex-shrink: 0;
    }

    .qm-explanation-box {
        margin-top: 12px; padding: 10px 14px; background: #eff6ff; border: 1px solid #bfdbfe;
        border-radius: 8px; color: #1e40af; font-size: 13px;
    }

    .qm-footer {
        padding: 14px 24px; border-top: 1px solid #e2e8f0; background: #fff;
        display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;
    }
</style>
@endsection

@section('scripts')
<script>
    let currentBoardData = null;
    let activePhaseIndex = 0;

    function openQuestionsModal(boardId) {
        const modal = document.getElementById('questionsModal');
        const loadingState = document.getElementById('qmLoading');
        const contentState = document.getElementById('qmContent');
        const titleEl = document.getElementById('qmBoardTitle');
        const metaEl = document.getElementById('qmBoardMeta');
        const actionsEl = document.getElementById('qmFooterActions');
        
        modal.style.display = 'flex';
        loadingState.style.display = 'block';
        contentState.style.display = 'none';
        
        fetch(`/admin/mock-boards/${boardId}/questions`, {
            headers: { 
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.mock_board) {
                loadingState.innerHTML = '<div style="text-align:center;color:#ef4444;padding:30px;"><i class="fas fa-exclamation-circle" style="font-size:32px;"></i><p style="margin-top:10px;">Failed to load questions.</p></div>';
                return;
            }
            
            currentBoardData = data.mock_board;
            activePhaseIndex = 0;
            
            titleEl.textContent = currentBoardData.title || 'Mock Board Questions';
            metaEl.innerHTML = `
                <span><i class="fas fa-graduation-cap"></i> ${currentBoardData.program ? currentBoardData.program.toUpperCase() : 'N/A'}</span> &bull; 
                <span><i class="fas fa-chalkboard-teacher"></i> ${escapeHtml(currentBoardData.teacher.name)}</span> &bull; 
                <span><i class="fas fa-bullseye"></i> Passing: ${currentBoardData.passing_percentage}%</span>
                ${currentBoardData.review_period ? `&bull; <span><i class="fas fa-calendar-alt"></i> ${escapeHtml(currentBoardData.review_period)}</span>` : ''}
            `;
            
            // Quick approve/reject buttons in modal if pending
            if (currentBoardData.status === 'pending') {
                actionsEl.innerHTML = `
                    <form action="/admin/mock-boards/${currentBoardData.id}/approve" method="POST" style="display:inline;">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <button type="submit" class="rv-btn rv-btn-primary">
                            <i class="fas fa-check"></i> Approve Mock Board
                        </button>
                    </form>
                    <button type="button" class="rv-btn rv-btn-danger-outline" onclick="closeQuestionsModal(); openRejectModal(${currentBoardData.id});">
                        <i class="fas fa-times"></i> Reject
                    </button>
                `;
            } else {
                actionsEl.innerHTML = '';
            }
            
            renderPhaseTabs();
            renderActivePhaseQuestions();
            
            loadingState.style.display = 'none';
            contentState.style.display = 'flex';
        })
        .catch(err => {
            console.error(err);
            loadingState.innerHTML = '<div style="text-align:center;color:#ef4444;padding:30px;"><i class="fas fa-exclamation-circle" style="font-size:32px;"></i><p style="margin-top:10px;">Network error loading questions.</p></div>';
        });
    }

    function closeQuestionsModal() {
        document.getElementById('questionsModal').style.display = 'none';
    }

    function renderPhaseTabs() {
        const tabsContainer = document.getElementById('qmPhaseTabs');
        if (!currentBoardData || !currentBoardData.phases || currentBoardData.phases.length === 0) {
            tabsContainer.innerHTML = '<span style="color:#64748b;font-size:13px;">No phases configured for this board yet.</span>';
            return;
        }
        
        let html = '';
        currentBoardData.phases.forEach((phase, idx) => {
            const isActive = idx === activePhaseIndex;
            html += `
                <button type="button" class="qm-tab-btn ${isActive ? 'active' : ''}" onclick="switchPhaseTab(${idx})">
                    <span>${escapeHtml(phase.label)}</span>
                    <span class="qm-tab-badge">${phase.total_questions} Qs</span>
                </button>
            `;
        });
        tabsContainer.innerHTML = html;
    }

    function switchPhaseTab(idx) {
        activePhaseIndex = idx;
        renderPhaseTabs();
        renderActivePhaseQuestions();
    }

    function renderActivePhaseQuestions() {
        const container = document.getElementById('qmQuestionsList');
        if (!currentBoardData || !currentBoardData.phases || !currentBoardData.phases[activePhaseIndex]) {
            container.innerHTML = '<div style="text-align:center;padding:40px;color:#94a3b8;">No questions available.</div>';
            return;
        }
        
        const phase = currentBoardData.phases[activePhaseIndex];
        const questions = phase.questions || [];
        
        if (questions.length === 0) {
            container.innerHTML = `
                <div style="text-align:center;padding:50px 20px;color:#64748b;background:#f8fafc;border-radius:12px;border:1px dashed #cbd5e1;">
                    <i class="fas fa-question-circle" style="font-size:42px;color:#cbd5e1;margin-bottom:12px;"></i>
                    <h4 style="margin:0 0 6px;color:#334155;font-size:16px;">No Questions in this Phase</h4>
                    <p style="margin:0;font-size:13.5px;">The instructor has not added questions to this phase yet.</p>
                </div>
            `;
            return;
        }
        
        let html = `
            <div class="qm-phase-summary-bar">
                <span><strong>Phase:</strong> ${escapeHtml(phase.label)}</span>
                <span><strong>Questions:</strong> ${phase.total_questions} items</span>
                ${phase.time_limit ? `<span><strong>Time Limit:</strong> ${phase.time_limit} mins</span>` : ''}
                <span><strong>Passing Grade:</strong> ${phase.passing_grade}%</span>
            </div>
        `;
        
        questions.forEach((q, index) => {
            const qNum = index + 1;
            const options = q.options || {};
            const correctOpt = String(q.correct_option || '').trim().toLowerCase();
            
            html += `
                <div class="qm-question-card">
                    <div class="qm-q-header">
                        <div class="qm-q-num">Question #${qNum}</div>
                        <div class="qm-q-tags">
                            ${q.domain ? `<span class="qm-tag domain"><i class="fas fa-tag"></i> ${escapeHtml(q.domain)}</span>` : ''}
                            ${q.difficulty ? `<span class="qm-tag difficulty ${escapeHtml(String(q.difficulty).toLowerCase())}">${escapeHtml(q.difficulty)}</span>` : ''}
                            <span class="qm-tag points">${q.points || 1} pt${(q.points || 1) > 1 ? 's' : ''}</span>
                        </div>
                    </div>
                    
                    <div class="qm-q-text">${escapeHtml(q.question_text)}</div>
                    
                    <div class="qm-options-list">
            `;
            
            if (Array.isArray(options)) {
                const letters = ['a', 'b', 'c', 'd', 'e', 'f', 'g'];
                options.forEach((optText, optIdx) => {
                    const letter = letters[optIdx] || String.fromCharCode(97 + optIdx);
                    const isCorrect = correctOpt === letter || correctOpt === String(optText).trim().toLowerCase();
                    
                    html += `
                        <div class="qm-option-item ${isCorrect ? 'correct' : ''}">
                            <div class="qm-opt-letter">${letter.toUpperCase()}</div>
                            <div class="qm-opt-text">${escapeHtml(optText)}</div>
                            ${isCorrect ? '<span class="qm-correct-badge"><i class="fas fa-check-circle"></i> Correct Answer</span>' : ''}
                        </div>
                    `;
                });
            } else if (typeof options === 'object' && options !== null) {
                Object.keys(options).forEach(key => {
                    const optText = options[key];
                    const cleanKey = String(key).trim().toLowerCase();
                    const isCorrect = correctOpt === cleanKey || correctOpt === String(optText).trim().toLowerCase();
                    
                    html += `
                        <div class="qm-option-item ${isCorrect ? 'correct' : ''}">
                            <div class="qm-opt-letter">${cleanKey.toUpperCase()}</div>
                            <div class="qm-opt-text">${escapeHtml(optText)}</div>
                            ${isCorrect ? '<span class="qm-correct-badge"><i class="fas fa-check-circle"></i> Correct Answer</span>' : ''}
                        </div>
                    `;
                });
            }
            
            html += `</div>`;
            
            if (q.explanation) {
                html += `
                    <div class="qm-explanation-box">
                        <strong><i class="fas fa-lightbulb"></i> Explanation / Rationale:</strong>
                        <p style="margin:4px 0 0;line-height:1.5;">${escapeHtml(q.explanation)}</p>
                    </div>
                `;
            }
            
            html += `</div>`;
        });
        
        container.innerHTML = html;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function openRejectModal(boardId) {
        const form = document.getElementById('rejectForm');
        form.action = `/admin/mock-boards/${boardId}/reject`;
        document.getElementById('rejectModal').style.display = 'flex';
    }
    function closeRejectModal() {
        document.getElementById('rejectModal').style.display = 'none';
    }
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = "none";
        }
    }
</script>
@endsection