let allInvoices = [];
let allCustomers = [];
let allParts = [];
let exchangeRate = 1;
let invoiceItems = [];
let selectedCurrency = 'SYP';

async function loadInvoices() {
    try {
        const [invData, custData, partsData] = await Promise.all([
            apiFetch('/invoices'),
            apiFetch('/customers'),
            apiFetch('/parts'),
        ]);
        allInvoices = invData.data || [];
        allCustomers = custData.data || [];
        allParts = partsData.data || [];
        renderInvoices();
    } catch (e) {
        showToast('حدث خطأ أثناء تحميل البيانات', 'error');
        console.error(e);
    }
}

function groupKey(inv) {
    return `${inv.sale_date}_${inv.id}`;
}

function groupInvoices() {
    const map = {};
    allInvoices.forEach((inv) => {
        const key = groupKey(inv);
        if (!map[key]) {
            map[key] = { items: [], total: 0, paid: 0, remaining: 0, over: 0, debt: 0, first: inv, status: 'مسدد' };
        }
        const total = parseFloat(inv.total || 0);
        const remaining = parseFloat(inv.remaining || 0);
        const cashApplied = parseFloat((total - remaining).toFixed(2));
        const over = parseFloat((Math.max(0, (inv.paid || 0) - total)).toFixed(2));
        map[key].items.push(inv);
        map[key].total += total;
        map[key].paid += cashApplied;
        map[key].remaining += remaining;
        map[key].over += over;
        if (remaining > 0) map[key].status = 'عليه دين';
    });
    return Object.values(map);
}

function renderInvoices() {
    const search = (document.getElementById('invoiceSearch')?.value || '').toLowerCase();
    const groups = groupInvoices();
    let filtered = groups.filter((g) => {
        const cname = (g.first.customer?.name || '').toLowerCase();
        const itemNames = g.items.flatMap(inv => (inv.items || []).map(i => (i.part?.name || '').toLowerCase())).join(' ');
        return cname.includes(search) || itemNames.includes(search) || String(g.first.id).includes(search);
    });
    const tbody = document.getElementById('invoicesTableBody');
    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="12"><div class="empty-state">🧾 لا توجد فواتير</div></td></tr>';
        return;
    }
    tbody.innerHTML = filtered.map((g) => {
        const curr = g.first.currency === 'USD' ? '$' : 'ل.س';
        const totalParts = g.items.reduce((sum, inv) => sum + (inv.items || []).length, 0);
        const countText = totalParts > 1 ? `${totalParts} قطع` : 'قطعة';
        return `<tr>
            <td>${g.first.id}</td>
            <td>${formatDate(g.first.sale_date)}</td>
            <td>${formatTime(g.first.created_at)}</td>
            <td>${g.first.customer?.name || '؟'}</td>
            <td>${countText}</td>
            <td>${formatCurrency(g.total, curr)}</td>
            <td>${formatCurrency(g.paid + g.debt + g.over, curr)}</td>
            <td>${formatCurrency(g.remaining, curr)}</td>
            <td>${formatCurrency(g.debt, curr)}</td>
            <td>${formatCurrency(g.over, curr)}</td>
            <td>${renderBadge(g.status)}</td>
            <td>
                <button class="btn btn-outline btn-xs" onclick="viewInvoice(${g.first.id})">عرض</button>
                <button class="btn btn-warning btn-xs" onclick="openReturnInvoiceModal(${g.first.id})">مرتجع ↩️</button>
                <button class="btn btn-danger btn-xs" onclick="deleteInvoice(${g.first.id})">حذف</button>
            </td>
        </tr>`;
    }).join('');
}

function formatNumber(n) {
    return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

window.openInvoiceModal = function () {
    invoiceItems = [];
    selectedCurrency = 'SYP';
    const customerOpts = allCustomers.map((c) => ({ value: String(c.id), text: `${c.name} - ${c.phone || ''}` }));
    const today = new Date().toISOString().split('T')[0];

    const html = `
        <div class="modal-overlay" id="invoiceModal">
            <div class="modal" style="max-width: 720px;">
                <div class="modal-header"><h3>🧾 فاتورة جديدة</h3><button class="modal-close" onclick="closeModal('invoiceModal')">✕</button></div>
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group"><label>العميل *</label><input id="invCustomerInput" placeholder="اكتب اسم العميل..."><input type="hidden" id="invCustomer"></div>
                        <div class="form-group"><label>التاريخ *</label><input type="date" id="invDate" value="${today}"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>العملة</label>
                            <select id="invCurrency" onchange="selectedCurrency = this.value; refreshSelectedInvoicePartPrice();">
                                <option value="SYP">ليرة سورية (SYP)</option>
                                <option value="USD">دولار (USD)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group"><label>ملاحظات</label><textarea id="invNotes" rows="2"></textarea></div>

                    <div style="border:1px solid var(--border);border-radius:8px;padding:12px;margin-top:12px;">
                        <div style="font-weight:bold;margin-bottom:8px;">📦 القطع</div>
                        <div class="form-row">
                            <div class="form-group" style="position:relative;flex:2;">
                                <label>بحث عن قطعة</label>
                                <input type="text" id="partSearchInput" placeholder="اكتب اسم أو رقم القطعة..." oninput="searchParts()" autocomplete="off">
                                <div id="partSearchResults" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid var(--border);border-radius:8px;box-shadow:var(--shadow-lg);z-index:100;max-height:200px;overflow-y:auto;"></div>
                            </div>
                            <div class="form-group" style="flex:1;"><label>الكمية</label><input type="number" id="addQty" value="1" min="1"></div>
                            <div class="form-group" style="flex:1;"><label>السعر</label><input type="number" id="addPrice" value="0" step="0.01"></div>
                            <div class="form-group" style="display:flex;align-items:flex-end;">
                                <button class="btn btn-success btn-xs" onclick="addItemToInvoice()" style="height:38px;">➕ إضافة</button>
                            </div>
                        </div>
                        <div id="invoiceItemsTable" style="margin-top:10px;">
                            <table style="width:100%;font-size:13px;">
                                <thead><tr><th>القطعة</th><th>الكمية</th><th>سعر الوحدة</th><th>الإجمالي</th><th></th></tr></thead>
                                <tbody id="itemsBody"><tr><td colspan="5" style="text-align:center;color:var(--text-light);">لا توجد قطع</td></tr></tbody>
                            </table>
                        </div>
                        <div style="text-align:left;margin-top:8px;font-weight:bold;font-size:16px;">
                            الإجمالي: <span id="invoiceTotal" style="color:var(--primary);">0</span>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top:12px;"><label>المبلغ المدفوع</label><input type="number" id="invPaid" value="0" step="0.01" oninput="updateInvoiceRemaining()"></div>
                    <div class="form-group"><label>المتبقي</label><input id="invRemaining" readonly style="background:#fef3c7;font-weight:700;color:var(--danger);"></div>
                    <div class="form-group"><label>الدين المخصوم</label><input id="invDebt" readonly style="background:#fee2e2;font-weight:700;color:var(--danger);"></div>
                    <div class="form-group"><label>مبلغ الزيادة</label><input id="invOver" readonly style="background:#d1fae5;font-weight:700;color:var(--success);"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline" onclick="closeModal('invoiceModal')">إلغاء</button>
                    <button class="btn btn-primary" onclick="saveInvoice()">💾 حفظ الفاتورة</button>
                </div>
            </div>
        </div>`;
    document.getElementById('modalContainer').innerHTML = html;
    window._invCustomerSelect = initSearchableSelect(
        'invCustomerInput',
        customerOpts,
        (val, text) => { document.getElementById('invCustomer').value = val; }
    );
};


function searchParts() {
    const q = document.getElementById('partSearchInput')?.value.toLowerCase() || '';
    const results = document.getElementById('partSearchResults');
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
            return `<div style="padding:10px;cursor:pointer;border-bottom:1px solid var(--border);" onclick="selectInvoicePart(${p.id}, '${p.name.replace(/'/g, "\\'")}')">
                <strong>${p.name}</strong> <small style="color:var(--text-light);">(${p.part_number || 'بدون رقم'})</small>
                <div style="font-size:12px;color:var(--text-light);">متاح: ${p.quantity} — السعر: ${formatNumber(p.purchase_price || 0)} ل.س / ${formatNumber(p.purchase_price_usd || 0)} $</div>
            </div>`;
        }).join('');
    }
    results.style.display = 'block';
}

function selectInvoicePart(id, name) {
    document.getElementById('partSearchInput').value = name;
    document.getElementById('partSearchInput').dataset.partId = id;
    document.getElementById('partSearchResults').style.display = 'none';

    const part = allParts.find(p => p.id == id);
    const price = selectedCurrency === 'USD' ? (part?.purchase_price_usd || 0) : (part?.purchase_price || 0);
    document.getElementById('addPrice').value = price || '';
}

function refreshSelectedInvoicePartPrice() {
    const id = document.getElementById('partSearchInput')?.dataset?.partId;
    const name = document.getElementById('partSearchInput')?.value;
    if (id && name) {
        selectInvoicePart(id, name);
    }
}

function addItemToInvoice() {
    const partId = document.getElementById('partSearchInput').dataset.partId;
    const partName = document.getElementById('partSearchInput').value;
    const qty = parseInt(document.getElementById('addQty').value) || 0;
    const price = parseFloat(document.getElementById('addPrice').value) || 0;

    if (!partId || qty < 1 || price <= 0) {
        showToast('اختر قطعة وحدد كمية وسعر صحيح', 'error');
        return;
    }

    const part = allParts.find(p => p.id == partId);
    if (part && part.quantity < qty) {
        showToast('الكمية المطلوبة غير متوفرة في المخزون', 'error');
        return;
    }

    invoiceItems.push({ part_id: parseInt(partId), part_name: partName, quantity: qty, unit_price: price, total: qty * price });
    renderInvoiceItems();
    document.getElementById('partSearchInput').value = '';
    document.getElementById('partSearchInput').dataset.partId = '';
    document.getElementById('addQty').value = 1;
    document.getElementById('addPrice').value = 0;
}

function renderInvoiceItems() {
    const tbody = document.getElementById('itemsBody');
    if (invoiceItems.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text-light);">لا توجد قطع</td></tr>';
    } else {
        tbody.innerHTML = invoiceItems.map((it, idx) => `
            <tr>
                <td>${it.part_name}</td>
                <td>${it.quantity}</td>
                <td>${formatNumber(it.unit_price)}</td>
                <td>${formatNumber(it.total)}</td>
                <td><button class="btn btn-danger btn-xs" onclick="removeItem(${idx})">🗑️</button></td>
            </tr>
        `).join('');
    }
    const total = invoiceItems.reduce((sum, it) => sum + it.total, 0);
    document.getElementById('invoiceTotal').textContent = formatNumber(total);
    updateInvoiceRemaining();
}

function updateInvoiceRemaining() {
    const total = invoiceItems.reduce((sum, it) => sum + it.total, 0);
    const paid = parseFloat(document.getElementById('invPaid')?.value) || 0;
    const remaining = Math.max(0, total - paid);
    const over = Math.max(0, paid - total);
    const rem = document.getElementById('invRemaining');
    const debtEl = document.getElementById('invDebt');
    const overEl = document.getElementById('invOver');
    if (rem) rem.value = formatNumber(remaining);
    if (debtEl) debtEl.value = formatNumber(0);
    if (overEl) overEl.value = formatNumber(over);
}

function removeItem(idx) {
    invoiceItems.splice(idx, 1);
    renderInvoiceItems();
}

window.saveInvoice = async function () {
    const customer_id = document.getElementById('invCustomer')?.value;
    const sale_date = document.getElementById('invDate')?.value;
    const paid = parseFloat(document.getElementById('invPaid')?.value) || 0;
    const currency = document.getElementById('invCurrency')?.value || 'SYP';
    const notes = document.getElementById('invNotes')?.value || '';

    if (!customer_id || !sale_date || invoiceItems.length === 0) {
        showToast('العميل، التاريخ، وقطعة واحدة على الأقل مطلوبة', 'error');
        return;
    }

    try {
        await apiFetch('/invoices', {
            method: 'POST',
            body: JSON.stringify({
                customer_id,
                sale_date,
                paid,
                currency,
                notes,
                items: invoiceItems.map(it => ({ part_id: it.part_id, quantity: it.quantity, unit_price: it.unit_price }))
            })
        });
        showToast('تم حفظ الفاتورة ✅');
        closeModal('invoiceModal');
        loadInvoices();
    } catch (e) {
        showToast(e.message || 'حدث خطأ أثناء الحفظ', 'error');
        console.error(e);
    }
};

window.viewInvoice = function (id) {
    const target = allInvoices.find(i => i.id == id);
    if (!target) return;
    const key = groupKey(target);
    const invoices = allInvoices.filter(i => groupKey(i) === key);
    const curr = target.currency === 'USD' ? '$' : 'ل.س';
    const total = invoices.reduce((s, i) => s + parseFloat(i.total || 0), 0);
    const remaining = invoices.reduce((s, i) => s + parseFloat(i.remaining || 0), 0);
    const credit = invoices.reduce((s, i) => s + parseFloat(i.credit_used || 0), 0);
    const debt = invoices.reduce((s, i) => s + parseFloat(i.debt || 0), 0);
    const over = invoices.reduce((s, i) => s + Math.max(0, parseFloat(i.paid || 0) + parseFloat(i.credit_used || 0) - parseFloat(i.debt || 0) - parseFloat(i.total || 0)), 0);
    const paid = total - remaining + over;
    const itemsHtml = invoices.flatMap(inv => inv.items || []).map(it => `<tr><td>${it.part?.name || '؟'}</td><td>${it.quantity}</td><td>${formatCurrency(it.unit_price, curr)}</td><td>${formatCurrency(it.total, curr)}</td></tr>`).join('');
    const first = invoices[0];

    showModal('🧾 تفاصيل الفاتورة #' + first.id, `
        <div style="margin-bottom:12px;">
            <div><strong>العميل:</strong> ${first.customer?.name || '؟'}</div>
            <div><strong>التاريخ:</strong> ${formatDate(first.sale_date)}</div>
            <div><strong>العملة:</strong> ${first.currency}</div>
            <div><strong>الملاحظات:</strong> ${first.notes || 'لا يوجد'}</div>
        </div>
        <table style="width:100%;font-size:13px;">
            <thead><tr><th>القطعة</th><th>الكمية</th><th>سعر الوحدة</th><th>الإجمالي</th></tr></thead>
            <tbody>${itemsHtml || '<tr><td colspan="4" style="text-align:center;">لا توجد قطع</td></tr>'}</tbody>
        </table>
        <div style="margin-top:12px;text-align:left;font-weight:bold;">
            <div>الإجمالي: ${formatCurrency(total, curr)}</div>
            <div>المدفوع: ${formatCurrency(paid, curr)}</div>
            <div>المتبقي: ${formatCurrency(remaining, curr)}</div>
        </div>
    `);
};

window.openReturnInvoiceModal = async function (id) {
    try {
        const inv = await apiFetch('/invoices/' + id);
        const itemsHtml = (inv.items || []).map(it => {
            const returned = it.returned_quantity || 0;
            const max = it.quantity - returned;
            return max > 0 ? `<tr>
                <td>${it.part?.name || '؟'}</td>
                <td>${it.quantity}</td>
                <td>${returned}</td>
                <td><input type="number" id="retQty_${it.id}" min="0" max="${max}" value="0" style="width:70px;padding:6px;border-radius:6px;border:1.5px solid var(--border);"></td>
            </tr>` : '';
        }).join('');
        if (!itemsHtml) {
            showToast('لا يوجد قطع متاحة للترجيع', 'error');
            return;
        }
        const html = `
        <div class="modal-overlay" id="retModal">
            <div class="modal"><div class="modal-header"><h3>↩️ ترجيع فاتورة ${inv.invoice_number}</h3><button class="modal-close" onclick="closeModal('retModal')">✕</button></div>
            <div class="modal-body">
                <table style="width:100%;font-size:13px;"><thead><tr><th>القطعة</th><th>الكمية</th><th>تم ترجيعها</th><th>ترجيع جديد</th></tr></thead><tbody>${itemsHtml}</tbody></table>
                <div class="form-group" style="margin-top:12px;"><label>سبب الترجيع</label><input id="retReason" placeholder="اكتب سبب الترجيع (اختياري)"></div>
            </div>
            <div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('retModal')">إلغاء</button>
            <button class="btn btn-danger" onclick="saveReturnInvoice(${inv.id})">تأكيد الترجيع</button></div></div></div>`;
        document.getElementById('modalContainer').innerHTML = html;
    } catch (e) {
        showToast(e.message || 'حدث خطأ أثناء التحميل', 'error');
        console.error(e);
    }
};

window.saveReturnInvoice = async function (id) {
    try {
        const inv = allInvoices.find(i => i.id === id);
        if (!inv) return;
        const retItems = [];
        for (const it of inv.items || []) {
            const max = it.quantity - (it.returned_quantity || 0);
            const qty = parseInt(document.getElementById('retQty_' + it.id)?.value) || 0;
            if (qty > 0) {
                if (qty > max) {
                    showToast('كمية الترجيع تتجاوز المتاح لـ ' + (it.part?.name || '؟'), 'error');
                    return;
                }
                retItems.push({ invoice_item_id: it.id, quantity: qty });
            }
        }
        if (retItems.length === 0) {
            showToast('أدخل كمية ترجيع واحدة على الأقل', 'error');
            return;
        }
        await apiFetch('/invoices/' + id + '/return', {
            method: 'POST',
            body: JSON.stringify({ items: retItems, reason: document.getElementById('retReason')?.value || '' })
        });
        showToast('تم الترجيع ✅');
        closeModal('retModal');
        loadInvoices();
    } catch (e) {
        showToast(e.message || 'حدث خطأ أثناء الترجيع', 'error');
        console.error(e);
    }
};

window.deleteInvoice = async function (id) {
    if (!confirm('حذف هذه الفاتورة؟')) return;
    try {
        await apiFetch('/invoices/' + id, { method: 'DELETE' });
        showToast('تم الحذف 🗑️');
        loadInvoices();
    } catch (e) {
        showToast('حدث خطأ أثناء الحذف', 'error');
        console.error(e);
    }
};

loadInvoices();
