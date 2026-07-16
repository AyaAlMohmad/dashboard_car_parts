مرحباً،

مُرفق مع هذا البريد نسخة احتياطية من نظام إدارة متجر قطع السيارات.

تفاصيل النسخة:
- الاسم: {{ $backup->filename }}
- الصيغة: {{ $backup->format === 'excel' ? 'Excel' : 'SQL/SQLite' }}
- الحجم: {{ $backup->size ? number_format($backup->size / 1024, 2) . ' KB' : '-' }}
- تاريخ الإنشاء: {{ $backup->created_at }}

مع التحية،
نظام إدارة متجر قطع السيارات
