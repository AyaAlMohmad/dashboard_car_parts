@extends('layouts.app')

@section('title', 'مدفوعات الموردين - نظام قطع السيارات')

@section('content')
<div class="page active">
    <div class="page-header">
        <div>
            <h1>🏦 مدفوعات الموردين</h1>
            <span class="subtitle">تسجيل دفعات الموردين</span>
        </div>
        <button class="btn btn-success" onclick="openSupplierPaymentModal()">💵 دفع للمورد</button>
    </div>
    <div class="table-container">
        <div class="table-toolbar">
            <input type="text" class="search-input" placeholder="🔍 بحث..." id="supplierPaymentSearch" oninput="renderSupplierPayments()">
            <div class="export-group">
                <select id="exportFormat_supplier_payments">
                    <option value="excel">Excel</option>
                    <option value="sql">SQL</option>
                </select>
                <button class="btn btn-success btn-xs" onclick="exportTable('supplier_payments', document.getElementById('exportFormat_supplier_payments').value)">📥 تصدير</button>
            </div>
        </div>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>التاريخ</th>
                        <th>الوقت</th>
                        <th>المورد</th>
                        <th>المبلغ</th>
                        <th>ملاحظات</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="supplierPaymentsTableBody"></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/supplier_payments.js') }}"></script>
@endpush
