<section class="card narrow">
    <h1>Forgot your password?</h1>
    <p class="lead">Enter the email you registered with. We will email you a link to choose a new password.</p>

    <form method="post" action="<?= e(url('/forgot-password')) ?>" novalidate>
        <?= csrf_field() ?>

        <div class="field">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" value="<?= old('email') ?>" autocomplete="email" required <?= field_attrs('email') ?>>
            <?= field_error('email') ?>
        </div>

        <button type="submit" class="btn">Send reset link</button>
        <p class="form-footer"><a href="<?= e(url('/login')) ?>">Back to log in</a></p>
    </form>
</section>