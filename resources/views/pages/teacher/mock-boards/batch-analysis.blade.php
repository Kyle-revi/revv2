@php
    $isAdmin = in_array(auth()->user()->role ?? '', ['admin', 'superadmin'], true) || ($isAdmin ?? false);
    $layout = $isAdmin ? 'layouts.appAdmin' : 'layouts.appTeach';
@endphp
@extends($layout)

@section('title', 'Mock Board Analysis - ' . ucfirst($program))
@section('page-heading', 'Batch Performance: ' . ucfirst($program))

@section('header-actions')
    <div style="display:flex;gap:10px;align-items:center;">
        <a href="{{ route('mock-boards.batch.dashboard', ['program' => $program]) }}" class="rv-btn rv-btn-secondary" style="display:inline-flex;align-items:center;gap:6px;text-decoration:none;">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <button class="rv-btn rv-btn-secondary" onclick="window.print()">
            <i class="fas fa-download"></i> Export Report
        </button>
    </div>
@endsection

@section('content')
<style>
    .mock-batch-analysis { font-family: var(--font, 'DM Sans', sans-serif); color: #2D2D2B; }
    .filter-card { background: #fff; border: 1px solid #DDD8CF; border-radius: 12px; padding: 16px 24px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .filter-group { display: flex; align-items: center; gap: 12px; }
    .filter-label { font-size: 14px; font-weight: 500; color: #5a5550; font-family: var(--font, 'DM Sans', sans-serif); }
    .filter-select { background: #F7F2E9; border: 1px solid #DDD8CF; border-radius: 8px; padding: 8px 14px; font-size: 14px; font-weight: 500; color: #2D2D2B; outline: none; cursor: pointer; font-family: var(--font, 'DM Sans', sans-serif); }
    .filter-select:focus { border-color: #ED773C; }

    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-bottom: 36px; }
    .stat-card { background: #fff; padding: 24px; border-radius: 16px; border: 1px solid #DDD8CF; box-shadow: 0 4px 12px rgba(0,0,0,0.03); font-family: var(--font, 'DM Sans', sans-serif); }
    .stat-card.batch-hero { background: #1a4840; color: #fff; grid-column: span 2; }
    .stat-label { font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.85; margin-bottom: 8px; font-weight: 500; font-family: var(--font, 'DM Sans', sans-serif); }
    .stat-value { font-size: 32px; font-weight: 500; font-family: var(--font, 'DM Sans', sans-serif); line-height: 1.1; }
    .stat-meta { font-size: 14px; margin-top: 8px; opacity: 0.75; font-weight: 400; font-family: var(--font, 'DM Sans', sans-serif); }

    .class-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-bottom: 40px;}
    .class-card { background: #F7F2E9; border: 1px solid #DDD8CF; border-radius: 12px; padding: 20px; transition: transform 0.2s; font-family: var(--font, 'DM Sans', sans-serif); }
    .class-card:hover { transform: translateY(-4px); border-color: #ED773C; }

    .rv-table-card { background: #fff; border-radius: 16px; border: 1px solid #DDD8CF; overflow: hidden; font-family: var(--font, 'DM Sans', sans-serif); }
    .rv-table { width: 100%; border-collapse: collapse; text-align: left; font-family: var(--font, 'DM Sans', sans-serif); }
    .rv-table th { padding: 16px 24px; background: #F7F2E9; font-size: 13px; text-transform: uppercase; color: #8a8580; font-weight: 500; letter-spacing: 0.04em; border-bottom: 1px solid #DDD8CF; font-family: var(--font, 'DM Sans', sans-serif); }
    .rv-table td { padding: 16px 24px; border-bottom: 1px solid #F0EDE7; font-size: 15px; font-weight: 400; font-family: var(--font, 'DM Sans', sans-serif); }

    .badge-pass { background: #d4e8e5; color: #1a4840; padding: 4px 12px; border-radius: 99px; font-size: 12px; font-weight: 500; font-family: var(--font, 'DM Sans', sans-serif); }
    .badge-fail { background: #f5e3e3; color: #9e2f2e; padding: 4px 12px; border-radius: 99px; font-size: 12px; font-weight: 500; font-family: var(--font, 'DM Sans', sans-serif); }
    .badge-pending { background: #f0f0f0; color: #8a8580; padding: 4px 12px; border-radius: 99px; font-size: 12px; font-weight: 500; font-family: var(--font, 'DM Sans', sans-serif); }

    .chart-card { background: #fff; border: 1px solid #DDD8CF; border-radius: 16px; padding: 24px; margin-bottom: 24px; font-family: var(--font, 'DM Sans', sans-serif); }
    .chart-card h4 { margin: 0 0 16px; font-size: 18px; font-weight: 500; color: #2D2D2B; font-family: var(--font, 'DM Sans', sans-serif); }
    .chart-canvas-wrap { position: relative; width: 100%; }
    .chart-row { display: grid; grid-template-columns: 1fr 1.4fr; gap: 20px; margin-bottom: 32px; }
    @media (max-width: 900px) { .chart-row { grid-template-columns: 1fr; } }

    /* Distractor Choices Badges */
    .distractor-wrap { display: flex; gap: 6px; flex-wrap: wrap; }
    .distractor-badge { font-size: 11px; padding: 2px 7px; border-radius: 6px; background: #f1f5f9; color: #475569; font-weight: 500; font-family: var(--font, 'DM Sans', sans-serif); }
    .distractor-badge.is-correct { background: #dcfce7; color: #15803d; border: 1px solid #86efac; font-weight: 500; }
    .distractor-badge.is-common { background: #fee2e2; color: #b91c1c; }

    /* Individual Student Modal */
    .item-modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.65); display: none; align-items: center; justify-content: center; z-index: 1200; backdrop-filter: blur(4px); padding: 20px; font-family: var(--font, 'DM Sans', sans-serif); }
    .item-modal-dialog { background: #fff; border-radius: 16px; width: 100%; max-width: 900px; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); font-family: var(--font, 'DM Sans', sans-serif); }
    .item-modal-head { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #faf8f5; }
    .item-modal-body { padding: 24px; overflow-y: auto; flex: 1; font-family: var(--font, 'DM Sans', sans-serif); }
    .student-q-card { background: #faf8f5; border: 1px solid #ece7dc; border-radius: 12px; padding: 16px; margin-bottom: 14px; transition: all 0.2s; font-family: var(--font, 'DM Sans', sans-serif); }
    .student-q-card.is-correct-card { border-left: 5px solid #22c55e; }
    .student-q-card.is-incorrect-card { border-left: 5px solid #ef4444; }
    .student-opt-item { padding: 8px 12px; border-radius: 8px; margin-top: 6px; font-size: 14px; border: 1px solid #e2e8f0; background: #fff; display: flex; justify-content: space-between; align-items: center; font-family: var(--font, 'DM Sans', sans-serif); }
    .student-opt-item.opt-correct { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; font-weight: 500; }
    .student-opt-item.opt-student-wrong { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
    .phase-pill-btn { padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; border: 1px solid #cbd5e1; background: #fff; color: #475569; transition: all 0.2s; font-family: var(--font, 'DM Sans', sans-serif); }
    .phase-pill-btn.active { background: #245E55; color: #fff; border-color: #245E55; }

    @keyframes modalScaleIn {
        from { opacity: 0; transform: scale(0.95) translateY(8px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }
</style>

<div class="mock-batch-analysis">

@php
    $examLabelMap = [
        'accountancy' => 'CPALE',
        'education' => 'LEPT',
        'educ' => 'LEPT',
        'psychology' => 'RPsy Board Exam',
        'psych' => 'RPsy Board Exam',
    ];
    $examLabel = $examLabelMap[strtolower($program)] ?? strtoupper($program);
@endphp

{{-- Top Level Batch Stats --}}
<div class="stats-grid">
    <div class="stat-card batch-hero">
        <div class="stat-label">Overall Passing Rate</div>
        <div class="stat-value">{{ $summary['pre_boards_passing_rate'] ?? 0 }}%</div>
        <div class="stat-meta">Target Rate for {{ ucfirst($program) }}: {{ $mockBoard->passing_percentage ?? '75' }}%</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Participants</div>
        <div class="stat-value">{{ $summary['total_students'] }}</div>
        <div class="stat-meta">Active in this Mock Board</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">ANOVA Result</div>
        <div class="stat-value" style="color: {{ ($anova['significant'] ?? false) ? '#22C55E' : '#C63F3E' }}">
            {{ ($anova['significant'] ?? false) ? 'Significant' : 'No Change' }}
        </div>
        <div class="stat-meta">P-Value: {{ $anova['p_value'] ?? 'N/A' }}</div>
    </div>
    <div class="stat-card" style="border-left: 4px solid #ED773C;">
        <div class="stat-label">Projected {{ $examLabel }} Pass Rate</div>
        <div class="stat-value">{{ $forecast['projected_batch_pass_rate'] ?? 0 }}%</div>
        <div class="stat-meta">Sample: {{ $forecast['sample_size'] ?? 0 }} student(s) &bull; {{ $forecast['confidence_note'] ?? '' }}</div>
    </div>
    @if(($overallPostTestStats['phases_total'] ?? 0) > 1)
        {{-- Only shown when this board has more than one post-test phase —
             this is the combined "best score across all post-tests" rate,
             distinct from the single-phase Pre-Boards rate above. --}}
        <div class="stat-card" style="border-left: 4px solid #245E55;">
            <div class="stat-label">Overall Post-Test Passing Rate (Best Score)</div>
            <div class="stat-value">{{ $overallPostTestStats['overall_passing_rate'] ?? 0 }}%</div>
            <div class="stat-meta">
                {{ $overallPostTestStats['students_passed'] ?? 0 }}/{{ $overallPostTestStats['students_attempted'] ?? 0 }} students passed
                &bull; combined across {{ $overallPostTestStats['phases_total'] }} post-test phases
            </div>
        </div>
    @endif
</div>

{{-- HISTORICAL BENCHMARK COMPARISON SECTION --}}
@if($historicalComparison)
    {{-- Comparison against a real, previous licensure exam the teacher linked --}}
    <div class="rv-table-card" style="margin-bottom: 24px; padding: 22px 24px; background: #fff; border: 1.5px solid #245E55; border-radius: 12px; box-shadow: 0 4px 12px rgba(36,94,85,0.06);">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
            <div>
                <span style="font-size:12px; font-weight:700; color:#245E55; text-transform:uppercase; letter-spacing:0.05em;">
                    <i class="fas fa-balance-scale"></i> Historical Licensure Exam Benchmark
                </span>
                <h3 style="margin:4px 0 0 0; font-size: 20px; font-weight: 600; color:#1e293b;">
                    {{ $historicalComparison['exam_label'] }} ({{ $historicalComparison['exam_period_or_year'] }})
                </h3>
            </div>
            <div style="display:flex; gap:8px; align-items:center;">
                <button type="button" class="rv-btn rv-btn-secondary" style="height:34px; padding:0 12px; font-size:13px;" onclick="document.getElementById('changeBenchmarkModal').style.display='flex'">
                    <i class="fas fa-exchange-alt"></i> Change Benchmark
                </button>
                <form id="removeBenchmarkForm" action="{{ route('student.mock-boards.link-historical-exam', $mockBoard->id) }}" method="POST" style="display:inline;">
                    @csrf
                    <input type="hidden" name="historical_board_exam_result_id" value="">
                    <button type="button" class="rv-btn rv-btn-danger" style="height:34px; padding:0 10px; font-size:13px; display:inline-flex; align-items:center; justify-content:center;" onclick="openRemoveBenchmarkModal()" title="Remove Benchmark">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </form>
            </div>
        </div>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:20px; align-items:center;">
            <div style="background: #f8fafc; padding: 14px 16px; border-radius: 10px; border: 1px solid #e2e8f0;">
                <div style="font-size:12px; color:#64748b; text-transform:uppercase; font-weight:600; letter-spacing:0.04em;">Physical Exam Passing Rate</div>
                <div style="font-size:26px; font-weight:700; color:#1e293b; margin-top: 2px;">{{ $historicalComparison['historical_passing_rate'] }}%</div>
            </div>
            <div style="background: #f8fafc; padding: 14px 16px; border-radius: 10px; border: 1px solid #e2e8f0;">
                <div style="font-size:12px; color:#64748b; text-transform:uppercase; font-weight:600; letter-spacing:0.04em;">Current System Post-Test Rate</div>
                <div style="font-size:26px; font-weight:700; color:#1e293b; margin-top: 2px;">{{ $historicalComparison['reviso_passing_rate'] }}%</div>
            </div>
            <div style="background: {{ $historicalComparison['delta'] >= 0 ? '#f0fdf4' : '#fef2f2' }}; padding: 14px 16px; border-radius: 10px; border: 1px solid {{ $historicalComparison['delta'] >= 0 ? '#bbf7d0' : '#fecaca' }};">
                <div style="font-size:12px; color:{{ $historicalComparison['delta'] >= 0 ? '#166534' : '#991b1b' }}; text-transform:uppercase; font-weight:600; letter-spacing:0.04em;">Variance vs. Physical Exam</div>
                <div style="font-size:26px; font-weight:700; color: {{ $historicalComparison['delta'] >= 0 ? '#15803d' : '#b91c1c' }}; margin-top: 2px;">
                    {{ $historicalComparison['delta'] >= 0 ? '+' : '' }}{{ $historicalComparison['delta'] }} pts
                </div>
            </div>
        </div>
    </div>
@else
    {{-- Always visible benchmark banner so teacher can quickly type down or select a passing rate --}}
    <div class="rv-table-card" style="margin-bottom: 24px; padding: 20px 24px; background: #ffffff; border: 1.5px dashed #245E55; border-radius: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.02);">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
            <div style="max-width: 480px;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <div style="width:32px; height:32px; border-radius:8px; background:#e6f4ea; color:#245E55; display:flex; align-items:center; justify-content:center; font-size:15px;">
                        <i class="fas fa-balance-scale"></i>
                    </div>
                    <h4 style="margin:0; font-size:16px; font-weight:600; color:#1e293b;">
                        Compare with Previous Physical Board Exam
                    </h4>
                </div>
                <p style="margin:6px 0 0 0; font-size:13px; color:#64748b; line-height: 1.45;">
                    Type down the passing rate from a previous physical PRC copy to compare this batch's performance against real-world licensure exam results.
                </p>
            </div>

            <form action="{{ route('mock-boards.quick-benchmark', $mockBoard->id) }}" method="POST" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
                @csrf
                <div>
                    <label style="display:block; font-size:11px; font-weight:600; text-transform:uppercase; color:#64748b; margin-bottom:4px;">Exam Name / Label</label>
                    <input type="text" name="exam_label" class="rv-input" placeholder="e.g. Oct 2024 CPALE" required style="height:38px; font-size:13px; padding:0 12px; width:170px;">
                </div>
                <div>
                    <label style="display:block; font-size:11px; font-weight:600; text-transform:uppercase; color:#64748b; margin-bottom:4px;">Year</label>
                    <input type="text" name="exam_period_or_year" class="rv-input" value="{{ date('Y') }}" style="height:38px; font-size:13px; padding:0 10px; width:75px;">
                </div>
                <div>
                    <label style="display:block; font-size:11px; font-weight:600; text-transform:uppercase; color:#64748b; margin-bottom:4px;">Physical Pass Rate %</label>
                    <input type="number" name="passing_rate" class="rv-input" min="1" max="100" step="0.1" placeholder="e.g. 70" required style="height:38px; font-size:13px; padding:0 10px; width:120px;">
                </div>
                <button type="submit" class="rv-btn rv-btn-primary" style="height:38px; padding:0 16px; font-size:13px; font-weight:600; display:inline-flex; align-items:center; gap:6px;">
                    <i class="fas fa-check"></i> Compare Now
                </button>
            </form>
        </div>

        @if(isset($historicalExamOptions) && $historicalExamOptions->isNotEmpty())
            <div style="margin-top: 14px; padding-top: 12px; border-top: 1px solid #f1f5f9; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <span style="font-size: 12px; color: #64748b; font-weight: 500;">Or pick from saved records:</span>
                <form action="{{ route('mock-boards.quick-benchmark', $mockBoard->id) }}" method="POST" style="display: inline-flex; align-items: center; gap: 8px;">
                    @csrf
                    <select name="historical_board_exam_result_id" class="rv-input" style="height: 32px; font-size: 12px; padding: 0 10px; min-width: 220px;" required>
                        <option value="">-- Select Saved Historical Exam --</option>
                        @foreach($historicalExamOptions as $opt)
                            <option value="{{ $opt->id }}">{{ $opt->exam_label }} ({{ $opt->exam_period_or_year }}) - {{ $opt->passing_rate }}%</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rv-btn rv-btn-secondary" style="height: 32px; padding: 0 12px; font-size: 12px;">Link</button>
                </form>
            </div>
        @endif
    </div>
@endif

{{-- CHANGE BENCHMARK MODAL --}}
<div id="changeBenchmarkModal" class="modal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.65); align-items:center; justify-content:center; z-index:1100; backdrop-filter:blur(4px); padding:20px;">
    <div style="background:#fff; border-radius:16px; padding:24px; max-width:500px; width:90%; box-shadow:0 20px 35px -5px rgba(0,0,0,0.2), 0 10px 15px -5px rgba(0,0,0,0.08); animation: modalScaleIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <div style="display:flex; align-items:center; gap:8px;">
                <div style="width:34px; height:34px; border-radius:8px; background:#e6f4ea; color:#245E55; display:flex; align-items:center; justify-content:center; font-size:15px;">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <h3 style="margin:0; font-size:18px; font-weight:600; color:#1e293b;">Change Historical Benchmark</h3>
            </div>
            <button type="button" onclick="document.getElementById('changeBenchmarkModal').style.display='none'" style="background:none; border:none; font-size:22px; cursor:pointer; color:#94a3b8;">&times;</button>
        </div>

        <form action="{{ route('mock-boards.quick-benchmark', $mockBoard->id) }}" method="POST">
            @csrf
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">Exam Name / Label *</label>
                <input type="text" name="exam_label" class="rv-input" placeholder="e.g. May 2024 CPA Licensure Exam" required style="width:100%;">
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">Year / Period</label>
                    <input type="text" name="exam_period_or_year" class="rv-input" value="{{ date('Y') }}" style="width:100%;">
                </div>
                <div>
                    <label style="display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:4px;">Passing Rate % *</label>
                    <input type="number" name="passing_rate" class="rv-input" min="1" max="100" step="0.1" placeholder="e.g. 75" required style="width:100%;">
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:20px;">
                <button type="button" class="rv-btn rv-btn-secondary" onclick="document.getElementById('changeBenchmarkModal').style.display='none'">Cancel</button>
                <button type="submit" class="rv-btn rv-btn-primary">Update Benchmark</button>
            </div>
        </form>
    </div>
</div>

@if($historicalComparison)
{{-- REMOVE BENCHMARK CONFIRMATION MODAL --}}
<div id="removeBenchmarkModal" class="modal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.65); align-items:center; justify-content:center; z-index:1100; backdrop-filter:blur(4px); padding:20px;">
    <div style="background:#fff; border-radius:16px; padding:28px 24px; max-width:440px; width:100%; box-shadow:0 20px 35px -5px rgba(0,0,0,0.2), 0 10px 15px -5px rgba(0,0,0,0.08); text-align:center; font-family:var(--font, 'DM Sans', sans-serif); animation: modalScaleIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);">
        <div style="width:54px; height:54px; border-radius:50%; background:#fee2e2; color:#dc2626; display:flex; align-items:center; justify-content:center; font-size:22px; margin:0 auto 16px; box-shadow: 0 4px 12px rgba(220,38,38,0.15);">
            <i class="fas fa-trash-alt"></i>
        </div>
        <h3 style="margin:0 0 8px; font-size:19px; font-weight:600; color:#1e293b;">
            Remove Benchmark Comparison?
        </h3>
        <p style="margin:0 0 16px; font-size:14px; color:#64748b; line-height:1.5;">
            Are you sure you want to remove the physical exam benchmark comparison for <br>
            <strong style="color:#1e293b;">{{ $historicalComparison['exam_label'] }} ({{ $historicalComparison['exam_period_or_year'] }})</strong>?
        </p>

        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px 16px; margin-bottom:20px; font-size:13px; color:#475569; display:flex; justify-content:space-between; align-items:center;">
            <span style="display:flex; align-items:center; gap:6px; color:#64748b;">
                <i class="fas fa-chart-pie" style="color:#245E55;"></i> Physical Passing Rate:
            </span>
            <span style="font-weight:700; color:#1e293b; font-size:15px;">{{ $historicalComparison['historical_passing_rate'] }}%</span>
        </div>

        <div style="display:flex; justify-content:center; gap:10px;">
            <button type="button" class="rv-btn rv-btn-secondary" style="height:40px; padding:0 18px; font-size:14px; flex:1; font-weight:500;" onclick="closeRemoveBenchmarkModal()">
                Cancel
            </button>
            <button type="button" class="rv-btn rv-btn-danger" style="height:40px; padding:0 18px; font-size:14px; flex:1; display:inline-flex; align-items:center; justify-content:center; gap:8px; font-weight:600;" onclick="confirmRemoveBenchmark()">
                <i class="fas fa-trash-alt"></i> Yes, Remove
            </button>
        </div>
    </div>
</div>
@endif

<div class="tab-nav" style="display:flex; gap:8px; margin-bottom:24px; border-bottom:2px solid #DDD8CF;">
    <button class="tab-btn active" onclick="switchTab('overview', this)" style="padding:12px 20px; border:none; background:none; font-weight:500; font-size:15px; cursor:pointer; border-bottom:3px solid #245E55; color:#2D2D2B; font-family: var(--font, 'DM Sans', sans-serif);">Overview & Student Results</button>
    <button class="tab-btn" onclick="switchTab('item-analysis', this)" style="padding:12px 20px; border:none; background:none; font-weight:500; font-size:15px; cursor:pointer; color:#8a8580; border-bottom:3px solid transparent; font-family: var(--font, 'DM Sans', sans-serif);">Item Analysis (By Group)</button>
</div>

<div id="tab-overview">

{{-- Individual Student List --}}
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 16px;">
    <div>
        <h3 style="margin: 0; font-size: 22px; font-weight: 500; color: #1f2937;">Individual Student Performance</h3>
        <p style="margin: 2px 0 0; font-size: 14px; color: #8e8678;">Detailed scores with direct question-by-question breakdown</p>
    </div>
</div>

<div class="rv-table-card">
    <table class="rv-table">
        <thead>
            <tr>
                <th>Student Name</th>
                <th>Program</th>
                <th>Pre-Test</th>
                <th>Post-Test</th>
                <th>Improvement</th>
                <th>Status</th>
                <th style="text-align: center;">Item Breakdown</th>
            </tr>
        </thead>
        <tbody>
            @forelse($student_results as $result)
            <tr>
                <td style="font-weight: 500; color: #2D2D2B;">{{ $result['name'] }}</td>
                <td style="color: #5a5550; font-size: 14px;">{{ $result['program'] }}</td>
                <td>{{ $result['pre_test_score'] !== null ? $result['pre_test_score'].'%' : '--' }}</td>
                <td>{{ $result['pre_boards_score'] !== null ? $result['pre_boards_score'].'%' : '--' }}</td>
                <td>
                    @if(($result['improvement'] ?? 0) > 0)
                        <span style="color: #22C55E; font-weight: 600;">+{{ $result['improvement'] }}%</span>
                    @elseif(($result['improvement'] ?? 0) < 0)
                        <span style="color: #C63F3E; font-weight: 600;">{{ $result['improvement'] }}%</span>
                    @else
                        <span style="color: #B5AFA5;">0%</span>
                    @endif
                </td>
                <td>
                    @php
                        $hasPassedCurrent = false;
                        if ($result['pre_boards_score'] !== null) {
                            $hasPassedCurrent = $result['pre_boards_passed'];
                        } else {
                            $hasPassedCurrent = $result['pre_test_passed'] ?? false;
                        }

                        $isStarted = $result['pre_test_score'] !== null || $result['pre_boards_score'] !== null;
                    @endphp

                    @if(!$isStarted)
                        <span class="badge-pending">NOT STARTED</span>
                    @elseif($hasPassedCurrent)
                        <span class="badge-pass">PASSED</span>
                    @else
                        <span class="badge-fail">FAILED</span>
                    @endif
                </td>
                <td style="text-align: center;">
                    <button type="button" class="rv-btn rv-btn-secondary" style="padding: 6px 12px; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;" onclick="openStudentItemModal({{ $result['user_id'] }}, '{{ addslashes($result['name']) }}')">
                        <i class="fas fa-list-check" style="color: #245E55;"></i> Item Analysis
                    </button>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="text-align: center; color: #8a8580; padding: 24px;">No student results found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

</div><!-- end tab-overview -->

<div id="tab-item-analysis" style="display:none;">
    @php
        $phasesToRender = $boardPhases ?? collect();
        if ($phasesToRender->isEmpty()) {
            $phasesToRender = collect([
                (object)['id' => 'pre_test', 'phase_type' => 'pre_test', 'phase_label' => 'Pre-Test'],
                (object)['id' => 'pre_boards', 'phase_type' => 'pre_boards', 'phase_label' => 'Pre-Boards'],
            ]);
        }
        // Prefer first post-test phase as default active tab
        $defaultActiveIdx = $phasesToRender->search(fn ($p) => ($p->phase_type ?? '') !== 'pre_test');
        if ($defaultActiveIdx === false) {
            $defaultActiveIdx = 0;
        }
    @endphp

    <div class="filter-card" style="gap:16px;">
        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <span class="filter-label"><i class="fas fa-layer-group"></i> Phase:</span>
            @foreach($phasesToRender as $idx => $ph)
                @php
                    $pKey = is_numeric($ph->id) ? 'phase_'.$ph->id : $ph->id;
                @endphp
                <button class="tab-btn phase-btn {{ $idx === 0 ? 'active' : '' }}" onclick="switchPhase('{{ $pKey }}', this)" style="padding:8px 16px; border:1px solid #DDD8CF; border-radius:8px; background: {{ $idx === 0 ? '#245E55' : '#fff' }}; color: {{ $idx === 0 ? '#fff' : '#5a5550' }}; cursor:pointer;">
                    {{ $ph->phase_label ?? ucfirst(str_replace('_', ' ', $ph->phase_type)) }}
                </button>
            @endforeach
        </div>

        @if(isset($boardClasses) && $boardClasses->count() > 0)
            <div style="display: flex; align-items: center; gap: 8px;">
                <span class="filter-label"><i class="fas fa-users"></i> Class Filter:</span>
                <select class="filter-select" onchange="filterItemAnalysisClass(this.value)">
                    <option value="">All Classes (Batch)</option>
                    @foreach($boardClasses as $cls)
                        <option value="{{ $cls->id }}" {{ ($selectedClassFilter ?? '') == $cls->id ? 'selected' : '' }}>{{ $cls->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>

    @foreach($phasesToRender as $idx => $ph)
    @php
        $pKey = is_numeric($ph->id) ? 'phase_'.$ph->id : $ph->id;
        $phaseData = $item_analysis[$pKey] ?? [];
        $phaseQuestions = $phaseData['questions'] ?? (is_array($phaseData) && !isset($phaseData['questions']) ? $phaseData : []);

        $colorMap = [
            'Very Difficult' => '#C63F3E',
            'Difficult'      => '#ED773C',
            'Moderate'       => '#EAC119',
            'Easy'           => '#22c55e',
            'Very Easy'      => '#10b981',
        ];
        $defaultColor = '#245E55';

        $distributionCounts = collect($phaseQuestions)->countBy('interpretation');
        $distLabels = $distributionCounts->keys()->values()->all();
        $distValues = $distributionCounts->values()->values()->all();
        $distColors = collect($distLabels)->map(fn ($label) => $colorMap[$label] ?? $defaultColor)->all();

        $barLabels = [];
        $barShortLabels = [];
        $barValues = [];
        $barColors = [];
        foreach ($phaseQuestions as $qIdx => $q) {
            $barLabels[] = 'Q'.($q['order'] ?? ($qIdx + 1)).' — '.\Illuminate\Support\Str::limit($q['question_text'], 60);
            $barShortLabels[] = 'Q'.($q['order'] ?? ($qIdx + 1));
            $barValues[] = round($q['difficulty'] * 100, 1);
            $barColors[] = $colorMap[$q['interpretation']] ?? $defaultColor;
        }
    @endphp
    <div id="phase-{{ $pKey }}" class="phase-container" style="{{ $idx !== 0 ? 'display:none;' : '' }}"
         data-dist-labels='@json($distLabels)'
         data-dist-values='@json($distValues)'
         data-dist-colors='@json($distColors)'
         data-bar-labels='@json($barLabels)'
         data-bar-short-labels='@json($barShortLabels)'
         data-bar-values='@json($barValues)'
         data-bar-colors='@json($barColors)'>

        @if(empty($phaseQuestions))
            <div class="rv-table-card" style="padding: 40px 20px; text-align: center; color: #8a8580;">
                <i class="fas fa-clipboard-question" style="font-size: 40px; margin-bottom: 12px; color: #cbd5e1;"></i>
                <p style="margin: 0; font-size: 16px;">No question item analysis data recorded for {{ $ph->phase_label ?? 'this phase' }} yet.</p>
            </div>
        @else
            <div class="chart-row">
                <div class="chart-card">
                    <h4>Difficulty Distribution</h4>
                    <div class="chart-canvas-wrap" style="height: 280px;">
                        <canvas id="distChart_{{ $pKey }}"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <h4>% Correct per Question <span style="font-weight:400; color:#8a8580; font-size:14px;">(lower percentage = more difficult)</span></h4>
                    <div class="chart-canvas-wrap" style="height: 320px; max-width: {{ max(400, count($barValues) * 60) }}px; margin-right: auto;">
                        <canvas id="barChart_{{ $pKey }}"></canvas>
                    </div>
                </div>
            </div>

            @php $hardest = collect($phaseQuestions)->sortBy('difficulty')->take(6)->values(); @endphp

            @if($hardest->isNotEmpty())
                <h4 style="margin:24px 0 12px; font-size: 18px; font-weight: 500;">Needs Review — Hardest Questions</h4>
                <div class="class-grid" style="margin-bottom:32px;">
                    @foreach($hardest as $q)
                    <div class="class-card">
                        <div style="display:flex; justify-content:space-between; margin-bottom: 6px;">
                            <span style="font-size: 12px; font-weight: 600; color: #245E55;">Question #{{ $q['order'] ?? $loop->iteration }}</span>
                            <span style="font-weight:600; color:#C63F3E; font-size: 13px;">{{ round($q['difficulty'] * 100, 1) }}% Correct</span>
                        </div>
                        <p style="font-size:14px; margin:0 0 10px; color:#2D2D2B; line-height: 1.4;">{{ Str::limit($q['question_text'], 110) }}</p>
                        <div style="display:flex; justify-content:space-between; align-items:center; border-top: 1px solid #e5dfd5; padding-top: 8px;">
                            <span style="font-size:12px; color:#8a8580;">{{ $q['correct_count'] }}/{{ $q['total_count'] }} correct</span>
                            @if(isset($q['correct_option']))
                                <span style="font-size: 12px; font-weight: 500; color: #15803d; background: #dcfce7; padding: 2px 8px; border-radius: 4px;">Key: {{ $q['correct_option'] }}</span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif

            <h4 style="margin:24px 0 12px; font-size: 18px; font-weight: 500;">All Questions Breakdown</h4>
            <div class="rv-table-card">
                <table class="rv-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Question</th>
                            <th>Answered</th>
                            <th>% Correct</th>
                            <th>Difficulty</th>
                            <th>Discrimination</th>
                            <th>Distractor Choices</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($phaseQuestions as $q)
                        <tr>
                            <td style="font-weight: 500;">{{ $q['order'] ?? $loop->iteration }}</td>
                            <td>
                                <div style="font-weight: 500; color: #2D2D2B;">{{ Str::limit($q['question_text'], 75) }}</div>
                                @if(isset($q['correct_option']))
                                    <div style="font-size: 12px; color: #15803d; margin-top: 2px;">
                                        <strong>Correct Key:</strong> {{ $q['correct_option'] }}
                                    </div>
                                @endif
                            </td>
                            <td>{{ $q['correct_count'] }}/{{ $q['total_count'] }}</td>
                            <td style="font-weight: 600;">{{ round($q['difficulty'] * 100, 1) }}%</td>
                            <td>
                                @if($q['interpretation'] === 'Very Difficult')
                                    <span class="badge-fail">{{ $q['interpretation'] }}</span>
                                @elseif(in_array($q['interpretation'], ['Difficult', 'Moderate']))
                                    <span class="badge-pending">{{ $q['interpretation'] }}</span>
                                @else
                                    <span class="badge-pass">{{ $q['interpretation'] }}</span>
                                @endif
                            </td>
                            <td>
                                @if($q['discrimination'] === null)
                                    <span style="color:#8a8580; font-size:12px;">No data yet</span>
                                @elseif($q['discrimination'] < 0.1)
                                    <span class="badge-fail">{{ $q['discrimination'] }} — Review</span>
                                @elseif($q['discrimination'] < 0.3)
                                    <span class="badge-pending">{{ $q['discrimination'] }}</span>
                                @else
                                    <span class="badge-pass">{{ $q['discrimination'] }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="distractor-wrap">
                                    @if(isset($q['option_percentages']) && is_array($q['option_percentages']))
                                        @foreach($q['option_percentages'] as $optKey => $pct)
                                            @php
                                                $isCorrectOpt = ($q['correct_option'] ?? '') === $optKey;
                                            @endphp
                                            <span class="distractor-badge {{ $isCorrectOpt ? 'is-correct' : '' }}" title="{{ $pct }}% of students selected {{ $optKey }}">
                                                {{ $optKey }}: {{ $pct }}%
                                            </span>
                                        @endforeach
                                    @else
                                        <span style="color:#8a8580; font-size:12px;">—</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    @endforeach
</div>

{{-- Individual Student Item Analysis Modal --}}
<div id="studentItemModal" class="item-modal">
    <div class="item-modal-dialog">
        <div class="item-modal-head">
            <div>
                <h3 id="modalStudentName" style="margin: 0; font-size: 20px; font-weight: 500; color: #1f2937;">Student Item Analysis</h3>
                <p id="modalStudentMeta" style="margin: 2px 0 0; font-size: 13px; color: #8e8678;">Question-by-question breakdown</p>
            </div>
            <button type="button" onclick="closeStudentItemModal()" style="background:none; border:none; font-size: 24px; color: #94a3b8; cursor: pointer;">&times;</button>
        </div>

        <div class="item-modal-body">
            {{-- Phase Pills --}}
            <div id="modalPhaseSelector" style="display:flex; gap:8px; margin-bottom: 16px; flex-wrap: wrap;"></div>

            {{-- Summary Stats Bar --}}
            <div id="modalSummaryBar" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; background: #faf8f5; border: 1px solid #ece7dc; border-radius: 10px; padding: 12px; margin-bottom: 16px; text-align: center;"></div>

            {{-- Filter Controls --}}
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                <div style="display: flex; gap: 6px;">
                    <button type="button" class="phase-pill-btn active" id="btnFilterAll" onclick="filterModalQuestions('all')">All Questions</button>
                    <button type="button" class="phase-pill-btn" id="btnFilterIncorrect" onclick="filterModalQuestions('incorrect')">Missed Only</button>
                    <button type="button" class="phase-pill-btn" id="btnFilterCorrect" onclick="filterModalQuestions('correct')">Correct Only</button>
                </div>
                <span id="modalQuestionsCount" style="font-size: 13px; color: #8e8678;"></span>
            </div>

            {{-- Questions List Container --}}
            <div id="modalQuestionsList">
                <div style="text-align: center; padding: 40px; color: #8a8580;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 28px; margin-bottom: 10px;"></i>
                    <p style="margin:0;">Loading student responses...</p>
                </div>
            </div>
</div>
</div><!-- end .mock-batch-analysis -->

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    let currentStudentId = null;
    let currentStudentData = null;
    let currentModalFilter = 'all';

    function switchTab(tab, btn) {
        document.getElementById('tab-overview').style.display = tab === 'overview' ? 'block' : 'none';
        document.getElementById('tab-item-analysis').style.display = tab === 'item-analysis' ? 'block' : 'none';

        document.querySelectorAll('.tab-nav .tab-btn').forEach(b => {
            b.style.borderBottomColor = 'transparent';
            b.style.color = '#8a8580';
        });
        btn.style.borderBottomColor = '#245E55';
        btn.style.color = '#2D2D2B';

        if (tab === 'item-analysis') {
            const activePhase = document.querySelector('#tab-item-analysis .phase-container:not([style*="display:none"])');
            if (activePhase) initPhaseCharts(activePhase.id.replace('phase-', ''));
        }
    }

    function switchPhase(phaseKey, btn) {
        document.querySelectorAll('.phase-container').forEach(el => el.style.display = 'none');
        const target = document.getElementById('phase-' + phaseKey);
        if (target) target.style.display = 'block';

        document.querySelectorAll('.phase-btn').forEach(b => {
            b.style.background = '#fff';
            b.style.color = '#5a5550';
        });
        btn.style.background = '#245E55';
        btn.style.color = '#fff';

        initPhaseCharts(phaseKey);
    }

    function filterItemAnalysisClass(classId) {
        const url = new URL(window.location.href);
        if (classId) {
            url.searchParams.set('class_filter', classId);
        } else {
            url.searchParams.delete('class_filter');
        }
        window.location.href = url.toString();
    }

    const rvChartInstances = {};

    document.addEventListener('DOMContentLoaded', function () {
        const firstPhase = document.querySelector('.phase-container');
        if (firstPhase) {
            initPhaseCharts(firstPhase.id.replace('phase-', ''));
        }
    });

    function initPhaseCharts(phase) {
        const wrap = document.getElementById('phase-' + phase);
        if (!wrap) return;

        const distKey = 'dist_' + phase;
        const barKey = 'bar_' + phase;

        const distCanvas = document.getElementById('distChart_' + phase);
        if (distCanvas && !rvChartInstances[distKey]) {
            const distLabels = JSON.parse(wrap.dataset.distLabels || '[]');
            const distValues = JSON.parse(wrap.dataset.distValues || '[]');
            const distColors = JSON.parse(wrap.dataset.distColors || '[]');

            if (distValues.length > 0) {
                rvChartInstances[distKey] = new Chart(distCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: distLabels,
                        datasets: [{
                            data: distValues,
                            backgroundColor: distColors,
                            borderWidth: 2,
                            borderColor: '#fff',
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                        const pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                                        return ctx.label + ': ' + ctx.parsed + ' questions (' + pct + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }

        const barCanvas = document.getElementById('barChart_' + phase);
        if (barCanvas && !rvChartInstances[barKey]) {
            const fullLabels = JSON.parse(wrap.dataset.barLabels || '[]');
            const shortLabels = JSON.parse(wrap.dataset.barShortLabels || '[]');
            const barValues = JSON.parse(wrap.dataset.barValues || '[]');
            const barColors = JSON.parse(wrap.dataset.barColors || '[]');

            if (barValues.length > 0) {
                rvChartInstances[barKey] = new Chart(barCanvas, {
                    type: 'bar',
                    data: {
                        labels: shortLabels,
                        datasets: [{
                            label: '% Correct (Difficulty)',
                            data: barValues,
                            backgroundColor: barColors,
                            borderRadius: 4,
                            maxBarThickness: 30,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    title: function (items) {
                                        return fullLabels[items[0].dataIndex] || '';
                                    },
                                    label: function (ctx) {
                                        return ctx.parsed.y + '% correct';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { ticks: { maxRotation: 45, minRotation: 0 } },
                            y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } }
                        }
                    }
                });
            }
        }
    }

    // ==========================================
    // Individual Student Item Analysis Modal
    // ==========================================
    function openStudentItemModal(userId, userName, phaseId = null) {
        currentStudentId = userId;
        const modal = document.getElementById('studentItemModal');
        modal.style.display = 'flex';

        document.getElementById('modalStudentName').textContent = userName + ' — Item Breakdown';
        document.getElementById('modalStudentMeta').textContent = 'Loading student answers...';

        document.getElementById('modalQuestionsList').innerHTML = `
            <div style="text-align: center; padding: 40px; color: #8a8580;">
                <i class="fas fa-spinner fa-spin" style="font-size: 28px; margin-bottom: 10px;"></i>
                <p style="margin:0;">Loading student responses...</p>
            </div>
        `;

        const url = `{{ url('mock-boards/batch-analytics/'.$program.'/'.$mockBoard->id.'/student-analysis') }}/${userId}` + (phaseId ? `?phase_id=${phaseId}` : '');

        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    document.getElementById('modalQuestionsList').innerHTML = `<p style="color:#C63F3E; padding:20px;">Unable to load data: ${data.message || 'Unknown error'}</p>`;
                    return;
                }
                currentStudentData = data;
                renderStudentModal(data);
            })
            .catch(err => {
                document.getElementById('modalQuestionsList').innerHTML = `<p style="color:#C63F3E; padding:20px;">Failed to fetch student analysis. Please try again.</p>`;
            });
    }

    function renderStudentModal(data) {
        const student = data.student;
        const summary = data.summary;
        const activePhase = data.active_phase;

        document.getElementById('modalStudentMeta').textContent = `${student.name} (${student.idnumber}) · ${student.program} · Attempted: ${summary.attempted_at}`;

        // Phase Pills
        let phaseTabsHtml = '';
        (data.phases || []).forEach(ph => {
            const isActive = activePhase && ph.id === activePhase.id;
            const scoreLabel = ph.percentage !== null && ph.percentage !== undefined ? ` (${ph.percentage}%)` : ' (Pending)';
            phaseTabsHtml += `
                <button type="button" class="phase-pill-btn ${isActive ? 'active' : ''}" onclick="openStudentItemModal(${student.id}, '${escapeJs(student.name)}', ${ph.id})">
                    ${ph.phase_label}${scoreLabel}
                </button>
            `;
        });
        document.getElementById('modalPhaseSelector').innerHTML = phaseTabsHtml;

        // Summary Bar
        const passBadgeColor = summary.passed ? '#15803d' : '#b91c1c';
        const passBadgeBg = summary.passed ? '#dcfce7' : '#fee2e2';
        document.getElementById('modalSummaryBar').innerHTML = `
            <div>
                <p style="margin:0; font-size:11px; color:#8e8678; text-transform:uppercase; font-weight:600;">Total Questions</p>
                <p style="margin:4px 0 0; font-size:18px; font-weight:600; color:#1f2937;">${summary.total_questions}</p>
            </div>
            <div>
                <p style="margin:0; font-size:11px; color:#8e8678; text-transform:uppercase; font-weight:600;">Correct</p>
                <p style="margin:4px 0 0; font-size:18px; font-weight:600; color:#22c55e;">${summary.correct_count}</p>
            </div>
            <div>
                <p style="margin:0; font-size:11px; color:#8e8678; text-transform:uppercase; font-weight:600;">Incorrect</p>
                <p style="margin:4px 0 0; font-size:18px; font-weight:600; color:#ef4444;">${summary.incorrect_count}</p>
            </div>
            <div>
                <p style="margin:0; font-size:11px; color:#8e8678; text-transform:uppercase; font-weight:600;">Score</p>
                <p style="margin:4px 0 0; font-size:18px; font-weight:600; color:#1f2937;">${summary.score_percentage}%</p>
            </div>
            <div>
                <p style="margin:0; font-size:11px; color:#8e8678; text-transform:uppercase; font-weight:600;">Status</p>
                <span style="display:inline-block; margin-top:4px; font-size:12px; font-weight:600; padding:2px 8px; border-radius:99px; background:${passBadgeBg}; color:${passBadgeColor};">
                    ${summary.passed ? 'PASSED' : 'BELOW PASSING'}
                </span>
            </div>
        `;

        renderModalQuestions();
    }

    function filterModalQuestions(type) {
        currentModalFilter = type;
        document.getElementById('btnFilterAll').classList.toggle('active', type === 'all');
        document.getElementById('btnFilterIncorrect').classList.toggle('active', type === 'incorrect');
        document.getElementById('btnFilterCorrect').classList.toggle('active', type === 'correct');
        renderModalQuestions();
    }

    function renderModalQuestions() {
        if (!currentStudentData || !currentStudentData.questions) return;

        let list = currentStudentData.questions;
        if (currentModalFilter === 'incorrect') {
            list = list.filter(q => !q.is_correct);
        } else if (currentModalFilter === 'correct') {
            list = list.filter(q => q.is_correct);
        }

        document.getElementById('modalQuestionsCount').textContent = `Showing ${list.length} of ${currentStudentData.questions.length} questions`;

        if (list.length === 0) {
            document.getElementById('modalQuestionsList').innerHTML = `
                <div style="text-align: center; padding: 40px; color: #8a8580;">
                    <p style="margin:0;">No questions match the current filter.</p>
                </div>
            `;
            return;
        }

        let html = '';
        list.forEach((q, idx) => {
            const isCorrect = q.is_correct;
            const cardClass = isCorrect ? 'is-correct-card' : 'is-incorrect-card';
            const badgeIcon = isCorrect ? '<i class="fas fa-check-circle" style="color:#22c55e;"></i> Correct' : '<i class="fas fa-times-circle" style="color:#ef4444;"></i> Incorrect';

            let optionsHtml = '';
            const opts = q.options || {};
            Object.keys(opts).forEach(optKey => {
                const optText = opts[optKey];
                const isThisStudentChoice = q.selected_option === optKey;
                const isThisCorrectChoice = q.correct_option === optKey;

                let optClass = 'student-opt-item';
                let tag = '';

                if (isThisCorrectChoice) {
                    optClass += ' opt-correct';
                    tag = '<span style="font-size:11px; font-weight:600; color:#059669;"><i class="fas fa-check"></i> Correct Answer</span>';
                }
                if (isThisStudentChoice && !isThisCorrectChoice) {
                    optClass += ' opt-student-wrong';
                    tag = '<span style="font-size:11px; font-weight:600; color:#dc2626;"><i class="fas fa-times"></i> Student Selected</span>';
                } else if (isThisStudentChoice && isThisCorrectChoice) {
                    tag = '<span style="font-size:11px; font-weight:600; color:#059669;"><i class="fas fa-check-double"></i> Student Correct Choice</span>';
                }

                optionsHtml += `
                    <div class="${optClass}">
                        <span><strong>${optKey}.</strong> ${escapeHtml(optText)}</span>
                        ${tag}
                    </div>
                `;
            });

            html += `
                <div class="student-q-card ${cardClass}">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <span style="font-size:13px; font-weight:600; color:#245E55;">Question #${q.order || (idx + 1)} · <span style="color:#64748b; font-weight:400;">Points: ${q.points_earned}/${q.points}</span></span>
                        <span style="font-size:13px; font-weight:600;">${badgeIcon}</span>
                    </div>
                    <p style="margin:0 0 10px; font-size:15px; color:#1e293b; line-height:1.4;">${escapeHtml(q.question_text)}</p>
                    <div>${optionsHtml}</div>
                    ${q.explanation ? `<div style="margin-top:10px; padding:8px 12px; background:#f1f5f9; border-radius:6px; font-size:13px; color:#475569;"><strong>Explanation:</strong> ${escapeHtml(q.explanation)}</div>` : ''}
                </div>
            `;
        });

        document.getElementById('modalQuestionsList').innerHTML = html;
    }

    function closeStudentItemModal() {
        document.getElementById('studentItemModal').style.display = 'none';
        currentStudentId = null;
        currentStudentData = null;
    }

    function openRemoveBenchmarkModal() {
        const modal = document.getElementById('removeBenchmarkModal');
        if (modal) {
            modal.style.display = 'flex';
        }
    }

    function closeRemoveBenchmarkModal() {
        const modal = document.getElementById('removeBenchmarkModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    function confirmRemoveBenchmark() {
        const form = document.getElementById('removeBenchmarkForm');
        if (form) {
            form.submit();
        }
    }

    window.onclick = function(event) {
        const studentModal = document.getElementById('studentItemModal');
        if (event.target === studentModal) {
            closeStudentItemModal();
        }

        const removeModal = document.getElementById('removeBenchmarkModal');
        if (event.target === removeModal) {
            closeRemoveBenchmarkModal();
        }

        const changeModal = document.getElementById('changeBenchmarkModal');
        if (event.target === changeModal) {
            changeModal.style.display = 'none';
        }
    };

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeStudentItemModal();
            closeRemoveBenchmarkModal();
            const changeModal = document.getElementById('changeBenchmarkModal');
            if (changeModal) {
                changeModal.style.display = 'none';
            }
        }
    });

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function escapeJs(str) {
        if (!str) return '';
        return String(str).replace(/'/g, "\\'");
    }
</script>
@endsection