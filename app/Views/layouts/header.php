<?php
use App\Core\Session;
use App\Interfaces\RoleInterface;

/** @var string $pageTitle */
/** @var ?\App\Models\User $currentUser */
$success = Session::flashed('success');
$error   = Session::flashed('error');
$isAdminShell = $currentUser !== null && $currentUser->canAccess(RoleInterface::AREA_ADMIN);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle !== '' ? $pageTitle . ' | TrueBallot' : 'TrueBallot') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body class="<?= $isAdminShell ? 'is-admin' : '' ?>">
<a class="skip-link" href="#main">Skip to main content</a>

<?php if ($isAdminShell):
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $nav = ['Overview' => $currentUser->homePath(), 'Voters' => '/admin/voters', 'Elections' => '/admin/elections', 'Candidates' => '/admin/candidates', 'Results' => '/results', 'My profile' => '/profile'];
    ?>
<div class="admin-shell">
    <aside class="sidebar" aria-label="Admin navigation">
        <a class="brand" href="<?= e(url('/')) ?>">TrueBallot <span>Admin</span></a>
        <?php foreach ($nav as $label => $href): ?>
            <a href="<?= e(url($href)) ?>"<?= str_ends_with($path, $href) ? ' class="active"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
        <div class="sidebar-foot">
            <p><?= e($currentUser->fullName()) ?></p>
            <form class="inline-form" method="post" action="<?= e(url('/logout')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="link-button">Log out</button>
            </form>
        </div>
    </aside>
    <div class="admin-main">
<?php else: ?>
<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="<?= e(url('/')) ?>">TrueBallot</a>

        <nav aria-label="Main navigation">
            <?php if ($currentUser !== null): ?>
                <a href="<?= e(url($currentUser->homePath())) ?>">Dashboard</a>
                <?php if ($currentUser->canAccess(RoleInterface::AREA_VOTING)): ?>
                    <a href="<?= e(url('/vote')) ?>">Vote</a>
                <?php endif; ?>
                <a href="<?= e(url('/results')) ?>">Results</a>
                <a href="<?= e(url('/profile')) ?>">My profile</a>
                <form class="inline-form" method="post" action="<?= e(url('/logout')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="link-button">Log out (<?= e($currentUser->fullName()) ?>)</button>
                </form>
            <?php else: ?>
                <a href="<?= e(url('/register')) ?>">Register</a>
                <a class="btn btn-small" href="<?= e(url('/login')) ?>">Log in</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<?php endif; ?>

<main id="main" class="container">
    <?php if ($success): ?>
        <div class="alert alert-success" role="status"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>