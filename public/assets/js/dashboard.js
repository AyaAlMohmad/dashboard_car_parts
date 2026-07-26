(async function () {
    try {
        const data = await apiFetch('/dashboard');

        const s = data.stats;
        const topRow = `
            <div class="stat-card"><div class="stat-icon red">�</div><div class="stat-info"><h3>ديون العملاء</h3><div class="value">${formatCurrency(s.debts_syp || 0, 'ل.س')} | ${formatCurrency(s.debts_usd || 0, '$')}</div></div></div>
            <div class="stat-card"><div class="stat-icon orange">📦</div><div class="stat-info"><h3>القطع</h3><div class="value">${s.parts_count}</div><small>الكمية: ${s.parts_quantity}</small></div></div>
            <div class="stat-card"><div class="stat-icon orange">🏭</div><div class="stat-info"><h3>الموردين</h3><div class="value">${s.suppliers_count}</div></div></div>
            <div class="stat-card"><div class="stat-icon blue">👥</div><div class="stat-info"><h3>العملاء</h3><div class="value">${s.customers_count}</div></div></div>
        `;
        const bottomRow = `
            <div class="stat-card"><div class="stat-icon orange">�</div><div class="stat-info"><h3>إجمالي السحوبات</h3><div class="value">${formatCurrency(s.total_withdrawals_syp || 0, 'ل.س')} | ${formatCurrency(s.total_withdrawals_usd || 0, '$')}</div></div></div>
            <div class="stat-card"><div class="stat-icon red">💰</div><div class="stat-info"><h3>ديون للموردين</h3><div class="value">${formatCurrency(s.supplier_debts_syp || 0, 'ل.س')} | ${formatCurrency(s.supplier_debts_usd || 0, '$')}</div></div></div>
            <div class="stat-card"><div class="stat-icon green">📈</div><div class="stat-info"><h3>ربح المستودع</h3><div class="value">${formatCurrency(s.warehouse_profit_syp || 0, 'ل.س')} | ${formatCurrency(s.warehouse_profit_usd || 0, '$')}</div></div></div>
        `;
        document.getElementById('dashboardStats').innerHTML = `
            <div class="stats-grid" style="margin-bottom:16px;">${topRow}</div>
            <div class="stats-grid stats-grid--center">${bottomRow}</div>
        `;

        const recent = data.recent_sales || [];
        let html = '';
        if (recent.length === 0) {
            html = '<p class="empty-state">📭 لا توجد عمليات</p>';
        } else {
            html = '<ul style="list-style:none;padding:0;">';
            recent.forEach((op) => {
                const cname = op.customer?.name || '؟';
                const items = op.items || [];
                const itemNames = items.map(i => i.part?.name || '؟').join('، ');
                const curr = op.currency === 'USD' ? '$' : 'ل.س';
                html += `<li style="padding:6px 0;border-bottom:1px solid var(--border);">🧾 فاتورة ${op.invoice_number}: ${itemNames} لـ ${cname} — ${curr} ${op.total} | ${formatDate(op.sale_date)} ${renderBadge(op.status)}</li>`;
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
