<?php

class TestController
{
    public function home(): void
    {
        TestService::expireOpenAttempts();
        TestService::cleanupOldAttempts();
        $error = '';
        $invitation = TestService::invitationByToken(trim($_GET['token'] ?? $_POST['invite_token'] ?? ''));
        $inviteToken = $invitation['token'] ?? '';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));

            if (!empty($_POST['invite_token']) && !$invitation) {
                $error = 'Dieser Einladungslink ist ungültig, abgelaufen oder wurde bereits verwendet.';
            } elseif ($invitation && $email !== strtolower($invitation['email'])) {
                $error = 'Die E-Mail-Adresse passt nicht zur Einladung.';
            } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Bitte Name und gültige E-Mail-Adresse eingeben.';
            } else {
                $existing = db()->prepare("SELECT * FROM attempts WHERE email=? AND status IN ('started','submitted','expired')");
                $existing->execute([$email]);

                if ($existing->fetch()) {
                    $error = 'Für diese E-Mail-Adresse existiert bereits ein Test. Bitte wenden Sie sich an die Administration, falls eine erneute Teilnahme freigegeben werden soll.';
                } else {
                    try {
                        $_SESSION['attempt_id'] = TestService::createAttempt($name, $email);
                        if ($inviteToken !== '') {
                            TestService::markInvitationUsed($inviteToken, (int)$_SESSION['attempt_id']);
                        }
                        redirect('/test');
                    } catch (RuntimeException $e) {
                        $error = $e->getMessage();
                    }
                }
            }
        } elseif (isset($_GET['token']) && !$invitation) {
            $error = 'Dieser Einladungslink ist ungültig, abgelaufen oder wurde bereits verwendet.';
        }

        render_page(setting('test_title', 'Bewerbertest'), 'home', [
            'error' => $error,
            'introText' => setting('intro_text', ''),
            'privacyText' => setting('privacy_text', ''),
            'invitation' => $invitation,
        ]);
    }

    public function test(): void
    {
        $attemptId = $_SESSION['attempt_id'] ?? null;
        if (!$attemptId) {
            redirect('/');
        }

        $stmt = db()->prepare('SELECT * FROM attempts WHERE id=?');
        $stmt->execute([$attemptId]);
        $attempt = $stmt->fetch();

        if (!$attempt || in_array($attempt['status'], ['submitted', 'expired'], true)) {
            redirect('/thanks');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            TestService::finalizeAttempt((int)$attemptId, $_POST);
            redirect('/thanks');
        }

        $started = strtotime($attempt['started_at'] . ' UTC');
        $elapsed = time() - $started;
        $remaining = max(0, (int)$attempt['duration_seconds'] - $elapsed);

        if ($remaining <= 0) {
            db()->prepare("UPDATE attempts SET status='expired' WHERE id=?")->execute([(int)$attemptId]);
            redirect('/thanks');
        }

        $questions = TestService::questionsForAttempt($attempt);
        $drafts = TestService::draftAnswers((int)$attemptId);
        $groups = [];
        foreach ($questions as $q) {
            $groups[$q['category']][] = $q;
        }

        render_page('Bewerbertest', 'test', [
            'attemptId' => (int)$attemptId,
            'remaining' => $remaining,
            'groups' => $groups,
            'totalQuestions' => count($questions),
            'drafts' => $drafts,
        ]);
    }

    public function autosave(): void
    {
        $attemptId = $_SESSION['attempt_id'] ?? null;
        if (!$attemptId) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false]);
            return;
        }

        TestService::saveDraft((int)$attemptId, $_POST);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    }

    public function thanks(): void
    {
        $attempt = null;
        if (!empty($_SESSION['attempt_id'])) {
            $stmt = db()->prepare('SELECT * FROM attempts WHERE id=?');
            $stmt->execute([(int)$_SESSION['attempt_id']]);
            $attempt = $stmt->fetch();
        }
        render_page('Abgabe gespeichert', 'thanks', compact('attempt'));
    }
}
