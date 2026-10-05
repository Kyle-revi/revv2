@extends('layouts.domain')

@section('content')
<style>
    /* Override content-wrapper to be a flex row */
    .content-wrapper {
        display: flex !important;
        flex-direction: row !important;
        height: calc(100vh - 58px) !important;
        overflow: hidden !important;
    }

    /* Sidebar */
    .mod-sidebar {
        width: 312px;
        min-width: 312px;
        background: #111827;
        display: flex;
        flex-direction: column;
        height: 100%;
        overflow: hidden;
        border-right: 1px solid rgba(255,255,255,0.07);
        font-family: 'DM Sans', sans-serif;
    }

    .mod-sidebar-head {
        padding: 16px 18px;
        border-bottom: 1px solid rgba(255,255,255,0.07);
        flex-shrink: 0;
    }

    .mod-sidebar-label {
        font-size: 14px; font-weight: 500;
        letter-spacing: 0.1em; text-transform: uppercase;
        color: rgba(255,255,255,0.3); margin: 0 0 9px;
    }

    .mod-search {
        width: 100%; background: rgba(255,255,255,0.06);
        border: 1px solid rgba(255,255,255,0.1); border-radius: 8px;
        padding: 9px 12px; font-family: 'DM Sans', sans-serif;
        font-size: 15px; color: #fff; outline: none; transition: border-color 0.15s;
    }

    .mod-search::placeholder { color: rgba(255,255,255,0.25); }
    .mod-search:focus { border-color: #3b82f6; }

    .mod-list { flex: 1; overflow-y: auto; padding: 6px 0; }
    .mod-list::-webkit-scrollbar { width: 3px; }
    .mod-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 99px; }

    .mod-item {
        padding: 12px 18px; cursor: pointer;
        border-left: 3px solid transparent;
        transition: background 0.15s, border-color 0.15s;
        border-bottom: 1px solid rgba(255,255,255,0.03);
    }

    .mod-item:hover { background: rgba(255,255,255,0.04); }
    .mod-item.active { background: rgba(59,130,246,0.1); border-left-color: #3b82f6; }

    .mod-item.locked {
        opacity: 0.45;
        cursor: not-allowed;
        pointer-events: none;
    }

    .mod-badge.locked-badge { background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.35); }

    .mod-item-row {
        display: flex; align-items: center;
        justify-content: space-between; gap: 8px; margin-bottom: 6px;
    }

    .mod-item-title {
        font-size: 15px; font-weight: 500;
        color: rgba(255,255,255,0.65); line-height: 1.4; flex: 1;
    }

    .mod-item.active .mod-item-title { color: #fff; }

    .mod-item.lecture-item { padding-bottom: 6px; }
    .mod-item.lecture-item .mod-item-row { margin-bottom: 4px; }
    .mod-tree-toggle {
        width: 18px; height: 18px; border: 0; padding: 0; flex-shrink: 0;
        background: transparent; color: rgba(255,255,255,0.45); cursor: pointer;
    }
    .mod-tree-toggle i { transition: transform 0.15s ease; }
    .mod-item.lecture-item.expanded .mod-tree-toggle i { transform: rotate(90deg); }
    .mod-tree-list { display: none; margin: 0 -18px 0 -3px; padding: 2px 0 0 22px; }
    .mod-item.lecture-item.expanded .mod-tree-list { display: block; }
    .mod-tree-content {
        padding: 7px 12px; color: rgba(255,255,255,0.46); font-size: 13px;
        border-left: 1px solid rgba(255,255,255,0.12);
    }
    .mod-tree-content-label { margin-bottom: 4px; color: rgba(255,255,255,0.3); text-transform: uppercase; font-size: 11px; letter-spacing: 0.08em; }
    .mod-tree-node {
        width: 100%; padding: 7px 8px; border: 0; border-left: 1px solid rgba(255,255,255,0.12);
        background: transparent; color: rgba(255,255,255,0.62); text-align: left; cursor: pointer;
        font: 13px 'DM Sans', sans-serif; display: flex; align-items: center; gap: 6px;
    }
    .mod-tree-node:hover, .mod-tree-node.active { background: rgba(59,130,246,0.12); color: #fff; }
    .mod-tree-node i { color: #6ee7b7; font-size: 10px; }
    .mod-tree-node-text { flex: 1; min-width: 0; }
    .mod-tree-progress { width: 34px; flex-shrink: 0; text-align: right; color: rgba(255,255,255,0.42); font-size: 11px; }
    .mod-tree-progress-track { height: 3px; margin: 2px 8px 5px 28px; background: rgba(255,255,255,0.1); border-radius: 99px; overflow: hidden; }
    .mod-tree-progress-fill { height: 100%; width: 0; background: #1d9e75; border-radius: 99px; transition: width 0.3s ease; }
    .mod-tree-lessons { display: none; padding-left: 14px; }
    .mod-subpart-item { margin-bottom: 4px; }
    .mod-subpart-item.expanded > .mod-tree-node.domain .domain-caret { transform: rotate(90deg); }
    .mod-subpart-item.expanded > .mod-tree-lessons { display: block !important; }
    .domain-caret { transition: transform 0.15s ease; color: rgba(255,255,255,0.6); }

    .mod-badge {
        font-size: 14px; font-weight: 500;
        padding: 2px 8px; border-radius: 99px; white-space: nowrap; flex-shrink: 0;
    }

    .mod-badge.quiz            { background: rgba(59,130,246,0.2); color: #93c5fd; }
    .mod-badge.pre-test-badge  { background: rgba(245, 158, 11, 0.18); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
    .mod-badge.post-test-badge { background: rgba(139, 92, 246, 0.18); color: #c084fc; border: 1px solid rgba(139, 92, 246, 0.3); }
    .mod-badge.formal-badge    { background: rgba(59, 130, 246, 0.18); color: #93c5fd; border: 1px solid rgba(59, 130, 246, 0.3); }
    .mod-badge.doc             { background: rgba(29,158,117,0.2); color: #6ee7b7; }

    .mod-bar-track { height: 4px; background: rgba(255,255,255,0.08); border-radius: 99px; overflow: hidden; }
    .mod-bar-fill  { height: 100%; border-radius: 99px; background: #1d9e75; transition: width 0.4s ease; }
    .mod-bar-fill.quiz      { background: #3b82f6; }
    .mod-bar-fill.pre-test  { background: #f59e0b; }
    .mod-bar-fill.post-test { background: #8b5cf6; }

    /* Main */
    .mod-main {
        flex: 1; display: flex; flex-direction: column;
        overflow: hidden; background: #f8f7f5; min-width: 0;
    }

    .mod-content {
        flex: 1; overflow-y: auto; padding: 40px 48px;
        font-family: 'DM Sans', sans-serif;
        font-size: 16px;
    }

    .mod-content::-webkit-scrollbar { width: 4px; }
    .mod-content::-webkit-scrollbar-thumb { background: #ddd; border-radius: 99px; }

    /* Footer */
    .mod-footer {
        background: #fff; border-top: 1px solid #ebebeb;
        padding: 12px 24px; display: flex; align-items: center;
        justify-content: space-between; flex-shrink: 0;
        font-family: 'DM Sans', sans-serif;
    }

    .mod-progress-label { font-size: 16px; color: #555; font-weight: 500; }
    .mod-progress-label strong { color: #111; }

    .mod-footer-track {
        flex: 1; max-width: 260px; height: 6px;
        background: #f3f3f3; border-radius: 99px; overflow: hidden; margin: 0 16px;
    }

    .mod-footer-fill { height: 100%; background: #1d9e75; border-radius: 99px; transition: width 0.4s ease; }

    .mod-nav-btns { display: flex; gap: 6px; }

    .mod-nav-btn {
        height: 36px; padding: 0 15px; border: 1px solid #e4e4e4;
        background: #fff; border-radius: 8px; font-family: 'DM Sans', sans-serif;
        font-size: 15px; font-weight: 500; color: #555; cursor: pointer;
        display: flex; align-items: center; gap: 5px; transition: border-color 0.15s, color 0.15s;
    }

    .mod-nav-btn:hover { border-color: #111; color: #111; }

    /* Placeholder */
    .mod-placeholder {
        display: flex; flex-direction: column; align-items: center;
        justify-content: center; height: 100%; text-align: center; color: #ccc; gap: 10px;
    }

    .mod-placeholder i { font-size: 34px; opacity: 0.2; }
    .mod-placeholder p { font-size: 16px; }

    /* Doc view */
    .mod-doc-heading {
        font-family: 'DM Sans', sans-serif;
        font-size: 28px; font-weight: 500; color: #111; margin: 0 0 6px; letter-spacing: -0.02em;
    }

    .mod-doc-desc { font-size: 16px; color: #64748b; margin: 0 0 22px; }

    .mod-pdf-wrap {
        width: 100%; height: 640px; border: 1px solid #ebebeb;
        border-radius: 12px; overflow: hidden; background: #fff;
    }

    .mod-pdf-wrap iframe { border: none; width: 100%; height: 100%; }

    /* Quiz intro */
    .qi-wrap { max-width: 620px; margin: 0 auto; text-align: center; padding: 30px 0; }

    .qi-icon {
        width: 64px; height: 64px; border-radius: 15px;
        background: #eff6ff; color: #2563eb;
        display: flex; align-items: center; justify-content: center;
        font-size: 24px; margin: 0 auto 18px;
    }

    .qi-title { font-family: 'DM Sans', sans-serif; font-size: 28px; font-weight: 500; color: #111; margin: 0 0 6px; }
    .qi-sub   { font-size: 16px; color: #64748b; margin: 0 0 24px; }

    .qi-rules {
        background: #fff; border: 1px solid #ebebeb;
        border-radius: 10px; padding: 18px 22px; text-align: left; margin-bottom: 24px;
    }

    .qi-rules-title { font-size: 14px; font-weight: 500; color: #64748b; letter-spacing: 0.06em; text-transform: uppercase; margin: 0 0 10px; }

    .qi-rule {
        display: flex; align-items: flex-start; gap: 9px;
        font-size: 16px; color: #444; padding: 7px 0; border-bottom: 1px solid #f7f7f7;
    }

    .qi-rule:last-child { border-bottom: none; }
    .qi-rule-dot { width: 6px; height: 6px; border-radius: 50%; background: #3b82f6; flex-shrink: 0; margin-top: 5px; }

    .qi-start-btn {
        height: 44px; padding: 0 28px; background: #111827; color: #fff;
        border: none; border-radius: 10px; font-family: 'DM Sans', sans-serif;
        font-size: 15px; font-weight: 500; cursor: pointer;
        display: inline-flex; align-items: center; gap: 7px;
        transition: background 0.15s, transform 0.1s;
    }

    .qi-start-btn:hover  { background: #1f2937; }
    .qi-start-btn:active { transform: scale(0.98); }

    /* Quiz */
    .qz-wrap { max-width: 760px; margin: 0 auto; }

    .qz-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }

    .qz-title { font-family: 'DM Sans', sans-serif; font-size: 24px; font-weight: 500; color: #111; margin: 0; }

    .qz-timer {
        display: flex; align-items: center; gap: 5px;
        background: #111827; color: #fff; padding: 5px 12px;
        border-radius: 7px; font-size: 17px; font-weight: 500; font-family: monospace;
    }

    .qz-timer.warning { background: #e24b4a; }

    .qz-nav {
        display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 18px;
        padding: 12px 14px; background: #fff; border: 1px solid #ebebeb; border-radius: 9px;
    }

    .qz-pill {
        width: 36px; height: 36px; border-radius: 8px;
        border: 1px solid #e4e4e4; background: #fff;
        font-family: 'DM Sans', sans-serif; font-size: 16px; font-weight: 500;
        color: #555; cursor: pointer;
        display: flex; align-items: center; justify-content: center; transition: all 0.15s;
    }

    .qz-pill:hover    { border-color: #3b82f6; color: #3b82f6; }
    .qz-pill.answered { background: #1d9e75; border-color: #1d9e75; color: #fff; }
    .qz-pill.current  { border-color: #3b82f6; color: #3b82f6; font-weight: 500; box-shadow: 0 0 0 3px rgba(59,130,246,0.12); }
    .qz-pill.answered.current { background: #1d9e75; border-color: #1d9e75; color: #fff; box-shadow: 0 0 0 3px rgba(29,158,117,0.15); }

    .qz-q-card {
        background: #fff; border: 1px solid #ebebeb;
        border-radius: 11px; padding: 22px 26px; margin-bottom: 14px;
    }

    .qz-q-num  { font-size: 14px; font-weight: 500; color: #64748b; letter-spacing: 0.06em; text-transform: uppercase; margin: 0 0 8px; }
    .qz-q-text { font-size: 16px; color: #111; font-weight: 500; margin: 0 0 18px; line-height: 1.5; }

    .qz-option {
        display: flex; align-items: center; gap: 10px;
        padding: 12px 14px; border: 1px solid #e4e4e4; border-radius: 8px;
        margin-bottom: 8px; cursor: pointer; background: #fff;
        transition: border-color 0.15s, background 0.15s;
        font-size: 16px; color: #333; user-select: none;
    }

    .qz-option:last-child { margin-bottom: 0; }
    .qz-option:hover:not(.disabled) { border-color: #3b82f6; background: #eff6ff; }
    .qz-option.selected  { border-color: #3b82f6; background: #eff6ff; color: #1e40af; font-weight: 500; }
    .qz-option.disabled  { cursor: not-allowed; opacity: 0.6; }
    .qz-option input[type="radio"] { display: none; }

    .qz-option-key {
        width: 26px; height: 26px; border-radius: 6px;
        background: #f3f3f3; color: #555;
        display: flex; align-items: center; justify-content: center;
        font-size: 15px; font-weight: 500; flex-shrink: 0;
        transition: background 0.15s, color 0.15s;
    }

    .qz-option.selected .qz-option-key { background: #3b82f6; color: #fff; }

    .qz-actions { display: flex; justify-content: space-between; align-items: center; gap: 10px; }
    .qz-progress { font-size: 15px; color: #64748b; }

    .qz-btn {
        height: 38px; padding: 0 17px; border-radius: 8px;
        font-family: 'DM Sans', sans-serif; font-size: 15px; font-weight: 500;
        cursor: pointer; display: inline-flex; align-items: center; gap: 5px;
        border: 1px solid transparent; transition: background 0.15s, transform 0.1s;
    }

    .qz-btn:active { transform: scale(0.98); }
    .qz-btn-dark    { background: #111827; color: #fff; }
    .qz-btn-dark:hover { background: #1f2937; }
    .qz-btn-outline { background: #fff; color: #555; border-color: #e4e4e4; }
    .qz-btn-outline:hover { border-color: #111; color: #111; }
    .qz-btn-success { background: #1d9e75; color: #fff; }
    .qz-btn-success:hover { background: #0f6e56; }

    /* Result */
    .qz-result { max-width: 560px; margin: 0 auto; text-align: center; padding: 30px 0; }

    .qz-result h2 { font-family: 'DM Sans', sans-serif; font-size: 26px; font-weight: 500; color: #111; margin: 0 0 24px; }

    .qz-gauge-wrap { position: relative; width: 240px; height: 138px; margin: 0 auto 18px; }

    .qz-gauge-score {
        position: absolute; bottom: 6px; left: 50%; transform: translateX(-50%);
        font-family: 'DM Sans', sans-serif; font-size: 44px; font-weight: 500; color: #111; line-height: 1;
    }

    .qz-verdict {
        font-size: 16px; font-weight: 500; margin: 0 0 20px;
        display: flex; align-items: center; justify-content: center; gap: 6px;
    }

    .qz-verdict.pass { color: #1d9e75; }
    .qz-verdict.fail { color: #e24b4a; }

    /* AI Insights Card Redesign */
    .qz-ai-box {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 20px;
        text-align: left;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    .qz-ai-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 14px;
        padding-bottom: 12px;
        border-bottom: 1px solid #f1f5f9;
        flex-wrap: wrap;
        gap: 10px;
    }
    .qz-ai-header-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .qz-ai-icon-badge {
        width: 34px;
        height: 34px;
        border-radius: 10px;
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

    .qz-result-btns { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }

    .qz-toast {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 13000;
        min-width: 260px;
        max-width: min(92vw, 380px);
        padding: 10px 12px;
        border-radius: 10px;
        border: 1px solid #f2deaf;
        background: #fff9ea;
        color: #7b5a10;
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.14);
        font-size: 14px;
        line-height: 1.45;
        opacity: 0;
        transform: translateY(-6px);
        transition: opacity 0.2s ease, transform 0.2s ease;
        pointer-events: none;
        white-space: pre-line;
    }

    .qz-toast.show {
        opacity: 1;
        transform: translateY(0);
    }

    .qz-toast.fail {
        border-color: #f4b3b2;
        background: #fff4f3;
        color: #7b2220;
    }

    /* Attempt History Breakdown */
    .qz-history-card {
        background: #fff;
        border: 1px solid #ebebeb;
        border-radius: 11px;
        padding: 16px 20px;
        margin-bottom: 20px;
        text-align: left;
    }
    .qz-history-title {
        font-size: 14px;
        font-weight: 500;
        color: #111;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        margin: 0 0 12px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .qz-history-item {
        border: 1px solid #ebebeb;
        border-radius: 8px;
        margin-bottom: 8px;
        overflow: hidden;
    }
    .qz-history-item:last-child { margin-bottom: 0; }
    .qz-history-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 14px;
        cursor: pointer;
        background: #fafafa;
        user-select: none;
        transition: background 0.15s;
    }
    .qz-history-row:hover { background: #f3f3f3; }
    .qz-history-left { display: flex; align-items: center; gap: 10px; }
    .qz-history-num { font-size: 15px; font-weight: 500; color: #555; }
    .qz-history-score {
        font-size: 14px;
        font-weight: 500;
        padding: 2px 7px;
        border-radius: 5px;
    }
    .qz-history-score.pass { background: #e1f5ee; color: #0f6e56; }
    .qz-history-score.fail { background: #fcebeb; color: #a32d2d; }
    .qz-history-date { font-size: 13px; color: #aaa; }
    .qz-history-chevron { transition: transform 0.15s; color: #aaa; }
    .qz-history-item.open .qz-history-chevron { transform: rotate(180deg); }
    .qz-history-detail { display: none; padding: 14px; border-top: 1px solid #ebebeb; }
    .qz-history-item.open .qz-history-detail { display: block; }
    .qz-history-q {
        border-left: 3px solid #ddd;
        padding: 8px 12px;
        margin-bottom: 10px;
        border-radius: 0 6px 6px 0;
        font-size: 14px;
    }
    .qz-history-q:last-child { margin-bottom: 0; }
    .qz-history-q.correct   { background: #f0fdf7; border-color: #bfe8d6; }
    .qz-history-q.incorrect { background: #fff8f8; border-color: #f4c9c8; }
    .qz-history-q-text { font-weight: 500; color: #111; margin: 0 0 6px; }
    .qz-history-q-ans  { font-size: 13px; color: #555; margin: 2px 0; }
    .qz-history-empty { font-size: 14px; color: #aaa; text-align: center; padding: 12px 0; }

    /* ── Lecture stage tabs (pre-test / content / post-test) ── */
    .lec-header { margin-bottom: 4px; }

    .lec-tabs {
        display: flex; gap: 8px; margin-bottom: 24px;
        border-bottom: 1px solid #ebebeb; padding-bottom: 16px;
    }

    .lec-tab {
        height: 36px; padding: 0 16px; border-radius: 8px;
        border: 1px solid #e4e4e4; background: #fff;
        font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 500;
        color: #666; cursor: pointer; transition: all 0.15s;
        display: inline-flex; align-items: center; gap: 6px;
    }

    .lec-tab:hover  { border-color: #3b82f6; color: #3b82f6; }
    .lec-tab.active { background: #111827; border-color: #111827; color: #fff; }
    .lec-tab .lec-tab-check { font-size: 11px; color: #1d9e75; }
    .lec-tab.active .lec-tab-check { color: #6ee7b7; }

    /* ── Sub-parts (content stage) ── */
    .sp-layout { display: flex; gap: 20px; align-items: flex-start; }

    .sp-list {
        width: 260px; flex-shrink: 0; background: #fff;
        border: 1px solid #ebebeb; border-radius: 11px; overflow: hidden;
    }

    .sp-item {
        padding: 12px 14px; cursor: pointer;
        border-bottom: 1px solid #f5f5f5; transition: background 0.15s;
    }

    .sp-item:last-child { border-bottom: none; }
    .sp-item:hover { background: #f8f7f5; }
    .sp-item.active { background: #eff6ff; }

    .sp-item-title {
        font-size: 14px; font-weight: 500; color: #333;
        margin-bottom: 6px; display: flex; align-items: center; gap: 6px;
    }

    .sp-item.active .sp-item-title { color: #1e40af; }
    .sp-item.completed .sp-item-title i { color: #1d9e75; }

    .sp-item-bar-track { height: 3px; background: #f0f0f0; border-radius: 99px; overflow: hidden; }
    .sp-item-bar-fill  { height: 100%; background: #1d9e75; border-radius: 99px; transition: width 0.3s; }

    .sp-viewer { flex: 1; min-width: 0; }

    .sp-body { line-height: 1.7; color: #333; margin-bottom: 20px; }

    .sp-empty {
        text-align: center; color: #bbb; padding: 60px 20px;
        display: flex; flex-direction: column; align-items: center; gap: 10px;
    }
</style>

{{-- Sidebar --}}
<div class="mod-sidebar">
    <div class="mod-sidebar-head">
        <p class="mod-sidebar-label">Course Outline</p>
        <input type="text" class="mod-search" id="modSearch" placeholder="Search modules...">
    </div>
    <div class="mod-list" id="modList">
        @forelse($modules as $module)
            @php
                $isLocked = $locked[$module->id] ?? false;
                $isPreTest = ($module->quiz_stage === 'pre_test')
                    || in_array($module->assessment_purpose, ['pre_test', 'pre_assessment'])
                    || preg_match('/pre[- ]?(test|assessment)/i', (string) $module->title);
                $isPostTest = ($module->quiz_stage === 'post_test')
                    || in_array($module->assessment_purpose, ['post_test', 'post_assessment'])
                    || preg_match('/post[- ]?(test|assessment)|final assessment/i', (string) $module->title);
                $isQuizOrAssessment = (bool) ($module->is_quiz || $module->is_formal_assessment || $isPreTest || $isPostTest);
            @endphp
              <div class="mod-item {{ $isLocked ? 'locked' : '' }} {{ $module->is_lecture ? 'lecture-item' : '' }}"
                 data-module-id="{{ $module->id }}"
                 data-is-quiz="{{ $isQuizOrAssessment ? '1' : '0' }}"
                 data-locked="{{ $isLocked ? '1' : '0' }}">
                <div class="mod-item-row">
                    @if($module->is_lecture && $module->subparts->isNotEmpty())
                        <button type="button" class="mod-tree-toggle" onclick="toggleModuleOutline(event, {{ $module->id }})" aria-label="Toggle Lecture content">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    @endif
                    <span class="mod-item-title">{{ $loop->iteration }}. {{ $module->title }}</span>
                    @if($isLocked)
                        @php
                            $av = $availabilityInfo[$module->id] ?? null;
                            $isLockedByAssessment = $av['is_locked_by_assessment'] ?? false;
                            $lockTitle = $isLockedByAssessment
                                ? "Locked: " . ($activeAssessmentLabel ?? 'Assessment') . " in progress"
                                : (($av['is_upcoming'] ?? false)
                                    ? 'Opens on ' . ($av['available_at'] ?? 'scheduled date')
                                    : (($av['is_closed'] ?? false) ? 'Closed / Past Due' : 'Locked'));
                            $lockText = $isLockedByAssessment
                                ? 'In Assessment'
                                : (($av['is_upcoming'] ?? false) ? 'Opens Soon' : ((($av['is_closed'] ?? false) ? 'Closed' : 'Locked')));
                        @endphp
                        <span class="mod-badge locked-badge" title="{{ $lockTitle }}"><i class="fas fa-lock" style="font-size:9px;"></i> {{ $lockText }}</span>
                    @elseif($isPostTest)
                        <span class="mod-badge post-test-badge">Post-Test</span>
                    @elseif($module->is_formal_assessment)
                        <span class="mod-badge formal-badge">Formal Assessment</span>
                    @elseif($isPreTest || $module->is_quiz)
                        <span class="mod-badge pre-test-badge">Pre-Test</span>
                    @else
                        <span class="mod-badge doc module-progress-badge" data-module-id="{{ $module->id }}">
                            {{ $progress[$module->id] ?? 0 }}%
                        </span>
                    @endif
                </div>
                <div class="mod-bar-track">
                    <div class="mod-bar-fill {{ $isPostTest ? 'post-test' : ($module->is_formal_assessment ? 'quiz' : (($isPreTest || $module->is_quiz) ? 'pre-test' : '')) }} module-progress-bar"
                         data-module-id="{{ $module->id }}"
                         style="width:{{ $progress[$module->id] ?? 0 }}%">
                    </div>
                </div>
                @if($module->is_lecture && $module->subparts->isNotEmpty())
                    <div class="mod-tree-list" data-outline-module="{{ $module->id }}">
                        <div class="mod-tree-content">
                            @foreach($module->subparts as $subpart)
                                <div class="mod-subpart-item {{ $subpart->lessons->isNotEmpty() ? 'has-lessons' : '' }}" data-subpart-id="{{ $subpart->id }}">
                                    <button type="button" class="mod-tree-node domain" data-module-id="{{ $module->id }}" data-subpart-id="{{ $subpart->id }}" data-subpart-index="{{ $loop->index }}" onclick="selectOutlineSubpart(event, {{ $module->id }}, {{ $loop->index }}, this)">
                                        @if($subpart->lessons->isNotEmpty())
                                            <i class="fas fa-chevron-right domain-caret"></i>
                                        @else
                                            <i class="fas fa-folder"></i>
                                        @endif
                                        <span class="mod-tree-node-text">{{ $subpart->title }}</span>
                                        <span class="mod-tree-progress" data-subpart-progress-label="{{ $subpart->id }}">{{ (int) $subpart->student_progress }}%</span>
                                    </button>
                                    <div class="mod-tree-progress-track"><div class="mod-tree-progress-fill" data-subpart-progress-bar="{{ $subpart->id }}" style="width:{{ $subpart->student_progress }}%"></div></div>
                                    @if($subpart->lessons->isNotEmpty())
                                        <div class="mod-tree-lessons">
                                            @foreach($subpart->lessons as $lesson)
                                                <button type="button" class="mod-tree-node lesson" data-lesson-id="{{ $lesson->id }}" onclick="selectOutlineLesson(event, {{ $module->id }}, {{ $subpart->id }}, {{ $loop->index }}, this)">
                                                    <i class="fas fa-folder" style="color:#6ee7b7;"></i>
                                                    <span class="mod-tree-node-text">{{ $lesson->title }}</span>
                                                    <span class="mod-tree-progress" data-lesson-progress-label="{{ $lesson->id }}">{{ (int) $lesson->student_progress }}%</span>
                                                </button>
                                                <div class="mod-tree-progress-track lesson-progress-track"><div class="mod-tree-progress-fill" data-lesson-progress-bar="{{ $lesson->id }}" style="width:{{ $lesson->student_progress }}%"></div></div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div style="padding:2rem;text-align:center;color:rgba(255,255,255,0.2);font-size: 15px;font-family:'DM Sans',sans-serif;">
                No modules yet.
            </div>
        @endforelse
    </div>
</div>

{{-- Main --}}
<div class="mod-main">
    @if($hasActiveAssessment ?? false)
        <div id="assessmentLockBanner" style="background: #fffbeb; border-bottom: 1px solid #fef3c7; padding: 10px 18px; display: flex; align-items: center; gap: 10px; color: #92400e; font-size: 13.5px; font-family: 'DM Sans', sans-serif;">
            <i class="fas fa-lock" style="color: #d97706; font-size: 14px;"></i>
            <span><strong id="assessmentBannerTitle">{{ $activeAssessmentLabel ?? 'Formal Assessment' }} in Progress:</strong> Lecture modules are temporarily locked while you are taking an assessment. Submit your assessment to unlock your lessons.</span>
        </div>
    @endif
    <div class="mod-content" id="modContent">
        <div class="mod-placeholder">
            <i class="fas fa-book-open"></i>
            <p>Select a module from the outline to begin.</p>
        </div>
    </div>
    <div class="mod-footer">
        <span class="mod-progress-label">
            Progress: <strong id="overallProgress">{{ $overallCompletion }}%</strong>
        </span>
        <div class="mod-footer-track">
            <div class="mod-footer-fill" id="overallProgressBar" style="width:{{ $overallCompletion }}%"></div>
        </div>
        <div class="mod-nav-btns">
            <button class="mod-nav-btn" id="prevModule"><i class="fas fa-chevron-left"></i> Prev</button>
            <button class="mod-nav-btn" id="nextModule">Next <i class="fas fa-chevron-right"></i></button>
        </div>
    </div>
</div>

@section('scripts')
<script>
    let modules              = @json($modules->toArray() ?? []);
    let lockedModules        = @json($locked ?? []);
    let availabilityMap      = @json($availabilityInfo ?? []);
    let hasActiveAssessment  = @json($hasActiveAssessment ?? false);
    // Keyed "{moduleId}" for standalone quizzes / mock board phases (unchanged
    // shape, exactly as before quiz_stage existed), and "{moduleId}:pre_test"
    // / "{moduleId}:post_test" for lecture module stages. See
    // quizAttemptKey() below. The controller must supply both key shapes —
    // see STUDENTMODULES_CONTROLLER_NOTE at the bottom of this file's PR.
    let quizAttempts         = @json($quizAttempts ?? []);
    let currentModuleId      = null;
    let quizTimerInterval    = null;
    let progressSaveTimer    = null;
    let timeLeft             = 0;
    let currentQuizQuestions = [];
    let currentQIndex        = 0;
    let answeredQuestions    = new Set();
    let selectedAnswers      = {};
    let isQuizActive         = false;
    let warningCount         = 0;
    let lastWarningTime      = 0; // Prevent duplicate warnings
    let isFormalAssessment   = false; // Track if current module is formal assessment
    let moduleProgressMap    = @json($progress ?? []);
    let quizToastTimer       = null;
    let attemptLimits        = @json($attemptLimits ?? []);

    // ── Generic quiz-taking state — shared by standalone quizzes AND
    // lecture pre-test/post-test stages, so there is exactly one
    // implementation of the quiz-taking UI instead of two diverging copies.
    let quizRenderTarget = '#modContent'; // which container the quiz UI renders into
    let currentQuizStage = null;          // null = standalone quiz, else 'pre_test' | 'post_test'
    let quizBackHandler  = null;          // function called when "Back" is pressed mid-result

    // Cross-tab formal assessment synchronization with gentle debounce delay (to avoid server crash / race conditions)
    function broadcastAssessmentEvent(eventType) {
        try {
            if ('BroadcastChannel' in window) {
                const channel = new BroadcastChannel('formal_assessment_sync_channel');
                channel.postMessage({ event: eventType, timestamp: Date.now() });
                channel.close();
            }
        } catch (e) {}

        try {
            localStorage.setItem('formal_assessment_sync', JSON.stringify({
                event: eventType,
                timestamp: Date.now()
            }));
        } catch (e) {}
    }

    function initAssessmentCrossTabSync() {
        let syncTimeout = null;

        function handleAssessmentEvent(evt) {
            if (isQuizActive) return; // Don't interrupt if currently taking a quiz in this tab
            if (syncTimeout) clearTimeout(syncTimeout);

            showToast('Assessment status updated. Refreshing modules in a moment...', 'pass');

            // Gentle delay (800ms) to ensure smooth database commitment and avoid server overload
            syncTimeout = setTimeout(() => {
                window.location.reload();
            }, 800);
        }

        try {
            if ('BroadcastChannel' in window) {
                const channel = new BroadcastChannel('formal_assessment_sync_channel');
                channel.onmessage = (e) => {
                    if (e.data && e.data.event) {
                        handleAssessmentEvent(e.data.event);
                    }
                };
            }
        } catch (e) {}

        window.addEventListener('storage', (e) => {
            if (e.key === 'formal_assessment_sync' && e.newValue) {
                try {
                    const parsed = JSON.parse(e.newValue);
                    if (parsed && parsed.event) {
                        handleAssessmentEvent(parsed.event);
                    }
                } catch (err) {}
            }
        });

        // Tab focus / visibility check: If student switches back to this tab, refresh gently if assessment started elsewhere
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && !hasActiveAssessment && !isQuizActive) {
                const lastSync = localStorage.getItem('formal_assessment_sync');
                if (lastSync) {
                    try {
                        const parsed = JSON.parse(lastSync);
                        if (parsed.event === 'assessment_started' && (Date.now() - parsed.timestamp) < 300000) {
                            setTimeout(() => { window.location.reload(); }, 600);
                        }
                    } catch (err) {}
                }
            }
        });
    }

    initAssessmentCrossTabSync();

    // ── Lecture (pre-test / content / post-test) state ──
    let currentLectureStage  = 'content'; // 'pre_test' | 'content' | 'post_test'
    let currentSubpartIndex  = 0;
    let currentLessonIndex   = 0;
    let subpartProgressSaveTimer = null;

    function quizAttemptKey(moduleId, stage) {
        return stage ? `${moduleId}:${stage}` : `${moduleId}`;
    }

    function getAttemptRuleText(moduleId) {
        const limit = attemptLimits && attemptLimits[moduleId];
        if (!limit) {
            return 'Multiple attempts allowed for practice.';
        }
        const allowed = limit.attempts_allowed || limit.base_max_attempts || 1;
        const used = limit.attempts_used || 0;
        const remaining = Math.max(0, allowed - used);
        if (allowed === 1) {
            return 'You only have 1 attempt for this assessment.';
        }
        return `You have ${remaining} of ${allowed} attempt(s) remaining.`;
    }

    function toggleModuleOutline(event, moduleId) {
        event.stopPropagation();
        const moduleItem = document.querySelector(`.mod-item[data-module-id="${moduleId}"]`);
        moduleItem?.classList.toggle('expanded');
    }

    function selectOutlineSubpart(event, moduleId, index, node) {
        event.stopPropagation();
        const mod = modules.find(module => module.id == moduleId);
        if (!mod) return;

        const subpart = (mod.subparts || [])[index];
        const subpartItem = node.closest('.mod-subpart-item');

        // If this domain has sub-domains (lessons), only toggle its collapsible accordion!
        if (subpart && subpart.lessons && subpart.lessons.length > 0) {
            if (subpartItem) {
                subpartItem.classList.toggle('expanded');
            } else {
                node.classList.toggle('expanded');
            }
            return;
        }

        // Standalone subpart without child lessons -> load its content directly
        document.querySelectorAll('.mod-tree-node').forEach(item => item.classList.remove('active'));
        node.classList.add('active');
        loadModule(moduleId, false);
        currentLectureStage = 'content';
        renderLectureShell(mod);
        loadSubpart(moduleId, index);
    }

    function selectOutlineLesson(event, moduleId, subpartId, index, node) {
        event.stopPropagation();
        const mod = modules.find(module => module.id == moduleId);
        const subpart = (mod?.subparts || []).find(item => item.id == subpartId);
        const lesson = (subpart?.lessons || [])[index];
        if (!mod || !subpart || !lesson) return;

        document.querySelectorAll('.mod-tree-node').forEach(item => item.classList.remove('active'));
        node.classList.add('active');

        // Ensure parent module and subpart are expanded
        $(`.mod-item[data-module-id="${moduleId}"]`).addClass('expanded');
        node.closest('.mod-subpart-item')?.classList.add('expanded');

        loadModule(moduleId, false);
        currentLectureStage = 'content';
        renderLectureShell(mod);
        renderLectureContentStage(mod);
        renderLessonViewer(mod, subpart, lesson);
    }

    function showQuizWarningToast(message, type) {
        type = type || 'warn';
        let toast = document.getElementById('quizWarningToast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'quizWarningToast';
            toast.className = 'qz-toast';
            document.body.appendChild(toast);
        }

        toast.classList.remove('fail');
        if (type === 'fail') {
            toast.classList.add('fail');
        }

        toast.textContent = message;
        toast.classList.add('show');

        if (quizToastTimer) {
            clearTimeout(quizToastTimer);
        }
        quizToastTimer = setTimeout(function () {
            toast.classList.remove('show');
        }, type === 'fail' ? 4200 : 3200);
    }

    $(document).ready(function () {
        $(document).on('click', '.mod-item', function (e) {
            // If click originated from inside the tree (subparts/lessons), ignore parent handler
            if ($(e.target).closest('.mod-tree-list').length) {
                return;
            }

            const moduleId = $(this).data('module-id');
            const mod = modules.find(m => m.id == moduleId);

            if ($(this).data('locked') == '1') {
                const av = availabilityMap[moduleId] || {};
                let lockMsg = 'Complete the previous module to unlock this one.';
                if (av.is_locked_by_assessment || hasActiveAssessment) {
                    lockMsg = 'Lecture modules and learning materials are temporarily locked while you are taking a Formal Assessment. Complete or submit your assessment to unlock your lessons.';
                } else if (av.is_upcoming) {
                    lockMsg = `This module will be available on ${av.available_at || 'the scheduled date'}.`;
                } else if (av.is_closed) {
                    lockMsg = 'This assessment is past its due date and is no longer accessible.';
                }
                $('#modContent').html(`
                    <div class="mod-placeholder">
                        <i class="fas fa-lock" style="font-size:2.5rem;color:#f59e0b;"></i>
                        <h3 style="margin-top:16px;color:#111;font-size:18px;font-weight:600;">Module Locked</h3>
                        <p style="margin-top:8px;color:#666;max-width:440px;line-height:1.5;">${lockMsg}</p>
                    </div>
                `);
                return;
            }

            // If this lecture module has subparts/domains, clicking it toggles the collapsible accordion
            if (mod && mod.is_lecture && mod.subparts && mod.subparts.length > 0) {
                $(this).toggleClass('expanded');
                return;
            }

            $('.mod-item').removeClass('active');
            $(this).addClass('active');
            loadModule(moduleId, $(this).data('is-quiz') == '1');
        });

        $('#modSearch').on('input', function () {
            const q = $(this).val().toLowerCase();
            $('.mod-item').each(function () {
                $(this).toggle($(this).find('.mod-item-title').text().toLowerCase().includes(q));
            });
        });

        $('#prevModule').on('click', function () { navigateModule(-1); });
        $('#nextModule').on('click', function () { navigateModule(1); });

        selectInitialModule();
    });

    function selectInitialModule() {
        if (modules.length === 0) {
            return;
        }

        const params = new URLSearchParams(window.location.search);
        const focus = params.get('focus');

        let initialModule = null;

        if (focus === 'lecture') {
            initialModule = modules.find(module => !module.is_quiz && !lockedModules[module.id]);
            if (!initialModule) {
                return;
            }
        }

        if (!initialModule) {
            initialModule = modules.find(module => !lockedModules[module.id]);
        }

        if (!initialModule) {
            return;
        }

        const initialItem = document.querySelector('.mod-item[data-module-id="' + initialModule.id + '"]');
        if (initialItem) {
            initialItem.click();
        }
    }

    function navigateModule(dir) {
        const items  = $('.mod-item').toArray();
        const active = items.findIndex(el => $(el).hasClass('active'));
        const next   = active + dir;
        if (next >= 0 && next < items.length) $(items[next]).trigger('click');
    }

    function isQuizModule(mod) {
        if (!mod) return false;
        return Boolean(
            mod.is_quiz ||
            mod.is_formal_assessment ||
            mod.quiz_stage === 'pre_test' ||
            mod.quiz_stage === 'post_test' ||
            mod.assessment_purpose === 'pre_test' ||
            mod.assessment_purpose === 'post_test' ||
            /pre[- ]?(test|assessment)|post[- ]?(test|assessment)|final assessment/i.test(mod.title || '')
        );
    }

    /**
     * A module is "lecture-style" when it isn't a standalone quiz AND it has
     * sub-parts and/or a pre-test/post-test configured. Plain document
     * modules (old-style, just a file_path, no subparts/pre/post-test) and
     * standalone quiz modules (is_quiz = true) keep their original,
     * untouched code paths below — only lecture-style modules get the new
     * pre-test → content → post-test tab flow.
     */
    function isLectureModule(mod, isQuiz) {
        return !isQuiz && Boolean(mod && mod.is_lecture && mod.subparts && mod.subparts.length > 0);
    }

    function loadModule(moduleId, isQuiz) {
        stopProgressTracking();
        stopSubpartProgressTracking();

        currentModuleId = moduleId;
        const mod = modules.find(m => m.id == moduleId);
        if (!mod) return;

        const effectiveIsQuiz = Boolean(isQuiz || isQuizModule(mod));

        if (isLectureModule(mod, effectiveIsQuiz)) {
            loadLectureModule(mod);
            return;
        }

        if (effectiveIsQuiz) {
            quizRenderTarget = '#modContent';
            currentQuizStage = mod.quiz_stage || null;
            quizBackHandler = backToModuleList;

            const isPre = (mod.quiz_stage === 'pre_test')
                || (mod.assessment_purpose === 'pre_test' || mod.assessment_purpose === 'pre_assessment')
                || /pre[- ]?(test|assessment)/i.test(mod.title || '');
            const isPost = (mod.quiz_stage === 'post_test')
                || (mod.assessment_purpose === 'post_test' || mod.assessment_purpose === 'post_assessment')
                || /post[- ]?(test|assessment)|final assessment/i.test(mod.title || '');

            const existingAttempt = quizAttempts[quizAttemptKey(moduleId, currentQuizStage)]
                || quizAttempts[moduleId]
                || quizAttempts[quizAttemptKey(moduleId, 'pre_test')]
                || quizAttempts[quizAttemptKey(moduleId, 'post_test')];

            if (existingAttempt) {
                // Already completed - show locked result directly, no retake.
                showResult(
                    existingAttempt.percentage,
                    existingAttempt.score,
                    existingAttempt.total,
                    true,
                    existingAttempt.attempt_count,
                    null
                );
                // Always re-check AI availability per class to avoid stale cached insights when feature is disabled.
                getAI(moduleId, existingAttempt.attempt_id);
            } else {
                let subText = 'Complete this pre-test to evaluate your knowledge and understanding.';
                let btnText = 'Start Pre-Test';
                if (isPost) {
                    subText = 'Complete this post-test to evaluate your comprehensive mastery of the course material.';
                    btnText = 'Start Post-Test';
                } else if (mod.is_formal_assessment) {
                    subText = 'Complete this formal assessment to record your grade.';
                    btnText = 'Start Formal Assessment';
                }

                $('#modContent').html(`
                    <div class="qi-wrap">
                        <div class="qi-icon"><i class="fas fa-clipboard-list"></i></div>
                        <h2 class="qi-title">${mod.title}</h2>
                        <p class="qi-sub">${subText}</p>
                        <div class="qi-rules">
                            <p class="qi-rules-title">Instructions</p>
                            <div class="qi-rule"><div class="qi-rule-dot"></div><span>A score of 70% or higher is required to pass.</span></div>
                            <div class="qi-rule"><div class="qi-rule-dot"></div><span>${getAttemptRuleText(moduleId)}</span></div>
                            <div class="qi-rule"><div class="qi-rule-dot"></div><span>Do not switch tabs.</span></div>
                            <div class="qi-rule"><div class="qi-rule-dot"></div><span>Answer all questions before submitting.</span></div>
                        </div>
                        <button class="qi-start-btn" onclick="startQuiz(${moduleId})">
                            <i class="fas fa-play"></i> ${btnText}
                        </button>
                            <button class="qz-btn qz-btn-outline" style="margin-left:8px;" onclick="backToModuleList()">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                    </div>
                `);
            }
        } else {
            // Document module (PDF or video)
            const isVideo = mod.file_type === 'mov';
            $('#modContent').html(`
                <h2 class="mod-doc-heading">${mod.title}</h2>
                <p class="mod-doc-desc">${mod.description || 'No description provided.'}</p>
                ${mod.file_path ? `
                    <div class="mod-pdf-wrap">
                        ${isVideo
                            ? `<video controls style="width:100%;height:100%;background:#000;">
                                 <source src="/modules/${mod.id}/view" type="video/quicktime">
                                 Your browser does not support the video tag.
                               </video>`
                            : `<iframe id="pdfViewerFrame" src="/modules/${mod.id}/pdfjs" width="100%" height="100%" allowfullscreen></iframe>`
                        }
                    </div>
                ` : '<p style="color:#64748b;font-size:16px;font-family:\'DM Sans\',sans-serif;">No file attached.</p>'}
            `);

            startProgressTracking(moduleId);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // Lecture flow: pre-test → content (sub-parts) → post-test
    // ═══════════════════════════════════════════════════════════════

    function loadLectureModule(mod) {
        // Default landing tab only — navigation between tabs afterward is
        // completely free, no gating. Land on the pre-test if it exists and
        // hasn't been attempted yet, otherwise land on content, otherwise
        // the post-test.
        const preAttempt = quizAttempts[quizAttemptKey(mod.id, 'pre_test')];

        if (mod.has_pre_test && !preAttempt) {
            currentLectureStage = 'pre_test';
        } else if (mod.subparts && mod.subparts.length > 0) {
            currentLectureStage = 'content';
        } else if (mod.has_post_test) {
            currentLectureStage = 'post_test';
        } else {
            currentLectureStage = 'content';
        }

        currentSubpartIndex = 0;
        renderLectureShell(mod);
    }

    function renderLectureShell(mod) {
        const tabs = [];
        if (mod.has_pre_test) tabs.push({ key: 'pre_test', label: 'Pre-Test' });
        if (mod.has_post_test) tabs.push({ key: 'post_test', label: 'Post-Test' });

        const tabsHtml = tabs.map(t => {
            const attempt = t.key !== 'content' ? quizAttempts[quizAttemptKey(mod.id, t.key)] : null;
            const check = attempt ? '<i class="fas fa-check-circle lec-tab-check"></i>' : '';
            return `
                <button class="lec-tab ${currentLectureStage === t.key ? 'active' : ''}" onclick="switchLectureStage(${mod.id}, '${t.key}')">
                    ${t.label} ${check}
                </button>
            `;
        }).join('');

        $('#modContent').html(`
            <div class="lec-header">
                <h2 class="mod-doc-heading" style="margin-bottom:14px;">${mod.title}</h2>
                <div class="lec-tabs">${tabsHtml}</div>
            </div>
            <div id="lecStageArea"></div>
        `);

        renderLectureStage(mod);
    }

    function switchLectureStage(moduleId, stage) {
        const mod = modules.find(m => m.id == moduleId);
        if (!mod) return;

        stopProgressTracking();
        stopSubpartProgressTracking();
        currentLectureStage = stage;
        renderLectureShell(mod);
    }

    function renderLectureStage(mod) {
        if (currentLectureStage === 'pre_test' || currentLectureStage === 'post_test') {
            renderLectureQuizStage(mod, currentLectureStage);
        } else {
            renderLectureContentStage(mod);
        }
    }

    function renderLectureQuizStage(mod, stage) {
        quizRenderTarget = '#lecStageArea';
        currentQuizStage = stage;
        quizBackHandler = function () { renderLectureShell(mod); };

        const key = quizAttemptKey(mod.id, stage);
        const existingAttempt = quizAttempts[key];
        const label = stage === 'pre_test' ? 'Pre-Test' : 'Post-Test';

        if (existingAttempt) {
            showResult(
                existingAttempt.percentage,
                existingAttempt.score,
                existingAttempt.total,
                true,
                existingAttempt.attempt_count,
                null
            );
            getAI(mod.id, existingAttempt.attempt_id);
            return;
        }

        $('#lecStageArea').html(`
            <div class="qi-wrap">
                <div class="qi-icon"><i class="fas fa-clipboard-list"></i></div>
                <h2 class="qi-title">${label}</h2>
                <p class="qi-sub">Complete this ${label.toLowerCase()} to continue.</p>
                <div class="qi-rules">
                    <p class="qi-rules-title">Instructions</p>
                    <div class="qi-rule"><div class="qi-rule-dot"></div><span>A score of 70% or higher is required to pass.</span></div>
                    <div class="qi-rule"><div class="qi-rule-dot"></div><span>${getAttemptRuleText(mod.id)}</span></div>
                    <div class="qi-rule"><div class="qi-rule-dot"></div><span>Do not switch tabs.</span></div>
                    <div class="qi-rule"><div class="qi-rule-dot"></div><span>Answer all questions before submitting.</span></div>
                </div>
                <button class="qi-start-btn" onclick="startLectureQuiz(${mod.id}, '${stage}')">
                    <i class="fas fa-play"></i> Start ${label}
                </button>
            </div>
        `);
    }

    function startLectureQuiz(moduleId, stage) {
        quizRenderTarget = '#lecStageArea';
        currentQuizStage = stage;
        const mod = modules.find(m => m.id == moduleId);
        quizBackHandler = function () { renderLectureShell(mod); };
        beginQuizFlow(moduleId);
    }

    function renderLectureContentStage(mod) {
        const subparts = mod.subparts || [];

        if (subparts.length === 0) {
            $('#lecStageArea').html(`
                <div class="sp-empty">
                    <i class="fas fa-inbox" style="font-size:28px;opacity:.3;"></i>
                    <p>No content added yet.</p>
                </div>
            `);
            return;
        }

        const listHtml = subparts.map((sp, idx) => `
            <div class="sp-item ${idx === currentSubpartIndex ? 'active' : ''} ${sp.student_completed ? 'completed' : ''}"
                 data-subpart-index="${idx}" onclick="loadSubpart(${mod.id}, ${idx})">
                <div class="sp-item-title">
                    ${idx + 1}. ${sp.title}
                    ${sp.student_completed ? '<i class="fas fa-check-circle" style="font-size:11px;"></i>' : ''}
                </div>
                <div class="sp-item-bar-track"><div class="sp-item-bar-fill" style="width:${sp.student_progress || 0}%"></div></div>
            </div>
        `).join('');

        $('#lecStageArea').html(`
            <div class="sp-viewer" id="spViewer"></div>
        `);

        if (currentSubpartIndex >= subparts.length) {
            currentSubpartIndex = 0;
        }

        renderSubpartViewer(mod, currentSubpartIndex);
    }

    function loadSubpart(moduleId, index) {
        stopSubpartProgressTracking();
        currentSubpartIndex = index;
        currentLessonIndex = 0;

        const mod = modules.find(m => m.id == moduleId);
        $('.sp-item').removeClass('active');
        $(`.sp-item[data-subpart-index="${index}"]`).addClass('active');

        renderSubpartViewer(mod, index);
    }

    function renderSubpartViewer(mod, index) {
        const sp = (mod.subparts || [])[index];
        if (!sp) return;

        if (sp.lessons && sp.lessons.length > 0) {
            renderLessonList(mod, sp);
            return;
        }

        const isVideo = sp.file_type === 'mov';
        const isDocx = sp.file_type === 'docx';

        $('#spViewer').html(`
            <h3 class="mod-doc-heading" style="font-size:22px;">${sp.title}</h3>
            ${sp.description ? `<p class="mod-doc-desc">${sp.description}</p>` : ''}
            ${sp.body ? `<div class="sp-body">${sp.body}</div>` : ''}
            ${sp.file_path ? `
                <div class="mod-pdf-wrap">
                    ${isVideo
                        ? `<video controls style="width:100%;height:100%;background:#000;">
                             <source src="/subparts/${sp.id}/view" type="video/quicktime">
                             Your browser does not support the video tag.
                           </video>`
                        : `<iframe src="/subparts/${sp.id}/${isDocx ? 'docxjs' : 'pdfjs'}" width="100%" height="100%" allowfullscreen></iframe>`
                    }
                </div>
            ` : (!sp.body ? '<p style="color:#64748b;">No content attached.</p>' : '')}
        `);

        startSubpartProgressTracking(sp);
    }

    function renderLessonList(mod, sp) {
        const lessons = sp.lessons || [];
        const listHtml = lessons.map((lesson, index) => `
            <button class="sp-item lesson-item ${lesson.student_completed ? 'completed' : ''}"
                    type="button" onclick="loadLesson(${mod.id}, ${sp.id}, ${index})">
                <div class="sp-item-title">
                    ${index + 1}. ${lesson.title}
                    ${lesson.student_completed ? '<i class="fas fa-check-circle" style="font-size:11px;"></i>' : ''}
                </div>
                <div class="sp-item-bar-track"><div class="sp-item-bar-fill" style="width:${lesson.student_progress || 0}%"></div></div>
            </button>
        `).join('');

        $('#spViewer').html(`
            <h3 class="mod-doc-heading" style="font-size:22px;">${sp.title}</h3>
            ${sp.description ? `<p class="mod-doc-desc">${sp.description}</p>` : ''}
            <div class="sp-list lesson-list">${listHtml}</div>
        `);
    }

    function loadLesson(moduleId, subpartId, index) {
        stopSubpartProgressTracking();
        const mod = modules.find(m => m.id == moduleId);
        const sp = (mod?.subparts || []).find(item => item.id == subpartId);
        const lesson = (sp?.lessons || [])[index];
        if (!sp || !lesson) return;

        currentLessonIndex = index;
        renderLessonViewer(mod, sp, lesson);
    }

    function renderLessonViewer(mod, sp, lesson) {
        const isVideo = lesson.file_type === 'mov';
        const isDocx = lesson.file_type === 'docx';
        $('#spViewer').html(`
            <button class="mod-nav-btn" type="button" onclick="renderLessonList(modules.find(m => m.id == ${mod.id}), modules.find(m => m.id == ${mod.id}).subparts.find(s => s.id == ${sp.id}))">
                <i class="fas fa-arrow-left"></i> Back to ${sp.title}
            </button>
            <h3 class="mod-doc-heading" style="font-size:22px;margin-top:18px;">${lesson.title}</h3>
            ${lesson.description ? `<p class="mod-doc-desc">${lesson.description}</p>` : ''}
            ${lesson.body ? `<div class="sp-body">${lesson.body}</div>` : ''}
            ${lesson.file_path ? `
                <div class="mod-pdf-wrap">
                    ${isVideo
                        ? `<video controls style="width:100%;height:100%;background:#000;">
                             <source src="/storage/${lesson.file_path}" type="video/quicktime">
                             Your browser does not support the video tag.
                           </video>`
                        : `<iframe src="${isDocx ? `/lessons/${lesson.id}/docxjs` : `/storage/${lesson.file_path}`}" width="100%" height="100%" allowfullscreen></iframe>`
                    }
                </div>
            ` : (!lesson.body ? '<p style="color:#64748b;">No content attached.</p>' : '')}
        `);

        startLessonProgressTracking(lesson, sp);
    }

    function startLessonProgressTracking(lesson, sp) {
        const $viewer = $('#spViewer');
        if (!$viewer.length) return;

        const current = Number(lesson.student_progress || 0);
        if (current < 10) {
            persistLessonProgress(lesson, sp, 10, false);
        }

        const videoElement = $viewer.find('video').get(0);
        if (videoElement) {
            $(videoElement).on('loadedmetadata.lessonProgress timeupdate.lessonProgress ended.lessonProgress', function (event) {
                if (event.type === 'ended') {
                    persistLessonProgress(lesson, sp, 100, true);
                    return;
                }

                if (videoElement.duration && Number.isFinite(videoElement.duration)) {
                    persistLessonProgress(lesson, sp, Math.round((videoElement.currentTime / videoElement.duration) * 100), false);
                }
            });
            return;
        }

        $viewer.on('scroll.lessonProgress', function () {
            const maxScrollable = Math.max(1, this.scrollHeight - this.clientHeight);
            const progress = Math.round(Math.max(0, Math.min(1, this.scrollTop / maxScrollable)) * 100);
            persistLessonProgress(lesson, sp, progress, progress >= 100);
        });
    }

    function persistLessonProgress(lesson, sp, progressValue, completed) {
        const current = Number(lesson.student_progress || 0);
        const normalized = Math.max(current, Math.min(100, Math.round(progressValue)));
        if (normalized <= current && !(completed && !lesson.student_completed)) return;

        lesson.student_progress = normalized;
        if (completed) lesson.student_completed = true;
        updateLessonUI(lesson);
        syncClientSubpartProgress(sp);

        $.post(`/lessons/${lesson.id}/progress`, {
            _token: '{{ csrf_token() }}',
            progress: normalized,
            completed: completed ? 1 : 0
        }).fail(function () {
            lesson.student_progress = current;
            updateLessonUI(lesson);
            syncClientSubpartProgress(sp);
            updateSubpartUI(sp);
        });

        updateSubpartUI(sp);
    }

    function startSubpartProgressTracking(sp) {
        const $viewer = $('#spViewer');
        if (!$viewer.length) return;

        if (Number(sp.student_progress || 0) < 10) {
            persistSubpartProgress(sp, 10, false);
        }

        const videoElement = $viewer.find('video').get(0);
        if (videoElement) {
            const syncVideoProgress = function () {
                if (!videoElement.duration || !Number.isFinite(videoElement.duration)) {
                    return;
                }

                const computedProgress = Math.round((videoElement.currentTime / videoElement.duration) * 100);
                queueSubpartProgressSave(sp, computedProgress);
            };

            $(videoElement)
                .off('.spVideoProgress')
                .on('loadedmetadata.spVideoProgress timeupdate.spVideoProgress seeked.spVideoProgress ended.spVideoProgress', function (event) {
                    if (event.type === 'ended') {
                        queueSubpartProgressSave(sp, 100);
                        return;
                    }

                    syncVideoProgress();
                });

            syncVideoProgress();
            return;
        }

        $viewer.off('scroll.spProgress').on('scroll.spProgress', function () {
            const el = this;
            const maxScrollable = Math.max(1, el.scrollHeight - el.clientHeight);
            const ratio = Math.max(0, Math.min(1, el.scrollTop / maxScrollable));
            queueSubpartProgressSave(sp, Math.round(ratio * 100));
        });
    }

    // Receive scroll-progress messages from a subpart's pdfjs-viewer iframe
    window.addEventListener('message', function (event) {
        if (event.origin !== window.location.origin) return;
        if (!event.data || event.data.type !== 'pdf-scroll-progress') return;

        const mod = modules.find(m => m.id == currentModuleId);
        const sp = (mod?.subparts || []).find(s => s.id == event.data.moduleId);
        if (sp) {
            queueSubpartProgressSave(sp, event.data.progress);
        }
    });

    function stopSubpartProgressTracking() {
        $('#spViewer').off('scroll.spProgress');
        $('#spViewer').find('video').off('.spVideoProgress');

        if (subpartProgressSaveTimer) {
            clearTimeout(subpartProgressSaveTimer);
            subpartProgressSaveTimer = null;
        }
    }

    function queueSubpartProgressSave(sp, candidateProgress) {
        if (subpartProgressSaveTimer) {
            clearTimeout(subpartProgressSaveTimer);
        }

        subpartProgressSaveTimer = setTimeout(function () {
            const current = Number(sp.student_progress || 0);
            const next = Math.max(current, Math.min(100, candidateProgress));

            if (next > current) {
                persistSubpartProgress(sp, next, next >= 100);
            }
        }, 350);
    }

    function persistSubpartProgress(sp, progressValue, completed) {
        const current = Number(sp.student_progress || 0);
        const normalized = Math.max(current, Math.min(100, Math.round(progressValue)));

        if (normalized <= current) {
            return;
        }

        sp.student_progress = normalized;
        if (completed) {
            sp.student_completed = true;
        }
        updateSubpartUI(sp);

        $.post(`/subparts/${sp.id}/progress`, {
            _token: '{{ csrf_token() }}',
            progress: normalized,
            completed: completed ? 1 : 0
        }).fail(function () {
            sp.student_progress = current;
            updateSubpartUI(sp);
        });
    }

    function updateSubpartUI(sp) {
        const mod = modules.find(m => m.id == currentModuleId);
        if (!mod || !mod.subparts) return;

        const idx = mod.subparts.findIndex(s => s.id === sp.id);
        if (idx === -1) return;

        const bar = document.querySelector(`[data-subpart-progress-bar="${sp.id}"]`);
        if (bar) {
            bar.style.width = `${sp.student_progress}%`;
        }
        const label = document.querySelector(`[data-subpart-progress-label="${sp.id}"]`);
        if (label) {
            label.textContent = `${Math.round(Number(sp.student_progress || 0))}%`;
        }
        if (sp.student_completed) {
            $(`.sp-item[data-subpart-index="${idx}"]`).addClass('completed');
        }

        // Mirror ModuleSubpartController::syncModuleProgress() client-side —
        // this module's whole-module progress (sidebar bar/badge) is the
        // average of its subparts' progress, recomputed immediately instead
        // of waiting for a reload.
        const avg = Math.round(
            mod.subparts.reduce((sum, s) => sum + Number(s.student_progress || 0), 0) / mod.subparts.length
        );
        moduleProgressMap[currentModuleId] = avg;
        updateProgressUI(currentModuleId, avg);
    }

    function updateLessonUI(lesson) {
        const progress = Math.round(Number(lesson.student_progress || 0));
        const bar = document.querySelector(`[data-lesson-progress-bar="${lesson.id}"]`);
        if (bar) {
            bar.style.width = `${progress}%`;
        }

        const label = document.querySelector(`[data-lesson-progress-label="${lesson.id}"]`);
        if (label) {
            label.textContent = `${progress}%`;
        }

        if (lesson.student_completed) {
            $(`.mod-tree-node.lesson[data-lesson-id="${lesson.id}"]`).addClass('completed');
        }
    }

    function syncClientSubpartProgress(sp) {
        const lessons = sp.lessons || [];
        if (lessons.length === 0) return;

        sp.student_progress = Math.round(
            lessons.reduce((sum, lesson) => sum + Number(lesson.student_progress || 0), 0) / lessons.length
        );
        sp.student_completed = lessons.every(lesson => lesson.student_completed);
        updateSubpartUI(sp);
    }

    // ═══════════════════════════════════════════════════════════════
    // Generic quiz-taking core — used by BOTH standalone quiz modules
    // (currentQuizStage = null) and lecture pre-test/post-test stages
    // (currentQuizStage = 'pre_test' | 'post_test'). Renders into
    // quizRenderTarget, "Back" goes through quizBackHandler().
    // ═══════════════════════════════════════════════════════════════

    function startProgressTracking(moduleId) {
        const $content = $('#modContent');
        if (!$content.length) {
            return;
        }

        const current = Number(moduleProgressMap[moduleId] || 0);
        if (current < 10) {
            persistProgress(moduleId, 10, false);
        }

        const videoElement = $content.find('video').get(0);
        if (videoElement) {
            const syncVideoProgress = function () {
                if (!videoElement.duration || !Number.isFinite(videoElement.duration)) {
                    return;
                }

                const computedProgress = Math.round((videoElement.currentTime / videoElement.duration) * 100);
                queueProgressSave(moduleId, computedProgress);
            };

            $(videoElement)
                .off('.videoProgress')
                .on('loadedmetadata.videoProgress timeupdate.videoProgress seeked.videoProgress ended.videoProgress', function (event) {
                    if (event.type === 'ended') {
                        queueProgressSave(moduleId, 100);
                        return;
                    }

                    syncVideoProgress();
                });

            syncVideoProgress();
            return;
        }

        // Outer container scroll (for non-iframe content)
        $content.off('scroll.moduleProgress').on('scroll.moduleProgress', function () {
            const el = this;
            const maxScrollable = Math.max(1, el.scrollHeight - el.clientHeight);
            const ratio = Math.max(0, Math.min(1, el.scrollTop / maxScrollable));
            const computedProgress = Math.round(ratio * 100);

            queueProgressSave(moduleId, computedProgress);
        });
    }

    // Receive scroll-progress messages from the pdfjs-viewer iframe
    window.addEventListener('message', function (event) {
        if (event.origin !== window.location.origin) return;
        if (!event.data || event.data.type !== 'pdf-scroll-progress') return;
        if (event.data.moduleId !== currentModuleId) return;

        queueProgressSave(event.data.moduleId, event.data.progress);
    });

    function stopProgressTracking() {
        $('#modContent').off('scroll.moduleProgress');
        $('#modContent').find('video').off('.videoProgress');

        if (progressSaveTimer) {
            clearTimeout(progressSaveTimer);
            progressSaveTimer = null;
        }
    }

    function queueProgressSave(moduleId, candidateProgress) {
        if (progressSaveTimer) {
            clearTimeout(progressSaveTimer);
        }

        progressSaveTimer = setTimeout(function () {
            const current = Number(moduleProgressMap[moduleId] || 0);
            const next = Math.max(current, Math.min(100, candidateProgress));

            if (next > current) {
                persistProgress(moduleId, next, next >= 100);
            }
        }, 350);
    }

    function persistProgress(moduleId, progressValue, completed) {
        const current = Number(moduleProgressMap[moduleId] || 0);
        const normalized = Math.max(current, Math.min(100, Math.round(progressValue)));

        if (normalized <= current) {
            return;
        }

        moduleProgressMap[moduleId] = normalized;
        updateProgressUI(moduleId, normalized);

        $.post(`/modules/${moduleId}/progress`, {
            _token: '{{ csrf_token() }}',
            progress: normalized,
            completed: completed ? 1 : 0
        }).fail(function () {
            // Revert local optimistic update when persistence fails.
            moduleProgressMap[moduleId] = current;
            updateProgressUI(moduleId, current);
        });
    }

    function updateProgressUI(moduleId, progressValue) {
        const pct = Math.max(0, Math.min(100, Math.round(progressValue)));
        const bar = document.querySelector(`.module-progress-bar[data-module-id="${moduleId}"]`);
        if (bar) {
            bar.style.width = `${pct}%`;
        }

        const badge = document.querySelector(`.module-progress-badge[data-module-id="${moduleId}"]`);
        if (badge) {
            badge.textContent = `${pct}%`;
        }

        const values = modules
            .filter(m => !m.is_quiz)
            .map(m => Number(moduleProgressMap[m.id] || 0));

        const overall = values.length
            ? Math.round(values.reduce((sum, val) => sum + val, 0) / values.length)
            : 0;

        $('#overallProgress').text(`${overall}%`);
        $('#overallProgressBar').css('width', `${overall}%`);
    }

    function startQuiz(moduleId) {
        quizRenderTarget = '#modContent';
        const mod = modules.find(module => module.id == moduleId);
        currentQuizStage = mod?.quiz_stage || null;
        quizBackHandler = backToModuleList;
        beginQuizFlow(moduleId);
    }

    function beginQuizFlow(moduleId) {
        const mod = modules.find(m => m.id == moduleId);
        isFormalAssessment = Boolean(mod?.is_formal_assessment || mod?.quiz_stage || mod?.assessment_purpose);

        const startPayload = { _token: '{{ csrf_token() }}' };
        if (currentQuizStage) {
            startPayload.quiz_stage = currentQuizStage;
        }

        // Kailangan munang i-check/i-record ang pagsisimula ng attempt bago
        // magpakita ng tanong — dito i-e-enforce ang max_attempts + grants
        // para sa formal assessments (Pre-Test, Post-Test, Mock Board).
        $.post(`/modules/${moduleId}/quiz/start`, startPayload)
            .done(function (startRes) {
                renderQuizShell(moduleId, mod);

                if (isFormalAssessment) {
                    broadcastAssessmentEvent('assessment_started');
                    startAntiCheat();
                    hasActiveAssessment = true;

                    let label = 'Formal Assessment';
                    if (mod?.mock_board_id) {
                        label = 'Mock Board Exam';
                    } else if (mod?.is_formal_assessment) {
                        label = 'Formal Assessment';
                    } else if (mod?.quiz_stage === 'pre_test' || mod?.assessment_purpose === 'pre_test' || /pre[- ]?(test|assessment)/i.test(mod?.title || '')) {
                        label = 'Pre-Test';
                    } else if (mod?.quiz_stage === 'post_test' || mod?.assessment_purpose === 'post_test' || /post[- ]?(test|assessment)|final assessment/i.test(mod?.title || '')) {
                        label = 'Post-Test';
                    } else {
                        label = 'Assessment';
                    }

                    let banner = document.getElementById('assessmentLockBanner');
                    if (!banner) {
                        const bannerHtml = `
                            <div id="assessmentLockBanner" style="background: #fffbeb; border-bottom: 1px solid #fef3c7; padding: 10px 18px; display: flex; align-items: center; gap: 10px; color: #92400e; font-size: 13.5px; font-family: 'DM Sans', sans-serif;">
                                <i class="fas fa-lock" style="color: #d97706; font-size: 14px;"></i>
                                <span><strong id="assessmentBannerTitle">${label} in Progress:</strong> Lecture modules are temporarily locked while you are taking an assessment. Submit your assessment to unlock your lessons.</span>
                            </div>
                        `;
                        $('.mod-main').prepend(bannerHtml);
                    } else {
                        $('#assessmentBannerTitle').text(`${label} in Progress:`);
                        $(banner).show();
                    }

                    modules.forEach(m => {
                        if (!m.is_formal_assessment && !m.is_quiz && !m.quiz_stage) {
                            lockedModules[m.id] = true;
                            const el = document.querySelector(`.mod-item[data-module-id="${m.id}"]`);
                            if (el) {
                                el.classList.add('locked');
                                el.setAttribute('data-locked', '1');
                            }
                        }
                    });
                }

                const questionParams = currentQuizStage ? { quiz_stage: currentQuizStage } : {};

                $.get(`/modules/${moduleId}/quiz/questions`, questionParams, function (res) {
                    if (res.success && res.questions && res.questions.length > 0) {
                        currentQuizQuestions = res.questions;
                        currentQIndex        = 0;
                        answeredQuestions    = new Set();
                        selectedAnswers      = {};
                        renderNav();
                        renderQ();
                        startTimer(parseInt(res.time_limit) || 0);
                    } else {
                        const msg = res?.message || 'No questions found for this assessment.';
                        $(quizRenderTarget).html(`
                            <div class="mod-placeholder">
                                <i class="fas fa-exclamation-triangle" style="font-size:2rem;color:#f59e0b;"></i>
                                <p style="margin-top:12px;color:#444;max-width:420px;">${msg}</p>
                                <button class="qz-btn qz-btn-outline" style="margin-top:12px;" onclick="quizBackHandler()">
                                    <i class="fas fa-arrow-left"></i> Back
                                </button>
                            </div>
                        `);
                    }
                }).fail(function (xhr) {
                    const message = xhr?.responseJSON?.message || 'Failed to load questions for this assessment.';
                    $(quizRenderTarget).html(`
                        <div class="mod-placeholder">
                            <i class="fas fa-exclamation-triangle" style="font-size:2rem;color:#e24b4a;"></i>
                            <p style="margin-top:12px;color:#444;max-width:420px;">${message}</p>
                            <button class="qz-btn qz-btn-outline" style="margin-top:12px;" onclick="quizBackHandler()">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                        </div>
                    `);
                });
            })
            .fail(function (xhr) {
                const message = xhr?.responseJSON?.message || 'You cannot start a new attempt right now.';
                $(quizRenderTarget).html(`
                    <div class="mod-placeholder">
                        <i class="fas fa-lock" style="font-size:2rem;color:#e24b4a;"></i>
                        <p style="margin-top:12px;color:#444;max-width:420px;">${message}</p>
                        <button class="qz-btn qz-btn-outline" style="margin-top:12px;" onclick="quizBackHandler()">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                    </div>
                `);
            });
    }

    function renderQuizShell(moduleId, mod) {
        $(quizRenderTarget).html(`
            <div class="qz-wrap">
                <div class="qz-header">
                    <p class="qz-title">${mod?.title ?? 'Quiz'}</p>
                    <div class="qz-timer" id="quizTimer">
                        <i class="fas fa-clock" style="font-size: 14px;opacity:0.7;"></i> Loading...
                    </div>
                </div>
                <div class="qz-nav" id="quizNav"></div>
                <div id="quizArea"></div>
                <div class="qz-actions">
                    <span class="qz-progress">Question <span id="qNum">-</span> of <span id="qTotal">-</span></span>
                    <div style="display:flex;gap:6px;">
                        <button class="qz-btn qz-btn-outline" onclick="prevQ()"><i class="fas fa-chevron-left"></i> Prev</button>
                        <button class="qz-btn qz-btn-dark" id="nextBtn" onclick="nextQ()">Next <i class="fas fa-chevron-right"></i></button>
                    </div>
                </div>
            </div>
        `);
    }

    function renderNav() {
        const total = currentQuizQuestions.length;
        let html = '';
        for (let i = 0; i < total; i++) {
            const a = answeredQuestions.has(i), c = i === currentQIndex;
            html += `<button class="qz-pill ${a ? 'answered' : ''} ${c ? 'current' : ''}" onclick="jumpTo(${i})">${i + 1}</button>`;
        }
        $('#quizNav').html(html);
        $('#qTotal').text(total);
    }

    function jumpTo(index) { currentQIndex = index; renderQ(); renderNav(); }

    function renderQ() {
        const q      = currentQuizQuestions[currentQIndex];
        const saved  = selectedAnswers[q.id];
        const isLast = currentQIndex === currentQuizQuestions.length - 1;

        $('#qNum').text(currentQIndex + 1);

        const opts = Object.keys(q.options).map(key => `
            <label class="qz-option ${saved === key ? 'selected' : ''}">
                <input type="radio" name="choice_${q.id}" value="${key}"
                       ${saved === key ? 'checked' : ''}
                       onchange="selectOpt('${q.id}', '${key}', this.closest('.qz-option'))">
                <span class="qz-option-key">${key}</span>
                <span>${q.options[key]}</span>
            </label>
        `).join('');

        $('#quizArea').html(`
            <div class="qz-q-card">
                <p class="qz-q-num">Question ${currentQIndex + 1}</p>
                <p class="qz-q-text">${q.question_text}</p>
                <div id="optionsList">${opts}</div>
            </div>
        `);

        if (isLast) {
            $('#nextBtn').html('Submit Quiz <i class="fas fa-check"></i>').removeClass('qz-btn-dark').addClass('qz-btn-success');
        } else {
            $('#nextBtn').html('Next <i class="fas fa-chevron-right"></i>').removeClass('qz-btn-success').addClass('qz-btn-dark');
        }
    }

    function selectOpt(qId, key, el) {
        $(el).closest('#optionsList').find('.qz-option').removeClass('selected');
        $(el).closest('#optionsList').find('.qz-option-key').css({ background: '#f3f3f3', color: '#555' });
        $(el).addClass('selected');
        $('.qz-option-key', el).css({ background: '#3b82f6', color: '#fff' });
        selectedAnswers[qId] = key;
    }

    function nextQ() {
        const q = currentQuizQuestions[currentQIndex];
        if (selectedAnswers[q.id]) answeredQuestions.add(currentQIndex);
        if (currentQIndex < currentQuizQuestions.length - 1) {
            currentQIndex++; renderQ(); renderNav();
        } else { submitQuiz(); }
    }

    function prevQ() {
        if (currentQIndex > 0) { currentQIndex--; renderQ(); renderNav(); }
    }

    function submitQuiz(forcedFail = false) {
        clearInterval(quizTimerInterval);
        stopAntiCheat();
        stopProgressTracking();

        const q = currentQuizQuestions[currentQIndex];
        if (selectedAnswers[q?.id]) answeredQuestions.add(currentQIndex);

        let score = 0;
        const total = currentQuizQuestions.length;
        if (!forcedFail) {
            currentQuizQuestions.forEach(q => {
                if (selectedAnswers[q.id]?.toUpperCase() === q.correct?.toUpperCase()) score++;
            });
        }

        const pct = forcedFail ? 0 : Math.round((score / total) * 100);
        const attemptKey = quizAttemptKey(currentModuleId, currentQuizStage);

        let savedAttemptCount = 1;
        let savedAttemptId = null;

        const persistAnswers = saveAnswers(currentModuleId, forcedFail)
            .catch(() => null);

        persistAnswers
            .then(() => saveScore(currentModuleId, score, total, pct))
            .then((res) => {
                if (res && res.attempt_count) { savedAttemptCount = res.attempt_count; }
                if (res && res.attempt_id) { savedAttemptId = res.attempt_id; }
            })
            .then(() => new Promise(resolve => setTimeout(resolve, 300)))
            .then(() => {
                // Record the completed attempt client-side so re-opening the module/stage shows locked result.
                quizAttempts[attemptKey] = {
                    score: score,
                    total: total,
                    percentage: pct,
                    passed: pct >= 50,
                    attempt_count: savedAttemptCount,
                    attempt_id: savedAttemptId,
                };
                if (isFormalAssessment) {
                    broadcastAssessmentEvent('assessment_ended');
                    hasActiveAssessment = false;
                    $('#assessmentLockBanner').remove();
                    modules.forEach(m => {
                        const av = availabilityMap[m.id] || {};
                        if (!av.is_upcoming && !av.is_closed && !av.is_inactive) {
                            delete lockedModules[m.id];
                            const el = document.querySelector(`.mod-item[data-module-id="${m.id}"]`);
                            if (el) {
                                el.classList.remove('locked');
                                el.setAttribute('data-locked', '0');
                            }
                        }
                    });
                }
                showResult(pct, score, total, false, savedAttemptCount, null);
                getAI(currentModuleId, savedAttemptId);
            })
            .catch(() => {
                if (isFormalAssessment) {
                    broadcastAssessmentEvent('assessment_ended');
                    hasActiveAssessment = false;
                    $('#assessmentLockBanner').remove();
                    modules.forEach(m => {
                        const av = availabilityMap[m.id] || {};
                        if (!av.is_upcoming && !av.is_closed && !av.is_inactive) {
                            delete lockedModules[m.id];
                            const el = document.querySelector(`.mod-item[data-module-id="${m.id}"]`);
                            if (el) {
                                el.classList.remove('locked');
                                el.setAttribute('data-locked', '0');
                            }
                        }
                    });
                }
                showResult(pct, score, total, false, savedAttemptCount, null);
                getAI(currentModuleId, savedAttemptId);
            });
    }

    function formatAiInsightHtml(rawText, defaultText = 'None detected') {
        if (!rawText || !rawText.trim()) {
            return `<p style="margin:0;font-style:italic;color:#94a3b8;">${defaultText}</p>`;
        }

        let text = rawText.trim();
        text = text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
        text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        text = text.replace(/([^\n])\s*-\s+/g, "$1\n- ");
        text = text.replace(/([^\n])\s*(\d+\.)\s+/g, "$1\n$2 ");

        const lines = text.split(/\r?\n/).map(l => l.trim()).filter(l => l.length > 0);
        let isOrdered = false;
        let isUnordered = false;
        let listItems = [];
        let html = '';

        for (const line of lines) {
            const bulletMatch = line.match(/^[-*•]\s+(.*)$/);
            const orderedMatch = line.match(/^(\d+)[.)]\s+(.*)$/);

            if (bulletMatch) {
                if (isOrdered) {
                    html += `<ol>${listItems.join('')}</ol>`;
                    listItems = [];
                    isOrdered = false;
                }
                isUnordered = true;
                listItems.push(`<li>${bulletMatch[1]}</li>`);
            } else if (orderedMatch) {
                if (isUnordered) {
                    html += `<ul>${listItems.join('')}</ul>`;
                    listItems = [];
                    isUnordered = false;
                }
                isOrdered = true;
                listItems.push(`<li>${orderedMatch[2]}</li>`);
            } else {
                if (listItems.length > 0) {
                    html += isOrdered ? `<ol>${listItems.join('')}</ol>` : `<ul>${listItems.join('')}</ul>`;
                    listItems = [];
                    isOrdered = false;
                    isUnordered = false;
                }
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
        const recHtml = formatAiInsightHtml(recommendation, 'Review the module summary before retaking.');

        return `
            <div class="qz-ai-header">
                <div class="qz-ai-header-left">
                    <span class="qz-ai-icon-badge"><i class="fas fa-brain"></i></span>
                    <div>
                        <h4 class="qz-ai-title">AI Performance Diagnostic</h4>
                        <p class="qz-ai-subtitle">Personalized feedback based on your quiz responses</p>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <button type="button" class="qz-btn qz-btn-outline" style="font-size:12px;padding:4px 10px;border-radius:6px;display:inline-flex;align-items:center;gap:6px;cursor:pointer;background:#fff;border:1px solid #cbd5e1;color:#334155;font-weight:500;" onclick="refreshCurrentAiInsights(this)">
                        <i class="fas fa-sync-alt"></i> Refresh Insight
                    </button>
                    <span class="qz-ai-badge">Instant Analysis</span>
                </div>
            </div>
            <div class="qz-ai-grid">
                <div class="qz-ai-card qz-ai-card-strong">
                    <div class="qz-ai-card-head">
                        <i class="fas fa-check-circle"></i>
                        <span>Strong Areas</span>
                    </div>
                    <div class="qz-ai-card-body">
                        ${strongHtml}
                    </div>
                </div>
                <div class="qz-ai-card qz-ai-card-weak">
                    <div class="qz-ai-card-head">
                        <i class="fas fa-bullseye"></i>
                        <span>Weak Areas</span>
                    </div>
                    <div class="qz-ai-card-body">
                        ${weakHtml}
                    </div>
                </div>
                <div class="qz-ai-card qz-ai-card-rec">
                    <div class="qz-ai-card-head">
                        <i class="fas fa-lightbulb"></i>
                        <span>Recommendation</span>
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

    function showResult(pct, score, total, isLocked = false, attemptCount = 1, cachedInsights = null) {
        const passed  = pct >= 50;
        const color   = passed ? '#1d9e75' : '#e24b4a';
        const dashArr = 251;
        const dashOff = dashArr - (pct / 100 * dashArr);

        const aiHtml = cachedInsights && (cachedInsights.strong || cachedInsights.weak || cachedInsights.recommendation)
            ? renderAiInsightsCard(cachedInsights.strong, cachedInsights.weak, cachedInsights.recommendation)
            : renderAiInsightsLoading();

        $(quizRenderTarget).html(`
            <div class="qz-result">
                <h2>Quiz Complete</h2>
                <p style="font-size: 14px;color:#999;margin:-8px 0 16px;">Attempt ${attemptCount}</p>
                <div class="qz-gauge-wrap">
                    <svg width="240" height="138" viewBox="0 0 240 138">
                        <path d="M 35 118 A 85 85 0 0 1 205 118" fill="none" stroke="#f3f3f3" stroke-width="16" stroke-linecap="round"/>
                        <path d="M 35 118 A 85 85 0 0 1 205 118" fill="none" stroke="${color}"
                              stroke-width="16" stroke-linecap="round"
                              stroke-dasharray="${dashArr}" stroke-dashoffset="${dashOff}"
                              style="transition:stroke-dashoffset 1s ease;"/>
                    </svg>
                    <div class="qz-gauge-score">${pct}%</div>
                </div>
                <p class="qz-verdict ${passed ? 'pass' : 'fail'}">
                    <i class="fas fa-${passed ? 'check-circle' : 'times-circle'}"></i>
                    ${passed ? 'You passed!' : 'You did not pass.'} &nbsp;${score} / ${total} correct.
                </p>
                <div class="qz-ai-box" id="aiBox">
                    ${aiHtml}
                </div>
                <div class="qz-history-card" id="historyBox">
                    <p class="qz-history-title"><i class="fas fa-history"></i> Attempt History</p>
                    <p class="qz-history-empty">Loading history...</p>
                </div>
                <div class="qz-result-btns">
                    <button class="qz-btn qz-btn-outline" onclick="quizBackHandler()"><i class="fas fa-arrow-left"></i> Back</button>
                </div>
            </div>
        `);

        loadAttemptHistory(currentModuleId, currentQuizStage);
    }

    function loadAttemptHistory(targetModuleId, stage = null) {
        let url = `/modules/${targetModuleId}/quiz/history`;
        if (stage) {
            url += `?quiz_stage=${stage}`;
        }

        fetch(url, {
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            var box = document.getElementById('historyBox');
            if (!box) { return; }
            if (res.success && res.attempts && res.attempts.length) {
                renderAttemptHistory(res.attempts);
            } else {
                box.innerHTML = '<p class="qz-history-title"><i class="fas fa-history"></i> Attempt History</p>' +
                    '<p class="qz-history-empty">No previous attempts yet.</p>';
            }
        })
        .catch(function () {
            var box = document.getElementById('historyBox');
            if (box) {
                box.innerHTML = '<p class="qz-history-title"><i class="fas fa-history"></i> Attempt History</p>' +
                    '<p class="qz-history-empty">Failed to load history.</p>';
            }
        });
    }

    function renderAttemptHistory(attempts) {
        var box = document.getElementById('historyBox');
        if (!box) { return; }

        var rows = attempts.map(function (a) {
            var pct = Math.round(a.percentage);
            var scoreClass = a.passed ? 'pass' : 'fail';
            var dateStr = a.completed_at
                ? new Date(a.completed_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
                : '';

            var detailContent = cachedHistoryDetailHtml[a.id]
                ? cachedHistoryDetailHtml[a.id]
                : '<p class="qz-history-empty"><i class="fas fa-spinner fa-spin"></i> Loading details...</p>';

            return '<div class="qz-history-item" id="historyItem_' + a.id + '">' +
                '<div class="qz-history-row" onclick="toggleAttemptHistory(' + a.id + ')">' +
                '<div class="qz-history-left">' +
                '<span class="qz-history-num">Attempt ' + a.attempt_number + '</span>' +
                '<span class="qz-history-score ' + scoreClass + '">' + pct + '% &bull; ' + a.score + '/' + a.total + '</span>' +
                '</div>' +
                '<div style="display:flex;align-items:center;gap:8px;">' +
                '<span class="qz-history-date">' + dateStr + '</span>' +
                '<i class="fas fa-chevron-down qz-history-chevron"></i>' +
                '</div>' +
                '</div>' +
                '<div class="qz-history-detail" id="historyDetail_' + a.id + '">' +
                detailContent +
                '</div>' +
                '</div>';
        }).join('');

        box.innerHTML = '<p class="qz-history-title"><i class="fas fa-history"></i> Attempt History</p>' + rows;
    }

    function escHtml(str) {
        if (str === null || str === undefined) return '';
        var d = document.createElement('div');
        d.textContent = String(str);
        return d.innerHTML;
    }

    var cachedHistoryDetailHtml = {};

    function toggleAttemptHistory(snapshotId) {
        var item = document.getElementById('historyItem_' + snapshotId);
        if (!item) { return; }

        var isCurrentlyOpen = item.classList.contains('open');

        if (isCurrentlyOpen) {
            item.classList.remove('open');
            return;
        }

        item.classList.add('open');

        var detailEl = document.getElementById('historyDetail_' + snapshotId);
        if (!detailEl) { return; }

        if (cachedHistoryDetailHtml[snapshotId]) {
            detailEl.innerHTML = cachedHistoryDetailHtml[snapshotId];
            return;
        }

        detailEl.innerHTML = '<p class="qz-history-empty"><i class="fas fa-spinner fa-spin"></i> Loading details...</p>';

        fetch('/quiz/attempts/' + snapshotId + '/detail', {
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success && res.questions && res.questions.length) {
                var html = res.questions.map(function (q, i) {
                    var cls = q.is_correct ? 'correct' : 'incorrect';
                    var yourAns = q.selected_option
                        ? q.selected_option + (q.options && q.options[q.selected_option] ? ' - ' + q.options[q.selected_option] : '')
                        : 'No answer';
                    var correctAns = q.correct_option
                        ? q.correct_option + (q.options && q.options[q.correct_option] ? ' - ' + q.options[q.correct_option] : '')
                        : '-';
                    return '<div class="qz-history-q ' + cls + '">' +
                        '<p class="qz-history-q-text">' + (i + 1) + '. ' + escHtml(q.question_text || '') + '</p>' +
                        '<p class="qz-history-q-ans"><i class="fas fa-' + (q.is_correct ? 'check' : 'times') + '"></i> Your answer: ' + escHtml(yourAns) + '</p>' +
                        (!q.is_correct
                            ? '<p class="qz-history-q-ans"><i class="fas fa-check"></i> Correct answer: ' + escHtml(correctAns) + '</p>'
                            : '') +
                        '</div>';
                }).join('');

                cachedHistoryDetailHtml[snapshotId] = html;
                detailEl.innerHTML = html;
            } else {
                detailEl.innerHTML = '<p class="qz-history-empty">No details available for this attempt.</p>';
            }
        })
        .catch(function (err) {
            console.error('Error loading attempt details:', err);
            detailEl.innerHTML = '<p class="qz-history-empty">Failed to load details.</p>';
        });
    }

    function backToModuleList() {
        stopAntiCheat();
        stopProgressTracking();
        stopSubpartProgressTracking();

        $('.mod-item').removeClass('active');
        $('#modContent').html(`
            <div class="mod-placeholder">
                <i class="fas fa-book-open"></i>
                <p>Select a module from the outline to begin.</p>
            </div>
        `);
    }

    function refreshCurrentAiInsights(btn) {
        if (!currentModuleId) return;
        const attemptKey = quizAttemptKey(currentModuleId, currentQuizStage);
        const attemptId = quizAttempts[attemptKey]?.attempt_id || null;

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Refreshing...';
        }

        $('#aiBox').html(renderAiInsightsLoading());
        getAI(currentModuleId, attemptId, true);
    }

    function getAI(moduleId, attemptId = null, forceRefresh = false) {
        function renderAiMessage(message) {
            $('#aiBox').html(renderAiInsightsMessage(message));
        }

        const attemptKey = quizAttemptKey(moduleId, currentQuizStage);
        const payload = { _token: '{{ csrf_token() }}' };
        if (attemptId) {
            payload.attempt_id = attemptId;
        }
        if (currentQuizStage) {
            payload.quiz_stage = currentQuizStage;
        }
        if (forceRefresh) {
            payload.force_refresh = 1;
        }

        $.post(`/modules/${moduleId}/quiz/insights`, payload)
            .done(function (res) {
                if (res.success) {
                    $('#aiBox').html(renderAiInsightsCard(res.strong, res.weak, res.recommendation));
                    // Cache insights client-side so re-navigating to the result doesn't refetch.
                    if (quizAttempts[attemptKey]) {
                        quizAttempts[attemptKey].ai_strong = res.strong;
                        quizAttempts[attemptKey].ai_weak = res.weak;
                        quizAttempts[attemptKey].ai_recommendation = res.recommendation;
                    }
                } else {
                    if (quizAttempts[attemptKey]) {
                        quizAttempts[attemptKey].ai_strong = null;
                        quizAttempts[attemptKey].ai_weak = null;
                        quizAttempts[attemptKey].ai_recommendation = null;
                    }
                    renderAiMessage(res.message || 'No insights available.');
                }
            })
            .fail(function (xhr) {
                const apiMessage = xhr?.responseJSON?.message;
                if (quizAttempts[attemptKey] && (xhr?.status === 403 || apiMessage)) {
                    quizAttempts[attemptKey].ai_strong = null;
                    quizAttempts[attemptKey].ai_weak = null;
                    quizAttempts[attemptKey].ai_recommendation = null;
                }
                renderAiMessage(apiMessage || 'Failed to load.');
            });
    }

    function saveScore(moduleId, score, total, pct) {
        const payload = { _token: '{{ csrf_token() }}', score, total, percentage: pct };
        if (currentQuizStage) {
            payload.quiz_stage = currentQuizStage;
        }
        return $.post(`/modules/${moduleId}/quiz/submit`, payload);
    }

    function saveAnswers(moduleId, forcedFail) {
        if (forcedFail) return $.Deferred().resolve().promise();
        const requests = currentQuizQuestions
            .filter(q => selectedAnswers[q.id])
            .map(q => {
                const payload = {
                    _token: '{{ csrf_token() }}',
                    question_id: q.id,
                    selected_option: selectedAnswers[q.id],
                };
                if (currentQuizStage) {
                    payload.quiz_stage = currentQuizStage;
                }
                return $.post(`/quiz/${moduleId}/answer`, payload);
            });
        return requests.length ? $.when.apply($, requests) : $.Deferred().resolve().promise();
    }

    function startTimer(minutes) {
        if (minutes <= 0) { $('#quizTimer').html('<i class="fas fa-infinity" style="font-size: 14px;opacity:0.7;"></i> No limit'); return; }
        timeLeft = minutes * 60;
        clearInterval(quizTimerInterval);
        quizTimerInterval = setInterval(() => {
            const min = Math.floor(timeLeft / 60), sec = timeLeft % 60;
            $('#quizTimer').html(`<i class="fas fa-clock" style="font-size: 14px;opacity:0.7;"></i> ${min}:${sec < 10 ? '0' : ''}${sec}`);
            if (timeLeft <= 300) $('#quizTimer').addClass('warning');
            if (timeLeft <= 0)  { clearInterval(quizTimerInterval); submitQuiz(); }
            timeLeft--;
        }, 1000);
    }

    function startAntiCheat() {
        isQuizActive = true; warningCount = 0;
        document.removeEventListener('visibilitychange', handleTab);
        window.removeEventListener('blur', handleTab);
        document.addEventListener('visibilitychange', handleTab);
        window.addEventListener('blur', handleTab);
    }

    function stopAntiCheat() {
        isQuizActive = false;
        document.removeEventListener('visibilitychange', handleTab);
        window.removeEventListener('blur', handleTab);
    }

function handleTab() {
    if (!isQuizActive) return;
    if (!isFormalAssessment) return; // Skip anti-cheat for pre-assessments

    // Prevent duplicate warnings within 2 seconds
    let now = Date.now();
    if (now - lastWarningTime < 2000) return;
    lastWarningTime = now;

    warningCount++;
    if (warningCount >= 4) {
        showQuizWarningToast('You switched tabs too many times.\nThe quiz is now auto-submitted as FAILED.', 'fail');
        submitQuiz(true); stopAntiCheat(); return;
    }
    showQuizWarningToast('Tab switching detected. Continued tab switching may cause this assessment to be auto-submitted as failed.', 'warn');
}

</script>

@endsection
@endsection