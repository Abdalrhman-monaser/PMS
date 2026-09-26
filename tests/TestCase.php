<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use PDO;

abstract class TestCase extends BaseTestCase
{
    protected ?PDO $pdo = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
    }

    /**
     * إعداد قاعدة بيانات تجريبية في الذاكرة (SQLite In-Memory)
     */
    protected function setUpDatabase(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                role TEXT DEFAULT 'developer',
                status TEXT DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                deleted_at DATETIME NULL
            );

            CREATE TABLE IF NOT EXISTS projects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT UNIQUE NOT NULL,
                name TEXT NOT NULL,
                description TEXT NULL,
                status TEXT DEFAULT 'planning',
                priority TEXT DEFAULT 'medium',
                progress INTEGER DEFAULT 0,
                start_date DATE NULL,
                end_date DATE NULL,
                owner_id INTEGER NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                deleted_at DATETIME NULL,
                FOREIGN KEY (owner_id) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                description TEXT NULL,
                status TEXT DEFAULT 'todo',
                priority TEXT DEFAULT 'medium',
                assigned_to INTEGER NULL,
                due_date DATE NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                deleted_at DATETIME NULL,
                FOREIGN KEY (project_id) REFERENCES projects(id),
                FOREIGN KEY (assigned_to) REFERENCES users(id)
            );
        ");
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
        parent::tearDown();
    }
}