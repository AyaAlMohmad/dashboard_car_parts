@extends('layouts.app')

@section('title', 'النسخ الاحتياطية - نظام قطع السيارات')

@section('content')
<div class="page active">
    <div class="page-header">
        <div>
            <h1>💾 النسخ الاحتياطية</h1>
            <span class="subtitle">إدارة وتحميل النسخ المحفوظة</span>
        </div>
        <div>
            <button class="btn btn-success" onclick="createBackup('excel')">📥 Excel جديد</button>
            <button class="btn btn-primary" onclick="createBackup('sql')">🗄️ SQL/SQLite جديد</button>
            <button class="btn btn-warning" onclick="openScheduleModal()">⏰ تصدير تلقائي</button>
            <button class="btn btn-info" onclick="openEmailSettingsModal()">📧 إرسال بالبريد</button>
        </div>
    </div>

    <div class="stats-grid" id="nextRunCard" style="display:none;">
        <div class="stat-card" style="cursor:default;">
            <div class="stat-icon" style="background:var(--warning-light);color:var(--warning);">⏳</div>
            <div class="stat-info">
                <div class="label">النسخة التلقائية القادمة</div>
                <div class="value" id="nextRunCountdown" style="font-family:monospace;font-size:22px;">--:--:--</div>
                <div class="subtitle" id="nextRunLabel">جاري الحساب...</div>
            </div>
        </div>
    </div>

    <div class="table-container" style="margin-top:24px;">
        <div class="table-toolbar"><strong>📅 جدولة التصدير التلقائي</strong></div>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الاسم</th>
                        <th>النوع</th>
                        <th>الصيغة</th>
                        <th>الفترة</th>
                        <th>آخر تشغيل</th>
                        <th>الوقت</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody id="schedulesTableBody"></tbody>
            </table>
        </div>
    </div>

    <div class="table-container" style="margin-top:24px;">
        <div class="table-toolbar"><strong>💾 النسخ المحفوظة</strong><input type="text" class="search-input" placeholder="🔍 بحث..." id="backupSearch" oninput="renderBackups()" style="margin-right:8px;"></div>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الاسم</th>
                        <th>الصيغة</th>
                        <th>الحجم</th>
                        <th>التاريخ</th>
                        <th>الوقت</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody id="backupsTableBody"></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/backups.js') }}"></script>
@endpush
