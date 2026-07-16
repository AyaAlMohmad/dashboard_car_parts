@extends('layouts.app')

@section('title', 'المستودع - نظام قطع السيارات')

@section('content')
<div class="page active">
    <div class="page-header">
        <div>
            <h1>📦 المستودع</h1>
            <span class="subtitle">إدارة مخزون قطع السيارات</span>
        </div>
        <button class="btn btn-primary" onclick="openPartModal()">➕ إضافة قطعة</button>
    </div>
    <div class="table-container">
        <div class="table-toolbar">
            <input type="text" class="search-input" placeholder="🔍 بحث..." id="partSearch" oninput="renderParts()">
            <select id="categoryFilter" onchange="renderParts()" style="padding:9px;border-radius:8px;border:1.5px solid var(--border);">
                <option value="">جميع الفئات</option>
            </select>
            <div class="export-group">
                <select id="exportFormat_parts">
                    <option value="excel">Excel</option>
                    <option value="sql">SQL</option>
                </select>
                <button class="btn btn-success btn-xs" onclick="exportTable('parts', document.getElementById('exportFormat_parts').value)">📥 تصدير</button>
            </div>
        </div>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>اسم القطعة</th>
                        <th>رقم القطعة</th>
                        <th>الفئة</th>
                        <th>الكمية</th>
                        <th>سعر الشراء</th>
                        <th>سعر البيع</th>
                        <th>المورد</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                        <th>الوقت</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="partsTableBody"></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/parts.js') }}"></script>
@endpush
