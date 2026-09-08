let allPayments = [];
let allCustomers = [];
let _paymentModalOverrides = {};
let paymentsPage = 1;
const paymentsPerPage = 20;

async function loadPayments() {
    window.loadPayments = loadPayments;
    try {
        let page = 1;
        let allData = [];
        while (true) {
            const res = await apiFetch(`/payments?page=${page}`);
            allData = allData.concat(res.data || []);
            if (page >= (res.last_page || 1)) break;
            page++;
            if (page > 100) break;
        }
        allPayments = allData;

        let cPage = 1;
        let allCust = [];
        while (true) {
            const res = await apiFetch(`/customers?page=${cPage}`);
            allCust = allCust.concat(res.data || []);
            if (cPage >= (res.last_page || 1)) break;
            cPage++;
            if (cPage > 100) break;
        }
        allCustomers = allCust;
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

    const totalPages = Math.ceil(filtered.length / paymentsPerPage);
    if (paymentsPage > totalPages) paymentsPage = totalPages || 1;
    const start = (paymentsPage - 1) * paymentsPerPage;
    const pageItems = filtered.slice(start, start + paymentsPerPage);

    const tbody = document.getElementById('paymentsTableBody');
    if (pageItems.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state">💳 لا توجد تسديدات</div></td></tr>';
        renderPaymentsPagination(0);
        return;
    }
    tbody.innerHTML = pageItems.map((p) => {
        return `<tr>
            <td>${p.id}</td>
            <td>${formatDate(p.payment_date)}</td>
            <td>${formatTime(p.created_at)}</td>
            <td>${p.customer?.name || '؟'}</td>
            <td>${formatCurrency(p.amount, p.currency === 'USD' ? '$' : 'ل.س')}</td>
            <td>${p.notes || '-'}</td>
            <td>
                <button class="btn btn-danger btn-xs" onclick="deletePayment(${p.id})">🗑️</button>
            </td>
        </tr>`;
    }).join('');
    renderPaymentsPagination(filtered.length);
}

function renderPaymentsPagination(total) {
    const totalPages = Math.ceil(total / paymentsPerPage);
    const container = document.getElementById('paymentsPagination');
    if (!container) return;
    if (totalPages <= 1) { container.innerHTML = ''; return; }
    let html = '<div class="pagination" style="display:flex;justify-content:center;align-items:center;gap:6px;margin-top:16px;flex-wrap:wrap;">';
    html += `<button class="btn btn-outline btn-xs" onclick="changePaymentsPage(${paymentsPage - 1})" ${paymentsPage === 1 ? 'disabled' : ''}>← السابق</button>`;
    for (let i = 1; i <= totalPages; i++) {
        if (i === paymentsPage) { html += `<span class="btn btn-primary btn-xs" style="cursor:default">${i}</span>`; }
        else if (i === 1 || i === totalPages || (i >= paymentsPage - 1 && i <= paymentsPage + 1)) { html += `<button class="btn btn-outline btn-xs" onclick="changePaymentsPage(${i})">${i}</button>`; }
        else if (i === paymentsPage - 2 || i === paymentsPage + 2) { html += `<span style="padding:4px 8px;">…</span>`; }
    }
    html += `<button class="btn btn-outline btn-xs" onclick="changePaymentsPage(${paymentsPage + 1})" ${paymentsPage === totalPages ? 'disabled' : ''}>التالي →</button>`;
    html += '</div>';
    container.innerHTML = html;
}

window.changePaymentsPage = function (page) {
    const search = (document.getElementById('paymentSearch')?.value || '').toLowerCase();
    let filtered = allPayments.filter((p) => {
        const cname = (p.customer?.name || '').toLowerCase();
        return cname.includes(search);
    });
    const totalPages = Math.ceil(filtered.length / paymentsPerPage);
    if (page < 1 || page > totalPages) return;
    paymentsPage = page;
    renderPayments();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cachePaymentModalState() {
    _paymentModalOverrides = {
        customer: document.getElementById('payCustomer')?.value || '',
        customerInput: document.getElementById('payCustomerInput')?.value || '',
        amount: document.getElementById('payAmount')?.value || '',
        currency: document.getElementById('payCurrency')?.value || '',
        date: document.getElementById('payDate')?.value || '',
        notes: document.getElementById('payNotes')?.value || '',
    };
}
function restorePaymentModalState() {
    const ov = _paymentModalOverrides;
    if (ov.customer !== undefined && window._payCustomerSelect) window._payCustomerSelect.setValue(ov.customer, ov.customerInput || '');
    if (ov.amount !== undefined && document.getElementById('payAmount')) document.getElementById('payAmount').value = ov.amount;
    if (ov.currency !== undefined && document.getElementById('payCurrency')) document.getElementById('payCurrency').value = ov.currency;
    if (ov.date !== undefined && document.getElementById('payDate')) document.getElementById('payDate').value = ov.date;
    if (ov.notes !== undefined && document.getElementById('payNotes')) document.getElementById('payNotes').value = ov.notes;
    _paymentModalOverrides = {};
}

window.openPaymentModal = function () {
    const customerOpts = allCustomers.map((c) => ({ value: String(c.id), text: `${c.name} - ${c.phone || ''}` }));
    const today = new Date().toISOString().split('T')[0];

    const html = `
        <div class="modal-overlay" id="payModal">
            <div class="modal">
                <div class="modal-header"><h3>💵 تسجيل دفعة</h3><button class="modal-close" onclick="closeModal('payModal')">✕</button></div>
                <div class="modal-body">
                    <div class="form-group" style="display:flex;align-items:flex-end;gap:6px;">
                        <div style="flex:1;"><label>العميل *</label><input id="payCustomerInput" placeholder="اكتب اسم العميل..."><input type="hidden" id="payCustomer"></div>
                        <button class="btn btn-success btn-xs" style="margin-bottom:0;height:38px;" onclick="addCustomerInlinePayment()" title="إضافة عميل">➕</button>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>المبلغ *</label><input type="number" id="payAmount" step="0.01" min="0.01"></div>
                        <div class="form-group"><label>التاريخ *</label><input type="date" id="payDate" value="${today}"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>العملة</label>
                            <select id="payCurrency">
                                <option value="SYP">ليرة سورية (SYP)</option>
                                <option value="USD">دولار (USD)</option>
                            </select>
                        </div>
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
    window._payCustomerSelect = initSearchableSelect('payCustomerInput', customerOpts, (val, text) => { document.getElementById('payCustomer').value = val; });
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
    const currency = document.getElementById('payCurrency')?.value || 'SYP';

    if (!customer_id || amount <= 0 || !payment_date) {
        showToast('جميع الحقول المطلوبة يجب ملؤها', 'error');
        return;
    }

    try {
        await apiFetch('/payments', {
            method: 'POST',
            body: JSON.stringify({ customer_id, amount, payment_date, notes, currency })
        });
        showToast('تم تسجيل الدفعة ✅');
        closeModal('payModal');
        loadPayments();
        if (typeof window.loadCustomers === 'function') window.loadCustomers();
        if (typeof window.loadSales === 'function') window.loadSales();
    } catch (e) {
        showToast('حدث خطأ أثناء الحفظ', 'error');
        console.error(e);
    }
};

window.openPaymentRefundModal = function () {
    const customerOpts = allCustomers.map((c) => ({ value: String(c.id), text: `${c.name} - ${c.phone || ''}` }));
    const today = new Date().toISOString().split('T')[0];

    const html = `
        <div class="modal-overlay" id="payRefundModal">
            <div class="modal">
                <div class="modal-header"><h3>↩️ استرجاع مبلغ للعميل</h3><button class="modal-close" onclick="closeModal('payRefundModal')">✕</button></div>
                <div class="modal-body">
                    <div class="form-group"><label>العميل *</label>
                        <input id="payRefundCustomerInput" placeholder="اكتب اسم العميل..."><input type="hidden" id="payRefundCustomer">
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>المبلغ *</label><input type="number" id="payRefundAmount" step="0.01" min="0.01"></div>
                        <div class="form-group"><label>التاريخ *</label><input type="date" id="payRefundDate" value="${today}"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>العملة</label>
                            <select id="payRefundCurrency">
                                <option value="SYP">ليرة سورية (SYP)</option>
                                <option value="USD">دولار (USD)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group"><label>ملاحظات</label><textarea id="payRefundNotes" rows="2"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline" onclick="closeModal('payRefundModal')">إلغاء</button>
                    <button class="btn btn-warning" onclick="savePaymentRefund()">💾 حفظ الاسترجاع</button>
                </div>
            </div>
        </div>`;
    document.getElementById('modalContainer').innerHTML = html;
    window._payRefundCustomerSelect = initSearchableSelect('payRefundCustomerInput', customerOpts, (val, text) => { document.getElementById('payRefundCustomer').value = val; });
};

window.savePaymentRefund = async function () {
    const customer_id = document.getElementById('payRefundCustomer')?.value;
    const amount = parseFloat(document.getElementById('payRefundAmount')?.value) || 0;
    const payment_date = document.getElementById('payRefundDate')?.value;
    const notes = document.getElementById('payRefundNotes')?.value.trim() || '';
    const currency = document.getElementById('payRefundCurrency')?.value || 'SYP';

    if (!customer_id || amount <= 0 || !payment_date) {
        showToast('جميع الحقول المطلوبة يجب ملؤها', 'error');
        return;
    }

    try {
        await apiFetch('/payments/refund', {
            method: 'POST',
            body: JSON.stringify({ customer_id, amount, payment_date, notes, currency })
        });
        showToast('تم استرجاع المبلغ ✅');
        closeModal('payRefundModal');
        loadPayments();
        if (typeof window.loadCustomers === 'function') window.loadCustomers();
        if (typeof window.loadSales === 'function') window.loadSales();
    } catch (e) {
        showToast('حدث خطأ أثناء الاسترجاع', 'error');
        console.error(e);
    }
};

window.deletePayment = async function (id) {
    if (!confirm('حذف هذه الدفعة؟')) return;
    try {
        await apiFetch('/payments/' + id, { method: 'DELETE' });
        showToast('تم الحذف 🗑️');
        loadPayments();
        if (typeof window.loadCustomers === 'function') window.loadCustomers();
        if (typeof window.loadSales === 'function') window.loadSales();
    } catch (e) {
        showToast('حدث خطأ أثناء الحذف', 'error');
        console.error(e);
    }
};

loadPayments();
