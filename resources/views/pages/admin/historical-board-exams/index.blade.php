@extends('layouts.appAdmin')

@section('title', 'Historical Board Exam Results & Benchmarks')

@section('page-heading')
    Historical <span style="color: #245E55;">Board Exam Results & Benchmarks</span>
@endsection

@section('header-actions')
    <div class="no-print" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <button type="button" onclick="window.print()" class="rv-btn rv-btn-secondary" style="display:inline-flex;align-items:center;gap:6px;background:#fff;border:1.5px solid #245E55;color:#245E55;font-weight:600;">
            <i class="fas fa-print"></i> Print Benchmark Report
        </button>
        <button type="button" onclick="exportHistoricalBenchmarksExcel()" class="rv-btn rv-btn-primary" style="display:inline-flex;align-items:center;gap:6px;background:#107c41;border:none;color:#fff;font-weight:600;">
            <i class="fas fa-file-excel"></i> Export Excel (.xlsx)
        </button>
    </div>
@endsection

@section('content')
<style>
    .hb-print-header,
    .hb-print-signatures {
        display: none;
    }

    @media print {
        @page { size: portrait; margin: 12mm; }
        body { background: #fff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        .rv-sidebar, .rv-topbar, .rv-overlay, .no-print, .hb-filter-bar, .hb-sub, .alert { display: none !important; }
        .rv-main { margin-left: 0 !important; width: 100% !important; }
        .rv-content { padding: 0 !important; }
        .hb-print-header {
            display: block !important;
            border-bottom: 2.5px solid #245E55;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .hb-print-signatures {
            display: grid !important;
            grid-template-columns: repeat(3, 1fr);
            gap: 28px;
            margin-top: 32px;
            padding-top: 16px;
            page-break-inside: avoid;
        }
        .hb-card, .hb-comp-card { page-break-inside: avoid; box-shadow: none !important; }
    }

    .hb-wrap { display: flex; flex-direction: column; gap: 24px; max-width: 1300px; }
    .hb-sub { font-size: 15px; color: #64748b; margin: -12px 0 6px; line-height: 1.5; }

    /* ── Filter Pills ── */
    .hb-filter-bar {
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    }
    .hb-pill {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 7px 16px; border-radius: 99px;
        font-size: 14px; font-weight: 500; text-decoration: none;
        background: #fff; color: #475569; border: 1px solid #e2e8f0;
        transition: all 0.15s ease;
    }
    .hb-pill:hover { border-color: #245E55; color: #245E55; }
    .hb-pill.active { background: #245E55; color: #fff; border-color: #245E55; font-weight: 600; }

    /* ── Cards ── */
    .hb-card {
        background: #fff; border: 1px solid #e2e8f0;
        border-radius: 14px; padding: 22px 24px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03);
    }
    .hb-card-head {
        display: flex; justify-content: space-between; align-items: center;
        flex-wrap: wrap; gap: 12px; margin-bottom: 18px;
        border-bottom: 1px solid #f1f5f9; padding-bottom: 14px;
    }
    .hb-card-title {
        font-size: 18px; font-weight: 600; color: #0f172a; margin: 0;
        display: flex; align-items: center; gap: 10px;
    }
    .hb-card-title i { color: #245E55; }

    /* ── Form Inputs ── */
    .hb-form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        align-items: flex-end;
    }
    .hb-form-group {
        display: flex; flex-direction: column; gap: 6px;
    }
    .hb-label {
        font-size: 13px; font-weight: 600; color: #334155;
    }
    .hb-input, .hb-select {
        height: 42px; padding: 0 14px;
        border: 1.5px solid #cbd5e1; border-radius: 8px;
        font-size: 14px; font-family: inherit; color: #1e293b;
        background: #fff; width: 100%; box-sizing: border-box;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .hb-input:focus, .hb-select:focus {
        border-color: #245E55;
        box-shadow: 0 0 0 3px rgba(36,94,85,0.12);
        outline: none;
    }
    .hb-btn-submit {
        height: 42px; padding: 0 20px;
        background: #245E55; color: #fff; border: none;
        border-radius: 8px; font-size: 14px; font-weight: 600;
        cursor: pointer; display: inline-flex; align-items: center;
        justify-content: center; gap: 8px; transition: background 0.15s, transform 0.1s;
        white-space: nowrap;
    }
    .hb-btn-submit:hover { background: #1b4740; }
    .hb-btn-submit:active { transform: scale(0.98); }

    /* ── Comparison Table / Grid ── */
    .hb-comp-grid {
        display: grid; grid-template-columns: 1fr; gap: 16px;
    }
    .hb-comp-card {
        background: #fff; border: 1.5px solid #e2e8f0; border-radius: 12px;
        padding: 20px 22px; transition: border-color 0.15s, box-shadow 0.15s;
    }
    .hb-comp-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 16px rgba(0,0,0,0.04);
    }
    .hb-comp-card.has-benchmark {
        border-left: 5px solid #245E55;
    }
    .hb-comp-card.no-benchmark {
        border-left: 5px solid #cbd5e1;
    }

    .hb-comp-header {
        display: flex; justify-content: space-between; align-items: flex-start;
        flex-wrap: wrap; gap: 12px; margin-bottom: 16px;
    }
    .hb-comp-title {
        font-size: 17px; font-weight: 600; color: #0f172a; margin: 0 0 4px;
    }
    .hb-comp-meta {
        font-size: 13px; color: #64748b; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    }

    .hb-stats-row {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 14px; margin-top: 12px;
    }
    .hb-stat-box {
        background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px;
        padding: 12px 16px;
    }
    .hb-stat-label {
        font-size: 12px; font-weight: 600; text-transform: uppercase;
        color: #64748b; letter-spacing: 0.04em; margin-bottom: 4px;
    }
    .hb-stat-val {
        font-size: 24px; font-weight: 700; color: #0f172a; line-height: 1.2;
    }
    .hb-stat-sub {
        font-size: 12px; color: #94a3b8; margin-top: 2px;
    }

    /* Variance badge */
    .hb-delta-box {
        border-radius: 10px; padding: 12px 16px; border: 1px solid transparent;
        display: flex; flex-direction: column; justify-content: center;
    }
    .hb-delta-box.positive {
        background: #f0fdf4; border-color: #bbf7d0; color: #166534;
    }
    .hb-delta-box.negative {
        background: #fef2f2; border-color: #fecaca; color: #991b1b;
    }
    .hb-delta-box.neutral {
        background: #f8fafc; border-color: #e2e8f0; color: #475569;
    }

    /* ── Program Badges ── */
    .hb-badge {
        font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 99px;
        text-transform: uppercase; letter-spacing: 0.04em;
    }
    .hb-badge-psych { background: #ede9fe; color: #6b21a8; }
    .hb-badge-educ  { background: #e0f2fe; color: #0369a1; }
    .hb-badge-acc   { background: #fef3c7; color: #92400e; }

    /* ── Table ── */
    .hb-table { width: 100%; border-collapse: collapse; font-size: 14px; }
    .hb-table th {
        text-align: left; padding: 12px 14px; font-size: 12px; font-weight: 600;
        text-transform: uppercase; color: #64748b; letter-spacing: 0.04em;
        border-bottom: 1.5px solid #e2e8f0; background: #fafafa;
    }
    .hb-table td {
        padding: 14px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle;
    }
    .hb-table tr:hover td { background: #fafafa; }

    .hb-empty {
        text-align: center; padding: 3rem 1rem; color: #94a3b8;
    }
</style>

<div class="hb-wrap">
    {{-- Official PCC Printable Header --}}
    <div class="hb-print-header">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
            <div style="display:flex;align-items:center;gap:14px;">
                <img src="{{ asset('assets/img/brand/pcc_logo.png') }}" alt="PCC Logo" style="width:62px;height:62px;object-fit:contain;flex-shrink:0;">
                <div>
                    <div style="font-size:15px;font-weight:800;color:#245E55;text-transform:uppercase;letter-spacing:0.6px;">
                        Pasig Catholic College
                    </div>
                    <div style="font-size:12px;color:#475569;margin-top:1px;">
                        College Department &bull; Office of Academic Affairs &amp; Licensure Review
                    </div>
                    <div style="font-size:18px;font-weight:800;color:#0f172a;margin-top:4px;">
                        Historical PRC Board Exam Benchmarks &amp; Variance Report
                    </div>
                </div>
            </div>
            <div style="text-align:right;font-size:11.5px;color:#475569;line-height:1.5;">
                <div><strong>Program Filter:</strong> {{ $selectedProgram ? ucfirst($selectedProgram) : 'All Programs' }}</div>
                <div><strong>Compared Mock Boards:</strong> {{ count($comparisons) }}</div>
                <div><strong>Date Generated:</strong> {{ now()->format('F d, Y h:i A') }}</div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success" style="background:#e1f5ee;color:#0f6e56;padding:14px 18px;border-radius:10px;font-size:15px;display:flex;align-items:center;gap:10px;">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger" style="background:#fcebeb;color:#a32d2d;padding:14px 18px;border-radius:10px;font-size:15px;display:flex;align-items:center;gap:10px;">
            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
        </div>
    @endif

    <p class="hb-sub">
        Benchmark and compare your Mock Board's actual student Post-Test passing rates against official historical PRC licensure exam results.
    </p>

    {{-- Program Filter Pills --}}
    <div class="hb-filter-bar">
        <a href="{{ route('historical-board-exams.index') }}" class="hb-pill {{ empty($selectedProgram) ? 'active' : '' }}">
            All Programs
        </a>
        <a href="{{ route('historical-board-exams.index', ['program' => 'psychology']) }}" class="hb-pill {{ $selectedProgram === 'psychology' ? 'active' : '' }}">
            <i class="fas fa-brain"></i> Psychology
        </a>
        <a href="{{ route('historical-board-exams.index', ['program' => 'education']) }}" class="hb-pill {{ $selectedProgram === 'education' ? 'active' : '' }}">
            <i class="fas fa-graduation-cap"></i> Education
        </a>
        <a href="{{ route('historical-board-exams.index', ['program' => 'accountancy']) }}" class="hb-pill {{ $selectedProgram === 'accountancy' ? 'active' : '' }}">
            <i class="fas fa-calculator"></i> Accountancy
        </a>
    </div>

    {{-- SECTION 1: ADD NEW HISTORICAL RECORD --}}
    @if(auth()->user() && in_array(auth()->user()->role, ['admin', 'superadmin'], true))
        <div class="hb-card no-print">
            <div class="hb-card-head">
                <h3 class="hb-card-title">
                    <i class="fas fa-plus-circle"></i> Add Historical Board Exam Result
                </h3>
                <span style="font-size:13px; color:#64748b;">
                    Type in real-world physical PRC exam data
                </span>
            </div>

            <form action="{{ route('historical-board-exams.store') }}" method="POST">
                @csrf
                <div class="hb-form-grid">
                    <div class="hb-form-group">
                        <label class="hb-label">Program *</label>
                        <select name="program" class="hb-select" required>
                            <option value="psychology" {{ $selectedProgram === 'psychology' ? 'selected' : '' }}>Psychology</option>
                            <option value="education" {{ $selectedProgram === 'education' ? 'selected' : '' }}>Education</option>
                            <option value="accountancy" {{ $selectedProgram === 'accountancy' ? 'selected' : '' }}>Accountancy</option>
                        </select>
                    </div>

                    <div class="hb-form-group" style="grid-column: span 2;">
                        <label class="hb-label">Exam Label *</label>
                        <input type="text" name="exam_label" class="hb-input" placeholder="e.g. October 2024 CPA Licensure Exam" required>
                    </div>

                    <div class="hb-form-group">
                        <label class="hb-label">Period / Year *</label>
                        <input type="text" name="exam_period_or_year" class="hb-input" placeholder="2024" value="{{ date('Y') }}" required>
                    </div>

                    <div class="hb-form-group">
                        <label class="hb-label">Total Examinees *</label>
                        <input type="number" name="total_examinees" class="hb-input" min="1" placeholder="e.g. 1000" required>
                    </div>

                    <div class="hb-form-group">
                        <label class="hb-label">Passed Count *</label>
                        <input type="number" name="passed_count" class="hb-input" min="0" placeholder="e.g. 720" required>
                    </div>

                    <div class="hb-form-group" style="grid-column: span 2;">
                        <label class="hb-label">Source Note (Optional)</label>
                        <input type="text" name="source_note" class="hb-input" placeholder="e.g. Typed from PRC Official Board Bulletin, Page 4">
                    </div>

                    <div class="hb-form-group" style="align-self: flex-end;">
                        <button type="submit" class="hb-btn-submit">
                            <i class="fas fa-save"></i> Add Benchmark Record
                        </button>
                    </div>
                </div>
            </form>
        </div>
    @endif

    {{-- SECTION 2: MOCK BOARD POST-TEST vs HISTORICAL BENCHMARK COMPARISONS --}}
    <div class="hb-card">
        <div class="hb-card-head">
            <div>
                <h3 class="hb-card-title">
                    <i class="fas fa-balance-scale"></i> Mock Board Post-Test Comparisons & Variance
                </h3>
                <p style="font-size:13px; color:#64748b; margin:4px 0 0 0;">
                    Compares each Mock Board's actual Post-Test passing rate directly against the selected historical physical board exam benchmark.
                </p>
            </div>
        </div>

        @if(empty($comparisons))
            <div class="hb-empty">
                <i class="fas fa-clipboard-list" style="font-size: 42px; opacity: 0.3; margin-bottom: 12px; display: block;"></i>
                <p style="font-size: 15px; margin: 0;">No mock boards found for the selected program.</p>
            </div>
        @else
            <div class="hb-comp-grid">
                @foreach($comparisons as $item)
                    @php
                        $progClass = match(strtolower($item['program'])) {
                            'psychology', 'psych' => 'hb-badge-psych',
                            'education', 'educ' => 'hb-badge-educ',
                            default => 'hb-badge-acc'
                        };
                        $progName = ucfirst($item['program']);
                        $hasBenchmark = !empty($item['historical_id']);
                    @endphp

                    <div class="hb-comp-card {{ $hasBenchmark ? 'has-benchmark' : 'no-benchmark' }}">
                        <div class="hb-comp-header">
                            <div>
                                <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px; flex-wrap:wrap;">
                                    <h4 class="hb-comp-title">{{ $item['mock_board_title'] }}</h4>
                                    <span class="hb-badge {{ $progClass }}">{{ $progName }}</span>
                                </div>
                                <div class="hb-comp-meta">
                                    <span><i class="fas fa-layer-group"></i> <strong>Target Post-Test Phase:</strong> {{ $item['phase_names'] }}</span>
                                </div>
                            </div>

                            {{-- Link / Change Benchmark Dropdown --}}
                            <form class="no-print" action="{{ route('student.mock-boards.link-historical-exam', $item['mock_board_id']) }}" method="POST" style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                @csrf
                                <select name="historical_board_exam_result_id" class="hb-select" style="height:36px; font-size:13px; min-width:240px;" onchange="this.form.submit()">
                                    <option value="">-- Select Historical Benchmark --</option>
                                    @foreach($historicalOptions->where('program', strtolower($item['program'])) as $opt)
                                        <option value="{{ $opt->id }}" {{ $item['historical_id'] == $opt->id ? 'selected' : '' }}>
                                            {{ $opt->exam_label }} ({{ $opt->exam_period_or_year }}) — {{ $opt->passing_rate }}%
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="hb-btn-submit" style="height:36px; padding:0 12px; font-size:13px; background:#475569;">
                                    <i class="fas fa-link"></i> Link
                                </button>
                            </form>
                        </div>

                        {{-- Stats comparison cards --}}
                        <div class="hb-stats-row">
                            {{-- Reviso Post-Test Passing Rate --}}
                            <div class="hb-stat-box">
                                <div class="hb-stat-label">Reviso Post-Test Rate</div>
                                <div class="hb-stat-val" style="color: #245E55;">
                                    {{ number_format($item['reviso_passing_rate'], 1) }}%
                                </div>
                                <div class="hb-stat-sub">
                                    {{ $item['students_passed'] }} / {{ $item['students_attempted'] }} students passed
                                </div>
                            </div>

                            {{-- Real Licensure Exam Benchmark --}}
                            <div class="hb-stat-box">
                                <div class="hb-stat-label">PRC Physical Exam Benchmark</div>
                                @if($hasBenchmark)
                                    <div class="hb-stat-val">
                                        {{ number_format($item['historical_passing_rate'], 1) }}%
                                    </div>
                                    <div class="hb-stat-sub" style="color:#475569;">
                                        {{ $item['historical_label'] }}
                                    </div>
                                @else
                                    <div class="hb-stat-val" style="font-size:16px; color:#94a3b8; padding-top:4px;">
                                        Not Linked Yet
                                    </div>
                                    <div class="hb-stat-sub">
                                        Choose a benchmark above
                                    </div>
                                @endif
                            </div>

                            {{-- Variance / Difference --}}
                            @if($hasBenchmark)
                                @php
                                    $delta = $item['delta'];
                                    $isPositive = $delta > 0;
                                    $isNeutral = $delta == 0;
                                @endphp
                                <div class="hb-delta-box {{ $isPositive ? 'positive' : ($isNeutral ? 'neutral' : 'negative') }}">
                                    <div class="hb-stat-label" style="color: inherit;">Variance vs. Benchmark</div>
                                    <div class="hb-stat-val" style="color: inherit;">
                                        @if($isPositive)
                                            <i class="fas fa-arrow-up" style="font-size:18px;"></i> +{{ number_format($delta, 1) }} pts
                                        @elseif($isNeutral)
                                            ±0.0 pts
                                        @else
                                            <i class="fas fa-arrow-down" style="font-size:18px;"></i> {{ number_format($delta, 1) }} pts
                                        @endif
                                    </div>
                                    <div class="hb-stat-sub" style="color: inherit; opacity: 0.85; font-weight:500;">
                                        {{ $isPositive ? 'Above real board exam rate' : ($isNeutral ? 'Equal to board exam rate' : 'Below real board exam rate') }}
                                    </div>
                                </div>
                            @else
                                <div class="hb-delta-box neutral">
                                    <div class="hb-stat-label">Variance</div>
                                    <div class="hb-stat-val" style="font-size:16px; color:#94a3b8;">
                                        —
                                    </div>
                                    <div class="hb-stat-sub">
                                        Link an exam to calculate
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- SECTION 3: SAVED HISTORICAL BENCHMARKS DATABASE --}}
    <div class="hb-card">
        <div class="hb-card-head">
            <h3 class="hb-card-title">
                <i class="fas fa-database"></i> Saved Physical Board Exam Records (PRC Reference Data)
            </h3>
            <span style="font-size:13px; color:#64748b;">
                Total: {{ count($results) }} records
            </span>
        </div>

        @if(empty($results) || count($results) === 0)
            <div class="hb-empty">
                <i class="fas fa-file-invoice" style="font-size: 42px; opacity: 0.3; margin-bottom: 12px; display: block;"></i>
                <p style="font-size: 15px; margin: 0;">No historical board exam records saved yet.</p>
            </div>
        @else
            <div style="overflow-x:auto;">
                <table class="hb-table">
                    <thead>
                        <tr>
                            <th>Program</th>
                            <th>Exam Label</th>
                            <th>Period / Year</th>
                            <th>Passed / Examinees</th>
                            <th>Passing Rate</th>
                            <th>Source Note</th>
                            @if(auth()->user() && in_array(auth()->user()->role, ['admin', 'superadmin'], true))
                                <th class="no-print" style="text-align:right;">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($results as $result)
                            @php
                                $pClass = match(strtolower($result['program'])) {
                                    'psychology', 'psych' => 'hb-badge-psych',
                                    'education', 'educ' => 'hb-badge-educ',
                                    default => 'hb-badge-acc'
                                };
                            @endphp
                            <tr>
                                <td>
                                    <span class="hb-badge {{ $pClass }}">{{ ucfirst($result['program']) }}</span>
                                </td>
                                <td style="font-weight:600; color:#0f172a;">
                                    {{ $result['exam_label'] }}
                                </td>
                                <td>{{ $result['exam_period_or_year'] }}</td>
                                <td>
                                    <strong>{{ number_format($result['passed_count']) }}</strong> / {{ number_format($result['total_examinees']) }}
                                </td>
                                <td>
                                    <span style="font-size:15px; font-weight:700; color:#245E55;">
                                        {{ $result['passing_rate'] }}%
                                    </span>
                                </td>
                                <td style="color:#64748b; font-size:13px;">
                                    {{ $result['source_note'] ?? '—' }}
                                </td>
                                @if(auth()->user() && in_array(auth()->user()->role, ['admin', 'superadmin'], true))
                                    <td class="no-print" style="text-align:right;">
                                        <form action="{{ route('historical-board-exams.destroy', $result['id']) }}" method="POST" onsubmit="return confirm('Delete this historical exam record?')" style="display:inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" style="background:#fee2e2; color:#dc2626; border:none; padding:6px 12px; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:5px; transition:background 0.15s;">
                                                <i class="fas fa-trash-alt"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Official PCC Printable Signature Block --}}
    <div class="hb-print-signatures">
        <div style="text-align:center;">
            <div style="border-bottom:1.5px solid #0f172a;height:36px;margin-bottom:6px;"></div>
            <div style="font-weight:700;font-size:12px;color:#0f172a;text-transform:uppercase;">{{ auth()->user()->name ?? 'System Administrator' }}</div>
            <div style="font-size:11px;color:#64748b;">Prepared By &bull; System Administrator</div>
        </div>
        <div style="text-align:center;">
            <div style="border-bottom:1.5px solid #0f172a;height:36px;margin-bottom:6px;"></div>
            <div style="font-weight:700;font-size:12px;color:#0f172a;text-transform:uppercase;">Program Chair / Coordinator</div>
            <div style="font-size:11px;color:#64748b;">Reviewed &amp; Verified By</div>
        </div>
        <div style="text-align:center;">
            <div style="border-bottom:1.5px solid #0f172a;height:36px;margin-bottom:6px;"></div>
            <div style="font-weight:700;font-size:12px;color:#0f172a;text-transform:uppercase;">College Dean</div>
            <div style="font-size:11px;color:#64748b;">Noted &amp; Approved By</div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/exceljs@4.4.0/dist/exceljs.min.js"></script>
<script>
    async function exportHistoricalBenchmarksExcel() {
        const wb = new ExcelJS.Workbook();
        wb.creator = 'Reviso LMS - Pasig Catholic College';
        wb.created = new Date();

        let logoId = null;
        try {
            const resp = await fetch("{{ asset('assets/img/brand/pcc_logo.png') }}");
            if (resp.ok) {
                const buf = await resp.arrayBuffer();
                logoId = wb.addImage({ buffer: buf, extension: 'png' });
            }
        } catch (e) {
            console.warn('Could not load PCC logo for Excel export:', e);
        }

        function styleHeaderWithLogo(ws, title, subtitle) {
            ws.mergeCells('A1:F1');
            ws.mergeCells('A2:F2');
            ws.mergeCells('A3:F3');

            ws.getCell('A1').value = 'PASIG CATHOLIC COLLEGE — COLLEGE DEPARTMENT';
            ws.getCell('A1').font = { name: 'Calibri', size: 13, bold: true, color: { argb: 'FF245E55' } };
            ws.getCell('A1').alignment = { vertical: 'middle', horizontal: 'left', indent: 7 };

            ws.getCell('A2').value = title;
            ws.getCell('A2').font = { name: 'Calibri', size: 11, bold: true, color: { argb: 'FF0F172A' } };
            ws.getCell('A2').alignment = { vertical: 'middle', horizontal: 'left', indent: 7 };

            ws.getCell('A3').value = subtitle;
            ws.getCell('A3').font = { name: 'Calibri', size: 9.5, italic: true, color: { argb: 'FF64748B' } };
            ws.getCell('A3').alignment = { vertical: 'middle', horizontal: 'left', indent: 7 };

            ws.getRow(1).height = 20;
            ws.getRow(2).height = 18;
            ws.getRow(3).height = 18;

            if (logoId !== null) {
                ws.addImage(logoId, {
                    tl: { col: 0.08, row: 0.12 },
                    ext: { width: 50, height: 50 },
                    editAs: 'oneCell'
                });
            }
        }

        const ws1 = wb.addWorksheet('Mock Board vs PRC Variance');
        ws1.columns = [
            { width: 34 },
            { width: 16 },
            { width: 24 },
            { width: 22 },
            { width: 26 },
            { width: 20 },
        ];
        styleHeaderWithLogo(
            ws1,
            'Mock Board Post-Test vs. PRC Physical Licensure Benchmark Variance',
            'Generated on ' + new Date().toLocaleString()
        );

        ws1.addRow([]);
        const h1 = ws1.addRow(['Mock Board Title', 'Program', 'Target Phase', 'Reviso Post-Test Rate', 'PRC Benchmark', 'Variance (pts)']);
        h1.eachCell(cell => {
            cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF245E55' } };
            cell.font = { bold: true, color: { argb: 'FFFFFFFF' }, size: 10 };
        });

        const comparisons = @json($comparisons);
        comparisons.forEach(item => {
            const deltaStr = item.historical_id
                ? ((item.delta > 0 ? '+' : '') + Number(item.delta).toFixed(1) + ' pts')
                : 'Not Linked';
            ws1.addRow([
                item.mock_board_title || '—',
                (item.program || '').toUpperCase(),
                item.phase_names || '—',
                Number(item.reviso_passing_rate || 0).toFixed(1) + '% (' + (item.students_passed || 0) + '/' + (item.students_attempted || 0) + ')',
                item.historical_id ? (Number(item.historical_passing_rate || 0).toFixed(1) + '% — ' + (item.historical_label || '')) : 'Not Linked',
                deltaStr
            ]);
        });

        const ws2 = wb.addWorksheet('Saved PRC Reference Data');
        ws2.columns = [
            { width: 18 },
            { width: 34 },
            { width: 16 },
            { width: 22 },
            { width: 18 },
            { width: 32 },
        ];
        styleHeaderWithLogo(
            ws2,
            'Saved Official PRC Physical Board Exam Reference Records',
            'Generated on ' + new Date().toLocaleString()
        );

        ws2.addRow([]);
        const h2 = ws2.addRow(['Program', 'Exam Label', 'Period / Year', 'Passed / Examinees', 'Passing Rate (%)', 'Source Note']);
        h2.eachCell(cell => {
            cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF245E55' } };
            cell.font = { bold: true, color: { argb: 'FFFFFFFF' }, size: 10 };
        });

        const results = @json($results);
        results.forEach(r => {
            ws2.addRow([
                (r.program || '').toUpperCase(),
                r.exam_label || '—',
                r.exam_period_or_year || '—',
                (r.passed_count ?? 0) + ' / ' + (r.total_examinees ?? 0),
                (r.passing_rate ?? 0) + '%',
                r.source_note || '—'
            ]);
        });

        const buf = await wb.xlsx.writeBuffer();
        const blob = new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'PCC_Historical_PRC_Benchmarks_' + new Date().toISOString().slice(0, 10) + '.xlsx';
        link.click();
    }
</script>
@endsection
