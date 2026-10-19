<section class="card narrow">
    <h1>Log in</h1>

    <form method="post" action="<?= e(url('/login')) ?>" novalidate>
        <?= csrf_field() ?>

        <div class="field">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" value="<?= old('email') ?>" autocomplete="email" required <?= field_attrs('email') ?>>
            <?= field_error('email') ?>
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required <?= field_attrs('password') ?>>
            <?= field_error('password') ?>
        </div>

        <button type="submit" class="btn">Log in</button>
        <p class="form-footer"><a href="<?= e(url('/forgot-password')) ?>">Forgot your password?</a></p>
        <p class="form-footer">New here? <a href="<?= e(url('/register')) ?>">Create an account</a></p>
    </form>
</section>