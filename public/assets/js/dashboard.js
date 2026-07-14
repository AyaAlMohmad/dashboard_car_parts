(async function () {
    try {
        const data = await apiFetch('/dashboard');

        const s = data.stats;
        document.getElementById('dashboardStats').innerHTML = `
            <div class="stat-card"><div class="stat-icon blue">👥</div><div class="stat-info"><h3>العملاء</h3><div class="value">${s.customers_count}</div></div></div>
            <div class="stat-card"><div class="stat-icon blue">🏭</div><div class="stat-info"><h3>الموردين</h3><div class="value">${s.suppliers_count}</div></div></div>
            <div class="stat-card"><div class="stat-icon blue">📦</div><div class="stat-info"><h3>القطع</h3><div class="value">${s.parts_count}</div><small>الكمية: ${s.parts_low_stock} منخفض</small></div></div>
            <div class="stat-card"><div class="stat-icon red">💸</div><div class="stat-info"><h3>إجمالي المبيعات</h3><div class="value">${formatCurrency(s.sales_total)}</div></div></div>
            <div class="stat-card"><div class="stat-icon green">✅</div><div class="stat-info"><h3>المدفوع</h3><div class="value">${formatCurrency(s.sales_paid)}</div></div></div>
            <div class="stat-card"><div class="stat-icon orange">⚠️</div><div class="stat-info"><h3>الديون المتبقية</h3><div class="value">${formatCurrency(s.debts)}</div></div></div>
            <div class="stat-card"><div class="stat-icon red">📥</div><div class="stat-info"><h3>إجمالي المشتريات</h3><div class="value">${formatCurrency(s.purchases_total)}</div></div></div>
            <div class="stat-card"><div class="stat-icon orange">⚠️</div><div class="stat-info"><h3>ديون الموردين</h3><div class="value">${formatCurrency(s.supplier_debts)}</div></div></div>
            <div class="stat-card"><div class="stat-icon orange">⚠️</div><div class="stat-info"><h3>قطع منخفضة</h3><div class="value">${s.parts_low_stock}</div></div></div>
        `;

        const recent = data.recent_sales || [];
        let html = '';
        if (recent.length === 0) {
            html = '<p class="empty-state">📭 لا توجد عمليات</p>';
        } else {
            html = '<ul style="list-style:none;padding:0;">';
            recent.forEach((op) => {
                const cname = op.customer?.name || '؟';
                const pname = op.part?.name || '؟';
                html += `<li style="padding:6px 0;border-bottom:1px solid var(--border);">🛒 بيع: ${pname} لـ ${cname} - ${formatCurrency(op.total)} | ${formatDate(op.sale_date)} ${renderBadge(op.status)}</li>`;
            });
            html += '</ul>';
        }
        document.getElementById('recentActivity').innerHTML = html;
    } catch (e) {
        showToast('حدث خطأ أثناء تحميل البيانات', 'error');
        console.error(e);
    }
})();

window.downloadBackup = async function () {
    try {
        showToast('جاري إنشاء ملف Excel...', 'info');
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const res = await fetch('/api/backup/download-excel', {
            headers: { 'X-CSRF-TOKEN': token },
        });
        if (!res.ok) throw new Error('Failed to download backup');
        const blob = await res.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'backup_' + new Date().toISOString().slice(0,19).replace(/:/g,'') + '.xlsx';
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(url);
        showToast('✅ تم التحميل');
    } catch (e) {
        showToast('حدث خطأ أثناء التحميل', 'error');
        console.error(e);
    }
};
