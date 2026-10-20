<?php
use App\Core\Session;
use App\Interfaces\RoleInterface;

/** @var string $pageTitle */
/** @var ?\App\Models\User $currentUser */
$success = Session::flashed('success');
$error   = Session::flashed('error');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle !== '' ? $pageTitle . ' | TrueBallot' : 'TrueBallot') ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>

<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="<?= e(url('/')) ?>">TrueBallot</a>

        <nav aria-label="Main navigation">
            <?php if ($currentUser !== null): ?>
                <a href="<?= e(url($currentUser->homePath())) ?>">Dashboard</a>
                <?php if ($currentUser->canAccess(RoleInterface::AREA_ADMIN)): ?>
                    <a href="<?= e(url('/admin/voters')) ?>">Voters</a>
                    <a href="<?= e(url('/admin/elections')) ?>">Elections</a>
                    <a href="<?= e(url('/admin/candidates')) ?>">Candidates</a>
                <?php elseif ($currentUser->canAccess(RoleInterface::AREA_VOTING)): ?>
                    <a href="<?= e(url('/vote')) ?>">Vote</a>
                    <a href="<?= e(url('/candidates/apply')) ?>">Apply</a>
                <?php endif; ?>
                <a href="<?= e(url('/results')) ?>">Results</a>
                                <a href="<?= e(url('/results')) ?>">Results</a>
                <a href="<?= e(url('/profile')) ?>">My profile</a>
                <form class="inline-form" method="post" action="<?= e(url('/logout')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="link-button">Log out (<?= e($currentUser->fullName()) ?>)</button>
                </form>
            <?php else: ?>
                <a href="<?= e(url('/register')) ?>">Register</a>
                <a href="<?= e(url('/login')) ?>">Log in</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main id="main" class="container">
    <?php if ($success): ?>
        <div class="alert alert-success" role="status"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>