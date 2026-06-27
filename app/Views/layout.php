<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isAdminArea = str_starts_with($currentPath, '/admin');
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root{--page:#f4f6f8;--panel:#fff;--ink:#1f2933;--muted:#65758b;--line:#d9e0e8;--brand:#245b72;--brand-strong:#17465a;--accent:#6a7f2b;--danger:#b42318;--ok:#257a4f;--warn:#9a6700}
        *{letter-spacing:0}
        body{background:var(--page);color:var(--ink);font-size:15px}
        a{color:var(--brand)}
        a:hover{color:var(--brand-strong)}
        .container-narrow{max-width:1120px}
        .app-shell{padding-top:1.25rem;padding-bottom:2rem}
        .app-header{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:.85rem 1rem;box-shadow:0 1px 2px rgba(16,24,40,.04)}
        .app-title{font-size:1.35rem;font-weight:700;line-height:1.2;margin:0}
        .nav{gap:.25rem;justify-content:flex-end}
        .nav a{display:inline-flex;align-items:center;min-height:34px;padding:.35rem .6rem;border-radius:6px;color:#405164;text-decoration:none}
        .nav a:hover,.nav a.active{background:#eaf1f4;color:var(--brand-strong)}
        .card{border:1px solid var(--line);border-radius:8px;box-shadow:0 1px 2px rgba(16,24,40,.05)}
        .card-body{padding:1.15rem}
        .form-control,.form-select{border-color:#cfd8e3;border-radius:6px}
        .form-control:focus,.form-select:focus{border-color:#6f9cb0;box-shadow:0 0 0 .18rem rgba(36,91,114,.15)}
        .btn{border-radius:6px;font-weight:600}
        .btn-primary{background:var(--brand);border-color:var(--brand)}
        .btn-primary:hover,.btn-primary:focus{background:var(--brand-strong);border-color:var(--brand-strong)}
        .btn-success{background:var(--ok);border-color:var(--ok)}
        .table{--bs-table-striped-bg:#f8fafc}
        .table th{font-size:.78rem;text-transform:uppercase;color:#526174;background:#f2f5f8;white-space:nowrap}
        .table td{vertical-align:middle}
        .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
        .small-muted{font-size:.9rem;color:var(--muted)}
        .hero-panel{display:grid;grid-template-columns:minmax(0,1fr) minmax(300px,420px);gap:1rem;align-items:start}
        .intro-panel{padding:1.25rem 0}
        .intro-kicker{font-size:.78rem;text-transform:uppercase;font-weight:700;color:var(--brand);margin-bottom:.35rem}
        .stat-card h2{font-size:2rem;margin:0;color:var(--brand-strong)}
        .stat-card p{margin:.2rem 0 0;color:var(--muted)}
        .question-box{border-left:4px solid var(--brand)}
        .timer{position:sticky;top:.75rem;z-index:20;border-color:#bad5df;background:#eef7fa}
        .answer-option{display:flex;gap:.7rem;align-items:flex-start;border:1px solid var(--line);border-radius:8px;padding:.75rem .8rem;margin-bottom:.55rem;background:#fff}
        .answer-option:hover{border-color:#8db4c2;background:#fbfdfe}
        .answer-option:has(.form-check-input:checked){border-color:var(--brand);background:#eef7fa}
        .answer-option .form-check-input{margin-top:.2rem;flex:0 0 auto}
        .answer-ok{background:#e9f8ee}.answer-bad{background:#fdeeee}
        .status-pill{display:inline-flex;align-items:center;border-radius:999px;padding:.18rem .5rem;font-size:.78rem;font-weight:700}
        .status-started{background:#eef2f6;color:#405164}.status-submitted{background:#e8f5ee;color:#176344}.status-expired{background:#fff2d6;color:#7a4b00}
        .type-badge{background:#edf2f7;color:#405164;border:1px solid #d9e2ec}
        .table-actions{display:flex;gap:.35rem;flex-wrap:wrap;justify-content:flex-end}
        @media(max-width:760px){.hero-panel{grid-template-columns:1fr}.app-header{align-items:flex-start!important}.nav{justify-content:flex-start;margin-top:.75rem}.table-responsive{font-size:.9rem}.btn{width:100%;margin-bottom:.35rem}.table-actions{justify-content:stretch}.table-actions>*{width:100%}}
        @media print{body{background:#fff}.nav,.btn,form.card,form.mb-3{display:none!important}.card{box-shadow:none;border:1px solid #ddd}}
    </style>
</head>
<body>
<div class="container container-narrow app-shell">
    <div class="app-header d-flex justify-content-between align-items-center mb-3">
        <h1 class="app-title"><?= h($title) ?></h1>
        <nav class="nav">
            <?php if (!empty($admin)): ?>
                <a class="<?= $currentPath === '/admin' ? 'active' : '' ?>" href="<?= h(url('/admin')) ?>">Dashboard</a>
                <a class="<?= str_starts_with($currentPath, '/admin/question') ? 'active' : '' ?>" href="<?= h(url('/admin/questions')) ?>">Fragen</a>
                <a class="<?= str_starts_with($currentPath, '/admin/attempt') ? 'active' : '' ?>" href="<?= h(url('/admin/attempts')) ?>">Auswertung</a>
                <a class="<?= str_starts_with($currentPath, '/admin/invitations') ? 'active' : '' ?>" href="<?= h(url('/admin/invitations')) ?>">Einladungen</a>
                <a class="<?= str_starts_with($currentPath, '/admin/settings') ? 'active' : '' ?>" href="<?= h(url('/admin/settings')) ?>">Einstellungen</a>
                <a class="<?= str_starts_with($currentPath, '/admin/users') ? 'active' : '' ?>" href="<?= h(url('/admin/users')) ?>">Admins</a>
                <a class="<?= str_starts_with($currentPath, '/admin/maintenance') ? 'active' : '' ?>" href="<?= h(url('/admin/maintenance')) ?>">Wartung</a>
                <a href="<?= h(url('/admin/logout')) ?>">Logout</a>
            <?php elseif (!$isAdminArea): ?>
                <a href="<?= h(url('/')) ?>" class="<?= $currentPath === '/' ? 'active' : '' ?>">Test</a>
                <a href="<?= h(url('/admin/login')) ?>">Admin</a>
            <?php endif; ?>
        </nav>
    </div>
    <?= $content ?>
</div>
</body>
</html>
