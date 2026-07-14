let allSales = [];
let allCustomers = [];
let allParts = [];
let _saleModalOverrides = {};

async function loadSales() {
    try {
        const [salesData, customersData, partsData] = await Promise.all([
            apiFetch('/sales'),
            apiFetch('/customers'),
            apiFetch('/parts')
        ]);
        allSales = salesData.data || [];
        allCustomers = customersData.data || [];
        allParts = partsData.data || [];
        renderSales();
    } catch (e) {
        showToast('حدث خطأ أثناء تحميل البيانات', 'error');
        console.error(e);
    }
}

function renderSales() {
    const search = (document.getElementById('saleSearch')?.value || '').toLowerCase();
    let filtered = allSales.filter((s) => {
        const cname = (s.customer?.name || '').toLowerCase();
        const pname = (s.part?.name || '').toLowerCase();
        return cname.includes(search) || pname.includes(search);
    });
    const tbody = document.getElementById('salesTableBody');
    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10"><div class="empty-state">🛒 لا توجد مبيعات</div></td></tr>';
        return;
    }
    tbody.innerHTML = filtered.map((s) => {
        return `<tr>
            <td>${s.id}</td>
            <td>${formatDate(s.sale_date)}</td>
            <td>${s.customer?.name || '؟'}</td>
            <td>${s.part?.name || '؟'}</td>
            <td>${s.quantity}</td>
            <td>${formatCurrency(s.total)}</td>
            <td>${formatCurrency(s.paid)}</td>
            <td>${formatCurrency(s.remaining)}</td>
            <td>${renderBadge(s.status)}</td>
            <td>
                <button class="btn btn-danger btn-xs" onclick="deleteSale(${s.id})">🗑️</button>
            </td>
        </tr>`;
    }).join('');
}

function cacheSaleModalState() {
    _saleModalOverrides = {
        customer: document.getElementById('saleCustomer')?.value || '',
        part: document.getElementById('salePart')?.value || '',
        qty: document.getElementById('saleQty')?.value || '',
        date: document.getElementById('saleDate')?.value || '',
        paid: document.getElementById('salePaid')?.value || '',
    };
}
function restoreSaleModalState() {
    const ov = _saleModalOverrides;
    if (ov.customer !== undefined && document.getElementById('saleCustomer')) document.getElementById('saleCustomer').value = ov.customer;
    if (ov.part !== undefined && document.getElementById('salePart')) document.getElementById('salePart').value = ov.part;
    if (ov.qty !== undefined && document.getElementById('saleQty')) document.getElementById('saleQty').value = ov.qty;
    if (ov.date !== undefined && document.getElementById('saleDate')) document.getElementById('saleDate').value = ov.date;
    if (ov.paid !== undefined && document.getElementById('salePaid')) document.getElementById('salePaid').value = ov.paid;
    _saleModalOverrides = {};
}

window.openSaleModal = function () {
    const customerOpts = allCustomers.map((c) => `<option value="${c.id}">${c.name}</option>`).join('');
    const partOpts = allParts.map((p) => `<option value="${p.id}">${p.name} (${p.part_number || 'بدون رقم'}) - متاح: ${p.quantity}</option>`).join('');
    const today = new Date().toISOString().split('T')[0];

    const html = `
        <div class="modal-overlay" id="saleModal">
            <div class="modal">
                <div class="modal-header"><h3>🛒 تسجيل عملية بيع</h3><button class="modal-close" onclick="closeModal('saleModal')">✕</button></div>
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group" style="display:flex;align-items:flex-end;gap:6px;">
                            <div style="flex:1;"><label>العميل *</label><select id="saleCustomer">${customerOpts}</select></div>
                            <button class="btn btn-success btn-xs" style="margin-bottom:0;height:38px;" onclick="addCustomerInline()" title="إضافة عميل">➕</button>
                        </div>
                        <div class="form-group" style="display:flex;align-items:flex-end;gap:6px;">
                            <div style="flex:1;"><label>القطعة *</label><select id="salePart">${partOpts}</select></div>
                            <button class="btn btn-success btn-xs" style="margin-bottom:0;height:38px;" onclick="addPartInline()" title="إضافة قطعة">➕</button>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>الكمية *</label><input type="number" id="saleQty" value="1" min="1"></div>
                        <div class="form-group"><label>التاريخ *</label><input type="date" id="saleDate" value="${today}"></div>
                    </div>
                    <div class="form-group"><label>المبلغ المدفوع</label><input type="number" id="salePaid" value="0" step="0.01"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline" onclick="closeModal('saleModal')">إلغاء</button>
                    <button class="btn btn-primary" onclick="saveSale()">💾 حفظ</button>
                </div>
            </div>
        </div>`;
    document.getElementById('modalContainer').innerHTML = html;
    restoreSaleModalState();
};

window.addCustomerInline = function () {
    cacheSaleModalState();
    const html = `
        <div class="modal-overlay" id="inlineCustModal">
            <div class="modal">
                <div class="modal-header"><h3>➕ إضافة عميل</h3><button class="modal-close" onclick="closeInlineCustomer()">✕</button></div>
                <div class="modal-body">
                    <div class="form-group"><label>الاسم *</label><input id="inlineCustName"></div>
                    <div class="form-row"><div class="form-group"><label>الهاتف</label><input id="inlineCustPhone"></div>
                    <div class="form-group"><label>العنوان</label><input id="inlineCustAddress"></div></div>
                </div>
                <div class="modal-footer"><button class="btn btn-outline" onclick="closeInlineCustomer()">إلغاء</button>
                <button class="btn btn-primary" onclick="saveInlineCustomer()">💾 حفظ</button></div>
            </div>
        </div>`;
    document.getElementById('modalContainer').insertAdjacentHTML('beforeend', html);
};
window.closeInlineCustomer = function () {
    document.getElementById('inlineCustModal')?.remove();
    openSaleModal();
};
window.saveInlineCustomer = async function () {
    const name = document.getElementById('inlineCustName')?.value.trim();
    if (!name) { showToast('الاسم مطلوب', 'error'); return; }
    const phone = document.getElementById('inlineCustPhone')?.value.trim() || '';
    const address = document.getElementById('inlineCustAddress')?.value.trim() || '';
    try {
        const res = await apiFetch('/customers', { method: 'POST', body: JSON.stringify({ name, phone, address }) });
        showToast('تم إضافة العميل ✅');
        allCustomers.push(res);
        document.getElementById('inlineCustModal')?.remove();
        openSaleModal();
    } catch (e) {
        showToast('حدث خطأ أثناء الحفظ', 'error');
        console.error(e);
    }
};

window.addPartInline = function () {
    cacheSaleModalState();
    const html = `
        <div class="modal-overlay" id="inlinePartModal">
            <div class="modal">
                <div class="modal-header"><h3>➕ إضافة قطعة</h3><button class="modal-close" onclick="closeInlinePart()">✕</button></div>
                <div class="modal-body">
                    <div class="form-group"><label>الاسم *</label><input id="inlinePartName"></div>
                    <div class="form-row"><div class="form-group"><label>رقم القطعة</label><input id="inlinePartNumber"></div>
                    <div class="form-group"><label>الفئة</label><select id="inlinePartCategory"></select></div></div>
                    <div class="form-row"><div class="form-group"><label>الكمية *</label><input type="number" id="inlinePartQty" value="0" min="0"></div>
                    <div class="form-group"><label>حد التنبيه</label><input type="number" id="inlinePartAlert" value="5" min="1"></div></div>
                    <div class="form-row"><div class="form-group"><label>سعر الشراء</label><input type="number" id="inlinePartPurchasePrice" value="0" step="0.01"></div>
                    <div class="form-group"><label>سعر البيع *</label><input type="number" id="inlinePartSalePrice" value="0" step="0.01"></div></div>
                    <div class="form-group"><label>المورد</label><select id="inlinePartSupplier"></select></div>
                </div>
                <div class="modal-footer"><button class="btn btn-outline" onclick="closeInlinePart()">إلغاء</button>
                <button class="btn btn-primary" onclick="saveInlinePart()">💾 حفظ</button></div>
            </div>
        </div>`;
    document.getElementById('modalContainer').insertAdjacentHTML('beforeend', html);
    const catSelect = document.getElementById('inlinePartCategory');
    const supSelect = document.getElementById('inlinePartSupplier');
    // Load categories and suppliers from parts.js or fetch them
    Promise.all([apiFetch('/categories'), apiFetch('/suppliers')]).then(([cats, sups]) => {
        if (catSelect) catSelect.innerHTML = '<option value="">-- اختر الفئة --</option>' + (cats || []).map(c => `<option value="${c.id}">${c.name}</option>`).join('');
        if (supSelect) supSelect.innerHTML = '<option value="">-- اختر المورد --</option>' + (sups.data || []).map(s => `<option value="${s.name}">${s.name}</option>`).join('');
    });
};
window.closeInlinePart = function () {
    document.getElementById('inlinePartModal')?.remove();
    openSaleModal();
};
window.saveInlinePart = async function () {
    const name = document.getElementById('inlinePartName')?.value.trim();
    if (!name) { showToast('الاسم مطلوب', 'error'); return; }
    const part_number = document.getElementById('inlinePartNumber')?.value.trim() || '';
    const category_id = document.getElementById('inlinePartCategory')?.value;
    const quantity = parseInt(document.getElementById('inlinePartQty')?.value) || 0;
    const alert_threshold = parseInt(document.getElementById('inlinePartAlert')?.value) || 5;
    const purchase_price = parseFloat(document.getElementById('inlinePartPurchasePrice')?.value) || 0;
    const sale_price = parseFloat(document.getElementById('inlinePartSalePrice')?.value) || 0;
    if (sale_price <= 0) { showToast('سعر البيع مطلوب', 'error'); return; }
    const supplier = document.getElementById('inlinePartSupplier')?.value || '';
    try {
        const res = await apiFetch('/parts', { method: 'POST', body: JSON.stringify({ name, part_number, category_id, quantity, alert_threshold, purchase_price, sale_price, supplier }) });
        showToast('تم إضافة القطعة ✅');
        allParts.push(res);
        document.getElementById('inlinePartModal')?.remove();
        openSaleModal();
    } catch (e) {
        showToast('حدث خطأ أثناء الحفظ', 'error');
        console.error(e);
    }
};

window.saveSale = async function () {
    const customer_id = document.getElementById('saleCustomer')?.value;
    const part_id = document.getElementById('salePart')?.value;
    const quantity = parseInt(document.getElementById('saleQty')?.value) || 0;
    const sale_date = document.getElementById('saleDate')?.value;
    const paid = parseFloat(document.getElementById('salePaid')?.value) || 0;

    if (!customer_id || !part_id || quantity < 1 || !sale_date) {
        showToast('جميع الحقول المطلوبة يجب ملؤها', 'error');
        return;
    }

    try {
        await apiFetch('/sales', {
            method: 'POST',
            body: JSON.stringify({ customer_id, part_id, quantity, sale_date, paid })
        });
        showToast('تم تسجيل البيع ✅');
        closeModal('saleModal');
        loadSales();
    } catch (e) {
        showToast('حدث خطأ أثناء الحفظ', 'error');
        console.error(e);
    }
};

window.deleteSale = async function (id) {
    if (!confirm('حذف هذه العملية؟')) return;
    try {
        await apiFetch('/sales/' + id, { method: 'DELETE' });
        showToast('تم الحذف 🗑️');
        loadSales();
    } catch (e) {
        showToast('حدث خطأ أثناء الحذف', 'error');
        console.error(e);
    }
};

loadSales();
