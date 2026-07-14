window.formatCurrency = function (amount) {
    if (amount === null || amount === undefined) return '0 SP';
    return parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) + ' SP';
};

window.formatDate = function (dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    if (isNaN(d)) return dateStr;
    return d.toLocaleDateString('ar-EG', { year: 'numeric', month: 'short', day: 'numeric' });
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
        throw err;
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
