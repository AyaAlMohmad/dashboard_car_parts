window.formatCurrency = function (amount, symbol = 'ل.س') {
    if (amount === null || amount === undefined) return '0 ' + symbol;
    return parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) + ' ' + symbol;
};

window.formatDate = function (dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    if (isNaN(d)) return dateStr;
    return d.toLocaleDateString('ar-EG', { year: 'numeric', month: 'short', day: 'numeric' });
};

<<<<<<< HEAD
window.formatTime = function (dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    if (isNaN(d)) return '-';
=======
window.formatDateTime = function (dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    if (isNaN(d)) return dateStr;
    return d.toLocaleDateString('ar-EG', { year: 'numeric', month: 'short', day: 'numeric' }) + ' ' + d.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
};

window.formatTime = function (dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    if (isNaN(d)) return dateStr;
>>>>>>> 092fd02fa1e4004e138d6f4f84e824e290f61d74
    return d.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
};

window.showToast = function (message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
};

window.toggleNotifDropdown = function () {
    const dd = document.getElementById('notifDropdown');
    if (!dd) return;
    dd.style.display = dd.style.display === 'none' ? 'block' : 'none';
};

document.addEventListener('click', function (e) {
    const bell = document.getElementById('notifBell');
    const dd = document.getElementById('notifDropdown');
    if (!bell || !dd) return;
    if (!bell.contains(e.target) && !dd.contains(e.target)) {
        dd.style.display = 'none';
    }
});

window.updateNotifDropdown = function (items) {
    const badge = document.getElementById('notifBadge');
    const list = document.getElementById('notifList');
    if (!badge || !list) return;
    if (items && items.length > 0) {
        badge.textContent = items.length;
        badge.style.display = 'flex';
        list.innerHTML = items.map(i => `
            <div class="notif-item">
                <div class="notif-dot"></div>
                <div><strong>${i.name}</strong> — الكمية: ${i.quantity}</div>
            </div>
        `).join('');
    } else {
        badge.style.display = 'none';
        list.innerHTML = '<div class="notif-item empty">✅ لا توجد تنبيهات</div>';
    }
};

window.closeModal = function (id) {
    const el = document.getElementById(id);
    if (el) el.remove();
};

window.apiFetch = async function (url, options = {}) {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const defaults = {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': token,
        },
        credentials: 'same-origin',
    };
    if (options.body && !(options.body instanceof FormData)) {
        defaults.headers['Content-Type'] = 'application/json';
    }
    const response = await fetch('/api' + url, { ...defaults, ...options });
    if (!response.ok) {
        const text = await response.text();
        let err = text;
        try { err = JSON.parse(text); } catch (e) {}
        // استخراج رسائل Laravel validation
        let msg = typeof err === 'string' ? err : (err.message || 'حدث خطأ');
        if (err && err.errors) {
            const firstErrors = Object.values(err.errors).flat();
            if (firstErrors.length > 0) msg = firstErrors.join(' | ');
        }
        throw new Error(msg);
    }
    if (response.status === 204) return null;
    return response.json();
};

window.renderBadge = function (status) {
    if (status === 'مدين') return '<span class="badge badge-danger">مدين</span>';
    if (status === 'متوان') return '<span class="badge badge-info">متوان</span>';
    if (status === 'متوفر') return '<span class="badge badge-success">متوفر</span>';
    if (status === 'منخفض') return '<span class="badge badge-warning">منخفض</span>';
    if (status === 'غير متوفر') return '<span class="badge badge-danger">غير متوفر</span>';
    if (status === 'مسدد') return '<span class="badge badge-success">مسدد</span>';
    if (status === 'عليه دين') return '<span class="badge badge-danger">عليه دين</span>';
    return '<span class="badge badge-info">' + status + '</span>';
};

window.showModal = function (title, bodyHtml) {
    const html = `
        <div class="modal-overlay" id="genericModal">
            <div class="modal">
                <div class="modal-header"><h3>${title}</h3><button class="modal-close" onclick="closeModal('genericModal')">✕</button></div>
                <div class="modal-body">${bodyHtml}</div>
                <div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('genericModal')">إغلاق</button></div>
            </div>
        </div>`;
    document.getElementById('modalContainer').innerHTML = html;
};

window.exportTable = async function (table, format) {
    try {
        showToast('جاري التصدير...', 'info');
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const res = await fetch('/api/export/table?table=' + encodeURIComponent(table) + '&format=' + encodeURIComponent(format), {
            headers: { 'X-CSRF-TOKEN': token },
        });
        if (!res.ok) throw new Error('Export failed');
        const blob = await res.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        const ext = format === 'excel' ? 'xlsx' : 'sql';
        a.download = table + '_' + new Date().toISOString().slice(0,19).replace(/:/g,'') + '.' + ext;
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(url);
        showToast('✅ تم التصدير');
    } catch (e) {
        showToast('حدث خطأ أثناء التصدير', 'error');
        console.error(e);
    }
};

window.toggleNotifDropdown = function () {
    const el = document.getElementById('notifDropdown');
    if (!el) return;
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
};

window.updateNotifDropdown = function (items) {
    const list = document.getElementById('notifList');
    const badge = document.getElementById('notifBadge');
    if (!list || !badge) return;
    if (items.length === 0) {
        list.innerHTML = '<div class="notif-empty">لا توجد تنبيهات حالياً</div>';
        badge.style.display = 'none';
        return;
    }
    badge.textContent = items.length;
    badge.style.display = 'flex';
    list.innerHTML = items.map(i => `<div class="notif-item">
        <span>⚠️ <strong>${i.name}</strong> — كمية: ${i.quantity}</span>
        <span class="badge badge-danger">منخفض</span>
    </div>`).join('');
};

window.loadInventoryNotifications = async function () {
    try {
        const data = await apiFetch('/parts');
        const parts = data.data || [];
        const lowStockItems = parts.filter(i => i.quantity <= (i.alert_threshold || 5));
        const badge = document.getElementById('inventoryBadge');
        if (badge) {
            badge.textContent = lowStockItems.length;
            badge.style.display = lowStockItems.length > 0 ? 'inline-block' : 'none';
        }
        updateNotifDropdown(lowStockItems);
    } catch (e) {
        console.error(e);
    }
};
loadInventoryNotifications();

// Mobile menu
(function () {
    const btn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('sidebar');
    if (btn && sidebar) {
        btn.addEventListener('click', () => sidebar.classList.toggle('open'));
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768 && !sidebar.contains(e.target) && e.target !== btn && !btn.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    }
})();
