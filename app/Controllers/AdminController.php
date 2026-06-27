<?php

class AdminController
{
    public function login(): void
    {
        $error = '';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $stmt = db()->prepare('SELECT * FROM admins WHERE username=?');
            $stmt->execute([$_POST['username'] ?? '']);
            $admin = $stmt->fetch();

            if ($admin && isset($admin['active']) && (int)$admin['active'] !== 1) {
                app_log('Inactive admin login denied', ['username' => $admin['username']]);
                $error = 'Dieses Admin-Konto ist deaktiviert.';
                render_page('Admin Login', 'admin/login', compact('error'));
                return;
            }

            if ($admin && !empty($admin['locked_until']) && strtotime($admin['locked_until'] . ' UTC') > time()) {
                app_log('Admin login locked', ['username' => $admin['username']]);
                $error = 'Login vorübergehend gesperrt. Bitte später erneut versuchen.';
                render_page('Admin Login', 'admin/login', compact('error'));
                return;
            }

            if ($admin && password_verify($_POST['password'] ?? '', $admin['password_hash'])) {
                db()->prepare('UPDATE admins SET failed_login_count=0, locked_until=NULL, last_login_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$admin['id']]);
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_last_activity'] = time();
                $_SESSION['admin_must_change_password'] = !empty($admin['must_change_password']);
                redirect('/admin');
            }

            if ($admin) {
                $failed = ((int)$admin['failed_login_count']) + 1;
                $lockedUntil = $failed >= 5 ? date('Y-m-d H:i:s', time() + 15 * 60) : null;
                db()->prepare('UPDATE admins SET failed_login_count=?, locked_until=? WHERE id=?')->execute([$failed, $lockedUntil, $admin['id']]);
                app_log('Admin login failed', ['username' => $admin['username'], 'failed' => $failed]);
            }
            $error = 'Login fehlgeschlagen.';
        }

        render_page('Admin Login', 'admin/login', compact('error'));
    }

    public function logout(): void
    {
        session_destroy();
        redirect('/');
    }

    public function dashboard(): void
    {
        require_admin();
        TestService::expireOpenAttempts();

        $stats = [
            'q' => db()->query('SELECT COUNT(*) c FROM questions')->fetch()['c'],
            'active' => db()->query('SELECT COUNT(*) c FROM questions WHERE active=1')->fetch()['c'],
            'attempts' => db()->query('SELECT COUNT(*) c FROM attempts')->fetch()['c'],
            'submitted' => db()->query("SELECT COUNT(*) c FROM attempts WHERE status='submitted'")->fetch()['c'],
            'expired' => db()->query("SELECT COUNT(*) c FROM attempts WHERE status='expired'")->fetch()['c'],
        ];
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $bewerberLink = $scheme . '://' . $_SERVER['HTTP_HOST'] . url('/');

        render_page('Admin Dashboard', 'admin/dashboard', compact('stats', 'bewerberLink'), true);
    }

    public function questions(): void
    {
        require_admin();
        $message = '';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (isset($_POST['toggle'])) {
                db()->exec('UPDATE questions SET active=0');
                $stmt = db()->prepare('UPDATE questions SET active=1 WHERE id=?');
                foreach (array_keys($_POST['active'] ?? []) as $id) {
                    $stmt->execute([(int)$id]);
                }
                redirect('/admin/questions');
            }

            if (isset($_POST['import_catalog'])) {
                if (!isset($_FILES['catalog']) || ($_FILES['catalog']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    $message = 'Bitte eine gültige JSON-Datei auswählen.';
                } else {
                    try {
                        $count = TestService::importCatalog($_FILES['catalog']['tmp_name']);
                        $message = $count . ' Fragen wurden importiert.';
                    } catch (Throwable $e) {
                        $message = 'Import fehlgeschlagen: ' . $e->getMessage();
                    }
                }
            }
        }

        $rows = db()->query('SELECT * FROM questions ORDER BY sort_order,id')->fetchAll();
        render_page('Fragenverwaltung', 'admin/questions', compact('rows', 'message'), true);
    }

    public function questionEdit(): void
    {
        require_admin();
        $id = (int)($_GET['id'] ?? 0);
        $question = null;

        if ($id) {
            $stmt = db()->prepare('SELECT * FROM questions WHERE id=?');
            $stmt->execute([$id]);
            $question = $stmt->fetch();
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            [$options, $correct] = $this->questionPayloadFromPost($_POST);
            $params = [
                $_POST['category'],
                $_POST['competency'] ?? '',
                (int)($_POST['difficulty'] ?? 0),
                $_POST['document_ref'] ?? '',
                trim($_POST['source_hint'] ?? ''),
                $_POST['type'],
                $_POST['question'],
                json_encode($options, JSON_UNESCAPED_UNICODE),
                json_encode($correct, JSON_UNESCAPED_UNICODE),
                $_POST['explanation'],
                (float)$_POST['points'],
                isset($_POST['active']) ? 1 : 0,
                (int)$_POST['sort_order'],
            ];

            if ($id) {
                $params[] = $id;
                db()->prepare('UPDATE questions SET category=?,competency=?,difficulty=?,document_ref=?,source_hint=?,type=?,question=?,options=?,correct_answers=?,explanation=?,points=?,active=?,sort_order=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')
                    ->execute($params);
            } else {
                db()->prepare('INSERT INTO questions(category,competency,difficulty,document_ref,source_hint,type,question,options,correct_answers,explanation,points,active,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute($params);
            }

            set_setting('question_catalog_version', date('Ymd-His'));
            redirect('/admin/questions');
        }

        render_page('Frage bearbeiten', 'admin/question-form', compact('question'), true);
    }

    private function questionPayloadFromPost(array $post): array
    {
        if (($post['editor_mode'] ?? '') !== 'structured') {
            return $this->legacyQuestionPayloadFromPost($post);
        }

        $type = $post['type'] ?? 'single';
        if (in_array($type, ['single', 'multiple', 'true_false'], true)) {
            $options = [];
            $correct = [];
            $selectedSingle = isset($post['choice_correct_single']) ? (string)$post['choice_correct_single'] : '';
            $selectedMulti = array_flip(array_map('strval', $post['choice_correct_multi'] ?? []));
            $texts = $post['choice_text'] ?? [];
            $keys = $post['choice_key'] ?? [];

            foreach ($texts as $index => $text) {
                $text = trim((string)$text);
                $key = strtoupper(trim((string)($keys[$index] ?? '')));
                if ($text === '' && $key === '') {
                    continue;
                }
                $key = $key !== '' ? $key : $this->optionKey(count($options));
                $options[] = ['key' => $key, 'text' => $text];

                if (($type === 'multiple' && isset($selectedMulti[(string)$index]))
                    || ($type !== 'multiple' && $selectedSingle === (string)$index)) {
                    $correct[] = $key;
                }
            }

            return [$options, array_values(array_unique($correct))];
        }

        if ($type === 'ordering') {
            $options = [];
            $correct = [];
            $texts = $post['ordering_text'] ?? [];
            $keys = $post['ordering_key'] ?? [];

            foreach ($texts as $index => $text) {
                $text = trim((string)$text);
                $key = strtoupper(trim((string)($keys[$index] ?? '')));
                if ($text === '' && $key === '') {
                    continue;
                }
                $key = $key !== '' ? $key : $this->optionKey(count($options));
                $options[] = ['key' => $key, 'text' => $text];
                $correct[(string)(count($options) - 1)] = $key;
            }

            return [$options, $correct];
        }

        if ($type === 'matching') {
            $left = [];
            $answers = [];
            $correct = [];
            $leftTexts = $post['match_left_text'] ?? [];
            $answerTexts = $post['match_answer_text'] ?? [];

            foreach ($leftTexts as $index => $leftText) {
                $leftText = trim((string)$leftText);
                $answerText = trim((string)($answerTexts[$index] ?? ''));
                if ($leftText === '' && $answerText === '') {
                    continue;
                }

                $leftKey = 'L' . (count($left) + 1);
                $answerKey = $this->optionKey(count($answers));
                $left[] = ['key' => $leftKey, 'text' => $leftText];
                $answers[] = ['key' => $answerKey, 'text' => $answerText];
                $correct[$leftKey] = $answerKey;
            }

            return [['left' => $left, 'answers' => $answers], $correct];
        }

        return $this->legacyQuestionPayloadFromPost($post);
    }

    private function legacyQuestionPayloadFromPost(array $post): array
    {
        $optionsInput = trim((string)($post['options'] ?? ''));
        $decodedOptions = json_decode($optionsInput, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedOptions)) {
            $options = $decodedOptions;
        } else {
            $options = [];
            foreach (preg_split('/\R/', $optionsInput) as $line) {
                if (trim($line) === '') {
                    continue;
                }
                [$key, $text] = array_pad(explode('|', $line, 2), 2, '');
                $options[] = ['key' => trim($key), 'text' => trim($text)];
            }
        }

        $correctInput = trim((string)($post['correct_answers'] ?? ''));
        $decodedCorrect = json_decode($correctInput, true);
        $correct = (json_last_error() === JSON_ERROR_NONE)
            ? $decodedCorrect
            : array_values(array_filter(array_map('trim', explode(',', $correctInput))));

        return [$options, $correct];
    }

    private function optionKey(int $index): string
    {
        $letters = range('A', 'Z');
        if ($index < count($letters)) {
            return $letters[$index];
        }

        return 'A' . ($index - count($letters) + 1);
    }

    public function attempts(): void
    {
        require_admin();
        TestService::expireOpenAttempts();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $id = (int)($_POST['id'] ?? 0);
            if (isset($_POST['reset']) && $id) {
                TestService::resetAttempt($id);
                redirect('/admin/attempts');
            }
            if (isset($_POST['delete']) && $id) {
                db()->prepare('DELETE FROM attempts WHERE id=?')->execute([$id]);
                redirect('/admin/attempts');
            }
            if (isset($_POST['release_email'])) {
                TestService::releaseEmail($_POST['email'] ?? '');
                redirect('/admin/attempts');
            }
            if (isset($_POST['cleanup'])) {
                TestService::cleanupOldAttempts();
                redirect('/admin/attempts');
            }
        }

        [$where, $params] = $this->attemptFilter();
        $sql = 'SELECT * FROM attempts ' . $where . ' ORDER BY started_at DESC';

        if (isset($_GET['csv'])) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="bewerbertest.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Email', 'Status', 'Entscheidung', 'Punkte', 'Max', 'Prozent', 'Start', 'Abgabe', 'Fragenversion'], ';');
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll() as $attempt) {
                fputcsv($out, [
                    $attempt['name'],
                    $attempt['email'],
                    $attempt['status'],
                    $attempt['review_decision'] ?? 'open',
                    $attempt['total_points'],
                    $attempt['max_points'],
                    $attempt['max_points'] ? round($attempt['total_points'] / $attempt['max_points'] * 100, 1) : 0,
                    $attempt['started_at'],
                    $attempt['submitted_at'],
                    $attempt['question_version'] ?? '',
                ], ';');
            }
            exit;
        }

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        render_page('Auswertung', 'admin/attempts', [
            'rows' => $rows,
            'filters' => $_GET,
            'passPercent' => (float)setting('pass_percent', '60'),
            'decisions' => $this->reviewDecisions(),
        ], true);
    }

    public function attempt(): void
    {
        require_admin();
        $id = (int)($_GET['id'] ?? 0);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $decision = $_POST['review_decision'] ?? 'open';
            if (!array_key_exists($decision, $this->reviewDecisions())) {
                $decision = 'open';
            }
            db()->prepare('UPDATE attempts SET review_decision=?, admin_note=?, reviewed_at=CURRENT_TIMESTAMP, reviewed_by=? WHERE id=?')
                ->execute([$decision, trim($_POST['admin_note'] ?? ''), $_SESSION['admin_id'] ?? null, $id]);
            redirect('/admin/attempt?id=' . $id);
        }

        $stmt = db()->prepare('SELECT * FROM attempts WHERE id=?');
        $stmt->execute([$id]);
        $attempt = $stmt->fetch();

        if (!$attempt) {
            http_response_code(404);
            exit('Nicht gefunden');
        }

        $questions = TestService::questionsForAttempt($attempt);
        $answers = [];
        $answerStmt = db()->prepare('SELECT * FROM answers WHERE attempt_id=?');
        $answerStmt->execute([$id]);

        foreach ($answerStmt->fetchAll() as $row) {
            $answers[$row['question_id']] = $row;
        }

        $byCategory = [];
        foreach ($questions as $q) {
            $answer = $answers[$q['id']] ?? null;
            $byCategory[$q['category']]['s'] = ($byCategory[$q['category']]['s'] ?? 0) + ($answer['score'] ?? 0);
            $byCategory[$q['category']]['m'] = ($byCategory[$q['category']]['m'] ?? 0) + (float)$q['points'];
        }

        render_page('Detailauswertung', 'admin/attempt-detail', [
            'attempt' => $attempt,
            'questions' => $questions,
            'answers' => $answers,
            'byCategory' => $byCategory,
            'passPercent' => (float)setting('pass_percent', '60'),
            'decisions' => $this->reviewDecisions(),
        ], true);
    }

    public function settings(): void
    {
        require_admin();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            set_setting('duration_minutes', (string)max(1, (int)$_POST['duration_minutes']));
            set_setting('test_title', trim($_POST['test_title']));
            set_setting('intro_text', trim($_POST['intro_text'] ?? ''));
            set_setting('privacy_text', trim($_POST['privacy_text'] ?? ''));
            set_setting('question_limit', (string)max(0, (int)$_POST['question_limit']));
            set_setting('pass_percent', (string)max(0, min(100, (float)$_POST['pass_percent'])));
            set_setting('retention_days', (string)max(0, (int)$_POST['retention_days']));
            set_setting('admin_session_timeout_minutes', (string)max(1, (int)$_POST['admin_session_timeout_minutes']));
            set_setting('debug_mode', isset($_POST['debug_mode']) ? '1' : '0');

            if (!empty($_POST['new_password'])) {
                db()->prepare('UPDATE admins SET password_hash=?, must_change_password=0 WHERE id=?')
                    ->execute([password_hash($_POST['new_password'], PASSWORD_DEFAULT), $_SESSION['admin_id']]);
                $_SESSION['admin_must_change_password'] = false;
            }

            if (isset($_POST['delete_attempts'])) {
                db()->exec('DELETE FROM attempts');
            }

            redirect('/admin/settings');
        }

        render_page('Einstellungen', 'admin/settings', [
            'testTitle' => setting('test_title', 'AutiSta Bewerbertest'),
            'durationMinutes' => setting('duration_minutes', '30'),
            'introText' => setting('intro_text', ''),
            'privacyText' => setting('privacy_text', ''),
            'questionLimit' => setting('question_limit', '0'),
            'passPercent' => setting('pass_percent', '60'),
            'retentionDays' => setting('retention_days', '0'),
            'questionVersion' => setting('question_catalog_version', 'initial'),
            'adminSessionTimeoutMinutes' => setting('admin_session_timeout_minutes', '30'),
            'debugMode' => setting('debug_mode', '0'),
        ], true);
    }

    public function users(): void
    {
        require_admin();
        $rows = db()->query('SELECT * FROM admins ORDER BY username')->fetchAll();
        render_page('Admin-Benutzer', 'admin/users', [
            'rows' => $rows,
            'currentAdminId' => (int)($_SESSION['admin_id'] ?? 0),
        ], true);
    }

    public function userEdit(): void
    {
        require_admin();
        $id = (int)($_GET['id'] ?? 0);
        $user = null;
        $error = '';

        if ($id) {
            $stmt = db()->prepare('SELECT * FROM admins WHERE id=?');
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            if (!$user) {
                http_response_code(404);
                exit('Nicht gefunden');
            }
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $displayName = trim($_POST['display_name'] ?? '');
            $password = (string)($_POST['password'] ?? '');
            $active = isset($_POST['active']) ? 1 : 0;
            $mustChangePassword = isset($_POST['must_change_password']) ? 1 : 0;

            if ($username === '') {
                $error = 'Bitte einen Benutzernamen eingeben.';
            } elseif (!$id && $password === '') {
                $error = 'Bitte für neue Admins ein Passwort setzen.';
            } else {
                try {
                    if ($id) {
                        if ($id === (int)($_SESSION['admin_id'] ?? 0)) {
                            $active = 1;
                        }
                        $params = [$username, $displayName, $active, $mustChangePassword, $id];
                        db()->prepare('UPDATE admins SET username=?, display_name=?, active=?, must_change_password=? WHERE id=?')->execute($params);
                        if ($password !== '') {
                            db()->prepare('UPDATE admins SET password_hash=?, failed_login_count=0, locked_until=NULL WHERE id=?')
                                ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
                        }
                    } else {
                        db()->prepare('INSERT INTO admins(username,display_name,password_hash,must_change_password,active) VALUES(?,?,?,?,?)')
                            ->execute([$username, $displayName, password_hash($password, PASSWORD_DEFAULT), $mustChangePassword, $active]);
                    }
                    redirect('/admin/users');
                } catch (PDOException $e) {
                    $error = 'Speichern fehlgeschlagen. Ist der Benutzername bereits vergeben?';
                    app_log('Admin user save failed', ['message' => $e->getMessage()]);
                }
            }
        }

        render_page($id ? 'Admin bearbeiten' : 'Admin anlegen', 'admin/user-form', compact('user', 'error'), true);
    }

    public function invitations(): void
    {
        require_admin();
        $message = '';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (isset($_POST['create_invitation'])) {
                $name = trim($_POST['name'] ?? '');
                $email = strtolower(trim($_POST['email'] ?? ''));
                $expiresAt = str_replace('T', ' ', trim($_POST['expires_at'] ?? ''));
                if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $message = 'Bitte Name und gültige E-Mail-Adresse eingeben.';
                } else {
                    TestService::createInvitation($name, $email, $expiresAt, $_SESSION['admin_id'] ?? null);
                    redirect('/admin/invitations');
                }
            }
            if (isset($_POST['delete_invitation'])) {
                db()->prepare('DELETE FROM invitations WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
                redirect('/admin/invitations');
            }
        }

        db()->exec("UPDATE invitations SET status='expired' WHERE status='open' AND expires_at IS NOT NULL AND expires_at < CURRENT_TIMESTAMP");
        $rows = db()->query('SELECT * FROM invitations ORDER BY created_at DESC')->fetchAll();
        $baseUrl = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . url('/');

        render_page('Einladungen', 'admin/invitations', compact('rows', 'message', 'baseUrl'), true);
    }

    public function userDelete(): void
    {
        require_admin();
        $id = (int)($_POST['id'] ?? 0);
        if ($id && $id !== (int)($_SESSION['admin_id'] ?? 0)) {
            db()->prepare('DELETE FROM admins WHERE id=?')->execute([$id]);
        }
        redirect('/admin/users');
    }

    public function userUnlock(): void
    {
        require_admin();
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            db()->prepare('UPDATE admins SET failed_login_count=0, locked_until=NULL WHERE id=?')->execute([$id]);
        }
        redirect('/admin/users');
    }

    public function maintenance(): void
    {
        require_admin();
        $message = '';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['clear_log'])) {
            if (is_file(LOG_PATH)) {
                file_put_contents(LOG_PATH, '');
            }
            app_log('Application log cleared', ['admin_id' => $_SESSION['admin_id'] ?? null]);
            $message = 'Logdatei wurde geleert.';
        }

        $backupDir = BASE_PATH . '/storage/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0775, true);
        }
        $backups = glob($backupDir . '/*.sqlite') ?: [];
        usort($backups, fn($a, $b) => filemtime($b) <=> filemtime($a));

        $logLines = [];
        if (is_file(LOG_PATH)) {
            $lines = file(LOG_PATH, FILE_IGNORE_NEW_LINES) ?: [];
            $logLines = array_slice($lines, -200);
        }

        render_page('Wartung', 'admin/maintenance', [
            'message' => $message,
            'backups' => $backups,
            'logLines' => $logLines,
            'logPath' => LOG_PATH,
            'dbPath' => DB_PATH,
        ], true);
    }

    public function databaseBackup(): void
    {
        require_admin();
        $backupDir = BASE_PATH . '/storage/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0775, true);
        }

        $backup = $backupDir . '/app-' . date('Ymd-His') . '.sqlite';
        copy(DB_PATH, $backup);
        app_log('Database backup created', ['file' => basename($backup)]);
        $this->sendFile($backup, basename($backup), 'application/vnd.sqlite3');
    }

    public function questionsExport(): void
    {
        require_admin();
        $rows = db()->query('SELECT * FROM questions ORDER BY sort_order,id')->fetchAll();
        $items = [];
        foreach ($rows as $row) {
            $row['options'] = json_decode($row['options'] ?: '[]', true) ?: [];
            $row['correct_answers'] = json_decode($row['correct_answers'] ?: '[]', true) ?: [];
            $items[] = $row;
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="fragen-export-' . date('Ymd-His') . '.json"');
        echo json_encode([
            'meta' => [
                'exported_at' => date('c'),
                'question_count' => count($items),
                'version' => setting('question_catalog_version', 'initial'),
            ],
            'questions' => $items,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function logDownload(): void
    {
        require_admin();
        if (!is_file(LOG_PATH)) {
            file_put_contents(LOG_PATH, '');
        }
        $this->sendFile(LOG_PATH, 'app-log-' . date('Ymd-His') . '.log', 'text/plain; charset=utf-8');
    }

    private function attemptFilter(): array
    {
        $where = [];
        $params = [];

        if (($q = trim($_GET['q'] ?? '')) !== '') {
            $where[] = '(name LIKE ? OR email LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        if (($status = trim($_GET['status'] ?? '')) !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if (($decision = trim($_GET['decision'] ?? '')) !== '') {
            $where[] = 'review_decision = ?';
            $params[] = $decision;
        }
        if (($from = trim($_GET['from'] ?? '')) !== '') {
            $where[] = 'date(started_at) >= date(?)';
            $params[] = $from;
        }
        if (($to = trim($_GET['to'] ?? '')) !== '') {
            $where[] = 'date(started_at) <= date(?)';
            $params[] = $to;
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    private function reviewDecisions(): array
    {
        return [
            'open' => 'offen',
            'shortlist' => 'engere Auswahl',
            'invite' => 'einladen',
            'hold' => 'zurückstellen',
            'reject' => 'absagen',
        ];
    }

    private function sendFile(string $path, string $downloadName, string $contentType): void
    {
        if (!is_file($path)) {
            http_response_code(404);
            exit('Datei nicht gefunden');
        }
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}
