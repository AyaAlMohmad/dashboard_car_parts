let allBackups = [];
let allSchedules = [];

async function loadBackups() {
    try {
        const data = await apiFetch('/backups');
        allBackups = data.data || [];
        renderBackups();
    } catch (e) {
        showToast('حدث خطأ أثناء تحميل النسخ الاحتياطية', 'error');
        console.error(e);
    }
}

async function loadSchedules() {
    try {
        const data = await apiFetch('/backup-schedules');
        allSchedules = data || [];
        renderSchedules();
    } catch (e) {
        showToast('حدث خطأ أثناء تحميل الجداول', 'error');
        console.error(e);
    }
}

function formatBytes(bytes, decimals = 2) {
    if (!bytes || bytes === 0) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

function renderBackups() {
    const search = (document.getElementById('backupSearch')?.value || '').toLowerCase();
    let filtered = allBackups.filter((b) => b.filename.toLowerCase().includes(search));
    const tbody = document.getElementById('backupsTableBody');
    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6"><div class="empty-state">💾 لا توجد نسخ احتياطية</div></td></tr>';
        return;
    }
    tbody.innerHTML = filtered.map((b) => {
        const fmt = b.format === 'excel' ? 'Excel' : 'SQL/SQLite';
        const icon = b.format === 'excel' ? '📊' : '🗄️';
        return `<tr>
            <td>${b.id}</td>
            <td>${icon} ${b.filename}</td>
            <td><span class="badge badge-info">${fmt}</span></td>
            <td>${formatBytes(b.size)}</td>
            <td>${formatDate(b.created_at)}</td>
            <td>
                <a class="btn btn-success btn-xs" href="/api/backups/${b.id}/download" download>📥 تحميل</a>
                <button class="btn btn-danger btn-xs" onclick="deleteBackup(${b.id})">🗑️ حذف</button>
            </td>
        </tr>`;
    }).join('');
}

function renderSchedules() {
    const tbody = document.getElementById('schedulesTableBody');
    if (!allSchedules.length) {
        tbody.innerHTML = '<tr><td colspan="8"><div class="empty-state">⏰ لا توجد جداول تصدير تلقائي</div></td></tr>';
        return;
    }
    const typeLabels = {
        full: 'كامل', customers: 'العملاء', parts: 'القطع', sales: 'المبيعات',
        payments: 'التسديدات', suppliers: 'الموردين', purchases: 'المشتريات', supplier_payments: 'مدفوعات الموردين'
    };
    const intervalLabels = { hourly: 'كل ساعة', daily: 'يومي', weekly: 'أسبوعي', monthly: 'شهري' };
    tbody.innerHTML = allSchedules.map((s) => {
        const status = s.enabled
            ? '<span class="badge badge-success">🟢 نشط</span>'
            : '<span class="badge badge-danger">🔴 معطل</span>';
        return `<tr>
            <td>${s.id}</td>
            <td>${s.name || '-'}</td>
            <td>${typeLabels[s.type] || s.type}</td>
            <td>${s.format === 'excel' ? '📊 Excel' : '🗄️ SQL'}</td>
            <td>${intervalLabels[s.interval] || s.interval}</td>
            <td>${s.last_run_at ? formatDate(s.last_run_at) : 'لم يُشغّل'}</td>
            <td>${status}</td>
            <td>
                <button class="btn btn-outline btn-xs" onclick="toggleSchedule(${s.id}, ${!s.enabled})">${s.enabled ? '🔴 تعطيل' : '🟢 تفعيل'}</button>
                <button class="btn btn-danger btn-xs" onclick="deleteSchedule(${s.id})">🗑️</button>
            </td>
        </tr>`;
    }).join('');
}

window.createBackup = async function (format) {
    try {
        showToast('جاري إنشاء النسخة الاحتياطية...', 'info');
        await apiFetch('/backups', {
            method: 'POST',
            body: JSON.stringify({ format })
        });
        showToast('✅ تم إنشاء النسخة الاحتياطية');
        loadBackups();
    } catch (e) {
        showToast('حدث خطأ أثناء الإنشاء', 'error');
        console.error(e);
    }
};

window.openScheduleModal = function () {
    const html = `
        <div class="modal-overlay" id="scheduleModal">
            <div class="modal">
                <div class="modal-header"><h3>⏰ جدولة تصدير تلقائي</h3><button class="modal-close" onclick="closeModal('scheduleModal')">✕</button></div>
                <div class="modal-body">
                    <div class="form-group"><label>الاسم (اختياري)</label><input id="schName" placeholder="مثال: نسخ يومي"></div>
                    <div class="form-row">
                        <div class="form-group"><label>النوع *</label>
                            <select id="schType">
                                <option value="full">كامل</option>
                                <option value="customers">العملاء</option>
                                <option value="parts">القطع</option>
                                <option value="sales">المبيعات</option>
                                <option value="payments">التسديدات</option>
                                <option value="suppliers">الموردين</option>
                                <option value="purchases">المشتريات</option>
                                <option value="supplier_payments">مدفوعات الموردين</option>
                            </select>
                        </div>
                        <div class="form-group"><label>الصيغة *</label>
                            <select id="schFormat">
                                <option value="excel">Excel</option>
                                <option value="sql">SQL/SQLite</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group"><label>الفترة *</label>
                        <select id="schInterval">
                            <option value="hourly">كل ساعة</option>
                            <option value="daily" selected>يومي</option>
                            <option value="weekly">أسبوعي</option>
                            <option value="monthly">شهري</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline" onclick="closeModal('scheduleModal')">إلغاء</button>
                    <button class="btn btn-warning" onclick="saveSchedule()">💾 حفظ الجدولة</button>
                </div>
            </div>
        </div>`;
    document.getElementById('modalContainer').innerHTML = html;
};

window.saveSchedule = async function () {
    const name = document.getElementById('schName')?.value.trim() || null;
    const type = document.getElementById('schType')?.value;
    const format = document.getElementById('schFormat')?.value;
    const interval = document.getElementById('schInterval')?.value;
    if (!type || !format || !interval) {
        showToast('جميع الحقول المطلوبة يجب ملؤها', 'error');
        return;
    }
    try {
        await apiFetch('/backup-schedules', {
            method: 'POST',
            body: JSON.stringify({ name, type, format, interval, enabled: true })
        });
        showToast('✅ تم إنشاء الجدولة');
        closeModal('scheduleModal');
        loadSchedules();
    } catch (e) {
        showToast('حدث خطأ أثناء الحفظ', 'error');
        console.error(e);
    }
};

window.toggleSchedule = async function (id, enabled) {
    try {
        await apiFetch('/backup-schedules/' + id, {
            method: 'PUT',
            body: JSON.stringify({ enabled })
        });
        showToast(enabled ? '🟢 تم التفعيل' : '🔴 تم التعطيل');
        loadSchedules();
    } catch (e) {
        showToast('حدث خطأ', 'error');
        console.error(e);
    }
};

window.deleteBackup = async function (id) {
    if (!confirm('حذف هذه النسخة الاحتياطية؟')) return;
    try {
        await apiFetch('/backups/' + id, { method: 'DELETE' });
        showToast('🗑️ تم حذف النسخة');
        loadBackups();
    } catch (e) {
        showToast('حدث خطأ أثناء الحذف', 'error');
        console.error(e);
    }
};

window.deleteSchedule = async function (id) {
    if (!confirm('حذف هذه الجدولة؟')) return;
    try {
        await apiFetch('/backup-schedules/' + id, { method: 'DELETE' });
        showToast('🗑️ تم الحذف');
        loadSchedules();
    } catch (e) {
        showToast('حدث خطأ', 'error');
        console.error(e);
    }
};

let nextRunTimer = null;

async function loadNextRun() {
    try {
        const data = await apiFetch('/backup-schedules/next-run');
        if (data.message) {
            document.getElementById('nextRunCard').style.display = 'none';
            return;
        }
        document.getElementById('nextRunCard').style.display = 'grid';
        const intervalLabels = { hourly: 'كل ساعة', daily: 'يومي', weekly: 'أسبوعي', monthly: 'شهري' };
        const label = (data.name || 'جدولة') + ' — ' + intervalLabels[data.interval];

        const seconds = data.seconds_remaining;
        if (seconds <= 0) {
            document.getElementById('nextRunLabel').textContent = label + ' — ' + '⏰ التشغيل متأخر (شغّل schedule:work)';
            startCountdown(0);
        } else {
            document.getElementById('nextRunLabel').textContent = label;
            startCountdown(seconds);
        }
    } catch (e) {
        console.error(e);
    }
}

function startCountdown(seconds) {
    if (nextRunTimer) clearInterval(nextRunTimer);
    let remaining = seconds;
    const el = document.getElementById('nextRunCountdown');

    function tick() {
        if (remaining <= 0) {
            el.textContent = '⏰ 00:00:00';
            el.style.color = 'var(--danger)';
            return;
        }
        const h = Math.floor(remaining / 3600).toString().padStart(2, '0');
        const m = Math.floor((remaining % 3600) / 60).toString().padStart(2, '0');
        const s = (remaining % 60).toString().padStart(2, '0');
        el.textContent = `${h}:${m}:${s}`;
        el.style.color = remaining < 300 ? 'var(--danger)' : 'var(--text)';
        remaining--;
    }
    tick();
    nextRunTimer = setInterval(tick, 1000);
}

loadBackups();
loadSchedules();
loadNextRun();
setInterval(loadNextRun, 60000); // refresh every 60s
