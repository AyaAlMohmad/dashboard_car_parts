@extends('layouts.app')

@section('title', 'الفئات - نظام قطع السيارات')

@section('content')
<div class="page active">
    <div class="page-header">
        <div>
            <h1>🏷️ الفئات</h1>
            <span class="subtitle">إدارة فئات قطع الغيار</span>
        </div>
        <button class="btn btn-primary" onclick="openCategoryModal()">➕ إضافة فئة</button>
    </div>
    <div class="table-container">
        <div class="table-toolbar">
            <input type="text" class="search-input" placeholder="🔍 بحث..." id="categorySearch" oninput="renderCategories()">
        </div>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>اسم الفئة</th>
                        <th>عدد القطع</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="categoriesTableBody"></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/categories.js') }}"></script>
@endpush
