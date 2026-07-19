let allParts = [];
let allCategories = [];
let allSuppliers = [];
let _partModalItem = null;
let _partModalOverrides = {};

async function loadParts() {
    window.loadParts = loadParts;
    try {
        const [partsData, catsData, suppliersData] = await Promise.all([
            apiFetch('/parts'),
            apiFetch('/categories'),
            apiFetch('/suppliers')
        ]);
        allParts = partsData.data || [];
        allCategories = catsData || [];
        allSuppliers = suppliersData.data || [];

        const catSelect = document.getElementById('categoryFilter');
        if (catSelect) {
            const currentVal = catSelect.value;
            catSelect.innerHTML = '<option value="">جميع الفئات</option>' +
                allCategories.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
            catSelect.value = currentVal;
        }
        renderParts();

        // إشعارات المستودع: تنبيه للقطع التي وصلت للحد الأدنى
        const lowStockItems = allParts.filter(i => i.quantity <= (i.alert_threshold || 5));
        const badge = document.getElementById('inventoryBadge');
        if (badge) {
            badge.textContent = lowStockItems.length;
            badge.style.display = lowStockItems.length > 0 ? 'inline-block' : 'none';
        }
        updateNotifDropdown(lowStockItems);
        if (lowStockItems.length > 0) {
            lowStockItems.forEach(item => {
                showToast(`⚠️ المخزون منخفض: ${item.name} (الكمية: ${item.quantity})`, 'error');
            });
        }
    } catch (e) {
        showToast('حدث خطأ أثناء تحميل البيانات', 'error');
        console.error(e);
    }
}

function renderParts() {
    const search = (document.getElementById('partSearch')?.value || '').toLowerCase();
    const catId = document.getElementById('categoryFilter')?.value || '';
    let filtered = allParts.filter((i) => {
        const matchSearch = i.name.toLowerCase().includes(search) || (i.part_number || '').toLowerCase().includes(search);
        const matchCat = !catId || String(i.category_id) === String(catId);
        return matchSearch && matchCat;
    });
    const tbody = document.getElementById('partsTableBody');
    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="14"><div class="empty-state">📦 لا توجد قطع</div></td></tr>';
        return;
    }
    tbody.innerHTML = filtered.map((i) => {
        const low = i.quantity <= (i.alert_threshold || 5);
        return `<tr style="${low ? 'background:#ffcccc;color:#900;font-weight:bold;' : ''}">
            <td>${i.id}</td>
            <td><strong>${i.name}</strong></td>
            <td>${i.part_number || '-'}</td>
            <td><span class="badge badge-info">${i.category?.name || 'أخرى'}</span></td>
            <td>${i.quantity}</td>
            <td>${formatCurrency(i.purchase_price, 'ل.س')}</td>
            <td>${formatCurrency(i.sale_price, 'ل.س')}</td>
            <td>${i.purchase_price_usd ? formatCurrency(i.purchase_price_usd, '$') : '-'}</td>
            <td>${i.sale_price_usd ? formatCurrency(i.sale_price_usd, '$') : '-'}</td>
            <td>${i.supplier || '-'}</td>
            <td>${renderBadge(i.status)}</td>
            <td>${formatDate(i.created_at)}</td>
            <td>${formatTime(i.created_at)}</td>
            <td>
                <button class="btn btn-outline btn-xs" onclick="editPart(${i.id})">✏️</button>
                <button class="btn btn-danger btn-xs" onclick="deletePart(${i.id})">🗑️</button>
            </td>
        </tr>`;
    }).join('');
}

function cachePartModalState(item) {
    _partModalItem = item;
    _partModalOverrides = {
        name: document.getElementById('partName')?.value || '',
        partNumber: document.getElementById('partNumber')?.value || '',
        category: document.getElementById('partCategory')?.value || '',
        qty: document.getElementById('partQty')?.value || '',
        alert: document.getElementById('partAlert')?.value || '',
        purchasePrice: document.getElementById('partPurchasePrice')?.value || '',
        salePrice: document.getElementById('partSalePrice')?.value || '',
        purchasePriceUsd: document.getElementById('partPurchasePriceUsd')?.value || '',
        salePriceUsd: document.getElementById('partSalePriceUsd')?.value || '',
        supplier: document.getElementById('partSupplier')?.value || '',
    };
}

function restorePartModalState() {
    const ov = _partModalOverrides;
    if (ov.name !== undefined) document.getElementById('partName').value = ov.name;
    if (ov.partNumber !== undefined) document.getElementById('partNumber').value = ov.partNumber;
    if (ov.category !== undefined) document.getElementById('partCategory').value = ov.category;
    if (ov.qty !== undefined) document.getElementById('partQty').value = ov.qty;
    if (ov.alert !== undefined) document.getElementById('partAlert').value = ov.alert;
    if (ov.purchasePrice !== undefined) document.getElementById('partPurchasePrice').value = ov.purchasePrice;
    if (ov.salePrice !== undefined) document.getElementById('partSalePrice').value = ov.salePrice;
    if (ov.purchasePriceUsd !== undefined) document.getElementById('partPurchasePriceUsd').value = ov.purchasePriceUsd;
    if (ov.salePriceUsd !== undefined) document.getElementById('partSalePriceUsd').value = ov.salePriceUsd;
    if (ov.supplier !== undefined) document.getElementById('partSupplier').value = ov.supplier;
    _partModalOverrides = {};
}

window.openPartModal = function (item = null) {
    _partModalItem = item;
    const isEdit = item !== null;
    const catOpts = '<option value="">-- اختر الفئة --</option>' + allCategories.map((c) => `<option value="${c.id}" ${isEdit && item.category_id === c.id ? 'selected' : ''}>${c.name}</option>`).join('');
    const supOpts = '<option value="">-- اختر المورد --</option>' + allSuppliers.map((s) => `<option value="${s.name}" ${isEdit && item.supplier === s.name ? 'selected' : ''}>${s.name}</option>`).join('');
    const html = `
        <div class="modal-overlay" id="partModal">
            <div class="modal">
                <div class="modal-header"><h3>${isEdit ? '✏️ تعديل' : '➕ إضافة'} قطعة</h3><button class="modal-close" onclick="closeModal('partModal')">✕</button></div>
                <div class="modal-body">
                    <div class="form-group"><label>الاسم *</label><input id="partName" value="${isEdit ? item.name : ''}"></div>
                    <div class="form-row">
                        <div class="form-group"><label>رقم القطعة</label><input id="partNumber" value="${isEdit ? item.part_number || '' : ''}"></div>
                        <div class="form-group" style="display:flex;align-items:flex-end;gap:6px;">
                            <div style="flex:1;">
                                <label>الفئة</label>
                                <select id="partCategory">${catOpts}</select>
                            </div>
                            <button class="btn btn-success btn-xs" style="margin-bottom:0;height:38px;" onclick="addCategoryInline()" title="إضافة فئة">➕</button>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>الكمية *</label><input type="number" id="partQty" value="${isEdit ? item.quantity : 0}" min="0"></div>
                        <div class="form-group"><label>حد التنبيه</label><input type="number" id="partAlert" value="${isEdit ? item.alert_threshold || 5 : 5}" min="1"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>سعر الشراء (ل.س)</label><input type="number" id="partPurchasePrice" value="${isEdit ? item.purchase_price || 0 : 0}" step="0.01"></div>
                        <div class="form-group"><label>سعر البيع (ل.س) *</label><input type="number" id="partSalePrice" value="${isEdit ? item.sale_price || 0 : 0}" step="0.01"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>سعر الشراء ($)</label><input type="number" id="partPurchasePriceUsd" value="${isEdit ? item.purchase_price_usd || 0 : 0}" step="0.01"></div>
                        <div class="form-group"><label>سعر البيع ($)</label><input type="number" id="partSalePriceUsd" value="${isEdit ? item.sale_price_usd || 0 : 0}" step="0.01"></div>
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end;gap:6px;">
                        <div style="flex:1;">
                            <label>المورد</label>
                            <select id="partSupplier">${supOpts}</select>
                        </div>
                        <button class="btn btn-success btn-xs" style="margin-bottom:0;height:38px;" onclick="addSupplierInline()" title="إضافة مورد">➕</button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline" onclick="closeModal('partModal')">إلغاء</button>
                    <button class="btn btn-primary" onclick="savePart(${isEdit ? item.id : 'null'})">💾 حفظ</button>
                </div>
            </div>
        </div>`;
    document.getElementById('modalContainer').innerHTML = html;
    restorePartModalState();
};

window.editPart = function (id) {
    const i = allParts.find((x) => x.id === id);
    if (i) openPartModal(i);
};

window.savePart = async function (id) {
    const name = document.getElementById('partName')?.value.trim();
    if (!name) { showToast('الاسم مطلوب', 'error'); return; }
    const part_number = document.getElementById('partNumber')?.value.trim() || '';
    const category_id = document.getElementById('partCategory')?.value;
    const quantity = parseInt(document.getElementById('partQty')?.value) || 0;
    const alert_threshold = parseInt(document.getElementById('partAlert')?.value) || 5;
    const purchase_price = parseFloat(document.getElementById('partPurchasePrice')?.value) || 0;
    const sale_price = parseFloat(document.getElementById('partSalePrice')?.value) || 0;
    if (sale_price <= 0) { showToast('سعر البيع مطلوب', 'error'); return; }
    const purchase_price_usd = parseFloat(document.getElementById('partPurchasePriceUsd')?.value) || 0;
    const sale_price_usd = parseFloat(document.getElementById('partSalePriceUsd')?.value) || 0;
    const supplier = document.getElementById('partSupplier')?.value || '';

    const payload = { name, part_number, category_id, quantity, alert_threshold, purchase_price, sale_price, purchase_price_usd, sale_price_usd, supplier };
    try {
        if (id) {
            await apiFetch('/parts/' + id, { method: 'PUT', body: JSON.stringify(payload) });
            showToast('تم التحديث ✅');
        } else {
            await apiFetch('/parts', { method: 'POST', body: JSON.stringify(payload) });
            showToast('تم الإضافة ✅');
        }
        closeModal('partModal');
        loadParts();
        if (typeof window.loadSales === 'function') window.loadSales();
        if (typeof window.loadPurchases === 'function') window.loadPurchases();
    } catch (e) {
        showToast('حدث خطأ أثناء الحفظ', 'error');
        console.error(e);
    }
};

window.addSupplierInline = function () {
    cachePartModalState(_partModalItem);
    const html = `
        <div class="modal-overlay" id="inlineSupModal">
            <div class="modal">
                <div class="modal-header"><h3>➕ إضافة مورد</h3><button class="modal-close" onclick="closeInlineSupplier()">✕</button></div>
                <div class="modal-body">
                    <div class="form-group"><label>الاسم *</label><input id="inlineSupName"></div>
                    <div class="form-row">
                        <div class="form-group"><label>الهاتف</label><input id="inlineSupPhone"></div>
                        <div class="form-group"><label>العنوان</label><input id="inlineSupAddress"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline" onclick="closeInlineSupplier()">إلغاء</button>
                    <button class="btn btn-primary" onclick="saveInlineSupplier()">💾 حفظ</button>
                </div>
            </div>
        </div>`;
    document.getElementById('modalContainer').insertAdjacentHTML('beforeend', html);
};

window.closeInlineSupplier = function () {
    document.getElementById('inlineSupModal')?.remove();
    openPartModal(_partModalItem);
};

window.saveInlineSupplier = async function () {
    const name = document.getElementById('inlineSupName')?.value.trim();
    if (!name) { showToast('الاسم مطلوب', 'error'); return; }
    const phone = document.getElementById('inlineSupPhone')?.value.trim() || '';
    const address = document.getElementById('inlineSupAddress')?.value.trim() || '';
    try {
        const res = await apiFetch('/suppliers', { method: 'POST', body: JSON.stringify({ name, phone, address }) });
        showToast('تم إضافة المورد ✅');
        allSuppliers.push(res);
        document.getElementById('inlineSupModal')?.remove();
        openPartModal(_partModalItem);
    } catch (e) {
        showToast('حدث خطأ أثناء الحفظ', 'error');
        console.error(e);
    }
};

window.addCategoryInline = function () {
    cachePartModalState(_partModalItem);
    const html = `
        <div class="modal-overlay" id="inlineCatModal">
            <div class="modal">
                <div class="modal-header"><h3>➕ إضافة فئة</h3><button class="modal-close" onclick="closeInlineCategory()">✕</button></div>
                <div class="modal-body">
                    <div class="form-group"><label>اسم الفئة *</label><input id="inlineCatName"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline" onclick="closeInlineCategory()">إلغاء</button>
                    <button class="btn btn-primary" onclick="saveInlineCategory()">💾 حفظ</button>
                </div>
            </div>
        </div>`;
    document.getElementById('modalContainer').insertAdjacentHTML('beforeend', html);
};

window.closeInlineCategory = function () {
    document.getElementById('inlineCatModal')?.remove();
    openPartModal(_partModalItem);
};

window.saveInlineCategory = async function () {
    const name = document.getElementById('inlineCatName')?.value.trim();
    if (!name) { showToast('اسم الفئة مطلوب', 'error'); return; }
    try {
        const res = await apiFetch('/categories', { method: 'POST', body: JSON.stringify({ name }) });
        showToast('تم إضافة الفئة ✅');
        allCategories.push(res);
        document.getElementById('inlineCatModal')?.remove();
        openPartModal(_partModalItem);
    } catch (e) {
        showToast('حدث خطأ أثناء الحفظ', 'error');
        console.error(e);
    }
};

window.deletePart = async function (id) {
    const i = allParts.find((x) => x.id === id);
    if (!i) return;
    if (!confirm('حذف ' + i.name + '؟')) return;
    try {
        await apiFetch('/parts/' + id, { method: 'DELETE' });
        showToast('تم الحذف 🗑️');
        loadParts();
        if (typeof window.loadSales === 'function') window.loadSales();
        if (typeof window.loadPurchases === 'function') window.loadPurchases();
    } catch (e) {
        showToast('حدث خطأ أثناء الحذف', 'error');
        console.error(e);
    }
};

loadParts();
