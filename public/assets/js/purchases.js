let allPurchases = [];
let allSuppliers = [];
let allParts = [];
let _purchaseModalOverrides = {};

function formatNumber(n) {
    return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

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
        tbody.innerHTML = '<tr><td colspan="12"><div class="empty-state">📥 لا توجد مشتريات</div></td></tr>';
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
            <td>${formatCurrency(p.unit_price, p.currency === 'USD' ? '$' : 'ل.س')}</td>
            <td>${formatCurrency(p.total, p.currency === 'USD' ? '$' : 'ل.س')}</td>
            <td>${formatCurrency(p.paid, p.currency === 'USD' ? '$' : 'ل.س')}</td>
            <td>${formatCurrency(p.remaining, p.currency === 'USD' ? '$' : 'ل.س')}</td>
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
        supplierInput: document.getElementById('purchaseSupplierInput')?.value || '',
        partSearch: document.getElementById('purchasePartSearch')?.value || '',
        partId: document.getElementById('purchasePartId')?.value || '',
        qty: document.getElementById('purchaseQty')?.value || '',
        date: document.getElementById('purchaseDate')?.value || '',
        purchasePrice: document.getElementById('purchasePrice')?.value || '',
        paid: document.getElementById('purchasePaid')?.value || '',
        currency: document.getElementById('purchaseCurrency')?.value || '',
    };
}
function restorePurchaseModalState() {
    const ov = _purchaseModalOverrides;
    if (ov.supplier !== undefined && window._purchaseSupplierSelect) window._purchaseSupplierSelect.setValue(ov.supplier, ov.supplierInput || '');
    if (ov.partSearch !== undefined && document.getElementById('purchasePartSearch')) document.getElementById('purchasePartSearch').value = ov.partSearch;
    if (ov.partId !== undefined && document.getElementById('purchasePartId')) document.getElementById('purchasePartId').value = ov.partId;
    if (ov.qty !== undefined && document.getElementById('purchaseQty')) document.getElementById('purchaseQty').value = ov.qty;
    if (ov.date !== undefined && document.getElementById('purchaseDate')) document.getElementById('purchaseDate').value = ov.date;
    if (ov.purchasePrice !== undefined && document.getElementById('purchasePrice')) document.getElementById('purchasePrice').value = ov.purchasePrice;
    if (ov.paid !== undefined && document.getElementById('purchasePaid')) document.getElementById('purchasePaid').value = ov.paid;
    if (ov.currency !== undefined && document.getElementById('purchaseCurrency')) document.getElementById('purchaseCurrency').value = ov.currency;
    _purchaseModalOverrides = {};
}

window.openPurchaseModal = function () {
    const supplierOpts = allSuppliers.map((s) => ({ value: String(s.id), text: `${s.name} - ${s.phone || ''}` }));
    const partOpts = allParts.map((p) => ({ value: String(p.id), text: `${p.name}${p.part_number ? ' - ' + p.part_number : ''}` }));
    const today = new Date().toISOString().split('T')[0];

    const html = `
        <div class="modal-overlay" id="purchaseModal">
            <div class="modal">
                <div class="modal-header"><h3>🧾 شراء جديد</h3><button class="modal-close" onclick="closeModal('purchaseModal')">✕</button></div>
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group" style="display:flex;align-items:flex-end;gap:6px;">
                            <div style="flex:1;"><label>المورد *</label><input id="purchaseSupplierInput" placeholder="اكتب اسم المورد..."><input type="hidden" id="purchaseSupplier"></div>
                            <button class="btn btn-success btn-xs" style="margin-bottom:0;height:38px;" onclick="addSupplierInlinePurchase()" title="إضافة مورد">➕</button>
                        </div>
                    </div>
                    <div class="form-group" style="position:relative;">
                        <label>القطعة *</label>
                        <input type="text" id="purchasePartSearch" placeholder="اكتب اسم أو رقم القطعة...">
                        <input type="hidden" id="purchasePartId">
                        <button class="btn btn-success btn-xs" style="margin-top:6px;" onclick="addPartInlinePurchase()" title="إضافة قطعة جديدة">➕ قطعة جديدة</button>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>الكمية *</label><input type="number" id="purchaseQty" value="1" min="1" oninput="updatePurchaseTotal()"></div>
                        <div class="form-group"><label>سعر الشراء للوحدة *</label><input type="number" id="purchasePrice" value="0" step="0.01" oninput="updatePurchaseTotal()"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>العملة</label>
                            <select id="purchaseCurrency">
                                <option value="SYP">ليرة سورية (SYP)</option>
                                <option value="USD">دولار (USD)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>التاريخ *</label><input type="date" id="purchaseDate" value="${today}"></div>
                        <div class="form-group"><label>الإجمالي</label><input id="purchaseTotal" readonly style="background:#f1f5f9;font-weight:700;"></div>
                    </div>
                    <div class="form-group"><label>المبلغ المدفوع</label><input type="number" id="purchasePaid" value="0" step="0.01" oninput="updatePurchaseRemaining()"></div>
                    <div class="form-group"><label>المتبقي</label><input id="purchaseRemaining" readonly style="background:#fef3c7;font-weight:700;color:var(--danger);"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline" onclick="closeModal('purchaseModal')">إلغاء</button>
                    <button class="btn btn-primary" onclick="savePurchase()">💾 حفظ</button>
                </div>
            </div>
        </div>`;
    document.getElementById('modalContainer').innerHTML = html;
    window._purchaseSupplierSelect = initSearchableSelect(
        'purchaseSupplierInput',
        supplierOpts,
        (val, text) => { document.getElementById('purchaseSupplier').value = val; }
    );
    window._purchasePartSelect = initSearchableSelect(
        'purchasePartSearch',
        partOpts,
        (val, text) => {
            document.getElementById('purchasePartId').value = val;
            const part = allParts.find(p => String(p.id) === val);
            if (part) {
                document.getElementById('purchasePrice').value = part.purchase_price || '';
                updatePurchaseTotal();
            }
        }
    );
    restorePurchaseModalState();
    updatePurchaseTotal();
};

function searchPurchaseParts() {
    const q = document.getElementById('purchasePartSearch')?.value.toLowerCase() || '';
    const results = document.getElementById('purchasePartResults');
    if (!q || q.length < 1) {
        results.style.display = 'none';
        return;
    }
    const matches = allParts.filter(p =>
        p.name.toLowerCase().includes(q) ||
        (p.part_number || '').toLowerCase().includes(q)
    ).slice(0, 8);

    if (matches.length === 0) {
        results.innerHTML = '<div style="padding:10px;color:var(--text-light);">لا توجد نتائج</div>';
    } else {
        results.innerHTML = matches.map(p => {
            return `<div style="padding:10px;cursor:pointer;border-bottom:1px solid var(--border);" onclick="selectPurchasePart(${p.id}, '${p.name.replace(/'/g, "\\'")}', ${p.purchase_price || 0})">
                <strong>${p.name}</strong> <small style="color:var(--text-light);">(${p.part_number || 'بدون رقم'})</small>
                <div style="font-size:12px;color:var(--text-light);">متاح: ${p.quantity} — سعر الشراء: ${formatNumber(p.purchase_price || 0)}</div>
            </div>`;
        }).join('');
    }
    results.style.display = 'block';
}

function selectPurchasePart(id, name, price) {
    document.getElementById('purchasePartSearch').value = name;
    document.getElementById('purchasePartId').value = id;
    document.getElementById('purchasePrice').value = price || '';
    updatePurchaseTotal();
    document.getElementById('purchasePartResults').style.display = 'none';
}

window.clickPurchasePart = selectPurchasePart;

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
                    <div class="form-group"><label>الفئة</label><input id="inlinePartCategoryInput" placeholder="ابحث عن الفئة..."><input type="hidden" id="inlinePartCategory"></div></div>
                    <div class="form-row"><div class="form-group"><label>الكمية *</label><input type="number" id="inlinePartQty" value="0" min="0"></div>
                    <div class="form-group"><label>حد التنبيه</label><input type="number" id="inlinePartAlert" value="5" min="1"></div></div>
                    <div class="form-row"><div class="form-group"><label>سعر الشراء</label><input type="number" id="inlinePartPurchasePrice" value="0" step="0.01"></div>
                    <div class="form-group"><label>سعر البيع *</label><input type="number" id="inlinePartSalePrice" value="0" step="0.01"></div></div>
                    <div class="form-group"><label>المورد</label><input id="inlinePartSupplierInput" placeholder="ابحث عن المورد..."><input type="hidden" id="inlinePartSupplier"></div>
                </div>
                <div class="modal-footer"><button class="btn btn-outline" onclick="closeInlinePartPurchase()">إلغاء</button>
                <button class="btn btn-primary" onclick="saveInlinePartPurchase()">💾 حفظ</button></div>
            </div>
        </div>`;
    document.getElementById('modalContainer').insertAdjacentHTML('beforeend', html);
    Promise.all([apiFetch('/categories'), apiFetch('/suppliers')]).then(([cats, sups]) => {
        const catOpts = (cats || []).map(c => ({ value: String(c.id), text: c.name }));
        const supOpts = (sups.data || []).map(s => ({ value: s.name, text: s.name }));
        window._inlinePartCategorySelect = initSearchableSelect('inlinePartCategoryInput', catOpts, (val, text) => { document.getElementById('inlinePartCategory').value = val; });
        window._inlinePartSupplierSelect = initSearchableSelect('inlinePartSupplierInput', supOpts, (val, text) => { document.getElementById('inlinePartSupplier').value = val; });
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

window.updatePurchaseTotal = function () {
    const qty = parseInt(document.getElementById('purchaseQty')?.value) || 0;
    const price = parseFloat(document.getElementById('purchasePrice')?.value) || 0;
    const total = document.getElementById('purchaseTotal');
    if (total) total.value = formatNumber(qty * price);
    updatePurchaseRemaining();
};

window.updatePurchaseRemaining = function () {
    const total = parseFloat(document.getElementById('purchaseTotal')?.value.replace(/,/g, '')) || 0;
    const paid = parseFloat(document.getElementById('purchasePaid')?.value) || 0;
    const rem = document.getElementById('purchaseRemaining');
    if (rem) rem.value = formatNumber(Math.max(0, total - paid));
};

window.savePurchase = async function () {
    const supplier_id = document.getElementById('purchaseSupplier')?.value;
    const part_id = document.getElementById('purchasePartId')?.value;
    const quantity = parseInt(document.getElementById('purchaseQty')?.value) || 0;
    const unit_price = parseFloat(document.getElementById('purchasePrice')?.value) || 0;
    const purchase_date = document.getElementById('purchaseDate')?.value;
    const paid = parseFloat(document.getElementById('purchasePaid')?.value) || 0;
    const currency = document.getElementById('purchaseCurrency')?.value || 'SYP';

    if (!supplier_id || !part_id || quantity < 1 || unit_price <= 0 || !purchase_date) {
        showToast('المورد، القطعة، الكمية، سعر الشراء، والتاريخ مطلوبة', 'error');
        return;
    }

    try {
        await apiFetch('/purchases', {
            method: 'POST',
            body: JSON.stringify({ supplier_id, part_id, quantity, unit_price, purchase_date, paid, currency })
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
