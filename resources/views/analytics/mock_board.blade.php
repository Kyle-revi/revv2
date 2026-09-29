@extends(($isAdmin ?? false) ? 'layouts.appAdmin' : 'layouts.appTeach')

@section('title', 'Mock Board Analysis - ' . ucfirst($program))
@section('page-heading', 'Batch Performance: ' . ucfirst($program))

@section('header-actions')
    <button class="rv-btn rv-btn-secondary" onclick="window.print()">
        <i class="fas fa-download"></i> Export Report
    </button>
@endsection

@section('content')
<style>
    /* Filter Bar Styling */
    .filter-card { background: #fff; border: 1px solid #DDD8CF; border-radius: 12px; padding: 16px 24px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .filter-group { display: flex; align-items: center; gap: 12px; }
    .filter-label { font-size: 14px; font-weight: 600; color: #5a5550; }
    .filter-select { background: #F7F2E9; border: 1px solid #DDD8CF; border-radius: 8px; padding: 8px 14px; font-size: 14px; color: #2D2D2B; outline: none; cursor: pointer; }
    .filter-select:focus { border-color: #ED773C; }

    /* Analytics Cards */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-bottom: 36px; }
    .stat-card { background: #fff; padding: 24px; border-radius: 16px; border: 1px solid #DDD8CF; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
    .stat-card.batch-hero { background: #1a4840; color: #fff; grid-column: span 2; }
    .stat-label { font-size: 14px; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.8; margin-bottom: 8px; }
    .stat-value { font-size: 32px; font-weight: 600; }
    .stat-meta { font-size: 14px; margin-top: 8px; opacity: 0.7; }

    /* Class Comparison Grid */
    .class-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-bottom: 40px;}
    .class-card { background: #F7F2E9; border: 1px solid #DDD8CF; border-radius: 12px; padding: 20px; transition: transform 0.2s; }
    .class-card:hover { transform: translateY(-4px); border-color: #ED773C; }

    .progress-bar-bg { background: #DDD8CF; height: 8px; border-radius: 4px; margin: 12px 0; overflow: hidden; }
    .progress-fill { background: #ED773C; height: 100%; transition: width 0.5s ease; }

    /* Student Table Styling */
    .rv-table-card { background: #fff; border-radius: 16px; border: 1px solid #DDD8CF; overflow: hidden; }
    .rv-table { width: 100%; border-collapse: collapse; text-align: left; }
    .rv-table th { padding: 16px 24px; background: #F7F2E9; font-size: 13px; text-transform: uppercase; color: #8a8580; font-weight: 600; border-bottom: 1px solid #DDD8CF; }
    .rv-table td { padding: 16px 24px; border-bottom: 1px solid #F0EDE7; font-size: 16px; }

    .badge-pass { background: #d4e8e5; color: #1a4840; padding: 4px 12px; border-radius: 99px; font-size: 12px; font-weight: 700; }
    .badge-fail { background: #f5e3e3; color: #9e2f2e; padding: 4px 12px; border-radius: 99px; font-size: 12px; font-weight: 700; }
    .badge-pending { background: #f0f0f0; color: #8a8580; padding: 4px 12px; border-radius: 99px; font-size: 12px; font-weight: 700; }
</style>

{{-- Admin Control Bar --}}
@if($isAdmin)
<form method="GET" action="{{ route('admin.mock-board-analytics') }}" id="analyticsFilterForm" class="filter-card">
    <div class="filter-group">
        <span class="filter-label"><i class="fas fa-filter"></i> View Mode:</span>
        <select name="view_type" class="filter-select" onchange="document.getElementById('analyticsFilterForm').submit()">
            <option value="batch" {{ $viewType === 'batch' ? 'selected' : '' }}>Whole Batch (Global)</option>
            <option value="program" {{ $viewType === 'program' ? 'selected' : '' }}>Per Program</option>
            <option value="class" {{ $viewType === 'class' ? 'selected' : '' }}>Per Specific Class</option>
        </select>
    </div>

    @if($viewType === 'program')
    <div class="filter-group">
        <span class="filter-label">Select Program:</span>
        <select name="program" class="filter-select" onchange="document.getElementById('analyticsFilterForm').submit()">
            <option value="All">All Programs</option>
            @foreach($programsList as $p)
                <option value="{{ $p }}" {{ $selectedProgram === $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
            @endforeach
        </select>
    </div>
    @endif

    @if($viewType === 'class')
    <div class="filter-group">
        <span class="filter-label">Select Class:</span>
        <select name="class_id" class="filter-select" onchange="document.getElementById('analyticsFilterForm').submit()">
            <option value="">All Classes</option>
            @foreach($classList as $c)
                <option value="{{ $c->id }}" {{ $selectedClassId == $c->id ? 'selected' : '' }}>{{ $c->name }} ({{ $c->program }})</option>
            @endforeach
        </select>
    </div>
    @endif
</form>
@endif

{{-- 1. Top Level Batch Stats --}}
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
</div>

<div class="tab-nav" style="display:flex; gap:8px; margin-bottom:24px; border-bottom:2px solid #DDD8CF;">
    <button class="tab-btn active" onclick="switchTab('overview', this)" style="padding:12px 20px; border:none; background:none; font-weight:600; cursor:pointer; border-bottom:3px solid #245E55;">Overview</button>
    <button class="tab-btn" onclick="switchTab('item-analysis', this)" style="padding:12px 20px; border:none; background:none; font-weight:600; cursor:pointer; color:#8a8580; border-bottom:3px solid transparent;">Item Analysis</button>
</div>

<div id="tab-overview">

{{-- 2. Class-by-Class Breakdown --}}
<h3 style="margin-bottom: 20px; font-size: 24px;">Class Performance Comparison</h3>
<div class="class-grid">
    @forelse($hierarchical_stats['classes'] as $class)
    <div class="class-card">
        <div style="display:flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <h4 style="margin:0; font-size: 18px;">{{ $class['class_name'] }}</h4>
                <p style="font-size: 13px; color: #8a8580; margin: 4px 0 0 0;">{{ $class['student_count'] }} Students</p>
            </div>
            <div style="text-align:right;">
                <span style="font-weight:700; color: #245E55;">{{ $class['passing_rate'] }}%</span>
            </div>
        </div>

        <div class="progress-bar-bg">
            <div class="progress-fill" style="width: {{ $class['passing_rate'] }}%;"></div>
        </div>

        <div style="display:flex; justify-content: space-between; font-size: 14px; color: #5a5550;">
            <span>Class Average:</span>
            <strong>{{ $class['average_score'] }}%</strong>
        </div>
    </div>
    @empty
    <p style="color: #8a8580;">No class data available for this view selection.</p>
    @endforelse
</div>

<hr style="margin: 40px 0; border: 0; border-top: 1px solid #DDD8CF;">

{{-- 3. Individual Student List --}}
<h3 style="margin-bottom: 20px; font-size: 24px;">Individual Student Results</h3>
<div class="rv-table-card">
    <table class="rv-table">
        <thead>
            <tr>
                <th>Student Name</th>
                <th>Program</th>
                <th>Pre-Test</th>
                <th>Pre-Board</th>
                <th>Improvement</th>
                <th>Status</th>
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
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align: center; color: #8a8580; padding: 24px;">No student results found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

</div><!-- end tab-overview -->

<div id="tab-item-analysis" style="display:none;">
    <div class="filter-card" style="justify-content:flex-start; gap:16px; align-items:center;">
        <span class="filter-label">Phase:</span>
        <button class="tab-btn phase-btn active" onclick="switchPhase('pre_boards', this)" style="padding:8px 16px; border:1px solid #DDD8CF; border-radius:8px; background:#245E55; color:#fff; cursor:pointer;">Pre-Boards (Post-Test)</button>
        <span style="font-size:12px; color:#8a8580; margin-left:8px; display:inline-flex; align-items:center; gap:4px;">
            <i class="fas fa-info-circle"></i> Item analysis is disabled for Pre-Test (Diagnostic Assessment).
        </span>
    </div>

    @foreach(['pre_boards'] as $phaseKey)
    <div id="phase-{{ $phaseKey }}" style="">
        @php $phaseQuestions = $item_analysis[$phaseKey] ?? []; @endphp

        @if(empty($phaseQuestions))
            <p style="color:#8a8580; padding:20px;">No data yet.</p>
        @else
            {{-- FIX: only surface questions that are ACTUALLY difficult (below 60% correct) —
                 previously this always took up to 10 regardless of difficulty value,
                 which surfaced 100%-correct "Very Easy" questions as if they needed review. --}}
            @php
                $hardest = collect($phaseQuestions)
                    ->filter(fn ($q) => $q['difficulty'] < 0.6)
                    ->sortBy('difficulty')
                    ->take(10)
                    ->values();
            @endphp

            @if($hardest->isNotEmpty())
                <h4 style="margin:24px 0 12px;">Needs Review — Difficult Question</h4>
                <div class="class-grid" style="margin-bottom:32px;">
                    @foreach($hardest as $q)
                    <div class="class-card">
                        <p style="font-size:14px; margin:0 0 8px; color:#2D2D2B;">{{ Str::limit($q['question_text'], 90) }}</p>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:12px; color:#8a8580;">{{ $q['correct_count'] }}/{{ $q['total_count'] }} tama</span>
                            <span style="font-weight:700; color:#C63F3E;">{{ round($q['difficulty'] * 100, 1) }}%</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif

            <h4 style="margin:24px 0 12px;">All of the Questions</h4>
            <div class="rv-table-card">
                <table class="rv-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Question</th>
                            <th>Answered</th>
                            <th>% Correct</th>
                            <th>Difficulty</th>
                            <th>Discrimination</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($phaseQuestions as $q)
                        <tr>
                            <td>{{ $q['order'] ?? $loop->iteration }}</td>
                            <td>{{ Str::limit($q['question_text'], 70) }}</td>
                            <td>{{ $q['correct_count'] }}/{{ $q['total_count'] }}</td>
                            <td>{{ round($q['difficulty'] * 100, 1) }}%</td>
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
                                    <span style="color:#8a8580; font-size:12px;">No data yet.</span>
                                @elseif($q['discrimination'] < 0.1)
                                    <span class="badge-fail">{{ $q['discrimination'] }} — Need to review</span>
                                @elseif($q['discrimination'] < 0.3)
                                    <span class="badge-pending">{{ $q['discrimination'] }}</span>
                                @else
                                    <span class="badge-pass">{{ $q['discrimination'] }}</span>
                                @endif
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

@endsection

@section('scripts')
<script>
    function switchTab(tab, btn) {
        document.getElementById('tab-overview').style.display = tab === 'overview' ? 'block' : 'none';
        document.getElementById('tab-item-analysis').style.display = tab === 'item-analysis' ? 'block' : 'none';

        document.querySelectorAll('.tab-nav .tab-btn').forEach(b => {
            b.style.borderBottomColor = 'transparent';
            b.style.color = '#8a8580';
        });
        btn.style.borderBottomColor = '#245E55';
        btn.style.color = '#2D2D2B';
    }

    function switchPhase(phase, btn) {
        document.getElementById('phase-pre_test').style.display = phase === 'pre_test' ? 'block' : 'none';
        document.getElementById('phase-pre_boards').style.display = phase === 'pre_boards' ? 'block' : 'none';

        document.querySelectorAll('.phase-btn').forEach(b => {
            b.style.background = '#fff';
            b.style.color = '#5a5550';
        });
        btn.style.background = '#245E55';
        btn.style.color = '#fff';
    }
</script>
@endsection