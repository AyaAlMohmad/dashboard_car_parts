let allCategories = [];
let editCategoryId = null;
let categoriesPage = 1;
const categoriesPerPage = 20;

async function loadCategories() {
    try {
        allCategories = await apiFetch('/categories');
        renderCategories();
    } catch (e) {
        showToast('حدث خطأ أثناء تحميل الفئات', 'error');
        console.error(e);
    }
}

function renderCategories() {
    const tbody = document.getElementById('categoriesTableBody');
    const search = document.getElementById('categorySearch')?.value || '';

    let list = allCategories;
    if (search) {
        list = list.filter(c => c.name.toLowerCase().includes(search.toLowerCase()));
    }

    const totalPages = Math.ceil(list.length / categoriesPerPage);
    if (categoriesPage > totalPages) categoriesPage = totalPages || 1;
    const start = (categoriesPage - 1) * categoriesPerPage;
    const pageItems = list.slice(start, start + categoriesPerPage);

    if (!pageItems.length) {
        tbody.innerHTML = '<tr><td colspan="4"><div class="empty-state">🏷️ لا توجد فئات</div></td></tr>';
        renderCategoriesPagination(0);
        return;
    }

    tbody.innerHTML = pageItems.map(c => {
        return `<tr>
            <td>${c.id}</td>
            <td><strong>${c.name}</strong></td>
            <td>${c.parts_count || 0}</td>
            <td>
                <button class="btn btn-xs btn-primary" onclick="openCategoryModal(${c.id})">✏️ تعديل</button>
                <button class="btn btn-xs btn-danger" onclick="deleteCategory(${c.id})">🗑️ حذف</button>
            </td>
        </tr>`;
    }).join('');
    renderCategoriesPagination(list.length);
}

function renderCategoriesPagination(total) {
    const totalPages = Math.ceil(total / categoriesPerPage);
    const container = document.getElementById('categoriesPagination');
    if (!container) return;
    if (totalPages <= 1) { container.innerHTML = ''; return; }
    let html = '<div class="pagination" style="display:flex;justify-content:center;align-items:center;gap:6px;margin-top:16px;flex-wrap:wrap;">';
    html += `<button class="btn btn-outline btn-xs" onclick="changeCategoriesPage(${categoriesPage - 1})" ${categoriesPage === 1 ? 'disabled' : ''}>← السابق</button>`;
    for (let i = 1; i <= totalPages; i++) {
        if (i === categoriesPage) { html += `<span class="btn btn-primary btn-xs" style="cursor:default">${i}</span>`; }
        else if (i === 1 || i === totalPages || (i >= categoriesPage - 1 && i <= categoriesPage + 1)) { html += `<button class="btn btn-outline btn-xs" onclick="changeCategoriesPage(${i})">${i}</button>`; }
        else if (i === categoriesPage - 2 || i === categoriesPage + 2) { html += `<span style="padding:4px 8px;">…</span>`; }
    }
    html += `<button class="btn btn-outline btn-xs" onclick="changeCategoriesPage(${categoriesPage + 1})" ${categoriesPage === totalPages ? 'disabled' : ''}>التالي →</button>`;
    html += '</div>';
    container.innerHTML = html;
}

window.changeCategoriesPage = function (page) {
    const search = document.getElementById('categorySearch')?.value || '';
    let list = allCategories;
    if (search) {
        list = list.filter(c => c.name.toLowerCase().includes(search.toLowerCase()));
    }
    const totalPages = Math.ceil(list.length / categoriesPerPage);
    if (page < 1 || page > totalPages) return;
    categoriesPage = page;
    renderCategories();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

window.openCategoryModal = function (id = null) {
    editCategoryId = id || null;
    const title = id ? '✏️ تعديل فئة' : '➕ إضافة فئة';
    const c = id ? allCategories.find(x => x.id === id) : null;
    const name = c ? c.name : '';

    const html = `
        <div class="modal-overlay" id="catModal">
            <div class="modal">
                <div class="modal-header"><h3>${title}</h3><button class="modal-close" onclick="closeModal('catModal')">✕</button></div>
                <div class="modal-body">
                    <div class="form-group"><label>اسم الفئة *</label><input id="catName" class="form-control" value="${name}" required maxlength="255"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline" onclick="closeModal('catModal')">إلغاء</button>
                    <button class="btn btn-primary" onclick="saveCategory()">${id ? '💾 حفظ التعديل' : '💾 إضافة'}</button>
                </div>
            </div>
        </div>`;
    document.getElementById('modalContainer').innerHTML = html;
}

window.saveCategory = async function () {
    const name = document.getElementById('catName')?.value.trim();
    if (!name) {
        showToast('اسم الفئة مطلوب', 'error');
        return;
    }
    const payload = { name };

    try {
        if (editCategoryId) {
            await apiFetch('/categories/' + editCategoryId, {
                method: 'PUT',
                body: JSON.stringify(payload)
            });
            showToast('✅ تم تعديل الفئة');
        } else {
            await apiFetch('/categories', {
                method: 'POST',
                body: JSON.stringify(payload)
            });
            showToast('✅ تمت إضافة الفئة');
        }
        closeModal('catModal');
        loadCategories();
    } catch (err) {
        showToast('حدث خطأ أثناء الحفظ', 'error');
        console.error(err);
    }
}

window.deleteCategory = async function (id) {
    if (!confirm('هل أنت متأكد من حذف هذه الفئة؟ ستُحذف الفئة من جميع القطع المرتبطة بها.')) return;
    try {
        await apiFetch('/categories/' + id, { method: 'DELETE' });
        showToast('🗑️ تم حذف الفئة');
        loadCategories();
    } catch (e) {
        showToast('حدث خطأ أثناء الحذف', 'error');
        console.error(e);
    }
};

loadCategories();
