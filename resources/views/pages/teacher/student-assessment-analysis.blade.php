@extends('layouts.appTeach')

@section('title', 'Assessment Data Analysis')
@section('page-heading', 'Assessment Data Analysis')

@section('content')
<style>
    .saa-wrap {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    /* -”€-”€ Back nav -”€-”€ */
    .saa-back {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 18px;
        font-weight: 500;
        color: #aaa;
        text-decoration: none;
        transition: color 0.15s;
    }

    .saa-back:hover { color: #111; }

    /* -”€-”€ Student header -”€-”€ */
    .saa-student-head {
        background: #fff;
        border: 1px solid #ebebeb;
        border-radius: 14px;
        padding: 20px 24px;
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .saa-avatar {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: #f3f3f3;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: #bbb;
        flex-shrink: 0;
    }

    .saa-student-name {
        font-family: 'DM Sans', sans-serif;
        font-size: 24px;
        font-weight: 500;
        letter-spacing: -0.02em;
        color: #111;
        margin: 0 0 2px;
    }

    .saa-student-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .saa-badge {
        display: inline-block;
        font-size: 15px;
        font-weight: 500;
        padding: 3px 10px;
        border-radius: 99px;
        background: #f3f3f3;
        color: #777;
    }

    .saa-badge.class { background: #eeebff; color: #5b52c2; }

    /* -”€-”€ Stat row -”€-”€ */
    .saa-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 18px;
    }

    .saa-stat {
        background: #fff;
        border: 1px solid #ebebeb;
        border-radius: 14px;
        padding: 20px 24px;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .saa-stat-label {
        font-size: 14px;
        font-weight: 500;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #bbb;
        margin: 0;
    }

    .saa-stat-val {
        font-family: 'DM Sans', sans-serif;
        font-size: 34px;
        font-weight: 500;
        color: #111;
        line-height: 1;
        margin: 0;
    }

    .saa-stat-val.green { color: #1d9e75; }
    .saa-stat-val.amber { color: #854f0b; }
    .saa-stat-val.red   { color: #a32d2d; }

    /* -”€-”€ Grid -”€-”€ */
    .saa-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 18px;
        align-items: start;
    }

    /* -”€-”€ Cards -”€-”€ */
    .saa-card {
        background: #fff;
        border: 1px solid #ebebeb;
        border-radius: 14px;
        padding: 1.35rem 1.5rem;
    }

    .saa-card-title {
        font-size: 20px;
        font-weight: 500;
        color: #111;
        margin: 0 0 16px;
    }

    /* -”€-”€ Attempt table -”€-”€ */
    .saa-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 17px;
    }

    .saa-table thead th {
        font-size: 14px;
        font-weight: 500;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #bbb;
        padding: 8px 10px 12px;
        text-align: left;
        border-bottom: 1px solid #f0f0f0;
    }

    .saa-table tbody tr { border-bottom: 1px solid #f7f7f7; transition: background 0.1s; }
    .saa-table tbody tr:last-child { border-bottom: none; }
    .saa-table tbody tr:hover { background: #fafafa; }
    .saa-table tbody td { padding: 12px 10px; color: #333; vertical-align: middle; }

    .saa-score-pill {
        display: inline-block;
        font-size: 17px;
        font-weight: 500;
        padding: 4px 11px;
        border-radius: 99px;
        background: #f3f3f3;
        color: #555;
    }

    .saa-score-pill.high { background: #e1f5ee; color: #0f6e56; }
    .saa-score-pill.mid  { background: #faeeda; color: #854f0b; }
    .saa-score-pill.low  { background: #fcebeb; color: #a32d2d; }

    .saa-pass-badge {
        display: inline-block;
        font-size: 17px;
        font-weight: 500;
        padding: 4px 10px;
        border-radius: 99px;
    }

    .saa-pass-badge.pass { background: #e1f5ee; color: #0f6e56; }
    .saa-pass-badge.fail { background: #fcebeb; color: #a32d2d; }

    /* -”€-”€ Question performance -”€-”€ */
    .saa-q-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-height: 340px;
        overflow-y: auto;
        padding-right: 4px;
    }

    .saa-q-list::-webkit-scrollbar { width: 4px; }
    .saa-q-list::-webkit-scrollbar-thumb { background: #e8e8e8; border-radius: 99px; }

    .saa-q-item {
        padding: 12px 14px;
        border-radius: 10px;
        border-left: 3px solid transparent;
        background: #fafafa;
        border-top: 1px solid #f0f0f0;
        border-right: 1px solid #f0f0f0;
        border-bottom: 1px solid #f0f0f0;
    }

    .saa-q-item.q-high { background: #f0fdf7; border-left-color: #1d9e75; }
    .saa-q-item.q-mid  { background: #fffbf0; border-left-color: #ef9f27; }
    .saa-q-item.q-low  { background: #fff8f8; border-left-color: #e24b4a; }

    .saa-q-meta {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 10px;
        margin-bottom: 3px;
    }

    .saa-q-text { font-size: 17px; color: #333; line-height: 1.5; flex: 1; word-break: break-word; }
    .saa-q-pct  { font-size: 18px; font-weight: 500; white-space: nowrap; flex-shrink: 0; }

    .saa-q-item.q-high .saa-q-pct { color: #0f6e56; }
    .saa-q-item.q-mid  .saa-q-pct { color: #854f0b; }
    .saa-q-item.q-low  .saa-q-pct { color: #a32d2d; }

    .saa-q-count { font-size: 17px; color: #999; display: block; margin-bottom: 7px; }

    .saa-bar-track { height: 6px; background: rgba(0,0,0,0.06); border-radius: 99px; overflow: hidden; }
    .saa-bar-fill  { height: 100%; border-radius: 99px; }

    /* -”€-”€ AI card -”€-”€ */
    .saa-ai-card {
        border-left: 3px solid #7f77dd !important;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .saa-ai-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    .saa-ai-body {
        font-size: 17px;
        color: #444;
        line-height: 1.8;
    }

    .saa-ai-body p             { margin: 0 0 8px; }
    .saa-ai-body p:last-child  { margin-bottom: 0; }
    .saa-ai-body strong        { font-weight: 500; color: #111; }
    .saa-ai-body ul            { padding-left: 1.2rem; margin: 4px 0 8px; }
    .saa-ai-body li            { margin-bottom: 3px; }

    .saa-generate-btn {
        height: 38px;
        padding: 0 16px;
        background: transparent;
        border: 1px solid #e4e4e4;
        border-radius: 10px;
        font-family: 'DM Sans', sans-serif;
        font-size: 16px;
        color: #888;
        cursor: pointer;
        transition: border-color 0.15s, color 0.15s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .saa-generate-btn:hover { border-color: #111; color: #111; }

    .saa-empty { font-size: 18px; color: #bbb; text-align: center; padding: 2.2rem 0; }

    .saa-alert { padding: 12px 16px; border-radius: 10px; font-size: 17px; }
    .saa-alert.success { background: #e1f5ee; color: #0f6e56; }
    .saa-alert.error   { background: #fcebeb; color: #a32d2d; }

    @media (max-width: 1100px) {
        .saa-grid  { grid-template-columns: 1fr 1fr; }
    }

    @media (max-width: 860px) {
        .saa-stats { grid-template-columns: repeat(2, 1fr); }
        .saa-grid  { grid-template-columns: 1fr; }
    }
</style>

@php
    $avgClass = ($avgScore ?? 0) >= 75 ? 'high' : (($avgScore ?? 0) >= 50 ? 'mid' : 'low');
@endphp

<div class="saa-wrap">

    <a href="{{ route('student.performance', $class) }}" class="saa-back">
        <i class="fas fa-arrow-left" style="font-size: 18px;"></i>
        Back to Student Performance
    </a>

    {{-- Student header --}}
    <div class="saa-student-head">
        <div class="saa-avatar"><i class="fas fa-user"></i></div>
        <div>
            <p class="saa-student-name">{{ $student->name }}</p>
            <div class="saa-student-meta">
                @if($student->program)
                    <span class="saa-badge">{{ $student->program }}</span>
                @endif
                <span class="saa-badge class">{{ $class->name }}</span>
                <span class="saa-badge">Formal Assessment Report</span>
            </div>
        </div>
    </div>

    {{-- Stats row --}}
    <div class="saa-stats">
        <div class="saa-stat">
            <p class="saa-stat-label">Attempts</p>
            <p class="saa-stat-val">{{ $totalAttempts }}</p>
        </div>
        <div class="saa-stat">
            <p class="saa-stat-label">Average Score</p>
            <p class="saa-stat-val {{ $avgClass }}">{{ $avgScore }}%</p>
        </div>
        <div class="saa-stat">
            <p class="saa-stat-label">Best Score</p>
            <p class="saa-stat-val {{ ($bestScore ?? 0) >= 75 ? 'green' : (($bestScore ?? 0) >= 50 ? 'amber' : 'red') }}">{{ $bestScore }}%</p>
        </div>
        <div class="saa-stat">
            <p class="saa-stat-label">Passed</p>
            <p class="saa-stat-val {{ $passedCount > 0 ? 'green' : 'red' }}">{{ $passedCount }}/{{ $totalAttempts }}</p>
        </div>
    </div>

    {{-- Main grid --}}
    <div class="saa-grid">

        {{-- Attempt history --}}
        <div class="saa-card">
            <p class="saa-card-title">Attempt History</p>
            @if($attempts->isNotEmpty())
                <table class="saa-table">
                    <thead>
                        <tr>
                            <th>Assessment</th>
                            <th style="text-align:right">Score</th>
                            <th style="text-align:right">Result</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($attempts as $attempt)
                            @php
                                $pct = (float) $attempt->percentage;
                                $pillClass = $pct >= 75 ? 'high' : ($pct >= 50 ? 'mid' : 'low');
                            @endphp
                            <tr>
                                <td style="max-width:160px;word-break:break-word;">
                                    {{ $attempt->module?->title ?? 'Unknown' }}
                                </td>
                                <td style="text-align:right">
                                    <span class="saa-score-pill {{ $pillClass }}">
                                        {{ number_format($pct, 1) }}%
                                    </span>
                                </td>
                                <td style="text-align:right">
                                    <span class="saa-pass-badge {{ $attempt->passed ? 'pass' : 'fail' }}">
                                        {{ $attempt->passed ? 'Passed' : 'Failed' }}
                                    </span>
                                </td>
                                <td style="font-size: 17px;color:#aaa;white-space:nowrap;">
                                    {{ $attempt->created_at?->format('M d, Y') ?? '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="saa-empty">No assessment attempts yet.</p>
            @endif
        </div>

        {{-- Question performance breakdown --}}
        <div class="saa-card">
            <p class="saa-card-title">Question Performance</p>
            @if($questionPerformance->isNotEmpty())
                <div class="saa-q-list">
                    @foreach($questionPerformance as $q)
                        @php
                            $qPct    = (float) $q->pct_correct;
                            $qColor  = $qPct >= 70 ? '#1d9e75' : ($qPct >= 40 ? '#ef9f27' : '#e24b4a');
                            $qClass  = $qPct >= 70 ? 'q-high' : ($qPct >= 40 ? 'q-mid' : 'q-low');
                        @endphp
                        <div class="saa-q-item {{ $qClass }}">
                            <div class="saa-q-meta">
                                <span class="saa-q-text">{{ html_entity_decode(strip_tags($q->question_text)) }}</span>
                                <span class="saa-q-pct">{{ $qPct }}%</span>
                            </div>
                            <span class="saa-q-count">
                                {{ $q->correct_count }}/{{ $q->attempt_count }} correct
                            </span>
                            <div class="saa-bar-track">
                                <div class="saa-bar-fill" style="width:{{ $qPct }}%;background:{{ $qColor }};"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="saa-empty">No question data yet.</p>
            @endif
        </div>

        {{-- AI Analysis --}}
        <div class="saa-card saa-ai-card">
        <div class="saa-ai-head">
            <p class="saa-card-title" style="margin:0;">
                <i class="fas fa-robot" style="color:#7f77dd;margin-right:6px;font-size: 18px;"></i>
                AI Assessment Analysis
            </p>
            <form action="{{ route('student.assessment.analysis.generate-ai', [$class, $student]) }}"
                  method="POST" id="generateAiForm">
                @csrf
                <button type="submit" class="saa-generate-btn" @if($totalAttempts === 0 || !($isAssessmentAnalysisEnabled ?? true)) disabled @endif>
                    <i class="fas fa-sync-alt" style="font-size: 18px;"></i>
                    {{ $aiAnalysis ? 'Regenerate' : 'Generate Analysis' }}
                </button>
            </form>
        </div>

        @if(!($isAssessmentAnalysisEnabled ?? true))
            <p class="saa-empty" style="padding:1.2rem 0;">
                Assessment AI analysis is disabled for this class.
            </p>
        @elseif($aiAnalysis)
            <div class="saa-ai-body" id="aiAnalysisBody"></div>
            <div id="aiAnalysisRaw" style="display:none;">{{ $aiAnalysis }}</div>
        @elseif($totalAttempts > 0)
            <p class="saa-empty" style="padding:1.2rem 0;">
                Click <strong>Generate Analysis</strong> to get an AI-powered breakdown of this student's assessment performance.
            </p>
        @else
            <p class="saa-empty" style="padding:1.2rem 0;">
                No assessment attempts found. AI analysis is unavailable.
            </p>
        @endif
    </div>

    </div>

</div>
@endsection

@section('scripts')
<script>
function parseAI(text) {
    var t = document.createElement('textarea');
    t.innerHTML = text || '';
    text = t.value;
    text = text.replace(/\\n/g, '\n');
    text = text.replace(/<[^>]+>/g, '');
    text = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
    text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    var lines = text.split('\n'), html = '', inList = false;
    lines.forEach(function (line) {
        line = line.trim();
        if (!line) { if (inList) { html += '</ul>'; inList = false; } return; }
        if (/^[-*•]\s+/.test(line) || /^\d+\.\s+/.test(line)) {
            if (!inList) { html += '<ul>'; inList = true; }
            html += '<li>' + line.replace(/^[-*•]\s+/, '').replace(/^\d+\.\s+/, '') + '</li>';
            return;
        }
        if (inList) { html += '</ul>'; inList = false; }
        if (/:\s*$/.test(line) && line.length < 60) { html += '<p><strong>' + line + '</strong></p>'; return; }
        html += '<p>' + line + '</p>';
    });
    if (inList) html += '</ul>';
    return html;
}

(function () {
    var rawEl  = document.getElementById('aiAnalysisRaw');
    var bodyEl = document.getElementById('aiAnalysisBody');
    if (rawEl && bodyEl) {
        bodyEl.innerHTML = parseAI(rawEl.textContent || '');
    }
})();
</script>
@endsection


