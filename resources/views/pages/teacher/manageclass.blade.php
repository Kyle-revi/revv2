@extends('layouts.appTeach')

@section('title', 'Class Management')
@section('page-heading', 'Class Management')

@section('head')
<script>
window.currentClassId = null;
window.deleteModeActive = false;
window.currentDeleteForm = null;

window.openCreateDialog = function() {
    var d = document.getElementById('dialogCreate');
    if (d) {
        if (typeof d.showModal === 'function') {
            d.showModal();
        } else {
            d.setAttribute('open', '');
        }
    }
};

window.closeCreateDialog = function() {
    var d = document.getElementById('dialogCreate');
    if (d) {
        if (typeof d.close === 'function') {
            d.close();
        } else {
            d.removeAttribute('open');
        }
    }
};

window.toggleDeleteMode = function() {
    window.deleteModeActive = !window.deleteModeActive;
    var toggleBtn = document.getElementById('deleteModeToggle');
    var deleteActions = document.querySelectorAll('.class-delete-action');
    if (window.deleteModeActive) {
        if (toggleBtn) {
            toggleBtn.classList.add('active');
            toggleBtn.innerHTML = '<i class="fas fa-times"></i> Cancel Delete';
        }
        deleteActions.forEach(function(el) { el.style.display = 'block'; });
    } else {
        if (toggleBtn) {
            toggleBtn.classList.remove('active');
            toggleBtn.innerHTML = '<i class="fas fa-trash-alt"></i> Delete Classes';
        }
        deleteActions.forEach(function(el) { el.style.display = 'none'; });
    }
};

window.openDeleteClassConfirm = function(btn) {
    window.currentDeleteForm = btn.closest('.delete-class-form');
    var className = window.currentDeleteForm ? window.currentDeleteForm.dataset.className : '';
    var msg = document.getElementById('deleteClassConfirmMessage');
    if (msg) msg.textContent = 'Delete class "' + className + '"? This cannot be undone.';
    var overlay = document.getElementById('deleteClassConfirmOverlay');
    if (overlay) overlay.setAttribute('aria-hidden', 'false');
};

window.closeDeleteClassConfirm = function() {
    var overlay = document.getElementById('deleteClassConfirmOverlay');
    if (overlay) overlay.setAttribute('aria-hidden', 'true');
    window.currentDeleteForm = null;
};

window.openStudentsDrawer = function(classId, className) {
    window.currentClassId = classId;
    var sub = document.getElementById('studentsDrawerSubtitle');
    if (sub) sub.textContent = className;
    var d = document.getElementById('dialogStudents');
    if (d) {
        if (typeof d.showModal === 'function') {
            d.showModal();
        } else {
            d.setAttribute('open', '');
        }
    }
    var joinInput = document.getElementById('joinLinkInput');
    if (joinInput) joinInput.value = '';
    var joinCopy = document.getElementById('joinLinkCopyBtn');
    if (joinCopy) joinCopy.disabled = true;

    if (typeof loadCurrentStudents === 'function') {
        loadCurrentStudents();
    }
    if (typeof initStudentSelect2 === 'function') {
        initStudentSelect2();
    }
};

window.openModulesDrawer = function(classId, className) {
    window.currentClassId = classId;
    var sub = document.getElementById('modulesDrawerSubtitle');
    if (sub) sub.textContent = className;
    var mId = document.getElementById('moduleClassId');
    if (mId) mId.value = classId;
    var qId = document.getElementById('quizClassId');
    if (qId) qId.value = classId;
    var qForm = document.getElementById('quizDraftForm');
    if (qForm) qForm.action = "{{ url('/quiz/create-draft') }}/" + classId;
    var aId = document.getElementById('assessmentClassId');
    if (aId) aId.value = classId;
    var aForm = document.getElementById('assessmentDraftForm');
    if (aForm) aForm.action = "{{ url('/quiz/create-draft') }}/" + classId;
    var annForm = document.getElementById('announcementForm');
    if (annForm) annForm.action = "{{ url('/classes') }}/" + classId + "/announcements";

    var sel = document.getElementById('testTypeSelect');
    if (sel) sel.value = 'pre_test';
    if (typeof onTestTypeChange === 'function') {
        onTestTypeChange('pre_test');
    }
    if (typeof resetLectureUploadFields === 'function') {
        resetLectureUploadFields();
    }
    if (typeof switchTab === 'function') {
        switchTab('tabQuiz', document.querySelector('.rv-tab'));
    }
    if (typeof resetVisibilityPicker === 'function') {
        ['doc','quiz','assessment'].forEach(resetVisibilityPicker);
    }
    var d = document.getElementById('dialogModules');
    if (d) {
        if (typeof d.showModal === 'function') {
            d.showModal();
        } else {
            d.setAttribute('open', '');
        }
    }
};
</script>
@endsection


@section('header-actions')
    <button class="rv-btn" id="deleteModeToggle" onclick="toggleDeleteMode()">
        <i class="fas fa-trash-alt"></i> Delete Classes
    </button>
    <button class="rv-btn rv-btn-primary" onclick="openCreateDialog()">
        <i class="fas fa-plus"></i> New Class
    </button>
@endsection

@section('content')


<style>

    /* -”€-”€ Stats row -”€-”€ */

    .mc-stats {

        display: grid;

        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));

        gap: 12px;

        margin-bottom: 24px;

    }



    .mc-stat {

        background: #fff;

        border: 1px solid #ebebeb;

        border-radius: 12px;

        padding: 16px 18px;

    }



    .mc-stat-label { font-size: 17px; font-weight: 500; letter-spacing: 0.06em; text-transform: uppercase; color: #bbb; margin-bottom: 6px; }

    .mc-stat-val { font-family: 'DM Sans', sans-serif; font-size: 36px; color: #111; line-height: 1; }



    /* -”€-”€ Class cards grid -”€-”€ */

    .mc-grid {

        display: grid;

        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));

        gap: 14px;

    }



    .mc-card {

        background: #fff;

        border: 1px solid #ebebeb;

        border-radius: 14px;

        padding: 20px;

        display: flex;

        flex-direction: column;

        gap: 14px;

        transition: box-shadow 0.15s, border-color 0.15s;

        animation: mc-fadein 0.2s ease both;

    }



    @keyframes mc-fadein {

        from { opacity: 0; transform: translateY(6px); }

        to   { opacity: 1; transform: translateY(0); }

    }



    .mc-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.06); border-color: #d8d8d8; }



    .mc-card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }



    .mc-card-icon {

        width: 40px; height: 40px; border-radius: 10px;

        background: #f3f3f3; display: flex; align-items: center;

        justify-content: center; font-size: 16px; color: #888; flex-shrink: 0;

    }



    .mc-card-badge {

        font-size: 17px; font-weight: 500; padding: 3px 9px;

        border-radius: 99px; background: #f3f3f3; color: #666;

    }



    .mc-card-name { font-size: 18px; font-weight: 500; color: #111; margin: 0 0 3px; line-height: 1.3; }

    .mc-card-meta { font-size: 17px; color: #aaa; }



    .mc-card-divider { height: 1px; background: #f3f3f3; }



    .mc-card-stats { display: flex; gap: 16px; }

    .mc-card-stat-item { display: flex; flex-direction: column; gap: 2px; }

    .mc-card-stat-num { font-size: 20px; font-weight: 500; color: #111; }

    .mc-card-stat-lbl { font-size: 17px; color: #bbb; }



    .mc-card-actions { display: flex; gap: 8px; }



    .mc-action-btn {

        flex: 1; height: 38px; border-radius: 8px;

        font-family: 'DM Sans', sans-serif; font-size: 17px; font-weight: 500;

        cursor: pointer; border: 1px solid #e4e4e4; background: #fff; color: #555;

        transition: background 0.15s, border-color 0.15s, color 0.15s;

        display: flex; align-items: center; justify-content: center; gap: 5px;

    }



    .mc-action-btn:hover { border-color: #bbb; color: #111; background: #fafafa; }



    .mc-action-btn.accent { background: #0f0f0f; color: #fff; border-color: #0f0f0f; }

    .mc-action-btn.accent:hover { background: #333; }



    /* -”€-”€ Empty state -”€-”€ */

    .mc-empty {

        text-align: center; padding: 4rem 0; color: #ccc; font-size: 17px;

        grid-column: 1 / -1;

    }



    .mc-empty-icon { font-size: 36px; margin-bottom: 12px; opacity: 0.3; }



    /* -”€-”€ Drawer tabs -”€-”€ */

    .rv-tabs { display: flex; flex-wrap: wrap; gap: 0; border-bottom: 1px solid #f0f0f0; margin-bottom: 20px; }

    .rv-tab {

        padding: 10px 14px; font-size: 13.5px; font-weight: 500; color: #aaa;

        cursor: pointer; border-bottom: 2px solid transparent; transition: color 0.15s, border-color 0.15s;

        background: none; border-top: none; border-left: none; border-right: none;

        font-family: 'DM Sans', sans-serif; white-space: nowrap;

    }

    .rv-tab.active { color: #111; border-bottom-color: #111; }



    .rv-tab-panel { display: none; }

    .rv-tab-panel.active { display: block; }



    /* ── Student list items ── */
    .rv-student-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #f7f7f7;
        font-size: 17px;
        color: #333;
        gap: 10px;
        min-width: 0;
        width: 100%;
        box-sizing: border-box;
    }
    .rv-student-item:last-child { border-bottom: none; }

    /* ── Module list items ── */
    .rv-module-item {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid #f7f7f7;
        gap: 12px;
        min-width: 0;
        width: 100%;
        box-sizing: border-box;
    }
    .rv-module-item:last-child { border-bottom: none; }
    .rv-module-title {
        font-size: 17px;
        font-weight: 500;
        color: #111;
        margin-bottom: 4px;
        overflow-wrap: anywhere;
        word-break: break-word;
        word-wrap: break-word;
        line-height: 1.35;
    }
    .rv-module-meta {
        font-size: 14px;
        color: #888;
        overflow-wrap: anywhere;
        word-break: break-word;
        word-wrap: break-word;
        line-height: 1.35;
    }
    .rv-module-actions {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-shrink: 0;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .rv-module-type {
        font-size: 14px;
        font-weight: 500;
        padding: 2px 7px;
        border-radius: 99px;
        white-space: nowrap;
        flex-shrink: 0;
    }
    .rv-module-type.doc { background: #e6f1fb; color: #185fa5; }
    .rv-module-type.quiz { background: #eeedfe; color: #3c3489; }



    /* Select2 override */

    .select2-container .select2-selection--multiple {

        border: 1px solid #e4e4e4 !important; border-radius: 8px !important;

        min-height: 40px !important; font-family: 'DM Sans', sans-serif !important;

    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice {

        background: #f3f3f3 !important; border: none !important; border-radius: 6px !important;

        color: #444 !important; font-size: 17px !important;

    }



    /* -”€-”€ Visibility picker -”€-”€ */

    .vis-toggle { display: flex; gap: 6px; margin-bottom: 10px; }

    .vis-opt {

        padding: 5px 12px; border-radius: 8px; font-size: 17px; font-weight: 500;

        border: 1px solid #e4e4e4; background: #fff; color: #777; cursor: pointer;

        font-family: 'DM Sans', sans-serif; transition: background 0.15s, color 0.15s, border-color 0.15s;

    }

    .vis-opt:hover { border-color: #bbb; color: #333; }

    .vis-opt.active { background: #0f0f0f; color: #fff; border-color: #0f0f0f; }

    .vis-student-picker { display: none; margin-top: 8px; }

    .vis-search-wrap { position: relative; margin-bottom: 6px; }

    .vis-search-wrap input {

        width: 100%; padding: 8px 12px; border: 1px solid #e4e4e4; border-radius: 8px;

        font-size: 17px; font-family: 'DM Sans', sans-serif; box-sizing: border-box;

    }

    .vis-results {

        position: absolute; left: 0; right: 0; top: 100%; background: #fff;

        border: 1px solid #e4e4e4; border-radius: 8px; max-height: 180px;

        overflow-y: auto; display: none; z-index: 50; box-shadow: 0 4px 16px rgba(0,0,0,0.08);

    }

    .vis-result-item {

        padding: 8px 12px; font-size: 17px; cursor: pointer; display: flex;

        align-items: center; gap: 8px; transition: background 0.1s;

    }

    .vis-result-item:hover { background: #f7f7f7; }

    .vis-result-name { font-weight: 500; color: #111; }

    .vis-result-id { color: #999; font-size: 17px; }

    .vis-result-email { color: #bbb; font-size: 17px; }

    .vis-result-program {

        font-size: 17px; padding: 1px 7px; border-radius: 99px;

        background: #f3f3f3; color: #666; margin-left: auto; white-space: nowrap;

    }

    .vis-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }

    .vis-chip {

        display: flex; align-items: center; gap: 4px; padding: 3px 10px;

        background: #f3f3f3; border-radius: 99px; font-size: 17px; color: #444;

    }

    .vis-chip-remove {

        background: none; border: none; cursor: pointer; color: #aaa;

        font-size: 17px; line-height: 1; padding: 0 2px;

    }

    .vis-chip-remove:hover { color: #e24b4a; }

    .vis-empty-hint { font-size: 17px; color: #ccc; margin-top: 6px; }



    /* -"€-"€ Join Link Section (inside drawer) -"€-"€ */

    .rv-join-link-section {

        margin-top: 24px;

        padding: 18px;

        background: #f8f9fa;

        border-radius: 12px;

        border: 1px solid #e9ecef;

    }

    .rv-join-link-header {

        display: flex;

        align-items: center;

        gap: 8px;

        font-size: 16px;

        font-weight: 600;

        color: #111;

        margin-bottom: 6px;

    }

    .rv-join-link-header i {

        color: #0f0f0f;

        font-size: 15px;

    }

    .rv-join-link-desc {

        font-size: 14px;

        color: #888;

        margin: 0 0 12px 0;

        line-height: 1.4;

    }

    .rv-join-link-wrap {

        display: flex;

        gap: 8px;

    }

    .rv-join-link-input {

        flex: 1;

        padding: 10px 12px;

        border: 1px solid #ddd;

        border-radius: 8px;

        font-size: 13px;

        font-family: 'DM Sans', sans-serif;

        background: #fff;

        color: #555;

    }

    .rv-join-link-btn {

        padding: 10px 14px;

        border-radius: 8px;

        border: none;

        background: #0f0f0f;

        color: #fff;

        font-size: 14px;

        cursor: pointer;

        display: flex;

        align-items: center;

        justify-content: center;

        transition: all 0.15s;

    }

    .rv-join-link-btn:hover {

        background: #333;

        transform: translateY(-1px);

    }

    .rv-join-link-btn:disabled {

        background: #ccc;

        cursor: not-allowed;

        transform: none;

    }

    .rv-join-link-btn.copied {

        background: #22c55e;

    }

    .rv-join-link-btn.generating {

        animation: rv-spin 1s linear infinite;

    }

    @keyframes rv-spin {

        from { transform: rotate(0deg); }

        to { transform: rotate(360deg); }

    }



    #dialogModules .rv-dialog-panel {

        position: relative;

    }



    #dialogModules .rv-drawer-title {

        font-family: 'DM Sans', sans-serif;

        font-size: 30px;

        font-weight: 500;

        letter-spacing: -0.02em;

        color: #111827;

    }



    #dialogModules .rv-drawer-subtitle {

        font-family: 'DM Sans', sans-serif;

        font-size: 16px;

        font-weight: 500;

        color: #7b8794;

        margin-top: 4px;

    }



    #dialogModules .rv-tab {

        font-family: 'DM Sans', sans-serif;

        font-size: 15px;

        font-weight: 500;

        letter-spacing: 0.01em;

    }



    #dialogModules .rv-label {

        font-family: 'DM Sans', sans-serif;

        font-size: 13px;

        font-weight: 500;

        letter-spacing: 0.08em;

        text-transform: uppercase;

        color: #6b7280;

    }



    #dialogModules .rv-input,

    #dialogModules .rv-textarea,

    #dialogModules .vis-search-wrap input,

    #dialogModules .vis-opt,

    #dialogModules .vis-empty-hint,

    #dialogModules .rv-module-title,

    #dialogModules .rv-module-meta,

    #dialogModules .rv-module-type {

        font-family: 'DM Sans', sans-serif;

    }



    #dialogModules .rv-input,

    #dialogModules .rv-textarea,

    #dialogModules .vis-search-wrap input {

        font-size: 17px;

    }



    #dialogModules .rv-textarea {

        min-height: 92px;

        line-height: 1.55;

    }



    #dialogModules .vis-opt,

    #dialogModules .vis-empty-hint,

    #dialogModules .rv-module-meta,

    #dialogModules .rv-module-type {

        font-size: 17px;

    }



    #dialogModules .rv-module-title {

        font-size: 19px;

        font-weight: 500;

    }



    #dialogModules .rv-btn {

        font-family: 'DM Sans', sans-serif;

        font-size: 17px;

        font-weight: 500;

    }



    .mc-toast {

        position: absolute;

        top: 14px;

        right: 14px;

        min-width: 260px;

        max-width: 360px;

        padding: 12px 14px;

        border-radius: 10px;

        border: 1px solid #f4b3b2;

        background: #fff4f3;

        color: #7b2220;

        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.12);

        font-size: 13.5px;

        font-weight: 500;

        z-index: 9999;

        opacity: 0;

        transform: translateY(-8px);

        pointer-events: none;

        transition: opacity 0.2s ease, transform 0.2s ease;

    }



    .mc-toast.success { border-color: #c9e8d4; background: #e9f8ef; color: #17653f; }

    .mc-toast.error { border-color: #f4b3b2; background: #fff4f3; color: #7b2220; }

    .mc-toast.warn { border-color: #f2deaf; background: #fff9ea; color: #7b5a10; }



    .mc-toast.show {

        opacity: 1;

        transform: translateY(0);

    }



    .mc-confirm-overlay {

        position: fixed;

        inset: 0;

        background: rgba(0, 0, 0, 0.42);

        display: none;

        align-items: center;

        justify-content: center;

        z-index: 12000;

        padding: 16px;

    }



    .mc-confirm-overlay.show { display: flex; }



    .mc-confirm-modal {

        width: min(420px, 96vw);

        background: #fff;

        border: 1px solid #ececec;

        border-radius: 12px;

        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.22);

        padding: 14px;

    }



    .mc-confirm-title {

        margin: 0 0 8px;

        font-size: 16px;

        font-weight: 500;

        color: #111;

    }



    .mc-confirm-text {

        margin: 0;

        font-size: 16px;

        color: #555;

        line-height: 1.45;

    }



    .mc-confirm-actions {

        margin-top: 12px;

        display: flex;

        justify-content: flex-end;

        gap: 8px;

    }



    .ann-edit-overlay {

        position: fixed;

        inset: 0;

        background: rgba(0, 0, 0, 0.38);

        display: none;

        align-items: center;

        justify-content: center;

        z-index: 12000;

        padding: 16px;

    }



    .ann-edit-overlay.show {

        display: flex;

    }



    .ann-edit-modal {

        width: min(520px, 96vw);

        background: #fff;

        border-radius: 12px;

        border: 1px solid #ececec;

        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.22);

        padding: 14px;

    }



    .ann-edit-title {

        margin: 0 0 10px;

        font-size: 16px;

        font-weight: 500;

        color: #111;

    }



    .ann-edit-input {

        width: 100%;

        min-height: 120px;

        resize: vertical;

        border: 1px solid #e4e4e4;

        border-radius: 8px;

        padding: 10px 12px;

        font-size: 16px;

        line-height: 1.45;

        font-family: 'DM Sans', sans-serif;

        color: #222;

        box-sizing: border-box;

        outline: none;

    }



    .ann-edit-input:focus {

        border-color: #111;

    }



    .ann-edit-actions {

        margin-top: 12px;

        display: flex;

        justify-content: flex-end;

        gap: 8px;

    }

    /* Delete mode toggle button */
    #deleteModeToggle {
        background: #fff;
        color: #dc2626;
        border: 1px solid #dc2626;
        transition: all 0.15s ease;
    }

    #deleteModeToggle:hover {
        background: #dc2626;
        color: #fff;
    }

    #deleteModeToggle.active {
        background: #dc2626;
        border-color: #dc2626;
        color: #fff;
    }

    /* Delete action button on cards */
    .class-delete-action {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #f3f4f6;
    }

    .class-delete-action form button {
        flex: 1;
        height: 38px;
        border-radius: 8px;
        font-family: 'DM Sans', sans-serif;
        font-size: 17px;
        font-weight: 500;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        background: #fff;
        color: #dc2626;
        border: 1px solid #dc2626;
        transition: background 0.15s, border-color 0.15s, color 0.15s;
        width: 100%;
    }

    .class-delete-action form button:hover {
        background: #dc2626;
        color: #fff;
        border-color: #dc2626;
    }

</style>



{{-- -”€-”€ Stats row -”€-”€ --}}

<div class="mc-stats">

    <div class="mc-stat">

        <div class="mc-stat-label">Total classes</div>

        <div class="mc-stat-val">{{ $classes->count() }}</div>

    </div>

    <div class="mc-stat">

        <div class="mc-stat-label">Total students</div>

        <div class="mc-stat-val">{{ $classes->sum(fn($c) => $c->students_count ?? $c->users_count ?? 0) }}</div>

    </div>

</div>



{{-- -”€-”€ Class cards -”€-”€ --}}

<div class="mc-grid" id="classGrid">

    @forelse($classes as $i => $class)

        <div class="mc-card" style="animation-delay: {{ $i * 40 }}ms">

            <div class="mc-card-top">

                <div class="mc-card-icon"><i class="fas fa-chalkboard"></i></div>

                @if($class->school_year)

                    <span class="mc-card-badge">{{ $class->school_year }}</span>

                @endif

            </div>



            <div>

                <p class="mc-card-name">{{ $class->name }}</p>

                <p class="mc-card-meta">

                    {{ $class->code ? 'Code: ' . $class->code : 'No code' }}

                    {{ $class->description ? ' ' . Str::limit($class->description, 40) : '' }}

                </p>

            </div>



            <div class="mc-card-divider"></div>



            <div class="mc-card-stats">

                <div class="mc-card-stat-item">

                    <span class="mc-card-stat-num">{{ $class->students_count ?? $class->users_count ?? 0 }}</span>

                    <span class="mc-card-stat-lbl">Students</span>

                </div>

            </div>



            <div class="mc-card-actions">

                <button class="mc-action-btn" onclick="openStudentsDrawer({{ $class->id }}, '{{ addslashes($class->name) }}')">

                    <i class="fas fa-users"></i> Students

                </button>

                <button class="mc-action-btn accent" onclick="openModulesDrawer({{ $class->id }}, '{{ addslashes($class->name) }}')">

                    <i class="fas fa-folder-open"></i> Modules

                </button>

            </div>

            {{-- Delete class button (hidden by default, shown in delete mode) --}}
            <div class="class-delete-action" style="display: none;">
                <form method="POST" action="{{ route('classes.destroy', $class) }}" class="delete-class-form" style="display: contents;" data-class-name="{{ $class->name }}">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="delete-class-btn" onclick="openDeleteClassConfirm(this)">
                        <i class="fas fa-trash-alt"></i> Delete Class
                    </button>
                </form>
            </div>

        </div>

    @empty

        <div class="mc-empty">

            <div class="mc-empty-icon"><i class="fas fa-chalkboard"></i></div>

            No classes yet. Create your first class to get started.

        </div>

    @endforelse

</div>





{{-- Drawers and Dialogs --}}


<style>
    /* Delete Class Confirmation Modal */
    .delete-confirm-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.32);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        opacity: 0;
        transition: opacity 0.2s ease;
    }

    .delete-confirm-overlay[aria-hidden="false"] {
        display: flex;
        opacity: 1;
    }

    .delete-confirm-modal {
        background: #ffffff;
        border-radius: 14px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.12);
        padding: 28px;
        max-width: 420px;
        width: 90%;
        text-align: center;
        transform: scale(0.95);
        transition: transform 0.2s ease;
    }

    .delete-confirm-overlay[aria-hidden="false"] .delete-confirm-modal {
        transform: scale(1);
    }

    .delete-confirm-title {
        font-family: 'DM Sans', sans-serif;
        font-size: 22px;
        font-weight: 600;
        color: #111827;
        margin: 0 0 12px 0;
    }

    .delete-confirm-text {
        font-family: 'DM Sans', sans-serif;
        font-size: 16px;
        color: #6b7280;
        margin: 0 0 24px 0;
        line-height: 1.5;
    }

    .delete-confirm-actions {
        display: flex;
        gap: 12px;
        justify-content: center;
    }

    .delete-confirm-actions button {
        flex: 1;
        height: 44px;
        border-radius: 8px;
        font-family: 'DM Sans', sans-serif;
        font-size: 16px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s ease;
        border: none;
    }

    .delete-confirm-cancel {
        background: #fafafa;
        color: #374151;
        border: 1px solid #e4e4e4;
    }

    .delete-confirm-cancel:hover {
        background: #f3f4f6;
        border-color: #d1d5db;
    }

    .delete-confirm-continue {
        background: #fff5f5;
        color: #dc2626;
        border: 1px solid #f0cccc;
    }

    .delete-confirm-continue:hover {
        background: #dc2626;
        color: #ffffff;
        border-color: #dc2626;
    }


    /* -”€-”€ Native dialog panels -”€-”€ */

    dialog.rv-dialog {
        padding: 0;
        border: none;
        border-radius: 0;
        margin: 0 0 0 auto;
        width: 460px;
        max-width: 95vw;
        height: 100vh;
        max-height: 100vh;
        background: transparent;
        outline: none;
    }

    #dialogModules {
        width: 500px;
    }



    dialog.rv-dialog::backdrop {

        background: rgba(0, 0, 0, 0.45);

    }



    .rv-dialog-panel {

        display: flex;

        flex-direction: column;

        height: 100%;

        background: #fff;

        border-left: 1px solid rgba(255,255,255,0.08);

        overflow: hidden;

    }

</style>



{{-- -•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•

     DIALOG: Create Class

-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-• --}}

<dialog id="dialogCreate" class="rv-dialog">
    <div class="rv-dialog-panel">
        <div class="rv-drawer-head">
            <div>
                <div class="rv-drawer-title">New Class</div>
                <div class="rv-drawer-subtitle">Fill in the details below to create a class.</div>
            </div>
            <button class="rv-drawer-close" onclick="document.getElementById('dialogCreate').close()">&#x2715;</button>
        </div>

        <div class="rv-drawer-body">
            <form method="POST" action="{{ route('classes.store') }}" id="createClassForm">
                @csrf

                <div class="rv-form-group">
                    <label class="rv-label">Class Name <span style="color:#e24b4a">*</span></label>
                    <input type="text" name="name" class="rv-input" required placeholder="e.g. Grade 10 - Section A">
                </div>

                <div class="rv-form-group">
                    <label class="rv-label">Code (optional)</label>
                    <input type="text" name="code" class="rv-input" placeholder="e.g. 10A-2026">
                </div>

                <div class="rv-form-group">
                    <label class="rv-label">School Year</label>
                    <input type="number" name="school_year" class="rv-input" placeholder="2026">
                </div>

                <!-- IDINAGDAG NA YEAR LEVEL -->
                <div class="rv-form-group">
                    <label class="rv-label">Year Level (optional)</label>
                    <select name="year_level" class="rv-input">
                        <option value="">Select Year Level</option>
                        <option value="1">1st Year</option>
                        <option value="2">2nd Year</option>
                        <option value="3">3rd Year</option>
                        <option value="4">4th Year</option>
                    </select>
                </div>

                <div class="rv-form-group">
                    <label class="rv-label">Description (optional)</label>
                    <textarea name="description" class="rv-textarea" placeholder="Brief description of the class..."></textarea>
                </div>
            </form>
        </div>

        <div class="rv-drawer-footer">
            <button class="rv-btn rv-btn-secondary" onclick="document.getElementById('dialogCreate').close()">Cancel</button>
            <button class="rv-btn rv-btn-primary" onclick="document.getElementById('createClassForm').submit()">
                <i class="fas fa-plus"></i> Create Class
            </button>
        </div>
    </div>
</dialog>



{{-- -•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•

     DIALOG: Manage Students

-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-• --}}

<dialog id="dialogStudents" class="rv-dialog">

    <div class="rv-dialog-panel">

        <div class="rv-drawer-head">

            <div>

                <div class="rv-drawer-title">Students</div>

                <div class="rv-drawer-subtitle" id="studentsDrawerSubtitle">-€”</div>

            </div>

            <button class="rv-drawer-close" onclick="document.getElementById('dialogStudents').close()">&#x2715;</button>

        </div>

        <div class="rv-drawer-body">

            <div class="rv-form-group">

                <label class="rv-label">Add students</label>

                <select id="studentSelect" multiple="multiple" style="width:100%"></select>

                <button class="rv-btn rv-btn-success" style="margin-top:10px;width:100%;" id="addSelectedStudentsBtn">

                    <i class="fas fa-user-plus"></i> Add Selected

                </button>

            </div>



            <div style="margin-top:8px;">

                <label class="rv-label" style="margin-bottom:12px;">Current students</label>

                <div id="currentStudentsList">

                    <p style="font-size: 16px;color:#ccc;text-align:center;padding:1rem 0;">Loading...</p>

                </div>

            </div>



            {{-- Join Link Section --}}

            <div class="rv-join-link-section">

                <div class="rv-join-link-header">

                    <i class="fas fa-link"></i>

                    <span>Invite Link</span>

                </div>

                <p class="rv-join-link-desc">Generate a link valid for 24 hours to share with students.</p>

                <div class="rv-join-link-wrap">

                    <input type="text" class="rv-join-link-input" id="joinLinkInput" readonly placeholder="Click Generate to create link...">

                    <button class="rv-join-link-btn" id="joinLinkGenerateBtn" onclick="generateJoinLink()" title="Generate new link">

                        <i class="fas fa-sync-alt"></i>

                    </button>

                    <button class="rv-join-link-btn" id="joinLinkCopyBtn" onclick="copyJoinLink()" title="Copy link" disabled>

                        <i class="fas fa-copy"></i>

                    </button>

                </div>

            </div>

        </div>

    </div>

</dialog>



{{-- -•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•

     DIALOG: Manage Modules

-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-•-• --}}

<dialog id="dialogModules" class="rv-dialog">

    <div class="rv-dialog-panel">

        <div class="rv-drawer-head">

            <div>

                <div class="rv-drawer-title">Modules</div>

                <div class="rv-drawer-subtitle" id="modulesDrawerSubtitle">Manage uploads and assessments for this class.</div>

            </div>

            <button class="rv-drawer-close" onclick="document.getElementById('dialogModules').close()">&#x2715;</button>

        </div>

        <div class="rv-drawer-body">



            {{-- Tabs --}}

            {{-- Tabs --}}
            <div class="rv-tabs">
                <button class="rv-tab active" onclick="switchTab('tabQuiz', this)">Pre-Test / Post-Test</button>
                <button class="rv-tab" onclick="switchTab('tabUpload', this)">Domain / Documents</button>
                <button class="rv-tab" onclick="switchTab('tabAssessment', this)">Formal Assessment</button>
                <button class="rv-tab" onclick="switchTab('tabAnnouncements', this)">Announcements</button>
            </div>

            {{-- 1. Pre-Test / Post-Test tab --}}
            <div class="rv-tab-panel active" id="tabQuiz">
                <form id="quizDraftForm" method="POST" action="{{ url('/quiz/create-draft/0') }}">
                    @csrf
                    <input type="hidden" name="class_id" id="quizClassId" value="0">
                    <input type="hidden" name="is_formal_assessment" id="quizIsFormalAssessment" value="0">
                    <input type="hidden" name="quiz_stage" id="quizStageInput" value="pre_test">

                    <div class="rv-form-group">
                        <label class="rv-label">Test Type <span style="color:#e24b4a">*</span></label>
                        <select name="quiz_stage" id="testTypeSelect" class="rv-input" onchange="onTestTypeChange(this.value)" style="width:100%;height:42px;font-size:15px;font-weight:500;">
                            <option value="pre_test" selected>Pre-Test</option>
                            <option value="post_test">Post-Test</option>
                        </select>
                        <p id="testTypeHint" style="font-size: 14px; color:#666; margin:6px 0 0 2px;">
                            <i class="fas fa-info-circle"></i> <strong>Pre-Test:</strong> Diagnostic assessment taken at the start before lecture modules. Always positioned at the beginning.
                        </p>
                    </div>

                    <div class="rv-form-group">
                        <label class="rv-label" id="testTitleLabel">Pre-Test Title <span style="color:#e24b4a">*</span></label>
                        <input type="text" name="title" id="testTitleInput" class="rv-input" required placeholder="e.g. Pre-Test 1: Diagnostic Assessment">
                    </div>

                    <div class="rv-form-group">
                        <label class="rv-label">Description (optional)</label>
                        <textarea name="description" class="rv-textarea" placeholder="Brief overview..."></textarea>
                    </div>

                    <div class="rv-form-group">
                        <label class="rv-label">Time Limit (minutes, 0 = unlimited)</label>
                        <input type="number" name="time_limit" class="rv-input" min="0" value="0" style="width:50%;">
                    </div>

                    <div class="rv-form-group">
                        <label class="rv-label">Passing Grade (%, leave blank for default 50%)</label>
                        <input type="number" name="passing_grade" class="rv-input" min="1" max="100" placeholder="50" style="width:50%;">
                    </div>
                    <div class="rv-form-group">
                        <label class="rv-label">Max Attempts (base)</label>
                        <input type="number" name="max_attempts" class="rv-input" min="1" max="20" value="1" style="width:50%;">
                    </div>
                    <div class="rv-form-group">
                        <label class="rv-label">Due Date (optional)</label>
                        <input type="datetime-local" name="due_date" class="rv-input" style="width:60%;">
                    </div>
                    <div class="rv-form-group">
                        <label class="rv-label">Who can see this?</label>
                        <input type="hidden" name="visibility" id="visInput_quiz" value="all">

                        <div class="vis-toggle">
                            <button type="button" class="vis-opt active" onclick="setVisibility(this,'quiz')">All Students</button>
                            <button type="button" class="vis-opt" onclick="setVisibility(this,'quiz')">Selected Students</button>
                            <button type="button" class="vis-opt" onclick="setVisibility(this,'quiz')">Except Students</button>
                        </div>

                        <div class="vis-student-picker" id="visPicker_quiz">
                            <div class="vis-search-wrap">
                                <input type="text" id="visSearch_quiz" placeholder="Search students by name, ID, email..." autocomplete="off">
                                <div class="vis-results" id="visResults_quiz"></div>
                            </div>
                            <div class="vis-chips" id="visChips_quiz"></div>
                            <div class="vis-empty-hint" id="visHint_quiz">Search and select students above.</div>
                        </div>
                    </div>

                    <button type="submit" id="quizSubmitBtn" class="rv-btn rv-btn-primary" style="width:100%;">
                        <i class="fas fa-brain"></i> Start Pre-Test Creation
                    </button>
                </form>

                <div style="margin-top:16px;">
                    <label class="rv-label" style="margin-bottom:10px;">Pre-Tests & Post-Tests:</label>
                    <div id="prePostTestList">
                        <p style="font-size: 16px;color:#ccc;text-align:center;padding:1rem 0;">Open this tab to load.</p>
                    </div>
                </div>
            </div>

            {{-- 2. Domain / Documents tab --}}
            <div class="rv-tab-panel" id="tabUpload">
                <form id="moduleUploadForm" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="class_id" id="moduleClassId">
                    <input type="hidden" name="type" value="lecture">

                    <div class="rv-form-group">
                        <label class="rv-label">Module / Domain Title <span style="color:#e24b4a">*</span></label>
                        <input type="text" name="title" class="rv-input" required placeholder="e.g. Domain 1: Network Fundamentals">
                    </div>

                    <div class="rv-form-group">
                        <label class="rv-label">Description (optional)</label>
                        <textarea name="description" class="rv-textarea" placeholder="Brief overview of this domain..."></textarea>
                    </div>

                    <div class="rv-form-group">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                            <label class="rv-label" style="margin-bottom:0;">Subdomain Files & Topics (1.1, 1.2, 1.3...) <span style="color:#e24b4a">*</span></label>
                            <button type="button" class="rv-btn rv-btn-secondary" style="height:30px;padding:0 10px;font-size:13px;" onclick="addLectureUploadField()">
                                <i class="fas fa-plus"></i> Add Sub-lesson
                            </button>
                        </div>

                        <div id="lectureContentUploadFields">
                            <div class="lecture-content-upload-row" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin-bottom:10px;">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                                    <span class="subpart-badge-num" style="font-size:13px;font-weight:700;color:#172b4d;background:#e2e8f0;padding:2px 8px;border-radius:6px;">1.1</span>
                                    <button type="button" class="rv-btn rv-btn-danger lecture-remove-upload" style="height:26px;padding:0 8px;font-size:12px;display:none;" onclick="removeLectureUploadField(this)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                <div class="rv-form-group" style="margin-bottom:8px;">
                                    <label class="rv-label" style="font-size:13px;color:#555;">Topic / Subdomain Title <span style="color:#e24b4a">*</span></label>
                                    <input type="text" name="subpart_titles[]" class="rv-input" required maxlength="150" placeholder="e.g. Introduction to IP Addressing">
                                </div>
                                <div class="rv-form-group" style="margin-bottom:0;">
                                    <label class="rv-label" style="font-size:13px;color:#555;">File (PDF, PPT, DOCX - max 100MB) <span style="color:#e24b4a">*</span></label>
                                    <input type="file" name="files[]" class="rv-input" accept=".pdf,.ppt,.pptx,.doc,.docx,.mov" required style="padding:7px 12px;background:#fff;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rv-form-group">
                        <label class="rv-label">Who can see this?</label>
                        <input type="hidden" name="visibility" id="visInput_doc" value="all">

                        <div class="vis-toggle">
                            <button type="button" class="vis-opt active" onclick="setVisibility(this,'doc')">All Students</button>
                            <button type="button" class="vis-opt" onclick="setVisibility(this,'doc')">Selected Students</button>
                            <button type="button" class="vis-opt" onclick="setVisibility(this,'doc')">Except Students</button>
                        </div>

                        <div class="vis-student-picker" id="visPicker_doc">
                            <div class="vis-search-wrap">
                                <input type="text" id="visSearch_doc" placeholder="Search students by name, ID, email..." autocomplete="off">
                                <div class="vis-results" id="visResults_doc"></div>
                            </div>
                            <div class="vis-chips" id="visChips_doc"></div>
                            <div class="vis-empty-hint" id="visHint_doc">Search and select students above.</div>
                        </div>
                    </div>

                    <button type="submit" class="rv-btn rv-btn-primary" style="width:100%;margin-top:10px;">
                        <i class="fas fa-upload"></i> Upload Domain & Subparts
                    </button>
                </form>

                <div style="margin-top:16px;">
                    <label class="rv-label" style="margin-bottom:10px;">Domain / Documents:</label>
                    <div id="documentsList">
                        <p style="font-size: 16px;color:#ccc;text-align:center;padding:1rem 0;">Open this tab to load.</p>
                    </div>
                </div>
            </div>

            {{-- 3. Formal Assessment tab --}}
            <div class="rv-tab-panel" id="tabAssessment">
                <form id="assessmentDraftForm" method="POST" action="{{ url('/quiz/create-draft/0') }}">
                    @csrf
                    <input type="hidden" name="class_id" id="assessmentClassId" value="0">
                    <input type="hidden" name="is_formal_assessment" value="1">
                    <input type="hidden" name="quiz_stage" value="">

                    <div class="rv-form-group">
                        <label class="rv-label">Assessment Title <span style="color:#e24b4a">*</span></label>
                        <input type="text" name="title" class="rv-input" required placeholder="e.g. Midterm Assessment">
                    </div>

                    <div class="rv-form-group">
                        <label class="rv-label">Description (optional)</label>
                        <textarea name="description" class="rv-textarea" placeholder="Brief overview..."></textarea>
                    </div>

                    <div class="rv-form-group">
                        <label class="rv-label">Time Limit (minutes, 0 = unlimited)</label>
                        <input type="number" name="time_limit" class="rv-input" min="0" value="0" style="width:50%;">
                    </div>

                    <div class="rv-form-group">
                        <label class="rv-label">Passing Grade (%, leave blank for default 50%)</label>
                        <input type="number" name="passing_grade" class="rv-input" min="1" max="100" placeholder="50" style="width:50%;">
                    </div>
                    <div class="rv-form-group">
                        <label class="rv-label">Max Attempts (base)</label>
                        <input type="number" name="max_attempts" class="rv-input" min="1" max="20" value="1" style="width:50%;">
                    </div>
                    <div class="rv-form-group">
                        <label class="rv-label">Due Date (optional)</label>
                        <input type="datetime-local" name="due_date" class="rv-input" style="width:60%;">
                    </div>
                    <div class="rv-form-group">
                        <label class="rv-label">Who can see this?</label>
                        <input type="hidden" name="visibility" id="visInput_assessment" value="all">

                        <div class="vis-toggle">
                            <button type="button" class="vis-opt active" onclick="setVisibility(this,'assessment')">All Students</button>
                            <button type="button" class="vis-opt" onclick="setVisibility(this,'assessment')">Selected Students</button>
                            <button type="button" class="vis-opt" onclick="setVisibility(this,'assessment')">Except Students</button>
                        </div>

                        <div class="vis-student-picker" id="visPicker_assessment">
                            <div class="vis-search-wrap">
                                <input type="text" id="visSearch_assessment" placeholder="Search students by name, ID, email..." autocomplete="off">
                                <div class="vis-results" id="visResults_assessment"></div>
                            </div>
                            <div class="vis-chips" id="visChips_assessment"></div>
                            <div class="vis-empty-hint" id="visHint_assessment">Search and select students above.</div>
                        </div>
                    </div>

                    <p style="font-size: 16px;color:#aaa;margin-top:4px;">
                        <i class="fas fa-info-circle"></i> Formal assessments appear in the student Assessment tab.
                    </p>

                    <button type="submit" class="rv-btn rv-btn-primary" style="width:100%;margin-top:8px;">
                        <i class="fas fa-clipboard-check"></i> Start Assessment Creation
                    </button>
                </form>

                <div style="margin-top:16px;">
                    <label class="rv-label" style="margin-bottom:10px;">Formal Assessments:</label>
                    <div id="formalAssessmentsList">
                        <p style="font-size: 16px;color:#ccc;text-align:center;padding:1rem 0;">Open this tab to load.</p>
                    </div>
                </div>
            </div>



            {{-- Announcements tab --}}

            <div class="rv-tab-panel" id="tabAnnouncements">

                <form id="announcementForm" method="POST" action="{{ url('/classes/0/announcements') }}">

                    @csrf

                    <div class="rv-form-group">

                        <label class="rv-label">Announcement <span style="color:#e24b4a">*</span></label>

                        <textarea name="message" class="rv-textarea" maxlength="1000" required placeholder="Post an update to this class..."></textarea>

                    </div>

                    <label style="display:flex;align-items:center;gap:8px;margin-bottom:12px;font-size: 16px;color:#666;">

                        <input type="checkbox" name="is_pinned" value="1"> Pin announcement

                    </label>

                    <button type="submit" class="rv-btn rv-btn-primary" style="width:100%;">

                        <i class="fas fa-bullhorn"></i> Post Announcement

                    </button>

                </form>



                <div id="announcementEditOverlay" class="ann-edit-overlay" aria-hidden="true">

                    <div class="ann-edit-modal" role="dialog" aria-modal="true" aria-labelledby="announcementEditTitle">

                        <p class="ann-edit-title" id="announcementEditTitle">Edit Announcement</p>

                        <textarea id="announcementEditInput" class="ann-edit-input" maxlength="1000" placeholder="Update announcement..."></textarea>

                        <div class="ann-edit-actions">

                            <button type="button" class="rv-btn rv-btn-secondary" onclick="closeAnnouncementEditModal()">Cancel</button>

                            <button type="button" class="rv-btn rv-btn-primary" id="announcementEditSaveBtn" onclick="submitAnnouncementEdit()">

                                <i class="fas fa-save"></i> Save Changes

                            </button>

                        </div>

                    </div>

                </div>



                <div id="mcConfirmOverlay" class="mc-confirm-overlay" aria-hidden="true">

                    <div class="mc-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="mcConfirmTitle">

                        <p class="mc-confirm-title" id="mcConfirmTitle">Please Confirm</p>

                        <p class="mc-confirm-text" id="mcConfirmMessage">Are you sure?</p>

                        <div class="mc-confirm-actions">

                            <button type="button" class="rv-btn rv-btn-secondary" onclick="closeManageConfirm()">Cancel</button>

                            <button type="button" class="rv-btn rv-btn-danger" id="mcConfirmProceedBtn">Continue</button>

                        </div>

                    </div>

                </div>



                <div style="margin-top:16px;">

                    <label class="rv-label" style="margin-bottom:10px;">Announcements:</label>

                    <div id="classAnnouncementsList">

                        <p style="font-size: 16px;color:#ccc;text-align:center;padding:1rem 0;">Open this tab to load announcements.</p>

                    </div>

                </div>

            </div>



        </div>
    </div>
</dialog>

<div id="deleteClassConfirmOverlay" class="delete-confirm-overlay" aria-hidden="true">
    <div class="delete-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="deleteClassConfirmTitle">
        <p class="delete-confirm-title" id="deleteClassConfirmTitle">Please Confirm</p>
        <p class="delete-confirm-text" id="deleteClassConfirmMessage">Are you sure?</p>
        <div class="delete-confirm-actions">
            <button type="button" class="delete-confirm-cancel" onclick="closeDeleteClassConfirm()">Cancel</button>
            <button type="button" class="delete-confirm-continue" id="deleteClassConfirmProceedBtn">Continue</button>
        </div>
    </div>
</div>


@endsection

@section('scripts')


<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>



<script>

let currentClassId = null;
let manageConfirmAction = null;
let uploadToastTimer = null;



// ── Change Test Type via Dropdown (Pre-Test vs Post-Test) ──
function onTestTypeChange(val) {
    const isPre = val === 'pre_test';
    const stageInput = document.getElementById('quizStageInput');
    const isFormalInput = document.getElementById('quizIsFormalAssessment');
    if (stageInput) stageInput.value = isPre ? 'pre_test' : 'post_test';
    if (isFormalInput) isFormalInput.value = isPre ? '0' : '1';

    const hint = document.getElementById('testTypeHint');
    const titleLabel = document.getElementById('testTitleLabel');
    const titleInput = document.getElementById('testTitleInput');
    const submitBtn = document.getElementById('quizSubmitBtn');

    if (isPre) {
        if (hint) hint.innerHTML = '<i class="fas fa-info-circle"></i> <strong>Pre-Test:</strong> Diagnostic assessment taken at the start before lecture modules. Always positioned at the beginning.';
        if (titleLabel) titleLabel.innerHTML = 'Pre-Test Title <span style="color:#e24b4a">*</span>';
        if (titleInput) titleInput.placeholder = 'e.g. Pre-Test 1: Diagnostic Assessment';
        if (submitBtn) submitBtn.innerHTML = '<i class="fas fa-brain"></i> Start Pre-Test Creation';
    } else {
        if (hint) hint.innerHTML = '<i class="fas fa-info-circle"></i> <strong>Post-Test:</strong> Final evaluation assessment taken after completing lecture modules. Always positioned at the end.';
        if (titleLabel) titleLabel.innerHTML = 'Post-Test Title <span style="color:#e24b4a">*</span>';
        if (titleInput) titleInput.placeholder = 'e.g. Post-Test: Final Module Evaluation';
        if (submitBtn) submitBtn.innerHTML = '<i class="fas fa-flag-checkered"></i> Start Post-Test Creation';
    }
}

// ── Tab switcher ──
function switchTab(panelId, btn) {
    closeManageConfirm();
    document.querySelectorAll('.rv-tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.rv-tab').forEach(b => b.classList.remove('active'));
    document.getElementById(panelId).classList.add('active');
    btn.classList.add('active');

    if (panelId === 'tabQuiz' && currentClassId) loadModulesForTab(currentClassId, 'pre_post_test', 'prePostTestList');
    if (panelId === 'tabUpload' && currentClassId) loadModulesForTab(currentClassId, 'document', 'documentsList');
    if (panelId === 'tabAssessment' && currentClassId) loadModulesForTab(currentClassId, 'formal_assessment', 'formalAssessmentsList');
    if (panelId === 'tabAnnouncements' && currentClassId) loadClassAnnouncements(currentClassId);
}



// ── Open Students dialog ──
function openStudentsDrawer(classId, className) {

    currentClassId = classId;

    document.getElementById('studentsDrawerSubtitle').textContent = className;

    document.getElementById('dialogStudents').showModal();



    // Reset join link section

    document.getElementById('joinLinkInput').value = '';

    document.getElementById('joinLinkCopyBtn').disabled = true;



    loadCurrentStudents();



    if (!$('#studentSelect').hasClass('select2-hidden-accessible')) {

        $('#studentSelect').select2({

            placeholder: 'Search by name or ID...',

            allowClear: true,

            multiple: true,

            minimumInputLength: 1,

            dropdownParent: $('#dialogStudents'),

            ajax: {

                url: "{{ route('students.search') }}",

                dataType: 'json',

                delay: 200,

                data: params => ({ q: params.term }),

                processResults: data => ({ results: data.results }),

                cache: true

            }

        });

    }

}



// ── Open Modules dialog ──
function openModulesDrawer(classId, className) {
    currentClassId = classId;
    document.getElementById('modulesDrawerSubtitle').textContent = className;
    document.getElementById('moduleClassId').value = classId;
    document.getElementById('quizClassId').value = classId;
    document.getElementById('quizDraftForm').action = "{{ url('/quiz/create-draft') }}/" + classId;
    document.getElementById('assessmentClassId').value = classId;
    document.getElementById('assessmentDraftForm').action = "{{ url('/quiz/create-draft') }}/" + classId;
    document.getElementById('announcementForm').action = "{{ url('/classes') }}/" + classId + "/announcements";

    // Reset to pre-test
    var sel = document.getElementById('testTypeSelect');
    if (sel) sel.value = 'pre_test';
    onTestTypeChange('pre_test');
    if (typeof resetLectureUploadFields === 'function') {
        resetLectureUploadFields();
    }
    switchTab('tabQuiz', document.querySelector('.rv-tab'));
    ['doc','quiz','assessment'].forEach(resetVisibilityPicker);
    document.getElementById('dialogModules').showModal();
}



// ── Load current students ──
function loadCurrentStudents() {

    $('#currentStudentsList').html('<p style="font-size: 16px;color:#ccc;text-align:center;padding:1rem 0;">Loading...</p>');



    $.get("{{ url('/classes') }}/" + currentClassId + "/students", function(data) {

        if (!data.students || data.students.length === 0) {

            $('#currentStudentsList').html('<p style="font-size: 16px;color:#ccc;text-align:center;padding:1rem 0;">No students yet.</p>');

            return;

        }



        const programLabel = p => p ? `<span style="font-size: 16px;padding:2px 7px;border-radius:99px;background:#f3f3f3;color:#666;margin-left:6px;">${p.charAt(0).toUpperCase()+p.slice(1)}</span>` : '';



        const html = data.students.map(s => `
            <div class="rv-student-item" data-student-id="${s.id}">
                <span style="flex:1;min-width:0;overflow-wrap:anywhere;word-break:break-word;">${s.text}${programLabel(s.program)}</span>
                <button class="rv-btn rv-btn-danger" style="height:28px;padding:0 10px;font-size: 14px;flex-shrink:0;" onclick="removeStudent(this, ${s.id})">
                    Remove
                </button>
            </div>
        `).join('');



        $('#currentStudentsList').html(html);

    }).fail(() => {

        $('#currentStudentsList').html('<p style="font-size: 16px;color:#e24b4a;text-align:center;">Failed to load.</p>');

    });

}



// ── Add students ──
$(document).ready(function () {

    $('#addSelectedStudentsBtn').on('click', function () {

        const selected = $('#studentSelect').val() || [];

        if (selected.length === 0) {

            showUploadValidationToast('Select at least one student.', 'warn');

            return;

        }



        $.post("{{ url('/classes') }}/" + currentClassId + "/students", {

            _token: '{{ csrf_token() }}',

            student_ids: selected

        }).done(function () {

            loadCurrentStudents();

            $('#studentSelect').val(null).trigger('change');

        }).fail(() => showUploadValidationToast('Failed to add students.', 'error'));

    });

});



// ── Remove student ──
function removeStudent(triggerBtn, id) {

    openManageConfirm('Remove this student from the class?', function () {

        $.ajax({

            url: "{{ url('/classes') }}/" + currentClassId + "/students/" + id,

            type: 'DELETE',

            data: { _token: '{{ csrf_token() }}' }

        }).done(function () {

            const studentRow = triggerBtn ? triggerBtn.closest('.rv-student-item') : null;

            if (studentRow) {

                studentRow.remove();

            }



            if (!document.querySelector('#currentStudentsList .rv-student-item')) {

                $('#currentStudentsList').html('<p style="font-size: 16px;color:#ccc;text-align:center;padding:1rem 0;">No students yet.</p>');

            }



            showUploadValidationToast('Student removed.', 'success');

            loadCurrentStudents();

        })

          .fail(() => showUploadValidationToast('Failed to remove student.', 'error'));

    });

}



// ── Load modules by type ──
function loadModulesForTab(classId, type, containerId) {
    $('#' + containerId).html('<p style="font-size: 16px;color:#ccc;text-align:center;padding:1rem 0;">Loading...</p>');

    $.get("{{ url('/classes') }}/" + classId + "/modules/list", function (data) {
        const all = data.modules || [];

        const filtered = all.filter(function (m) {
            const isPreTest = m.quiz_stage === 'pre_test' || (m.is_quiz && !m.is_formal_assessment && m.quiz_stage !== 'post_test');
            const isPostTest = m.quiz_stage === 'post_test';
            const isFormal = m.is_formal_assessment && m.quiz_stage !== 'post_test' && m.quiz_stage !== 'pre_test';
            const isDoc = !m.is_quiz && m.type !== 'Quiz' && !m.is_formal_assessment;

            if (type === 'pre_post_test' || type === 'pre_test' || type === 'pre_assessment') return (isPreTest || isPostTest);
            if (type === 'document') return isDoc;
            if (type === 'formal_assessment') return isFormal;
            return false;
        });

        if (filtered.length === 0) {
            $('#' + containerId).html('<p style="font-size: 16px;color:#ccc;text-align:center;padding:1rem 0;">None yet.</p>');
            return;
        }

        let html = '';
        filtered.forEach(function (m) {
            const isPre = m.quiz_stage === 'pre_test' || (m.is_quiz && !m.is_formal_assessment && m.quiz_stage !== 'post_test');
            let badge = '';
            if (type === 'pre_post_test') {
                badge = isPre
                    ? '<span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:99px;background:#1d9e75;color:#fff;margin-left:6px;text-transform:uppercase;">Pre-Test</span>'
                    : '<span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:99px;background:#7b1fa2;color:#fff;margin-left:6px;text-transform:uppercase;">Post-Test</span>';
            }

            let editBtn = '';
            if (m.edit_url) {
                editBtn = '<a href="' + m.edit_url + '" class="rv-btn rv-btn-secondary" style="height:28px;padding:0 10px;font-size:16px;text-decoration:none;" title="Edit Questions"><i class="fas fa-pen"></i></a>';
            }

            let viewBtn = '';
            if (m.file_path) {
                viewBtn = '<a href="' + m.file_path + '" target="_blank" class="rv-btn rv-btn-secondary" style="height:28px;padding:0 10px;font-size:16px;text-decoration:none;"><i class="fas fa-eye"></i></a>';
            }

            let duplicateBtn = '<button class="rv-btn rv-btn-secondary" title="Duplicate (New clean copy)" style="height:28px;padding:0 10px;font-size:16px;" onclick="duplicateModuleFromTab(' + m.id + ', \'' + type + '\', \'' + containerId + '\')"><i class="fas fa-copy"></i></button>';

            let toggleStatusBtn = '';
            if (m.is_quiz || m.is_formal_assessment) {
                if (m.is_open) {
                    toggleStatusBtn = '<button class="rv-btn rv-btn-secondary" title="Close Exam" style="height:28px;padding:0 8px;font-size:12px;font-weight:600;color:#dc2626;border-color:#fca5a5;background:#fef2f2;" onclick="toggleModuleStatusFromTab(' + m.id + ', \'close\', \'' + type + '\', \'' + containerId + '\')"><i class="fas fa-lock"></i> Close</button>';
                } else {
                    toggleStatusBtn = '<button class="rv-btn rv-btn-secondary" title="Open Exam" style="height:28px;padding:0 8px;font-size:12px;font-weight:600;color:#16a34a;border-color:#bbf7d0;background:#f0fdf4;" onclick="toggleModuleStatusFromTab(' + m.id + ', \'open\', \'' + type + '\', \'' + containerId + '\')"><i class="fas fa-unlock"></i> Open</button>';
                }
            }

            let statusBadge = '';
            if (m.is_quiz || m.is_formal_assessment) {
                if (m.is_open) {
                    statusBadge = '<span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:99px;background:#dcfce7;color:#166534;margin-left:6px;"><i class="fas fa-check-circle"></i> Open</span>';
                } else if (m.is_upcoming) {
                    statusBadge = '<span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:99px;background:#fef3c7;color:#92400e;margin-left:6px;"><i class="fas fa-clock"></i> Opens ' + (m.available_at || 'Soon') + '</span>';
                } else {
                    statusBadge = '<span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:99px;background:#fee2e2;color:#991b1b;margin-left:6px;"><i class="fas fa-lock"></i> Closed</span>';
                }
            }

            let dateMeta = '';
            if (m.available_at && !m.is_upcoming) {
                dateMeta += ' · Opened ' + m.available_at;
            }
            if (m.due_date) {
                dateMeta += ' · Due ' + m.due_date;
            }

            html += '<div class="rv-module-item">' +
                '<div style="flex:1;min-width:0;">' +
                    '<div class="rv-module-title">' + m.title + ' ' + badge + ' ' + statusBadge + '</div>' +
                    '<div class="rv-module-meta">' + m.created_at + dateMeta + '</div>' +
                '</div>' +
                '<div class="rv-module-actions" style="display:flex;align-items:center;gap:6px;flex-shrink:0;">' +
                    toggleStatusBtn +
                    editBtn +
                    duplicateBtn +
                    viewBtn +
                    '<button class="rv-btn rv-btn-danger" style="height:28px;padding:0 10px;font-size:16px;flex-shrink:0;" title="Delete" onclick="deleteModuleFromTab(' + m.id + ', \'' + type + '\', \'' + containerId + '\')">' +
                        '<i class="fas fa-trash"></i>' +
                    '</button>' +
                '</div>' +
                '</div>';
        });

        $('#' + containerId).html(html);
    }).fail(function () {
        $('#' + containerId).html('<p style="font-size: 16px;color:#e24b4a;text-align:center;">Failed to load.</p>');
    });
}

// ── Toggle exam status (Open / Close with Confirmation) ──
function toggleModuleStatusFromTab(moduleId, action, type, containerId) {
    const promptText = (action === 'open' || action === 'reopen')
        ? 'Are you sure you want to OPEN this exam now? Students will immediately be able to take it.'
        : 'Are you sure you want to CLOSE this exam now? Students will no longer be able to take it.';

    openManageConfirm(promptText, function () {
        $.ajax({
            url: "{{ url('/modules') }}/" + moduleId + "/toggle-status",
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                action: action
            }
        }).done(function (res) {
            loadModulesForTab(currentClassId, type, containerId);
            showUploadValidationToast(res.message || 'Exam status updated.', 'success');
        }).fail(function (xhr) {
            showUploadValidationToast(xhr.responseJSON?.message || 'Failed to update exam status.', 'error');
        });
    });
}

// ── Duplicate module from a typed tab ──
function duplicateModuleFromTab(moduleId, type, containerId) {
    openManageConfirm('Duplicate this item? A fresh reusable copy with no student data will be created.', function () {
        $.ajax({
            url: "{{ url('/modules') }}/" + moduleId + "/duplicate",
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' }
        }).done(function () {
            loadModulesForTab(currentClassId, type, containerId);
            showUploadValidationToast('Item duplicated successfully.', 'success');
        }).fail(function (xhr) {
            showUploadValidationToast(xhr.responseJSON?.message || 'Failed to duplicate.', 'error');
        });
    });
}

// ── Delete module from a typed tab ──
function deleteModuleFromTab(moduleId, type, containerId) {
    openManageConfirm('Delete this item? This cannot be undone.', function () {
        $.ajax({
            url: "{{ url('/modules') }}/" + moduleId,
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' }
        }).done(() => {
            loadModulesForTab(currentClassId, type, containerId);
            showUploadValidationToast('Item deleted.', 'success');
        })
          .fail(xhr => showUploadValidationToast(xhr.responseJSON?.message || 'Failed to delete.', 'error'));
    });
}



function loadClassAnnouncements(classId) {

    $('#classAnnouncementsList').html('<p style="font-size: 16px;color:#ccc;text-align:center;padding:1rem 0;">Loading...</p>');



    $.get("{{ url('/classes') }}/" + classId + "/announcements/feed", function (data) {

        const items = data.announcements || [];



        if (items.length === 0) {

            $('#classAnnouncementsList').html('<p style="font-size: 16px;color:#ccc;text-align:center;padding:1rem 0;">No announcements yet.</p>');

            return;

        }



        const html = items.map(a => {

            const pinned = a.is_pinned ? '<span style="font-size: 16px;padding:2px 8px;border-radius:99px;background:#f8df9f;color:#825b00;">Pinned</span>' : '';

            const edit = a.can_edit

                ? `<button class="rv-btn rv-btn-secondary" style="height:24px;padding:0 8px;font-size: 16px;" onclick='editAnnouncement(${a.id}, ${JSON.stringify(a.message)})'><i class="fas fa-pen"></i></button>`

                : '';

            const del = a.can_delete

                ? `<button class="rv-btn rv-btn-danger" style="height:24px;padding:0 8px;font-size: 16px;" onclick="deleteAnnouncement(${a.id})"><i class="fas fa-trash"></i></button>`

                : '';



            return `
                <div style="border:1px solid #f1f1f1;border-radius:8px;padding:10px;margin-bottom:8px;${a.is_pinned ? 'background:#fffaf0;border-color:#f2d17f;' : ''}">
                    <div style="display:flex;justify-content:space-between;gap:8px;margin-bottom:5px;align-items:flex-start;">
                        <div style="font-size: 14px;color:#888;display:flex;gap:8px;align-items:center;flex-wrap:wrap;flex:1;min-width:0;">
                            ${pinned}
                            <span style="font-weight:600;color:#333;">${a.author}</span>
                            <span>${a.created_human ?? ''}</span>
                        </div>
                        <div style="display:flex;gap:6px;align-items:center;flex-shrink:0;">
                            ${edit}
                            ${del}
                        </div>
                    </div>
                    <div style="font-size: 15px;color:#222;overflow-wrap:anywhere;word-break:break-word;line-height:1.45;">${$('<div/>').text(a.message).html()}</div>
                </div>
            `;

        }).join('');



        $('#classAnnouncementsList').html(html);

    }).fail(() => {

        $('#classAnnouncementsList').html('<p style="font-size: 16px;color:#e24b4a;text-align:center;padding:1rem 0;">Failed to load announcements.</p>');

    });

}



function deleteAnnouncement(announcementId) {

    openManageConfirm('Delete this announcement?', function () {

        $.ajax({

            url: "{{ url('/announcements') }}/" + announcementId,

            type: 'POST',

            data: {

                _token: '{{ csrf_token() }}',

                _method: 'DELETE'

            }

        }).done(() => {

            loadClassAnnouncements(currentClassId);

            showUploadValidationToast('Announcement deleted.', 'success');

        })

          .fail(() => showUploadValidationToast('Failed to delete announcement.', 'error'));

    });

}



let editingAnnouncementId = null;



function closeAnnouncementEditModal() {

    const overlay = document.getElementById('announcementEditOverlay');

    const input = document.getElementById('announcementEditInput');

    const saveBtn = document.getElementById('announcementEditSaveBtn');



    editingAnnouncementId = null;

    overlay.classList.remove('show');

    overlay.setAttribute('aria-hidden', 'true');

    input.value = '';

    saveBtn.disabled = false;

}



function editAnnouncement(announcementId, currentMessage) {

    editingAnnouncementId = announcementId;



    const overlay = document.getElementById('announcementEditOverlay');

    const input = document.getElementById('announcementEditInput');

    overlay.classList.add('show');

    overlay.setAttribute('aria-hidden', 'false');

    input.value = (currentMessage || '').trim();

    setTimeout(function () {

        input.focus();

        input.setSelectionRange(input.value.length, input.value.length);

    }, 0);

}



function submitAnnouncementEdit() {

    if (!editingAnnouncementId) {

        return;

    }



    const input = document.getElementById('announcementEditInput');

    const saveBtn = document.getElementById('announcementEditSaveBtn');

    const trimmedMessage = input.value.trim();



    if (trimmedMessage === '') {

        showUploadValidationToast('Announcement message is required.');

        input.focus();

        return;

    }



    saveBtn.disabled = true;



    $.ajax({

        url: "{{ url('/announcements') }}/" + editingAnnouncementId,

        type: 'POST',

        data: {

            _token: '{{ csrf_token() }}',

            _method: 'PATCH',

            message: trimmedMessage,

        }

    }).done(() => {

        closeAnnouncementEditModal();

        loadClassAnnouncements(currentClassId);

    })

      .fail(xhr => {

          saveBtn.disabled = false;

          const message = xhr.responseJSON?.message || 'Failed to update announcement.';

          showUploadValidationToast(message);

      });

}



document.addEventListener('keydown', function (e) {

    if (e.key === 'Escape') {

        const overlay = document.getElementById('announcementEditOverlay');

        if (overlay && overlay.classList.contains('show')) {

            closeAnnouncementEditModal();

        }

    }

});



$('#announcementForm').on('submit', function (e) {

    e.preventDefault();



    $.post($(this).attr('action'), $(this).serialize())

        .done(function () {

            document.getElementById('announcementForm').reset();

            loadClassAnnouncements(currentClassId);

        })

        .fail(xhr => {

            const message = xhr.responseJSON?.message || 'Failed to post announcement.';

            showUploadValidationToast(message, 'error');

        });

});



// -”€-”€ Visibility helpers -”€-”€

const visDebounceTimers = {};
const visSelectedStudents = { doc: {}, quiz: {}, assessment: {} };

function setVisibility(btn, form) {
    btn.closest('.vis-toggle').querySelectorAll('.vis-opt').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const label = btn.textContent.trim();
    const map = { 'All Students': 'all', 'Selected Students': 'selected', 'Except Students': 'except' };
    const val = map[label] || 'all';
    document.getElementById('visInput_' + form).value = val;

    const picker = document.getElementById('visPicker_' + form);
    const hint = document.getElementById('visHint_' + form);

    if (val === 'all') {
        picker.style.display = 'none';
    } else {
        picker.style.display = 'block';
        hint.textContent = val === 'selected'
            ? 'Only these students will see this content.'
            : 'Everyone EXCEPT these students will see this content.';
    }
}

function resetVisibilityPicker(form) {
    visSelectedStudents[form] = {};
    const picker = document.getElementById('visPicker_' + form);
    if (picker) {
        picker.style.display = 'none';
        document.getElementById('visSearch_' + form).value = '';
        document.getElementById('visResults_' + form).innerHTML = '';
        document.getElementById('visResults_' + form).style.display = 'none';
        document.getElementById('visChips_' + form).innerHTML = '';
        document.getElementById('visHint_' + form).textContent = 'Search and select students above.';
    }
    document.getElementById('visInput_' + form).value = 'all';

    const formKeyToTab = { doc: 'tabUpload', quiz: 'tabQuiz', assessment: 'tabAssessment' };
    const panel = document.getElementById(formKeyToTab[form]);
    if (panel) {
        panel.querySelectorAll('.vis-opt').forEach((b, i) => {
            b.classList.toggle('active', i === 0);
        });
    }
}



function addVisChip(form, id, name) {

    if (visSelectedStudents[form][id]) return;

    visSelectedStudents[form][id] = name;



    const chip = document.createElement('span');

    chip.className = 'vis-chip';

    chip.dataset.userId = id;

    chip.innerHTML = name + ' <button type="button" class="vis-chip-remove" onclick="removeVisChip(this,\'' + form + '\',' + id + ')">&times;</button>';

    document.getElementById('visChips_' + form).appendChild(chip);

    document.getElementById('visHint_' + form).style.display = 'none';

}



function removeVisChip(btn, form, id) {

    delete visSelectedStudents[form][id];

    btn.closest('.vis-chip').remove();

    if (Object.keys(visSelectedStudents[form]).length === 0) {

        document.getElementById('visHint_' + form).style.display = '';

    }

}



['doc', 'quiz', 'assessment'].forEach(function (form) {

    const input = document.getElementById('visSearch_' + form);

    const resultsDiv = document.getElementById('visResults_' + form);
    if (!input || !resultsDiv) return;



    input.addEventListener('input', function () {

        clearTimeout(visDebounceTimers[form]);

        const q = this.value.trim();

        if (q.length < 1) { resultsDiv.style.display = 'none'; return; }



        visDebounceTimers[form] = setTimeout(function () {

            $.get("{{ url('/classes') }}/" + currentClassId + "/students/search", { q: q }, function (data) {

                if (!data || data.length === 0) {

                    resultsDiv.innerHTML = '<div style="padding:10px;font-size: 16px;color:#ccc;text-align:center;">No results</div>';

                    resultsDiv.style.display = 'block';

                    return;

                }



                resultsDiv.innerHTML = data.map(function (s) {

                    const prog = s.program

                        ? '<span class="vis-result-program">' + s.program.charAt(0).toUpperCase() + s.program.slice(1) + '</span>'

                        : '';

                    const idnum = s.idnumber ? ' <span class="vis-result-id">(' + s.idnumber + ')</span>' : '';

                    return '<div class="vis-result-item" data-id="' + s.id + '" data-name="' + $('<span>').text(s.name).html() + '">'

                        + '<span class="vis-result-name">' + $('<span>').text(s.name).html() + idnum + '</span> '

                        + '<span class="vis-result-email">' + $('<span>').text(s.email).html() + '</span>'

                        + prog

                        + '</div>';

                }).join('');

                resultsDiv.style.display = 'block';

            });

        }, 300);

    });



    $(document).on('click', '#visResults_' + form + ' .vis-result-item', function () {

        addVisChip(form, $(this).data('id'), $(this).data('name'));

        resultsDiv.style.display = 'none';

        input.value = '';

    });



    input.addEventListener('blur', function () {

        setTimeout(function () { resultsDiv.style.display = 'none'; }, 200);

    });

});



function injectVisUserIds(form, formData) {

    const ids = Object.keys(visSelectedStudents[form]);

    ids.forEach(function (id) {

        if (formData instanceof FormData) {

            formData.append('visible_user_ids[]', id);

        }

    });

}



function injectVisHiddenInputs(form, formEl) {

    formEl.querySelectorAll('input[name="visible_user_ids[]"]').forEach(function (el) { el.remove(); });

    Object.keys(visSelectedStudents[form]).forEach(function (id) {

        const inp = document.createElement('input');

        inp.type = 'hidden';

        inp.name = 'visible_user_ids[]';

        inp.value = id;

        formEl.appendChild(inp);

    });

}







function closeManageConfirm() {

    const overlay = document.getElementById('mcConfirmOverlay');

    if (!overlay) {

        return;

    }



    overlay.classList.remove('show');

    overlay.setAttribute('aria-hidden', 'true');

    manageConfirmAction = null;

}



function openManageConfirm(message, onConfirm) {

    const overlay = document.getElementById('mcConfirmOverlay');

    const messageEl = document.getElementById('mcConfirmMessage');

    const proceedBtn = document.getElementById('mcConfirmProceedBtn');

    if (!overlay || !messageEl || !proceedBtn) {

        return;

    }



    const activeDialogPanel = document.querySelector('dialog[open] .rv-dialog-panel')

        || document.querySelector('#dialogModules .rv-dialog-panel')

        || document.querySelector('#dialogStudents .rv-dialog-panel');

    if (activeDialogPanel && overlay.parentElement !== activeDialogPanel) {

        activeDialogPanel.appendChild(overlay);

    }



    messageEl.textContent = message;

    manageConfirmAction = onConfirm;

    overlay.classList.add('show');

    overlay.setAttribute('aria-hidden', 'false');



    proceedBtn.onclick = function () {

        const action = manageConfirmAction;

        closeManageConfirm();

        if (typeof action === 'function') {

            action();

        }

    };

}



function showUploadValidationToast(message, type) {

    type = type || 'error';

    let toast = document.getElementById('mcUploadToast');

    const activeDialogPanel = document.querySelector('dialog[open] .rv-dialog-panel')

        || document.querySelector('#dialogModules .rv-dialog-panel')

        || document.querySelector('#dialogStudents .rv-dialog-panel');

    if (!toast) {

        toast = document.createElement('div');

        toast.id = 'mcUploadToast';

        toast.className = 'mc-toast';

        (activeDialogPanel || document.body).appendChild(toast);

    } else if (activeDialogPanel && toast.parentElement !== activeDialogPanel) {

        activeDialogPanel.appendChild(toast);

    }



    toast.classList.remove('success', 'error', 'warn');

    toast.classList.add(type);

    toast.textContent = message;

    toast.classList.add('show');



    if (uploadToastTimer) {

        clearTimeout(uploadToastTimer);

    }



    uploadToastTimer = setTimeout(function () {

        toast.classList.remove('show');

    }, 2600);

}



// Intercept quiz/assessment form submits to inject visible_user_ids

$('#quizDraftForm').on('submit', function (e) {

    const visibility = document.getElementById('visInput_quiz').value;

    if ((visibility === 'selected' || visibility === 'except') && Object.keys(visSelectedStudents['quiz']).length === 0) {

        e.preventDefault();

        showUploadValidationToast('Please select at least one student.');

        return;

    }



    injectVisHiddenInputs('quiz', this);

});



$('#assessmentDraftForm').on('submit', function (e) {
    const visibility = document.getElementById('visInput_assessment').value;
    if ((visibility === 'selected' || visibility === 'except') && Object.keys(visSelectedStudents['assessment']).length === 0) {
        e.preventDefault();
        showUploadValidationToast('Please select at least one student.');
        return;
    }
    injectVisHiddenInputs('assessment', this);
});



// ── Dynamic Subpart Upload Helpers (1.1, 1.2, 1.3...) ──
function updateSubpartNumbers() {
    const container = document.getElementById('lectureContentUploadFields');
    if (!container) return;
    const rows = container.querySelectorAll('.lecture-content-upload-row');
    rows.forEach(function (row, idx) {
        const badge = row.querySelector('.subpart-badge-num');
        if (badge) {
            badge.textContent = '1.' + (idx + 1);
        }
        const removeBtn = row.querySelector('.lecture-remove-upload');
        if (removeBtn) {
            removeBtn.style.display = rows.length > 1 ? 'inline-flex' : 'none';
        }
    });
}

function addLectureUploadField() {
    const container = document.getElementById('lectureContentUploadFields');
    if (!container) return;
    const count = container.querySelectorAll('.lecture-content-upload-row').length + 1;
    const row = document.createElement('div');
    row.className = 'lecture-content-upload-row';
    row.style.cssText = 'background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin-bottom:10px;';
    row.innerHTML =
        '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">' +
            '<span class="subpart-badge-num" style="font-size:13px;font-weight:700;color:#172b4d;background:#e2e8f0;padding:2px 8px;border-radius:6px;">1.' + count + '</span>' +
            '<button type="button" class="rv-btn rv-btn-danger lecture-remove-upload" style="height:26px;padding:0 8px;font-size:12px;" onclick="removeLectureUploadField(this)">' +
                '<i class="fas fa-trash"></i>' +
            '</button>' +
        '</div>' +
        '<div class="rv-form-group" style="margin-bottom:8px;">' +
            '<label class="rv-label" style="font-size:13px;color:#555;">Topic / Subdomain Title <span style="color:#e24b4a">*</span></label>' +
            '<input type="text" name="subpart_titles[]" class="rv-input" required maxlength="150" placeholder="e.g. Subtopic ' + count + '">' +
        '</div>' +
        '<div class="rv-form-group" style="margin-bottom:0;">' +
            '<label class="rv-label" style="font-size:13px;color:#555;">File (PDF, PPT, DOCX - max 100MB) <span style="color:#e24b4a">*</span></label>' +
            '<input type="file" name="files[]" class="rv-input" accept=".pdf,.ppt,.pptx,.doc,.docx,.mov" required style="padding:7px 12px;background:#fff;">' +
        '</div>';
    container.appendChild(row);
    updateSubpartNumbers();
}

function removeLectureUploadField(btn) {
    const row = btn.closest('.lecture-content-upload-row');
    if (row) {
        row.remove();
        updateSubpartNumbers();
    }
}

function resetLectureUploadFields() {
    const container = document.getElementById('lectureContentUploadFields');
    if (!container) return;
    const rows = container.querySelectorAll('.lecture-content-upload-row');
    rows.forEach(function (r, i) {
        if (i > 0) r.remove();
    });
    const firstRow = container.querySelector('.lecture-content-upload-row');
    if (firstRow) {
        const titleInput = firstRow.querySelector('input[type="text"]');
        const fileInput = firstRow.querySelector('input[type="file"]');
        if (titleInput) titleInput.value = '';
        if (fileInput) fileInput.value = '';
    }
    updateSubpartNumbers();
}

// ── Upload module form ──
$('#moduleUploadForm').on('submit', function (e) {
    e.preventDefault();

    const visibility = document.getElementById('visInput_doc').value;
    if ((visibility === 'selected' || visibility === 'except') && Object.keys(visSelectedStudents['doc']).length === 0) {
        showUploadValidationToast('Please select at least one student.');
        return;
    }

    const formData = new FormData(this);
    injectVisUserIds('doc', formData);

    $.ajax({
        url: "{{ url('/classes') }}/" + currentClassId + "/modules",
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    }).done(function (res) {
        showUploadValidationToast(res.success || 'Domain & Subparts Uploaded!', 'success');
        document.getElementById('moduleUploadForm').reset();
        resetLectureUploadFields();
        resetVisibilityPicker('doc');
        loadModulesForTab(currentClassId, 'document', 'documentsList');
    }).fail(function (xhr) {
        showUploadValidationToast('Upload failed: ' + (xhr.responseJSON?.message || 'Unknown error'), 'error');
    });
});



// -"€-"€ Join Link functionality -"€-"€

function generateJoinLink() {

    const genBtn = document.getElementById('joinLinkGenerateBtn');

    const copyBtn = document.getElementById('joinLinkCopyBtn');

    const input = document.getElementById('joinLinkInput');



    genBtn.classList.add('generating');

    genBtn.disabled = true;



    fetch(`/classes/${currentClassId}/join-link`, {

        method: 'POST',

        headers: {

            'X-CSRF-TOKEN': '{{ csrf_token() }}',

            'Accept': 'application/json'

        }

    })

    .then(r => r.json())

    .then(data => {

        if (data.success) {

            input.value = data.url;

            copyBtn.disabled = false;

        } else {

            input.value = 'Error: ' + (data.message || 'Failed to generate');

        }

    })

    .catch(() => {

        input.value = 'Error generating link';

    })

    .finally(() => {

        genBtn.classList.remove('generating');

        genBtn.disabled = false;

    });

}



// Toggle delete mode for classes
let deleteModeActive = false;
function toggleDeleteMode() {
    deleteModeActive = !deleteModeActive;
    const toggleBtn = document.getElementById('deleteModeToggle');
    const deleteActions = document.querySelectorAll('.class-delete-action');

    if (deleteModeActive) {
        toggleBtn.classList.add('active');
        toggleBtn.innerHTML = '<i class="fas fa-times"></i> Cancel Delete';
        deleteActions.forEach(el => el.style.display = 'block');
    } else {
        toggleBtn.classList.remove('active');
        toggleBtn.innerHTML = '<i class="fas fa-trash-alt"></i> Delete Classes';
        deleteActions.forEach(el => el.style.display = 'none');
    }
}

// Delete class confirmation modal
let currentDeleteForm = null;

function openDeleteClassConfirm(btn) {
    currentDeleteForm = btn.closest('.delete-class-form');
    const className = currentDeleteForm.dataset.className;
    document.getElementById('deleteClassConfirmMessage').textContent = 'Delete class "' + className + '"? This cannot be undone.';
    document.getElementById('deleteClassConfirmOverlay').setAttribute('aria-hidden', 'false');
}

function closeDeleteClassConfirm() {
    document.getElementById('deleteClassConfirmOverlay').setAttribute('aria-hidden', 'true');
    currentDeleteForm = null;
}

document.getElementById('deleteClassConfirmProceedBtn').addEventListener('click', function() {
    if (currentDeleteForm) {
        currentDeleteForm.submit();
    }
});

function copyJoinLink() {

    const input = document.getElementById('joinLinkInput');

    const btn = document.getElementById('joinLinkCopyBtn');



    if (!input.value) return;



    input.select();

    input.setSelectionRange(0, 99999);



    navigator.clipboard.writeText(input.value).then(() => {

        btn.classList.add('copied');

        btn.innerHTML = '<i class="fas fa-check"></i>';

        setTimeout(() => {

            btn.classList.remove('copied');

            btn.innerHTML = '<i class="fas fa-copy"></i>';

        }, 2000);

    });

}

</script>


@endsection
