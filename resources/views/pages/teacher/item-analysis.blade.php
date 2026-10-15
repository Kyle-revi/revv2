@extends('layouts.appTeach')

@section('title', 'Psychometric Item Analysis - ' . ($module->title ?? 'Module'))
@section('page-heading', 'Item Analysis')

@section('header-actions')
    @if($class)
        <a href="{{ route('student.performance', $class) }}" class="rv-btn" style="text-decoration:none;">
            <i class="fas fa-arrow-left"></i> Back to Class Performance
        </a>
    @endif
    <button type="button" class="rv-btn rv-btn-secondary" onclick="window.print()" title="Print or Save Official Examination Quality Report as PDF">
        <i class="fas fa-print"></i> Print Report (PDF)
    </button>
    <button type="button" class="rv-btn rv-btn-primary" id="btnRecalculateAnalysis" onclick="triggerRecalculate()">
        <i class="fas fa-sync-alt" id="recalculateIcon"></i> Recalculate Analysis
    </button>
@endsection

@section('content')
<style>
    /* Embed mode when inside iframe */
    body.is-embedded .rv-sidebar,
    body.is-embedded .rv-topbar {
        display: none !important;
    }
    body.is-embedded .rv-main {
        margin-left: 0 !important;
        min-height: auto !important;
    }
    body.is-embedded .rv-content {
        padding: 16px !important;
    }

    .ia-wrapper {
        display: flex;
        flex-direction: column;
        gap: 20px;
        max-width: 1400px;
        margin: 0 auto;
    }

    /* Breadcrumb & Subtitle */
    .ia-header-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        background: #fff;
        padding: 16px 20px;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
    }
    .ia-header-title {
        font-size: 22px;
        font-weight: 500;
        color: #111;
        margin-bottom: 4px;
    }
    .ia-header-meta {
        font-size: 14px;
        color: #6b7280;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    /* Stage Tabs */
    .ia-stage-tabs {
        display: inline-flex;
        background: #f3f4f6;
        padding: 4px;
        border-radius: 10px;
        gap: 4px;
    }
    .ia-stage-tab {
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        color: #4b5563;
        text-decoration: none;
        transition: all 0.15s ease;
    }
    .ia-stage-tab.active {
        background: #fff;
        color: #111;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    }

    /* Confidence Badges */
    .confidence-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 12px;
        font-weight: 500;
        padding: 3px 10px;
        border-radius: 99px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .confidence-full { background: #dcfce7; color: #166534; }
    .confidence-low { background: #fef3c7; color: #92400e; }
    .confidence-insufficient { background: #fee2e2; color: #991b1b; }

    /* KPI Cards */
    .ia-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
    }
    .ia-kpi-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 18px 20px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-shadow: 0 1px 2px rgba(0,0,0,0.02);
    }
    .ia-kpi-label {
        font-size: 12px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #6b7280;
        margin-bottom: 6px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .ia-kpi-val {
        font-size: 32px;
        font-weight: 500;
        color: #111;
        line-height: 1.1;
    }
    .ia-kpi-sub {
        font-size: 13px;
        color: #6b7280;
        margin-top: 6px;
    }

    /* Verdict Summary Bar */
    .ia-summary-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 16px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 14px;
    }
    .ia-summary-counts {
        display: flex;
        gap: 14px;
        flex-wrap: wrap;
    }
    .ia-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
    }
    .ia-pill-retain { background: #dcfce7; color: #166534; }
    .ia-pill-revise { background: #fef3c7; color: #92400e; }
    .ia-pill-reject { background: #fee2e2; color: #991b1b; }
    .ia-pill-insufficient { background: #f3f4f6; color: #4b5563; }

    /* Section Cards */
    .ia-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.02);
    }
    .ia-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        flex-wrap: wrap;
        gap: 12px;
    }
    .ia-card-title {
        font-size: 19px;
        font-weight: 500;
        color: #111;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* Table Toolbar */
    .ia-toolbar {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .ia-search-input {
        padding: 7px 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 13px;
        outline: none;
        width: 240px;
    }
    .ia-filter-select {
        padding: 7px 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 13px;
        background: #fff;
        outline: none;
    }

    /* Main Table */
    .ia-table-wrapper {
        overflow-x: auto;
        border: 1px solid #f3f4f6;
        border-radius: 8px;
    }
    .ia-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        text-align: left;
    }
    .ia-table th {
        background: #f9fafb;
        color: #4b5563;
        font-weight: 500;
        padding: 10px 14px;
        border-bottom: 1px solid #e5e7eb;
        white-space: nowrap;
    }
    .ia-table td {
        padding: 12px 14px;
        border-bottom: 1px solid #f3f4f6;
        color: #1f2937;
        vertical-align: middle;
    }
    .ia-table tbody tr {
        cursor: pointer;
        transition: background-color 0.1s ease;
    }
    .ia-table tbody tr:hover {
        background-color: #f8fafc;
    }

    /* Badges */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .badge-diff-vdiff { background: #fee2e2; color: #991b1b; }
    .badge-diff-diff  { background: #fef3c7; color: #92400e; }
    .badge-diff-avg   { background: #e0f2fe; color: #0369a1; }
    .badge-diff-easy  { background: #dcfce7; color: #166534; }
    .badge-diff-veasy { background: #f3e8ff; color: #6b21a8; }

    .badge-disc-vgood { background: #dcfce7; color: #166534; }
    .badge-disc-good  { background: #e0f2fe; color: #0369a1; }
    .badge-disc-marg  { background: #fef3c7; color: #92400e; }
    .badge-disc-poor  { background: #fee2e2; color: #991b1b; }
    .badge-disc-neg   { background: #991b1b; color: #fff; font-weight: 500; }

    .badge-verdict-retain  { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
    .badge-verdict-revise  { background: #fef3c7; color: #92400e; border: 1px solid #fde047; }
    .badge-verdict-reject  { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    .badge-verdict-insuf   { background: #f3f4f6; color: #6b7280; border: 1px solid #d1d5db; }

    .alert-tag {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        font-size: 11px;
        font-weight: 500;
        padding: 2px 6px;
        border-radius: 4px;
        margin: 2px 2px 0 0;
        white-space: nowrap;
    }
    .alert-tag-danger { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
    .alert-tag-warning { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }

    /* AI Calibration Card */
    .calibration-row {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 10px;
    }
    .calibration-label {
        width: 100px;
        font-size: 13px;
        font-weight: 500;
        color: #374151;
        flex-shrink: 0;
    }
    .calibration-bar-track {
        flex: 1;
        height: 20px;
        background: #f3f4f6;
        border-radius: 6px;
        overflow: hidden;
        position: relative;
    }
    .calibration-bar-fill {
        height: 100%;
        border-radius: 6px;
        transition: width 0.4s ease;
    }
    .calibration-val {
        width: 80px;
        font-size: 13px;
        font-weight: 500;
        color: #111;
        text-align: right;
        flex-shrink: 0;
    }

    /* Diagnostic Drawer / Modal */
    .ia-modal-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .ia-modal-dialog {
        background: #fff;
        border-radius: 14px;
        width: 860px;
        max-width: 95vw;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        display: flex;
        flex-direction: column;
    }
    .ia-modal-header {
        padding: 18px 24px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        position: sticky;
        top: 0;
        background: #fff;
        z-index: 10;
    }
    .ia-modal-body {
        padding: 24px;
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    .ia-modal-footer {
        padding: 16px 24px;
        border-top: 1px solid #e5e7eb;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        background: #f9fafb;
    }

    /* Option table inside modal */
    .opt-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    .opt-table th {
        background: #f9fafb;
        color: #4b5563;
        font-weight: 500;
        padding: 8px 12px;
        border-bottom: 1px solid #e5e7eb;
        text-align: left;
    }
    .opt-table td {
        padding: 10px 12px;
        border-bottom: 1px solid #f3f4f6;
        vertical-align: middle;
    }
    .opt-row-key {
        background: #f0fdf4 !important;
        font-weight: 500;
    }
    .opt-row-problem {
        background: #fff1f2 !important;
    }

    .ia-wrapper strong,
    .ia-wrapper b,
    .ia-modal-dialog strong,
    .ia-modal-dialog b {
        font-weight: 500;
    }

    .ia-print-header,
    .ia-print-signatures {
        display: none;
    }

    @media print {
        @page {
            size: portrait;
            margin: 12mm 10mm;
        }
        body {
            background: #fff !important;
            color: #000 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .rv-sidebar, .rv-topbar, .rv-header-actions, .rv-btn, .ia-stage-tabs,
        #diagnosticModalBackdrop, #btnRecalculateAnalysis, .sp-refresh-btn,
        .ia-card-header input, .ia-card-header select {
            display: none !important;
        }
        .rv-main {
            margin: 0 !important;
            padding: 0 !important;
        }
        .rv-content {
            padding: 0 !important;
        }
        .ia-wrapper {
            max-width: 100% !important;
            gap: 12px !important;
        }
        .ia-print-header {
            display: block !important;
        }
        .ia-print-signatures {
            display: block !important;
            page-break-inside: avoid;
        }
        .ia-header-info {
            display: none !important;
        }
        .ia-kpi-card, .ia-summary-card, .ia-card {
            box-shadow: none !important;
            border: 1px solid #cbd5e1 !important;
            page-break-inside: avoid;
        }
        .ia-kpi-grid {
            grid-template-columns: repeat(3, 1fr) !important;
            gap: 8px !important;
        }
        .ia-kpi-card {
            padding: 8px 10px !important;
        }
        .ia-kpi-val {
            font-size: 20px !important;
        }
        .ia-kpi-label {
            font-size: 10px !important;
            margin-bottom: 2px !important;
        }
        .ia-kpi-sub {
            font-size: 10px !important;
            margin-top: 2px !important;
        }
        table {
            width: 100% !important;
            border-collapse: collapse !important;
        }
        th, td {
            border: 1px solid #cbd5e1 !important;
            padding: 6px 8px !important;
            font-size: 8.5pt !important;
        }
        th {
            background: #f1f5f9 !important;
            color: #0f172a !important;
        }
        .ia-table tr {
            page-break-inside: avoid;
        }
        /* Hide inspect column on print */
        .ia-table th:last-child,
        .ia-table td:last-child {
            display: none !important;
        }
    }
</style>

<div class="ia-wrapper">

    {{-- Institutional Print Header (Shown only during Print / PDF generation) --}}
    <div class="ia-print-header">
        <div style="display:flex; align-items:center; gap:16px; border-bottom:2px solid #0f172a; padding-bottom:12px; margin-bottom:14px;">
            <img src="{{ asset('assets/img/brand/pcc_logo.png') }}" alt="Pasig Catholic College" style="width:68px; height:68px; object-fit:contain;">
            <div style="flex:1;">
                <div style="font-size:15px; font-weight:800; color:#0f172a; letter-spacing:0.5px; text-transform:uppercase;">
                    Pasig Catholic College
                </div>
                <div style="font-size:11px; color:#475569; font-weight:600; text-transform:uppercase; margin-bottom:2px;">
                    College Department &bull; Center for Teaching & Learning Assessment
                </div>
                <h1 style="font-size:15px; font-weight:700; margin:0; text-transform:uppercase; letter-spacing:0.3px; color:#0f172a;">
                    Examination Quality & Psychometric Validation Report
                </h1>
                <p style="font-size:11px; color:#475569; margin:2px 0 0 0;">
                    Classical Test Theory (CTT) Diagnostic, Item Calibration, & Question Verification
                </p>
            </div>
            <div style="text-align:right; font-size:11px; color:#334155; white-space:nowrap;">
                <p style="margin:0;"><strong>Report Date:</strong> {{ now()->format('F d, Y h:i A') }}</p>
                <p style="margin:2px 0 0 0;"><strong>Module:</strong> {{ $module->title }}</p>
                @if($class)
                    <p style="margin:2px 0 0 0;"><strong>Class:</strong> {{ $class->name }} ({{ $class->code }})</p>
                @endif
                <p style="margin:2px 0 0 0;"><strong>Stage:</strong> {{ strtoupper($currentStage ?: 'Pre-Assessment') }}</p>
            </div>
        </div>
    </div>

    {{-- Breadcrumb & Title Bar --}}
    <div class="ia-header-info">
        <div>
            <div class="ia-header-title">
                <i class="fas fa-microscope" style="color:#245E55;margin-right:6px;"></i>
                Psychometric Item Analysis
            </div>
            <div class="ia-header-meta">
                <span><strong>Module:</strong> {{ $module->title }}</span>
                @if($class)
                    <span>&bull;</span>
                    <span><strong>Class:</strong> {{ $class->name }}</span>
                @endif
                @if($testStats['analyzed_at'])
                    <span>&bull;</span>
                    <span><strong>Analyzed:</strong> {{ \Carbon\Carbon::parse($testStats['analyzed_at'])->diffForHumans() }}</span>
                @endif
            </div>
        </div>

        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            {{-- Stage Switcher (Pre vs Post test if present) --}}
            @if(count($availableStages) > 0)
                <div class="ia-stage-tabs">
                    <a href="{{ route('modules.item-analysis', ['module' => $module->id]) }}"
                       class="ia-stage-tab {{ empty($currentStage) ? 'active' : '' }}">
                        Standard / All
                    </a>
                    @foreach($availableStages as $stg)
                        <a href="{{ route('modules.item-analysis', ['module' => $module->id, 'stage' => $stg]) }}"
                           class="ia-stage-tab {{ $currentStage === $stg ? 'active' : '' }}">
                            {{ $stg === 'pre_test' ? 'Pre-Test' : ($stg === 'post_test' ? 'Post-Test' : ucfirst($stg)) }}
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- Sample size confidence badge --}}
            @php
                $nExaminees = $testStats['n_examinees'] ?? 0;
                $conf = 'insufficient';
                if ($nExaminees >= config('item_analysis.min_n_full_confidence', 30)) {
                    $conf = 'full';
                } elseif ($nExaminees >= config('item_analysis.min_n_insufficient', 10)) {
                    $conf = 'low';
                }
            @endphp
            @if($conf === 'full')
                <span class="confidence-badge confidence-full" title="N >= 30: Sample size supports robust item calibration">
                    <i class="fas fa-check-circle"></i> High Confidence (N = {{ $nExaminees }})
                </span>
            @elseif($conf === 'low')
                <span class="confidence-badge confidence-low" title="10 <= N < 30: Interpret item indices with caution">
                    <i class="fas fa-exclamation-triangle"></i> Low Confidence (N = {{ $nExaminees }})
                </span>
            @else
                <span class="confidence-badge confidence-insufficient" title="N < 10: Insufficient sample size for formal psychometric decisions">
                    <i class="fas fa-info-circle"></i> Insufficient Data (N = {{ $nExaminees }})
                </span>
            @endif
        </div>
    </div>

    {{-- Screen 1: Executive KPI Cards --}}
    <div class="ia-kpi-grid">
        <div class="ia-kpi-card">
            <div class="ia-kpi-label">
                <span>Total Examinees</span>
                <i class="fas fa-users" style="color:#9ca3af;"></i>
            </div>
            <div class="ia-kpi-val">{{ $testStats['n_examinees'] ?? 0 }}</div>
            <div class="ia-kpi-sub">
                <i class="fas fa-filter"></i> Policy: {{ ucfirst(config('item_analysis.attempt_policy', 'first')) }} attempt
            </div>
        </div>

        <div class="ia-kpi-card">
            <div class="ia-kpi-label">
                <span>Mean Score & Variance</span>
                <i class="fas fa-chart-line" style="color:#9ca3af;"></i>
            </div>
            <div class="ia-kpi-val">
                {{ $testStats['mean_score'] !== null ? number_format($testStats['mean_score'], 1) : '—' }}
                <span style="font-size:16px;font-weight:500;color:#6b7280;">/ {{ $testStats['n_items'] ?? 0 }}</span>
            </div>
            <div class="ia-kpi-sub">
                SD (&sigma;) = {{ $testStats['sd_score'] !== null ? number_format($testStats['sd_score'], 2) : '0.00' }}
            </div>
        </div>

        <div class="ia-kpi-card">
            <div class="ia-kpi-label">
                <span>Test Reliability (KR-20)</span>
                <i class="fas fa-shield-alt" style="color:#9ca3af;"></i>
            </div>
            <div class="ia-kpi-val">
                {{ $testStats['kr20'] !== null ? number_format($testStats['kr20'], 2) : '—' }}
            </div>
            <div class="ia-kpi-sub">
                @if($testStats['kr20'] !== null)
                    <strong style="color: {{ $testStats['kr20'] >= 0.70 ? '#166534' : '#991b1b' }};">
                        {{ $testStats['kr20_interpretation'] ?? 'Calculated' }}
                    </strong>
                    ({{ $testStats['kr20'] >= 0.70 ? 'Benchmark Met' : 'Low Consistency' }})
                @else
                    <span>{{ $testStats['kr20_interpretation'] ?? 'Variance required' }}</span>
                @endif
            </div>
        </div>

        <div class="ia-kpi-card">
            <div class="ia-kpi-label">
                <span>AI Label Calibration</span>
                <i class="fas fa-robot" style="color:#9ca3af;"></i>
            </div>
            <div class="ia-kpi-val">
                {{ $testStats['label_accuracy'] !== null ? number_format($testStats['label_accuracy'], 0) . '%' : '—' }}
            </div>
            <div class="ia-kpi-sub">
                Empirical agreement with generated difficulty
            </div>
        </div>
    </div>

    {{-- Verdict Counts Bar --}}
    @php
        $vCounts = $testStats['verdict_counts'] ?? ['retain' => 0, 'revise' => 0, 'reject' => 0, 'insufficient' => 0];
    @endphp
    <div class="ia-summary-card">
        <div>
            <span style="font-weight:500;color:#111;font-size:14px;margin-right:12px;">Item Health Summary:</span>
            <span style="font-size:13px;color:#6b7280;">Based on Classical Test Theory (Difficulty [p] and Discrimination [D] indices)</span>
        </div>
        <div class="ia-summary-counts">
            <span class="ia-pill ia-pill-retain" title="High discriminating questions with optimal difficulty">
                <i class="fas fa-check-circle"></i> Retain: <strong>{{ $vCounts['retain'] ?? 0 }}</strong>
            </span>
            <span class="ia-pill ia-pill-revise" title="Items needing distractor rewriting or difficulty adjustment">
                <i class="fas fa-edit"></i> Revise: <strong>{{ $vCounts['revise'] ?? 0 }}</strong>
            </span>
            <span class="ia-pill ia-pill-reject" title="Defective items or possible incorrect answer keys">
                <i class="fas fa-times-circle"></i> Reject: <strong>{{ $vCounts['reject'] ?? 0 }}</strong>
            </span>
            @if(($vCounts['insufficient'] ?? 0) > 0)
                <span class="ia-pill ia-pill-insufficient" title="Needs at least 10 student completions for a formal verdict">
                    <i class="fas fa-clock"></i> Insufficient Data: <strong>{{ $vCounts['insufficient'] ?? 0 }}</strong>
                </span>
            @endif
        </div>
    </div>

    {{-- Screen 4: AI Difficulty Calibration Card (Panel Defense Evidence) --}}
    @php
        $meanPByLabel = $testStats['mean_p_by_label'] ?? [];
        $pEasy = $meanPByLabel['Easy'] ?? null;
        $pAvg  = $meanPByLabel['Average'] ?? null;
        $pDiff = $meanPByLabel['Difficult'] ?? null;
    @endphp
    @if($pEasy !== null || $pAvg !== null || $pDiff !== null)
    <div class="ia-card">
        <div class="ia-card-header">
            <div>
                <div class="ia-card-title">
                    <i class="fas fa-balance-scale" style="color:#245E55;"></i>
                    AI Difficulty Calibration Verification
                </div>
                <p style="font-size:13px;color:#6b7280;margin-top:2px;">
                    Empirical verification of generative model difficulty calibration. Monotonic validity requires Mean p(Easy) > Mean p(Average) > Mean p(Difficult) (higher p indicates an easier question).
                </p>
            </div>
            @if($pEasy !== null && $pAvg !== null && $pDiff !== null && $pEasy >= $pAvg && $pAvg >= $pDiff)
                <span class="badge badge-verdict-retain" style="font-size:12px;padding:5px 12px;">
                    <i class="fas fa-check-double"></i> Monotonic Gradient Verified
                </span>
            @endif
        </div>

        <div style="max-width: 700px; margin-top: 10px;">
            <div class="calibration-row">
                <div class="calibration-label">Easy Target:</div>
                <div class="calibration-bar-track">
                    <div class="calibration-bar-fill" style="width: {{ ($pEasy ?? 0) * 100 }}%; background: #10b981;"></div>
                </div>
                <div class="calibration-val">{{ $pEasy !== null ? 'p = ' . number_format($pEasy, 2) : 'N/A' }}</div>
            </div>
            <div class="calibration-row">
                <div class="calibration-label">Average Target:</div>
                <div class="calibration-bar-track">
                    <div class="calibration-bar-fill" style="width: {{ ($pAvg ?? 0) * 100 }}%; background: #3b82f6;"></div>
                </div>
                <div class="calibration-val">{{ $pAvg !== null ? 'p = ' . number_format($pAvg, 2) : 'N/A' }}</div>
            </div>
            <div class="calibration-row">
                <div class="calibration-label">Difficult Target:</div>
                <div class="calibration-bar-track">
                    <div class="calibration-bar-fill" style="width: {{ ($pDiff ?? 0) * 100 }}%; background: #f59e0b;"></div>
                </div>
                <div class="calibration-val">{{ $pDiff !== null ? 'p = ' . number_format($pDiff, 2) : 'N/A' }}</div>
            </div>
        </div>
    </div>
    @endif

    {{-- Screen 2: Interactive Item Analysis Table --}}
    <div class="ia-card">
        <div class="ia-card-header">
            <div class="ia-card-title">
                <i class="fas fa-list-ol" style="color:#245E55;"></i>
                Item Metrics & Discrimination Matrix ({{ count($itemStats) }} Items)
            </div>
            <div class="ia-toolbar">
                <div class="ia-search-box">
                    <input type="text" id="itemSearchInput" class="ia-search-input" placeholder="Search question stem..." onkeyup="filterItemsTable()">
                </div>
                <select id="verdictFilterSelect" class="ia-filter-select" onchange="filterItemsTable()">
                    <option value="all">All Verdicts</option>
                    <option value="retain">Retain Only</option>
                    <option value="revise">Revise Only</option>
                    <option value="reject">Reject Only</option>
                    <option value="flagged">Flagged Anomalies ⚠️</option>
                </select>
            </div>
        </div>

        @if(count($itemStats) === 0)
            <div style="text-align:center;padding:40px 20px;color:#9ca3af;">
                <i class="fas fa-clipboard-list" style="font-size:36px;margin-bottom:12px;display:block;"></i>
                <p style="font-size:16px;font-weight:500;color:#374151;">No questions found or no attempts submitted yet.</p>
                <p style="font-size:14px;margin-top:4px;">Students must submit at least one attempt to compute item psychometrics.</p>
            </div>
        @else
            <div class="ia-table-wrapper">
                <table class="ia-table" id="itemsTable">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th style="min-width:280px;">Question Stem</th>
                            <th>AI Label</th>
                            <th>Diff (p)</th>
                            <th>Discrim (D)</th>
                            <th>r_pb</th>
                            <th>DE</th>
                            <th>Verdict</th>
                            <th style="min-width:180px;">Diagnostic Flags</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($itemStats as $idx => $item)
                            @php
                                $flags = $item['flags'] ?? [];
                                $hasFlags = count($flags) > 0;
                                $v = strtolower($item['verdict'] ?? 'insufficient');
                                $dIndex = $item['d_index'];
                                $pIndex = $item['p_index'];
                            @endphp
                            <tr data-verdict="{{ $v }}"
                                data-has-flags="{{ $hasFlags ? '1' : '0' }}"
                                onclick="openItemDrawer({{ $idx }})">
                                <td style="font-weight:500;color:#6b7280;">{{ $idx + 1 }}</td>
                                <td>
                                    <div style="font-weight:500;color:#111;line-height:1.4;">
                                        {{ \Illuminate\Support\Str::limit($item['question_text'], 90) }}
                                    </div>
                                    <span style="font-size:11px;color:#9ca3af;">Key: [ <strong>{{ $item['correct_option'] }}</strong> ]</span>
                                </td>
                                <td>
                                    @if($item['ai_difficulty_label'])
                                        <span class="badge" style="background:#f3f4f6;color:#374151;">
                                             {{ $item['ai_difficulty_label'] }}
                                        </span>
                                    @else
                                        <span style="color:#9ca3af;">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($pIndex !== null)
                                        <div style="font-weight:500;color:#111;">{{ number_format($pIndex, 2) }}</div>
                                        @php
                                            $diffClass = 'badge-diff-avg';
                                            if ($pIndex <= 0.20) $diffClass = 'badge-diff-vdiff';
                                            elseif ($pIndex <= 0.40) $diffClass = 'badge-diff-diff';
                                            elseif ($pIndex <= 0.60) $diffClass = 'badge-diff-avg';
                                            elseif ($pIndex <= 0.80) $diffClass = 'badge-diff-easy';
                                            else $diffClass = 'badge-diff-veasy';
                                        @endphp
                                        <span class="badge {{ $diffClass }}">{{ $item['difficulty_interpretation'] }}</span>
                                    @else
                                        <span style="color:#9ca3af;">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($dIndex !== null)
                                        <div style="font-weight:500;color:#111;">{{ number_format($dIndex, 2) }}</div>
                                        @php
                                            $discClass = 'badge-disc-marg';
                                            if ($dIndex < 0) $discClass = 'badge-disc-neg';
                                            elseif ($dIndex < 0.20) $discClass = 'badge-disc-poor';
                                            elseif ($dIndex < 0.30) $discClass = 'badge-disc-marg';
                                            elseif ($dIndex < 0.40) $discClass = 'badge-disc-good';
                                            else $discClass = 'badge-disc-vgood';
                                        @endphp
                                        <span class="badge {{ $discClass }}">{{ $item['discrimination_interpretation'] }}</span>
                                    @else
                                        <span style="color:#9ca3af;">—</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $item['point_biserial'] !== null ? number_format($item['point_biserial'], 2) : '—' }}
                                </td>
                                <td>
                                    @if($item['distractor_efficiency'] !== null)
                                        <span style="font-weight:500;color: {{ $item['distractor_efficiency'] >= 66 ? '#166534' : '#b45309' }};">
                                            {{ number_format($item['distractor_efficiency'], 0) }}%
                                        </span>
                                    @else
                                        <span style="color:#9ca3af;">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($v === 'retain')
                                        <span class="badge badge-verdict-retain"><i class="fas fa-check"></i> Retain</span>
                                    @elseif($v === 'revise')
                                        <span class="badge badge-verdict-revise"><i class="fas fa-edit"></i> Revise</span>
                                    @elseif($v === 'reject')
                                        <span class="badge badge-verdict-reject"><i class="fas fa-times"></i> Reject</span>
                                    @else
                                        <span class="badge badge-verdict-insuf"><i class="fas fa-question"></i> Insufficient</span>
                                    @endif
                                </td>
                                <td>
                                    @if(in_array('negative_d', $flags))
                                        <span class="alert-tag alert-tag-danger"><i class="fas fa-exclamation-triangle"></i> D < 0 Key Alert</span>
                                    @endif
                                    @foreach($flags as $flg)
                                        @if(str_starts_with($flg, 'problem_distractor:'))
                                            <span class="alert-tag alert-tag-danger"><i class="fas fa-bomb"></i> Trap Opt {{ substr($flg, 19) }}</span>
                                        @elseif($flg === 'all_correct' || $flg === 'all_incorrect')
                                            <span class="alert-tag alert-tag-warning"><i class="fas fa-info-circle"></i> Zero Variance</span>
                                        @endif
                                    @endforeach
                                    @if(!$hasFlags)
                                        <span style="color:#9ca3af;font-size:12px;">No anomalies</span>
                                    @endif
                                </td>
                                <td style="text-align:right;">
                                    <button type="button" class="rv-btn" style="height:28px;padding:0 10px;font-size:12px;" onclick="event.stopPropagation(); openItemDrawer({{ $idx }});">
                                        <i class="fas fa-search-plus"></i> Inspect
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Official Academic Signatures (Printed on physical copy / PDF) --}}
    <div class="ia-print-signatures">
        <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:36px; text-align:center; margin-top:32px; padding-top:16px;">
            <div>
                <div style="border-bottom:1.5px solid #0f172a; height:45px; margin-bottom:6px;"></div>
                <p style="font-size:12px; font-weight:700; margin:0; color:#0f172a;">{{ Auth::user()->name ?? 'Subject Instructor' }}</p>
                <p style="font-size:11px; color:#475569; margin:2px 0 0 0;">Exam Author / Subject Instructor</p>
            </div>
            <div>
                <div style="border-bottom:1.5px solid #0f172a; height:45px; margin-bottom:6px;"></div>
                <p style="font-size:12px; font-weight:700; margin:0; color:#0f172a;">Department Chairperson</p>
                <p style="font-size:11px; color:#475569; margin:2px 0 0 0;">Psychometric Reviewer / Quality Assurance</p>
            </div>
            <div>
                <div style="border-bottom:1.5px solid #0f172a; height:45px; margin-bottom:6px;"></div>
                <p style="font-size:12px; font-weight:700; margin:0; color:#0f172a;">Dean of Academic Affairs</p>
                <p style="font-size:11px; color:#475569; margin:2px 0 0 0;">Institutional Academic Approval</p>
            </div>
        </div>
    </div>

</div>

{{-- Screen 3: Question Diagnostic Modal / Drawer --}}
<div class="ia-modal-backdrop" id="diagnosticModalBackdrop" onclick="closeItemDrawer(event)">
    <div class="ia-modal-dialog" id="diagnosticModalDialog" onclick="event.stopPropagation()">
        <div class="ia-modal-header">
            <div>
                <div style="font-size:18px;font-weight:500;color:#111;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-microscope" style="color:#245E55;"></i>
                    <span id="drawerTitle">Question Diagnostics</span>
                </div>
                <div style="font-size:13px;color:#6b7280;margin-top:2px;" id="drawerSubtitle">Detailed Distractor Traffic & Upper/Lower Breakdown</div>
            </div>
            <button type="button" class="rv-btn" style="border:none;background:transparent;font-size:18px;color:#6b7280;cursor:pointer;" onclick="closeItemDrawerDirect()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="ia-modal-body">
            {{-- Stem card --}}
            <div style="background:#f9fafb;padding:16px;border-radius:10px;border:1px solid #e5e7eb;">
                <div style="font-size:11px;font-weight:500;text-transform:uppercase;color:#6b7280;margin-bottom:6px;">Question Stem</div>
                <div id="drawerStemText" style="font-size:15px;font-weight:500;color:#111;line-height:1.5;"></div>
                <div style="margin-top:10px;display:flex;gap:12px;align-items:center;font-size:13px;">
                    <span>Correct Key: <strong id="drawerCorrectKey" style="color:#166534;"></strong></span>
                    <span>&bull;</span>
                    <span>AI Label: <strong id="drawerAiLabel"></strong></span>
                    <span>&bull;</span>
                    <span>Empirical Band: <strong id="drawerEmpiricalBand"></strong></span>
                </div>
            </div>

            {{-- Stat pills row --}}
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(130px, 1fr));gap:10px;">
                <div style="background:#fff;border:1px solid #e5e7eb;padding:10px;border-radius:8px;text-align:center;">
                    <div style="font-size:11px;color:#6b7280;font-weight:500;">Difficulty (p)</div>
                    <div id="drawerPValue" style="font-size:18px;font-weight:500;color:#111;"></div>
                    <div id="drawerPBadge" style="font-size:11px;"></div>
                </div>
                <div style="background:#fff;border:1px solid #e5e7eb;padding:10px;border-radius:8px;text-align:center;">
                    <div style="font-size:11px;color:#6b7280;font-weight:500;">Discrimination (D)</div>
                    <div id="drawerDValue" style="font-size:18px;font-weight:500;color:#111;"></div>
                    <div id="drawerDBadge" style="font-size:11px;"></div>
                </div>
                <div style="background:#fff;border:1px solid #e5e7eb;padding:10px;border-radius:8px;text-align:center;">
                    <div style="font-size:11px;color:#6b7280;font-weight:500;">Rest r_pb</div>
                    <div id="drawerRpbValue" style="font-size:18px;font-weight:500;color:#111;"></div>
                    <div style="font-size:11px;color:#6b7280;">Item-Rest Corr</div>
                </div>
                <div style="background:#fff;border:1px solid #e5e7eb;padding:10px;border-radius:8px;text-align:center;">
                    <div style="font-size:11px;color:#6b7280;font-weight:500;">Distractor Efficiency</div>
                    <div id="drawerDEValue" style="font-size:18px;font-weight:500;color:#111;"></div>
                    <div style="font-size:11px;color:#6b7280;">Functional options</div>
                </div>
                <div style="background:#fff;border:1px solid #e5e7eb;padding:10px;border-radius:8px;text-align:center;">
                    <div style="font-size:11px;color:#6b7280;font-weight:500;">Final Verdict</div>
                    <div id="drawerVerdictValue" style="font-size:16px;font-weight:500;margin-top:2px;"></div>
                </div>
            </div>

            {{-- Diagnostic Findings Alert Box --}}
            <div id="drawerFindingsBox" style="display:none;padding:14px 16px;border-radius:10px;border-left:4px solid #ef4444;background:#fef2f2;">
                <div style="font-size:13px;font-weight:500;color:#991b1b;margin-bottom:4px;">
                    <i class="fas fa-exclamation-circle"></i> Diagnostic Findings & Recommendations
                </div>
                <ul id="drawerFindingsList" style="margin:0;padding-left:18px;font-size:13px;color:#7f1d1d;line-height:1.6;">
                </ul>
            </div>

            {{-- Distractor Breakdown Table --}}
            <div>
                <div style="font-size:14px;font-weight:500;color:#111;margin-bottom:8px;">
                    Option Breakdown & Distractor Traffic (Kelley's 27% Upper vs Lower Groups)
                </div>
                <div style="overflow-x:auto;border:1px solid #e5e7eb;border-radius:8px;">
                    <table class="opt-table">
                        <thead>
                            <tr>
                                <th style="width:70px;">Option</th>
                                <th>Option Text</th>
                                <th style="width:110px;">Upper 27%</th>
                                <th style="width:110px;">Lower 27%</th>
                                <th style="width:110px;">Total (Rate)</th>
                                <th style="width:140px;">Distractor Status</th>
                            </tr>
                        </thead>
                        <tbody id="drawerOptionsTbody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="ia-modal-footer">
            <button type="button" class="rv-btn" onclick="closeItemDrawerDirect()">Close</button>
        </div>
    </div>
</div>

<script>
    if (window.self !== window.top) {
        document.body.classList.add('is-embedded');
    }

    // Data store passed from controller
    const allItems = @json($itemStats ?? []);
    const testLevelStats = @json($testStats ?? []);

    function filterItemsTable() {
        const query = (document.getElementById('itemSearchInput').value || '').toLowerCase();
        const verdictFilter = document.getElementById('verdictFilterSelect').value;
        const rows = document.querySelectorAll('#itemsTable tbody tr');

        rows.forEach(row => {
            const rowVerdict = row.getAttribute('data-verdict');
            const hasFlags = row.getAttribute('data-has-flags') === '1';
            const text = row.innerText.toLowerCase();

            let matchQuery = text.includes(query);
            let matchVerdict = true;

            if (verdictFilter === 'retain') {
                matchVerdict = (rowVerdict === 'retain');
            } else if (verdictFilter === 'revise') {
                matchVerdict = (rowVerdict === 'revise');
            } else if (verdictFilter === 'reject') {
                matchVerdict = (rowVerdict === 'reject');
            } else if (verdictFilter === 'flagged') {
                matchVerdict = hasFlags;
            }

            row.style.display = (matchQuery && matchVerdict) ? '' : 'none';
        });
    }

    function openItemDrawer(index) {
        const item = allItems[index];
        if (!item) return;

        document.getElementById('drawerTitle').innerText = 'Question Diagnostics (Item #' + (index + 1) + ')';
        document.getElementById('drawerStemText').innerText = item.question_text || '—';
        document.getElementById('drawerCorrectKey').innerText = item.correct_option || '—';
        document.getElementById('drawerAiLabel').innerText = item.ai_difficulty_label || 'Unlabeled';
        document.getElementById('drawerEmpiricalBand').innerText = item.empirical_band || '—';

        // P & D values
        document.getElementById('drawerPValue').innerText = item.p_index !== null ? Number(item.p_index).toFixed(2) : '—';
        document.getElementById('drawerPBadge').innerText = item.difficulty_interpretation || '';
        document.getElementById('drawerDValue').innerText = item.d_index !== null ? Number(item.d_index).toFixed(2) : '—';
        document.getElementById('drawerDBadge').innerText = item.discrimination_interpretation || '';
        document.getElementById('drawerRpbValue').innerText = item.point_biserial !== null ? Number(item.point_biserial).toFixed(2) : '—';
        document.getElementById('drawerDEValue').innerText = item.distractor_efficiency !== null ? Number(item.distractor_efficiency).toFixed(0) + '%' : '—';

        // Verdict
        const v = (item.verdict || 'insufficient').toUpperCase();
        let vColor = '#4b5563';
        if (v === 'RETAIN') vColor = '#166534';
        else if (v === 'REVISE') vColor = '#92400e';
        else if (v === 'REJECT') vColor = '#991b1b';
        document.getElementById('drawerVerdictValue').innerHTML = '<span style="color:' + vColor + ';">' + v + '</span>';

        // Findings
        const findingsBox = document.getElementById('drawerFindingsBox');
        const findingsList = document.getElementById('drawerFindingsList');
        findingsList.innerHTML = '';
        const flags = item.flags || [];
        let hasFindings = false;

        if (item.d_index !== null && item.d_index < 0) {
            hasFindings = true;
            findingsList.innerHTML += '<li><strong>Critical Alert:</strong> Discrimination is negative (D = ' + Number(item.d_index).toFixed(2) + '). More lower-scoring students answered correctly than top students. Verify if the registered answer key is incorrect.</li>';
        }

        flags.forEach(f => {
            if (f.startsWith('problem_distractor:')) {
                const optKey = f.substring(19);
                hasFindings = true;
                findingsList.innerHTML += '<li><strong>Trap Distractor (Option ' + optKey + '):</strong> Attracted more high-scoring students than low-scoring students. Check for ambiguity, outdated guidelines, or a second plausible correct answer.</li>';
            }
        });

        if (item.distractor_efficiency !== null && item.distractor_efficiency < 66.6) {
            hasFindings = true;
            findingsList.innerHTML += '<li><strong>Low Distractor Efficiency (' + Number(item.distractor_efficiency).toFixed(0) + '%):</strong> One or more distractors were chosen by fewer than 5% of examinees. Replace non-functional choices with plausible options.</li>';
        }

        if (item.p_index !== null && item.p_index > 0.80) {
            hasFindings = true;
            findingsList.innerHTML += '<li><strong>Too Easy (p = ' + Number(item.p_index).toFixed(2) + '):</strong> Almost all students answered correctly, offering low discriminating power.</li>';
        } else if (item.p_index !== null && item.p_index < 0.20) {
            hasFindings = true;
            findingsList.innerHTML += '<li><strong>Too Difficult (p = ' + Number(item.p_index).toFixed(2) + '):</strong> Very few students answered correctly. Check wording clarity or align with syllabus depth.</li>';
        }

        findingsBox.style.display = hasFindings ? 'block' : 'none';

        // Options breakdown
        const tbody = document.getElementById('drawerOptionsTbody');
        tbody.innerHTML = '';
        const optStats = item.option_stats || {};
        const correctOpt = item.correct_option;

        Object.keys(optStats).forEach(optKey => {
            const stat = optStats[optKey];
            const isCorrect = (optKey === correctOpt);
            const isProblem = flags.includes('problem_distractor:' + optKey);
            const isOmitted = (optKey === 'omitted');

            let rowClass = '';
            if (isCorrect) rowClass = 'opt-row-key';
            else if (isProblem) rowClass = 'opt-row-problem';

            let optLabel = isOmitted ? '<em>Omitted</em>' : ('<strong>' + optKey + '</strong>' + (isCorrect ? ' <i class="fas fa-star" style="color:#eab308;" title="Marked Key"></i>' : ''));
            let statusBadge = '';
            if (isCorrect) {
                statusBadge = '<span class="badge badge-verdict-retain">Answer Key</span>';
            } else if (isOmitted) {
                statusBadge = '<span style="color:#9ca3af;font-size:11px;">Unanswered</span>';
            } else if (isProblem) {
                statusBadge = '<span class="badge badge-verdict-reject"><i class="fas fa-bomb"></i> Trap Distractor</span>';
            } else if (stat.rate >= 0.05 && stat.lower >= stat.upper) {
                statusBadge = '<span class="badge badge-verdict-retain">Functional</span>';
            } else {
                statusBadge = '<span class="badge" style="background:#f3f4f6;color:#6b7280;">Weak / Non-func</span>';
            }

            const tr = document.createElement('tr');
            tr.className = rowClass;
            tr.innerHTML = `
                <td>${optLabel}</td>
                <td style="color:#374151;">${stat.text ? stat.text : (isOmitted ? 'No option selected' : '—')}</td>
                <td><strong>${stat.upper}</strong> <span style="font-size:11px;color:#6b7280;">(${testLevelStats.upper_count ? Math.round((stat.upper / testLevelStats.upper_count) * 100) : 0}%)</span></td>
                <td><strong>${stat.lower}</strong> <span style="font-size:11px;color:#6b7280;">(${testLevelStats.lower_count ? Math.round((stat.lower / testLevelStats.lower_count) * 100) : 0}%)</span></td>
                <td><strong>${stat.total}</strong> <span style="font-size:11px;color:#6b7280;">(${Math.round(stat.rate * 100)}%)</span></td>
                <td>${statusBadge}</td>
            `;
            tbody.appendChild(tr);
        });

        document.getElementById('diagnosticModalBackdrop').style.display = 'flex';
    }

    function closeItemDrawer(e) {
        if (e.target.id === 'diagnosticModalBackdrop') {
            document.getElementById('diagnosticModalBackdrop').style.display = 'none';
        }
    }

    function closeItemDrawerDirect() {
        document.getElementById('diagnosticModalBackdrop').style.display = 'none';
    }

    function triggerRecalculate() {
        const btn = document.getElementById('btnRecalculateAnalysis');
        const icon = document.getElementById('recalculateIcon');
        btn.disabled = true;
        icon.classList.add('fa-spin');

        const currentStage = "{{ $currentStage ?? '' }}";

        fetch("{{ route('modules.item-analysis.run', $module) }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                stage: currentStage
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Analysis error: ' + (data.message || 'Unable to recalculate.'));
                btn.disabled = false;
                icon.classList.remove('fa-spin');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Server error recalculating analysis.');
            btn.disabled = false;
            icon.classList.remove('fa-spin');
        });
    }
</script>
@endsection
