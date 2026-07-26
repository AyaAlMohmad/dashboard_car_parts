let allSupplierPayments = [];
let allSuppliers = [];
let _supPayModalOverrides = {};

async function loadSupplierPayments() {
    window.loadSupplierPayments = loadSupplierPayments;
    try {
        const [paymentsData, suppliersData] = await Promise.all([
            apiFetch('/supplier-payments'),
            apiFetch('/suppliers')
        ]);
        allSupplierPayments = paymentsData.data || [];
        allSuppliers = suppliersData.data || [];
        renderSupplierPayments();
    } catch (e) {
        showToast('حدث خطأ أثناء تحميل البيانات', 'error');
        console.error(e);
    }
}

function renderSupplierPayments() {
    const search = (document.getElementById('supplierPaymentSearch')?.value || '').toLowerCase();
    let filtered = allSupplierPayments.filter((p) => {
        const sname = (p.supplier?.name || '').toLowerCase();
        return sname.includes(search);
    });
    const tbody = document.getElementById('supplierPaymentsTableBody');
    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state">💳 لا توجد دفعات</div></td></tr>';
        return;
    }
    tbody.innerHTML = filtered.map((p) => {
        return `<tr>
            <td>${p.id}</td>
            <td>${formatDate(p.payment_date)}</td>
            <td>${formatTime(p.created_at)}</td>
            <td>${p.supplier?.name || '؟'}</td>
            <td>${formatCurrency(p.amount, p.currency === 'USD' ? '$' : 'ل.س')}</td>
            <td>${p.notes || '-'}</td>
            <td>
                <button class="btn btn-danger btn-xs" onclick="deleteSupplierPayment(${p.id})">🗑️</button>
            </td>
        </tr>`;
    }).join('');
}

function cacheSupPayModalState() {
    _supPayModalOverrides = {
        supplier: document.getElementById('supPaySupplier')?.value || '',
        supplierInput: document.getElementById('supPaySupplierInput')?.value || '',
        amount: document.getElementById('supPayAmount')?.value || '',
        currency: document.getElementById('supPayCurrency')?.value || '',
        date: document.getElementById('supPayDate')?.value || '',
        notes: document.getElementById('supPayNotes')?.value || '',
    };
}
function restoreSupPayModalState() {
    const ov = _supPayModalOverrides;
    if (ov.supplier !== undefined && window._supPaySupplierSelect) window._supPaySupplierSelect.setValue(ov.supplier, ov.supplierInput || '');
    if (ov.amount !== undefined && document.getElementById('supPayAmount')) document.getElementById('supPayAmount').value = ov.amount;
    if (ov.currency !== undefined && document.getElementById('supPayCurrency')) document.getElementById('supPayCurrency').value = ov.currency;
    if (ov.date !== undefined && document.getElementById('supPayDate')) document.getElementById('supPayDate').value = ov.date;
    if (ov.notes !== undefined && document.getElementById('supPayNotes')) document.getElementById('supPayNotes').value = ov.notes;
    _supPayModalOverrides = {};
}

window.openSupplierPaymentModal = function () {
    const supplierOpts = allSuppliers.map((s) => ({ value: String(s.id), text: `${s.name} - ${s.phone || ''}` }));
    const today = new Date().toISOString().split('T')[0];

    const html = `
        <div class="modal-overlay" id="supPayModal">
            <div class="modal">
                <div class="modal-header"><h3>💵 دفع للمورد</h3><button class="modal-close" onclick="closeModal('supPayModal')">✕</button></div>
                <div class="modal-body">
                    <div class="form-group" style="display:flex;align-items:flex-end;gap:6px;">
                        <div style="flex:1;"><label>المورد *</label><input id="supPaySupplierInput" placeholder="اكتب اسم المورد..."><input type="hidden" id="supPaySupplier"></div>
                        <button class="btn btn-success btn-xs" style="margin-bottom:0;height:38px;" onclick="addSupplierInlineSupPay()" title="إضافة مورد">➕</button>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>المبلغ *</label><input type="number" id="supPayAmount" step="0.01" min="0.01"></div>
                        <div class="form-group"><label>التاريخ *</label><input type="date" id="supPayDate" value="${today}"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>العملة</label>
                            <select id="supPayCurrency">
                                <option value="SYP">ليرة سورية (SYP)</option>
                                <option value="USD">دولار (USD)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group"><label>ملاحظات</label><textarea id="supPayNotes" rows="2"></textarea></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline" onclick="closeModal('supPayModal')">إلغاء</button>
                    <button class="btn btn-success" onclick="saveSupplierPayment()">💾 حفظ</button>
                </div>
            </div>
        </div>`;
    document.getElementById('modalContainer').innerHTML = html;
    window._supPaySupplierSelect = initSearchableSelect('supPaySupplierInput', supplierOpts, (val, text) => { document.getElementById('supPaySupplier').value = val; });
    restoreSupPayModalState();
};

window.addSupplierInlineSupPay = function () {
    cacheSupPayModalState();
    const html = `
        <div class="modal-overlay" id="inlineSupModal">
            <div class="modal">
                <div class="modal-header"><h3>➕ إضافة مورد</h3><button class="modal-close" onclick="closeInlineSupplierSupPay()">✕</button></div>
                <div class="modal-body">
                    <div class="form-group"><label>الاسم *</label><input id="inlineSupName"></div>
                    <div class="form-row"><div class="form-group"><label>الهاتف</label><input id="inlineSupPhone"></div>
                    <div class="form-group"><label>العنوان</label><input id="inlineSupAddress"></div></div>
                </div>
                <div class="modal-footer"><button class="btn btn-outline" onclick="closeInlineSupplierSupPay()">إلغاء</button>
                <button class="btn btn-primary" onclick="saveInlineSupplierSupPay()">💾 حفظ</button></div>
            </div>
        </div>`;
    document.getElementById('modalContainer').insertAdjacentHTML('beforeend', html);
};
window.closeInlineSupplierSupPay = function () {
    document.getElementById('inlineSupModal')?.remove();
    openSupplierPaymentModal();
};
window.saveInlineSupplierSupPay = async function () {
    const name = document.getElementById('inlineSupName')?.value.trim();
    if (!name) { showToast('الاسم مطلوب', 'error'); return; }
    const phone = document.getElementById('inlineSupPhone')?.value.trim() || '';
    const address = document.getElementById('inlineSupAddress')?.value.trim() || '';
    try {
        const res = await apiFetch('/suppliers', { method: 'POST', body: JSON.stringify({ name, phone, address }) });
        showToast('تم إضافة المورد ✅');
        allSuppliers.push(res);
        document.getElementById('inlineSupModal')?.remove();
        openSupplierPaymentModal();
    } catch (e) {
        showToast('حدث خطأ أثناء الحفظ', 'error');
        console.error(e);
    }
};

window.saveSupplierPayment = async function () {
    const supplier_id = document.getElementById('supPaySupplier')?.value;
    const amount = parseFloat(document.getElementById('supPayAmount')?.value) || 0;
    const payment_date = document.getElementById('supPayDate')?.value;
    const notes = document.getElementById('supPayNotes')?.value.trim() || '';
    const currency = document.getElementById('supPayCurrency')?.value || 'SYP';

    if (!supplier_id || amount <= 0 || !payment_date) {
        showToast('جميع الحقول المطلوبة يجب ملؤها', 'error');
        return;
    }

    try {
        await apiFetch('/supplier-payments', {
            method: 'POST',
            body: JSON.stringify({ supplier_id, amount, payment_date, notes, currency })
        });
        showToast('تم تسجيل الدفعة ✅');
        closeModal('supPayModal');
        loadSupplierPayments();
        if (typeof window.loadSuppliers === 'function') window.loadSuppliers();
        if (typeof window.loadPurchases === 'function') window.loadPurchases();
    } catch (e) {
        showToast('حدث خطأ أثناء الحفظ', 'error');
        console.error(e);
    }
};

window.deleteSupplierPayment = async function (id) {
    if (!confirm('حذف هذه الدفعة؟')) return;
    try {
        await apiFetch('/supplier-payments/' + id, { method: 'DELETE' });
        showToast('تم الحذف 🗑️');
        loadSupplierPayments();
        if (typeof window.loadSuppliers === 'function') window.loadSuppliers();
        if (typeof window.loadPurchases === 'function') window.loadPurchases();
    } catch (e) {
        showToast('حدث خطأ أثناء الحذف', 'error');
        console.error(e);
    }
};

loadSupplierPayments();
