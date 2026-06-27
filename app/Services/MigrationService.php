<?php

class MigrationService
{
    public static function run(): void
    {
        db()->exec(file_get_contents(BASE_PATH . '/database/schema.sql'));
        db()->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version TEXT PRIMARY KEY, applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');

        foreach (self::migrations() as $version => $migration) {
            if (self::isApplied($version)) {
                continue;
            }
            if ($version === '20260626_005_fix_answer_foreign_key') {
                $migration();
                db()->prepare('INSERT INTO schema_migrations(version) VALUES(?)')->execute([$version]);
                continue;
            }
            db()->beginTransaction();
            $migration();
            db()->prepare('INSERT INTO schema_migrations(version) VALUES(?)')->execute([$version]);
            db()->commit();
        }
    }

    private static function migrations(): array
    {
        return [
            '20260626_001_attempt_snapshots' => function (): void {
                self::addColumn('attempts', 'question_version', 'TEXT');
                self::addColumn('attempts', 'question_snapshot', 'TEXT');
                db()->exec('CREATE TABLE IF NOT EXISTS question_catalogs (version TEXT PRIMARY KEY, title TEXT, question_count INTEGER NOT NULL DEFAULT 0, imported_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
            },
            '20260626_002_question_metadata' => function (): void {
                self::addColumn('questions', 'competency', 'TEXT');
                self::addColumn('questions', 'difficulty', 'INTEGER');
                self::addColumn('questions', 'document_ref', 'TEXT');
            },
            '20260626_003_admin_security' => function (): void {
                self::addColumn('admins', 'failed_login_count', 'INTEGER NOT NULL DEFAULT 0');
                self::addColumn('admins', 'locked_until', 'TEXT');
                self::addColumn('admins', 'last_login_at', 'TEXT');
            },
            '20260626_004_answer_drafts' => function (): void {
                db()->exec('CREATE TABLE IF NOT EXISTS answer_drafts (
                  attempt_id INTEGER NOT NULL REFERENCES attempts(id) ON DELETE CASCADE,
                  question_id INTEGER NOT NULL,
                  given_answers TEXT NOT NULL,
                  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY(attempt_id, question_id)
                )');
            },
            '20260626_005_fix_answer_foreign_key' => function (): void {
                self::rebuildAnswersIfNeeded();
            },
            '20260626_006_admin_users' => function (): void {
                self::addColumn('admins', 'display_name', 'TEXT');
                self::addColumn('admins', 'active', 'INTEGER NOT NULL DEFAULT 1');
            },
            '20260626_007_attempt_review' => function (): void {
                self::addColumn('attempts', 'review_decision', "TEXT NOT NULL DEFAULT 'open'");
                self::addColumn('attempts', 'admin_note', 'TEXT');
                self::addColumn('attempts', 'reviewed_at', 'TEXT');
                self::addColumn('attempts', 'reviewed_by', 'INTEGER REFERENCES admins(id)');
            },
            '20260626_008_invitations' => function (): void {
                db()->exec("CREATE TABLE IF NOT EXISTS invitations (
                  id INTEGER PRIMARY KEY AUTOINCREMENT,
                  name TEXT NOT NULL,
                  email TEXT NOT NULL,
                  token TEXT NOT NULL UNIQUE,
                  status TEXT NOT NULL DEFAULT 'open',
                  expires_at TEXT,
                  used_at TEXT,
                  attempt_id INTEGER REFERENCES attempts(id) ON DELETE SET NULL,
                  created_by INTEGER REFERENCES admins(id),
                  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )");
            },
            '20260627_001_question_source_hints' => function (): void {
                self::addColumn('questions', 'source_hint', 'TEXT');
            },
        ];
    }

    private static function isApplied(string $version): bool
    {
        $stmt = db()->prepare('SELECT 1 FROM schema_migrations WHERE version=?');
        $stmt->execute([$version]);
        return (bool)$stmt->fetchColumn();
    }

    public static function addColumn(string $table, string $column, string $definition): void
    {
        if (self::columnExists($table, $column)) {
            return;
        }
        db()->exec("ALTER TABLE $table ADD COLUMN $column $definition");
    }

    public static function columnExists(string $table, string $column): bool
    {
        foreach (db()->query("PRAGMA table_info($table)") as $row) {
            if ($row['name'] === $column) {
                return true;
            }
        }
        return false;
    }

    private static function rebuildAnswersIfNeeded(): void
    {
        $needsRebuild = false;
        foreach (db()->query('PRAGMA foreign_key_list(answers)') as $row) {
            if ($row['from'] === 'question_id' && $row['table'] !== 'questions') {
                $needsRebuild = true;
            }
        }
        if (!$needsRebuild) {
            return;
        }

        db()->exec('PRAGMA foreign_keys = OFF');
        db()->exec('ALTER TABLE answers RENAME TO answers_broken_fk');
        db()->exec('CREATE TABLE answers (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          attempt_id INTEGER NOT NULL REFERENCES attempts(id) ON DELETE CASCADE,
          question_id INTEGER NOT NULL REFERENCES questions(id),
          given_answers TEXT NOT NULL,
          score REAL NOT NULL DEFAULT 0,
          max_score REAL NOT NULL DEFAULT 0,
          created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
          UNIQUE(attempt_id, question_id)
        )');
        db()->exec('INSERT OR IGNORE INTO answers(id,attempt_id,question_id,given_answers,score,max_score,created_at)
          SELECT id,attempt_id,question_id,given_answers,score,max_score,created_at
          FROM answers_broken_fk');
        db()->exec('DROP TABLE answers_broken_fk');
        db()->exec('PRAGMA foreign_keys = ON');
    }
}
