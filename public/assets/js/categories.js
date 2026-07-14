let allCategories = [];
let editCategoryId = null;

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

    if (!list.length) {
        tbody.innerHTML = '<tr><td colspan="4"><div class="empty-state">🏷️ لا توجد فئات</div></td></tr>';
        return;
    }

    tbody.innerHTML = list.map(c => {
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
