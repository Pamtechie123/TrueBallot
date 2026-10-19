<?php
/**
 * Shared look for the error pages. Does not use the database, so it still works if the database is down.
 *
 * @var int         $code
 * @var string      $title
 * @var string      $defaultMessage
 * @var string      $message
 * @var string|null $debugDetail
 */
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | TrueBallot</title>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body class="error-body">
<main id="main" class="error-box">
    <p class="error-code"><?= (int) $code ?></p>
    <h1><?= e($title) ?></h1>
    <p><?= e($message !== '' ? $message : $defaultMessage) ?></p>
    <p><a class="btn" href="<?= e(url('/')) ?>">Back to home</a></p>

    <?php if (!empty($debugDetail)): ?>
        <details class="debug">
            <summary>Technical details (debug mode only)</summary>
            <pre><?= e($debugDetail) ?></pre>
        </details>
    <?php endif; ?>
</main>
</body>
</html>
