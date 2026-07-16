let allPurchases = [];
let allSuppliers = [];
let allParts = [];
let _purchaseModalOverrides = {};

async function loadPurchases() {
    window.loadPurchases = loadPurchases;
    try {
        const [purchasesData, suppliersData, partsData] = await Promise.all([
            apiFetch('/purchases'),
            apiFetch('/suppliers'),
            apiFetch('/parts')
        ]);
        allPurchases = purchasesData.data || [];
        allSuppliers = suppliersData.data || [];
        allParts = partsData.data || [];
        renderPurchases();
    } catch (e) {
        showToast('حدث خطأ أثناء تحميل البيانات', 'error');
        console.error(e);
    }
}

function renderPurchases() {
    const search = (document.getElementById('purchaseSearch')?.value || '').toLowerCase();
    let filtered = allPurchases.filter((p) => {
        const sname = (p.supplier?.name || '').toLowerCase();
        const pname = (p.part?.name || '').toLowerCase();
        return sname.includes(search) || pname.includes(search);
    });
    const tbody = document.getElementById('purchasesTableBody');
    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="11"><div class="empty-state">📥 لا توجد مشتريات</div></td></tr>';
        return;
    }
    tbody.innerHTML = filtered.map((p) => {
        return `<tr>
            <td>${p.id}</td>
            <td>${formatDate(p.purchase_date)}</td>
            <td>${formatTime(p.created_at)}</td>
            <td>${p.supplier?.name || '؟'}</td>
            <td>${p.part?.name || '؟'}</td>
            <td>${p.quantity}</td>
            <td>${formatCurrency(p.total)}</td>
            <td>${formatCurrency(p.paid)}</td>
            <td>${formatCurrency(p.remaining)}</td>
            <td>${renderBadge(p.status)}</td>
            <td>
                <button class="btn btn-danger btn-xs" onclick="deletePurchase(${p.id})">🗑️</button>
            </td>
        </tr>`;
    }).join('');
}

function cachePurchaseModalState() {
    _purchaseModalOverrides = {
        supplier: document.getElementById('purchaseSupplier')?.value || '',
        part: document.getElementById('purchasePart')?.value || '',
        qty: document.getElementById('purchaseQty')?.value || '',
        date: document.getElementById('purchaseDate')?.value || '',
        paid: document.getElementById('purchasePaid')?.value || '',
    };
}
function restorePurchaseModalState() {
    const ov = _purchaseModalOverrides;
    if (ov.supplier !== undefined && document.getElementById('purchaseSupplier')) document.getElementById('purchaseSupplier').value = ov.supplier;
    if (ov.part !== undefined && document.getElementById('purchasePart')) document.getElementById('purchasePart').value = ov.part;
    if (ov.qty !== undefined && document.getElementById('purchaseQty')) document.getElementById('purchaseQty').value = ov.qty;
    if (ov.date !== undefined && document.getElementById('purchaseDate')) document.getElementById('purchaseDate').value = ov.date;
    if (ov.paid !== undefined && document.getElementById('purchasePaid')) document.getElementById('purchasePaid').value = ov.paid;
    _purchaseModalOverrides = {};
}

window.openPurchaseModal = function () {
    const supplierOpts = allSuppliers.map((s) => `<option value="${s.id}">${s.name}</option>`).join('');
    const partOpts = allParts.map((p) => `<option value="${p.id}">${p.name} (${p.part_number || 'بدون رقم'})</option>`).join('');
    const today = new Date().toISOString().split('T')[0];

    const html = `
        <div class="modal-overlay" id="purchaseModal">
            <div class="modal">
                <div class="modal-header"><h3>🧾 شراء جديد</h3><button class="modal-close" onclick="closeModal('purchaseModal')">✕</button></div>
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group" style="display:flex;align-items:flex-end;gap:6px;">
                            <div style="flex:1;"><label>المورد *</label><select id="purchaseSupplier">${supplierOpts}</select></div>
                            <button class="btn btn-success btn-xs" style="margin-bottom:0;height:38px;" onclick="addSupplierInlinePurchase()" title="إضافة مورد">➕</button>
                        </div>
                        <div class="form-group" style="display:flex;align-items:flex-end;gap:6px;">
                            <div style="flex:1;"><label>القطعة *</label><select id="purchasePart">${partOpts}</select></div>
                            <button class="btn btn-success btn-xs" style="margin-bottom:0;height:38px;" onclick="addPartInlinePurchase()" title="إضافة قطعة">➕</button>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>الكمية *</label><input type="number" id="purchaseQty" value="1" min="1"></div>
                        <div class="form-group"><label>التاريخ *</label><input type="date" id="purchaseDate" value="${today}"></div>
                    </div>
                    <div class="form-group"><label>المبلغ المدفوع</label><input type="number" id="purchasePaid" value="0" step="0.01"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline" onclick="closeModal('purchaseModal')">إلغاء</button>
                    <button class="btn btn-primary" onclick="savePurchase()">💾 حفظ</button>
                </div>
            </div>
        </div>`;
    document.getElementById('modalContainer').innerHTML = html;
    restorePurchaseModalState();
};

window.addSupplierInlinePurchase = function () {
    cachePurchaseModalState();
    const html = `
        <div class="modal-overlay" id="inlineSupModal">
            <div class="modal">
                <div class="modal-header"><h3>➕ إضافة مورد</h3><button class="modal-close" onclick="closeInlineSupplierPurchase()">✕</button></div>
                <div class="modal-body">
                    <div class="form-group"><label>الاسم *</label><input id="inlineSupName"></div>
                    <div class="form-row"><div class="form-group"><label>الهاتف</label><input id="inlineSupPhone"></div>
                    <div class="form-group"><label>العنوان</label><input id="inlineSupAddress"></div></div>
                </div>
                <div class="modal-footer"><button class="btn btn-outline" onclick="closeInlineSupplierPurchase()">إلغاء</button>
                <button class="btn btn-primary" onclick="saveInlineSupplierPurchase()">💾 حفظ</button></div>
            </div>
        </div>`;
    document.getElementById('modalContainer').insertAdjacentHTML('beforeend', html);
};
window.closeInlineSupplierPurchase = function () {
    document.getElementById('inlineSupModal')?.remove();
    openPurchaseModal();
};
window.saveInlineSupplierPurchase = async function () {
    const name = document.getElementById('inlineSupName')?.value.trim();
    if (!name) { showToast('الاسم مطلوب', 'error'); return; }
    const phone = document.getElementById('inlineSupPhone')?.value.trim() || '';
    const address = document.getElementById('inlineSupAddress')?.value.trim() || '';
    try {
        const res = await apiFetch('/suppliers', { method: 'POST', body: JSON.stringify({ name, phone, address }) });
        showToast('تم إضافة المورد ✅');
        allSuppliers.push(res);
        document.getElementById('inlineSupModal')?.remove();
        openPurchaseModal();
    } catch (e) {
        showToast('حدث خطأ أثناء الحفظ', 'error');
        console.error(e);
    }
};

window.addPartInlinePurchase = function () {
    cachePurchaseModalState();
    const html = `
        <div class="modal-overlay" id="inlinePartModal">
            <div class="modal">
                <div class="modal-header"><h3>➕ إضافة قطعة</h3><button class="modal-close" onclick="closeInlinePartPurchase()">✕</button></div>
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
                <div class="modal-footer"><button class="btn btn-outline" onclick="closeInlinePartPurchase()">إلغاء</button>
                <button class="btn btn-primary" onclick="saveInlinePartPurchase()">💾 حفظ</button></div>
            </div>
        </div>`;
    document.getElementById('modalContainer').insertAdjacentHTML('beforeend', html);
    const catSelect = document.getElementById('inlinePartCategory');
    const supSelect = document.getElementById('inlinePartSupplier');
    Promise.all([apiFetch('/categories'), apiFetch('/suppliers')]).then(([cats, sups]) => {
        if (catSelect) catSelect.innerHTML = '<option value="">-- اختر الفئة --</option>' + (cats || []).map(c => `<option value="${c.id}">${c.name}</option>`).join('');
        if (supSelect) supSelect.innerHTML = '<option value="">-- اختر المورد --</option>' + (sups.data || []).map(s => `<option value="${s.name}">${s.name}</option>`).join('');
    });
};
window.closeInlinePartPurchase = function () {
    document.getElementById('inlinePartModal')?.remove();
    openPurchaseModal();
};
window.saveInlinePartPurchase = async function () {
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
        openPurchaseModal();
    } catch (e) {
        const msg = (e && e.message) ? e.message : (typeof e === 'string' ? e : 'حدث خطأ أثناء الحفظ');
        showToast(msg, 'error');
        console.error(e);
    }
};

window.savePurchase = async function () {
    const supplier_id = document.getElementById('purchaseSupplier')?.value;
    const part_id = document.getElementById('purchasePart')?.value;
    const quantity = parseInt(document.getElementById('purchaseQty')?.value) || 0;
    const purchase_date = document.getElementById('purchaseDate')?.value;
    const paid = parseFloat(document.getElementById('purchasePaid')?.value) || 0;

    if (!supplier_id || !part_id || quantity < 1 || !purchase_date) {
        showToast('جميع الحقول المطلوبة يجب ملؤها', 'error');
        return;
    }

    try {
        await apiFetch('/purchases', {
            method: 'POST',
            body: JSON.stringify({ supplier_id, part_id, quantity, purchase_date, paid })
        });
        showToast('تم تسجيل الشراء ✅');
        closeModal('purchaseModal');
        loadPurchases();
        if (typeof window.loadSuppliers === 'function') window.loadSuppliers();
        if (typeof window.loadParts === 'function') window.loadParts();
    } catch (e) {
        showToast('حدث خطأ أثناء الحفظ', 'error');
        console.error(e);
    }
};

window.deletePurchase = async function (id) {
    if (!confirm('حذف هذه العملية؟')) return;
    try {
        await apiFetch('/purchases/' + id, { method: 'DELETE' });
        showToast('تم الحذف 🗑️');
        loadPurchases();
        if (typeof window.loadSuppliers === 'function') window.loadSuppliers();
        if (typeof window.loadParts === 'function') window.loadParts();
    } catch (e) {
        showToast('حدث خطأ أثناء الحذف', 'error');
        console.error(e);
    }
};

loadPurchases();
