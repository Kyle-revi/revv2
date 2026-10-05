@php
    $programLayoutMap = [
        'psych' => 'layouts.appPsych',
        'accountancy' => 'layouts.appAcc',
        'educ' => 'layouts.appEduc',
    ];
    $layout = $programLayoutMap[auth()->user()->program ?? ''] ?? 'layouts.appAcc';

    $preTestPhaseItem = collect($phasesDetail)->firstWhere('phase_type', 'pre_test');
    $preTestAttemptItem = $preTestPhaseItem['attempt'] ?? null;
@endphp
@extends($layout)

@section('title', 'Performance Report')
@section('page-heading', 'Performance Report')

@section('header-actions')
    <a href="{{ route('student.mock-boards.index') }}" class="rv-btn rv-btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Mock Boards
    </a>
@endsection

@section('content')
<div class="results-container">

    <div class="results-intro">
        <div class="results-icon"><i class="fas fa-chart-line"></i></div>
        <div>
            <h2 class="results-title">{{ $mockBoard->title }}</h2>
            <p class="results-subtitle">{{ ucfirst($mockBoard->program) }} &bull; Passing: {{ $mockBoard->passing_percentage }}%</p>
        </div>
    </div>

    {{-- OVERALL POST-TEST SUMMARY (when post-tests exist) --}}
    @if(isset($overallPostTest) && $overallPostTest)
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px 24px; margin-bottom: 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    <span style="font-size: 13px; font-weight: 500; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">Overall Post-Test Performance</span>
                    <h3 style="margin: 4px 0 0 0; font-size: 20px; font-weight: 500; color: #1e293b;">
                        Best Score: {{ (int) round($overallPostTest['best_percentage']) }}%
                    </h3>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span class="score-status-badge {{ $overallPostTest['passed'] ? 'pass' : 'fail' }}" style="font-size: 14px; padding: 6px 14px;">
                        {{ $overallPostTest['passed'] ? 'Passed Overall' : 'Below Passing' }}
                    </span>
                    <span style="font-size: 13px; color: #64748b;">
                        ({{ $overallPostTest['phases_attempted'] }} of {{ $overallPostTest['phases_total'] }} Post-Tests completed)
                    </span>
                </div>
            </div>
        </div>
    @endif

    {{-- GROWTH INSIGHT --}}
    @if($preTestAttemptItem && isset($overallPostTest) && $overallPostTest)
        @php 
            $diff = $overallPostTest['best_percentage'] - $preTestAttemptItem->percentage; 
        @endphp
        <div class="growth-box {{ $diff >= 0 ? 'positive' : 'negative' }}" style="margin-bottom: 24px;">
            <div class="growth-icon">
                <i class="fas fa-{{ $diff >= 0 ? 'arrow-trend-up' : 'arrow-trend-down' }}"></i>
            </div>
            <div>
                <p class="growth-label">Your Growth</p>
                <p class="growth-value">
                    {{ $diff >= 0 ? '+' : '-' }}{{ abs($diff) }}%
                    <span class="growth-note">
                        {{ $diff >= 0 ? 'improvement from Pre-Test (' . $preTestAttemptItem->percentage . '%) to Best Post-Test (' . $overallPostTest['best_percentage'] . '%)' : 'change from Pre-Test (' . $preTestAttemptItem->percentage . '%) to Best Post-Test (' . $overallPostTest['best_percentage'] . '%)' }}
                    </span>
                </p>
            </div>
        </div>
    @elseif($preTestAttemptItem && (!isset($overallPostTest) || !$overallPostTest))
        <div class="growth-box neutral" style="margin-bottom: 24px;">
            <div class="growth-icon"><i class="fas fa-hourglass-half"></i></div>
            <div>
                <p class="growth-label">Keep Going</p>
                <p class="growth-value" style="font-size:15px;">Complete a Post-Test phase to see your growth analysis.</p>
            </div>
        </div>
    @endif

    {{-- BOARD PASSING LIKELIHOOD CARD (75% PRC Benchmark) - ONLY SHOWN WHEN ENTIRE BOARD EXAM (PRE-TEST & POST-TEST) IS COMPLETED --}}
    @php
        $hasFinishedEntireBoard = $preTestAttemptItem && isset($overallPostTest) && $overallPostTest;
        $finalScore = $hasFinishedEntireBoard ? $overallPostTest['best_percentage'] : null;
        $threshold = $mockBoard->passing_percentage ?? 75;
    @endphp

    @if($hasFinishedEntireBoard && $finalScore !== null)
        @php
            $tierData = \App\Services\MockBoardStatisticsService::calculateReadinessTier((float) $finalScore, (int) $threshold);
            $blTier = $tierData['tier'];
            $blLabel = $tierData['label'];
            $blIcon = $tierData['icon'];
            $blNote = $tierData['description'];
            $blBoxClass = $tierData['color'];
        @endphp
        <div class="growth-box {{ $blBoxClass }}" style="margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
            <div style="display: flex; align-items: center; gap: 16px; flex: 1; min-width: 280px;">
                <div class="growth-icon">
                    <i class="fas {{ $blIcon }}"></i>
                </div>
                <div>
                    <p class="growth-label">Board Passing Likelihood</p>
                    <p class="growth-value">
                        {{ $blLabel }}
                        <span class="growth-note">
                            &bull; {{ $blNote }}
                        </span>
                    </p>
                </div>
            </div>
            <div style="flex-shrink: 0;">
                <a href="{{ route('student.mock-boards.readiness', $mockBoard) }}" class="btn-readiness" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 9px; font-weight: 500; font-size: 13.5px; text-decoration: none; background: #245E55; color: #ffffff; white-space: nowrap; box-shadow: 0 2px 4px rgba(36,94,85,0.25); transition: all 0.2s ease;">
                    <i class="fas fa-file-invoice"></i> View Readiness Report
                </a>
            </div>
        </div>
    @endif

    {{-- DYNAMIC PER-PHASE SCORE CARDS --}}
    <div class="score-cards">
        @foreach($phasesDetail as $phase)
            @php
                $phaseId = $phase['id'];
                $phaseType = $phase['phase_type'];
                $phaseLabel = $phase['label'];
                $phaseAttempt = $phase['attempt'];
                $isPreTest = $phaseType === 'pre_test';
            @endphp

            <div class="score-card {{ $phaseAttempt ? 'done' : 'pending' }}" id="scoreCard_{{ $phaseId }}">
                <div class="score-card-head" @if($phaseAttempt) onclick="toggleScoreCard('{{ $phaseId }}')" style="cursor:pointer;" @endif>
                    <span class="score-card-label">
                        <i class="fas {{ $isPreTest ? 'fa-pencil-alt' : 'fa-clipboard-check' }}" style="color: #245E55;"></i>
                        {{ $phaseLabel }}
                    </span>
                    <div style="display:flex;align-items:center;gap:10px;">
                        @if($phaseAttempt)
                            <span class="score-status-badge {{ $phaseAttempt->passed ? 'pass' : 'fail' }}">
                                {{ $phaseAttempt->passed ? 'Passed' : 'Failed' }}
                            </span>
                            <span class="score-card-pct">{{ (int) round($phaseAttempt->percentage) }}%</span>
                            <i class="fas fa-chevron-down score-card-chevron"></i>
                        @else
                            <span class="score-status-badge pending">Not Taken</span>
                        @endif
                    </div>
                </div>

                @if($phaseAttempt)
                    @php
                        $pct = (int) round($phaseAttempt->percentage);
                        $passed = $phaseAttempt->passed;
                        $color = $passed ? '#1d9e75' : '#e24b4a';
                        $dashArr = 251;
                        $dashOff = $dashArr - ($pct / 100 * $dashArr);
                    @endphp

                    <div class="score-card-body" id="cardBody_{{ $phaseId }}">
                        <div class="score-card-body-inner">
                            <div class="qz-gauge-wrap">
                                <svg width="240" height="138" viewBox="0 0 240 138">
                                    <path d="M 35 118 A 85 85 0 0 1 205 118" fill="none" stroke="#f3f3f3" stroke-width="16" stroke-linecap="round"/>
                                    <path d="M 35 118 A 85 85 0 0 1 205 118" fill="none" stroke="{{ $color }}"
                                        stroke-width="16" stroke-linecap="round"
                                        stroke-dasharray="{{ $dashArr }}" stroke-dashoffset="{{ $dashOff }}"
                                        style="transition:stroke-dashoffset 1s ease;"/>
                                </svg>
                                <div class="qz-gauge-score">{{ $pct }}%</div>
                            </div>

                            <p class="qz-verdict {{ $passed ? 'pass' : 'fail' }}">
                                <i class="fas fa-{{ $passed ? 'check-circle' : 'times-circle' }}"></i>
                                {{ $passed ? ' You passed!' : ' You did not pass.' }}
                                &nbsp;{{ $phaseAttempt->score }} / {{ $phaseAttempt->total_questions }} correct.
                            </p>

                            <div class="qz-ai-box" id="aiBox_{{ $phaseId }}">
                                <div class="qz-ai-header" style="margin-bottom: 0; border-bottom: none; padding-bottom: 0;">
                                    <div class="qz-ai-header-left">
                                        <span class="qz-ai-icon-badge"><i class="fas fa-brain fa-pulse"></i></span>
                                        <div>
                                            <h4 class="qz-ai-title">AI Performance Diagnostic</h4>
                                            <p class="qz-ai-subtitle" style="color: #7c3aed;">Analyzing your responses and formulating tailored recommendations...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="qz-history-card" id="historyBox_{{ $phaseId }}">
                                <p class="qz-history-title"><i class="fas fa-history"></i> Attempt History</p>
                                <p class="qz-history-empty">Loading history...</p>
                            </div>
                        </div>
                    </div>
                @else
                    <p class="score-detail muted" style="margin-top:16px;">You haven't taken this phase yet.</p>
                @endif
            </div>
        @endforeach
    </div>

</div>

@php
    $cachedInsightsMap = [];
    foreach ($phasesDetail as $p) {
        $att = $p['attempt'] ?? null;
        if ($att) {
            $cachedInsightsMap[$p['id']] = [
                'strong' => $att->ai_strong,
                'weak' => $att->ai_weak,
                'recommendation' => $att->ai_recommendation,
            ];
        } else {
            $cachedInsightsMap[$p['id']] = null;
        }
    }
@endphp

<script>
    var csrfToken = '{{ csrf_token() }}';
    var historyDataByPhase = @json($historyByPhaseId);
    var phaseIds = @json(collect($phasesDetail)->pluck('id'));

    var insightsRoutes = {
        @foreach($phasesDetail as $p)
            '{{ $p["id"] }}': '{{ route("student.mock-boards.insights", [$mockBoard, $p["id"]]) }}',
        @endforeach
    };

    var cachedInsights = @json($cachedInsightsMap);

    var loadedPhases = {}; // tracks which phases already had AI/history fetched (lazy load)

    function escHtml(str) {
        var d = document.createElement('div');
        d.textContent = String(str);
        return d.innerHTML;
    }

    function formatAiInsightHtml(rawText, fallbackText = '') {
        if (!rawText || !rawText.trim()) {
            return `<p style="margin:0;color:#94a3b8;font-style:italic;">${fallbackText || 'None recorded'}</p>`;
        }

        let text = rawText.trim();
        text = text.replace(/([^\n])\s*-\s+/g, '$1\n- ');
        text = text.replace(/([^\n])\s*(\d+\.\s+)/g, '$1\n$2');

        const lines = text.split('\n');
        let html = '';
        let listItems = [];
        let isOrdered = false;
        let isUnordered = false;

        for (let i = 0; i < lines.length; i++) {
            let line = lines[i].trim();
            if (!line) continue;

            const isBullet = line.startsWith('- ') || line.startsWith('* ');
            const numMatch = line.match(/^(\d+)\.\s+(.*)$/);

            if (isBullet) {
                if (isOrdered && listItems.length > 0) {
                    html += `<ol>${listItems.join('')}</ol>`;
                    listItems = [];
                    isOrdered = false;
                }
                isUnordered = true;
                let content = line.substring(2).trim();
                content = content.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
                content = content.replace(/__(.*?)__/g, '<strong>$1</strong>');
                listItems.push(`<li>${content}</li>`);
            } else if (numMatch) {
                if (isUnordered && listItems.length > 0) {
                    html += `<ul>${listItems.join('')}</ul>`;
                    listItems = [];
                    isUnordered = false;
                }
                isOrdered = true;
                let content = numMatch[2].trim();
                content = content.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
                content = content.replace(/__(.*?)__/g, '<strong>$1</strong>');
                listItems.push(`<li>${content}</li>`);
            } else {
                if (listItems.length > 0) {
                    html += isOrdered ? `<ol>${listItems.join('')}</ol>` : `<ul>${listItems.join('')}</ul>`;
                    listItems = [];
                    isOrdered = false;
                    isUnordered = false;
                }
                line = line.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
                line = line.replace(/__(.*?)__/g, '<strong>$1</strong>');
                html += `<p style="margin:0 0 5px 0;">${line}</p>`;
            }
        }

        if (listItems.length > 0) {
            html += isOrdered ? `<ol>${listItems.join('')}</ol>` : `<ul>${listItems.join('')}</ul>`;
        }

        return html || `<p style="margin:0;">${text}</p>`;
    }

    function renderAiInsightsCard(strong, weak, recommendation) {
        const strongHtml = formatAiInsightHtml(strong, 'None detected');
        const weakHtml = formatAiInsightHtml(weak, 'No critical weak areas detected');
        const recHtml = formatAiInsightHtml(recommendation, 'Review the phase concepts before retaking.');

        return `
            <div class="qz-ai-header">
                <div class="qz-ai-header-left">
                    <span class="qz-ai-icon-badge"><i class="fas fa-brain"></i></span>
                    <div>
                        <h4 class="qz-ai-title">AI Performance Diagnostic</h4>
                        <p class="qz-ai-subtitle">Personalized feedback based on your responses</p>
                    </div>
                </div>
                <span class="qz-ai-badge">Instant Analysis</span>
            </div>
            <div class="qz-ai-grid">
                <div class="qz-ai-card qz-ai-card-strong">
                    <div class="qz-ai-card-head">
                        <i class="fas fa-check-circle"></i>
                        <span>Mastered Concepts</span>
                    </div>
                    <div class="qz-ai-card-body">
                        ${strongHtml}
                    </div>
                </div>
                <div class="qz-ai-card qz-ai-card-weak">
                    <div class="qz-ai-card-head">
                        <i class="fas fa-bullseye"></i>
                        <span>Priority Focus Areas</span>
                    </div>
                    <div class="qz-ai-card-body">
                        ${weakHtml}
                    </div>
                </div>
                <div class="qz-ai-card qz-ai-card-rec">
                    <div class="qz-ai-card-head">
                        <i class="fas fa-lightbulb"></i>
                        <span>Actionable Study Plan</span>
                    </div>
                    <div class="qz-ai-card-body">
                        ${recHtml}
                    </div>
                </div>
            </div>
        `;
    }

    function renderAiInsightsLoading() {
        return `
            <div class="qz-ai-header" style="margin-bottom: 0; border-bottom: none; padding-bottom: 0;">
                <div class="qz-ai-header-left">
                    <span class="qz-ai-icon-badge"><i class="fas fa-brain fa-pulse"></i></span>
                    <div>
                        <h4 class="qz-ai-title">AI Performance Diagnostic</h4>
                        <p class="qz-ai-subtitle" style="color: #7c3aed;">Analyzing your responses and formulating tailored recommendations...</p>
                    </div>
                </div>
            </div>
        `;
    }

    function renderAiInsightsMessage(message) {
        return `
            <div class="qz-ai-header" style="margin-bottom: 0; border-bottom: none; padding-bottom: 0;">
                <div class="qz-ai-header-left">
                    <span class="qz-ai-icon-badge" style="background:#f1f5f9;color:#64748b;"><i class="fas fa-brain"></i></span>
                    <div>
                        <h4 class="qz-ai-title">AI Performance Diagnostic</h4>
                        <p class="qz-ai-subtitle">${message}</p>
                    </div>
                </div>
            </div>
        `;
    }

    function renderAiBox(phaseId, data) {
        var box = document.getElementById('aiBox_' + phaseId);
        if (!box) return;

        box.innerHTML = renderAiInsightsCard(data.strong, data.weak, data.recommendation);
    }

    function loadAiInsights(phaseId) {
        var box = document.getElementById('aiBox_' + phaseId);
        if (!box) return;
        var cached = cachedInsights[phaseId];
        if (cached && (cached.strong || cached.weak || cached.recommendation)) {
            renderAiBox(phaseId, cached);
            return;
        }
        var url = insightsRoutes[phaseId];
        if (!url) return;

        box.innerHTML = renderAiInsightsLoading();

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({}),
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            var data = {
                strong: (res.strong_areas || []).join(', '),
                weak: (res.weak_areas || []).join(', '),
                recommendation: res.recommendation,
            };
            cachedInsights[phaseId] = data;
            renderAiBox(phaseId, data);
        })
        .catch(function () {
            box.innerHTML = renderAiInsightsMessage('Failed to load insights.');
        });
    }

    function renderHistory(phaseId) {
        var box = document.getElementById('historyBox_' + phaseId);
        if (!box) return;
        var attempts = historyDataByPhase[phaseId] || [];
        if (!attempts.length) {
            box.innerHTML = '<p class="qz-history-title"><i class="fas fa-history"></i> Attempt History</p><p class="qz-history-empty">No previous attempts recorded yet.</p>';
            return;
        }
        var rows = attempts.map(function (a) {
            var pct = Math.round(a.percentage);
            var scoreClass = a.passed ? 'pass' : 'fail';
            var dateStr = a.completed_at ? new Date(a.completed_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
            var itemId = phaseId + '_' + a.attempt_number;
            var qHtml = (a.questions || []).map(function (q, i) {
                var cls = q.is_correct ? 'correct' : 'incorrect';
                var yourAns = q.selected_option ? q.selected_option + (q.options && q.options[q.selected_option] ? ' - ' + q.options[q.selected_option] : '') : 'No answer';
                var correctAns = q.correct_option ? q.correct_option + (q.options && q.options[q.correct_option] ? ' - ' + q.options[q.correct_option] : '') : '-';
                return '<div class="qz-history-q ' + cls + '">' +
                    '<p class="qz-history-q-text">' + (i + 1) + '. ' + escHtml(q.question_text || '') + '</p>' +
                    '<p class="qz-history-q-ans"><i class="fas fa-' + (q.is_correct ? 'check' : 'times') + '"></i> Your answer: ' + escHtml(yourAns) + '</p>' +
                    (!q.is_correct ? '<p class="qz-history-q-ans"><i class="fas fa-check"></i> Correct answer: ' + escHtml(correctAns) + '</p>' : '') +
                    '</div>';
            }).join('');
            return '<div class="qz-history-item" id="historyItem_' + itemId + '">' +
                '<div class="qz-history-row" onclick="toggleHistoryItem(\'' + itemId + '\')">' +
                '<div class="qz-history-left"><span class="qz-history-num">Attempt ' + a.attempt_number + '</span>' +
                '<span class="qz-history-score ' + scoreClass + '">' + pct + '% &bull; ' + a.score + '/' + a.total + '</span></div>' +
                '<div style="display:flex;align-items:center;gap:8px;"><span class="qz-history-date">' + dateStr + '</span>' +
                '<i class="fas fa-chevron-down qz-history-chevron"></i></div></div>' +
                '<div class="qz-history-detail" id="historyDetail_' + itemId + '">' + qHtml + '</div></div>';
        }).join('');
        box.innerHTML = '<p class="qz-history-title"><i class="fas fa-history"></i> Attempt History</p>' + rows;
    }

    function toggleHistoryItem(itemId) {
        var item = document.getElementById('historyItem_' + itemId);
        if (item) item.classList.toggle('open');
    }

    // ---- Accordion behavior for Phase cards ----
    function toggleScoreCard(phaseId) {
        var card = document.getElementById('scoreCard_' + phaseId);
        if (!card) return;
        var isOpen = card.classList.contains('expanded');

        // close every card first (accordion: only one open at a time)
        phaseIds.forEach(function (pid) {
            var c = document.getElementById('scoreCard_' + pid);
            if (c) c.classList.remove('expanded');
        });

        // if it wasn't open before, open it now (clicking an open card just closes it)
        if (!isOpen) {
            card.classList.add('expanded');
            if (!loadedPhases[phaseId]) {
                loadAiInsights(phaseId);
                renderHistory(phaseId);
                loadedPhases[phaseId] = true;
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        // auto-open the first completed phase by default (or the latest completed post-test)
        @php
            $firstCompleted = collect($phasesDetail)->whereNotNull('attempt')->last() 
                ?? collect($phasesDetail)->firstWhere('attempt', '!==', null);
        @endphp
        var defaultPhaseId = @json($firstCompleted ? $firstCompleted['id'] : null);
        if (defaultPhaseId) {
            toggleScoreCard(defaultPhaseId);
        }
    });
</script>
@endsection

@section('head')
<style>
    html {
        overflow-y: scroll;
    }

    .results-container {
        max-width: 640px; margin: 0 auto; padding: 4px 0 20px;
        font-family: 'DM Sans', sans-serif;
    }

    .results-intro { display: flex; align-items: center; gap: 16px; margin-bottom: 28px; }
    .results-icon { width: 52px; height: 52px; border-radius: 12px; background: #e6f4ea; color: #245E55; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
    .results-title { margin: 0; font-family: 'DM Sans', sans-serif; font-size: 24px; font-weight: 500; color: #111; }
    .results-subtitle { margin: 4px 0 0; font-size: 14px; color: #aaa; font-weight: 500; }

    .score-cards { display: flex; flex-direction: column; gap: 24px; margin-bottom: 24px; }
    .score-card { background: #fff; padding: 30px 28px; border-radius: 14px; text-align: center; border: 1px solid #ebebeb; }
    .score-card.done { border-top: 4px solid #245E55; }
    .score-card.pending { border-top: 4px solid #ebebeb; opacity: 0.85; }

    .score-card-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
    .score-card-label { font-size: 14px; font-weight: 500; letter-spacing: 0.03em; color: #334155; display: flex; align-items: center; gap: 8px; }

    .score-status-badge { font-size: 13px; font-weight: 500; padding: 4px 11px; border-radius: 99px; white-space: nowrap; }
    .score-status-badge.pass { background: #e1f5ee; color: #0f6e56; }
    .score-status-badge.fail { background: #fcebeb; color: #a32d2d; }
    .score-status-badge.pending { background: #f3f3f3; color: #aaa; }

    .score-card-pct { font-size: 14px; font-weight: 500; color: #111; }
    .score-card-chevron { color: #aaa; transition: transform 0.2s ease; }
    .score-card.expanded .score-card-chevron { transform: rotate(180deg); }

    .score-detail { font-size: 15px; color: #555; margin: 0; }
    .score-detail.muted { color: #aaa; }

    /* Collapsible body */
    .score-card-body {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.35s ease;
    }
    .score-card.expanded .score-card-body {
        max-height: 3000px; /* large enough to fit content */
    }
    .score-card-body-inner { padding-top: 18px; }

    .qz-gauge-wrap { position: relative; width: 240px; height: 138px; margin: 8px auto 4px; }
    .qz-gauge-score {
        position: absolute; bottom: 6px; left: 50%; transform: translateX(-50%);
        font-family: 'DM Sans', sans-serif; font-size: 42px; color: #111; line-height: 1;
    }

    .qz-verdict {
        font-size: 15px; font-weight: 500; margin: 0 0 20px;
        display: flex; align-items: center; justify-content: center; gap: 6px;
    }
    .qz-verdict.pass { color: #1d9e75; }
    .qz-verdict.fail { color: #e24b4a; }

    .qz-ai-box {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 18px 20px;
        text-align: left;
        margin-bottom: 20px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
    }
    .qz-ai-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 14px;
        padding-bottom: 10px;
        border-bottom: 1px solid #f1f5f9;
    }
    .qz-ai-header-left {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .qz-ai-icon-badge {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: #ede9fe;
        color: #7c3aed;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        flex-shrink: 0;
    }
    .qz-ai-title {
        font-size: 15px;
        font-weight: 500;
        color: #0f172a;
        margin: 0;
        line-height: 1.2;
    }
    .qz-ai-subtitle {
        font-size: 12px;
        color: #64748b;
        margin: 2px 0 0 0;
    }
    .qz-ai-badge {
        font-size: 11px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 3px 9px;
        border-radius: 99px;
        background: #ede9fe;
        color: #6d28d9;
    }
    .qz-ai-grid {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .qz-ai-card {
        border-radius: 10px;
        padding: 12px 14px;
        border: 1px solid;
    }
    .qz-ai-card-head {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12.5px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 6px;
    }
    .qz-ai-card-strong {
        background: #f0fdf4;
        border-color: #bbf7d0;
    }
    .qz-ai-card-strong .qz-ai-card-head {
        color: #15803d;
    }
    .qz-ai-card-weak {
        background: #fef2f2;
        border-color: #fecaca;
    }
    .qz-ai-card-weak .qz-ai-card-head {
        color: #b91c1c;
    }
    .qz-ai-card-rec {
        background: #faf5ff;
        border-color: #e9d5ff;
    }
    .qz-ai-card-rec .qz-ai-card-head {
        color: #6d28d9;
    }
    .qz-ai-card-body {
        font-size: 13.5px;
        color: #334155;
        line-height: 1.5;
    }
    .qz-ai-card-body ul,
    .qz-ai-card-body ol {
        margin: 0;
        padding-left: 18px;
    }
    .qz-ai-card-body li {
        margin-bottom: 5px;
        color: #334155;
    }
    .qz-ai-card-body li:last-child {
        margin-bottom: 0;
    }
    .qz-ai-card-body strong,
    .qz-ai-card-body b {
        font-weight: 500;
        color: #0f172a;
    }
    .qz-ai-card-body p {
        margin: 0 0 5px 0;
    }
    .qz-ai-card-body p:last-child {
        margin-bottom: 0;
    }

    .qz-history-card {
        background: #fff; border: 1px solid #ebebeb; border-radius: 11px;
        padding: 16px 18px; text-align: left;
    }
    .qz-history-title { font-size: 14px; font-weight: 500; color: #111; margin: 0 0 12px; display: flex; align-items: center; gap: 6px; }
    .qz-history-item { border: 1px solid #ebebeb; border-radius: 8px; margin-bottom: 8px; overflow: hidden; }
    .qz-history-item:last-child { margin-bottom: 0; }
    .qz-history-row { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; cursor: pointer; background: #fafafa; }
    .qz-history-row:hover { background: #f3f3f3; }
    .qz-history-left { display: flex; align-items: center; gap: 10px; }
    .qz-history-num { font-size: 13px; font-weight: 500; color: #555; }
    .qz-history-score { font-size: 12px; font-weight: 500; padding: 2px 8px; border-radius: 99px; background: #f3f3f3; color: #555; }
    .qz-history-score.pass { background: #e1f5ee; color: #0f6e56; }
    .qz-history-score.fail { background: #fcebeb; color: #a32d2d; }
    .qz-history-date { font-size: 12px; color: #aaa; }
    .qz-history-chevron { transition: transform 0.15s; color: #aaa; }
    .qz-history-item.open .qz-history-chevron { transform: rotate(180deg); }
    .qz-history-detail { display: none; padding: 14px; border-top: 1px solid #ebebeb; }
    .qz-history-item.open .qz-history-detail { display: block; }
    .qz-history-q { padding: 10px 12px; border-radius: 8px; margin-bottom: 8px; font-size: 13px; border: 1px solid #e4e4e4; }
    .qz-history-q:last-child { margin-bottom: 0; }
    .qz-history-q.correct   { background: #f0fdf7; border-color: #bfe8d6; }
    .qz-history-q.incorrect { background: #fff8f8; border-color: #f4c9c8; }
    .qz-history-q-text { font-weight: 500; color: #111; margin: 0 0 6px; }
    .qz-history-q-ans  { font-size: 12px; color: #555; margin: 2px 0; }
    .qz-history-empty { font-size: 13px; color: #aaa; text-align: center; padding: 12px 0; }

    .growth-box { display: flex; align-items: center; gap: 16px; padding: 20px 24px; border-radius: 14px; border: 1px solid; }
    .growth-box.positive { background: #f0fdfa; border-color: #a7e8d8; }
    .growth-box.negative { background: #fef2f2; border-color: #fecaca; }
    .growth-box.neutral { background: #fafafa; border-color: #ebebeb; }

    .growth-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
    .growth-box.positive .growth-icon { background: #e1f5ee; color: #0f6e56; }
    .growth-box.negative .growth-icon { background: #fcebeb; color: #a32d2d; }
    .growth-box.neutral .growth-icon { background: #f3f3f3; color: #888; }

    .growth-label { font-size: 14px; font-weight: 500; letter-spacing: 0.03em; margin: 0 0 4px; color: #aaa; }
    .growth-value { margin: 0; font-family: 'DM Sans', sans-serif; font-size: 24px; font-weight: 500; display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; }
    .growth-box.positive .growth-value { color: #0f6e56; }
    .growth-box.negative .growth-value { color: #a32d2d; }
    .growth-note { font-size: 14px; font-weight: 500; color: #555; }
</style>
@endsection