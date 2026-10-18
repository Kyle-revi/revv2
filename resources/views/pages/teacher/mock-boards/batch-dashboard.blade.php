@php
    $isAdmin = in_array(auth()->user()->role ?? '', ['admin', 'superadmin'], true) || ($isAdmin ?? false);
    $layout = $isAdmin ? 'layouts.appAdmin' : 'layouts.appTeach';
@endphp
@extends($layout)

@section('title', 'Mock Boards Dashboard')

@section('page-heading')
    Mock Boards: <span style="color: #245E55;">{{ ucfirst($selectedProgram ?? 'My Program') }}</span>
@endsection

@section('header-actions')
    @if($isAdmin)
        <a href="{{ route('adminDashboard') }}" class="rv-btn rv-btn-secondary" style="display:inline-flex;align-items:center;gap:6px;text-decoration:none;">
            <i class="fas fa-arrow-left"></i> Back to Admin Dashboard
        </a>
    @else
        <button class="rv-btn rv-btn-primary" onclick="openCreateModal()">
            <i class="fas fa-plus"></i> Create Mock Board
        </button>
    @endif
@endsection

@section('content')
<div class="mock-boards-container">

    {{-- SUCCESS ALERT --}}
    @if(session('success'))
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif

    {{-- PROGRAM TABS (FOR ADMIN / MULTI-PROGRAM VIEW) --}}
    @if(isset($programs) && count($programs) > 1)
        <div style="display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; flex-wrap: wrap;">
            @foreach($programs as $prog)
                @php
                    $isActive = ($selectedProgram ?? '') === $prog;
                    $progLabels = [
                        'accountancy' => 'Accountancy (BSA)',
                        'education' => 'Education (BSED/BEED)',
                        'educ' => 'Education (BSED/BEED)',
                        'psychology' => 'Psychology (BS Psych)',
                        'psych' => 'Psychology (BS Psych)',
                    ];
                    $pLabel = $progLabels[strtolower($prog)] ?? ucfirst($prog);
                @endphp
                <a href="{{ route('mock-boards.batch.dashboard', ['program' => $prog]) }}"
                   style="padding: 8px 16px; border-radius: 8px; font-weight: 500; font-size: 14px; text-decoration: none; transition: all 0.2s; {{ $isActive ? 'background: #245E55; color: white;' : 'background: #f1f5f9; color: #475569;' }}">
                    {{ $pLabel }}
                </a>
            @endforeach
        </div>
    @endif

    {{-- OVERVIEW ANALYTICS --}}
    <div class="analytics-overview-grid">
        <div class="analytics-card">
            <div class="analytics-icon green"><i class="fas fa-check-circle"></i></div>
            <div class="analytics-info">
                <span class="analytics-label">Passing Rate</span>
                <h3 class="analytics-value">{{ number_format($completionRate ?? 0, 0) }}%</h3>
            </div>
        </div>

        <div class="analytics-card">
            <div class="analytics-icon blue"><i class="fas fa-chart-bar"></i></div>
            <div class="analytics-info">
                <span class="analytics-label">Batch Average Score</span>
                <h3 class="analytics-value">{{ number_format($classAverageScore ?? 0, 1) }}%</h3>
            </div>
        </div>

        <div class="analytics-card">
            <div class="analytics-icon orange"><i class="fas fa-users"></i></div>
            <div class="analytics-info">
                <span class="analytics-label">Students in Program</span>
                <h3 class="analytics-value">{{ $totalProgramStudents ?? 0 }}</h3>
            </div>
        </div>
    </div>

    {{-- MOCK BOARDS LIST --}}
    @if(!isset($mockBoards) || $mockBoards->isEmpty())
        <div class="empty-state">
            <i class="fas fa-clipboard-list" style="font-size: 56px; color: #8a8580; margin-bottom: 16px;"></i>
            <h3>No Mock Boards Yet</h3>
            <p>{{ $isAdmin ? 'No mock boards created for ' . ucfirst($selectedProgram ?? 'this program') . ' yet.' : "You haven't created any mock boards for " . ucfirst($selectedProgram ?? 'your program') . ' yet.' }}</p>
            @if(!$isAdmin)
                <button class="rv-btn rv-btn-primary" style="margin-top: 16px;" onclick="openCreateModal()">
                    <i class="fas fa-plus"></i> Create Your First Mock Board
                </button>
            @endif
        </div>
    @else
        <div class="mock-boards-list">
            @foreach($mockBoards as $board)
                @php
                    $totalEnrolled = max($totalProgramStudents ?? 1, 1);
                    $preTestTakers = $board->pre_test_participants ?? 0;
                    $preBoardTakers = $board->pre_boards_participants ?? 0;

                    $preTestPct = min(100, round(($preTestTakers / $totalEnrolled) * 100));
                    $preBoardPct = min(100, round(($preBoardTakers / $totalEnrolled) * 100));

                    $preTestPhase = $board->phases->firstWhere('phase_type', 'pre_test');
                    $postTestPhases = $board->phases->where('phase_type', 'pre_boards')->sortBy('sequence_number')->values();
                @endphp

                <div class="mock-board-card">
                    <div class="board-header">
                        <div>
                            <h3 style="margin:0; font-size: 1.25rem; color: #1a202c;">{{ $board->title ?? 'Untitled Mock Board' }}</h3>
                            <span style="font-size: 12px; color: #718096; text-transform: uppercase; font-weight: bold;">
                                Program: {{ $board->program ?? 'N/A' }}
                            </span>
                        </div>
                        <span class="status-badge {{ $board->status ?? 'pending' }}">
                            {{ ucfirst($board->status ?? 'pending') }}
                        </span>
                    </div>

                    @if($board->status === 'rejected' && $board->rejection_reason)
                        <div style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:10px 14px; border-radius:8px; font-size:13px; margin-bottom:14px;">
                            <strong>Rejected:</strong> {{ $board->rejection_reason }}
                        </div>
                    @endif

                    <p class="board-description">{{ $board->description ?? 'No description provided.' }}</p>

                    {{-- PARTICIPATION METRICS --}}
                    <div class="analytics-section">
                        <div class="progress-metric">
                            <div class="metric-header">
                                <span>Pre-Test Takers</span>
                                <strong>{{ $preTestTakers }}/{{ $totalEnrolled }} ({{ $preTestPct }}%)</strong>
                            </div>
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill green" style="width: {{ $preTestPct }}%;"></div>
                            </div>
                        </div>

                        <div class="progress-metric">
                            <div class="metric-header">
                                <span>Pre-Board Takers</span>
                                <strong>{{ $preBoardTakers }}/{{ $totalEnrolled }} ({{ $preBoardPct }}%)</strong>
                            </div>
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill blue" style="width: {{ $preBoardPct }}%;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="board-meta">
                        @if($board->review_period_start && $board->review_period_end)
                            <span><i class="fas fa-calendar-alt"></i> {{ $board->review_period_start->format('M d') }} - {{ $board->review_period_end->format('M d, Y') }}</span>
                        @endif
                        <span><i class="fas fa-bullseye"></i> Passing: {{ $board->passing_percentage ?? 75 }}%</span>
                        <span><i class="fas fa-user-graduate"></i> {{ $board->attempts_count ?? 0 }} Attempts</span>
                    </div>

                    <div class="board-actions">
                        <a href="{{ route('mock-boards.batch.analysis', ['program' => $board->program, 'mock_board' => $board->id]) }}" class="rv-btn rv-btn-primary">
                            <i class="fas fa-chart-line"></i> Batch Analysis
                        </a>

                        @if($preTestPhase && $preTestPhase->module)
                            <a href="{{ route('quiz.create', $preTestPhase->module) }}" class="rv-btn rv-btn-primary-outline">
                                <i class="fas fa-edit"></i>
                                {{ $preTestPhase->module->quizQuestions->isNotEmpty() ? 'Edit Pre-Test' : 'Build Pre-Test' }}
                            </a>
                        @else
                            <form action="{{ route('student.mock-boards.phases.add', $board) }}" method="POST" style="display:inline;">
                                @csrf
                                <input type="hidden" name="phase_type" value="pre_test">
                                <button type="submit" class="rv-btn rv-btn-primary-outline">
                                    <i class="fas fa-plus"></i> Add Pre-Test
                                </button>
                            </form>
                        @endif

                        {{-- Each existing post-test phase gets its own Edit/Build
                             link, distinctly labeled — a board can now have
                             more than one (e.g. Post-Test 1, Post-Test 2, ...). --}}
                        @foreach($postTestPhases as $postTestPhase)
                            @if($postTestPhase->module)
                                <a href="{{ route('quiz.create', $postTestPhase->module) }}" class="rv-btn rv-btn-primary-outline">
                                    <i class="fas fa-pen-fancy"></i>
                                    {{ $postTestPhase->module->quizQuestions->isNotEmpty() ? 'Edit' : 'Build' }} {{ $postTestPhase->phase_label }}
                                </a>
                            @endif
                        @endforeach

                        {{-- Always available — lets the teacher add another
                             post-test phase on top of any existing ones. --}}
                        <form action="{{ route('student.mock-boards.phases.add', $board) }}" method="POST" style="display:inline;">
                            @csrf
                            <input type="hidden" name="phase_type" value="pre_boards">
                            <button type="submit" class="rv-btn rv-btn-primary-outline">
                                <i class="fas fa-plus"></i> Add Post-Test
                            </button>
                        </form>

                        @php
                            $boardAttempts = $board->phases->first()?->module?->max_attempts ?? 1;
                        @endphp
                        <button class="rv-btn rv-btn-secondary" onclick="editBoard(this)"
                            data-id="{{ $board->id }}"
                            data-title="{{ $board->title }}"
                            data-description="{{ $board->description ?? '' }}"
                            data-start="{{ optional($board->review_period_start)->toDateString() }}"
                            data-end="{{ optional($board->review_period_end)->toDateString() }}"
                            data-passing="{{ $board->passing_percentage }}"
                            data-attempts="{{ $boardAttempts }}">
                            <i class="fas fa-cog"></i> Settings
                        </button>

                        <form action="{{ route('student.mock-boards.destroy', $board) }}" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure? This will delete all student attempts and statistics for this board.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rv-btn rv-btn-danger">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>

{{-- Create Mock Board Modal --}}
<div id="createModal" class="modal">
    <div class="modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
            <h3 style="margin: 0; color: #1a202c; font-size: 1.5rem;"><i class="fas fa-layer-group" style="color: #245E55;"></i> Create Mock Board</h3>
            <span style="font-size: 24px; cursor: pointer; color: #a0aec0;" onclick="closeCreateModal()">&times;</span>
        </div>

        <form action="{{ route('student.mock-boards.store') }}" method="POST">
            @csrf

            <div class="modal-form-group">
                <label class="modal-label">Mock Board Title</label>
                <input type="text" name="title" class="modal-input" placeholder="e.g., Comprehensive Accountancy Mock Exam" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="modal-form-group">
                    <label class="modal-label">Passing Rate (%)</label>
                    <input type="number" name="passing_percentage" class="modal-input" min="0" max="100" value="75" required>
                </div>
                <div class="modal-form-group">
                    <label class="modal-label">Max Attempts for Students</label>
                    <input type="number" name="max_attempts" class="modal-input" min="1" max="20" value="1" placeholder="e.g. 1" required>
                </div>
            </div>

            <div class="modal-form-group">
                <label class="modal-label">Description (Optional)</label>
                <textarea name="description" class="modal-input" rows="2" placeholder="Brief instruction overview for student guidelines..."></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="modal-form-group">
                    <label class="modal-label">Review Period Start</label>
                    <input type="date" name="review_period_start" class="modal-input" required>
                </div>
                <div class="modal-form-group">
                    <label class="modal-label">Review Period End</label>
                    <input type="date" name="review_period_end" class="modal-input" required>
                </div>
            </div>

            <div class="modal-form-group">
                <label class="modal-label">Initial Assessment Phase to Build</label>
                <div style="display: flex; gap: 20px; margin-top: 5px;">
                    <label style="font-size: 14px; cursor: pointer;">
                        <input type="radio" name="selected_phase" value="pre_test" checked onchange="toggleModalPhaseInputs()"> Pre-Test
                    </label>
                    <label style="font-size: 14px; cursor: pointer;">
                        <input type="radio" name="selected_phase" value="pre_boards" onchange="toggleModalPhaseInputs()"> Pre-Boards
                    </label>
                </div>
            </div>

            <div id="modal_pre_test_title_group" class="modal-form-group">
                <label class="modal-label">Custom Pre-Test Name (Optional)</label>
                <input type="text" name="pre_test_title" class="modal-input" placeholder="Defaults to: [Title] - Pre-Test">
            </div>

            <div id="modal_pre_boards_title_group" class="modal-form-group" style="display: none;">
                <label class="modal-label">Custom Pre-Boards Name (Optional)</label>
                <input type="text" name="pre_boards_title" class="modal-input" placeholder="Defaults to: [Title] - Pre-Boards">
            </div>

            <div class="modal-form-group">
                <label class="modal-label">Time Limit (Minutes)</label>
                <input type="number" name="time_limit" class="modal-input" min="0" value="0" placeholder="0 for unlimited">
            </div>

            <div style="margin-top: 25px; padding-top: 15px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="rv-btn rv-btn-secondary" onclick="closeCreateModal()">Cancel</button>
                <button type="submit" class="rv-btn rv-btn-primary" style="background: #245E55;">
                    <i class="fas fa-rocket"></i> Create & Build Quiz
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Modal --}}
<div id="editModal" class="modal">
    <div class="modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
            <h3 style="margin: 0; color: #1a202c; font-size: 1.5rem;">Edit Settings</h3>
            <span style="font-size: 24px; cursor: pointer; color: #a0aec0;" onclick="closeEditModal()">&times;</span>
        </div>
        <form id="editForm" method="POST">
            @csrf
            @method('PUT')
            <div class="modal-form-group"><label class="modal-label">Title</label><input type="text" id="editTitle" name="title" class="modal-input" required></div>
            <div class="modal-form-group"><label class="modal-label">Description</label><textarea id="editDescription" name="description" class="modal-input"></textarea></div>
            <div class="modal-form-group"><label class="modal-label">Start Date</label><input type="date" id="editStart" name="review_period_start" class="modal-input" required></div>
            <div class="modal-form-group"><label class="modal-label">End Date</label><input type="date" id="editEnd" name="review_period_end" class="modal-input" required></div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="modal-form-group"><label class="modal-label">Passing Rate (%)</label><input type="number" id="editPassing" name="passing_percentage" class="modal-input" required></div>
                <div class="modal-form-group"><label class="modal-label">Max Attempts for Students</label><input type="number" id="editMaxAttempts" name="max_attempts" class="modal-input" min="1" max="20" required></div>
            </div>
            <p style="font-size:12px; color:#8a8580; margin-top:-8px;">Note: editing will resubmit this Mock Board for admin approval.</p>
            <div style="margin-top: 25px; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="rv-btn rv-btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="rv-btn rv-btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('head')
<style>
    .mock-boards-container { max-width: 1100px; padding: 20px; margin: 0 auto; font-family: var(--font, 'DM Sans', sans-serif); color: #2D2D2B; }

    .analytics-overview-grid {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;
    }
    .analytics-card {
        background: #fff; border: 1px solid #DDD8CF; border-radius: 12px; padding: 16px; display: flex; align-items: center; gap: 16px; font-family: var(--font, 'DM Sans', sans-serif);
    }
    .analytics-icon {
        width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px;
    }
    .analytics-icon.green { background: #e6f4ea; color: #137333; }
    .analytics-icon.blue { background: #e8f0fe; color: #1a73e8; }
    .analytics-icon.orange { background: #feefe3; color: #b06000; }
    .analytics-label { font-size: 12px; color: #706e6b; display: block; text-transform: uppercase; font-weight: 500; letter-spacing: 0.04em; font-family: var(--font, 'DM Sans', sans-serif); }
    .analytics-value { font-size: 26px; font-weight: 500; color: #2D2D2B; margin: 2px 0 0 0; font-family: var(--font, 'DM Sans', sans-serif); }

    .empty-state { text-align: center; padding: 60px 20px; color: #5a5550; background: #fff; border-radius: 12px; border: 2px dashed #e2e8f0; font-family: var(--font, 'DM Sans', sans-serif); }
    .empty-state h3 { font-size: 20px; font-weight: 500; color: #2D2D2B; margin: 12px 0 6px; font-family: var(--font, 'DM Sans', sans-serif); }
    .empty-state p { font-size: 15px; font-weight: 400; color: #718096; margin: 0; font-family: var(--font, 'DM Sans', sans-serif); }

    .mock-boards-list { display: flex; flex-direction: column; gap: 20px; font-family: var(--font, 'DM Sans', sans-serif); }
    .mock-board-card {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: box-shadow 0.3s ease; font-family: var(--font, 'DM Sans', sans-serif);
    }
    .mock-board-card:hover { box-shadow: 0 8px 15px -3px rgba(0,0,0,0.08); }
    .board-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
    .board-header h3 { font-size: 20px; font-weight: 500; color: #2D2D2B; margin: 0; font-family: var(--font, 'DM Sans', sans-serif); }
    .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 500; text-transform: uppercase; font-family: var(--font, 'DM Sans', sans-serif); letter-spacing: 0.04em; }
    .status-badge.approved { background: #dcfce7; color: #15803d; }
    .status-badge.pending { background: #fef3c7; color: #92400e; }
    .status-badge.rejected { background: #fee2e2; color: #b91c1c; }
    .board-description { color: #5a5550; margin-bottom: 16px; line-height: 1.5; font-size: 14px; font-weight: 400; font-family: var(--font, 'DM Sans', sans-serif); }

    .analytics-section { margin-bottom: 20px; display: flex; flex-direction: column; gap: 10px; background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #f1f5f9; font-family: var(--font, 'DM Sans', sans-serif); }
    .metric-header { display: flex; justify-content: space-between; font-size: 13px; font-weight: 500; color: #475569; margin-bottom: 4px; font-family: var(--font, 'DM Sans', sans-serif); }
    .progress-bar-bg { width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; }
    .progress-bar-fill { height: 100%; border-radius: 4px; transition: width 0.3s ease; }
    .progress-bar-fill.green { background: #245E55; }
    .progress-bar-fill.blue { background: #1a73e8; }

    .board-meta { display: flex; gap: 25px; font-size: 13px; color: #718096; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #f1f5f9; flex-wrap: wrap; font-family: var(--font, 'DM Sans', sans-serif); font-weight: 400; }
    .board-meta i { color: #245E55; margin-right: 6px; }
    .board-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }

    .rv-btn-primary { background: #245E55; color: white; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-weight: 500; font-size: 14px; border: none; cursor: pointer; font-family: var(--font, 'DM Sans', sans-serif); }
    .rv-btn-primary-outline { background: transparent; border: 1.5px solid #245E55; color: #245E55; font-weight: 500; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 14px; font-family: var(--font, 'DM Sans', sans-serif); }
    .rv-btn-primary-outline:hover { background: #245E55; color: white; }
    .rv-btn-secondary { background: #f8fafc; border: 1px solid #cbd5e1; color: #475569; font-weight: 500; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 14px; cursor: pointer; font-family: var(--font, 'DM Sans', sans-serif); }
    .rv-btn-danger { background: #fff5f5; border: 1px solid #feb2b2; color: #c53030; padding: 8px 12px; border-radius: 8px; cursor: pointer; font-family: var(--font, 'DM Sans', sans-serif); font-weight: 500; }
    .rv-btn-danger:hover { background: #c53030; color: white; }
    .alert-success { background: #dcfce7; color: #15803d; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-family: var(--font, 'DM Sans', sans-serif); font-weight: 500; }

    .modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); display: none; align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(4px); font-family: var(--font, 'DM Sans', sans-serif); }
    .modal-content { background: #fff; border-radius: 15px; padding: 30px; max-width: 600px; width: 95%; max-height: 90vh; overflow-y: auto; font-family: var(--font, 'DM Sans', sans-serif); }
    .modal-form-group { margin-bottom: 14px; }
    .modal-label { display: block; font-size: 12px; font-weight: 500; text-transform: uppercase; color: #475569; margin-bottom: 5px; letter-spacing: 0.5px; font-family: var(--font, 'DM Sans', sans-serif); }
    .modal-input { width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; font-family: var(--font, 'DM Sans', sans-serif); font-weight: 400; box-sizing: border-box; }
</style>
@endsection

@section('scripts')
<script>
    function openCreateModal() { document.getElementById('createModal').style.display = 'flex'; }
    function closeCreateModal() { document.getElementById('createModal').style.display = 'none'; }
    function closeEditModal() { document.getElementById('editModal').style.display = 'none'; }

    function toggleModalPhaseInputs() {
        const selected = document.querySelector('input[name="selected_phase"]:checked');
        if(!selected) return;
        const val = selected.value;
        document.getElementById('modal_pre_test_title_group').style.display = val === 'pre_test' ? 'block' : 'none';
        document.getElementById('modal_pre_boards_title_group').style.display = val === 'pre_boards' ? 'block' : 'none';
    }

    function editBoard(button) {
        const form = document.getElementById('editForm');
        const baseUrl = '{{ url("student/mock-boards") }}';
        const id = button.dataset.id;

        form.action = baseUrl + '/' + id;
        document.getElementById('editTitle').value = button.dataset.title || '';
        document.getElementById('editDescription').value = button.dataset.description || '';
        document.getElementById('editStart').value = button.dataset.start || '';
        document.getElementById('editEnd').value = button.dataset.end || '';
        document.getElementById('editPassing').value = button.dataset.passing || '';
        document.getElementById('editMaxAttempts').value = button.dataset.attempts || '1';

        document.getElementById('editModal').style.display = 'flex';
    }

    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = "none";
        }
    }
</script>
@endsection