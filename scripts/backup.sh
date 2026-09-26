#!/bin/bash
# ============================================================
# PMS Database Backup Script (Phase 7 / DevOps)
# ============================================================

set -e

DB_NAME="${DB_NAME:-pms_db}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
BACKUP_DIR="$(dirname "$0")/../storage/backups"
KEEP_DAYS=14

# البحث عن mysqldump تلقائياً إذا كان المسار الافتراضي لـ XAMPP
if ! command -v mysqldump &> /dev/null; then
    if [ -f "/c/xampp/mysql/bin/mysqldump.exe" ]; then
        MYSQLDUMP_CMD="/c/xampp/mysql/bin/mysqldump.exe"
    elif [ -f "C:/xampp/mysql/bin/mysqldump.exe" ]; then
        MYSQLDUMP_CMD="C:/xampp/mysql/bin/mysqldump.exe"
    else
        echo "Error: mysqldump command not found. Please add MySQL to your PATH." >&2
        exit 1
    fi
else
    MYSQLDUMP_CMD="mysqldump"
fi

mkdir -p "$BACKUP_DIR"

TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")
OUT_FILE="$BACKUP_DIR/pms_db_${TIMESTAMP}.sql.gz"

echo "Running backup using: $MYSQLDUMP_CMD ..."

if [ -z "$DB_PASS" ]; then
    "$MYSQLDUMP_CMD" -u "$DB_USER" "$DB_NAME" | gzip > "$OUT_FILE"
else
    "$MYSQLDUMP_CMD" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > "$OUT_FILE"
fi

echo "Backup written to $OUT_FILE"

# تنظيف النسخ القديمة
find "$BACKUP_DIR" -name "pms_db_*.sql.gz" -mtime +"$KEEP_DAYS" -delete

echo "Pruned backups older than $KEEP_DAYS days."