@extends('layouts.app')

@section('title', 'التقارير - نظام قطع السيارات')

@section('content')
<div class="page active">
    <div class="page-header">
        <div>
            <h1>📋 التقارير</h1>
            <span class="subtitle">ملخص الحسابات والمخزون</span>
        </div>
    </div>
    <div class="stats-grid" id="reportStats"></div>

    <div class="table-container" style="margin-top:16px;">
        <div class="table-toolbar"><strong>� الأرباح الشهرية</strong></div>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>الشهر</th>
                        <th>عدد الفواتير</th>
                        <th>إجمالي المبيعات</th>
                        <th>تكلفة البضاعة المباعة</th>
                        <th>صافي الربح / الخسارة</th>
                    </tr>
                </thead>
                <tbody id="monthlyProfitBody"></tbody>
            </table>
        </div>
    </div>

    <div class="table-container" style="margin-top:16px;">
        <div class="table-toolbar"><strong>📦 قيمة المخزون بسعر الشراء</strong></div>
        <div style="padding:16px; font-size:18px; font-weight:bold;" id="inventoryCostValue"></div>
    </div>

    <div class="table-container" style="margin-top:16px;">
        <div class="table-toolbar"><strong>💸 إجمالي السحوبات</strong></div>
        <div id="totalWithdrawals">💸 إجمالي السحوبات: 0 ل.س</div>
    </div>

    <div class="table-container" style="margin-top:16px;">
        <div class="table-toolbar"><strong>📋 تقرير الديون (العملاء المديونون)</strong></div>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>العميل</th>
                        <th>رقم الهاتف</th>
                        <th>إجمالي الديون</th>
                    </tr>
                </thead>
                <tbody id="debtorsTableBody"></tbody>
            </table>
        </div>
    </div>

    <div class="table-container" style="margin-top:16px;">
        <div class="table-toolbar"><strong>� تقرير الديون (الموردين - علينا)</strong></div>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>المورد</th>
                        <th>رقم الهاتف</th>
                        <th>إجمالي الديون</th>
                    </tr>
                </thead>
                <tbody id="creditorsTableBody"></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/reports.js') }}"></script>
@endpush
