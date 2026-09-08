let allCustomers = [];
let customersPage = 1;
const customersPerPage = 20;

async function loadCustomers() {
    window.loadCustomers = loadCustomers;
    try {
        let page = 1;
        let allData = [];
        while (true) {
            const res = await apiFetch(`/customers?page=${page}`);
            allData = allData.concat(res.data || []);
            if (page >= (res.last_page || 1)) break;
            page++;
            if (page > 100) break;
        }
        allCustomers = allData;
        renderCustomers();
    } catch (e) {
        showToast('حدث خطأ أثناء تحميل العملاء', 'error');
        console.error(e);
    }
}

function renderCustomers() {
    const search = (document.getElementById('customerSearch')?.value || '').toLowerCase();
    let filtered = allCustomers.filter(
        (c) => c.name.toLowerCase().includes(search) || (c.phone || '').includes(search)
    );

    const totalPages = Math.ceil(filtered.length / customersPerPage);
    if (customersPage > totalPages) customersPage = totalPages || 1;
    const start = (customersPage - 1) * customersPerPage;
    const pageItems = filtered.slice(start, start + customersPerPage);

    const tbody = document.getElementById('customersTableBody');
    if (pageItems.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9"><div class="empty-state">👤 لا يوجد عملاء</div></td></tr>';
        renderCustomersPagination(0);
        return;
    }
    tbody.innerHTML = pageItems.map((c) => {
        const debtSyp = parseFloat(c.debt_syp || 0);
        const debtUsd = parseFloat(c.debt_usd || 0);
        const paidSyp = parseFloat(c.paid_syp || 0);
        const paidUsd = parseFloat(c.paid_usd || 0);
        const overSyp = parseFloat(c.over_syp || 0);
        const overUsd = parseFloat(c.over_usd || 0);
        const balSyp = overSyp - debtSyp;
        const balUsd = overUsd - debtUsd;
        let badge = '';
        if (balSyp > 0) badge += `<span class="badge badge-success">دائن ${formatCurrency(balSyp, 'ل.س')}</span> `;
        if (balSyp < 0) badge += `<span class="badge badge-danger">مدين ${formatCurrency(Math.abs(balSyp), 'ل.س')}</span> `;
        if (balUsd > 0) badge += `<span class="badge badge-success">دائن ${formatCurrency(balUsd, '$')}</span> `;
        if (balUsd < 0) badge += `<span class="badge badge-danger">مدين ${formatCurrency(Math.abs(balUsd), '$')}</span> `;
        if (!badge) badge = '<span class="badge badge-info">متوازن</span>';
        return `<tr>
            <td>${c.id}</td>
            <td>${c.name}</td>
            <td>${c.phone || '-'}</td>
            <td>${c.address || '-'}</td>
            <td style="font-size:0.85em;line-height:1.5;">
                <div style="color:var(--danger)">دين: ${formatCurrency(debtSyp, 'ل.س')} | ${formatCurrency(debtUsd, '$')}</div>
                <div style="color:var(--success)">مسدد: ${formatCurrency(paidSyp, 'ل.س')} | ${formatCurrency(paidUsd, '$')}</div>
                <div style="color:var(--primary)">رصيد زائد: ${formatCurrency(overSyp, 'ل.س')} | ${formatCurrency(overUsd, '$')}</div>
            </td>
            <td>${badge}</td>
            <td>${formatDate(c.created_at)}</td>
            <td>${formatTime(c.created_at)}</td>
            <td>
                <button class="btn btn-outline btn-xs" onclick="editCustomer(${c.id})">✏️</button>
                <button class="btn btn-danger btn-xs" onclick="deleteCustomer(${c.id})">🗑️</button>
            </td>
        </tr>`;
    }).join('');
    renderCustomersPagination(filtered.length);
}

function renderCustomersPagination(total) {
    const totalPages = Math.ceil(total / customersPerPage);
    const container = document.getElementById('customersPagination');
    if (!container) return;
    if (totalPages <= 1) { container.innerHTML = ''; return; }
    let html = '<div class="pagination" style="display:flex;justify-content:center;align-items:center;gap:6px;margin-top:16px;flex-wrap:wrap;">';
    html += `<button class="btn btn-outline btn-xs" onclick="changeCustomersPage(${customersPage - 1})" ${customersPage === 1 ? 'disabled' : ''}>← السابق</button>`;
    for (let i = 1; i <= totalPages; i++) {
        if (i === customersPage) { html += `<span class="btn btn-primary btn-xs" style="cursor:default">${i}</span>`; }
        else if (i === 1 || i === totalPages || (i >= customersPage - 1 && i <= customersPage + 1)) { html += `<button class="btn btn-outline btn-xs" onclick="changeCustomersPage(${i})">${i}</button>`; }
        else if (i === customersPage - 2 || i === customersPage + 2) { html += `<span style="padding:4px 8px;">…</span>`; }
    }
    html += `<button class="btn btn-outline btn-xs" onclick="changeCustomersPage(${customersPage + 1})" ${customersPage === totalPages ? 'disabled' : ''}>التالي →</button>`;
    html += '</div>';
    container.innerHTML = html;
}

window.changeCustomersPage = function (page) {
    const search = (document.getElementById('customerSearch')?.value || '').toLowerCase();
    let filtered = allCustomers.filter((c) => c.name.toLowerCase().includes(search) || (c.phone || '').includes(search));
    const totalPages = Math.ceil(filtered.length / customersPerPage);
    if (page < 1 || page > totalPages) return;
    customersPage = page;
    renderCustomers();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

window.openCustomerModal = function (customer = null) {
    const isEdit = customer !== null;
    const html = `
        <div class="modal-overlay" id="custModal">
            <div class="modal">
                <div class="modal-header"><h3>${isEdit ? '✏️ تعديل' : '➕ إضافة'} عميل</h3><button class="modal-close" onclick="closeModal('custModal')">✕</button></div>
                <div class="modal-body">
                    <div class="form-group"><label>الاسم *</label><input id="custName" value="${isEdit ? customer.name : ''}"></div>
                    <div class="form-row">
                        <div class="form-group"><label>الهاتف</label><input id="custPhone" value="${isEdit ? customer.phone || '' : ''}"></div>
                        <div class="form-group"><label>العنوان</label><input id="custAddress" value="${isEdit ? customer.address || '' : ''}"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline" onclick="closeModal('custModal')">إلغاء</button>
                    <button class="btn btn-primary" onclick="saveCustomer(${isEdit ? customer.id : 'null'})">💾 حفظ</button>
                </div>
            </div>
        </div>`;
    document.getElementById('modalContainer').innerHTML = html;
};

window.editCustomer = function (id) {
    const c = allCustomers.find((x) => x.id === id);
    if (c) openCustomerModal(c);
};

window.saveCustomer = async function (id) {
    const name = document.getElementById('custName')?.value.trim();
    if (!name) { showToast('الاسم مطلوب', 'error'); return; }
    const phone = document.getElementById('custPhone')?.value.trim() || '';
    const address = document.getElementById('custAddress')?.value.trim() || '';

    try {
        if (id) {
            await apiFetch('/customers/' + id, {
                method: 'PUT',
                body: JSON.stringify({ name, phone, address })
            });
            showToast('تم التحديث ✅');
        } else {
            await apiFetch('/customers', {
                method: 'POST',
                body: JSON.stringify({ name, phone, address })
            });
            showToast('تم الإضافة ✅');
        }
        closeModal('custModal');
        loadCustomers();
        if (typeof window.loadSales === 'function') window.loadSales();
        if (typeof window.loadPayments === 'function') window.loadPayments();
    } catch (e) {
        showToast('حدث خطأ أثناء الحفظ', 'error');
        console.error(e);
    }
};

window.deleteCustomer = async function (id) {
    const c = allCustomers.find((x) => x.id === id);
    if (!c) return;
    if (!confirm('حذف ' + c.name + '؟')) return;
    try {
        await apiFetch('/customers/' + id, { method: 'DELETE' });
        showToast('تم الحذف 🗑️');
        loadCustomers();
        if (typeof window.loadSales === 'function') window.loadSales();
        if (typeof window.loadPayments === 'function') window.loadPayments();
    } catch (e) {
        showToast('حدث خطأ أثناء الحذف', 'error');
        console.error(e);
    }
};

loadCustomers();
