@extends('layouts.app')

@section('title', 'الموردين - نظام قطع السيارات')

@section('content')
<div class="page active">
    <div class="page-header">
        <div>
            <h1>🏭 الموردين</h1>
            <span class="subtitle">إدارة الموردين والديون المستحقة لهم</span>
        </div>
        <button class="btn btn-primary" onclick="openSupplierModal()">➕ إضافة مورد</button>
    </div>
    <div class="table-container">
        <div class="table-toolbar">
            <input type="text" class="search-input" placeholder="🔍 بحث..." id="supplierSearch" oninput="renderSuppliers()">
            <div class="export-group">
                <select id="exportFormat_suppliers">
                    <option value="excel">Excel</option>
                    <option value="sql">SQL</option>
                </select>
                <button class="btn btn-success btn-xs" onclick="exportTable('suppliers', document.getElementById('exportFormat_suppliers').value)">📥 تصدير</button>
            </div>
        </div>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الاسم</th>
                        <th>الهاتف</th>
                        <th>العنوان</th>
                        <th>الرصيد (نحن مدينون)</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                        <th>الوقت</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="suppliersTableBody"></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/suppliers.js') }}"></script>
@endpush
