# AI Agents & Architectural Skills Specification (AGENTS.md)

This document establishes the system context, engineering boundaries, and technical skills required for AI Coding Agents (Gemini, Claude, GPT) operating on the Project Management System (PMS) repository.

---

## 1. Project Technology Stack & Environment
- **Core Runtime**: Native PHP 8.2 (No heavy framework, custom modular architecture).
- **Web Server**: Apache 2.4 (XAMPP environment).
- **Database Engine**: MySQL 8.0+ / MariaDB via PHP PDO driver.
- **Frontend Stack**: Semantic HTML5, CSS3, Vanilla JavaScript, Bootstrap 5.
- **Testing Framework**: PHPUnit 10+ with SQLite in-memory / mocks.
- **CI/CD Automation**: GitHub Actions (PHP Syntax Linting, automated test suite).

---

## 2. Core Agent Skills & Engineering Rules (Skills Map)

### Skill 1: Database Operations & Security (PDO Only)
- Never write raw, unescaped SQL queries.
- Mandatory use of Prepared Statements for all parameters:
  ```php
  $stmt = $pdo->prepare("SELECT id, title, status FROM tasks WHERE project_id = :project_id");
  $stmt->execute(['project_id' => $projectId]);
  $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);