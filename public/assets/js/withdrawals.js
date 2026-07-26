let allWithdrawals = [];

async function loadWithdrawals() {
    window.loadWithdrawals = loadWithdrawals;
    try {
        const data = await apiFetch('/withdrawals');
        allWithdrawals = data.data || [];
        renderWithdrawals();
    } catch (e) {
        showToast('حدث خطأ أثناء تحميل السحوبات', 'error');
        console.error(e);
    }
}

function renderWithdrawals() {
    const search = (document.getElementById('withdrawalSearch')?.value || '').toLowerCase();
    let filtered = allWithdrawals.filter((w) => {
        const person = (w.person_name || '').toLowerCase();
        const reason = (w.reason || '').toLowerCase();
        return person.includes(search) || reason.includes(search);
    });
    const tbody = document.getElementById('withdrawalsTableBody');
    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state">💸 لا توجد سحوبات</div></td></tr>';
        return;
    }
    tbody.innerHTML = filtered.map((w) => {
        return `<tr>
            <td>${w.id}</td>
            <td>${formatDate(w.withdrawal_date)}</td>
            <td>${formatTime(w.created_at)}</td>
            <td>${w.person_name}</td>
            <td>${formatCurrency(w.amount, w.currency === 'USD' ? '$' : 'ل.س')}</td>
            <td>${w.reason}</td>
            <td>
                <button class="btn btn-danger btn-xs" onclick="deleteWithdrawal(${w.id})">🗑️</button>
            </td>
        </tr>`;
    }).join('');
}

window.openWithdrawalModal = function () {
    const today = new Date().toISOString().split('T')[0];
    const html = `
        <div class="modal-overlay" id="withdrawalModal">
            <div class="modal">
                <div class="modal-header"><h3>💸 تسجيل سحب من الخزينة</h3><button class="modal-close" onclick="closeModal('withdrawalModal')">✕</button></div>
                <div class="modal-body">
                    <div class="form-group"><label>اسم الشخص *</label><input id="wdPerson" placeholder="اسم الشخص الذي قام بالسحب"></div>
                    <div class="form-row">
                        <div class="form-group" style="flex:1.3;"><label>المبلغ *</label><input type="number" id="wdAmount" step="0.01" min="0.01" placeholder="0.00"></div>
                        <div class="form-group"><label>العملة</label>
                            <select id="wdCurrency">
                                <option value="SYP">ل.س</option>
                                <option value="USD">$</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex:1.5;"><label>التاريخ *</label><input type="date" id="wdDate" value="${today}"></div>
                    </div>
                    <div class="form-group"><label>السبب</label><textarea id="wdReason" rows="3" placeholder="سبب السحب..."></textarea></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline" onclick="closeModal('withdrawalModal')">إلغاء</button>
                    <button class="btn btn-danger" onclick="saveWithdrawal()">💾 تسجيل السحب</button>
                </div>
            </div>
        </div>`;
    document.getElementById('modalContainer').innerHTML = html;
};

window.saveWithdrawal = async function () {
    const person_name = document.getElementById('wdPerson')?.value.trim();
    const amount = parseFloat(document.getElementById('wdAmount')?.value) || 0;
    const currency = document.getElementById('wdCurrency')?.value || 'SYP';
    const withdrawal_date = document.getElementById('wdDate')?.value;
    const reason = document.getElementById('wdReason')?.value.trim();

    if (!person_name || amount <= 0 || !withdrawal_date) {
        showToast('اسم الشخص والمبلغ والتاريخ مطلوبة', 'error');
        return;
    }

    try {
        await apiFetch('/withdrawals', {
            method: 'POST',
            body: JSON.stringify({ person_name, amount, currency, withdrawal_date, reason })
        });
        showToast('تم تسجيل السحب ✅');
        closeModal('withdrawalModal');
        loadWithdrawals();
        if (typeof window.loadDashboard === 'function') window.loadDashboard();
        if (typeof window.loadReports === 'function') window.loadReports();
    } catch (e) {
        showToast('حدث خطأ أثناء الحفظ', 'error');
        console.error(e);
    }
};

window.deleteWithdrawal = async function (id) {
    if (!confirm('حذف هذا السحب؟')) return;
    try {
        await apiFetch('/withdrawals/' + id, { method: 'DELETE' });
        showToast('تم الحذف 🗑️');
        loadWithdrawals();
        if (typeof window.loadDashboard === 'function') window.loadDashboard();
        if (typeof window.loadReports === 'function') window.loadReports();
    } catch (e) {
        showToast('حدث خطأ أثناء الحذف', 'error');
        console.error(e);
    }
};

loadWithdrawals();
