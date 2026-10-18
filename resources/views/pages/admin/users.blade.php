@extends('layouts.appAdmin')

@section('title', 'User Management')
@section('page-heading', 'User Management')

@section('header-actions')
    <div class="no-print" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <button type="button" onclick="window.print()" class="rv-btn rv-btn-secondary" style="display:inline-flex;align-items:center;gap:6px;background:#fff;border:1.5px solid #245E55;color:#245E55;font-weight:600;">
            <i class="fas fa-print"></i> Print User Roster
        </button>
        <button type="button" onclick="exportUsersToExcel()" class="rv-btn rv-btn-primary" style="display:inline-flex;align-items:center;gap:6px;background:#107c41;border:none;color:#fff;font-weight:600;">
            <i class="fas fa-file-excel"></i> Export Excel (.xlsx)
        </button>
    </div>
@endsection

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Users</li>
@endsection

@section('content')
<style>
    .um-print-header,
    .um-print-signatures {
        display: none;
    }

    @media print {
        @page { size: portrait; margin: 12mm; }
        body { background: #fff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        .rv-sidebar, .rv-topbar, .rv-overlay, .no-print, .um-sub, .um-pagination, .um-modal-overlay { display: none !important; }
        .rv-main { margin-left: 0 !important; width: 100% !important; }
        .rv-content { padding: 0 !important; }
        .um-print-header {
            display: block !important;
            border-bottom: 2.5px solid #245E55;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .um-print-signatures {
            display: grid !important;
            grid-template-columns: repeat(3, 1fr);
            gap: 28px;
            margin-top: 32px;
            padding-top: 16px;
            page-break-inside: avoid;
        }
        .um-card { border: 1px solid #cbd5e1 !important; }
        .um-table th, .um-table td { padding: 8px 10px !important; font-size: 12px !important; }
    }

    .um-wrap { display: flex; flex-direction: column; gap: 20px; }
    .um-sub  { font-size: 15px; color: #64748b; margin: -6px 0 8px; }

    .um-card {
        background: #fff;
        border: 1px solid #ebebeb;
        border-radius: 14px;
        overflow: hidden;
    }

    .um-card-head {
        padding: 16px 20px;
        border-bottom: 1px solid #f3f3f3;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
    }

    .um-card-title { font-size: 20px; font-weight: 500; color: #111; }       

    .um-search {
        display: flex;
        gap: 9px;
    }

    .um-search input {
        border: 1px solid #e5e5e5;
        border-radius: 9px;
        padding: 9px 13px;
        font-size: 16px;
        outline: none;
        width: 260px;
    }

    .um-search input:focus { border-color: #002060; }

    .um-search button {
        background: #002060;
        color: #fff;
        border: none;
        border-radius: 9px;
        padding: 9px 16px;
        font-size: 15px;
        cursor: pointer;
    }

    /* Table */
    .um-table { width: 100%; border-collapse: collapse; }

    .um-table th {
        font-size: 14px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        padding: 12px 18px;
        border-bottom: 1px solid #f3f3f3;
        text-align: left;
        background: #fafafa;
    }

    .um-table td {
        font-size: 16px;
        color: #333;
        padding: 14px 18px;
        border-bottom: 1px solid #f7f7f7;
        vertical-align: middle;
    }

    .um-table tr:last-child td { border-bottom: none; }
    .um-table tr:hover td { background: #fafafa; }

    /* Badges */
    .um-badge {
        display: inline-block;
        font-size: 15px;
        font-weight: 500;
        padding: 4px 10px;
        border-radius: 99px;
    }

    .um-badge-admin     { background: #fdecea; color: #c0392b; }
    .um-badge-teacher   { background: #e8f5e9; color: #2e7d32; }
    .um-badge-student   { background: #e3f2fd; color: #1565c0; }
    .um-badge-active    { background: #e8f5e9; color: #2e7d32; }
    .um-badge-pending   { background: #fff8e1; color: #f57f17; }
    .um-badge-rejected  { background: #fdecea; color: #c0392b; }

    /* Action buttons */
    .um-btn {
        font-size: 15px;
        font-weight: 500;
        padding: 7px 13px;
        border-radius: 7px;
        border: none;
        cursor: pointer;
        white-space: nowrap;
    }

    .um-btn-warn   { background: #fff8e1; color: #e65100; border: 1px solid #ffe082; }
    .um-btn-info   { background: #e3f2fd; color: #1565c0; border: 1px solid #90caf9; }
    .um-btn-danger { background: #fdecea; color: #c0392b; border: 1px solid #f5c6cb; }
    .um-btn-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
    .um-btn-warn:hover { background: #fff3cd; }
    .um-btn-info:hover { background: #bbdefb; }
    .um-btn-danger:hover { background: #f8d7da; }
    .um-btn-success:hover { background: #d4edda; }

    .um-empty {
        text-align: center;
        padding: 52px 26px;
        color: #bbb;
        font-size: 16px;
    }

    .um-empty i { font-size: 36px; display: block; margin-bottom: 12px; }

    /* Pagination */
    .um-pagination {
        padding: 16px 20px;
        border-top: 1px solid #f3f3f3;
        background: #fff;
    }

    .um-pagination nav {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        width: 100%;
    }

    .um-pagination nav > div:first-child {
        display: none;
    }

    .um-pagination nav > div:last-child {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        flex-wrap: wrap;
        gap: 12px;
    }

    .um-pagination p,
    .um-pagination .text-muted,
    .um-pagination .small {
        margin: 0;
        font-size: 14px;
        color: #64748b;
    }

    .um-pagination .fw-semibold {
        font-weight: 600;
        color: #1e293b;
    }

    .um-pagination ul.pagination,
    .um-pagination ul {
        display: flex;
        align-items: center;
        list-style: none;
        margin: 0;
        padding: 0;
        gap: 6px;
        flex-wrap: wrap;
    }

    .um-pagination li.page-item,
    .um-pagination li {
        list-style: none;
        display: inline-block;
        margin: 0;
        padding: 0;
    }

    .um-pagination .page-link,
    .um-pagination li a,
    .um-pagination li span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        padding: 0 12px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        color: #334155;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        text-decoration: none;
        transition: all 0.15s ease;
        line-height: 1;
    }

    .um-pagination li a:hover,
    .um-pagination .page-link:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
        color: #002060;
    }

    .um-pagination li.active .page-link,
    .um-pagination li.active span,
    .um-pagination li [aria-current="page"] span {
        background: #002060;
        color: #ffffff;
        border-color: #002060;
        font-weight: 600;
    }

    .um-pagination li.disabled .page-link,
    .um-pagination li.disabled span,
    .um-pagination [aria-disabled="true"] span {
        color: #94a3b8;
        background: #f8fafc;
        border-color: #e2e8f0;
        cursor: not-allowed;
        pointer-events: none;
    }

    .um-pagination svg {
        width: 16px;
        height: 16px;
    }

    @media (max-width: 640px) {
        .um-pagination nav > div:first-child {
            display: flex;
            justify-content: space-between;
            width: 100%;
        }
        .um-pagination nav > div:last-child {
            display: none;
        }
    }

    .um-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(12, 17, 29, 0.58);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 13000;
        padding: 14px;
    }

    .um-modal-overlay.show { display: flex; }

    .um-modal {
        width: min(480px, 96vw);
        background: #0f1f2b;
        color: #f4fbff;
        border: 1px solid rgba(130, 214, 255, 0.25);
        border-radius: 16px;
        box-shadow: 0 24px 64px rgba(0, 0, 0, 0.45);
        padding: 18px;
    }

    .um-modal-title {
        margin: 0 0 10px;
        font-size: 20px;
        font-weight: 500;
        color: #9de8ff;
    }

    .um-modal-text {
        margin: 0;
        font-size: 16px;
        color: #e8f7ff;
        line-height: 1.45;
    }

    .um-modal-input-wrap {
        margin-top: 14px;
        display: none;
    }

    .um-modal-input {
        width: 100%;
        border: 2px solid #79e4ff;
        border-radius: 11px;
        height: 42px;
        background: #0e1a24;
        color: #f6fdff;
        padding: 0 13px;
        outline: none;
        font-size: 16px;
    }

    .um-modal-actions {
        margin-top: 16px;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .um-modal-btn {
        border: none;
        border-radius: 999px;
        padding: 9px 20px;
        font-size: 15px;
        font-weight: 500;
        cursor: pointer;
    }

    .um-modal-btn.cancel {
        background: #006879;
        color: #dbf9ff;
    }

    .um-modal-btn.confirm {
        background: #86ecff;
        color: #0b2b35;
    }

    .um-modal-btn.confirm.warn { background: #ffdca8; color: #5a3500; }
    .um-modal-btn.confirm.danger { background: #ffb0ad; color: #5f1111; }
    .um-modal-btn.confirm.success { background: #a7f2cb; color: #0d4a2f; }
</style>

<div class="um-wrap">
    {{-- Official PCC Printable Header --}}
    <div class="um-print-header">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
            <div style="display:flex;align-items:center;gap:14px;">
                <img src="{{ asset('assets/img/brand/pcc_logo.png') }}" alt="PCC Logo" style="width:62px;height:62px;object-fit:contain;flex-shrink:0;">
                <div>
                    <div style="font-size:15px;font-weight:800;color:#245E55;text-transform:uppercase;letter-spacing:0.6px;">
                        Pasig Catholic College
                    </div>
                    <div style="font-size:12px;color:#475569;margin-top:1px;">
                        College Department &bull; Office of the System Administrator &amp; Registrar
                    </div>
                    <div style="font-size:18px;font-weight:800;color:#0f172a;margin-top:4px;">
                        Official Platform User Roster &amp; Access Masterlist
                    </div>
                </div>
            </div>
            <div style="text-align:right;font-size:11.5px;color:#475569;line-height:1.5;">
                <div><strong>Role Filter:</strong> {{ request('role') ? ucfirst(request('role')) : 'All Roles' }}</div>
                <div><strong>Status Filter:</strong> {{ request('status') ? ucfirst(request('status')) : 'All Statuses' }}</div>
                <div><strong>Date Generated:</strong> {{ now()->format('F d, Y h:i A') }}</div>
            </div>
        </div>
    </div>

    <p class="um-sub">Manage user accounts, roles, status, and access permissions.</p>

    <div class="um-card">
        <div class="um-card-head">
            <span class="um-card-title">All Users</span>
            <div class="no-print" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <form method="GET" action="{{ route('admin.users') }}" class="um-search" style="display:flex;gap:8px;flex-wrap:wrap;">
                    <input type="text"
                           name="q"
                           placeholder="Search name, email, ID..."
                           value="{{ request('q') }}"
                              style="width:220px;">
                          <select name="role" style="border:1px solid #e5e5e5;border-radius:9px;padding:9px 12px;font-size:16px;outline:none;">
                        <option value="">All Roles</option>
                        <option value="student" {{ request('role') === 'student' ? 'selected' : '' }}>Student</option>
                        <option value="teacher" {{ request('role') === 'teacher' ? 'selected' : '' }}>Teacher</option>
                        <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                    </select>
                    <select name="status" style="border:1px solid #e5e5e5;border-radius:9px;padding:9px 12px;font-size:16px;outline:none;">
                        <option value="">All Statuses</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                    <button type="submit"><i class="fas fa-search"></i> Filter</button>
                </form>
                <a href="{{ route('admin.users.export', request()->only('q', 'role', 'status')) }}"
                   class="rv-btn rv-btn-secondary" style="height:40px;font-size:15px;">
                    <i class="fas fa-download"></i> Export CSV
                </a>
            </div>
        </div>

        <table class="um-table" id="umUsersTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>ID Number</th>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Program</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th class="no-print">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td style="color:#bbb;">{{ $user->id }}</td>
                        <td style="font-weight:500;">{{ $user->idnumber }}</td>
                        <td>{{ $user->name ?? '—' }}</td>
                        <td>
                            @php
                                $roleClass = match(true) {
                                    in_array($user->role, ['admin','superadmin']) => 'um-badge-admin',
                                    $user->role === 'teacher' => 'um-badge-teacher',
                                    default => 'um-badge-student',
                                };
                            @endphp
                            <span class="um-badge {{ $roleClass }}">{{ ucfirst($user->role ?? '—') }}</span>
                        </td>
                        <td>
                            @if ($user->program)
                                {{ ucfirst($user->program) }}
                                @if ($user->program_locked)
                                    <span title="Locked" style="margin-left:4px;">🔒</span>
                                @endif
                            @else
                                <span style="color:#bbb;">—</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $statusClass = match($user->status ?? 'pending') {
                                    'active'   => 'um-badge-active',
                                    'rejected' => 'um-badge-rejected',
                                    default    => 'um-badge-pending',
                                };
                            @endphp
                            <span class="um-badge {{ $statusClass }}">{{ ucfirst($user->status ?? 'pending') }}</span>
                        </td>
                        <td style="color:#999;">{{ $user->created_at?->format('d M Y') ?? '—' }}</td>
                        <td class="no-print">
                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                <form method="POST" action="{{ route('admin.users.reset', $user->id) }}"
                                      onsubmit="return submitSimpleConfirm(this, 'Reset password for {{ addslashes($user->idnumber) }}?', 'Reset Password', 'warn')">
                                    @csrf
                                    <button type="submit" class="um-btn um-btn-warn">Reset Password</button>
                                </form>
                                <form method="POST" action="{{ route('admin.users.toggle-status', $user->id) }}"
                                      onsubmit="return submitStatusForm(this, '{{ addslashes($user->idnumber) }}', '{{ $user->status ?? 'pending' }}')">
                                    @csrf
                                    <input type="hidden" name="reason" value="">
                                    @if(($user->status ?? 'pending') === 'active')
                                        <button type="submit" class="um-btn um-btn-danger">Deactivate</button>
                                    @else
                                        <button type="submit" class="um-btn um-btn-success">Activate</button>
                                    @endif
                                </form>
                                @if ($user->program && $user->program_locked)
                                    <form method="POST" action="{{ route('admin.unlock.program', $user) }}"
                                                                                    onsubmit="return submitSimpleConfirm(this, 'Unlock program for {{ addslashes($user->idnumber) }}?', 'Unlock Program', 'success')">
                                        @csrf
                                        <button type="submit" class="um-btn um-btn-info">Unlock Program</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <div class="um-empty">
                                <i class="fas fa-users-slash"></i>
                                No users found
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($users->hasPages())
            <div class="um-pagination">
                {{ $users->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>

    {{-- Official PCC Printable Signature Block --}}
    <div class="um-print-signatures">
        <div style="text-align:center;">
            <div style="border-bottom:1.5px solid #0f172a;height:36px;margin-bottom:6px;"></div>
            <div style="font-weight:700;font-size:12px;color:#0f172a;text-transform:uppercase;">{{ auth()->user()->name ?? 'System Administrator' }}</div>
            <div style="font-size:11px;color:#64748b;">Prepared By &bull; System Administrator</div>
        </div>
        <div style="text-align:center;">
            <div style="border-bottom:1.5px solid #0f172a;height:36px;margin-bottom:6px;"></div>
            <div style="font-weight:700;font-size:12px;color:#0f172a;text-transform:uppercase;">MIS / IT Head</div>
            <div style="font-size:11px;color:#64748b;">Reviewed &amp; Verified By</div>
        </div>
        <div style="text-align:center;">
            <div style="border-bottom:1.5px solid #0f172a;height:36px;margin-bottom:6px;"></div>
            <div style="font-weight:700;font-size:12px;color:#0f172a;text-transform:uppercase;">College Dean</div>
            <div style="font-size:11px;color:#64748b;">Noted &amp; Approved By</div>
        </div>
    </div>
</div>

<div id="umConfirmOverlay" class="um-modal-overlay" aria-hidden="true">
    <div class="um-modal" role="dialog" aria-modal="true" aria-labelledby="umModalTitle">
        <p id="umModalTitle" class="um-modal-title">Please Confirm</p>
        <p id="umModalText" class="um-modal-text">Are you sure?</p>
        <div id="umModalInputWrap" class="um-modal-input-wrap">
            <input id="umModalInput" class="um-modal-input" type="text" maxlength="255" placeholder="Optional reason">
        </div>
        <div class="um-modal-actions">
            <button type="button" class="um-modal-btn cancel" id="umModalCancel">Cancel</button>
            <button type="button" class="um-modal-btn confirm" id="umModalConfirm">Continue</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/exceljs@4.4.0/dist/exceljs.min.js"></script>
<script>
var umPendingAction = null;

function closeUmConfirm() {
    var overlay = document.getElementById('umConfirmOverlay');
    var input = document.getElementById('umModalInput');
    if (!overlay || !input) {
        return;
    }

    overlay.classList.remove('show');
    overlay.setAttribute('aria-hidden', 'true');
    input.value = '';
    umPendingAction = null;
}

function openUmConfirm(options) {
    var overlay = document.getElementById('umConfirmOverlay');
    var title = document.getElementById('umModalTitle');
    var text = document.getElementById('umModalText');
    var inputWrap = document.getElementById('umModalInputWrap');
    var input = document.getElementById('umModalInput');
    var confirmBtn = document.getElementById('umModalConfirm');
    if (!overlay || !title || !text || !inputWrap || !input || !confirmBtn) {
        return;
    }

    title.textContent = options.title || 'Please Confirm';
    text.textContent = options.message || 'Are you sure?';
    confirmBtn.textContent = options.confirmLabel || 'Continue';
    confirmBtn.classList.remove('warn', 'danger', 'success');
    if (options.confirmKind) {
        confirmBtn.classList.add(options.confirmKind);
    }

    if (options.showInput) {
        inputWrap.style.display = 'block';
        input.placeholder = options.inputPlaceholder || 'Optional reason';
    } else {
        inputWrap.style.display = 'none';
        input.value = '';
    }

    umPendingAction = options.onConfirm || null;
    overlay.classList.add('show');
    overlay.setAttribute('aria-hidden', 'false');

    setTimeout(function () {
        if (options.showInput) {
            input.focus();
        } else {
            confirmBtn.focus();
        }
    }, 0);
}

document.getElementById('umModalCancel')?.addEventListener('click', closeUmConfirm);
document.getElementById('umModalConfirm')?.addEventListener('click', function () {
    var input = document.getElementById('umModalInput');
    var action = umPendingAction;
    var value = input ? input.value.trim() : '';
    closeUmConfirm();
    if (typeof action === 'function') {
        action(value);
    }
});

function submitSimpleConfirm(form, message, confirmLabel, confirmKind) {
    openUmConfirm({
        title: 'Please Confirm',
        message: message,
        confirmLabel: confirmLabel || 'Continue',
        confirmKind: confirmKind || 'warn',
        showInput: false,
        onConfirm: function () {
            form.submit();
        }
    });

    return false;
}

function submitStatusForm(form, idNumber, status) {
    var reasonInput = form.querySelector('input[name="reason"]');
    var isActive = String(status) === 'active';

    if (isActive) {
        openUmConfirm({
            title: 'Deactivate Account',
            message: 'Optional reason for deactivation. Leave blank for no reason.',
            confirmLabel: 'Deactivate',
            confirmKind: 'danger',
            showInput: true,
            inputPlaceholder: 'Reason (optional)',
            onConfirm: function (reason) {
                if (reasonInput) {
                    reasonInput.value = reason;
                }
                form.submit();
            }
        });

        return false;
    }

    if (reasonInput) {
        reasonInput.value = '';
    }

    openUmConfirm({
        title: 'Activate Account',
        message: 'Activate account ' + idNumber + '?',
        confirmLabel: 'Activate',
        confirmKind: 'success',
        showInput: false,
        onConfirm: function () {
            form.submit();
        }
    });

    return false;
}

async function exportUsersToExcel() {
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

    const ws = wb.addWorksheet('User Roster');
    ws.columns = [
        { width: 10 },
        { width: 18 },
        { width: 28 },
        { width: 16 },
        { width: 20 },
        { width: 16 },
        { width: 18 },
    ];

    ws.mergeCells('A1:G1');
    ws.mergeCells('A2:G2');
    ws.mergeCells('A3:G3');

    ws.getCell('A1').value = 'PASIG CATHOLIC COLLEGE — COLLEGE DEPARTMENT';
    ws.getCell('A1').font = { name: 'Calibri', size: 13, bold: true, color: { argb: 'FF245E55' } };
    ws.getCell('A1').alignment = { vertical: 'middle', horizontal: 'left', indent: 7 };

    ws.getCell('A2').value = 'Official Platform User Roster & Access Masterlist';
    ws.getCell('A2').font = { name: 'Calibri', size: 11, bold: true, color: { argb: 'FF0F172A' } };
    ws.getCell('A2').alignment = { vertical: 'middle', horizontal: 'left', indent: 7 };

    ws.getCell('A3').value = 'Generated on ' + new Date().toLocaleString();
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

    ws.addRow([]);
    const headerRow = ws.addRow(['#', 'ID Number', 'Full Name', 'Role', 'Program', 'Status', 'Joined Date']);
    headerRow.eachCell(cell => {
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF245E55' } };
        cell.font = { bold: true, color: { argb: 'FFFFFFFF' }, size: 10 };
    });

    const rows = document.querySelectorAll('#umUsersTable tbody tr');
    rows.forEach(tr => {
        const tds = tr.querySelectorAll('td');
        if (tds.length >= 7) {
            ws.addRow([
                tds[0].innerText.trim(),
                tds[1].innerText.trim(),
                tds[2].innerText.trim(),
                tds[3].innerText.trim(),
                tds[4].innerText.replace('🔒', '').trim(),
                tds[5].innerText.trim(),
                tds[6].innerText.trim(),
            ]);
        }
    });

    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'PCC_User_Masterlist_' + new Date().toISOString().slice(0, 10) + '.xlsx';
    link.click();
}
</script>

@endsection
