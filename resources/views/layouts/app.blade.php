<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'نظام إدارة متجر قطع السيارات')</title>
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
</head>

<body>

    <button class="mobile-menu-btn" id="mobileMenuBtn" title="القائمة">☰</button>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <span class="logo-icon">🚗</span>
            <h2>متجر قطع السيارات</h2>
            <small>نظام شامل</small>
        </div>
        <nav class="sidebar-nav">
            <a href="{{ url('/') }}" class="{{ request()->is('/') ? 'active' : '' }}"><span class="nav-icon">📊</span> لوحة التحكم</a>
            <a href="{{ url('/customers') }}" class="{{ request()->is('customers') ? 'active' : '' }}"><span class="nav-icon">👥</span> العملاء</a>
            <a href="{{ url('/suppliers') }}" class="{{ request()->is('suppliers') ? 'active' : '' }}"><span class="nav-icon">🏭</span> الموردين</a>
            <a href="{{ url('/parts') }}" class="{{ request()->is('parts') ? 'active' : '' }}"><span class="nav-icon">📦</span> المستودع <span id="inventoryBadge" class="badge badge-danger" style="display:none;margin-right:8px;">0</span></a>
            <a href="{{ url('/categories') }}" class="{{ request()->is('categories') ? 'active' : '' }}"><span class="nav-icon">🏷️</span> الفئات</a>
            <a href="{{ url('/sales') }}" class="{{ request()->is('sales') ? 'active' : '' }}"><span class="nav-icon">🧾</span> الفواتير والمبيعات</a>
            <a href="{{ url('/purchases') }}" class="{{ request()->is('purchases') ? 'active' : '' }}"><span class="nav-icon">📥</span> المشتريات</a>
            <a href="{{ url('/payments') }}" class="{{ request()->is('payments') ? 'active' : '' }}"><span class="nav-icon">💳</span> تسديدات العملاء</a>
            <a href="{{ url('/supplier-payments') }}" class="{{ request()->is('supplier-payments') ? 'active' : '' }}"><span class="nav-icon">🏦</span> مدفوعات الموردين</a>
            <a href="{{ url('/reports') }}" class="{{ request()->is('reports') ? 'active' : '' }}"><span class="nav-icon">📋</span> التقارير</a>
            <a href="{{ url('/backups') }}" class="{{ request()->is('backups') ? 'active' : '' }}"><span class="nav-icon">💾</span> النسخ الاحتياطية</a>
        </nav>
        <div class="sidebar-footer">© 2025 - جميع الحقوق محفوظة</div>
    </aside>

    <main class="main-content" id="mainContent">
        <div class="topbar">
            <div class="topbar-title">نظام إدارة متجر قطع السيارات</div>
            <div class="topbar-actions">
                <div class="notif-bell" id="notifBell" onclick="toggleNotifDropdown()" title="الإشعارات">
                    🔔
                    <span class="notif-badge" id="notifBadge" style="display:none">0</span>
                </div>
                <div class="notif-dropdown" id="notifDropdown" style="display:none">
                    <div class="notif-header">📦 تنبيهات المستودع</div>
                    <div class="notif-list" id="notifList"></div>
                </div>
            </div>
        </div>
        @yield('content')
    </main>

    <div id="modalContainer"></div>
    <div class="toast-container" id="toastContainer"></div>

    <script src="{{ asset('assets/js/app.js') }}"></script>
    @stack('scripts')
</body>

</html>
