let allCustomers = [];

async function loadCustomers() {
    try {
        const data = await apiFetch('/customers');
        allCustomers = data.data || [];
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
    const tbody = document.getElementById('customersTableBody');
    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9"><div class="empty-state">👤 لا يوجد عملاء</div></td></tr>';
        return;
    }
    tbody.innerHTML = filtered.map((c) => {
        const bal = c.balance || 0;
        let badge = bal < 0
            ? '<span class="badge badge-danger">مدين ' + formatCurrency(Math.abs(bal)) + ' ل.س</span>'
            : bal > 0
                ? '<span class="badge badge-success">دائن ' + formatCurrency(bal) + ' ل.س</span>'
                : '<span class="badge badge-info">متوازن</span>';
        return `<tr>
            <td>${c.id}</td>
            <td>${c.name}</td>
            <td>${c.phone || '-'}</td>
            <td>${c.address || '-'}</td>
            <td>${formatCurrency(bal)}</td>
            <td>${badge}</td>
            <td>${formatDate(c.created_at)}</td>
            <td>${formatTime(c.created_at)}</td>
            <td>
                <button class="btn btn-outline btn-xs" onclick="editCustomer(${c.id})">✏️</button>
                <button class="btn btn-danger btn-xs" onclick="deleteCustomer(${c.id})">🗑️</button>
            </td>
        </tr>`;
    }).join('');
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
    } catch (e) {
        showToast('حدث خطأ أثناء الحذف', 'error');
        console.error(e);
    }
};

loadCustomers();
