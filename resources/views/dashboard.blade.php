@extends('layouts.app')

@section('title', 'لوحة التحكم - نظام قطع السيارات')

@section('content')
<div class="page active">
    <div class="page-header">
        <div>
            <h1>📊 لوحة التحكم</h1>
            <span class="subtitle">نظرة عامة على المتجر</span>
        </div>
        <button class="btn btn-success" onclick="downloadBackup()">📥 تصدير نسخة احتياطية (Excel)</button>
    </div>
    <div class="stats-grid" id="dashboardStats"></div>
    <div class="table-container" style="margin-top:16px;">
        <div class="table-toolbar"><strong>📋 آخر العمليات</strong></div>
        <div id="recentActivity" style="padding:16px;"></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/dashboard.js') }}"></script>
@endpush
