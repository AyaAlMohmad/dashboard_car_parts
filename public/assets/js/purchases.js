let allPurchases = [];
let allSuppliers = [];
let allParts = [];
let _purchaseModalOverrides = {};
let purchaseItems = [];

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

function groupKey(p) {
    const d = new Date(p.created_at);
    const sec = d.toISOString().slice(0, 19);
    return `${p.supplier_id || p.supplier?.id || 0}_${p.purchase_date}_${sec}`;
}

function groupPurchases() {
    const map = {};
    allPurchases.forEach((p) => {
        const key = groupKey(p);
        if (!map[key]) {
            map[key] = { items: [], total: 0, paid: 0, remaining: 0, over: 0, debt: 0, first: p, status: 'مسدد' };
        }
        const isOldDebt = p.part?.part_number === 'OLD_DEBT' || p.notes === 'دين قديم';
        map[key].items.push(p);
        if (!isOldDebt) {
            map[key].total += parseFloat(p.total);
            map[key].paid += parseFloat(p.paid);
            map[key].remaining += parseFloat(p.remaining);
        } else {
            map[key].debt += parseFloat(p.paid);
        }
        if (parseFloat(p.remaining) > 0) map[key].status = 'علينا دين';
    });
    Object.values(map).forEach((g) => {
        const payments = g.first.supplier?.supplier_payments || g.first.supplier?.supplierPayments || [];
        const purchaseIds = new Set(g.items.map((i) => String(i.id)));
        g.over = payments
            .filter((pay) => pay.notes === 'دفعة زائدة من فاتورة شراء' && pay.purchase_id && purchaseIds.has(String(pay.purchase_id)))
            .reduce((sum, pay) => sum + parseFloat(pay.amount), 0);

        g.debt = g.items
            .filter((i) => i.part?.part_number === 'OLD_DEBT' || i.notes === 'دين قديم')
            .reduce((sum, i) => sum + parseFloat(i.paid), 0);
    });
    return Object.values(map);
}

function renderPurchases() {
    const search = (document.getElementById('purchaseSearch')?.value || '').toLowerCase();
    const groups = groupPurchases();
    let filtered = groups.filter((g) => {
        const sname = (g.first.supplier?.name || '').toLowerCase();
        const itemNames = g.items.map(i => (i.part?.name || '').toLowerCase()).join(' ');
        return sname.includes(search) || itemNames.includes(search);
    });
    const tbody = document.getElementById('purchasesTableBody');
    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="12"><div class="empty-state">📥 لا توجد مشتريات</div></td></tr>';
        return;
    }
    tbody.innerHTML = filtered.map((g) => {
        const curr = g.first.currency === 'USD' ? '$' : 'ل.س';
        const countText = g.items.length > 1 ? `${g.items.length} قطع` : `${g.items.length} قطعة`;
        return `<tr>
            <td>${g.first.id}</td>
            <td>${formatDate(g.first.purchase_date)}</td>
            <td>${formatTime(g.first.created_at)}</td>
            <td>${g.first.supplier?.name || '؟'}</td>
            <td>${countText}</td>
            <td>${formatCurrency(g.total, curr)}</td>
            <td>${formatCurrency(g.paid + g.debt + g.over, curr)}</td>
            <td>${formatCurrency(g.remaining, curr)}</td>
            <td>${formatCurrency(g.debt, curr)}</td>
            <td>${formatCurrency(g.over, curr)}</td>
            <td>${renderBadge(g.status)}</td>
                        <td>
                <button class="btn btn-outline btn-xs" onclick="viewPurchase(${g.first.id})">عرض</button>
                <button class="btn btn-danger btn-xs" onclick="deletePurchase(${g.first.id})">حذف</button>
            </td>
        </tr>`;
    }).join('');
}

function cachePurchaseModalState() {
    _purchaseModalOverrides = {
        supplier: document.getElementById('purchaseSupplier')?.value || '',
        supplierInput: document.getElementById('purchaseSupplierInput')?.value || '',
        date: document.getElementById('purchaseDate')?.value || '',
        currency: document.getElementById('purchaseCurrency')?.value || '',
        notes: document.getElementById('purchaseNotes')?.value || '',
        paid: document.getElementById('purchasePaid')?.value || '',
        items: purchaseItems,
    };
}

function restorePurchaseModalState() {
    const ov = _purchaseModalOverrides;
    if (ov.supplier !== undefined && window._purchaseSupplierSelect) window._purchaseSupplierSelect.setValue(ov.supplier, ov.supplierInput || '');
    if (ov.date !== undefined && document.getElementById('purchaseDate')) document.getElementById('purchaseDate').value = ov.date;
    if (ov.currency !== undefined && document.getElementById('purchaseCurrency')) document.getElementById('purchaseCurrency').value = ov.currency;
    if (ov.notes !== undefined && document.getElementById('purchaseNotes')) document.getElementById('purchaseNotes').value = ov.notes;
    if (ov.paid !== undefined && document.getElementById('purchasePaid')) document.getElementById('purchasePaid').value = ov.paid;
    if (ov.items && ov.items.length > 0) {
        purchaseItems = ov.items;
        renderPurchaseItems();
    }
    _purchaseModalOverrides = {};
    updatePurchaseTotal();
}

window.viewPurchase = function (id) {
    const target = allPurchases.find(p => p.id == id);
    if (!target) return;
    const key = groupKey(target);
    const items = allPurchases.filter(p => groupKey(p) === key);
    const currSymbol = target.currency === 'USD' ? '$' : 'ل.س';
    const total = items.reduce((s, i) => s + parseFloat(i.total), 0);
    const paid = items.reduce((s, i) => s + parseFloat(i.paid), 0);
    const remaining = items.reduce((s, i) => s + parseFloat(i.remaining), 0);
    const itemsHtml = items.map(it => `<tr><td>${it.part?.name || '؟'}</td><td>${it.quantity}</td><td>${formatNumber(it.unit_price)}</td><td>${formatNumber(it.total)}</td></tr>`).join('');
    showModal('🧾 تفاصيل الشراء #' + id, `
        <div style="margin-bottom:12px;">
            <div><strong>المورد:</strong> ${target.supplier?.name || '؟'}</div>
            <div><strong>التاريخ:</strong> ${formatDate(target.purchase_date)}</div>
            <div><strong>ملاحظات:</strong> ${target.notes || 'لا يوجد'}</div>
        </div>
        <table style="width:100%;font-size:13px;">
            <thead><tr><th>القطعة</th><th>الكمية</th><th>سعر الوحدة</th><th>الإجمالي</th></tr></thead>
            <tbody>${itemsHtml || '<tr><td colspan="4" style="text-align:center;">لا توجد قطع</td></tr>'}</tbody>
        </table>
        <div style="margin-top:12px;text-align:left;font-weight:bold;">
            <div>الإجمالي: ${currSymbol} ${formatNumber(total)}</div>
            <div>المدفوع: ${currSymbol} ${formatNumber(paid)}</div>
            <div>المتبقي: ${currSymbol} ${formatNumber(remaining)}</div>
        </div>
    `);
};

window.openPurchaseModal = function (restoreState = false) {
    purchaseItems = [];
    if (!restoreState) _purchaseModalOverrides = {};
    const supplierOpts = allSuppliers.map((s) => ({ value: String(s.id), text: `${s.name} - ${s.phone || ''}` }));
    const today = new Date().toISOString().split('T')[0];

    const html = `
        <div class="modal-overlay" id="purchaseModal">
            <div class="modal" style="max-width: 720px;">
                <div class="modal-header"><h3>🧾 شراء جديد</h3><button class="modal-close" onclick="closeModal('purchaseModal')">✕</button></div>
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group" style="display:flex;align-items:flex-end;gap:6px;flex:2;">
                            <div style="flex:1;"><label>المورد *</label><input id="purchaseSupplierInput" placeholder="اكتب اسم المورد..."><input type="hidden" id="purchaseSupplier"></div>
                            <button class="btn btn-success btn-xs" style="margin-bottom:0;height:38px;" onclick="addSupplierInlinePurchase()" title="إضافة مورد">➕</button>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>العملة</label>
                            <select id="purchaseCurrency" onchange="refreshSelectedPartPrice()">
                                <option value="SYP">ليرة سورية (SYP)</option>
                                <option value="USD">دولار (USD)</option>
                            </select>
                        </div>
                        <div class="form-group"><label>التاريخ *</label><input type="date" id="purchaseDate" value="${today}"></div>
                    </div>
                    <div class="form-group"><label>ملاحظات</label><textarea id="purchaseNotes" rows="2" placeholder="ملاحظات..."></textarea></div>

                    <div style="border:1px solid var(--border);border-radius:8px;padding:12px;margin-top:12px;">
                        <div style="font-weight:bold;margin-bottom:8px;">📦 القطع</div>
                        <div class="form-row">
                            <div class="form-group" style="position:relative;flex:2;">
                                <label>بحث عن قطعة</label>
                                <input type="text" id="partSearchInput" placeholder="اكتب اسم أو رقم القطعة..." oninput="searchPurchaseParts()" autocomplete="off">
                                <input type="hidden" id="partSearchId">
                                <div id="partSearchResults" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid var(--border);border-radius:8px;box-shadow:var(--shadow-lg);z-index:100;max-height:200px;overflow-y:auto;"></div>
                            </div>
                            <div class="form-group" style="flex:1;"><label>الكمية</label><input type="number" id="addQty" value="1" min="1"></div>
                            <div class="form-group" style="flex:1;"><label>السعر</label><input type="number" id="addPrice" value="0" step="0.01"></div>
                            <div class="form-group" style="display:flex;align-items:flex-end;">
                                <button class="btn btn-success btn-xs" onclick="addItemToPurchase()" style="height:38px;">➕ إضافة</button>
                            </div>
                        </div>
                        <div id="purchaseItemsTable" style="margin-top:10px;">
                            <table style="width:100%;font-size:13px;">
                                <thead><tr><th>القطعة</th><th>الكمية</th><th>سعر الوحدة</th><th>الإجمالي</th><th></th></tr></thead>
                                <tbody id="purchaseItemsBody"><tr><td colspan="5" style="text-align:center;color:var(--text-light);">لا توجد قطع</td></tr></tbody>
                            </table>
                        </div>
                        <div style="text-align:left;margin-top:8px;font-weight:bold;font-size:16px;">
                            الإجمالي: <span id="purchaseTotal" style="color:var(--primary);">0</span>
                        </div>
                    </div>

                    <div class="form-row" style="margin-top:12px;">
                        <div class="form-group"><label>المبلغ المدفوع</label><input type="number" id="purchasePaid" value="0" step="0.01" oninput="updatePurchaseRemaining()"></div>
                        <div class="form-group"><label>المتبقي</label><input id="purchaseRemaining" readonly style="background:#fef3c7;font-weight:700;color:var(--danger);"></div>
                        <div class="form-group"><label>مبلغ الزيادة</label><input id="purchaseOver" readonly style="background:#d1fae5;font-weight:700;color:var(--success);"></div>
                    </div>
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
    restorePurchaseModalState();
};

function searchPurchaseParts() {
    const q = document.getElementById('partSearchInput')?.value.toLowerCase() || '';
    const results = document.getElementById('partSearchResults');
    const partId = document.getElementById('partSearchId');
    partId.value = '';
    if (!q || q.length < 1) {
        results.style.display = 'none';
        return;
    }
    const matches = allParts.filter(p =>
        p.name.toLowerCase().includes(q) ||
        (p.part_number || '').toLowerCase().includes(q)
    ).slice(0, 8);

    if (matches.length === 0) {
        results.innerHTML = '<div style="padding:10px;color:var(--text-light);">اضغط إضافة لإضافتها كقطعة جديدة</div>';
    } else {
        results.innerHTML = matches.map(p => {
            return `<div style="padding:10px;cursor:pointer;border-bottom:1px solid var(--border);" onclick="selectPurchasePart(${p.id}, '${p.name.replace(/'/g, "\\'")}')">
                <strong>${p.name}</strong> <small style="color:var(--text-light);">(${p.part_number || 'بدون رقم'})</small>
                <div style="font-size:12px;color:var(--text-light);">متاح: ${p.quantity} — سعر الشراء: ${formatNumber(p.purchase_price || 0)} ل.س / ${formatNumber(p.purchase_price_usd || 0)} $</div>
            </div>`;
        }).join('');
    }
    results.style.display = 'block';
}

function selectPurchasePart(id, name) {
    document.getElementById('partSearchInput').value = name;
    document.getElementById('partSearchId').value = id;
    document.getElementById('partSearchResults').style.display = 'none';

    const part = allParts.find(p => p.id == id);
    const currency = document.getElementById('purchaseCurrency')?.value || 'SYP';
    const price = currency === 'USD' ? (part?.purchase_price_usd || 0) : (part?.purchase_price || 0);
    document.getElementById('addPrice').value = price || '';
}

function refreshSelectedPartPrice() {
    const id = document.getElementById('partSearchId')?.value;
    const name = document.getElementById('partSearchInput')?.value;
    if (id && name) {
        selectPurchasePart(id, name);
    }
}

function addItemToPurchase() {
    const partId = document.getElementById('partSearchId')?.value;
    const partName = document.getElementById('partSearchInput')?.value.trim();
    const qty = parseInt(document.getElementById('addQty')?.value) || 0;
    const price = parseFloat(document.getElementById('addPrice')?.value) || 0;

    if (!partName || qty < 1 || price <= 0) {
        showToast('اكتب اسم القطعة وحدد كمية وسعر صحيح', 'error');
        return;
    }

    purchaseItems.push({
        part_id: partId ? parseInt(partId) : null,
        part_name: partName,
        quantity: qty,
        unit_price: price,
        total: qty * price,
    });
    renderPurchaseItems();
    document.getElementById('partSearchInput').value = '';
    document.getElementById('partSearchId').value = '';
    document.getElementById('addQty').value = 1;
    document.getElementById('addPrice').value = 0;
}

function renderPurchaseItems() {
    const tbody = document.getElementById('purchaseItemsBody');
    if (purchaseItems.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text-light);">لا توجد قطع</td></tr>';
    } else {
        tbody.innerHTML = purchaseItems.map((it, idx) => `
            <tr>
                <td>${it.part_name}</td>
                <td>${it.quantity}</td>
                <td>${formatNumber(it.unit_price)}</td>
                <td>${formatNumber(it.total)}</td>
                <td><button class="btn btn-danger btn-xs" onclick="removePurchaseItem(${idx})">🗑️</button></td>
            </tr>
        `).join('');
    }
    updatePurchaseTotal();
}

function updatePurchaseTotal() {
    const total = purchaseItems.reduce((sum, it) => sum + it.total, 0);
    const totalEl = document.getElementById('purchaseTotal');
    if (totalEl) totalEl.textContent = formatNumber(total);
    updatePurchaseRemaining();
}

function updatePurchaseRemaining() {
    const total = purchaseItems.reduce((sum, it) => sum + it.total, 0);
    const paid = parseFloat(document.getElementById('purchasePaid')?.value) || 0;
    const rem = document.getElementById('purchaseRemaining');
    const over = document.getElementById('purchaseOver');
    if (rem) rem.value = formatNumber(Math.max(0, total - paid));
    if (over) over.value = formatNumber(Math.max(0, paid - total));
}

function removePurchaseItem(idx) {
    purchaseItems.splice(idx, 1);
    renderPurchaseItems();
}

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
    openPurchaseModal(true);
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
        openPurchaseModal(true);
    } catch (e) {
        showToast('حدث خطأ أثناء الحفظ', 'error');
        console.error(e);
    }
};

window.savePurchase = async function () {
    const supplier_id = document.getElementById('purchaseSupplier')?.value;
    const purchase_date = document.getElementById('purchaseDate')?.value;
    const currency = document.getElementById('purchaseCurrency')?.value || 'SYP';
    const notes = document.getElementById('purchaseNotes')?.value || '';
    const paid = parseFloat(document.getElementById('purchasePaid')?.value) || 0;

    if (!supplier_id || !purchase_date || purchaseItems.length === 0) {
        showToast('المورد، التاريخ، وقطعة واحدة على الأقل مطلوبة', 'error');
        return;
    }

    try {
        await apiFetch('/purchases', {
            method: 'POST',
            body: JSON.stringify({
                supplier_id,
                purchase_date,
                currency,
                notes,
                paid,
                items: purchaseItems.map(it => ({ part_id: it.part_id, part_name: it.part_name, quantity: it.quantity, unit_price: it.unit_price }))
            })
        });
        showToast('تم تسجيل المشتريات ✅');
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
