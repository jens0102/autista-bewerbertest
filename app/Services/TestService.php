<?php

class TestService
{
    public static function installIfNeeded(): void
    {
        if (class_exists('MigrationService')) {
            MigrationService::run();
        } else {
            db()->exec(file_get_contents(BASE_PATH . '/database/schema.sql'));
        }
        self::ensureQuestionTypeSchema();
        self::ensureAttemptSchema();
        self::ensureAnswerReferences();

        $count = db()->query('SELECT COUNT(*) c FROM admins')->fetch()['c'];
        if ((int)$count === 0) {
            db()->prepare('INSERT INTO admins(username,password_hash,must_change_password) VALUES(?,?,1)')
                ->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT)]);
        }

        if (setting('duration_minutes') === null) {
            set_setting('duration_minutes', '30');
        }
        if (setting('test_title') === null) {
            set_setting('test_title', 'AutiSta Bewerbertest');
        }
        if (setting('question_catalog_version') === null) {
            set_setting('question_catalog_version', 'initial');
        }
        if (setting('question_limit') === null) {
            set_setting('question_limit', '0');
        }
        if (setting('pass_percent') === null) {
            set_setting('pass_percent', '60');
        }
        if (setting('intro_text') === null) {
            set_setting('intro_text', 'Bitte geben Sie Ihren Namen und Ihre E-Mail-Adresse ein. Nach Start läuft ein sichtbarer Countdown. Eine erneute Teilnahme mit derselben E-Mail-Adresse ist nur nach erneuter Freigabe möglich.');
        }
        if (setting('privacy_text') === null) {
            set_setting('privacy_text', 'Ihre Angaben und Antworten werden zur Durchführung und Auswertung des Bewerbertests gespeichert.');
        }
        if (setting('retention_days') === null) {
            set_setting('retention_days', '0');
        }
        if (setting('admin_session_timeout_minutes') === null) {
            set_setting('admin_session_timeout_minutes', '30');
        }
        if (setting('debug_mode') === null) {
            set_setting('debug_mode', '0');
        }

        $qCount = db()->query('SELECT COUNT(*) c FROM questions')->fetch()['c'];
        if ((int)$qCount === 0 && file_exists(BASE_PATH . '/database/questions.json')) {
            $items = json_decode(file_get_contents(BASE_PATH . '/database/questions.json'), true);
            $stmt = db()->prepare('INSERT INTO questions(category,type,question,options,correct_answers,explanation,points,active,sort_order) VALUES(?,?,?,?,?,?,?,?,?)');
            foreach ($items as $q) {
                $stmt->execute([
                    $q['category'],
                    $q['type'],
                    $q['question'],
                    json_encode($q['options'], JSON_UNESCAPED_UNICODE),
                    json_encode($q['correct_answers'], JSON_UNESCAPED_UNICODE),
                    $q['explanation'],
                    $q['points'],
                    $q['active'],
                    $q['sort_order'],
                ]);
            }
        }
    }

    private static function ensureAttemptSchema(): void
    {
        foreach ([
            'display_name' => 'TEXT',
            'active' => 'INTEGER NOT NULL DEFAULT 1',
        ] as $column => $definition) {
            if (!self::columnExists('admins', $column)) {
                db()->exec("ALTER TABLE admins ADD COLUMN $column $definition");
            }
        }

        foreach ([
            'competency' => 'TEXT',
            'difficulty' => 'INTEGER',
            'document_ref' => 'TEXT',
            'source_hint' => 'TEXT',
        ] as $column => $definition) {
            if (!self::columnExists('questions', $column)) {
                db()->exec("ALTER TABLE questions ADD COLUMN $column $definition");
            }
        }

        foreach ([
            'question_version' => 'TEXT',
            'question_snapshot' => 'TEXT',
            'review_decision' => "TEXT NOT NULL DEFAULT 'open'",
            'admin_note' => 'TEXT',
            'reviewed_at' => 'TEXT',
            'reviewed_by' => 'INTEGER REFERENCES admins(id)',
        ] as $column => $definition) {
            if (!self::columnExists('attempts', $column)) {
                db()->exec("ALTER TABLE attempts ADD COLUMN $column $definition");
            }
        }
        db()->exec('CREATE TABLE IF NOT EXISTS question_catalogs (version TEXT PRIMARY KEY, title TEXT, question_count INTEGER NOT NULL DEFAULT 0, imported_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
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
    }

    private static function ensureAnswerReferences(): void
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
        db()->beginTransaction();
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
        db()->commit();
        db()->exec('PRAGMA foreign_keys = ON');
    }

    private static function columnExists(string $table, string $column): bool
    {
        $stmt = db()->query("PRAGMA table_info($table)");
        foreach ($stmt->fetchAll() as $row) {
            if ($row['name'] === $column) {
                return true;
            }
        }
        return false;
    }

    private static function ensureQuestionTypeSchema(): void
    {
        try {
            db()->exec("INSERT INTO questions(category,type,question,options,correct_answers,explanation,points,active,sort_order) VALUES('__migration_check__','ordering','__migration_check__','[]','[]','',0,0,-999999)");
            db()->exec("DELETE FROM questions WHERE category='__migration_check__' AND question='__migration_check__'");
        } catch (PDOException $e) {
            db()->exec('PRAGMA foreign_keys = OFF');
            db()->exec('ALTER TABLE questions RENAME TO questions_old');
            db()->exec(file_get_contents(BASE_PATH . '/database/schema.sql'));
            db()->exec('INSERT INTO questions(id,category,type,question,options,correct_answers,explanation,points,active,sort_order,created_at,updated_at) SELECT id,category,type,question,options,correct_answers,explanation,points,active,sort_order,created_at,updated_at FROM questions_old');
            db()->exec('DROP TABLE questions_old');
            db()->exec('PRAGMA foreign_keys = ON');
        }
    }

    public static function activeQuestions(): array
    {
        $questions = db()->query('SELECT * FROM questions WHERE active=1 ORDER BY sort_order,id')->fetchAll();
        $limit = (int)setting('question_limit', '0');
        if ($limit > 0 && $limit < count($questions)) {
            $seed = random_int(PHP_INT_MIN, PHP_INT_MAX);
            usort($questions, fn($a, $b) => crc32($seed . '|' . $a['id']) <=> crc32($seed . '|' . $b['id']));
            $questions = array_slice($questions, 0, $limit);
            usort($questions, fn($a, $b) => [$a['sort_order'], $a['id']] <=> [$b['sort_order'], $b['id']]);
        }
        return $questions;
    }

    public static function questionsByIds(array $ids): array
    {
        if (!$ids) {
            return [];
        }

        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare("SELECT * FROM questions WHERE id IN ($ph)");
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();
        $map = [];

        foreach ($rows as $row) {
            $map[$row['id']] = $row;
        }

        return array_values(array_filter(array_map(fn($id) => $map[$id] ?? null, $ids)));
    }

    public static function questionsForAttempt(array $attempt): array
    {
        $snapshot = json_decode($attempt['question_snapshot'] ?? '', true);
        if (is_array($snapshot) && $snapshot) {
            return $snapshot;
        }
        return self::questionsByIds(json_decode($attempt['question_ids'], true) ?: []);
    }

    public static function createAttempt(string $name, string $email): int
    {
        self::expireOpenAttempts();
        $questions = self::activeQuestions();
        if (!$questions) {
            throw new RuntimeException('Derzeit sind keine Fragen aktiviert.');
        }

        $ids = array_column($questions, 'id');
        $duration = max(1, (int)setting('duration_minutes', '30')) * 60;
        $version = setting('question_catalog_version', 'initial');
        db()->prepare('INSERT INTO attempts(name,email,duration_seconds,question_ids,question_version,question_snapshot) VALUES(?,?,?,?,?,?)')
            ->execute([$name, $email, $duration, json_encode($ids), $version, json_encode($questions, JSON_UNESCAPED_UNICODE)]);
        return (int)db()->lastInsertId();
    }

    public static function createInvitation(string $name, string $email, ?string $expiresAt, ?int $adminId): array
    {
        $token = bin2hex(random_bytes(24));
        db()->prepare('INSERT INTO invitations(name,email,token,expires_at,created_by) VALUES(?,?,?,?,?)')
            ->execute([$name, strtolower(trim($email)), $token, $expiresAt ?: null, $adminId]);
        $stmt = db()->prepare('SELECT * FROM invitations WHERE token=?');
        $stmt->execute([$token]);
        return $stmt->fetch();
    }

    public static function invitationByToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        $stmt = db()->prepare('SELECT * FROM invitations WHERE token=?');
        $stmt->execute([$token]);
        $invitation = $stmt->fetch();
        if (!$invitation) {
            return null;
        }
        if ($invitation['status'] !== 'open') {
            return null;
        }
        if (!empty($invitation['expires_at']) && strtotime($invitation['expires_at'] . ' UTC') < time()) {
            db()->prepare("UPDATE invitations SET status='expired' WHERE id=?")->execute([$invitation['id']]);
            return null;
        }
        return $invitation;
    }

    public static function markInvitationUsed(string $token, int $attemptId): void
    {
        db()->prepare("UPDATE invitations SET status='used', used_at=CURRENT_TIMESTAMP, attempt_id=? WHERE token=?")
            ->execute([$attemptId, $token]);
    }

    public static function expireOpenAttempts(): void
    {
        db()->exec("UPDATE attempts SET status='expired' WHERE status='started' AND datetime(started_at, '+' || duration_seconds || ' seconds') < CURRENT_TIMESTAMP");
    }

    public static function cleanupOldAttempts(): int
    {
        $days = (int)setting('retention_days', '0');
        if ($days <= 0) {
            return 0;
        }
        $stmt = db()->prepare("DELETE FROM attempts WHERE status IN ('submitted','expired') AND COALESCE(submitted_at, started_at) < datetime('now', ?)");
        $stmt->execute(['-' . $days . ' days']);
        return $stmt->rowCount();
    }

    public static function resetAttempt(int $attemptId): void
    {
        $stmt = db()->prepare('SELECT email FROM attempts WHERE id=?');
        $stmt->execute([$attemptId]);
        $attempt = $stmt->fetch();
        if (!$attempt) {
            return;
        }
        db()->prepare('DELETE FROM attempts WHERE id=?')->execute([$attemptId]);
    }

    public static function releaseEmail(string $email): void
    {
        db()->prepare('DELETE FROM attempts WHERE email=?')->execute([strtolower(trim($email))]);
    }

    public static function finalizeAttempt(int $attemptId, array $post): void
    {
        $stmt = db()->prepare('SELECT * FROM attempts WHERE id=?');
        $stmt->execute([$attemptId]);
        $attempt = $stmt->fetch();

        if (!$attempt || $attempt['status'] === 'submitted') {
            return;
        }

        $ids = json_decode($attempt['question_ids'], true) ?: [];
        $questions = self::questionsForAttempt($attempt);
        $total = 0;
        $max = 0;

        $ins = db()->prepare('INSERT INTO answers(attempt_id,question_id,given_answers,score,max_score) VALUES(?,?,?,?,?) ON CONFLICT(attempt_id,question_id) DO UPDATE SET given_answers=excluded.given_answers, score=excluded.score, max_score=excluded.max_score');
        foreach ($questions as $q) {
            $given = self::normalizeGivenAnswers($q, $post['q'][$q['id']] ?? []);
            $score = score_question($q, $given);
            $total += $score;
            $max += (float)$q['points'];
            $ins->execute([$attemptId, $q['id'], json_encode($given, JSON_UNESCAPED_UNICODE), $score, (float)$q['points']]);
        }

        db()->prepare("UPDATE attempts SET submitted_at=CURRENT_TIMESTAMP,status='submitted',total_points=?,max_points=? WHERE id=?")
            ->execute([$total, $max, $attemptId]);
        db()->prepare('DELETE FROM answer_drafts WHERE attempt_id=?')->execute([$attemptId]);
    }

    public static function saveDraft(int $attemptId, array $post): void
    {
        $stmt = db()->prepare('SELECT * FROM attempts WHERE id=?');
        $stmt->execute([$attemptId]);
        $attempt = $stmt->fetch();
        if (!$attempt || $attempt['status'] !== 'started') {
            return;
        }

        $questions = self::questionsForAttempt($attempt);
        $upsert = db()->prepare('INSERT INTO answer_drafts(attempt_id,question_id,given_answers,updated_at) VALUES(?,?,?,CURRENT_TIMESTAMP) ON CONFLICT(attempt_id,question_id) DO UPDATE SET given_answers=excluded.given_answers, updated_at=CURRENT_TIMESTAMP');
        foreach ($questions as $q) {
            if (!isset($post['q'][$q['id']])) {
                continue;
            }
            $given = self::normalizeGivenAnswers($q, $post['q'][$q['id']]);
            $upsert->execute([$attemptId, $q['id'], json_encode($given, JSON_UNESCAPED_UNICODE)]);
        }
    }

    public static function draftAnswers(int $attemptId): array
    {
        $stmt = db()->prepare('SELECT question_id,given_answers FROM answer_drafts WHERE attempt_id=?');
        $stmt->execute([$attemptId]);
        $drafts = [];
        foreach ($stmt->fetchAll() as $row) {
            $drafts[$row['question_id']] = json_decode($row['given_answers'], true) ?: [];
        }
        return $drafts;
    }

    private static function normalizeGivenAnswers(array $q, mixed $given): array
    {
        if ($q['type'] === 'ordering' || $q['type'] === 'matching') {
            $given = is_array($given) ? array_filter($given, fn($v) => $v !== '') : [];
            ksort($given);
            return $given;
        }
        if (!is_array($given)) {
            $given = [$given];
        }
        return array_values(array_filter($given, fn($v) => $v !== ''));
    }

    public static function importCatalog(string $source): int
    {
        $data = json_decode(file_get_contents($source), true);
        $items = $data['questions'] ?? $data;
        if (!is_array($items)) {
            throw new RuntimeException('Ungültiger Fragenkatalog.');
        }

        $normalized = [];
        foreach ($items as $index => $item) {
            $normalized[] = self::normalizeImportedQuestion($item, $index + 1);
        }

        file_put_contents(BASE_PATH . '/database/questions.json', json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);

        $version = date('Ymd-His');
        set_setting('question_catalog_version', $version);
        db()->prepare('INSERT INTO question_catalogs(version,title,question_count) VALUES(?,?,?)')
            ->execute([$version, $data['meta']['title'] ?? 'Import ' . $version, count($normalized)]);

        db()->beginTransaction();
        $stmt = db()->prepare('INSERT INTO questions(id,category,competency,difficulty,document_ref,source_hint,type,question,options,correct_answers,explanation,points,active,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON CONFLICT(id) DO UPDATE SET category=excluded.category,competency=excluded.competency,difficulty=excluded.difficulty,document_ref=excluded.document_ref,source_hint=excluded.source_hint,type=excluded.type,question=excluded.question,options=excluded.options,correct_answers=excluded.correct_answers,explanation=excluded.explanation,points=excluded.points,active=excluded.active,sort_order=excluded.sort_order,updated_at=CURRENT_TIMESTAMP');
        foreach ($normalized as $q) {
            $stmt->execute([
                $q['id'],
                $q['category'],
                $q['competency'],
                $q['difficulty'],
                $q['document_ref'],
                $q['source_hint'],
                $q['type'],
                $q['question'],
                json_encode($q['options'], JSON_UNESCAPED_UNICODE),
                json_encode($q['correct_answers'], JSON_UNESCAPED_UNICODE),
                $q['explanation'],
                $q['points'],
                $q['active'],
                $q['sort_order'],
            ]);
        }
        $ids = array_column($normalized, 'id');
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $deactivate = db()->prepare("UPDATE questions SET active=0 WHERE id NOT IN ($placeholders)");
            $deactivate->execute($ids);
        }
        db()->commit();

        return count($normalized);
    }

    private static function normalizeImportedQuestion(array $item, int $fallbackOrder): array
    {
        $typeMap = [
            'single_choice' => 'single',
            'multiple_choice' => 'multiple',
            'true_false' => 'true_false',
            'ordering' => 'ordering',
            'matching' => 'matching',
        ];
        $type = $typeMap[$item['type'] ?? ''] ?? ($item['type'] ?? 'single');
        $sortOrder = (int)($item['id'] ?? $fallbackOrder);

        if ($type === 'matching') {
            $left = [];
            foreach (($item['left'] ?? []) as $idx => $text) {
                $left[] = ['key' => 'L' . ($idx + 1), 'text' => $text];
            }
            $answers = [];
            foreach (($item['answers'] ?? []) as $idx => $text) {
                $answers[] = ['key' => self::answerKey($idx + 1), 'text' => $text];
            }
            $correct = [];
            foreach (($item['correct'] ?? []) as $leftIndex => $answerIndex) {
                $correct['L' . (int)$leftIndex] = self::answerKey((int)$answerIndex);
            }
            $options = ['left' => $left, 'answers' => $answers];
        } else {
            $options = [];
            foreach (($item['answers'] ?? $item['options'] ?? []) as $idx => $answer) {
                $options[] = is_array($answer) && isset($answer['key'])
                    ? $answer
                    : ['key' => self::answerKey($idx + 1), 'text' => (string)$answer];
            }
            if ($type === 'ordering') {
                $correct = [];
                foreach (($item['correct'] ?? []) as $position => $answerIndex) {
                    $correct[(string)$position] = self::answerKey((int)$answerIndex);
                }
            } elseif (is_array($item['correct'] ?? null)) {
                $correct = array_map(fn($idx) => self::answerKey((int)$idx), $item['correct']);
            } else {
                $correct = [self::answerKey((int)($item['correct'] ?? 1))];
            }
        }

        return [
            'id' => $sortOrder,
            'category' => $item['category'] ?? 'Allgemein',
            'competency' => $item['competency'] ?? '',
            'difficulty' => (int)($item['difficulty'] ?? 0),
            'document_ref' => trim(($item['document'] ?? '') . (isset($item['chapter']) ? ' / ' . $item['chapter'] : '') . (isset($item['page']) ? ' / S. ' . $item['page'] : '')),
            'source_hint' => trim((string)($item['source_hint'] ?? '')),
            'type' => $type,
            'question' => $item['question'] ?? '',
            'options' => $options,
            'correct_answers' => $correct,
            'explanation' => $item['explanation'] ?? ($item['rationale'] ?? ''),
            'points' => (float)($item['points'] ?? 1),
            'active' => (int)($item['active'] ?? 1),
            'sort_order' => (int)($item['sort_order'] ?? $sortOrder),
        ];
    }

    private static function answerKey(int $index): string
    {
        $letters = range('A', 'Z');
        return $letters[$index - 1] ?? (string)$index;
    }
}
