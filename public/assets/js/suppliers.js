let allSuppliers = [];

async function loadSuppliers() {
    try {
        const data = await apiFetch('/suppliers');
        allSuppliers = data.data || [];
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
    const tbody = document.getElementById('suppliersTableBody');
    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9"><div class="empty-state">🏭 لا يوجد موردين</div></td></tr>';
        return;
    }
    tbody.innerHTML = filtered.map((s) => {
        const bal = s.balance || 0;
        let badge = bal > 0
            ? '<span class="badge badge-danger">علينا ' + formatCurrency(bal) + ' ل.س</span>'
            : bal < 0
                ? '<span class="badge badge-success">لنا ' + formatCurrency(Math.abs(bal)) + ' ل.س</span>'
                : '<span class="badge badge-info">متوازن</span>';
        return `<tr>
            <td>${s.id}</td>
            <td>${s.name}</td>
            <td>${s.phone || '-'}</td>
            <td>${s.address || '-'}</td>
            <td>${formatCurrency(bal)}</td>
            <td>${badge}</td>
            <td>${formatDate(s.created_at)}</td>
            <td>${formatTime(s.created_at)}</td>
            <td>
                <button class="btn btn-outline btn-xs" onclick="editSupplier(${s.id})">✏️</button>
                <button class="btn btn-danger btn-xs" onclick="deleteSupplier(${s.id})">🗑️</button>
            </td>
        </tr>`;
    }).join('');
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
    } catch (e) {
        showToast('حدث خطأ أثناء الحذف', 'error');
        console.error(e);
    }
};

loadSuppliers();
