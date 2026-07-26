let allInvoices = [];
let allCustomers = [];
let allParts = [];
let exchangeRate = 1;
let invoiceItems = [];
let selectedCurrency = 'SYP';

async function loadInvoices() {
    try {
        const [invData, custData, partsData, settings] = await Promise.all([
            apiFetch('/invoices'),
            apiFetch('/customers'),
            apiFetch('/parts'),
            apiFetch('/invoices/settings/exchange-rate').catch(() => ({ exchange_rate: 1 }))
        ]);
        allInvoices = invData.data || [];
        allCustomers = custData.data || [];
        allParts = partsData.data || [];
        exchangeRate = settings.exchange_rate || 1;
        renderInvoices();
    } catch (e) {
        showToast('حدث خطأ أثناء تحميل البيانات', 'error');
        console.error(e);
    }
}

function renderInvoices() {
    const search = (document.getElementById('invoiceSearch')?.value || '').toLowerCase();
    let filtered = allInvoices.filter((inv) => {
        const cname = (inv.customer?.name || '').toLowerCase();
        const items = inv.items || [];
        const itemNames = items.map(i => (i.part?.name || '').toLowerCase()).join(' ');
        return cname.includes(search) || itemNames.includes(search) || inv.invoice_number.toLowerCase().includes(search);
    });
    const tbody = document.getElementById('invoicesTableBody');
    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10"><div class="empty-state">🧾 لا توجد فواتير</div></td></tr>';
        return;
    }
    tbody.innerHTML = filtered.map((inv) => {
        const items = inv.items || [];
        const itemSummary = items.length > 1
            ? `${items.length} قطع`
            : (items[0]?.part?.name || '؟');
        const currSymbol = inv.currency === 'USD' ? '$' : 'ل.س';
        return `<tr>
            <td style="cursor:pointer;color:var(--primary);font-weight:600;" onclick="viewInvoice(${inv.id})">${inv.invoice_number}</td>
            <td>${formatDate(inv.sale_date)}</td>
            <td>${formatTime(inv.created_at)}</td>
            <td>${inv.customer?.name || '؟'}</td>
            <td>${itemSummary}</td>
            <td>${currSymbol} ${formatNumber(inv.total)}</td>
            <td>${currSymbol} ${formatNumber(inv.paid)}</td>
            <td>${currSymbol} ${formatNumber(inv.remaining)}</td>
            <td>${renderBadge(inv.status)}</td>
            <td>
                <button class="btn btn-outline btn-xs" onclick="viewInvoice(${inv.id})">👁️ عرض</button>
                <button class="btn btn-warning btn-xs" onclick="openReturnInvoiceModal(${inv.id})">مرتجع ↩️</button>
                <button class="btn btn-danger btn-xs" onclick="deleteInvoice(${inv.id})">🗑️</button>
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
                            <select id="invCurrency" onchange="selectedCurrency = this.value">
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
            const price = selectedCurrency === 'USD' && p.sale_price_usd ? p.sale_price_usd : p.sale_price;
            return `<div style="padding:10px;cursor:pointer;border-bottom:1px solid var(--border);" onclick="selectPart(${p.id}, '${p.name.replace(/'/g, "\\'")}', ${price})">
                <strong>${p.name}</strong> <small style="color:var(--text-light);">(${p.part_number || 'بدون رقم'})</small>
                <div style="font-size:12px;color:var(--text-light);">متاح: ${p.quantity} — السعر: ${formatNumber(price)}</div>
            </div>`;
        }).join('');
    }
    results.style.display = 'block';
}

function selectPart(id, name, price) {
    document.getElementById('partSearchInput').value = name;
    document.getElementById('partSearchInput').dataset.partId = id;
    document.getElementById('addPrice').value = price;
    document.getElementById('partSearchResults').style.display = 'none';
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
    const rem = document.getElementById('invRemaining');
    if (rem) rem.value = formatNumber(Math.max(0, total - paid));
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
    const inv = allInvoices.find(i => i.id === id);
    if (!inv) return;
    const items = inv.items || [];
    const currSymbol = inv.currency === 'USD' ? '$' : 'ل.س';
    const itemsHtml = items.length
        ? items.map(it => `<tr><td>${it.part?.name || '؟'}</td><td>${it.quantity}</td><td>${currSymbol} ${formatNumber(it.unit_price)}</td><td>${currSymbol} ${formatNumber(it.total)}</td></tr>`).join('')
        : '<tr><td colspan="4" style="text-align:center;">لا توجد قطع</td></tr>';

    showModal('🧾 تفاصيل الفاتورة ' + inv.invoice_number, `
        <div style="margin-bottom:12px;">
            <div><strong>العميل:</strong> ${inv.customer?.name || '؟'}</div>
            <div><strong>التاريخ:</strong> ${formatDate(inv.sale_date)}</div>
            <div><strong>العملة:</strong> ${inv.currency}</div>
            <div><strong>الملاحظات:</strong> ${inv.notes || 'لا يوجد'}</div>
        </div>
        <table style="width:100%;font-size:13px;">
            <thead><tr><th>القطعة</th><th>الكمية</th><th>سعر الوحدة</th><th>الإجمالي</th></tr></thead>
            <tbody>${itemsHtml}</tbody>
        </table>
        <div style="margin-top:12px;text-align:left;font-weight:bold;">
            <div>الإجمالي: ${currSymbol} ${formatNumber(inv.total)}</div>
            <div>المدفوع: ${currSymbol} ${formatNumber(inv.paid)}</div>
            <div>المتبقي: ${currSymbol} ${formatNumber(inv.remaining)}</div>
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
