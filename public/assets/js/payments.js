let allPayments = [];
let allCustomers = [];
let _paymentModalOverrides = {};

async function loadPayments() {
    try {
        const [paymentsData, customersData] = await Promise.all([
            apiFetch('/payments'),
            apiFetch('/customers')
        ]);
        allPayments = paymentsData.data || [];
        allCustomers = customersData.data || [];
        renderPayments();
    } catch (e) {
        showToast('حدث خطأ أثناء تحميل البيانات', 'error');
        console.error(e);
    }
}

function renderPayments() {
    const search = (document.getElementById('paymentSearch')?.value || '').toLowerCase();
    let filtered = allPayments.filter((p) => {
        const cname = (p.customer?.name || '').toLowerCase();
        return cname.includes(search);
    });
    const tbody = document.getElementById('paymentsTableBody');
    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6"><div class="empty-state">💳 لا توجد تسديدات</div></td></tr>';
        return;
    }
    tbody.innerHTML = filtered.map((p) => {
        return `<tr>
            <td>${p.id}</td>
            <td>${formatDate(p.payment_date)}</td>
            <td>${p.customer?.name || '؟'}</td>
            <td>${formatCurrency(p.amount)}</td>
            <td>${p.notes || '-'}</td>
            <td>
                <button class="btn btn-danger btn-xs" onclick="deletePayment(${p.id})">🗑️</button>
            </td>
        </tr>`;
    }).join('');
}

function cachePaymentModalState() {
    _paymentModalOverrides = {
        customer: document.getElementById('payCustomer')?.value || '',
        amount: document.getElementById('payAmount')?.value || '',
        date: document.getElementById('payDate')?.value || '',
        notes: document.getElementById('payNotes')?.value || '',
    };
}
function restorePaymentModalState() {
    const ov = _paymentModalOverrides;
    if (ov.customer !== undefined && document.getElementById('payCustomer')) document.getElementById('payCustomer').value = ov.customer;
    if (ov.amount !== undefined && document.getElementById('payAmount')) document.getElementById('payAmount').value = ov.amount;
    if (ov.date !== undefined && document.getElementById('payDate')) document.getElementById('payDate').value = ov.date;
    if (ov.notes !== undefined && document.getElementById('payNotes')) document.getElementById('payNotes').value = ov.notes;
    _paymentModalOverrides = {};
}

window.openPaymentModal = function () {
    const customerOpts = allCustomers.map((c) => `<option value="${c.id}">${c.name}</option>`).join('');
    const today = new Date().toISOString().split('T')[0];

    const html = `
        <div class="modal-overlay" id="payModal">
            <div class="modal">
                <div class="modal-header"><h3>💵 تسجيل دفعة</h3><button class="modal-close" onclick="closeModal('payModal')">✕</button></div>
                <div class="modal-body">
                    <div class="form-group" style="display:flex;align-items:flex-end;gap:6px;">
                        <div style="flex:1;"><label>العميل *</label><select id="payCustomer">${customerOpts}</select></div>
                        <button class="btn btn-success btn-xs" style="margin-bottom:0;height:38px;" onclick="addCustomerInlinePayment()" title="إضافة عميل">➕</button>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>المبلغ *</label><input type="number" id="payAmount" step="0.01" min="0.01"></div>
                        <div class="form-group"><label>التاريخ *</label><input type="date" id="payDate" value="${today}"></div>
                    </div>
                    <div class="form-group"><label>ملاحظات</label><textarea id="payNotes" rows="2"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline" onclick="closeModal('payModal')">إلغاء</button>
                    <button class="btn btn-success" onclick="savePayment()">💾 حفظ</button>
                </div>
            </div>
        </div>`;
    document.getElementById('modalContainer').innerHTML = html;
    restorePaymentModalState();
};

window.addCustomerInlinePayment = function () {
    cachePaymentModalState();
    const html = `
        <div class="modal-overlay" id="inlineCustModal">
            <div class="modal">
                <div class="modal-header"><h3>➕ إضافة عميل</h3><button class="modal-close" onclick="closeInlineCustomerPayment()">✕</button></div>
                <div class="modal-body">
                    <div class="form-group"><label>الاسم *</label><input id="inlineCustName"></div>
                    <div class="form-row"><div class="form-group"><label>الهاتف</label><input id="inlineCustPhone"></div>
                    <div class="form-group"><label>العنوان</label><input id="inlineCustAddress"></div></div>
                </div>
                <div class="modal-footer"><button class="btn btn-outline" onclick="closeInlineCustomerPayment()">إلغاء</button>
                <button class="btn btn-primary" onclick="saveInlineCustomerPayment()">💾 حفظ</button></div>
            </div>
        </div>`;
    document.getElementById('modalContainer').insertAdjacentHTML('beforeend', html);
};
window.closeInlineCustomerPayment = function () {
    document.getElementById('inlineCustModal')?.remove();
    openPaymentModal();
};
window.saveInlineCustomerPayment = async function () {
    const name = document.getElementById('inlineCustName')?.value.trim();
    if (!name) { showToast('الاسم مطلوب', 'error'); return; }
    const phone = document.getElementById('inlineCustPhone')?.value.trim() || '';
    const address = document.getElementById('inlineCustAddress')?.value.trim() || '';
    try {
        const res = await apiFetch('/customers', { method: 'POST', body: JSON.stringify({ name, phone, address }) });
        showToast('تم إضافة العميل ✅');
        allCustomers.push(res);
        document.getElementById('inlineCustModal')?.remove();
        openPaymentModal();
    } catch (e) {
        showToast('حدث خطأ أثناء الحفظ', 'error');
        console.error(e);
    }
};

window.savePayment = async function () {
    const customer_id = document.getElementById('payCustomer')?.value;
    const amount = parseFloat(document.getElementById('payAmount')?.value) || 0;
    const payment_date = document.getElementById('payDate')?.value;
    const notes = document.getElementById('payNotes')?.value.trim() || '';

    if (!customer_id || amount <= 0 || !payment_date) {
        showToast('جميع الحقول المطلوبة يجب ملؤها', 'error');
        return;
    }

    try {
        await apiFetch('/payments', {
            method: 'POST',
            body: JSON.stringify({ customer_id, amount, payment_date, notes })
        });
        showToast('تم تسجيل الدفعة ✅');
        closeModal('payModal');
        loadPayments();
    } catch (e) {
        showToast('حدث خطأ أثناء الحفظ', 'error');
        console.error(e);
    }
};

window.deletePayment = async function (id) {
    if (!confirm('حذف هذه الدفعة؟')) return;
    try {
        await apiFetch('/payments/' + id, { method: 'DELETE' });
        showToast('تم الحذف 🗑️');
        loadPayments();
    } catch (e) {
        showToast('حدث خطأ أثناء الحذف', 'error');
        console.error(e);
    }
};

loadPayments();
