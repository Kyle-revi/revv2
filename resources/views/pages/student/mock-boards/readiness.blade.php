@php
    $programLayoutMap = [
        'psych' => 'layouts.appPsych',
        'accountancy' => 'layouts.appAcc',
        'educ' => 'layouts.appEduc',
    ];
    $layout = $programLayoutMap[auth()->user()->program ?? ''] ?? 'layouts.appAcc';

    $summary = [
        'readiness_percentage' => (float) $report->readiness_percentage,
        'tier' => $report->tier,
        'tier_label' => $report->tier_label,
        'gap_percentage' => (float) $report->gap_percentage,
        'gap_items' => (int) $report->gap_items,
        'passing_threshold' => (int) ($mockBoard->passing_percentage ?? 75),
    ];
    $tierData = \App\Services\MockBoardStatisticsService::calculateReadinessTier($summary['readiness_percentage'], $summary['passing_threshold']);
    $domains = $report->domain_breakdown ?? ['list' => []];
    $itemInsights = $report->item_insights ?? [];
    $peer = $report->peer_benchmark ?? [];
    $hist = $report->historical_comparison ?? null;
    $aiPlan = $report->ai_action_plan ?? [];
@endphp

@extends($layout)

@section('title', 'Mock Board Student Readiness Report')
@section('page-heading', 'Mock Board Student Readiness Report')

@section('header-actions')
    <div class="no-print" style="display: flex; align-items: center; gap: 10px;">
        <a href="{{ route('student.mock-boards.results', $mockBoard) }}" class="btn-action-outline">
            <i class="fas fa-arrow-left"></i> Back to Results
        </a>
        <button type="button" onclick="exportReadinessToExcel()" class="btn-action-secondary" title="Download Excel (.xlsx)">
            <i class="fas fa-file-excel"></i> Export to Excel (.xlsx)
        </button>
        <button type="button" onclick="window.print()" class="btn-action-primary">
            <i class="fas fa-print"></i> Print Report
        </button>
    </div>
@endsection

@section('content')
<div class="readiness-page-wrapper">

    {{-- REPORT HEADER --}}
    <div class="readiness-header-card">
        <div class="header-left">
            <div class="report-badge">Diagnostic Licensure Readiness</div>
            <h1 class="report-title">{{ $mockBoard->title }}</h1>
            <p class="report-subtitle">
                <span><i class="fas fa-user-graduate"></i> {{ $user->name }}</span>
                <span><i class="fas fa-graduation-cap"></i> {{ strtoupper($user->program ?? $mockBoard->program ?? 'N/A') }}</span>
                <span><i class="fas fa-calendar-check"></i> Generated: {{ $report->generated_at?->format('M d, Y h:i A') ?? now()->format('M d, Y') }}</span>
            </p>
        </div>
        <div class="header-right no-print">
            <div class="quick-kpi">
                <span class="quick-kpi-label">Passing Standard</span>
                <span class="quick-kpi-value">{{ $summary['passing_threshold'] }}%</span>
            </div>
            <div class="quick-kpi">
                <span class="quick-kpi-label">Your Best Score</span>
                <span class="quick-kpi-value" style="color: #245E55;">{{ (int) round($summary['readiness_percentage']) }}%</span>
            </div>
        </div>
    </div>

    {{-- SECTION A: READINESS KPI & LIKELIHOOD CARD --}}
    <div class="readiness-section">
        <div class="kpi-grid">
            <div class="kpi-card main-score-card">
                <div class="kpi-card-header">
                    <span><i class="fas fa-award"></i> Board Readiness Score</span>
                    <span class="badge-status {{ $tierData['color'] }}">{{ $tierData['label'] }}</span>
                </div>
                <div class="score-display">
                    <span class="big-score">{{ (int) round($summary['readiness_percentage']) }}%</span>
                    <div class="score-meta">
                        <span class="score-benchmark">PRC Passing Mark: {{ $summary['passing_threshold'] }}%</span>
                        @if($summary['readiness_percentage'] >= $summary['passing_threshold'])
                            <span class="score-gap positive"><i class="fas fa-check-circle"></i> Passing threshold met</span>
                        @else
                            <span class="score-gap negative"><i class="fas fa-exclamation-triangle"></i> {{ $summary['gap_percentage'] }}% shy of passing (~{{ $summary['gap_items'] }} items)</span>
                        @endif
                    </div>
                </div>
                <div class="progress-track-wrapper">
                    <div class="progress-track">
                        <div class="progress-bar {{ $tierData['color'] }}" style="width: {{ min(100, $summary['readiness_percentage']) }}%;"></div>
                        <div class="benchmark-marker" style="left: {{ $summary['passing_threshold'] }}%;" title="Passing Mark {{ $summary['passing_threshold'] }}%">
                            <span class="marker-label">{{ $summary['passing_threshold'] }}%</span>
                        </div>
                    </div>
                </div>
                <p class="kpi-description">{{ $tierData['description'] }}</p>
            </div>

            <div class="kpi-card growth-kpi-card">
                <div class="kpi-card-header">
                    <span><i class="fas fa-chart-line"></i> Diagnostic Growth</span>
                    <span class="badge-neutral">{{ $report->consistency_status ?? 'Single Attempt' }}</span>
                </div>
                <div class="growth-metric-row">
                    <div class="growth-subcol">
                        <span class="metric-sublabel">Diagnostic Pre-Test</span>
                        <span class="metric-subval">{{ (int) round($report->pre_test_score ?? 0) }}%</span>
                    </div>
                    <div class="growth-arrow-wrap">
                        <i class="fas fa-arrow-right" style="color: #94a3b8;"></i>
                    </div>
                    <div class="growth-subcol">
                        <span class="metric-sublabel">Best Post-Test</span>
                        <span class="metric-subval" style="color: #245E55;">{{ (int) round($report->post_test_score ?? 0) }}%</span>
                    </div>
                    <div class="growth-delta-box {{ ($report->improvement_percentage ?? 0) >= 0 ? 'positive' : 'negative' }}">
                        <span class="delta-num">{{ ($report->improvement_percentage ?? 0) >= 0 ? '+' : '' }}{{ $report->improvement_percentage ?? 0 }}%</span>
                        <span class="delta-label">Net Score Gain</span>
                    </div>
                </div>
                <div class="consistency-note">
                    @if($report->consistency_status === 'Consistent')
                        <i class="fas fa-shield-check" style="color: #10b981;"></i> Stable and reproducible performance across multiple attempts.
                    @elseif($report->consistency_status === 'Inconsistent')
                        <i class="fas fa-exclamation-circle" style="color: #f59e0b;"></i> High score variation observed between attempts; review stamina and test-taking routine.
                    @else
                        <i class="fas fa-info-circle" style="color: #64748b;"></i> Single post-test submission recorded. Retake post-test phases to verify score consistency.
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- SECTION B & C: DOMAIN MASTERY & COHORT BENCHMARK --}}
    <div class="readiness-grid-two">
        {{-- DOMAIN MASTERY MATRIX --}}
        <div class="card-box">
            <div class="card-box-header">
                <div>
                    <h3 class="card-box-title"><i class="fas fa-book-reader" style="color: #245E55;"></i> Domain & Subject Mastery</h3>
                    <p class="card-box-desc">Performance breakdown mapped to standard licensure examination subject domains</p>
                </div>
            </div>

            @if(!empty($domains['list']))
                <div class="domain-table-wrap">
                    <table class="domain-table">
                        <thead>
                            <tr>
                                <th>Subject Domain</th>
                                <th style="text-align: center;">Pre-Test</th>
                                <th style="text-align: center;">Post-Test</th>
                                <th style="text-align: center;">Gain</th>
                                <th style="text-align: center;">Accuracy</th>
                                <th style="text-align: center;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($domains['list'] as $d)
                                <tr>
                                    <td>
                                        <div class="domain-name-cell">
                                            <strong>{{ $d['domain'] }}</strong>
                                            <span class="domain-items-count">{{ $d['correct_items'] }} / {{ $d['total_items'] }} items</span>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        {{ $d['pre_score'] !== null ? $d['pre_score'].'%' : '—' }}
                                    </td>
                                    <td style="text-align: center; font-weight: 500; color: #1e293b;">
                                        {{ $d['post_score'] }}%
                                    </td>
                                    <td style="text-align: center;">
                                        @if($d['change'] !== null)
                                            <span class="change-tag {{ $d['change'] >= 0 ? 'positive' : 'negative' }}">
                                                {{ $d['change'] >= 0 ? '+' : '' }}{{ $d['change'] }}%
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="mini-bar-track">
                                            <div class="mini-bar-fill {{ $d['status'] === 'Strong' ? 'strong' : ($d['status'] === 'Developing' ? 'developing' : 'weak') }}" style="width: {{ min(100, $d['post_score']) }}%;"></div>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="domain-status-badge {{ strtolower($d['status']) }}">
                                            {{ $d['status'] }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="domain-summary-highlights">
                    @php
                        $weakDomainName = $domains['weakest_domain'] ?? null;
                        $hasWeakDomain = !empty($weakDomainName) && $weakDomainName !== 'N/A';
                        if ($hasWeakDomain && !empty($domains['list'])) {
                            $weakItem = collect($domains['list'])->firstWhere('domain', $weakDomainName);
                            if ($weakItem && ($weakItem['post_score'] ?? 0) >= 100) {
                                $hasWeakDomain = false;
                            }
                        }
                        $allPerfect = !empty($domains['list']) && collect($domains['list'])->every(fn($d) => ($d['post_score'] ?? 0) >= 100);
                    @endphp

                    @if(!empty($domains['most_improved_domain']) && $domains['most_improved_domain'] !== 'N/A')
                        <div class="highlight-pill positive">
                            <i class="fas fa-arrow-trend-up"></i>
                            <span>Highest Improvement: <strong>{{ $domains['most_improved_domain'] }}</strong></span>
                        </div>
                    @endif

                    @if($hasWeakDomain)
                        <div class="highlight-pill negative">
                            <i class="fas fa-bullseye"></i>
                            <span>Priority Focus Area: <strong>{{ $weakDomainName }}</strong></span>
                        </div>
                    @elseif($allPerfect)
                        <div class="highlight-pill positive">
                            <i class="fas fa-check-circle"></i>
                            <span>All Domains Mastered (100%)</span>
                        </div>
                    @endif
                </div>
            @else
                <p class="empty-state-text">No domain breakdowns recorded for this mock board.</p>
            @endif
        </div>

        {{-- PEER & COHORT BENCHMARK --}}
        <div class="card-box">
            <div class="card-box-header">
                <div>
                    <h3 class="card-box-title"><i class="fas fa-users" style="color: #245E55;"></i> Peer & Cohort Standing</h3>
                    <p class="card-box-desc">Anonymous comparison against all students taking this mock board</p>
                </div>
            </div>

            @if(!empty($peer) && ($peer['cohort_size'] ?? 0) > 0)
                <div class="peer-benchmark-content">
                    <div class="peer-stat-grid">
                        <div class="peer-stat-card">
                            <span class="peer-stat-label">Batch Average</span>
                            <span class="peer-stat-val">{{ $peer['batch_average'] }}%</span>
                            <span class="peer-stat-sub">Across {{ $peer['cohort_size'] }} students</span>
                        </div>
                        <div class="peer-stat-card highlight">
                            <span class="peer-stat-label">Percentile Standing</span>
                            <span class="peer-stat-val">{{ $peer['percentile_rank'] }}<small style="font-size: 16px;">th</small></span>
                            <span class="peer-stat-sub">Higher than {{ $peer['higher_than_percentage'] }}% of cohort</span>
                        </div>
                        <div class="peer-stat-card">
                            <span class="peer-stat-label">Your Difference</span>
                            @php $diffFromBatch = $peer['score_diff_from_batch'] ?? 0; @endphp
                            <span class="peer-stat-val {{ $diffFromBatch >= 0 ? 'text-positive' : 'text-negative' }}">
                                {{ $diffFromBatch >= 0 ? '+' : '' }}{{ $diffFromBatch }}%
                            </span>
                            <span class="peer-stat-sub">relative to batch average</span>
                        </div>
                    </div>

                    <div class="peer-context-box">
                        <i class="fas fa-chart-column" style="color: #245E55; font-size: 20px;"></i>
                        <p style="margin: 0; font-size: 13.5px; color: #334155; line-height: 1.5;">
                            Your best post-test score of <strong>{{ (int) round($summary['readiness_percentage']) }}%</strong> places you in the <strong>{{ $peer['percentile_rank'] }}th percentile</strong>. 
                            @if($diffFromBatch >= 0)
                                You are performing <strong>{{ $diffFromBatch }}% above</strong> the current batch average ({{ $peer['batch_average'] }}%).
                            @else
                                You are currently <strong>{{ abs($diffFromBatch) }}% below</strong> the batch average ({{ $peer['batch_average'] }}%).
                            @endif
                        </p>
                    </div>
                </div>
            @else
                <p class="empty-state-text">Peer benchmarks will appear once multiple students complete this mock board.</p>
            @endif

            {{-- HISTORICAL COMPARISON (IF AVAILABLE) --}}
            @if(!empty($hist))
                <div class="historical-box-section">
                    <h4 class="historical-title">
                        <i class="fas fa-landmark" style="color: #0284c7;"></i> PRC Board Exam Context: {{ $hist['exam_label'] }}
                    </h4>
                    <div class="hist-compare-row">
                        <div class="hist-metric">
                            <span class="hist-label">Official PRC Passing Rate</span>
                            <span class="hist-num">{{ $hist['national_passing_rate'] }}%</span>
                            <span class="hist-detail">{{ $hist['exam_period_or_year'] ?? 'National Benchmark' }}</span>
                        </div>
                        <div class="hist-metric">
                            <span class="hist-label">Mock Board Batch Passing</span>
                            <span class="hist-num" style="color: #245E55;">{{ $hist['batch_passing_rate'] }}%</span>
                            <span class="hist-detail">Current Class Cohort</span>
                        </div>
                    </div>
                    @if(!empty($hist['source_note']))
                        <p class="hist-source-note"><i class="fas fa-info-circle"></i> {{ $hist['source_note'] }}</p>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- SECTION D: HIGH-YIELD ITEM & CONCEPTUAL GAPS --}}
    @if(!empty($itemInsights['repeatedly_missed_items']) || !empty($itemInsights['regressed_items']))
        <div class="card-box" style="margin-top: 24px;">
            <div class="card-box-header">
                <div>
                    <h3 class="card-box-title"><i class="fas fa-magnifying-glass-chart" style="color: #245E55;"></i> High-Yield Conceptual Gaps & Retention Analysis</h3>
                    <p class="card-box-desc">Specific item trends identified across your pre-test and post-test responses</p>
                </div>
            </div>

            <div class="item-insights-grid">
                {{-- REPEATEDLY MISSED --}}
                <div class="insight-column">
                    <div class="insight-column-header repeatedly-missed">
                        <i class="fas fa-times-circle"></i>
                        <span>Repeatedly Missed Concepts (Pre-Test & Post-Test)</span>
                    </div>
                    @forelse($itemInsights['repeatedly_missed_items'] as $item)
                        <div class="insight-item-card">
                            <span class="domain-tag">{{ $item['domain'] }}</span>
                            <p class="item-stem">{{ $item['stem'] }}</p>
                            @if(!empty($item['explanation']))
                                <p class="item-explanation"><strong>Key Takeaway:</strong> {{ $item['explanation'] }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="empty-state-text" style="padding: 16px;">No persistent pre/post missed questions detected.</p>
                    @endforelse
                </div>

                {{-- REGRESSED ITEMS --}}
                <div class="insight-column">
                    <div class="insight-column-header regressed">
                        <i class="fas fa-arrow-down-long"></i>
                        <span>Retention Warning (Correct in Pre-Test, Missed in Post-Test)</span>
                    </div>
                    @forelse($itemInsights['regressed_items'] as $item)
                        <div class="insight-item-card">
                            <span class="domain-tag">{{ $item['domain'] }}</span>
                            <p class="item-stem">{{ $item['stem'] }}</p>
                            @if(!empty($item['explanation']))
                                <p class="item-explanation"><strong>Key Takeaway:</strong> {{ $item['explanation'] }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="empty-state-text" style="padding: 16px;">No conceptual regressions detected. Good retention of diagnostic knowledge!</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    {{-- SECTION E: AI PERSONALIZED ACTION PLAN --}}
    <div class="card-box ai-plan-card" style="margin-top: 24px;">
        <div class="card-box-header">
            <div>
                <h3 class="card-box-title" style="color: #5b21b6;">
                    <i class="fas fa-brain" style="color: #7c3aed;"></i> AI-Generated Licensure Action Plan
                </h3>
                <p class="card-box-desc">Targeted, lecture-aligned remediation steps customized to your diagnostic strengths and weaknesses</p>
            </div>
            <span class="ai-generated-badge no-print"><i class="fas fa-sparkles"></i> AI Diagnostics</span>
        </div>

        @if(!empty($aiPlan))
            @if(!empty($aiPlan['summary_narrative']))
                <div class="ai-narrative-box">
                    <p>{{ $aiPlan['summary_narrative'] }}</p>
                </div>
            @endif

            @php
                $isPerfectScore = ($summary['readiness_percentage'] >= 100) || (!empty($domains['list']) && collect($domains['list'])->every(fn($d) => ($d['post_score'] ?? 0) >= 100));
                $priorityDomains = (array) ($aiPlan['priority_domains'] ?? []);
                $reviewTopics = (array) ($aiPlan['review_topics'] ?? []);
            @endphp

            <div class="ai-details-grid">
                @if($isPerfectScore)
                    <div class="ai-subcard" style="background: #f0fdf4; border: 1px solid #bbf7d0;">
                        <h4 class="ai-subcard-title" style="color: #15803d;"><i class="fas fa-award" style="color: #16a34a;"></i> Priority Focus Domains</h4>
                        <ul class="ai-tags-list">
                            <li class="ai-priority-tag positive">
                                <i class="fas fa-check-circle" style="margin-right: 4px;"></i> All Domains Mastered (100%)
                            </li>
                        </ul>
                    </div>
                @elseif(!empty($priorityDomains))
                    <div class="ai-subcard">
                        <h4 class="ai-subcard-title"><i class="fas fa-bullseye" style="color: #dc2626;"></i> Priority Focus Domains</h4>
                        <ul class="ai-tags-list">
                            @foreach($priorityDomains as $dom)
                                <li class="ai-priority-tag">{{ $dom }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if($isPerfectScore)
                    <div class="ai-subcard" style="background: #f0fdf4; border: 1px solid #bbf7d0;">
                        <h4 class="ai-subcard-title" style="color: #15803d;"><i class="fas fa-circle-check" style="color: #16a34a;"></i> Specific Concepts to Revisit</h4>
                        <ul class="ai-topic-bullets">
                            <li style="color: #15803d;"><i class="fas fa-check" style="margin-right: 6px; color: #16a34a;"></i> No conceptual gaps identified across evaluated examination items.</li>
                            <li style="color: #15803d;"><i class="fas fa-check" style="margin-right: 6px; color: #16a34a;"></i> Perfect accuracy (100%) recorded across all tested domains.</li>
                        </ul>
                    </div>
                @elseif(!empty($reviewTopics))
                    <div class="ai-subcard">
                        <h4 class="ai-subcard-title"><i class="fas fa-list-check" style="color: #d97706;"></i> Specific Concepts to Revisit</h4>
                        <ul class="ai-topic-bullets">
                            @foreach($reviewTopics as $topic)
                                <li>{{ $topic }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            @php
                $rawSteps = (array) ($aiPlan['study_steps'] ?? []);
                $hasUsHallucination = false;
                foreach ($rawSteps as $st) {
                    if (preg_match('/ASC\s*\d+|AICPA|US\s*GAAP/i', (string) $st)) {
                        $hasUsHallucination = true;
                        break;
                    }
                }
                if ($isPerfectScore && ($hasUsHallucination || empty($rawSteps))) {
                    $studySteps = [
                        '1. Maintain Mastery Through Spaced Retrieval: Schedule periodic active recall quizzes to retain theoretical frameworks and computational agility across all tested Philippine CPA syllabus topics.',
                        '2. Pacing and Time Management: Practice complete timed mock board simulations (3 hours per subject) to master pacing, time allocation per problem, and exam-day speed.',
                        '3. Stay Updated with Latest Regulatory Issuances: Review the latest BIR revenue regulations, PRC Board of Accountancy updates, and newly effective PFRS/PAS amendments.',
                        '4. Simulate Actual Licensure Exam Conditions: Rehearse under strict PRC CPALE examination conditions (non-programmable calculators, standard scratch paper, uninterrupted 3-hour blocks) to maximize mental stamina.',
                    ];
                } else {
                    $studySteps = $rawSteps;
                }
            @endphp

            @if(!empty($studySteps))
                <div class="ai-steps-section">
                    <h4 class="ai-subcard-title" style="margin-bottom: 14px;">
                        <i class="fas fa-clipboard-list" style="color: #245E55;"></i> Recommended Step-by-Step Study Sequence
                    </h4>
                    <div class="ai-steps-list">
                        @foreach($studySteps as $idx => $step)
                            <div class="ai-step-row">
                                <div class="step-num">{{ $idx + 1 }}</div>
                                <div class="step-body">{{ preg_replace('/^\d+\.\s*/', '', $step) }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @else
            <p class="empty-state-text">Action plan is being prepared...</p>
        @endif
    </div>

    {{-- REPORT FOOTER --}}
    <div class="report-footer">
        <p>REVISO Assessment & Review Platform &bull; Diagnostic Licensure Analytics</p>
        <p class="footer-timestamp">Confidential Student Academic Record &bull; Generated on {{ now()->format('F d, Y h:i A') }}</p>
    </div>

</div>

<style>
    /* PAGE WRAPPER */
    .readiness-page-wrapper {
        max-width: 1200px;
        margin: 0 auto;
        padding: 24px 20px 60px;
        font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        color: #1e293b;
    }
    .readiness-page-wrapper strong,
    .readiness-page-wrapper b,
    .readiness-page-wrapper h1,
    .readiness-page-wrapper h2,
    .readiness-page-wrapper h3,
    .readiness-page-wrapper h4,
    .readiness-page-wrapper h5,
    .readiness-page-wrapper h6,
    .readiness-page-wrapper th {
        font-weight: 500;
    }

    /* HEADER CARD */
    .readiness-header-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 28px 32px;
        margin-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03);
    }
    .report-badge {
        display: inline-block;
        font-size: 11.5px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        padding: 4px 10px;
        border-radius: 6px;
        background: #e6f4f1;
        color: #245E55;
        margin-bottom: 8px;
    }
    .report-title {
        font-size: 26px;
        font-weight: 500;
        color: #0f172a;
        margin: 0 0 10px;
        letter-spacing: -0.5px;
    }
    .report-subtitle {
        display: flex;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
        font-size: 13.5px;
        color: #64748b;
        margin: 0;
    }
    .report-subtitle span {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .header-right {
        display: flex;
        align-items: center;
        gap: 24px;
    }
    .quick-kpi {
        text-align: right;
    }
    .quick-kpi-label {
        display: block;
        font-size: 11.5px;
        text-transform: uppercase;
        font-weight: 500;
        color: #94a3b8;
        letter-spacing: 0.5px;
    }
    .quick-kpi-value {
        font-size: 26px;
        font-weight: 500;
        color: #1e293b;
    }

    /* ACTION BUTTONS */
    .btn-action-outline {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 16px;
        border-radius: 9px;
        font-size: 13.5px;
        font-weight: 500;
        color: #475569;
        background: #ffffff;
        border: 1px solid #cbd5e1;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .btn-action-outline:hover {
        background: #f1f5f9;
        color: #1e293b;
    }
    .btn-action-secondary {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 16px;
        border-radius: 9px;
        font-size: 13.5px;
        font-weight: 500;
        font-family: inherit;
        color: #065f46;
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .btn-action-secondary:hover {
        background: #d1fae5;
    }
    .btn-action-primary {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 18px;
        border-radius: 9px;
        font-size: 13.5px;
        font-weight: 500;
        color: #ffffff;
        background: #245E55;
        border: none;
        cursor: pointer;
        box-shadow: 0 2px 4px rgba(36,94,85,0.25);
        transition: all 0.2s ease;
    }
    .btn-action-primary:hover {
        background: #1b4942;
    }

    /* KPI GRID */
    .kpi-grid {
        display: grid;
        grid-template-columns: 1.3fr 1fr;
        gap: 20px;
    }
    @media (max-width: 900px) {
        .kpi-grid {
            grid-template-columns: 1fr;
        }
    }
    .kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    .kpi-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 13.5px;
        font-weight: 500;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 16px;
    }
    .badge-status {
        padding: 5px 12px;
        border-radius: 99px;
        font-size: 12px;
        font-weight: 500;
        border: 1px solid;
    }
    .badge-status.positive { background: #e6f4f1; color: #166534; border-color: #86efac; }
    .badge-status.neutral { background: #fef3c7; color: #92400e; border-color: #fcd34d; }
    .badge-status.negative { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }
    .badge-neutral {
        background: #f1f5f9;
        color: #475569;
        padding: 4px 10px;
        border-radius: 99px;
        font-size: 12px;
        font-weight: 500;
    }

    .score-display {
        display: flex;
        align-items: baseline;
        gap: 18px;
        margin-bottom: 14px;
    }
    .big-score {
        font-size: 54px;
        font-weight: 500;
        line-height: 1;
        color: #0f172a;
        letter-spacing: -1.5px;
    }
    .score-meta {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .score-benchmark {
        font-size: 13.5px;
        color: #64748b;
        font-weight: 500;
    }
    .score-gap {
        font-size: 13.5px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .score-gap.positive { color: #16a34a; }
    .score-gap.negative { color: #dc2626; }

    /* PROGRESS TRACK */
    .progress-track-wrapper {
        margin: 14px 0 16px;
        padding-top: 14px;
    }
    .progress-track {
        height: 12px;
        background: #e2e8f0;
        border-radius: 99px;
        position: relative;
    }
    .progress-bar {
        height: 100%;
        border-radius: 99px;
        transition: width 0.8s ease;
    }
    .progress-bar.positive { background: #245E55; }
    .progress-bar.neutral { background: #f59e0b; }
    .progress-bar.negative { background: #ef4444; }

    .benchmark-marker {
        position: absolute;
        top: -18px;
        transform: translateX(-50%);
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    .benchmark-marker::after {
        content: '';
        width: 3px;
        height: 18px;
        background: #0f172a;
        margin-top: 2px;
    }
    .marker-label {
        font-size: 10.5px;
        font-weight: 500;
        color: #0f172a;
    }
    .kpi-description {
        font-size: 13.5px;
        color: #475569;
        line-height: 1.5;
        margin: 0;
    }

    /* GROWTH CARD METRICS */
    .growth-metric-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 18px;
        background: #f8fafc;
        border-radius: 12px;
        border: 1px solid #f1f5f9;
        margin-bottom: 16px;
    }
    .growth-subcol {
        display: flex;
        flex-direction: column;
    }
    .metric-sublabel {
        font-size: 11.5px;
        font-weight: 500;
        text-transform: uppercase;
        color: #64748b;
    }
    .metric-subval {
        font-size: 24px;
        font-weight: 500;
        color: #0f172a;
    }
    .growth-delta-box {
        text-align: right;
        padding-left: 12px;
    }
    .growth-delta-box.positive .delta-num { color: #16a34a; }
    .growth-delta-box.negative .delta-num { color: #dc2626; }
    .delta-num {
        font-size: 26px;
        font-weight: 500;
        display: block;
        line-height: 1.1;
    }
    .delta-label {
        font-size: 11.5px;
        font-weight: 500;
        color: #64748b;
    }
    .consistency-note {
        font-size: 13px;
        color: #475569;
        display: flex;
        align-items: flex-start;
        gap: 8px;
        line-height: 1.5;
    }

    /* TWO COLUMN GRID */
    .readiness-grid-two {
        display: grid;
        grid-template-columns: 1.25fr 1fr;
        gap: 20px;
        margin-top: 24px;
    }
    @media (max-width: 980px) {
        .readiness-grid-two {
            grid-template-columns: 1fr;
        }
    }
    .card-box {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    .card-box-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 20px;
    }
    .card-box-title {
        font-size: 18px;
        font-weight: 500;
        color: #0f172a;
        margin: 0 0 4px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .card-box-desc {
        font-size: 13px;
        color: #64748b;
        margin: 0;
    }

    /* DOMAIN TABLE */
    .domain-table-wrap {
        overflow-x: auto;
    }
    .domain-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    .domain-table th {
        background: #f8fafc;
        color: #475569;
        font-weight: 500;
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: 0.5px;
        padding: 10px 12px;
        border-bottom: 1px solid #e2e8f0;
    }
    .domain-table td {
        padding: 12px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .domain-name-cell {
        display: flex;
        flex-direction: column;
    }
    .domain-items-count {
        font-size: 11px;
        color: #94a3b8;
    }
    .change-tag {
        font-weight: 500;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 12px;
    }
    .change-tag.positive { color: #16a34a; background: #ecfdf5; }
    .change-tag.negative { color: #dc2626; background: #fef2f2; }

    .mini-bar-track {
        width: 80px;
        height: 6px;
        background: #e2e8f0;
        border-radius: 99px;
        margin: 0 auto;
    }
    .mini-bar-fill {
        height: 100%;
        border-radius: 99px;
    }
    .mini-bar-fill.strong { background: #16a34a; }
    .mini-bar-fill.developing { background: #f59e0b; }
    .mini-bar-fill.weak { background: #ef4444; }

    .domain-status-badge {
        font-size: 11px;
        font-weight: 500;
        text-transform: uppercase;
        padding: 3px 8px;
        border-radius: 6px;
    }
    .domain-status-badge.strong { background: #e6f4f1; color: #166534; }
    .domain-status-badge.developing { background: #fef3c7; color: #92400e; }
    .domain-status-badge.weak { background: #fee2e2; color: #991b1b; }

    .domain-summary-highlights {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid #f1f5f9;
    }
    .highlight-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 12.5px;
        padding: 7px 12px;
        border-radius: 8px;
    }
    .highlight-pill.positive { background: #ecfdf5; color: #065f46; }
    .highlight-pill.negative { background: #fef2f2; color: #991b1b; }

    /* PEER STATS */
    .peer-stat-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 18px;
    }
    @media (max-width: 600px) {
        .peer-stat-grid {
            grid-template-columns: 1fr;
        }
    }
    .peer-stat-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px 14px;
        text-align: center;
    }
    .peer-stat-card.highlight {
        background: #f0fdf4;
        border-color: #bbf7d0;
    }
    .peer-stat-label {
        display: block;
        font-size: 11px;
        font-weight: 500;
        text-transform: uppercase;
        color: #64748b;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }
    .peer-stat-val {
        font-size: 26px;
        font-weight: 500;
        color: #0f172a;
        line-height: 1.1;
    }
    .peer-stat-sub {
        display: block;
        font-size: 11.5px;
        color: #94a3b8;
        margin-top: 4px;
    }
    .text-positive { color: #16a34a !important; }
    .text-negative { color: #dc2626 !important; }

    .peer-context-box {
        display: flex;
        align-items: center;
        gap: 14px;
        background: #f1f5f9;
        padding: 14px 18px;
        border-radius: 12px;
        margin-bottom: 20px;
    }

    /* HISTORICAL SECTION */
    .historical-box-section {
        background: #f0f9ff;
        border: 1px solid #bae6fd;
        border-radius: 12px;
        padding: 16px 18px;
    }
    .historical-title {
        font-size: 13.5px;
        font-weight: 500;
        color: #0369a1;
        margin: 0 0 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .hist-compare-row {
        display: flex;
        justify-content: space-around;
        text-align: center;
        gap: 12px;
    }
    .hist-label {
        display: block;
        font-size: 11px;
        font-weight: 500;
        color: #0284c7;
        text-transform: uppercase;
    }
    .hist-num {
        font-size: 22px;
        font-weight: 500;
        color: #0c4a6e;
    }
    .hist-detail {
        display: block;
        font-size: 11px;
        color: #64748b;
    }
    .hist-source-note {
        font-size: 11.5px;
        color: #64748b;
        margin: 10px 0 0;
        font-style: italic;
    }

    /* ITEM INSIGHTS GRID */
    .item-insights-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    @media (max-width: 850px) {
        .item-insights-grid {
            grid-template-columns: 1fr;
        }
    }
    .insight-column {
        background: #f8fafc;
        border-radius: 12px;
        padding: 16px;
        border: 1px solid #e2e8f0;
    }
    .insight-column-header {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding-bottom: 12px;
        margin-bottom: 12px;
        border-bottom: 1px solid #e2e8f0;
    }
    .insight-column-header.repeatedly-missed { color: #dc2626; }
    .insight-column-header.regressed { color: #d97706; }
    .insight-item-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 12px 14px;
        margin-bottom: 10px;
    }
    .domain-tag {
        display: inline-block;
        font-size: 10.5px;
        font-weight: 500;
        color: #245E55;
        background: #e6f4f1;
        padding: 2px 7px;
        border-radius: 4px;
        margin-bottom: 6px;
    }
    .item-stem {
        font-size: 13px;
        font-weight: 500;
        color: #1e293b;
        margin: 0 0 6px;
        line-height: 1.4;
    }
    .item-explanation {
        font-size: 12px;
        color: #64748b;
        margin: 0;
        line-height: 1.4;
        background: #f8fafc;
        padding: 6px 8px;
        border-radius: 4px;
    }

    /* AI ACTION PLAN */
    .ai-plan-card {
        border-left: 5px solid #7c3aed;
        background: linear-gradient(135deg, #ffffff 0%, #faf5ff 100%);
    }
    .ai-generated-badge {
        font-size: 12px;
        font-weight: 500;
        color: #7c3aed;
        background: #ede9fe;
        padding: 4px 10px;
        border-radius: 99px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .ai-narrative-box {
        background: #ffffff;
        border: 1px solid #ddd6fe;
        border-radius: 12px;
        padding: 16px 20px;
        font-size: 14.5px;
        color: #4c1d95;
        line-height: 1.6;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(124,58,237,0.04);
    }
    .ai-details-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 20px;
    }
    @media (max-width: 768px) {
        .ai-details-grid {
            grid-template-columns: 1fr;
        }
    }
    .ai-subcard {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px 18px;
    }
    .ai-subcard-title {
        font-size: 13px;
        font-weight: 500;
        text-transform: uppercase;
        color: #334155;
        letter-spacing: 0.5px;
        margin: 0 0 10px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .ai-tags-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .ai-priority-tag {
        font-size: 12.5px;
        font-weight: 500;
        background: #fef2f2;
        color: #991b1b;
        border: 1px solid #fecaca;
        padding: 5px 12px;
        border-radius: 8px;
    }
    .ai-priority-tag.positive {
        background: #ecfdf5;
        color: #065f46;
        border-color: #a7f3d0;
    }
    .ai-topic-bullets {
        padding-left: 18px;
        margin: 0;
        font-size: 13px;
        color: #475569;
        line-height: 1.5;
    }
    .ai-topic-bullets li {
        margin-bottom: 6px;
    }
    .ai-steps-section {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
    }
    .ai-steps-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .ai-step-row {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        padding: 10px 12px;
        background: #f8fafc;
        border-radius: 8px;
        border-left: 3px solid #245E55;
    }
    .step-num {
        width: 24px;
        height: 24px;
        background: #245E55;
        color: #ffffff;
        border-radius: 99px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 500;
        flex-shrink: 0;
    }
    .step-body {
        font-size: 13.5px;
        color: #334155;
        line-height: 1.5;
        font-weight: 500;
    }

    /* FOOTER */
    .report-footer {
        text-align: center;
        font-size: 12px;
        color: #94a3b8;
        margin-top: 40px;
        padding-top: 20px;
        border-top: 1px solid #e2e8f0;
    }
    .report-footer p { margin: 3px 0; }
    .empty-state-text {
        font-size: 13px;
        color: #94a3b8;
        font-style: italic;
        margin: 0;
    }

    /* =========================================================
       PRINT STYLES FOR HIGH-QUALITY BROWSER PRINTING / PDF SAVE
       ========================================================= */
    @media print {
        @page {
            size: A4 portrait;
            margin: 12mm;
        }

        /* Hide elements that shouldn't appear on paper */
        .no-print,
        nav,
        aside,
        header,
        .sidebar,
        .navbar,
        .btn-action-outline,
        .btn-action-secondary,
        .btn-action-primary,
        #sidebar,
        #topbar {
            display: none !important;
        }

        body {
            background: #ffffff !important;
            color: #000000 !important;
            font-size: 11pt !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .readiness-page-wrapper {
            max-width: 100% !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        .readiness-header-card,
        .kpi-card,
        .card-box,
        .ai-plan-card,
        .insight-column {
            box-shadow: none !important;
            border: 1px solid #cbd5e1 !important;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        .readiness-grid-two,
        .kpi-grid,
        .item-insights-grid,
        .ai-details-grid {
            display: block !important;
        }

        .kpi-card,
        .card-box {
            margin-bottom: 16px !important;
        }

        .report-title {
            font-size: 20pt !important;
        }

        .big-score {
            font-size: 38pt !important;
        }

        a {
            text-decoration: none !important;
            color: inherit !important;
        }
    }
</style>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
    const readinessExportData = {
        studentName: @json($user->name),
        studentId: @json($user->idnumber ?? '—'),
        studentProgram: @json(strtoupper($user->program ?? $mockBoard->program ?? 'N/A')),
        boardTitle: @json($mockBoard->title),
        generatedAt: @json($report->generated_at?->format('F d, Y h:i A') ?? now()->format('F d, Y h:i A')),
        passingThreshold: @json((int) ($mockBoard->passing_percentage ?? 75)),
        readinessScore: @json(round($summary['readiness_percentage'], 1)),
        tierLabel: @json($tierData['label']),
        tierDescription: @json($tierData['description']),
        gapPercentage: @json($summary['gap_percentage']),
        gapItems: @json($summary['gap_items']),
        preTestScore: @json($report->pre_test_score ?? null),
        improvementPercentage: @json($report->improvement_percentage ?? null),
        consistencyStatus: @json($report->consistency_status ?? 'N/A'),
        peer: @json($peer),
        hist: @json($hist),
        domains: @json($domains['list'] ?? []),
        repeatedlyMissed: @json($itemInsights['repeatedly_missed_items'] ?? []),
        regressedItems: @json($itemInsights['regressed_items'] ?? []),
        aiPlan: @json($aiPlan),
    };

    function exportReadinessToExcel() {
        if (typeof XLSX === 'undefined') {
            window.location.href = "{{ route('student.mock-boards.readiness.export', $mockBoard) }}";
            return;
        }

        const d = readinessExportData;
        const wb = XLSX.utils.book_new();

        // -------------------------------------------------------------
        // Sheet 1: Readiness Summary
        // -------------------------------------------------------------
        const summaryRows = [
            ['REVISO - MOCK BOARD STUDENT READINESS REPORT'],
            ['Report Generated At', d.generatedAt],
            ['Student Name', d.studentName],
            ['Student ID Number', d.studentId],
            ['Academic Program', d.studentProgram],
            ['Mock Board Examination', d.boardTitle],
            ['Passing Standard Benchmark', d.passingThreshold + '%'],
            [],
            ['1. READINESS KPI SUMMARY', '', ''],
            ['Metric / Indicator', 'Score / Value', 'Remarks & Diagnostic Assessment'],
            [
                'Board Readiness Score (Best Post-Test)',
                d.readinessScore + '%',
                d.tierLabel + ' (' + d.tierDescription + ')'
            ],
            [
                'Benchmark Passing Standard',
                d.passingThreshold + '%',
                (d.readinessScore >= d.passingThreshold ? 'Target Met (Passed)' : 'Below Benchmark')
            ],
            [
                'Gap to Pass Benchmark',
                d.gapPercentage + '%',
                (d.gapPercentage > 0 ? 'Need ~' + d.gapItems + ' more correct items to pass' : 'Threshold achieved')
            ],
            [
                'Diagnostic Pre-Test Score',
                (d.preTestScore !== null ? d.preTestScore + '%' : 'N/A'),
                'Baseline diagnostic score before post-test'
            ],
            [
                'Overall Score Improvement (Gain)',
                (d.improvementPercentage !== null ? (d.improvementPercentage >= 0 ? '+' : '') + d.improvementPercentage + '%' : 'N/A'),
                'Diagnostic-to-post-test growth rate'
            ],
            [
                'Performance Consistency',
                d.consistencyStatus,
                'Post-test score stability across attempts'
            ],
        ];

        if (d.peer && Object.keys(d.peer).length > 0) {
            summaryRows.push([]);
            summaryRows.push(['2. PEER & COHORT BENCHMARK', '', '']);
            summaryRows.push(['Metric / Indicator', 'Value', 'Standing Description']);
            summaryRows.push(['Batch Examinees Count', d.peer.cohort_size || 0, 'Total examinees in cohort']);
            summaryRows.push(['Batch Average Score', (d.peer.batch_average || 0) + '%', 'Overall cohort average']);
            summaryRows.push([
                'Your Score vs Batch Average',
                ((d.peer.score_diff_from_batch || 0) >= 0 ? '+' : '') + (d.peer.score_diff_from_batch || 0) + '%',
                ((d.peer.score_diff_from_batch || 0) >= 0 ? 'Above cohort average' : 'Below cohort average')
            ]);
            summaryRows.push([
                'Percentile Rank',
                (d.peer.percentile_rank || 0) + 'th Percentile',
                'Higher than ' + (d.peer.higher_than_percentage || 0) + '% of batch examinees'
            ]);
        }

        if (d.hist && Object.keys(d.hist).length > 0) {
            summaryRows.push([]);
            summaryRows.push(['3. HISTORICAL LICENSURE BENCHMARK', '', '']);
            summaryRows.push(['Metric / Indicator', 'Value', 'Remarks']);
            summaryRows.push(['Exam Benchmark Reference', d.hist.exam_label || 'PRC Licensure Exam', d.hist.exam_period_or_year || 'N/A']);
            summaryRows.push(['National Historical Passing Rate', (d.hist.national_passing_rate || 0) + '%', 'Official PRC national benchmark']);
            summaryRows.push(['Current Batch Passing Rate', (d.hist.batch_passing_rate || 0) + '%', 'Batch pass rate in this mock board']);
        }

        const wsSummary = XLSX.utils.aoa_to_sheet(summaryRows);
        wsSummary['!cols'] = [
            { wch: 38 },
            { wch: 22 },
            { wch: 55 }
        ];
        XLSX.utils.book_append_sheet(wb, wsSummary, 'Readiness Summary');

        // -------------------------------------------------------------
        // Sheet 2: Domain Mastery
        // -------------------------------------------------------------
        const domainRows = [
            ['DOMAIN & SUBJECT MASTERY BREAKDOWN'],
            ['Mock Board:', d.boardTitle],
            ['Student Name:', d.studentName],
            ['Exported At:', d.generatedAt],
            [],
            ['#', 'Domain / Subject Area', 'Pre-Test Score', 'Post-Test Score', 'Score Gain', 'Correct Items', 'Total Items', 'Mastery Status']
        ];

        if (d.domains && d.domains.length > 0) {
            d.domains.forEach(function(item, idx) {
                domainRows.push([
                    idx + 1,
                    item.domain || 'General',
                    item.pre_score !== null && item.pre_score !== undefined ? item.pre_score + '%' : 'N/A',
                    (item.post_score !== null && item.post_score !== undefined ? item.post_score : 0) + '%',
                    item.change !== null && item.change !== undefined ? ((item.change >= 0 ? '+' : '') + item.change + '%') : 'N/A',
                    item.correct_items || 0,
                    item.total_items || 0,
                    item.status || 'Developing'
                ]);
            });
        } else {
            domainRows.push(['—', 'No domain breakdown data available', '—', '—', '—', '—', '—', '—']);
        }

        const wsDomain = XLSX.utils.aoa_to_sheet(domainRows);
        wsDomain['!cols'] = [
            { wch: 6 },
            { wch: 36 },
            { wch: 16 },
            { wch: 16 },
            { wch: 16 },
            { wch: 15 },
            { wch: 14 },
            { wch: 20 }
        ];
        XLSX.utils.book_append_sheet(wb, wsDomain, 'Domain Mastery');

        // -------------------------------------------------------------
        // Sheet 3: Item Analysis & Conceptual Gaps
        // -------------------------------------------------------------
        const gapsRows = [
            ['HIGH-YIELD ITEM & CONCEPTUAL GAPS ANALYSIS'],
            ['Mock Board:', d.boardTitle],
            ['Student Name:', d.studentName],
            ['Exported At:', d.generatedAt],
            [],
            ['Gap Type', 'Domain / Subject', 'Question Concept / Stem', 'Detailed Explanation & Core Principle']
        ];

        let hasGaps = false;
        if (d.repeatedlyMissed && d.repeatedlyMissed.length > 0) {
            hasGaps = true;
            d.repeatedlyMissed.forEach(function(item) {
                gapsRows.push([
                    'Repeatedly Missed',
                    item.domain || 'General',
                    item.stem || '—',
                    item.explanation || '—'
                ]);
            });
        }

        if (d.regressedItems && d.regressedItems.length > 0) {
            hasGaps = true;
            d.regressedItems.forEach(function(item) {
                gapsRows.push([
                    'Regressed Item (Passed Pre, Missed Post)',
                    item.domain || 'General',
                    item.stem || '—',
                    item.explanation || '—'
                ]);
            });
        }

        if (!hasGaps) {
            gapsRows.push(['No Critical Gaps', 'All Domains', 'No repeated errors or regressions recorded between Pre-Test and Post-Test', 'Solid retention across tested items']);
        }

        const wsGaps = XLSX.utils.aoa_to_sheet(gapsRows);
        wsGaps['!cols'] = [
            { wch: 36 },
            { wch: 28 },
            { wch: 55 },
            { wch: 65 }
        ];
        XLSX.utils.book_append_sheet(wb, wsGaps, 'Item Analysis & Gaps');

        // -------------------------------------------------------------
        // Sheet 4: Action Plan & Recommendations
        // -------------------------------------------------------------
        const planRows = [
            ['PERSONALIZED ACTION PLAN & STUDY RECOMMENDATIONS'],
            ['Mock Board:', d.boardTitle],
            ['Student Name:', d.studentName],
            ['Exported At:', d.generatedAt],
            [],
            ['Section', 'Details & Recommendations']
        ];

        const plan = d.aiPlan || {};
        if (plan.summary_narrative) {
            planRows.push(['Readiness Summary Narrative', plan.summary_narrative]);
        }
        if (plan.priority_domains && plan.priority_domains.length > 0) {
            planRows.push(['Priority Focus Domains', plan.priority_domains.join(', ')]);
        }
        if (plan.review_topics && plan.review_topics.length > 0) {
            planRows.push(['Specific Concepts to Revisit', plan.review_topics.join(' | ')]);
        }

        planRows.push([]);
        planRows.push(['STEP-BY-STEP STUDY DIRECTIVES', '']);
        planRows.push(['Step #', 'Action Item']);

        if (plan.study_steps && plan.study_steps.length > 0) {
            plan.study_steps.forEach(function(step, idx) {
                planRows.push(['Step ' + (idx + 1), step]);
            });
        } else {
            planRows.push(['Step 1', 'Review core formulas, standards, and lecture handouts before your next mock board attempt.']);
        }

        const wsPlan = XLSX.utils.aoa_to_sheet(planRows);
        wsPlan['!cols'] = [
            { wch: 30 },
            { wch: 80 }
        ];
        XLSX.utils.book_append_sheet(wb, wsPlan, 'Action Plan & Recommendations');

        // -------------------------------------------------------------
        // Download generated .xlsx file
        // -------------------------------------------------------------
        const cleanStudent = (d.studentName || 'Student').replace(/[^a-z0-9]/gi, '_').toLowerCase();
        const cleanBoard = (d.boardTitle || 'MockBoard').replace(/[^a-z0-9]/gi, '_').toLowerCase();
        const dateStr = new Date().toISOString().slice(0, 10);
        const filename = 'Readiness_Report_' + cleanStudent + '_' + cleanBoard + '_' + dateStr + '.xlsx';

        XLSX.writeFile(wb, filename);
    }
</script>
@endsection
