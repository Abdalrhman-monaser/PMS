# Database Backup & Disaster Recovery Guide

يوثق هذا الدليل إجراءات النسخ الاحتياطي واستعادة البيانات لنظام إدارة المشاريع (PMS).

---

## 1. أتمتة النسخ الاحتياطي (Automated Backup)
يتم تشغيل سكربت النسخ عبر تنفيذ الأمر التالي:
```bash
bash scripts/backup.sh