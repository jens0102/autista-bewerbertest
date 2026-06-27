<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once BASE_PATH . '/app/Services/MigrationService.php';
require_once BASE_PATH . '/app/Services/TestService.php';
require_once BASE_PATH . '/app/Controllers/TestController.php';
require_once BASE_PATH . '/app/Controllers/AdminController.php';

TestService::installIfNeeded();
check_csrf();

$path = current_path();
$test = new TestController();
$admin = new AdminController();

match ($path) {
    '/' => $test->home(),
    '/test' => $test->test(),
    '/test/autosave' => $test->autosave(),
    '/thanks' => $test->thanks(),
    '/admin/login' => $admin->login(),
    '/admin/logout' => $admin->logout(),
    '/admin' => $admin->dashboard(),
    '/admin/questions' => $admin->questions(),
    '/admin/question/edit' => $admin->questionEdit(),
    '/admin/attempts' => $admin->attempts(),
    '/admin/attempt' => $admin->attempt(),
    '/admin/settings' => $admin->settings(),
    '/admin/users' => $admin->users(),
    '/admin/user/edit' => $admin->userEdit(),
    '/admin/user/delete' => $admin->userDelete(),
    '/admin/user/unlock' => $admin->userUnlock(),
    '/admin/invitations' => $admin->invitations(),
    '/admin/maintenance' => $admin->maintenance(),
    '/admin/backup/database' => $admin->databaseBackup(),
    '/admin/export/questions' => $admin->questionsExport(),
    '/admin/log/download' => $admin->logDownload(),
    default => (function () {
        http_response_code(404);
        render_page('404', 'not-found');
    })(),
};
