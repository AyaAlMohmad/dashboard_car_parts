let allSales = [];
let allCustomers = [];
let allParts = [];

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
                        <div class="form-group"><label>العميل *</label><select id="saleCustomer">${customerOpts}</select></div>
                        <div class="form-group"><label>القطعة *</label><select id="salePart">${partOpts}</select></div>
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
