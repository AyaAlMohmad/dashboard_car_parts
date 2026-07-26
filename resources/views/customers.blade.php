@extends('layouts.app')

@section('title', 'العملاء - نظام قطع السيارات')

@section('content')
<div class="page active">
    <div class="page-header">
        <div>
            <h1>👥 العملاء</h1>
            <span class="subtitle">إدارة العملاء - مدين ودائن</span>
        </div>
        <button class="btn btn-primary" onclick="openCustomerModal()">➕ إضافة عميل</button>
    </div>
    <div class="table-container">
        <div class="table-toolbar">
            <input type="text" class="search-input" placeholder="🔍 بحث..." id="customerSearch" oninput="renderCustomers()">
            <div class="export-group">
                <select id="exportFormat_customers">
                    <option value="excel">Excel</option>
                    <option value="sql">SQL</option>
                </select>
                <button class="btn btn-success btn-xs" onclick="exportTable('customers', document.getElementById('exportFormat_customers').value)">📥 تصدير</button>
            </div>
        </div>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الاسم</th>
                        <th>رقم الهاتف</th>
                        <th>العنوان</th>
                        <th>الديون / المسددات</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                        <th>الوقت</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="customersTableBody"></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/customers.js') }}"></script>
@endpush
