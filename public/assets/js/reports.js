(async function () {
    try {
        const [summary, debtors, creditors, monthlyProfit] = await Promise.all([
            apiFetch('/reports/summary'),
            apiFetch('/reports/debtors'),
            apiFetch('/reports/creditors'),
            apiFetch('/reports/monthly-profit')
        ]);

        const s = summary;
        document.getElementById('reportStats').innerHTML = `
            <div class="stat-card"><div class="stat-icon orange">⚠️</div><div class="stat-info"><h3>مخزون منخفض</h3><div class="value">${s.low_stock_parts || 0}</div></div></div>
            <div class="stat-card"><div class="stat-icon blue">📦</div><div class="stat-info"><h3>قيمة المخزون (شراء)</h3><div class="value">${formatCurrency(s.inventory_value_syp || 0, 'ل.س')} | ${formatCurrency(s.inventory_value_usd || 0, '$')}</div></div></div>
            <div class="stat-card"><div class="stat-icon red">💰</div><div class="stat-info"><h3>ديون للموردين</h3><div class="value">${formatCurrency(s.supplier_debts_syp || 0, 'ل.س')} | ${formatCurrency(s.supplier_debts_usd || 0, '$')}</div></div></div>
            <div class="stat-card"><div class="stat-icon red">�</div><div class="stat-info"><h3>ديون العملاء</h3><div class="value">${formatCurrency(s.total_debts_syp || 0, 'ل.س')} | ${formatCurrency(s.total_debts_usd || 0, '$')}</div></div></div>
            <div class="stat-card"><div class="stat-icon green">📈</div><div class="stat-info"><h3>ربح المستودع</h3><div class="value">${formatCurrency(s.warehouse_profit_syp || 0, 'ل.س')} | ${formatCurrency(s.warehouse_profit_usd || 0, '$')}</div></div></div>
        `;

        document.getElementById('inventoryCostValue').textContent = formatCurrency(s.inventory_value_syp || 0, 'ل.س') + ' | ' + formatCurrency(s.inventory_value_usd || 0, '$');

        document.getElementById('totalWithdrawals').innerHTML = `
            <div style="display:flex;justify-content:space-between;align-items:center;padding:16px; font-size:18px; font-weight:bold;">
                <span>💸 إجمالي السحوبات:</span>
                <span style="font-size:24px;color:var(--danger);">${formatCurrency(s.total_withdrawals_syp || 0, 'ل.س')} | ${formatCurrency(s.total_withdrawals_usd || 0, '$')}</span>
            </div>`;

        const debtorsBody = document.getElementById('debtorsTableBody');
        const debtList = debtors || [];
        if (debtList.length === 0) {
            debtorsBody.innerHTML = '<tr><td colspan="3"><div class="empty-state">✅ لا يوجد عملاء مديونون</div></td></tr>';
        } else {
            debtorsBody.innerHTML = debtList.map((c) => {
                return `<tr><td>${c.name}</td><td>${c.phone || '-'}</td><td style="color:var(--danger);font-weight:700;">${formatCurrency(c.total_debt_syp || 0, 'ل.س')} | ${formatCurrency(c.total_debt_usd || 0, '$')}</td></tr>`;
            }).join('');
        }

        const creditorsBody = document.getElementById('creditorsTableBody');
        const credList = creditors || [];
        if (credList.length === 0) {
            creditorsBody.innerHTML = '<tr><td colspan="3"><div class="empty-state">✅ لا يوجد ديون على الموردين</div></td></tr>';
        } else {
            creditorsBody.innerHTML = credList.map((s) => {
                return `<tr><td>${s.name}</td><td>${s.phone || '-'}</td><td style="color:var(--danger);font-weight:700;">${formatCurrency(s.total_debt_syp || 0, 'ل.س')} | ${formatCurrency(s.total_debt_usd || 0, '$')}</td></tr>`;
            }).join('');
        }

        const profitBody = document.getElementById('monthlyProfitBody');
        const profits = monthlyProfit || [];
        if (profits.length === 0) {
            profitBody.innerHTML = '<tr><td colspan="5"><div class="empty-state">📈 لا توجد بيانات أرباح</div></td></tr>';
        } else {
            profitBody.innerHTML = profits.map((r) => {
                const profitClass = r.net_profit >= 0 ? 'positive' : 'negative';
                return `<tr>
                    <td>${r.month}</td>
                    <td>${r.invoices}</td>
                    <td>${formatCurrency(r.total_sales_syp || 0, 'ل.س')} | ${formatCurrency(r.total_sales_usd || 0, '$')}</td>
                    <td>${formatCurrency(r.cost_syp || 0, 'ل.س')} | ${formatCurrency(r.cost_usd || 0, '$')}</td>
                    <td style="color:var(--${r.net_profit >= 0 ? 'success' : 'danger'});font-weight:700;">${formatCurrency(r.net_profit_syp || 0, 'ل.س')} | ${formatCurrency(r.net_profit_usd || 0, '$')}</td>
                </tr>`;
            }).join('');
        }
    } catch (e) {
        showToast('حدث خطأ أثناء تحميل البيانات', 'error');
        console.error(e);
    }
})();
