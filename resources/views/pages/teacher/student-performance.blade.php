@extends('layouts.appTeach')

@section('title', 'Student Performance')
@section('page-heading', 'Student Performance')

@section('content')
<style>
    .sp-wrap {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    .sp-sub {
        font-size: 19px;
        color: #888;
        margin: -6px 0 8px;
    }

    /* Class switcher */
    .sp-switcher {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .sp-switcher-label {
        font-size: 19px;
        font-weight: 500;
        color: #888;
        white-space: nowrap;
    }

    .sp-class-tabs {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .sp-class-tab {
        height: 38px; padding: 0 16px; border-radius: 10px;
        font-size: 18px; font-weight: 500;
        border: 1px solid #e4e4e4; background: #fff; color: #555;
        cursor: pointer; text-decoration: none;
        display: inline-flex; align-items: center;
        transition: background 0.15s, border-color 0.15s, color 0.15s;
    }

    .sp-class-tab:hover  { border-color: #bbb; color: #111; }
    .sp-class-tab.active { background: #0f0f0f; color: #fff; border-color: #0f0f0f; }

    .sp-top-grid {
        display: grid;
        grid-template-columns: 180px minmax(0, 1fr) minmax(0, 1fr);
        gap: 18px;
        align-items: stretch;
    }

    .sp-bottom-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
    }

    .sp-card {
        background: #fff;
        border: 1px solid #ebebeb;
        border-radius: 14px;
        padding: 1.35rem 1.5rem;
        min-width: 0;
    }

    .sp-section-title {
        font-size: 22px;
        font-weight: 500;
        color: #111;
        margin: 0 0 16px;
    }

    /* Class Avg card */
    .sp-avg-card {
        display: flex; flex-direction: column;
        justify-content: center; align-items: center;
        border-width: 1px; border-style: solid; border-radius: 12px;
        padding: 1rem 1.1rem; min-width: 0; transition: background 0.3s;
        min-height: 300px;
        text-align: center;
    }

    .sp-avg-card.high { background: #e1f5ee; border-color: #9fe1cb; }
    .sp-avg-card.mid  { background: #faeeda; border-color: #fac775; }
    .sp-avg-card.low  { background: #fcebeb; border-color: #f7c1c1; }

    .sp-card-label {
        font-size: 17px; font-weight: 500;
        letter-spacing: 0.06em; text-transform: uppercase; margin: 0 0 8px;
        text-align: center;
        white-space: nowrap;
    }

    .sp-avg-card.high .sp-card-label { color: #0f6e56; }
    .sp-avg-card.mid  .sp-card-label { color: #854f0b; }
    .sp-avg-card.low  .sp-card-label { color: #a32d2d; }

    .sp-avg-val {
        font-family: 'DM Sans', sans-serif;
        font-size: 48px; font-weight: 500; line-height: 1; margin: 0 0 6px;
    }

    .sp-avg-card.high .sp-avg-val { color: #085041; }
    .sp-avg-card.mid  .sp-avg-val { color: #633806; }
    .sp-avg-card.low  .sp-avg-val { color: #791f1f; }

    .sp-avg-unit { font-size: 17px; opacity: 0.7; }

    .sp-chart-card { display: flex; flex-direction: column; }

    .sp-chart-wrap {
        position: relative; width: 100%; flex: 1; min-height: 220px;
    }

    .sp-chart-wrap canvas { display: block; margin: 0 auto; }


    /* Question items (legacy list, kept for the empty state / fallback) */
    .sp-q-list {
        display: flex; flex-direction: column; gap: 8px;
        max-height: 300px; overflow-y: auto; padding-right: 4px;
    }

    .sp-q-list::-webkit-scrollbar { width: 4px; }
    .sp-q-list::-webkit-scrollbar-thumb { background: #e8e8e8; border-radius: 99px; }

    .sp-q-item {
        padding: 12px 14px; border-radius: 10px;
        border-left: 3px solid transparent; background: #fafafa;
        border-top: 1px solid #f0f0f0; border-right: 1px solid #f0f0f0; border-bottom: 1px solid #f0f0f0;
    }

    .sp-q-item.q-high { background: #f0fdf7; border-left-color: #1d9e75; }
    .sp-q-item.q-mid  { background: #fffbf0; border-left-color: #ef9f27; }
    .sp-q-item.q-low  { background: #fff8f8; border-left-color: #e24b4a; }

    .sp-q-meta {
        display: flex; justify-content: space-between;
        align-items: baseline; margin-bottom: 3px; gap: 10px;
    }

    .sp-q-text  { font-size: 18px; color: #333; line-height: 1.5; flex: 1; word-break: break-word; }
    .sp-q-pct   { font-size: 19px; font-weight: 500; white-space: nowrap; flex-shrink: 0; }

    .sp-q-item.q-high .sp-q-pct { color: #0f6e56; }
    .sp-q-item.q-mid  .sp-q-pct { color: #854f0b; }
    .sp-q-item.q-low  .sp-q-pct { color: #a32d2d; }

    .sp-q-count { font-size: 18px; color: #999; display: block; margin-bottom: 7px; }

    .sp-bar-track { height: 6px; background: rgba(0,0,0,0.06); border-radius: 99px; overflow: hidden; }
    .sp-bar-fill  { height: 100%; border-radius: 99px; transition: width 0.5s ease; }

    /* Ranking table */
    .sp-table { width: 100%; border-collapse: collapse; font-size: 18px; }

    .sp-table thead th {
        font-size: 14px; font-weight: 500; letter-spacing: 0.08em;
        text-transform: uppercase; color: #bbb;
        padding: 10px 10px 12px; text-align: left; border-bottom: 1px solid #f0f0f0;
    }

    .sp-table tbody tr { border-bottom: 1px solid #f7f7f7; transition: background 0.1s; }
    .sp-table tbody tr:hover { background: #fafafa; }
    .sp-table tbody td { padding: 12px 10px; color: #333; vertical-align: middle; }

    .sp-rank-num { font-size: 19px; color: #999; font-weight: 500; width: 32px; }

    .sp-rank-medal {
        display: inline-flex; align-items: center; justify-content: center;
        width: 28px; height: 28px; border-radius: 50%;
        font-size: 16px; font-weight: 500;
    }

    .sp-rank-medal.gold   { background: #faeeda; color: #854f0b; }
    .sp-rank-medal.silver { background: #f1efe8; color: #5f5e5a; }
    .sp-rank-medal.bronze { background: #faece7; color: #712b13; }

    .sp-score-pill {
        display: inline-block; font-size: 18px; font-weight: 500;
        padding: 4px 11px; border-radius: 99px; background: #f3f3f3; color: #555;
    }

    .sp-score-pill.high { background: #e1f5ee; color: #0f6e56; }
    .sp-score-pill.mid  { background: #faeeda; color: #854f0b; }
    .sp-score-pill.low  { background: #fcebeb; color: #a32d2d; }

    /* AI card */
    .sp-ai-card { display: flex; flex-direction: column; border-left: 3px solid #7f77dd !important; }

    .sp-ai-body { font-size: 18px; color: #444; line-height: 1.8; flex: 1; }
    .sp-ai-body p            { margin: 0 0 8px; }
    .sp-ai-body p:last-child  { margin-bottom: 0; }
    .sp-ai-body strong        { font-weight: 500; color: #111; }
    .sp-ai-body ul            { padding-left: 1.2rem; margin: 4px 0 8px; }
    .sp-ai-body li            { margin-bottom: 3px; }

    .sp-ai-footer { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #f0f0f0; }

    .sp-refresh-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 32px;
        padding: 0 12px;
        background: #fff;
        border: 1px solid #e4e4e4;
        border-radius: 8px;
        font-family: 'DM Sans', sans-serif;
        font-size: 13px;
        font-weight: 500;
        color: #555;
        white-space: nowrap;
        line-height: 1;
        cursor: pointer;
        transition: border-color 0.15s, color 0.15s, background 0.15s;
        text-decoration: none;
        box-sizing: border-box;
    }

    .sp-analysis-btn {
        height: 30px;
        padding: 0 10px;
        font-size: 12px;
        gap: 5px;
        white-space: nowrap;
    }

    .sp-analysis-btn i {
        font-size: 12px;
        margin-right: 0;
        flex-shrink: 0;
    }

    .sp-refresh-btn:hover { border-color: #111; color: #111; background: #f8fafc; }

    .sp-export-btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        height: 34px;
        padding: 0 14px;
        background: #107c41;
        color: #fff;
        border: 1px solid #0b6a37;
        border-radius: 8px;
        font-family: 'DM Sans', sans-serif;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
        text-decoration: none;
        white-space: nowrap;
        line-height: 1;
    }
    .sp-export-btn:hover {
        background: #0b6a37;
        border-color: #08522a;
        color: #fff;
        box-shadow: 0 2px 6px rgba(16, 124, 65, 0.25);
    }
    .sp-export-btn i {
        font-size: 14px;
    }

    .sp-empty { font-size: 19px; color: #bbb; text-align: center; padding: 2.25rem 0; }

    .sp-alert { padding: 12px 16px; border-radius: 10px; font-size: 18px; margin-bottom: 6px; }
    .sp-alert.success { background: #e1f5ee; color: #0f6e56; }
    .sp-alert.error   { background: #fcebeb; color: #a32d2d; }

    @media (max-width: 860px) {
        .sp-top-grid    { grid-template-columns: 180px 1fr; }
        .sp-bottom-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 640px) {
        .sp-top-grid { grid-template-columns: 1fr; }
        .sp-avg-card { min-height: 220px; }
    }

     /* ITEM ANALYSIS DIALOG (redesigned) */
    dialog.ia-dialog {
        border: none;
        border-radius: 16px;
        padding: 0;
        width: 760px;
        max-width: 95vw;
        max-height: 90vh;
        margin: auto;
        overflow: hidden;
        box-shadow: 0 24px 80px rgba(0,0,0,0.18);
        font-family: 'DM Sans', sans-serif;
    }

    dialog.ia-dialog::backdrop {
        background: rgba(0,0,0,0.45);
        backdrop-filter: blur(3px);
    }

    /* Dialog header */
    .ia-dialog-head {
        padding: 24px 28px 18px;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        background: #fff;
    }

    .ia-dialog-name {
        font-family: 'DM Sans', sans-serif;
        font-size: 24px;
        font-weight: 500;
        color: #111;
        margin: 0 0 6px;
        letter-spacing: -0.02em;
    }

    .ia-dialog-meta {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .ia-dialog-close {
        width: 38px; height: 38px; border: 1px solid #e4e4e4; background: #fff;
        border-radius: 10px; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        font-size: 18px; color: #888; flex-shrink: 0;
        transition: border-color 0.15s, color 0.15s;
    }

    .ia-dialog-close:hover { border-color: #111; color: #111; }

    /* Score summary strip */
    .ia-score-strip {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        border-bottom: 1px solid #f0f0f0;
        background: #fafafa;
    }

    .ia-score-cell {
        padding: 14px 18px;
        border-right: 1px solid #f0f0f0;
        text-align: center;
    }

    .ia-score-cell:last-child { border-right: none; }

    .ia-score-cell-label {
        font-size: 14px; font-weight: 500; letter-spacing: 0.08em;
        text-transform: uppercase; color: #bbb; margin: 0 0 4px;
    }

    .ia-score-cell-val {
        font-size: 24px; font-weight: 500; color: #111; line-height: 1;
    }

    .ia-score-cell-val.green { color: #1d9e75; }
    .ia-score-cell-val.red   { color: #e24b4a; }
    .ia-score-cell-val.amber { color: #854f0b; }

    /* Answer list */
    .ia-answer-list {
        padding: 18px 24px;
        overflow-y: auto;
        max-height: calc(90vh - 220px);
        display: flex;
        flex-direction: column;
        gap: 10px;
        background: #fff;
    }

    .ia-answer-list::-webkit-scrollbar { width: 4px; }
    .ia-answer-list::-webkit-scrollbar-thumb { background: #e8e8e8; border-radius: 99px; }

    .ia-answer-item {
        display: flex;
        gap: 14px;
        padding: 14px 16px;
        border-radius: 10px;
        border: 1px solid #f0f0f0;
        background: #fafafa;
        align-items: flex-start;
        transition: background 0.1s;
    }

    .ia-answer-item.correct { background: #f0fdf7; border-color: #c6f0e0; }
    .ia-answer-item.wrong   { background: #fff8f8; border-color: #fad4d4; }

    /* Icon */
    .ia-answer-icon {
        width: 32px; height: 32px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 17px; flex-shrink: 0; margin-top: 1px;
    }

    .ia-answer-item.correct .ia-answer-icon { background: #1d9e75; color: #fff; }
    .ia-answer-item.wrong   .ia-answer-icon { background: #e24b4a; color: #fff; }

    /* Content */
    .ia-answer-content { flex: 1; min-width: 0; }

    .ia-answer-q-num {
        font-size: 14px; font-weight: 500; letter-spacing: 0.08em;
        text-transform: uppercase; color: #bbb; margin: 0 0 3px;
    }

    .ia-answer-q-text {
        font-size: 17px; color: #111; font-weight: 500;
        line-height: 1.5; margin: 0 0 10px; word-break: break-word;
    }

    .ia-answer-options {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .ia-option-chip {
        display: inline-flex; align-items: center; gap: 5px;
        font-size: 16px; font-weight: 500;
        padding: 4px 11px; border-radius: 99px;
        border: 1px solid transparent;
    }

    .ia-option-chip.selected-correct {
        background: #e1f5ee; color: #0f6e56; border-color: #9fe1cb;
    }

    .ia-option-chip.selected-wrong {
        background: #fcebeb; color: #a32d2d; border-color: #f7c1c1;
    }

    .ia-option-chip.correct-answer {
        background: #e1f5ee; color: #0f6e56; border-color: #9fe1cb;
    }

    .ia-option-chip.neutral {
        background: #f3f3f3; color: #888; border-color: #e4e4e4;
    }

    .ia-chip-label { font-size: 15px; opacity: 0.75; }

    /* Empty / loading states */
    .ia-loading {
        display: flex; flex-direction: column; align-items: center;
        justify-content: center; padding: 3rem; gap: 10px;
        color: #bbb; font-size: 17px;
    }

    .ia-loading i { font-size: 24px; opacity: 0.3; }

    .ia-no-data {
        display: flex; flex-direction: column; align-items: center;
        justify-content: center; padding: 3rem; gap: 8px;
        text-align: center;
    }

    .ia-no-data i { font-size: 28px; color: #e8e8e8; }
    .ia-no-data p { font-size: 17px; color: #999; margin: 0; }
    .ia-no-data small { font-size: 15px; color: #bbb; }

    /* Performance tabs */
    .sp-tab-nav {
        display: flex;
        gap: 0;
        border-bottom: 2px solid #f0f0f0;
    }

    .sp-tab-btn {
        height: 38px;
        padding: 0 20px;
        background: transparent;
        border: none;
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
        font-family: 'DM Sans', sans-serif;
        font-size: 17px;
        font-weight: 500;
        color: #aaa;
        cursor: pointer;
        transition: color 0.15s, border-bottom-color 0.15s;
    }

    .sp-tab-btn:hover  { color: #555; }
    .sp-tab-btn.active { color: #111; border-bottom-color: #111; }

    .sp-tab-panel        { display: none; flex-direction: column; gap: 14px; }
    .sp-tab-panel.active { display: flex; }

    .sp-bottom-grid--full { grid-template-columns: 1fr; }

    .sp-table-header-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 14px;
        flex-wrap: wrap;
    }
    .sp-search-box {
        position: relative;
        display: inline-flex;
        align-items: center;
    }
    .sp-search-box i {
        position: absolute;
        left: 10px;
        color: #9ca3af;
        font-size: 13px;
        pointer-events: none;
    }
    .sp-search-input {
        padding: 6px 12px 6px 30px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        font-size: 13px;
        background: #f9fafb;
        color: #1f2937;
        outline: none;
        transition: border-color 0.15s, background-color 0.15s;
    }
    .sp-search-input:focus {
        border-color: #3b82f6;
        background: #fff;
    }
    .sp-pagination-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 14px;
        margin-top: 10px;
        border-top: 1px solid #f1f5f9;
        font-size: 13px;
        color: #64748b;
        flex-wrap: wrap;
        gap: 8px;
    }
    .sp-pagination-nav {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .sp-page-btn {
        padding: 4px 10px;
        border: 1px solid #e2e8f0;
        background: #fff;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 500;
        color: #475569;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .sp-page-btn:hover:not(:disabled) {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #1e293b;
    }
    .sp-page-btn.active {
        background: #2563eb;
        border-color: #2563eb;
        color: #fff;
    }
    .sp-page-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .sp-filter-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 10px 16px;
        gap: 12px;
        flex-wrap: wrap;
        box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    }
    .sp-filter-group {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .sp-filter-label {
        font-size: 13px;
        font-weight: 600;
        color: #4b5563;
        margin: 0;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .sp-filter-label i {
        color: #2563eb;
    }
    .sp-filter-select {
        padding: 6px 32px 6px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 500;
        color: #1f2937;
        background: #f9fafb url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e") no-repeat right 8px center/14px 14px;
        outline: none;
        cursor: pointer;
        transition: border-color 0.15s, background-color 0.15s;
        appearance: none;
        -webkit-appearance: none;
        min-width: 260px;
        max-width: 420px;
    }
    .sp-filter-select:focus {
        border-color: #2563eb;
        background-color: #fff;
        box-shadow: 0 0 0 2px rgba(37,99,235,0.1);
    }
</style>

@php
    $teacherClasses = $teacherClasses ?? collect();
    $preAssessmentModules = $preAssessmentModules ?? collect();
    $formalAssessmentModules = $formalAssessmentModules ?? collect();
    $selectedQuizModuleId = request('quiz_module_id');
    $selectedAssessModuleId = request('assess_module_id');

    $payload = $payload ?? [
        'classAverage' => $classAverage ?? 0,
        'passCount' => $passCount ?? 0,
        'failCount' => $failCount ?? 0,
        'questionStats' => $questionStats ?? collect(),
        'topStudents' => $topStudents ?? collect(),
        'remainingCount' => $remainingCount ?? 0,
        'aiSummary' => $aiSummary ?? 'No AI summary yet. Click refresh to generate one.',
    ];

    $classAverage   = (float) ($payload['classAverage'] ?? 0);
    $passCount      = (int) ($payload['passCount'] ?? 0);
    $failCount      = (int) ($payload['failCount'] ?? 0);
    $questionStats  = collect($payload['questionStats'] ?? collect());
    $topStudents    = collect($payload['topStudents'] ?? collect());
    $remainingCount = (int) ($payload['remainingCount'] ?? 0);
    $classSummaryEnabled = (bool) ($payload['classSummaryEnabled'] ?? true);
    $aiSummary      = $classSummaryEnabled
        ? ($payload['aiSummary'] ?? 'No AI summary yet. Click refresh to generate one.')
        : 'AI class insights are disabled for this class.';
    $avgClass       = $classAverage >= 75 ? 'high' : ($classAverage >= 50 ? 'mid' : 'low');

    $assessPayload   = $assessmentPayload ?? [];
    $aClassAverage   = (float) ($assessPayload['classAverage'] ?? 0);
    $aPassCount      = (int) ($assessPayload['passCount'] ?? 0);
    $aFailCount      = (int) ($assessPayload['failCount'] ?? 0);
    $aQuestionStats  = collect($assessPayload['questionStats'] ?? []);
    $aTopStudents    = collect($assessPayload['topStudents'] ?? []);
    $aRemainingCount = (int) ($assessPayload['remainingCount'] ?? 0);
    $assessmentAnalysisEnabled = (bool) ($assessPayload['assessmentAnalysisEnabled'] ?? true);
    $aAiSummary      = $assessmentAnalysisEnabled
        ? ($assessPayload['aiSummary'] ?? 'No assessment insights available yet.')
        : 'Assessment AI analysis is disabled for this class.';
    $aAvgClass       = $aClassAverage >= 75 ? 'high' : ($aClassAverage >= 50 ? 'mid' : 'low');

    // Pre-build the JS-facing question breakdown arrays here (outside @json())
    // so Blade's directive parser never has to deal with a multi-line
    // fn(...) => [...] arrow-array literal inline inside @json(...).
    $currentQuestionStatsArr = $questionStats->map(function ($q) {
        return [
            'question_text' => html_entity_decode(strip_tags($q->question_text)),
            'pct_correct' => (float) $q->pct_correct,
            'correct_count' => $q->correct_count,
            'total_answers' => $q->total_answers,
        ];
    })->values();

    $currentAssessQuestionStatsArr = $aQuestionStats->map(function ($q) {
        return [
            'question_text' => html_entity_decode(strip_tags($q->question_text)),
            'pct_correct' => (float) $q->pct_correct,
            'correct_count' => $q->correct_count,
            'total_answers' => $q->total_answers,
        ];
    })->values();
@endphp

<div class="sp-wrap">

    {{-- Class switcher --}}
    @if($teacherClasses->count() > 1)
    <div class="sp-switcher">
        <span class="sp-switcher-label">Class:</span>
        <div class="sp-class-tabs">
            @foreach($teacherClasses as $tc)
                <a href="{{ route('student.performance', $tc) }}"
                   data-class-id="{{ $tc->id }}"
                   class="sp-class-tab js-class-tab {{ $tc->id === $class->id ? 'active' : '' }}">
                    {{ $tc->name }}
                </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Performance type tabs --}}
    <div class="sp-tab-nav">
        <button class="sp-tab-btn active" data-tab="quiz">Pre-Assessment</button>
        <button class="sp-tab-btn" data-tab="assessment">Assessments</button>
    </div>

    {{-- Pre-Assessment panel --}}
    <div class="sp-tab-panel active" id="tab-quiz">
        <p class="sp-sub" id="spSubtitle">{{ $class->name ?? 'Class' }} - pre-assessment results and question breakdown.</p>

        {{-- Pre-Assessment module filter bar --}}
        <div class="sp-filter-bar">
            <div class="sp-filter-group">
                <label for="quizModuleSelect" class="sp-filter-label"><i class="fas fa-layer-group"></i> Module / Pre-Assessment:</label>
                <select id="quizModuleSelect" class="sp-filter-select" onchange="onQuizModuleSelect(this.value)">
                    <option value="all">All Modules / Topics (Class Overview)</option>
                    @foreach($preAssessmentModules as $pm)
                        <option value="{{ $pm->id }}" {{ (isset($selectedQuizModuleId) && (string)$selectedQuizModuleId === (string)$pm->id) ? 'selected' : '' }}>
                            {{ $pm->title }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

    {{-- Top row --}}
    <div class="sp-top-grid">

        <div class="sp-avg-card {{ $avgClass }}">
            <p class="sp-card-label">Class avg</p>
            <p class="sp-avg-val" id="classAverageValue">{{ number_format($classAverage, 1) }}</p>
            <p class="sp-avg-unit">percent</p>
        </div>

        <div class="sp-card sp-chart-card">
            <p class="sp-section-title">Pass / Fail</p>
            @if(($passCount + $failCount) > 0)
                <div class="sp-chart-wrap" id="chartWrap">
                    <canvas id="passFailChart"></canvas>
                </div>
            @else
                <p class="sp-empty">No attempts yet.</p>
            @endif
        </div>

        <div class="sp-card sp-chart-card">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 12px;">
                <p class="sp-section-title" style="margin: 0;">Question Breakdown</p>
                <div class="sp-chart-legend" style="display: flex; align-items: center; gap: 12px; font-size: 12px; font-weight: 500;">
                    <span style="display: inline-flex; align-items: center; gap: 5px; color: #166534;"><span style="width: 9px; height: 9px; border-radius: 2px; background: #1d9e75; display: inline-block;"></span> &ge;70% High</span>
                    <span style="display: inline-flex; align-items: center; gap: 5px; color: #9a3412;"><span style="width: 9px; height: 9px; border-radius: 2px; background: #ef9f27; display: inline-block;"></span> 40–69% Moderate</span>
                    <span style="display: inline-flex; align-items: center; gap: 5px; color: #991b1b;"><span style="width: 9px; height: 9px; border-radius: 2px; background: #e24b4a; display: inline-block;"></span> &lt;40% Low</span>
                </div>
            </div>
            @if($questionStats->isNotEmpty())
                <div class="sp-chart-wrap" id="questionChartWrap">
                    <canvas id="questionBreakdownChart"></canvas>
                </div>
            @else
                <p class="sp-empty" id="questionBreakdownEmpty">No answer data yet.</p>
            @endif
        </div>

    </div>

    {{-- Bottom row --}}
    <div class="sp-bottom-grid">
        <div class="sp-card" id="quizStudentsCard">
            <div class="sp-table-header-bar">
                <p class="sp-section-title" style="margin-bottom:0;">Student Rankings</p>
                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <div class="sp-search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="quizStudentSearch" class="sp-search-input" placeholder="Search student..." oninput="onQuizStudentSearch(this.value)">
                    </div>
                    <button type="button" class="sp-export-btn" onclick="exportPerformanceToExcel('quiz')" title="Download Excel (.xlsx)">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </button>
                </div>
            </div>
            <div id="quizStudentsTableWrap">
                <table class="sp-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student</th>
                            <th style="text-align:right">Avg Score</th>
                            <th style="text-align:right"></th>
                        </tr>
                    </thead>
                    <tbody id="topStudentsBody"></tbody>
                </table>
                <div class="sp-pagination-bar" id="quizStudentsPagination"></div>
            </div>
        </div>

        <div class="sp-card sp-ai-card">
            <p class="sp-section-title">AI Class Insights</p>
            <div id="aiRawText" style="display:none;">{{ $aiSummary ?? 'No insights available yet.' }}</div>
            <div class="sp-ai-body" id="aiSummaryBody"></div>
            <div class="sp-ai-footer">
                <form action="{{ route('student.performance.refresh', ['class' => $class->id]) }}"
                      method="POST" id="refreshInsightsForm">
                    @csrf
                    <button type="submit" class="sp-refresh-btn" id="refreshInsightsBtn" {{ $classSummaryEnabled ? '' : 'disabled' }}>
                        {{ $classSummaryEnabled ? 'Refresh insights' : 'Insights Disabled' }}
                    </button>
                </form>
            </div>
        </div>

    </div>

</div>{{-- /tab-quiz --}}

{{-- Assessments panel --}}
<div class="sp-tab-panel" id="tab-assessment">
    <p class="sp-sub" id="spAssessSubtitle">{{ $class->name ?? 'Class' }} - formal assessment results.</p>

    {{-- Formal Assessment filter bar --}}
    <div class="sp-filter-bar">
        <div class="sp-filter-group">
            <label for="assessModuleSelect" class="sp-filter-label"><i class="fas fa-clipboard-check"></i> Formal Assessment:</label>
            <select id="assessModuleSelect" class="sp-filter-select" onchange="onAssessModuleSelect(this.value)">
                <option value="all">All Formal Assessments (Class Overview)</option>
                @foreach($formalAssessmentModules as $fm)
                    <option value="{{ $fm->id }}" {{ (isset($selectedAssessModuleId) && (string)$selectedAssessModuleId === (string)$fm->id) ? 'selected' : '' }}>
                        {{ $fm->title }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Assessment top row --}}
    <div class="sp-top-grid">
        <div class="sp-avg-card {{ $aAvgClass }}" id="assessAvgCard">
            <p class="sp-card-label">Class avg</p>
            <p class="sp-avg-val" id="assessClassAverageValue">{{ number_format($aClassAverage, 1) }}</p>
            <p class="sp-avg-unit">percent</p>
        </div>

        <div class="sp-card sp-chart-card" id="assessChartCard">
            <p class="sp-section-title">Pass / Fail</p>
            @if(($aPassCount + $aFailCount) > 0)
                <div class="sp-chart-wrap" id="assessChartWrap">
                    <canvas id="assessPassFailChart"></canvas>
                </div>
            @else
                <p class="sp-empty">No attempts yet.</p>
            @endif
        </div>

        <div class="sp-card sp-chart-card" id="assessQuestionChartCard">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 12px;">
                <p class="sp-section-title" style="margin: 0;">Question Breakdown</p>
                <div class="sp-chart-legend" style="display: flex; align-items: center; gap: 12px; font-size: 12px; font-weight: 500;">
                    <span style="display: inline-flex; align-items: center; gap: 5px; color: #166534;"><span style="width: 9px; height: 9px; border-radius: 2px; background: #1d9e75; display: inline-block;"></span> &ge;70% High</span>
                    <span style="display: inline-flex; align-items: center; gap: 5px; color: #9a3412;"><span style="width: 9px; height: 9px; border-radius: 2px; background: #ef9f27; display: inline-block;"></span> 40–69% Moderate</span>
                    <span style="display: inline-flex; align-items: center; gap: 5px; color: #991b1b;"><span style="width: 9px; height: 9px; border-radius: 2px; background: #e24b4a; display: inline-block;"></span> &lt;40% Low</span>
                </div>
            </div>
            @if($aQuestionStats->isNotEmpty())
                <div class="sp-chart-wrap" id="assessQuestionChartWrap">
                    <canvas id="assessQuestionBreakdownChart"></canvas>
                </div>
            @else
                <p class="sp-empty" id="assessQuestionBreakdownEmpty">No answer data yet.</p>
            @endif
        </div>
    </div>

    {{-- Assessment bottom row --}}
    <div class="sp-bottom-grid">
        <div class="sp-card" id="assessStudentsCard">
            <div class="sp-table-header-bar">
                <p class="sp-section-title" style="margin-bottom:0;">Student Rankings</p>
                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <div class="sp-search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="assessStudentSearch" class="sp-search-input" placeholder="Search student..." oninput="onAssessStudentSearch(this.value)">
                    </div>
                    <button type="button" class="sp-export-btn" onclick="exportPerformanceToExcel('assessment')" title="Download Excel (.xlsx)">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </button>
                </div>
            </div>
            <div id="assessStudentsTableWrap">
                <table class="sp-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student</th>
                            <th style="text-align:right">Avg Score</th>
                            <th style="text-align:right"></th>
                        </tr>
                    </thead>
                    <tbody id="assessTopStudentsBody"></tbody>
                </table>
                <div class="sp-pagination-bar" id="assessStudentsPagination"></div>
            </div>
        </div>

        <div class="sp-card sp-ai-card">
            <p class="sp-section-title">AI Class Insights</p>
            <div id="assessAiRawText" style="display:none;">{{ $aAiSummary }}</div>
            <div class="sp-ai-body" id="assessAiSummaryBody"></div>
            <div class="sp-ai-footer">
                <form action="{{ route('student.performance.assessment.refresh', ['class' => $class->id]) }}"
                      method="POST" id="refreshAssessmentInsightsForm">
                    @csrf
                    <button type="submit" class="sp-refresh-btn" id="refreshAssessmentInsightsBtn" {{ $assessmentAnalysisEnabled ? '' : 'disabled' }}>
                        {{ $assessmentAnalysisEnabled ? 'Refresh insights' : 'Insights Disabled' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>{{-- /tab-assessment --}}

</div>

{{-- Item Analysis Dialog --}}
<dialog class="ia-dialog" id="itemAnalysisDialog">

    {{-- Head --}}
    <div class="ia-dialog-head">
        <div>
            <p class="ia-dialog-name" id="iaStudentName">Student Name</p>
            <div class="ia-dialog-meta" id="iaDialogMeta">
                <span class="sp-score-pill" id="iaScorePill">-</span>
                <span style="font-size: 17px;color:#999;" id="iaAttemptLabel">-</span>
            </div>
        </div>
        <button class="ia-dialog-close" onclick="document.getElementById('itemAnalysisDialog').close()">
            &#x2715;
        </button>
    </div>

    {{-- Attempt Limit / Grant Extra Attempt (formal assessments only) --}}
    <div id="iaAttemptLimitBox" style="display:none; padding: 14px 24px; border-bottom: 1px solid #f0f0f0; background: #fafafa;">
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
            <div style="font-size:16px; color:#555;">
                Attempts used: <strong id="iaAttemptsUsed">-</strong> / <span id="iaAttemptsAllowed">-</span>
                <span style="color:#bbb; font-size:14px;" id="iaAttemptsBreakdown"></span>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="button" class="sp-refresh-btn" onclick="toggleMaxAttemptsForm()">
                    <i class="fas fa-sliders-h"></i> Edit Base Limit
                </button>
                <button type="button" class="sp-refresh-btn" id="iaGrantToggleBtn" onclick="toggleGrantForm()">
                    <i class="fas fa-plus"></i> Grant Extra Attempt
                </button>
            </div>
        </div>
        <div id="iaMaxAttemptsForm" style="display:none; margin-top:12px; gap:8px; align-items:center; flex-wrap:wrap;">
            <label style="font-size:15px; color:#666;">New base limit:</label>
            <input type="number" id="iaMaxAttemptsInput" min="1" max="20" value="1" style="width:70px; padding:6px 8px; border:1px solid #e4e4e4; border-radius:8px;">
            <button type="button" class="sp-refresh-btn" style="background:#0f0f0f; color:#fff; border-color:#0f0f0f;" onclick="submitMaxAttempts()">
                Confirm
            </button>
        </div>
        <div id="iaGrantForm" style="display:none; margin-top:12px; gap:8px; align-items:center; flex-wrap:wrap;">
            <label style="font-size:15px; color:#666;">Extra attempts:</label>
            <input type="number" id="iaGrantAmount" min="1" max="10" value="1" style="width:70px; padding:6px 8px; border:1px solid #e4e4e4; border-radius:8px;">
            <input type="text" id="iaGrantReason" placeholder="Reason (optional)" style="flex:1; min-width:160px; padding:6px 10px; border:1px solid #e4e4e4; border-radius:8px;">
            <button type="button" class="sp-refresh-btn" style="background:#0f0f0f; color:#fff; border-color:#0f0f0f;" onclick="submitGrant()">
                Confirm
            </button>
        </div>
    </div>

    {{-- Score summary strip --}}
    <div class="ia-score-strip" id="iaScoreStrip" style="display:none;">
        <div class="ia-score-cell">
            <p class="ia-score-cell-label">Score</p>
            <p class="ia-score-cell-val" id="iaStripScore">-</p>
        </div>
        <div class="ia-score-cell">
            <p class="ia-score-cell-label">Percentage</p>
            <p class="ia-score-cell-val" id="iaStripPct">-</p>
        </div>
        <div class="ia-score-cell">
            <p class="ia-score-cell-label">Correct</p>
            <p class="ia-score-cell-val green" id="iaStripCorrect">-</p>
        </div>
        <div class="ia-score-cell">
            <p class="ia-score-cell-label">Wrong</p>
            <p class="ia-score-cell-val red" id="iaStripWrong">-</p>
        </div>
    </div>

    {{-- Answer list --}}
    <div class="ia-answer-list" id="iaAnswerList">
        <div class="ia-loading">
            <i class="fas fa-circle-notch fa-spin"></i>
            Loading...
        </div>
    </div>

</dialog>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
var passFailChart  = null;
var currentClassId = {{ $class->id }};
var currentQuizModuleId = @json($selectedQuizModuleId ?? 'all');
var currentAssessModuleId = @json($selectedAssessModuleId ?? 'all');
var currentPassCount = {{ $passCount }};
var currentFailCount = {{ $failCount }};
var assessPassFailChart = null;
var currentAssessPassCount = {{ $aPassCount }};
var currentAssessFailCount = {{ $aFailCount }};

/* Question breakdown chart state */
var questionChart = null;
var assessQuestionChart = null;
var currentQuestionStats = @json($currentQuestionStatsArr);
var currentAssessQuestionStats = @json($currentAssessQuestionStatsArr);

const itemAnalysisRouteTemplate = @json(route('student.performance.student-item-analysis', ['class' => '__CLASS__', 'student' => '__STUDENT__']));
const assessmentAnalysisRouteTemplate = @json(route('student.assessment.analysis', ['class' => '__CLASS__', 'student' => '__STUDENT__']));
const refreshInsightsRouteTemplate = @json(route('student.performance.refresh', ['class' => '__CLASS__']));
const refreshAssessmentInsightsRouteTemplate = @json(route('student.performance.assessment.refresh', ['class' => '__CLASS__']));

function populateModuleSelect(selectId, modules, defaultLabel) {
    var sel = document.getElementById(selectId);
    if (!sel) return;
    var list = Array.isArray(modules) ? modules : [];
    var html = `<option value="all">${defaultLabel}</option>`;
    list.forEach(function(m) {
        html += `<option value="${m.id}">${escapeHtml(m.title)}</option>`;
    });
    sel.innerHTML = html;
    sel.value = 'all';
}

async function onQuizModuleSelect(val) {
    currentQuizModuleId = val || 'all';
    await fetchAndUpdatePerformance('quiz');
}

async function onAssessModuleSelect(val) {
    currentAssessModuleId = val || 'all';
    await fetchAndUpdatePerformance('assessment');
}

async function fetchAndUpdatePerformance(targetTab) {
    var url = new URL(window.location.origin + '/student-performance/' + currentClassId);
    if (currentQuizModuleId && currentQuizModuleId !== 'all') {
        url.searchParams.set('quiz_module_id', currentQuizModuleId);
    }
    if (currentAssessModuleId && currentAssessModuleId !== 'all') {
        url.searchParams.set('assess_module_id', currentAssessModuleId);
    }

    try {
        var res = await fetch(url.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!res.ok) throw new Error('Failed to fetch performance data');
        var payload = await res.json();

        if (targetTab === 'quiz' || !targetTab) {
            currentPassCount = Number(payload.passCount || 0);
            currentFailCount = Number(payload.failCount || 0);
            var avgV = Number(payload.classAverage || 0);
            var avgEl = document.getElementById('classAverageValue');
            if (avgEl) {
                avgEl.textContent = avgV.toFixed(1);
                var avgCard = avgEl.closest('.sp-avg-card');
                if (avgCard) { avgCard.classList.remove('high','mid','low'); avgCard.classList.add(scoreClass(avgV)); }
            }
            renderPassFailCard();
            renderQuestionStats(payload.questionStats || []);
            renderTopStudents(payload.topStudents || []);
        }

        if (targetTab === 'assessment' || !targetTab) {
            if (payload.assessment) {
                currentAssessPassCount = Number(payload.assessment.passCount || 0);
                currentAssessFailCount = Number(payload.assessment.failCount || 0);
                var aAvgV = Number(payload.assessment.classAverage || 0);
                var aAvgEl = document.getElementById('assessClassAverageValue');
                if (aAvgEl) {
                    aAvgEl.textContent = aAvgV.toFixed(1);
                    var aAvgCard = document.getElementById('assessAvgCard');
                    if (aAvgCard) { aAvgCard.className = 'sp-avg-card ' + scoreClass(aAvgV); }
                }
                renderAssessmentPassFailCard();
                renderAssessmentQuestionStats(payload.assessment.questionStats || []);
                renderAssessmentTopStudents(payload.assessment.topStudents || []);
            }
        }
    } catch (err) {
        console.error('Error updating analytics:', err);
    }
}

/* Pass/Fail Chart */
function buildChart() {
    var wrap   = document.getElementById('chartWrap');
    var canvas = document.getElementById('passFailChart');
    if (!wrap || !canvas) return;

    var dpr = window.devicePixelRatio || 1;
    var w   = wrap.offsetWidth;
    var h   = wrap.offsetHeight || 160;

    canvas.width  = w * dpr;
    canvas.height = h * dpr;
    canvas.style.width  = w + 'px';
    canvas.style.height = h + 'px';

    if (passFailChart) { passFailChart.destroy(); passFailChart = null; }

    passFailChart = new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: ['Passed', 'Failed'],
            datasets: [{
                data: [currentPassCount, currentFailCount],
                backgroundColor: ['#1d9e75', '#e24b4a'],
                borderWidth: 0,
            }]
        },
        options: {
            responsive: false,
            devicePixelRatio: dpr,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { font: { family: 'DM Sans', size: 18, weight: '600' }, color: '#666', boxWidth: 14, padding: 22 }
                }
            }
        }
    });
}

function renderPassFailCard() {
    var chartCard = document.querySelector('#tab-quiz .sp-chart-card');
    if (!chartCard) {
        return;
    }

    if ((currentPassCount + currentFailCount) > 0) {
        if (!document.getElementById('chartWrap')) {
            chartCard.innerHTML = '<p class="sp-section-title">Pass / Fail</p><div class="sp-chart-wrap" id="chartWrap"><canvas id="passFailChart"></canvas></div>';
        }
        buildChart();
        return;
    }

    if (passFailChart) {
        passFailChart.destroy();
        passFailChart = null;
    }

    chartCard.innerHTML = '<p class="sp-section-title">Pass / Fail</p><p class="sp-empty">No attempts yet.</p>';
}

renderPassFailCard();

if (currentQuestionStats.length) {
    buildQuestionChart(currentQuestionStats);
}

var resizeTimer;
window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
        if ((currentPassCount + currentFailCount) > 0) {
            buildChart();
        }
        if (currentQuestionStats.length) {
            buildQuestionChart(currentQuestionStats);
        }
        var assessPanel = document.getElementById('tab-assessment');
        if (assessPanel && assessPanel.classList.contains('active')) {
            if ((currentAssessPassCount + currentAssessFailCount) > 0) {
                buildAssessmentChart();
            }
            if (currentAssessQuestionStats.length) {
                buildAssessmentQuestionChart(currentAssessQuestionStats);
            }
        }
    }, 150);
});

/* AI markdown */
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
    var rawEl  = document.getElementById('aiRawText');
    var bodyEl = document.getElementById('aiSummaryBody');
    if (rawEl && bodyEl) bodyEl.innerHTML = parseAI(rawEl.textContent || '');

    var assessRawEl  = document.getElementById('assessAiRawText');
    var assessBodyEl = document.getElementById('assessAiSummaryBody');
    if (assessRawEl && assessBodyEl) assessBodyEl.innerHTML = parseAI(assessRawEl.textContent || '');
})();

function scoreClass(v) { return v >= 75 ? 'high' : (v >= 50 ? 'mid' : 'low'); }
function diffColor(pct) { return pct >= 70 ? '#1d9e75' : (pct >= 40 ? '#ef9f27' : '#e24b4a'); }
function diffCls(pct)   { return pct >= 70 ? 'q-high' : (pct >= 40 ? 'q-mid' : 'q-low'); }

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function decodeAndStripHtml(value) {
    var txt = document.createElement('textarea');
    txt.innerHTML = String(value ?? '');
    return txt.value.replace(/<[^>]+>/g, '');
}

/* Question Breakdown - Pre-Assessment (Chart) */
function buildQuestionChart(stats) {
    var wrap = document.getElementById('questionChartWrap');
    var canvas = document.getElementById('questionBreakdownChart');
    if (!wrap || !canvas) return;

    var labels = stats.map(function (q, i) { return 'Q' + (i + 1); });
    var fullLabels = stats.map(function (q) { return q.question_text; });
    var values = stats.map(function (q) { return q.pct_correct; });
    var colors = values.map(function (v) { return diffColor(v); });
    var counts = stats.map(function (q) { return q.correct_count + '/' + q.total_answers; });

    if (questionChart) { questionChart.destroy(); questionChart = null; }

    questionChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: '% Correct',
                data: values,
                backgroundColor: colors,
                borderRadius: 4,
                maxBarThickness: 36,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: function (items) { return fullLabels[items[0].dataIndex] || ''; },
                        label: function (ctx) {
                            return ctx.parsed.y + '% correct (' + counts[ctx.dataIndex] + ')';
                        }
                    }
                }
            },
            scales: {
                x: { ticks: { maxRotation: 0 } },
                y: { beginAtZero: true, max: 100, ticks: { callback: function (v) { return v + '%'; } } }
            }
        }
    });
}

function renderQuestionStats(items) {
    var stats = Array.isArray(items) ? items : [];
    currentQuestionStats = stats;

    var wrap = document.getElementById('questionChartWrap');
    var card = document.querySelector('.sp-top-grid .sp-card.sp-chart-card:last-child');

    var qbHeaderHtml = '<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 12px;"><p class="sp-section-title" style="margin: 0;">Question Breakdown</p><div class="sp-chart-legend" style="display: flex; align-items: center; gap: 12px; font-size: 12px; font-weight: 500;"><span style="display: inline-flex; align-items: center; gap: 5px; color: #166534;"><span style="width: 9px; height: 9px; border-radius: 2px; background: #1d9e75; display: inline-block;"></span> &ge;70% High</span><span style="display: inline-flex; align-items: center; gap: 5px; color: #9a3412;"><span style="width: 9px; height: 9px; border-radius: 2px; background: #ef9f27; display: inline-block;"></span> 40–69% Moderate</span><span style="display: inline-flex; align-items: center; gap: 5px; color: #991b1b;"><span style="width: 9px; height: 9px; border-radius: 2px; background: #e24b4a; display: inline-block;"></span> &lt;40% Low</span></div></div>';

    if (!stats.length) {
        if (questionChart) { questionChart.destroy(); questionChart = null; }
        if (card) card.innerHTML = qbHeaderHtml + '<p class="sp-empty" id="questionBreakdownEmpty">No answer data yet.</p>';
        return;
    }

    if (!wrap) {
        if (card) card.innerHTML = qbHeaderHtml + '<div class="sp-chart-wrap" id="questionChartWrap"><canvas id="questionBreakdownChart"></canvas></div>';
    }

    buildQuestionChart(stats);
}

var quizStudentsState = {
    items: @json($topStudents->values()),
    search: '',
    page: 1,
    perPage: 10
};

function onQuizStudentSearch(val) {
    quizStudentsState.search = val || '';
    quizStudentsState.page = 1;
    renderTopStudents();
}

function setQuizPage(p) {
    quizStudentsState.page = p;
    renderTopStudents();
}

function renderTopStudents(newItems) {
    if (newItems !== undefined) {
        quizStudentsState.items = Array.isArray(newItems) ? newItems : [];
        quizStudentsState.page = 1;
        var searchInput = document.getElementById('quizStudentSearch');
        if (searchInput) searchInput.value = '';
        quizStudentsState.search = '';
    }

    var body = document.getElementById('topStudentsBody');
    var pagination = document.getElementById('quizStudentsPagination');
    if (!body) return;

    var rankedItems = quizStudentsState.items.map(function(item, idx) {
        return Object.assign({}, item, { _originalRank: idx + 1 });
    });

    var filtered = rankedItems;
    if (quizStudentsState.search.trim()) {
        var q = quizStudentsState.search.trim().toLowerCase();
        filtered = rankedItems.filter(function(st) {
            return (st.name && st.name.toLowerCase().includes(q)) ||
                   (st.program && st.program.toLowerCase().includes(q));
        });
    }

    if (!filtered.length) {
        body.innerHTML = `<tr><td colspan="4" style="text-align:center;padding:24px;color:#94a3b8;">${quizStudentsState.search ? 'No students matching "' + escapeHtml(quizStudentsState.search) + '"' : 'No student data yet.'}</td></tr>`;
        if (pagination) pagination.innerHTML = '';
        return;
    }

    var total = filtered.length;
    var perPage = quizStudentsState.perPage;
    var totalPages = Math.max(1, Math.ceil(total / perPage));
    if (quizStudentsState.page > totalPages) quizStudentsState.page = totalPages;
    if (quizStudentsState.page < 1) quizStudentsState.page = 1;

    var startIdx = (quizStudentsState.page - 1) * perPage;
    var endIdx = Math.min(startIdx + perPage, total);
    var pageItems = filtered.slice(startIdx, endIdx);

    body.innerHTML = pageItems.map(function (student) {
        var avg = Number(student.average_score || 0);
        var pillClass = scoreClass(avg);
        var rank = student._originalRank;
        var medalClass = rank === 1 ? 'gold' : (rank === 2 ? 'silver' : (rank === 3 ? 'bronze' : ''));
        var studentId = Number(student.id || 0);
        var safeName = String(student.name || 'Student').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
        var rankBadge = medalClass
            ? `<span class="sp-rank-medal ${medalClass}">${rank}</span>`
            : `<span class="sp-rank-num">${rank}</span>`;
        var program = student.program
            ? `<span style="font-size: 16px;color:#bbb;margin-left:4px;">${escapeHtml(student.program)}</span>`
            : '';
        return `
            <tr>
                <td>${rankBadge}</td>
                <td>
                    ${escapeHtml(student.name || 'Unknown Student')}
                    ${program}
                </td>
                <td style="text-align:right">
                    <span class="sp-score-pill ${pillClass}">${avg.toFixed(1)}%</span>
                </td>
                <td style="text-align:right">
                    <span style="font-size:12px; color:#94a3b8; font-style:italic;" title="Item analysis is disabled for Pre-Test">
                        <i class="fas fa-ban" style="font-size:10px;margin-right:2px;"></i> Disabled for Pre-Test
                    </span>
                </td>
            </tr>
        `;
    }).join('');

    if (pagination) {
        if (totalPages <= 1 && total <= perPage) {
            pagination.innerHTML = `<span style="color:#94a3b8;">Showing all ${total} students</span>`;
        } else {
            var paginationBtns = '';
            for (var p = 1; p <= totalPages; p++) {
                if (totalPages > 7) {
                    if (p > 1 && p < totalPages && Math.abs(p - quizStudentsState.page) > 2) {
                        if (p === 2 || p === totalPages - 1) {
                            paginationBtns += `<span style="padding:0 4px;color:#94a3b8;">...</span>`;
                        }
                        continue;
                    }
                }
                paginationBtns += `<button type="button" class="sp-page-btn ${p === quizStudentsState.page ? 'active' : ''}" onclick="setQuizPage(${p})">${p}</button>`;
            }

            pagination.innerHTML = `
                <div>Showing <strong>${startIdx + 1}</strong> to <strong>${endIdx}</strong> of <strong>${total}</strong> students</div>
                <div class="sp-pagination-nav">
                    <button type="button" class="sp-page-btn" ${quizStudentsState.page <= 1 ? 'disabled' : ''} onclick="setQuizPage(${quizStudentsState.page - 1})">
                        <i class="fas fa-chevron-left" style="font-size:10px;"></i> Prev
                    </button>
                    ${paginationBtns}
                    <button type="button" class="sp-page-btn" ${quizStudentsState.page >= totalPages ? 'disabled' : ''} onclick="setQuizPage(${quizStudentsState.page + 1})">
                        Next <i class="fas fa-chevron-right" style="font-size:10px;"></i>
                    </button>
                </div>
            `;
        }
    }
}
renderTopStudents();

function renderAiSummary(summaryText) {
    var rawEl = document.getElementById('aiRawText');
    var bodyEl = document.getElementById('aiSummaryBody');
    if (!rawEl || !bodyEl) {
        return;
    }

    rawEl.textContent = summaryText || 'No insights available yet.';
    bodyEl.innerHTML = parseAI(rawEl.textContent || '');
}

function setClassInsightsEnabled(enabled) {
    var refreshBtn = document.getElementById('refreshInsightsBtn');
    if (!refreshBtn) {
        return;
    }

    refreshBtn.disabled = !enabled;
    refreshBtn.textContent = enabled ? 'Refresh insights' : 'Insights Disabled';

    if (!enabled) {
        renderAiSummary('AI class insights are disabled for this class.');
    }
}

function updateRefreshFormAction(classId) {
    var form = document.getElementById('refreshInsightsForm');
    if (!form) {
        return;
    }

    form.action = refreshInsightsRouteTemplate.replace('__CLASS__', String(classId));
}

function renderAssessmentAiSummary(summaryText) {
    var rawEl = document.getElementById('assessAiRawText');
    var bodyEl = document.getElementById('assessAiSummaryBody');
    if (!rawEl || !bodyEl) {
        return;
    }

    rawEl.textContent = summaryText || 'No assessment insights available yet.';
    bodyEl.innerHTML = parseAI(rawEl.textContent || '');
}

function setAssessmentInsightsEnabled(enabled) {
    var refreshBtn = document.getElementById('refreshAssessmentInsightsBtn');
    if (!refreshBtn) {
        return;
    }

    refreshBtn.disabled = !enabled;
    refreshBtn.textContent = enabled ? 'Refresh insights' : 'Insights Disabled';

    if (!enabled) {
        renderAssessmentAiSummary('Assessment AI analysis is disabled for this class.');
    }
}

function updateAssessmentRefreshFormAction(classId) {
    var form = document.getElementById('refreshAssessmentInsightsForm');
    if (!form) {
        return;
    }

    form.action = refreshAssessmentInsightsRouteTemplate.replace('__CLASS__', String(classId));
}

/* Assessment Pass/Fail chart */
function buildAssessmentChart() {
    var wrap   = document.getElementById('assessChartWrap');
    var canvas = document.getElementById('assessPassFailChart');
    if (!wrap || !canvas) { return; }

    var dpr = window.devicePixelRatio || 1;
    var w   = wrap.offsetWidth;
    var h   = wrap.offsetHeight || 160;

    canvas.width  = w * dpr;
    canvas.height = h * dpr;
    canvas.style.width  = w + 'px';
    canvas.style.height = h + 'px';

    if (assessPassFailChart) { assessPassFailChart.destroy(); assessPassFailChart = null; }

    assessPassFailChart = new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: ['Passed', 'Failed'],
            datasets: [{
                data: [currentAssessPassCount, currentAssessFailCount],
                backgroundColor: ['#1d9e75', '#e24b4a'],
                borderWidth: 0,
            }]
        },
        options: {
            responsive: false,
            devicePixelRatio: dpr,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { font: { family: 'DM Sans', size: 18, weight: '600' }, color: '#666', boxWidth: 14, padding: 22 }
                }
            }
        }
    });
}

function renderAssessmentPassFailCard() {
    var chartCard = document.getElementById('assessChartCard');
    if (!chartCard) { return; }

    if ((currentAssessPassCount + currentAssessFailCount) > 0) {
        if (!document.getElementById('assessChartWrap')) {
            chartCard.innerHTML = '<p class="sp-section-title">Pass / Fail</p><div class="sp-chart-wrap" id="assessChartWrap"><canvas id="assessPassFailChart"></canvas></div>';
        }
        buildAssessmentChart();
        return;
    }

    if (assessPassFailChart) { assessPassFailChart.destroy(); assessPassFailChart = null; }
    chartCard.innerHTML = '<p class="sp-section-title">Pass / Fail</p><p class="sp-empty">No attempts yet.</p>';
}

/* Question Breakdown - Assessment (Chart) */
function buildAssessmentQuestionChart(stats) {
    var wrap = document.getElementById('assessQuestionChartWrap');
    var canvas = document.getElementById('assessQuestionBreakdownChart');
    if (!wrap || !canvas) return;

    var labels = stats.map(function (q, i) { return 'Q' + (i + 1); });
    var fullLabels = stats.map(function (q) { return q.question_text; });
    var values = stats.map(function (q) { return q.pct_correct; });
    var colors = values.map(function (v) { return diffColor(v); });
    var counts = stats.map(function (q) { return q.correct_count + '/' + q.total_answers; });

    if (assessQuestionChart) { assessQuestionChart.destroy(); assessQuestionChart = null; }

    assessQuestionChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: '% Correct',
                data: values,
                backgroundColor: colors,
                borderRadius: 4,
                maxBarThickness: 36,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: function (items) { return fullLabels[items[0].dataIndex] || ''; },
                        label: function (ctx) {
                            return ctx.parsed.y + '% correct (' + counts[ctx.dataIndex] + ')';
                        }
                    }
                }
            },
            scales: {
                x: { ticks: { maxRotation: 0 } },
                y: { beginAtZero: true, max: 100, ticks: { callback: function (v) { return v + '%'; } } }
            }
        }
    });
}

function renderAssessmentQuestionStats(items) {
    var stats = Array.isArray(items) ? items : [];
    currentAssessQuestionStats = stats;

    var wrap = document.getElementById('assessQuestionChartWrap');
    var card = document.getElementById('assessQuestionChartCard');

    var qbHeaderHtml = '<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 12px;"><p class="sp-section-title" style="margin: 0;">Question Breakdown</p><div class="sp-chart-legend" style="display: flex; align-items: center; gap: 12px; font-size: 12px; font-weight: 500;"><span style="display: inline-flex; align-items: center; gap: 5px; color: #166534;"><span style="width: 9px; height: 9px; border-radius: 2px; background: #1d9e75; display: inline-block;"></span> &ge;70% High</span><span style="display: inline-flex; align-items: center; gap: 5px; color: #9a3412;"><span style="width: 9px; height: 9px; border-radius: 2px; background: #ef9f27; display: inline-block;"></span> 40–69% Moderate</span><span style="display: inline-flex; align-items: center; gap: 5px; color: #991b1b;"><span style="width: 9px; height: 9px; border-radius: 2px; background: #e24b4a; display: inline-block;"></span> &lt;40% Low</span></div></div>';

    if (!stats.length) {
        if (assessQuestionChart) { assessQuestionChart.destroy(); assessQuestionChart = null; }
        if (card) card.innerHTML = qbHeaderHtml + '<p class="sp-empty" id="assessQuestionBreakdownEmpty">No answer data yet.</p>';
        return;
    }

    if (!wrap) {
        if (card) card.innerHTML = qbHeaderHtml + '<div class="sp-chart-wrap" id="assessQuestionChartWrap"><canvas id="assessQuestionBreakdownChart"></canvas></div>';
    }

    buildAssessmentQuestionChart(stats);
}

var assessStudentsState = {
    items: @json($aTopStudents->values()),
    search: '',
    page: 1,
    perPage: 10
};

function onAssessStudentSearch(val) {
    assessStudentsState.search = val || '';
    assessStudentsState.page = 1;
    renderAssessmentTopStudents();
}

function setAssessPage(p) {
    assessStudentsState.page = p;
    renderAssessmentTopStudents();
}

function renderAssessmentTopStudents(newItems) {
    if (newItems !== undefined) {
        assessStudentsState.items = Array.isArray(newItems) ? newItems : [];
        assessStudentsState.page = 1;
        var searchInput = document.getElementById('assessStudentSearch');
        if (searchInput) searchInput.value = '';
        assessStudentsState.search = '';
    }

    var body = document.getElementById('assessTopStudentsBody');
    var pagination = document.getElementById('assessStudentsPagination');
    if (!body) return;

    var rankedItems = assessStudentsState.items.map(function(item, idx) {
        return Object.assign({}, item, { _originalRank: idx + 1 });
    });

    var filtered = rankedItems;
    if (assessStudentsState.search.trim()) {
        var q = assessStudentsState.search.trim().toLowerCase();
        filtered = rankedItems.filter(function(st) {
            return (st.name && st.name.toLowerCase().includes(q)) ||
                   (st.program && st.program.toLowerCase().includes(q));
        });
    }

    if (!filtered.length) {
        body.innerHTML = `<tr><td colspan="4" style="text-align:center;padding:24px;color:#94a3b8;">${assessStudentsState.search ? 'No students matching "' + escapeHtml(assessStudentsState.search) + '"' : 'No student data yet.'}</td></tr>`;
        if (pagination) pagination.innerHTML = '';
        return;
    }

    var total = filtered.length;
    var perPage = assessStudentsState.perPage;
    var totalPages = Math.max(1, Math.ceil(total / perPage));
    if (assessStudentsState.page > totalPages) assessStudentsState.page = totalPages;
    if (assessStudentsState.page < 1) assessStudentsState.page = 1;

    var startIdx = (assessStudentsState.page - 1) * perPage;
    var endIdx = Math.min(startIdx + perPage, total);
    var pageItems = filtered.slice(startIdx, endIdx);

    body.innerHTML = pageItems.map(function (student) {
        var avg = Number(student.average_score || 0);
        var pillClass = scoreClass(avg);
        var rank = student._originalRank;
        var medalClass = rank === 1 ? 'gold' : (rank === 2 ? 'silver' : (rank === 3 ? 'bronze' : ''));
        var studentId = Number(student.id || 0);
        var safeName = String(student.name || 'Student').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
        var rankBadge = medalClass
            ? `<span class="sp-rank-medal ${medalClass}">${rank}</span>`
            : `<span class="sp-rank-num">${rank}</span>`;
        var program = student.program
            ? `<span style="font-size: 16px;color:#bbb;margin-left:4px;">${escapeHtml(student.program)}</span>`
            : '';
        return `
            <tr>
                <td>${rankBadge}</td>
                <td>${escapeHtml(student.name || '')} ${program}</td>
                <td style="text-align:right">
                    <span class="sp-score-pill ${pillClass}">${avg.toFixed(1)}%</span>
                </td>
                <td style="text-align:right;white-space:nowrap;">
                    <div style="display:inline-flex;align-items:center;gap:6px;white-space:nowrap;">
                        <button class="sp-refresh-btn sp-analysis-btn" type="button"
                            onclick="openItemAnalysis(${studentId}, '${safeName}', true)">
                            <i class="fas fa-chart-bar"></i> Latest
                        </button>
                        <a href="${assessmentAnalysisRouteTemplate.replace('__CLASS__', currentClassId).replace('__STUDENT__', studentId)}"
                           class="sp-refresh-btn sp-analysis-btn"
                           style="text-decoration:none;">
                            <i class="fas fa-robot"></i> AI Analysis
                        </a>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    if (pagination) {
        if (totalPages <= 1 && total <= perPage) {
            pagination.innerHTML = `<span style="color:#94a3b8;">Showing all ${total} students</span>`;
        } else {
            var paginationBtns = '';
            for (var p = 1; p <= totalPages; p++) {
                if (totalPages > 7) {
                    if (p > 1 && p < totalPages && Math.abs(p - assessStudentsState.page) > 2) {
                        if (p === 2 || p === totalPages - 1) {
                            paginationBtns += `<span style="padding:0 4px;color:#94a3b8;">...</span>`;
                        }
                        continue;
                    }
                }
                paginationBtns += `<button type="button" class="sp-page-btn ${p === assessStudentsState.page ? 'active' : ''}" onclick="setAssessPage(${p})">${p}</button>`;
            }

            pagination.innerHTML = `
                <div>Showing <strong>${startIdx + 1}</strong> to <strong>${endIdx}</strong> of <strong>${total}</strong> students</div>
                <div class="sp-pagination-nav">
                    <button type="button" class="sp-page-btn" ${assessStudentsState.page <= 1 ? 'disabled' : ''} onclick="setAssessPage(${assessStudentsState.page - 1})">
                        <i class="fas fa-chevron-left" style="font-size:10px;"></i> Prev
                    </button>
                    ${paginationBtns}
                    <button type="button" class="sp-page-btn" ${assessStudentsState.page >= totalPages ? 'disabled' : ''} onclick="setAssessPage(${assessStudentsState.page + 1})">
                        Next <i class="fas fa-chevron-right" style="font-size:10px;"></i>
                    </button>
                </div>
            `;
        }
    }
}
renderAssessmentTopStudents();

/* Tab switching */
document.querySelectorAll('.sp-tab-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var tab = this.dataset.tab;
        document.querySelectorAll('.sp-tab-btn').forEach(function (b) {
            b.classList.toggle('active', b.dataset.tab === tab);
        });
        document.querySelectorAll('.sp-tab-panel').forEach(function (p) {
            p.classList.toggle('active', p.id === 'tab-' + tab);
        });
        if (tab === 'quiz') {
            renderPassFailCard();
            if (currentQuestionStats.length) {
                buildQuestionChart(currentQuestionStats);
            }
        } else {
            renderAssessmentPassFailCard();
            if (currentAssessQuestionStats.length) {
                buildAssessmentQuestionChart(currentAssessQuestionStats);
            }
        }
    });
});

/* Class tabs */
document.querySelectorAll('.js-class-tab').forEach(function (tab) {
    tab.addEventListener('click', async function (e) {
        e.preventDefault();
        const classId = Number(this.dataset.classId);
        const href    = this.getAttribute('href');
        if (!classId || !href || classId === currentClassId) return;
        try {
            const res = await fetch(href, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) throw new Error();
            const payload = await res.json();
            currentClassId   = classId;
            currentQuizModuleId = 'all';
            currentAssessModuleId = 'all';
            currentPassCount = Number(payload.passCount || 0);
            currentFailCount = Number(payload.failCount || 0);
            document.getElementById('spSubtitle').textContent = `${payload.class.name} - pre-assessment results and question breakdown.`;
            var avgV = Number(payload.classAverage || 0);
            var avgEl = document.getElementById('classAverageValue');
            if (avgEl) {
                avgEl.textContent = avgV.toFixed(1);
                var avgCard = avgEl.closest('.sp-avg-card');
                if (avgCard) { avgCard.classList.remove('high','mid','low'); avgCard.classList.add(scoreClass(avgV)); }
            }
            populateModuleSelect('quizModuleSelect', payload.preAssessmentModules || [], 'All Modules / Topics (Class Overview)');
            renderQuestionStats(payload.questionStats || []);
            renderTopStudents(payload.topStudents || [], payload.remainingCount || 0);
            var classSummaryEnabled = Object.prototype.hasOwnProperty.call(payload, 'classSummaryEnabled')
                ? Boolean(payload.classSummaryEnabled)
                : true;
            setClassInsightsEnabled(classSummaryEnabled);
            renderAiSummary(classSummaryEnabled
                ? (payload.aiSummary || 'No insights available yet.')
                : 'AI class insights are disabled for this class.');
            updateRefreshFormAction(classId);
            updateAssessmentRefreshFormAction(classId);
            document.querySelectorAll('.js-class-tab').forEach(t => t.classList.toggle('active', Number(t.dataset.classId) === classId));
            window.history.replaceState({}, '', href);
            renderPassFailCard();
            if (currentQuestionStats.length) {
                buildQuestionChart(currentQuestionStats);
            }
            if (payload.assessment) {
                populateModuleSelect('assessModuleSelect', payload.assessment.formalAssessmentModules || [], 'All Formal Assessments (Class Overview)');
                currentAssessPassCount = Number(payload.assessment.passCount || 0);
                currentAssessFailCount = Number(payload.assessment.failCount || 0);
                var aAvgV = Number(payload.assessment.classAverage || 0);
                var aAvgEl = document.getElementById('assessClassAverageValue');
                if (aAvgEl) {
                    aAvgEl.textContent = aAvgV.toFixed(1);
                    var aAvgCard = document.getElementById('assessAvgCard');
                    if (aAvgCard) { aAvgCard.className = 'sp-avg-card ' + scoreClass(aAvgV); }
                }
                renderAssessmentQuestionStats(payload.assessment.questionStats || []);
                renderAssessmentTopStudents(payload.assessment.topStudents || [], payload.assessment.remainingCount || 0);
                var assessmentInsightsEnabled = Object.prototype.hasOwnProperty.call(payload.assessment, 'assessmentAnalysisEnabled')
                    ? Boolean(payload.assessment.assessmentAnalysisEnabled)
                    : true;
                setAssessmentInsightsEnabled(assessmentInsightsEnabled);
                renderAssessmentAiSummary(assessmentInsightsEnabled
                    ? (payload.assessment.aiSummary || 'No assessment insights available yet.')
                    : 'Assessment AI analysis is disabled for this class.');
                var aSubEl = document.getElementById('spAssessSubtitle');
                if (aSubEl) { aSubEl.textContent = payload.class.name + ' - formal assessment results.'; }
                var assessPanel = document.getElementById('tab-assessment');
                if (assessPanel && assessPanel.classList.contains('active')) {
                    renderAssessmentPassFailCard();
                    if (currentAssessQuestionStats.length) {
                        buildAssessmentQuestionChart(currentAssessQuestionStats);
                    }
                }
            }
        } catch (err) { window.location.href = href; }
    });
});

/* ITEM ANALYSIS DIALOG */
async function openItemAnalysis(studentId, studentName, isAssessment) {
    if (!isAssessment) {
        alert('Item analysis is disabled for Pre-Test (Diagnostic Assessment). It is available on Formal Assessment / Post-Test phases.');
        return;
    }
    const dialog    = document.getElementById('itemAnalysisDialog');
    const nameEl    = document.getElementById('iaStudentName');
    const metaEl    = document.getElementById('iaDialogMeta');
    const scorePill = document.getElementById('iaScorePill');
    const attemptLbl= document.getElementById('iaAttemptLabel');
    const strip     = document.getElementById('iaScoreStrip');
    const list      = document.getElementById('iaAnswerList');

    // Reset
    nameEl.textContent    = studentName || 'Student';
    scorePill.textContent = '-';
    scorePill.className   = 'sp-score-pill';
    attemptLbl.textContent= 'Loading...';
    strip.style.display   = 'none';
    list.innerHTML        = `
        <div class="ia-loading">
            <i class="fas fa-circle-notch fa-spin"></i>
            Loading item analysis...
        </div>`;

    dialog.showModal();

    try {
        var activeMod = isAssessment ? currentAssessModuleId : currentQuizModuleId;
        var queryParams = [];
        if (isAssessment) queryParams.push('type=assessment');
        if (activeMod && activeMod !== 'all') queryParams.push('module_id=' + encodeURIComponent(activeMod));
        var queryString = queryParams.length ? '?' + queryParams.join('&') : '';

        const itemAnalysisUrl = itemAnalysisRouteTemplate
            .replace('__CLASS__', String(currentClassId))
            .replace('__STUDENT__', String(studentId))
            + queryString;

        const res = await fetch(itemAnalysisUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });

        if (!res.ok) {
            throw new Error(`Request failed (${res.status})`);
        }

        const data = await res.json();

        if (!data.attempt) {
            attemptLbl.textContent = 'No attempts found';
            strip.style.display    = 'none';
            list.innerHTML = `
                <div class="ia-no-data">
                    <i class="fas fa-inbox"></i>
                    <p>No quiz attempts yet</p>
                    <small>This student hasn't submitted any quizzes in this class.</small>
                </div>`;
            return;
        }

        const pct      = Number(data.attempt.percentage || 0);
        const answers  = data.answers || [];
        const correct  = answers.filter(a => Number(a.is_correct) === 1).length;
        const wrong    = answers.length - correct;
        const total    = answers.length;

// Update header
        scorePill.textContent = pct.toFixed(1) + '%';
        scorePill.className   = 'sp-score-pill ' + scoreClass(pct);
        attemptLbl.textContent= `Attempt #${data.attempt.id} - ${total} questions`;

        // Attempt limit / grant control (formal assessments only)
        renderAttemptLimitBox(data.attempt, studentId);

        // Score strip
        strip.style.display = '';
        document.getElementById('iaStripScore').textContent   = `${correct}/${total}`;
        document.getElementById('iaStripPct').textContent     = pct.toFixed(1) + '%';
        document.getElementById('iaStripCorrect').textContent = correct;
        document.getElementById('iaStripWrong').textContent   = wrong;

        // Answer list
        if (!answers.length) {
            list.innerHTML = `
                <div class="ia-no-data">
                    <i class="fas fa-question-circle"></i>
                    <p>No answers recorded</p>
                    <small>Answers may not have been saved for this attempt.</small>
                </div>`;
            return;
        }

        list.innerHTML = answers.map(function (a, idx) {
            const ok         = Number(a.is_correct) === 1;
            const selected   = a.selected_option ?? '-';
            const correct_op = a.correct_option   ?? '-';
            const qText      = String(a.question_text || '').replace(/<[^>]+>/g, '');

            const selectedChipClass = ok ? 'selected-correct' : 'selected-wrong';
            const selectedLabel     = ok ? 'Your answer (correct)' : 'Your answer (wrong)';

            const correctChip = !ok ? `
                <span class="ia-option-chip correct-answer">
                    ${correct_op}
                    <span class="ia-chip-label">Correct</span>
                </span>` : '';

            return `
                <div class="ia-answer-item ${ok ? 'correct' : 'wrong'}">
                    <div class="ia-answer-icon">
                        <i class="fas fa-${ok ? 'check' : 'times'}"></i>
                    </div>
                    <div class="ia-answer-content">
                        <p class="ia-answer-q-num">Question ${idx + 1}</p>
                        <p class="ia-answer-q-text">${qText}</p>
                        <div class="ia-answer-options">
                            <span class="ia-option-chip ${selectedChipClass}">
                                ${selected}
                                <span class="ia-chip-label">${selectedLabel}</span>
                            </span>
                            ${correctChip}
                        </div>
                    </div>
                </div>
            `;
        }).join('');

    } catch (err) {
        var errorMessage = err && err.message ? err.message : 'Please try again.';
        attemptLbl.textContent = 'Failed to load';
        strip.style.display    = 'none';
        list.innerHTML = `
            <div class="ia-no-data">
                <i class="fas fa-exclamation-circle" style="color:#e24b4a;"></i>
                <p style="color:#a32d2d;">Failed to load item analysis</p>
                <small>${escapeHtml(errorMessage)}</small>
            </div>`;
    }
}
var currentGrantContext = { moduleId: null, studentId: null };

function renderAttemptLimitBox(attempt, studentId) {
    var box = document.getElementById('iaAttemptLimitBox');
    var grantForm = document.getElementById('iaGrantForm');

    if (!attempt.is_formal_assessment) {
        box.style.display = 'none';
        grantForm.style.display = 'none';
        return;
    }

    box.style.display = '';
    grantForm.style.display = 'none';
    document.getElementById('iaMaxAttemptsForm').style.display = 'none';
    document.getElementById('iaMaxAttemptsInput').value = attempt.base_max_attempts;

    document.getElementById('iaAttemptsUsed').textContent = attempt.attempts_used;
    document.getElementById('iaAttemptsAllowed').textContent = attempt.attempts_allowed;

    var breakdown = document.getElementById('iaAttemptsBreakdown');
    breakdown.textContent = attempt.extra_attempts_granted > 0
        ? `(base ${attempt.base_max_attempts} + ${attempt.extra_attempts_granted} granted)`
        : `(base ${attempt.base_max_attempts})`;

    currentGrantContext = { moduleId: attempt.module_id, studentId: studentId };
}

function toggleGrantForm() {
    var form = document.getElementById('iaGrantForm');
    form.style.display = form.style.display === 'none' ? 'flex' : 'none';
}

async function submitGrant() {
    if (!currentGrantContext.moduleId || !currentGrantContext.studentId) {
        alert('No module or student selected. Please reopen the analysis.');
        return;
    }

    var amount = parseInt(document.getElementById('iaGrantAmount').value, 10) || 1;
    var reason = document.getElementById('iaGrantReason').value || '';

    var url = `/modules/${currentGrantContext.moduleId}/quiz/grant-attempt/${currentGrantContext.studentId}`;

    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ extra_attempts: amount, reason: reason }),
        });

        const data = await res.json();

        if (!res.ok || !data.success) {
            alert(data.message || 'Failed to grant extra attempt.');
            return;
        }

        alert(data.message);
        document.getElementById('iaGrantForm').style.display = 'none';

        // Refresh dialog to reflect updated attempts_allowed
        var isAssessment = true; // grant is only for formal assessments
        openItemAnalysis(currentGrantContext.studentId, document.getElementById('iaStudentName').textContent, isAssessment);
    } catch (err) {
        alert('An error occurred while granting an extra attempt.');
    }
}

function toggleMaxAttemptsForm() {
    var form = document.getElementById('iaMaxAttemptsForm');
    form.style.display = form.style.display === 'none' ? 'flex' : 'none';
}

async function submitMaxAttempts() {
    if (!currentGrantContext.moduleId) {
        alert('No module selected. Please reopen the analysis.');
        return;
    }

    var value = parseInt(document.getElementById('iaMaxAttemptsInput').value, 10) || 1;
    var url = `/modules/${currentGrantContext.moduleId}/quiz/max-attempts`;

    try {
        const res = await fetch(url, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ max_attempts: value }),
        });

        const data = await res.json();

        if (!res.ok || !data.success) {
            alert(data.message || 'Failed to save max attempts.');
            return;
        }

        alert(data.message);
        document.getElementById('iaMaxAttemptsForm').style.display = 'none';

        openItemAnalysis(currentGrantContext.studentId, document.getElementById('iaStudentName').textContent, true);
    } catch (err) {
        alert('An error occurred while saving max attempts.');
    }
}

/* Export to Excel (.xlsx) */
function exportPerformanceToExcel(type) {
    if (typeof XLSX === 'undefined') {
        alert('Excel export library is still loading. Please try again in a moment.');
        return;
    }

    var isQuiz = (type === 'quiz');
    var students = isQuiz ? (quizStudentsState.items || []) : (assessStudentsState.items || []);
    var questions = isQuiz ? (currentQuestionStats || []) : (currentAssessQuestionStats || []);
    
    var subtitleEl = document.getElementById(isQuiz ? 'spSubtitle' : 'spAssessSubtitle');
    var className = subtitleEl ? subtitleEl.textContent.split(' - ')[0].trim() : 'Class';
    var activeTabName = isQuiz ? 'Pre-Assessment' : 'Formal Assessment';
    
    var selectEl = document.getElementById(isQuiz ? 'quizModuleSelect' : 'assessModuleSelect');
    var selectedModuleName = selectEl && selectEl.options[selectEl.selectedIndex] ? selectEl.options[selectEl.selectedIndex].text : 'All Modules / Topics';

    var avgEl = document.getElementById(isQuiz ? 'classAverageValue' : 'assessClassAverageValue');
    var classAvg = avgEl ? avgEl.textContent.trim() + '%' : '0%';
    var passCount = isQuiz ? currentPassCount : currentAssessPassCount;
    var failCount = isQuiz ? currentFailCount : currentAssessFailCount;
    var totalAttempts = passCount + failCount;
    var passRate = totalAttempts > 0 ? ((passCount / totalAttempts) * 100).toFixed(1) + '%' : '0%';

    var wb = XLSX.utils.book_new();

    // 1. Student Rankings Sheet Data
    var studentRows = [
        ['CLASS PERFORMANCE REPORT'],
        ['Class:', className],
        ['Assessment Type:', activeTabName],
        ['Module / Filter:', selectedModuleName],
        ['Class Average:', classAvg],
        ['Pass Rate:', passRate + ' (' + passCount + ' Passed, ' + failCount + ' Failed)'],
        ['Total Students Recorded:', students.length],
        ['Exported On:', new Date().toLocaleString()],
        [], // empty row separator
        ['Rank', 'Student ID', 'Student Name', 'Program / Course', 'Average Score', 'Status']
    ];

    students.forEach(function(student, index) {
        var avg = Number(student.average_score || 0);
        studentRows.push([
            index + 1,
            student.idnumber || '—',
            student.name || '—',
            student.program || '—',
            avg.toFixed(1) + '%',
            avg >= 50 ? 'Passed' : 'Failed'
        ]);
    });

    var wsStudents = XLSX.utils.aoa_to_sheet(studentRows);
    
    // Auto-fit column widths for students sheet
    wsStudents['!cols'] = [
        { wch: 8 },  // Rank
        { wch: 18 }, // Student ID
        { wch: 30 }, // Student Name
        { wch: 22 }, // Program
        { wch: 16 }, // Average Score
        { wch: 12 }  // Status
    ];
    XLSX.utils.book_append_sheet(wb, wsStudents, 'Student Rankings');

    // 2. Question Breakdown / Item Analysis Sheet Data
    if (questions && questions.length > 0) {
        var questionRows = [
            ['ITEM ANALYSIS (QUESTION BREAKDOWN)'],
            ['Class:', className],
            ['Assessment Type:', activeTabName],
            ['Module / Filter:', selectedModuleName],
            ['Total Questions Analyzed:', questions.length],
            ['Exported On:', new Date().toLocaleString()],
            [], // empty row separator
            ['#', 'Question Text', 'Correct Answers', 'Total Attempts', 'Accuracy Rate', 'Difficulty Level']
        ];

        questions.forEach(function(q, i) {
            var pct = Number(q.pct_correct || 0);
            var difficulty = pct >= 70 ? 'High (>=70%)' : (pct >= 40 ? 'Moderate (40-69%)' : 'Low (<40%)');
            var qText = q.question_text ? decodeAndStripHtml(q.question_text) : '—';
            questionRows.push([
                'Q' + (i + 1),
                qText,
                q.correct_count || 0,
                q.total_answers || 0,
                pct.toFixed(1) + '%',
                difficulty
            ]);
        });

        var wsQuestions = XLSX.utils.aoa_to_sheet(questionRows);
        wsQuestions['!cols'] = [
            { wch: 8 },  // #
            { wch: 60 }, // Question Text
            { wch: 16 }, // Correct Answers
            { wch: 16 }, // Total Attempts
            { wch: 16 }, // Accuracy Rate
            { wch: 24 }  // Difficulty Level
        ];
        XLSX.utils.book_append_sheet(wb, wsQuestions, 'Question Item Analysis');
    }

    // Build nice filename: Performance_BSIT_3A_Pre-Assessment_2026-09-18.xlsx
    var sanitizedClass = className.replace(/[^a-z0-9]/gi, '_').toLowerCase();
    var sanitizedTab = activeTabName.replace(/[^a-z0-9]/gi, '_').toLowerCase();
    var dateStr = new Date().toISOString().slice(0, 10);
    var filename = 'Performance_' + sanitizedClass + '_' + sanitizedTab + '_' + dateStr + '.xlsx';

    XLSX.writeFile(wb, filename);
}
</script>
@endsection