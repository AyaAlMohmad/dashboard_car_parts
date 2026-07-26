@extends('layouts.app')

@section('title', 'السحوبات - نظام قطع السيارات')

@section('content')
<div class="page active">
    <div class="page-header">
        <div>
            <h1>💸 السحوبات</h1>
            <span class="subtitle">تسجيل المبالغ المسحوبة من الخزينة</span>
        </div>
        <button class="btn btn-danger" onclick="openWithdrawalModal()">💸 تسجيل سحب</button>
    </div>
    <div class="table-container">
        <div class="table-toolbar">
            <input type="text" class="search-input" placeholder="🔍 بحث..." id="withdrawalSearch" oninput="renderWithdrawals()">
            <div class="export-group">
                <select id="exportFormat_withdrawals">
                    <option value="excel">Excel</option>
                    <option value="sql">SQL</option>
                </select>
                <button class="btn btn-success btn-xs" onclick="exportTable('withdrawals', document.getElementById('exportFormat_withdrawals').value)">📥 تصدير</button>
            </div>
        </div>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>التاريخ</th>
                        <th>الوقت</th>
                        <th>اسم الشخص</th>
                        <th>المبلغ</th>
                        <th>السبب</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="withdrawalsTableBody"></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/withdrawals.js') }}"></script>
@endpush
