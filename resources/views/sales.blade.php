@extends('layouts.app')

@section('title', 'المبيعات والديون - نظام قطع السيارات')

@section('content')
<div class="page active">
    <div class="page-header">
        <div>
            <h1>💰 المبيعات والديون</h1>
            <span class="subtitle">تسجيل مبيعات القطع وتتبع الديون</span>
        </div>
        <button class="btn btn-primary" onclick="openSaleModal()">🛒 تسجيل عملية بيع</button>
    </div>
    <div class="table-container">
        <div class="table-toolbar">
            <input type="text" class="search-input" placeholder="🔍 بحث..." id="saleSearch" oninput="renderSales()">
            <div class="export-group">
                <select id="exportFormat_sales">
                    <option value="excel">Excel</option>
                    <option value="sql">SQL</option>
                </select>
                <button class="btn btn-success btn-xs" onclick="exportTable('sales', document.getElementById('exportFormat_sales').value)">📥 تصدير</button>
            </div>
        </div>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>التاريخ</th>
                        <th>العميل</th>
                        <th>القطعة</th>
                        <th>الكمية</th>
                        <th>الإجمالي</th>
                        <th>المدفوع</th>
                        <th>المتبقي</th>
                        <th>الحالة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="salesTableBody"></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/sales.js') }}"></script>
@endpush
