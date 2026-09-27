# PMS — Project Management System

![PHP](https://img.shields.io/badge/PHP-Backend-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL%20%2F%20MariaDB-Database-4479A1?logo=mysql&logoColor=white)
![Apache](https://img.shields.io/badge/Apache-Web%20Server-D22128?logo=apache&logoColor=white)
![XAMPP](https://img.shields.io/badge/XAMPP-Local%20Environment-FB7A24?logo=xampp&logoColor=white)
![HTML5](https://img.shields.io/badge/HTML5-Frontend-E34F26?logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-Styling-1572B6?logo=css3&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-Client%20Side-F7DF1E?logo=javascript&logoColor=black)
![PDO](https://img.shields.io/badge/PDO-Data%20Access-777BB4?logo=php&logoColor=white)
![Bash](https://img.shields.io/badge/Bash-Automation-4EAA25?logo=gnubash&logoColor=white)

> A modular PHP/MySQL project management system for projects, tasks, teams, permissions, attachments, notifications, reporting, activity logging, and database backup.

---

## Table of Contents

- [Overview](#overview)
- [Key Features](#key-features)
- [Technology Stack](#technology-stack)
- [Requirements](#requirements)
- [Quick Start](#quick-start)
- [Installation](#installation)
- [Database Setup](#database-setup)
- [Environment Configuration](#environment-configuration)
- [First-Time Setup](#first-time-setup)
- [Existing Installation Upgrade](#existing-installation-upgrade)
- [Login](#login)
- [Project Structure](#project-structure)
- [Architecture](#architecture)
- [Feature Breakdown](#feature-breakdown)
- [Roles and Permissions](#roles-and-permissions)
- [Notifications](#notifications)
- [Reports and Analytics](#reports-and-analytics)
- [Development Milestones](#development-milestones)
- [Production Readiness](#production-readiness)
- [Database Backup](#database-backup)
- [Security](#security)
- [Project Status](#project-status)
- [Future Enhancements](#future-enhancements)
- [Operational Notes](#operational-notes)
- [License](#license)

---

## Overview

**PMS — Project Management System** is a PHP/MySQL application designed to manage projects, tasks, team members, permissions, attachments, activities, internal notifications, reports, and database backups.

The current implementation uses a modular PHP structure with **Thin Controllers + Repository Pattern**, environment-based configuration through `.env`, and XAMPP as the primary local deployment environment.

The project is organized around seven completed development phases, covering the core management functionality, repository refactoring, granular permissions, internal notifications, and deployment-oriented hardening.

---

## Key Features

| Area | Implemented Features |
|---|---|
| Authentication | Login and Administrator account creation during installation |
| Roles | `admin`, `manager`, `member` |
| Projects | Project CRUD |
| Tasks | Task CRUD, assignments, dependencies, My Tasks, `actual_hours` |
| Teams | Team CRUD and user management |
| Dashboard | Project and task overview |
| Activity Log | Application activity tracking |
| Attachments | Whitelisted extensions, 15 MB maximum, PHP execution protection |
| Soft Delete | Supported where implemented |
| Permissions | Granular role permissions and permission-aware UI actions |
| Notifications | Internal notifications and unread counter |
| Reporting | Gantt, Burndown, Team Performance |
| Export | CSV export |
| Backup | `mysqldump` + `gzip`, retaining the latest 14 backups |

---

## Technology Stack

### Application and Frontend

| Technology | Role |
|---|---|
| **PHP** | Backend and application logic |
| **HTML5** | Frontend structure |
| **CSS3** | UI styling |
| **JavaScript** | Client-side behavior |

### Database and Data Access

| Technology | Role |
|---|---|
| **MySQL / MariaDB** | Relational database |
| **PDO** | Database access layer |
| **Repository Pattern** | SQL/data-access isolation |

### Server and Environment

| Technology | Role |
|---|---|
| **Apache** | Web server |
| **XAMPP** | Local development environment |
| **`.env`** | Environment-specific configuration |
| **`.htaccess`** | Access and execution protection |

### Automation and Backup

| Technology | Role |
|---|---|
| **Bash** | Backup automation |
| **`mysqldump`** | Database dump generation |
| **`gzip`** | Backup compression |

> The source material does not specify a PHP version, Composer dependency set, JavaScript framework, CI/CD platform, Docker setup, or automated test framework; none are presented here as implemented project technologies.

---

## Requirements

For the documented local setup, you need:

- XAMPP with Apache and MySQL/MariaDB.
- A web browser.
- Access to `phpMyAdmin` for database initialization.
- Bash support when executing `scripts/backup.sh`.

---

## Quick Start

For a **new installation**:

```text
Copy project
    ↓
Start Apache + MySQL
    ↓
Open phpMyAdmin
    ↓
Run database/schema.sql
    ↓
Run 001_permissions.sql
    ↓
Run 002_notifications.sql
    ↓
Configure .env when required
    ↓
Open install.php
    ↓
Create Administrator account
    ↓
Login
```

### Default Local Paths

**Windows**

```text
C:\xampp\htdocs\pms
```

**macOS**

```text
/Applications/XAMPP/htdocs/pms
```

### Main URLs

| Purpose | URL |
|---|---|
| phpMyAdmin | `http://localhost/phpmyadmin` |
| Installer | `http://localhost/pms/install.php` |
| Login | `http://localhost/pms/modules/auth/login.php` |

---

## Installation

### 1. Copy the Project

Copy the complete `pms` directory into the XAMPP web root.

**Windows:**

```text
C:\xampp\htdocs\pms
```

**macOS:**

```text
/Applications/XAMPP/htdocs/pms
```

### 2. Start Apache and MySQL

Open the XAMPP Control Panel and start:

- Apache
- MySQL

### 3. Open phpMyAdmin

Navigate to:

```text
http://localhost/phpmyadmin
```

### 4. Initialize the Database

Execute:

```text
database/schema.sql
```

The schema creates:

```text
pms_db
```

### 5. Apply Initial Migrations

For the first installation, execute these files in order:

```text
database/migrations/001_permissions.sql
database/migrations/002_notifications.sql
```

### 6. Configure Environment Variables

For an environment that uses `.env`, copy:

```text
.env.example → .env
```

Then update the values as required.

### 7. Create the Administrator

Open:

```text
http://localhost/pms/install.php
```

The Administrator email address and password are defined during installation. There is no default account.

### 8. Login

Open:

```text
http://localhost/pms/modules/auth/login.php
```

### 9. Protect the Installer After Setup

After successful installation, it is preferable to **delete or rename `install.php`**.

---

## Database Setup

The database is initialized in two layers:

1. Base schema.
2. Incremental migrations.

### Base Schema

```text
database/schema.sql
```

Creates the `pms_db` database structure.

### Migrations

| Migration | Purpose |
|---|---|
| `001_permissions.sql` | Permissions and role-permission structures |
| `002_notifications.sql` | Internal notifications structures |

### Migration Rule

Migration order is significant.

For a new installation:

```text
schema.sql
→ 001_permissions.sql
→ 002_notifications.sql
```

For an existing installation, do **not** re-run `schema.sql` when it has already been applied. Apply only the migrations that have not yet been executed.

---

## Environment Configuration

The environment configuration uses the following files:

```text
.env.example
.env
config/env.php
config/database.php
```

### `.env.example`

Template containing the expected environment configuration.

### `.env`

Contains the actual environment-specific values and must not be committed to Git.

### `config/env.php`

Provides environment configuration loading for the application.

### `config/database.php`

Reads database settings from `.env` when available. When `.env` is not available, the project falls back to the documented XAMPP defaults.

### Default XAMPP Configuration

```env
DB_HOST=localhost
DB_NAME=pms_db
DB_USER=root
DB_PASS=
```

The `root` user with an empty password is a **local XAMPP development default only** and should not be treated as a production database security configuration.

### Application Environment

```env
APP_ENV=local
FORCE_HTTPS=0
```

For a production-oriented configuration:

```env
APP_ENV=production
FORCE_HTTPS=1
```

Behavior:

| Setting | Behavior |
|---|---|
| `APP_ENV=local` | Application errors are displayed |
| `APP_ENV=production` | Errors are logged to `storage/logs/error.log` |
| `FORCE_HTTPS=1` | Enables the `Secure` session-cookie attribute |

---

## First-Time Setup

Use this procedure only for a new installation:

```text
1. Copy pms into the XAMPP htdocs directory.
2. Start Apache and MySQL.
3. Open phpMyAdmin.
4. Execute database/schema.sql.
5. Execute 001_permissions.sql.
6. Execute 002_notifications.sql.
7. Configure .env if required.
8. Open /pms/install.php.
9. Create the Administrator account.
10. Open /pms/modules/auth/login.php.
```

There is **no predefined default username, email address, or password**.

---

## Existing Installation Upgrade

For an already initialized project:

```text
Do NOT re-run schema.sql
            ↓
Check which migrations are already applied
            ↓
Run only the new/unapplied migrations
```

This distinction is important for protecting existing database contents and avoiding unnecessary schema recreation.

---

## Login

The login endpoint documented for the current project is:

```text
http://localhost/pms/modules/auth/login.php
```

Administrator credentials are created through:

```text
http://localhost/pms/install.php
```

No default account is included.

---

## Project Structure

```text
pms/
├── .env.example
├── config/
│   ├── env.php
│   ├── database.php
│   └── app.php
├── includes/
│   ├── repositories/
│   ├── helpers.php
│   └── auth.php
├── modules/
│   ├── auth/
│   ├── dashboard/
│   ├── projects/
│   ├── tasks/
│   ├── team/
│   ├── reports/
│   ├── attachments/
│   ├── notifications/
│   └── settings/
├── assets/
│   └── css/
├── uploads/
├── storage/
│   ├── logs/
│   └── backups/
├── scripts/
│   └── backup.sh
├── database/
│   ├── schema.sql
│   └── migrations/
└── install.php
```

### Directory Responsibilities

| Path | Responsibility |
|---|---|
| `config/` | Application, environment, database, and bootstrap configuration |
| `includes/repositories/` | Repository classes for database/data access |
| `includes/helpers.php` | General application helpers |
| `includes/auth.php` | Authentication and authorization support |
| `modules/auth/` | Authentication flows |
| `modules/dashboard/` | Dashboard functionality |
| `modules/projects/` | Project management |
| `modules/tasks/` | Task management |
| `modules/team/` | Team and user management |
| `modules/reports/` | Reporting and analytics |
| `modules/attachments/` | Attachment management |
| `modules/notifications/` | Internal notifications |
| `modules/settings/` | Settings and permission management |
| `assets/css/` | Stylesheets |
| `uploads/` | Uploaded files |
| `storage/logs/` | Application logs |
| `storage/backups/` | Database backups |
| `scripts/backup.sh` | Database backup automation |
| `database/` | Schema and migration files |
| `install.php` | Initial Administrator/application installation |

---

## Architecture

The current implementation follows:

**Thin Controllers + Repository Pattern**

### High-Level Flow

```text
HTTP Request
     ↓
modules/*
     ↓
Application / Authentication Logic
     ↓
Repository Layer
     ↓
PDO
     ↓
MySQL / MariaDB
```

### Responsibilities

**`modules/*`**  
Handle module-specific request flow, user-facing functionality, and application operations.

**`repositories/*`**  
Encapsulate SQL and data-access operations so database queries remain isolated from thin request-handling code.

**`helpers.php`**  
Provides shared helper functions.

**`auth.php`**  
Handles authentication and authorization support.

**`config/app.php`**  
Provides the application bootstrap/configuration entry point.

### Implemented Repositories

```text
ProjectRepository
TaskRepository
UserRepository
ActivityRepository
AttachmentRepository
PermissionRepository
NotificationRepository
```

The repository refactoring also moves operations such as:

```text
logActivity()
attemptLogin()
```

toward the Repository layer while preserving user-facing behavior.

---

## Feature Breakdown

### Authentication and Users

- Authentication/login.
- Administrator account creation through `install.php`.
- `admin`, `manager`, and `member` roles.
- Permission-aware operations.

### Projects

- Project CRUD.
- Project-related management workflows.

### Tasks

- Task CRUD.
- Assignment and reassignment.
- Task dependencies.
- Soft Delete.
- `actual_hours`.
- My Tasks.

### Team

- Team/user CRUD.
- Role-based access through the permission system.

### Dashboard

Provides the documented project/task overview functionality.

### Activity Log

Records application activity through the activity logging functionality.

### Attachments

Attachment handling includes:

- Whitelisted file extensions.
- Maximum size of **15 MB**.
- Protection against PHP execution.

---

## Roles and Permissions

The granular permission system is based on:

```text
permissions
role_permissions
PermissionRepository
```

Permission checks are exposed through:

```php
userCan('key')
requirePermission('key')
```

### Role Model

| Role | Behavior |
|---|---|
| `admin` | Full permissions automatically |
| `manager` | Permissions can be customized |
| `member` | Permissions can be customized |

### Permission Administration

Permissions are managed from:

```text
modules/settings/permissions.php
```

The system supports:

- `settings.manage`.
- Permission customization for Managers and Members.
- Permission-aware UI actions.
- Automatic full access for Administrators.

Administrator permissions include a fail-safe behavior so the Administrator retains full access automatically.

---

## Notifications

The internal notification system uses:

```text
notifications
NotificationRepository
```

Implemented capabilities:

- Unread notification counter.
- Notifications page/center.
- Notification when a task is assigned or reassigned.
- Notification when someone other than the assigned person adds a comment.

### Deadline Reminders

Deadline reminder automation is **not implemented yet**.

The source material specifies that this functionality requires **Cron**.

---

## Reports and Analytics

The current reporting functionality includes:

### Gantt

Gantt reporting/visualization.

### Burndown

Burndown reporting.

### Team Performance

Team performance reporting.

### CSV Export

CSV export is implemented as the current lightweight export mechanism.

### PDF and Excel Export

Native PDF and Excel export are **not implemented**.

The source material identifies external libraries such as **TCPDF** or **PhpSpreadsheet** as possible requirements for those formats, but those libraries are not part of the documented current implementation.

---

## Development Milestones

### Phase 1 — Foundation

Implemented:

- Authentication.
- `admin` / `manager` / `member`.
- Project CRUD.
- Task CRUD.
- Team CRUD.
- Dashboard.
- Activity Log.

### Phase 2 — Functional Completion

Implemented:

- Attachments.
- Whitelisted extensions.
- Maximum upload size of 15 MB.
- Protection against PHP execution.
- Task Dependencies.
- Soft Delete.
- Prevention of deleting the last Administrator.
- Prevention of self-deletion.
- My Tasks.
- `actual_hours`.

### Phase 3 — Reports & Analytics

Implemented:

- Gantt.
- Burndown.
- Team Performance.
- CSV Export.

Native PDF/Excel export was not implemented and would require external libraries.

### Phase 4 — Repository Refactoring

Implemented:

- Repository Pattern.
- `ProjectRepository`.
- `TaskRepository`.
- `UserRepository`.
- `ActivityRepository`.
- `AttachmentRepository`.
- Thin Controllers.
- Repository-based handling for `logActivity()` and `attemptLogin()`.

The refactoring isolates SQL/data-access responsibilities without changing the documented user-facing behavior.

### Phase 5 — Granular Permissions

Implemented:

- `permissions`.
- `role_permissions`.
- `PermissionRepository`.
- `userCan()`.
- `requirePermission()`.
- Automatic full permissions for Administrators.
- Customizable Manager and Member permissions.
- `settings.manage`.
- Permission administration at `modules/settings/permissions.php`.
- UI actions that respond to the current user's permissions.

### Phase 6 — Internal Notifications

Implemented:

- Notification schema.
- `NotificationRepository`.
- Unread counter.
- Notifications page.
- Assignment/reassignment notifications.
- Comment notifications triggered when someone other than the assigned user comments.

Deadline reminders remain unimplemented and require Cron-based scheduling.

### Phase 7 — Production Readiness

Implemented:

- `.env.example`.
- `config/env.php`.
- Environment-based database configuration.
- `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS`.
- `HttpOnly`.
- `SameSite=Lax`.
- `Secure` when `FORCE_HTTPS=1`.
- `APP_ENV=production` error logging.
- `APP_ENV=local` error display.
- `storage` protection through `.htaccess`.
- `backup.sh` using `mysqldump` + `gzip`.
- Retention of the latest 14 backups.
- Cron-ready backup scheduling.
- Git exclusions for `.env`, uploads, logs, and backups.

---

## Production Readiness

The completed Phase 7 work provides a deployment-oriented foundation rather than a claim of universal production security.

### Environment Isolation

Use `.env` for deployment-specific configuration rather than hard-coding credentials in application files.

### Error Handling

```text
APP_ENV=local
→ display errors

APP_ENV=production
→ log errors to storage/logs/error.log
```

### Session Hardening

The documented session-cookie controls are:

```text
HttpOnly
SameSite=Lax
Secure when FORCE_HTTPS=1
```

### Storage Protection

`storage/` is protected using `.htaccess`.

### Backup Support

`backup.sh` provides compressed database backups with retention of the latest 14 copies.

---

## Database Backup

The backup script is:

```text
scripts/backup.sh
```

It uses:

```text
mysqldump
+
gzip
```

Backups are stored under:

```text
storage/backups/
```

The system retains the latest:

```text
14
```

backup copies.

The script is prepared for **Cron** scheduling, with scheduling instructions contained within the script.

> Do not add a custom Cron command to this README unless it is explicitly documented by the project itself.

---

## Security

The documented security controls include the following.

### Session Security

- `HttpOnly` session cookies.
- `SameSite=Lax`.
- `Secure` when `FORCE_HTTPS=1`.

### Upload Security

- Whitelisted extensions.
- Maximum upload size of 15 MB.
- Protection against PHP execution.

### Storage Security

The `storage/` directory is protected through `.htaccess` against direct access.

### Environment Security

`.env` is excluded from Git and should remain outside version control.

### Account Safety

The system prevents:

- Deleting the last Administrator.
- Users deleting themselves.

---

## Project Status

**All seven planned phases are complete.**

The project is documented as **ready for full internal operation**.

### Implemented

- Authentication.
- Project, task, and team management.
- Roles and granular permissions.
- Dashboard.
- Activity logging.
- Attachments.
- Task dependencies.
- Soft Delete.
- My Tasks.
- `actual_hours`.
- Gantt.
- Burndown.
- Team Performance.
- CSV Export.
- Repository Pattern.
- Internal notifications.
- Environment configuration.
- Session hardening.
- Error logging.
- Storage protection.
- Database backup and 14-backup retention.

### Not Yet Implemented

- Native PDF export.
- Native Excel export.
- Deadline reminder automation.
- Email notifications.
- Mobile application.

---

## Future Enhancements

The following are optional extensions and are not part of the original seven-phase roadmap:

- Email notifications.
- Native PDF export.
- Mobile app.
- Deadline reminders.

These should be treated as future extensions of the completed project rather than unfinished items in the seven original phases.

---

## Operational Notes

### Installer Protection

Use the installer only during initial setup:

```text
http://localhost/pms/install.php
```

After installation, delete or rename:

```text
install.php
```

### Login

```text
http://localhost/pms/modules/auth/login.php
```

### Local Database Defaults

```env
DB_HOST=localhost
DB_NAME=pms_db
DB_USER=root
DB_PASS=
```

These values represent the documented local XAMPP defaults and are not intended as a production credential policy.

### Migration Safety

For an existing installation:

```text
Do not re-run database/schema.sql
Run only unapplied migrations
```

### Version-Control Exclusions

The documented `.gitignore` behavior excludes:

```text
.env
uploads/
logs/
backups/
```

---
## 👥 فريق العمل والأدوار الهندسية (Team Members & Engineering Roles)

بناءً على خطة التوزيع التنفيذية المعتمدة للمشروع ومحاور هندسة البرمجيات، يضم الفريق 6 مهندسين موزعة مسؤولياتهم وفق مصفوفة التخصص والأدوار كالتالي:

| العضو | المهندس | الدور الهندسي والتخصصي (Role & Domain) | المسؤوليات والمهام الأساسية المعتمدة |
| :---: | :--- | :--- | :--- |
| **1** | **م. حامد** | **معمارية النظام والمشاريع والواجهة الرئيسية**<br>*(System Architect & UI Lead)* | • تجريد مستودع المشاريع (`ProjectRepositoryInterface`).<br>• تطوير لوحة التحكم والمؤشرات الحيوية (`Executive Dashboard`).<br>• محرك فلترة وبحث المشاريع المتقدم والحذف الناعم (`Soft Delete`).<br>• المسؤول الشريك في ضبط القواعد المعمارية في `AGENTS.md`. |
| **2** | **م. أسامة** | **إدارة المهام وتدفق العمل**<br>*(Tasks & Workflow Engineer)* | • تجريد كلاس ومستودع المهام (`TaskRepositoryInterface`).<br>• بناء منطق وحماية تبعيات المهام ومنع الحلقات الدائرية (`Dependencies`).<br>• واجهة "مهامي" السريعة بنمط Kanban والتبديل السريع للحالة.<br>• بناء نظام التحقق المركزي للمدخلات (`TaskValidator`). |
| **3** | **م. عبد الرحمن** | **المستخدمون والفرق والصلاحيات**<br>*(Users, Teams & RBAC Engineer)* | • تأسيس مستودع المستخدمين وتشفير الحسابات (`BCRYPT`).<br>• محرك الصلاحيات الحبيبية (`RBAC & Fine-Grained Permissions`).<br>• شاشة مصفوفة الصلاحيات للأدمن (`Permissions Matrix UI`).<br>• إدارة دورة حياة أعضاء الفريق وتجميد الحسابات (`Lockout Protection`). |
| **4** | **م. تامر** | **التقارير والتحليلات**<br>*(Reports & Analytics Engineer)* | • تطوير مخطط Gantt التفاعلي وجدولة المشاريع (`Interactive Gantt Chart`).<br>• محرك مخطط الاحتراق وسرعة الإنجاز (`Burndown Chart & Agile Velocity`).<br>• تقرير أداء الفريق وتحليل الساعات الفعلية مقابل المقدرة.<br>• وحدة تصدير التقارير بصيغة CSV والتنسيق الطباعي النظيف. |
| **5** | **م. رياض** | **الإشعارات والمرفقات الرقمية**<br>*(Notifications & Attachments Engineer)* | • بناء محرك وجرس الإشعارات الفوري وتحديث العداد (`Notification Engine`).<br>• ربط أحداث النظام بالتنبيهات التلقائية عند إسناد أو اكتمال المهام.<br>• تأمين نظام رفع وتخزين المرفقات وفحص الامتدادات وحماية `.htaccess`.<br>• وحدة التحميل الآمن للملفات وسجل تعقب الأنشطة (`Audit Log / Traits`). |
| **6** | **م. رؤوف**<br>`(profrauf)` | **مسؤول المستودع والاختبار والأمان والتكامل**<br>*(DevOps & Security Maintainer)* | • إدارة المستودع وحوكمة Git وسير العمل (CI/CD Pipelines & Linting).<br>• المسؤول الأساسي عن إعداد ملفات `AGENTS.md` و `CLAUDE.md` وسجل `AI_Log.md`.<br>• تأسيس بيئة الاختبارات الآلية بنمط PHPUnit و AAA Pattern.<br>• بوابات REST API وتأمينها برمز الحامل (`Bearer Token`).<br>• تعزيز أمان النظام والترويسات وسكربتات النسخ الاحتياطي والتعافي (`backup.sh`). |


---
## License

License information is not currently specified.
