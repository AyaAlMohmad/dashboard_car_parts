@extends('layouts.app')

@section('title', 'المشتريات - نظام قطع السيارات')

@section('content')
<div class="page active">
    <div class="page-header">
        <div>
            <h1>📥 المشتريات</h1>
            <span class="subtitle">فواتير الشراء من الموردين</span>
        </div>
        <button class="btn btn-primary" onclick="openPurchaseModal()">🧾 شراء جديد</button>
    </div>
    <div class="table-container">
        <div class="table-toolbar">
            <input type="text" class="search-input" placeholder="🔍 بحث..." id="purchaseSearch" oninput="renderPurchases()">
            <div class="export-group">
                <select id="exportFormat_purchases">
                    <option value="excel">Excel</option>
                    <option value="sql">SQL</option>
                </select>
                <button class="btn btn-success btn-xs" onclick="exportTable('purchases', document.getElementById('exportFormat_purchases').value)">📥 تصدير</button>
            </div>
        </div>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>التاريخ</th>
                        <th>المورد</th>
                        <th>القطعة</th>
                        <th>الكمية</th>
                        <th>الإجمالي</th>
                        <th>المدفوع</th>
                        <th>المتبقي</th>
                        <th>الحالة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="purchasesTableBody"></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/purchases.js') }}"></script>
@endpush
