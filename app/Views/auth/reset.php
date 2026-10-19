<?php /** @var string $token */ ?>
<section class="card narrow">
    <h1>Choose a new password</h1>

    <form method="post" action="<?= e(url('/reset-password')) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <div class="field">
            <label for="password">New password</label>
            <input type="password" id="password" name="password" autocomplete="new-password" required <?= field_attrs('password') ?>>
            <p class="hint">At least 8 characters.</p>
            <?= field_error('password') ?>
        </div>

        <div class="field">
            <label for="password_confirm">Confirm new password</label>
            <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required <?= field_attrs('password_confirm') ?>>
            <?= field_error('password_confirm') ?>
        </div>

        <button type="submit" class="btn">Change password</button>
    </form>
</section>