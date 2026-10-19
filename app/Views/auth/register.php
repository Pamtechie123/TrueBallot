<section class="card narrow">
    <h1>Create your voter account</h1>
    <p class="lead">Fill in your details. An administrator will review your registration before you can vote.</p>

    <form method="post" action="<?= e(url('/register')) ?>" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>

        <div class="field">
            <label for="full_name">Full name</label>
            <input type="text" id="full_name" name="full_name" value="<?= old('full_name') ?>" maxlength="150" autocomplete="name" required <?= field_attrs('full_name') ?>>
            <?= field_error('full_name') ?>
        </div>

        <div class="field">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" value="<?= old('email') ?>" maxlength="150" autocomplete="email" required <?= field_attrs('email') ?>>
            <?= field_error('email') ?>
        </div>

        <div class="field-row">
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" aria-describedby="password-hint" required>
                <p class="hint" id="password-hint">At least 8 characters.</p>
                <?= field_error('password') ?>
            </div>
            <div class="field">
                <label for="password_confirm">Confirm password</label>
                <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required <?= field_attrs('password_confirm') ?>>
                <?= field_error('password_confirm') ?>
            </div>
        </div>

        <div class="field-row">
            <div class="field">
                <label for="age">Age</label>
                <input type="number" id="age" name="age" value="<?= old('age') ?>" min="18" max="120" inputmode="numeric" required aria-describedby="age-hint" <?= has_error('age') ? 'aria-invalid="true"' : '' ?>>
                <p class="hint" id="age-hint">You must be 18 or older.</p>
                <?= field_error('age') ?>
            </div>
            <div class="field">
                <label for="state">State</label>
                <input type="text" id="state" name="state" value="<?= old('state') ?>" maxlength="100" autocomplete="address-level1" required <?= field_attrs('state') ?>>
                <?= field_error('state') ?>
            </div>
        </div>

        <div class="field-row">
            <div class="field">
                <label for="sex">Sex</label>
                <select id="sex" name="sex" required <?= field_attrs('sex') ?>>
                    <?php foreach (['male', 'female', 'other', 'prefer_not_to_say'] as $option): ?>
                        <option value="<?= e($option) ?>" <?= old('sex', 'prefer_not_to_say') === $option ? 'selected' : '' ?>><?= e(label($option)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error('sex') ?>
            </div>
            <div class="field">
                <label for="disability_status">Do you have a disability?</label>
                <select id="disability_status" name="disability_status" required aria-describedby="disability-hint" <?= has_error('disability_status') ? 'aria-invalid="true"' : '' ?>>
                    <?php foreach (['yes', 'no', 'prefer_not_to_say'] as $option): ?>
                        <option value="<?= e($option) ?>" <?= old('disability_status', 'prefer_not_to_say') === $option ? 'selected' : '' ?>><?= e(label($option)) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="hint" id="disability-hint">Used only to provide accessible voting support.</p>
                <?= field_error('disability_status') ?>
            </div>
        </div>

        <div class="field">
            <label for="photo">Your photo <span class="muted">(optional)</span></label>
            <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" data-max-bytes="<?= \App\Services\PhotoStorage::MAX_BYTES ?>" <?= field_attrs('photo') ?>>
            <p class="hint">A clear photo of your face. JPG, PNG or WebP, up to 2 MB. It appears on your voter card.</p>
            <?= field_error('photo') ?>
        </div>

        <button type="submit" class="btn">Register</button>
        <p class="form-footer">Already registered? <a href="<?= e(url('/login')) ?>">Log in</a></p>
    </form>
</section>
