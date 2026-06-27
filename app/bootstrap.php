<?php
session_start();
define('BASE_PATH', dirname(__DIR__));
define('DB_PATH', BASE_PATH . '/storage/app.sqlite');
define('LOG_PATH', BASE_PATH . '/storage/logs/app.log');

function app_log(string $message, array $context = []): void {
    $dir = dirname(LOG_PATH);
    if (!is_dir($dir)) { mkdir($dir, 0775, true); }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($context) $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
    file_put_contents(LOG_PATH, $line . PHP_EOL, FILE_APPEND);
}

function app_debug(): bool {
    try {
        return setting('debug_mode', '0') === '1';
    } catch (Throwable) {
        return false;
    }
}

function handle_exception(Throwable $e): void {
    app_log('Uncaught exception', ['type' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    if (!headers_sent()) http_response_code(500);
    $message = app_debug() ? $e->getMessage() : 'Es ist ein interner Fehler aufgetreten. Bitte versuchen Sie es erneut oder informieren Sie die Administration.';
    try {
        render_page('Fehler', 'error', ['message' => $message]);
    } catch (Throwable) {
        echo '<h1>Fehler</h1><p>' . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
    }
    exit;
}

function handle_error(int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
}

set_error_handler('handle_error');
set_exception_handler('handle_exception');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        if (!is_dir(dirname(DB_PATH))) { mkdir(dirname(DB_PATH), 0775, true); }
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function app_base(): string {
    // Unter Apache/XAMPP läuft die Anwendung häufig in einem Unterordner
    // wie /autista-bewerbertest/public. URLs werden deshalb bewusst über
    // index.php erzeugt und benötigen keine Rewrite-Regeln/.htaccess.
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $dir = dirname($script);
    if ($dir === '/' || $dir === '.' || $dir === '\\') return '';
    return rtrim($dir, '/');
}
function url(string $path): string {
    return app_base() . '/index.php/' . ltrim($path, '/');
}
function current_path(): string {
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $base = app_base();

    // Variante 1: /unterordner/public/index.php/test
    if (str_starts_with($uriPath, $script)) {
        $path = substr($uriPath, strlen($script));
        return $path === '' ? '/' : $path;
    }

    // Variante 2: /unterordner/public/test, falls Rewrite aktiv ist
    if ($base !== '' && str_starts_with($uriPath, $base)) {
        $path = substr($uriPath, strlen($base));
        return $path === '' ? '/' : $path;
    }

    return $uriPath === '' ? '/' : $uriPath;
}
function redirect(string $path): never { header('Location: ' . url($path)); exit; }
function is_admin(): bool { return !empty($_SESSION['admin_id']); }
function require_admin(): void {
    if (!is_admin()) redirect('/admin/login');
    $timeout = max(1, (int)setting('admin_session_timeout_minutes', '30')) * 60;
    if (!empty($_SESSION['admin_last_activity']) && time() - (int)$_SESSION['admin_last_activity'] > $timeout) {
        session_destroy();
        redirect('/admin/login');
    }
    $_SESSION['admin_last_activity'] = time();
    if (!empty($_SESSION['admin_must_change_password']) && current_path() !== '/admin/settings' && current_path() !== '/admin/logout') {
        redirect('/admin/settings');
    }
}
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function check_csrf(): void { if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) { http_response_code(400); exit('Ungültiges CSRF-Token'); } }

function setting(string $key, $default = null) {
    $stmt = db()->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['value'] : $default;
}
function set_setting(string $key, string $value): void {
    $stmt = db()->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value');
    $stmt->execute([$key, $value]);
}
function view(string $name, array $data = []): string {
    $file = BASE_PATH . '/app/Views/' . ltrim($name, '/');
    if (!str_ends_with($file, '.php')) {
        $file .= '.php';
    }
    if (!is_file($file)) {
        throw new RuntimeException('View nicht gefunden: ' . $name);
    }
    extract($data, EXTR_SKIP);
    ob_start();
    require $file;
    return ob_get_clean();
}
function render_page(string $title, string $view, array $data = [], bool $admin = false): void {
    echo view('layout', [
        'title' => $title,
        'content' => view($view, $data),
        'admin' => $admin,
    ]);
}
function layout(string $title, string $content, bool $admin=false): void {
    echo view('layout', compact('title', 'content', 'admin'));
}
function score_question(array $q, array $given): float {
    $correct = json_decode($q['correct_answers'] ?: '[]', true) ?: [];
    $points = (float)$q['points'];
    if ($q['type'] === 'matching') {
        ksort($correct);
        ksort($given);
        if (!$correct) return 0.0;
        $hits = 0;
        foreach ($correct as $key => $value) {
            if (($given[$key] ?? null) === $value) $hits++;
        }
        return round($points * ($hits / count($correct)), 2);
    }
    if ($q['type'] === 'ordering') {
        ksort($correct);
        ksort($given);
        if (!$correct) return 0.0;
        $hits = 0;
        foreach ($correct as $key => $value) {
            if (($given[$key] ?? null) === $value) $hits++;
        }
        return round($points * ($hits / count($correct)), 2);
    }
    if ($q['type'] === 'multiple') {
        $correct = array_values($correct);
        $given = array_values($given);
        if (!$correct) return 0.0;
        $hits = count(array_intersect($given, $correct));
        $wrong = count(array_diff($given, $correct));
        $ratio = max(0, ($hits - $wrong) / count($correct));
        return round($points * min(1, $ratio), 2);
    }
    sort($correct); sort($given);
    return $correct == $given ? $points : 0.0;
}
function answer_label(array $q, array $vals): string {
    $options = json_decode($q['options'] ?: '[]', true) ?: [];
    if ($q['type'] === 'matching') {
        $left = $options['left'] ?? [];
        $answers = $options['answers'] ?? [];
        $leftMap = [];
        $answerMap = [];
        foreach ($left as $item) $leftMap[$item['key']] = $item['text'];
        foreach ($answers as $item) $answerMap[$item['key']] = $item['text'];

        $parts = [];
        foreach ($vals as $leftKey => $answerKey) {
            $parts[] = ($leftMap[$leftKey] ?? $leftKey) . ' → ' . ($answerMap[$answerKey] ?? $answerKey);
        }
        return implode('; ', $parts);
    }
    if ($q['type'] === 'ordering') {
        $map = [];
        foreach ($options as $o) $map[$o['key']] = $o['text'];
        $parts = [];
        foreach ($vals as $position => $key) {
            $parts[] = ((int)$position + 1) . '. ' . ($map[$key] ?? $key);
        }
        return implode('; ', $parts);
    }
    $map = [];
    foreach ($options as $o) $map[$o['key']] = $o['text'];
    return implode('; ', array_map(fn($v)=>$map[$v] ?? $v, $vals));
}
