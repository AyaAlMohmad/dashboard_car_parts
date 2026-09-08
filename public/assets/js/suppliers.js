let allSuppliers = [];
let suppliersPage = 1;
const suppliersPerPage = 20;

async function loadSuppliers() {
    window.loadSuppliers = loadSuppliers;
    try {
        let page = 1;
        let allData = [];
        while (true) {
            const res = await apiFetch(`/suppliers?page=${page}`);
            allData = allData.concat(res.data || []);
            if (page >= (res.last_page || 1)) break;
            page++;
            if (page > 100) break;
        }
        allSuppliers = allData;
        renderSuppliers();
    } catch (e) {
        showToast('حدث خطأ أثناء تحميل الموردين', 'error');
        console.error(e);
    }
}

function renderSuppliers() {
    const search = (document.getElementById('supplierSearch')?.value || '').toLowerCase();
    let filtered = allSuppliers.filter(
        (s) => s.name.toLowerCase().includes(search) || (s.phone || '').includes(search)
    );

    const totalPages = Math.ceil(filtered.length / suppliersPerPage);
    if (suppliersPage > totalPages) suppliersPage = totalPages || 1;
    const start = (suppliersPage - 1) * suppliersPerPage;
    const pageItems = filtered.slice(start, start + suppliersPerPage);

    const tbody = document.getElementById('suppliersTableBody');
    if (pageItems.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9"><div class="empty-state">🏭 لا يوجد موردين</div></td></tr>';
        renderSuppliersPagination(0);
        return;
    }
    tbody.innerHTML = pageItems.map((s) => {
        const debtSyp = parseFloat(s.debt_syp || 0);
        const debtUsd = parseFloat(s.debt_usd || 0);
        const paidSyp = parseFloat(s.paid_syp || 0);
        const paidUsd = parseFloat(s.paid_usd || 0);
        const overSyp = parseFloat(s.over_syp || 0);
        const overUsd = parseFloat(s.over_usd || 0);
        const balSyp = parseFloat(s.balance_syp || 0);
        const balUsd = parseFloat(s.balance_usd || 0);
        let badge = '';
        if (balSyp > 0) badge += `<span class="badge badge-danger">علينا ${formatCurrency(balSyp, 'ل.س')}</span> `;
        if (balSyp < 0) badge += `<span class="badge badge-success">لنا ${formatCurrency(Math.abs(balSyp), 'ل.س')}</span> `;
        if (balUsd > 0) badge += `<span class="badge badge-danger">علينا ${formatCurrency(balUsd, '$')}</span> `;
        if (balUsd < 0) badge += `<span class="badge badge-success">لنا ${formatCurrency(Math.abs(balUsd), '$')}</span> `;
        if (!badge) badge = '<span class="badge badge-info">متوازن</span>';
        return `<tr>
            <td>${s.id}</td>
            <td>${s.name}</td>
            <td>${s.phone || '-'}</td>
            <td>${s.address || '-'}</td>
            <td style="font-size:0.85em;line-height:1.5;">
                <div style="color:var(--danger)">دين: ${formatCurrency(debtSyp, 'ل.س')} | ${formatCurrency(debtUsd, '$')}</div>
                <div style="color:var(--success)">مسدد: ${formatCurrency(paidSyp, 'ل.س')} | ${formatCurrency(paidUsd, '$')}</div>
                <div style="color:var(--primary)">رصيد زائد: ${formatCurrency(overSyp, 'ل.س')} | ${formatCurrency(overUsd, '$')}</div>
            </td>
            <td>${badge}</td>
            <td>${formatDate(s.created_at)}</td>
            <td>${formatTime(s.created_at)}</td>
            <td>
                <button class="btn btn-outline btn-xs" onclick="editSupplier(${s.id})">✏️</button>
                <button class="btn btn-danger btn-xs" onclick="deleteSupplier(${s.id})">🗑️</button>
            </td>
        </tr>`;
    }).join('');
    renderSuppliersPagination(filtered.length);
}

function renderSuppliersPagination(total) {
    const totalPages = Math.ceil(total / suppliersPerPage);
    const container = document.getElementById('suppliersPagination');
    if (!container) return;
    if (totalPages <= 1) { container.innerHTML = ''; return; }
    let html = '<div class="pagination" style="display:flex;justify-content:center;align-items:center;gap:6px;margin-top:16px;flex-wrap:wrap;">';
    html += `<button class="btn btn-outline btn-xs" onclick="changeSuppliersPage(${suppliersPage - 1})" ${suppliersPage === 1 ? 'disabled' : ''}>← السابق</button>`;
    for (let i = 1; i <= totalPages; i++) {
        if (i === suppliersPage) { html += `<span class="btn btn-primary btn-xs" style="cursor:default">${i}</span>`; }
        else if (i === 1 || i === totalPages || (i >= suppliersPage - 1 && i <= suppliersPage + 1)) { html += `<button class="btn btn-outline btn-xs" onclick="changeSuppliersPage(${i})">${i}</button>`; }
        else if (i === suppliersPage - 2 || i === suppliersPage + 2) { html += `<span style="padding:4px 8px;">…</span>`; }
    }
    html += `<button class="btn btn-outline btn-xs" onclick="changeSuppliersPage(${suppliersPage + 1})" ${suppliersPage === totalPages ? 'disabled' : ''}>التالي →</button>`;
    html += '</div>';
    container.innerHTML = html;
}

window.changeSuppliersPage = function (page) {
    const search = (document.getElementById('supplierSearch')?.value || '').toLowerCase();
    let filtered = allSuppliers.filter((s) => s.name.toLowerCase().includes(search) || (s.phone || '').includes(search));
    const totalPages = Math.ceil(filtered.length / suppliersPerPage);
    if (page < 1 || page > totalPages) return;
    suppliersPage = page;
    renderSuppliers();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

window.openSupplierModal = function (supplier = null) {
    const isEdit = supplier !== null;
    const html = `
        <div class="modal-overlay" id="supModal">
            <div class="modal">
                <div class="modal-header"><h3>${isEdit ? '✏️ تعديل' : '➕ إضافة'} مورد</h3><button class="modal-close" onclick="closeModal('supModal')">✕</button></div>
                <div class="modal-body">
                    <div class="form-group"><label>الاسم *</label><input id="supName" value="${isEdit ? supplier.name : ''}"></div>
                    <div class="form-row">
                        <div class="form-group"><label>الهاتف</label><input id="supPhone" value="${isEdit ? supplier.phone || '' : ''}"></div>
                        <div class="form-group"><label>العنوان</label><input id="supAddress" value="${isEdit ? supplier.address || '' : ''}"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline" onclick="closeModal('supModal')">إلغاء</button>
                    <button class="btn btn-primary" onclick="saveSupplier(${isEdit ? supplier.id : 'null'})">💾 حفظ</button>
                </div>
            </div>
        </div>`;
    document.getElementById('modalContainer').innerHTML = html;
};

window.editSupplier = function (id) {
    const s = allSuppliers.find((x) => x.id === id);
    if (s) openSupplierModal(s);
};

window.saveSupplier = async function (id) {
    const name = document.getElementById('supName')?.value.trim();
    if (!name) { showToast('الاسم مطلوب', 'error'); return; }
    const phone = document.getElementById('supPhone')?.value.trim() || '';
    const address = document.getElementById('supAddress')?.value.trim() || '';
    try {
        if (id) {
            await apiFetch('/suppliers/' + id, { method: 'PUT', body: JSON.stringify({ name, phone, address }) });
            showToast('تم التحديث ✅');
        } else {
            await apiFetch('/suppliers', { method: 'POST', body: JSON.stringify({ name, phone, address }) });
            showToast('تم الإضافة ✅');
        }
        closeModal('supModal');
        loadSuppliers();
        if (typeof window.loadPurchases === 'function') window.loadPurchases();
        if (typeof window.loadSupplierPayments === 'function') window.loadSupplierPayments();
    } catch (e) {
        showToast('حدث خطأ أثناء الحفظ', 'error');
        console.error(e);
    }
};

window.deleteSupplier = async function (id) {
    const s = allSuppliers.find((x) => x.id === id);
    if (!s) return;
    if (!confirm('حذف ' + s.name + '؟')) return;
    try {
        await apiFetch('/suppliers/' + id, { method: 'DELETE' });
        showToast('تم الحذف 🗑️');
        loadSuppliers();
        if (typeof window.loadPurchases === 'function') window.loadPurchases();
        if (typeof window.loadSupplierPayments === 'function') window.loadSupplierPayments();
    } catch (e) {
        showToast('حدث خطأ أثناء الحذف', 'error');
        console.error(e);
    }
};

loadSuppliers();
