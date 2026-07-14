@extends('layouts.app')

@section('title', 'التسديدات - نظام قطع السيارات')

@section('content')
<div class="page active">
    <div class="page-header">
        <div>
            <h1>💳 التسديدات</h1>
            <span class="subtitle">تسجيل دفعات العملاء</span>
        </div>
        <button class="btn btn-success" onclick="openPaymentModal()">💵 تسجيل دفعة</button>
    </div>
    <div class="table-container">
        <div class="table-toolbar">
            <input type="text" class="search-input" placeholder="🔍 بحث..." id="paymentSearch" oninput="renderPayments()">
            <div class="export-group">
                <select id="exportFormat_payments">
                    <option value="excel">Excel</option>
                    <option value="sql">SQL</option>
                </select>
                <button class="btn btn-success btn-xs" onclick="exportTable('payments', document.getElementById('exportFormat_payments').value)">📥 تصدير</button>
            </div>
        </div>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>التاريخ</th>
                        <th>العميل</th>
                        <th>المبلغ</th>
                        <th>ملاحظات</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="paymentsTableBody"></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/payments.js') }}"></script>
@endpush
