@extends('layouts.app')

@section('title', 'الفواتير والمبيعات - نظام قطع السيارات')

@section('content')
<div class="page active">
    <div class="page-header">
        <div>
            <h1>🧾 الفواتير والمبيعات</h1>
            <span class="subtitle">فواتير متعددة القطع مع دعم الدولار والليرة</span>
        </div>
        <button class="btn btn-primary" onclick="openInvoiceModal()">🧾 فاتورة جديدة</button>
    </div>
    <div class="table-container">
        <div class="table-toolbar">
            <input type="text" class="search-input" placeholder="🔍 بحث (اسم العميل أو القطعة)..." id="invoiceSearch" oninput="renderInvoices()">
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
                        <th>رقم الفاتورة</th>
                        <th>التاريخ</th>
                        <th>الوقت</th>
                        <th>العميل</th>
                        <th>القطع</th>
                        <th>الإجمالي</th>
                        <th>المدفوع</th>
                        <th>المتبقي</th>
                        <th>الحالة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="invoicesTableBody"></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/invoices.js') }}"></script>
@endpush
