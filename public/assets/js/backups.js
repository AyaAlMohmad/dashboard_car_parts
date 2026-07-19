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
        tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state">💾 لا توجد نسخ احتياطية</div></td></tr>';
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
            <td>${formatTime(b.created_at)}</td>
            <td>
                <a class="btn btn-success btn-xs" href="/api/backups/${b.id}/download" download>📥 تحميل</a>
                <button class="btn btn-info btn-xs" onclick="sendBackupEmail(${b.id})">📧 إرسال</button>
                <button class="btn btn-danger btn-xs" onclick="deleteBackup(${b.id})">🗑️ حذف</button>
            </td>
        </tr>`;
    }).join('');
}

function renderSchedules() {
    const tbody = document.getElementById('schedulesTableBody');
    if (!allSchedules.length) {
        tbody.innerHTML = '<tr><td colspan="9"><div class="empty-state">⏰ لا توجد جداول تصدير تلقائي</div></td></tr>';
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
            <td>${s.last_run_at ? formatTime(s.last_run_at) : '-'}</td>
            <td>${status}</td>
            <td>
                <button class="btn btn-outline btn-xs" onclick="toggleSchedule(${s.id}, ${!s.enabled})">${s.enabled ? '🔴 تعطيل' : '🟢 تفعيل'}</button>
                <button class="btn btn-primary btn-xs" onclick="runScheduleNow(${s.id})">▶️ تشغيل</button>
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
        if (enabled) {
            showToast('🟢 تم التفعيل، جاري التشغيل...', 'info');
            await apiFetch('/backup-schedules/' + id + '/run', { method: 'POST' });
            showToast('✅ تم تشغيل الجدولة وإنشاء النسخة');
            loadBackups();
        } else {
            showToast('🔴 تم التعطيل');
        }
        loadSchedules();
    } catch (e) {
        showToast('حدث خطأ', 'error');
        console.error(e);
    }
};

window.runScheduleNow = async function (id) {
    try {
        showToast('جاري تشغيل الجدولة...', 'info');
        await apiFetch('/backup-schedules/' + id + '/run', { method: 'POST' });
        showToast('✅ تم تشغيل الجدولة وإنشاء النسخة');
        loadSchedules();
        loadBackups();
    } catch (e) {
        showToast('حدث خطأ أثناء التشغيل', 'error');
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
        const label = (data.name || 'جدولة') + ' — ' + (data.interval_label || 'مؤقت');

        const seconds = data.seconds_remaining;
        if (seconds <= 0) {
            document.getElementById('nextRunLabel').textContent = label + ' — ' + '⏰ التشغيل متأخر (شغّل schedule:work)';
            startCountdown(0, data.interval);
        } else {
            document.getElementById('nextRunLabel').textContent = label;
            startCountdown(seconds, data.interval);
        }
    } catch (e) {
        console.error(e);
    }
}

function startCountdown(seconds, intervalType) {
    if (nextRunTimer) clearInterval(nextRunTimer);
    let remaining = seconds;
    const el = document.getElementById('nextRunCountdown');

    function formatCountdown(sec) {
        if (sec <= 0) return '⏰ 00:00:00';
        const d = Math.floor(sec / 86400);
        const h = Math.floor((sec % 86400) / 3600);
        const m = Math.floor((sec % 3600) / 60);
        const s = sec % 60;

        // للفترات الطويلة (أسبوعي/شهري) نظهر الأيام أيضاً
        if (intervalType === 'weekly' || intervalType === 'monthly') {
            return `${d}ي ${h.toString().padStart(2,'0')}:${m.toString().padStart(2,'0')}:${s.toString().padStart(2,'0')}`;
        }
        // للفترات القصيرة (ساعي/يومي) نظهر الساعات فقط
        const totalH = Math.floor(sec / 3600);
        return `${totalH.toString().padStart(2,'0')}:${m.toString().padStart(2,'0')}:${s.toString().padStart(2,'0')}`;
    }

    function tick() {
        if (remaining <= 0) {
            el.textContent = '⏰ 00:00:00';
            el.style.color = 'var(--danger)';
            return;
        }
        el.textContent = formatCountdown(remaining);
        el.style.color = remaining < 300 ? 'var(--danger)' : 'var(--text)';
        remaining--;
    }
    tick();
    nextRunTimer = setInterval(tick, 1000);
}

// --- Email ---
window.openEmailSettingsModal = async function () {
    try {
        const settings = await apiFetch('/backups/email/settings');
        const html = `
            <div class="modal-overlay" id="emailSettingsModal">
                <div class="modal">
                    <div class="modal-header"><h3>📧 إعدادات البريد الإلكتروني</h3><button class="modal-close" onclick="closeModal('emailSettingsModal')">✕</button></div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label><input type="checkbox" id="emailEnabled" ${settings.enabled ? 'checked' : ''}> تفعيل الإرسال التلقائي بالبريد عند إنشاء نسخة</label>
                        </div>
                        <div class="form-group"><label>البريد المستلم (إلزامي)</label><input id="emailTo" type="email" value="${settings.to || ''}" placeholder="example@gmail.com"></div>
                        <div class="form-group"><label>SMTP Host (مثلاً: smtp.gmail.com)</label><input id="emailSmtpHost" value="${settings.smtp_host || ''}" placeholder="smtp.gmail.com"></div>
                        <div class="form-group"><label>SMTP Port</label><input id="emailSmtpPort" value="${settings.smtp_port || '587'}" placeholder="587"></div>
                        <div class="form-group"><label>SMTP User (بريد المُرسِل)</label><input id="emailSmtpUser" value="${settings.smtp_user || ''}" placeholder="example@gmail.com"></div>
                        <div class="form-group"><label>SMTP Password (كلمة مرور التطبيق)</label><input id="emailSmtpPass" type="password" value="${settings.smtp_pass || ''}" placeholder="كلمة المرور"></div>
                        <div class="form-group"><label>SMTP Encryption</label>
                            <select id="emailSmtpEncryption">
                                <option value="tls" ${settings.smtp_encryption === 'tls' ? 'selected' : ''}>TLS</option>
                                <option value="ssl" ${settings.smtp_encryption === 'ssl' ? 'selected' : ''}>SSL</option>
                                <option value="" ${settings.smtp_encryption === '' ? 'selected' : ''}>بدون</option>
                            </select>
                        </div>
                        <div style="font-size:12px;color:var(--text-light);margin-top:8px;">
                            💡 <strong>نصيحة Gmail:</strong> استخدم "App Password" من إعدادات أمان حساب Google بدلاً من كلمة المرور العادية.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline" onclick="closeModal('emailSettingsModal')">إلغاء</button>
                        <button class="btn btn-primary" onclick="saveEmailSettings()">💾 حفظ الإعدادات</button>
                    </div>
                </div>
            </div>`;
        document.getElementById('modalContainer').innerHTML = html;
    } catch (e) {
        showToast('حدث خطأ أثناء تحميل الإعدادات', 'error');
    }
};

window.saveEmailSettings = async function () {
    const enabled = document.getElementById('emailEnabled')?.checked || false;
    const to = document.getElementById('emailTo')?.value.trim() || '';
    const smtp_host = document.getElementById('emailSmtpHost')?.value.trim() || '';
    const smtp_port = document.getElementById('emailSmtpPort')?.value.trim() || '587';
    const smtp_user = document.getElementById('emailSmtpUser')?.value.trim() || '';
    const smtp_pass = document.getElementById('emailSmtpPass')?.value.trim() || '';
    const smtp_encryption = document.getElementById('emailSmtpEncryption')?.value || 'tls';

    if (enabled && !to) {
        showToast('أدخل البريد المستلم لتفعيل الإرسال', 'error');
        return;
    }

    try {
        await apiFetch('/backups/email/settings', {
            method: 'POST',
            body: JSON.stringify({ enabled, to, smtp_host, smtp_port, smtp_user, smtp_pass, smtp_encryption })
        });
        showToast('✅ تم حفظ إعدادات البريد');
    } catch (e) {
        showToast('حدث خطأ أثناء الحفظ', 'error');
    }
};

window.sendBackupEmail = async function (id) {
    try {
        showToast('جاري إرسال النسخة بالبريد...', 'info');
        const data = await apiFetch('/backups/' + id + '/send-email', { method: 'POST' });
        showToast(data.message || '✅ تم الإرسال');
    } catch (e) {
        showToast(e.message || 'حدث خطأ أثناء الإرسال', 'error');
    }
};

loadBackups();
loadSchedules();
loadNextRun();
setInterval(loadNextRun, 60000); // refresh every 60s
